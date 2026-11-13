import type { FastifyInstance } from 'fastify';
import { z } from 'zod';
import { resolveActor } from '../auth/actor.js';
import { requireUserId } from '../auth/requireUser.js';
import type { Queryable } from '../db.js';
import {
  acknowledgeAlarmLog,
  acknowledgeAlarmState,
  alarmLogsPeriodLabel,
  buildAlarmLogsCsv,
  buildAlarmLogsExcelXlsx,
  buildAlarmLogsPdf,
  bulkAcknowledgeAlarmLogs,
  bulkResolveAlarmLogs,
  getAlarmLogDetail,
  getAlarmLogFilterOptions,
  getAlarmLogStatistics,
  listActiveAlarms,
  listAlarmLogsForExport,
  resolveAlarmLog,
  type AlarmLogFilters,
} from '../repositories/alarms.js';

function filtersFromQuery(query: Record<string, string | undefined>): AlarmLogFilters {
  return {
    range: query.range ?? 'last_30_days',
    startDate: query.startDate || query.startRange || null,
    endDate: query.endDate || query.endRange || null,
    deviceFilter: query.device_filter ?? query.deviceFilter ?? null,
    severityFilter: query.severity_filter ?? query.severityFilter ?? null,
    statusFilter: query.status_filter ?? query.statusFilter ?? null,
    search: query.search ?? null,
  };
}

function exportFilename(prefix: string, ext: string): string {
  const stamp = new Date()
    .toISOString()
    .replace(/[:.]/g, '-')
    .replace('T', '_')
    .slice(0, 19);
  return `${prefix}_${stamp}.${ext}`;
}

const noteBody = z.object({
  note: z.string().optional().nullable(),
});

const resolveBody = z.object({
  resolution_note: z.string().min(1),
});

const bulkAckBody = z.object({
  alarm_ids: z.array(z.coerce.number().int().positive()).min(1),
  note: z.string().optional().nullable(),
});

const bulkResolveBody = z.object({
  alarm_ids: z.array(z.coerce.number().int().positive()).min(1),
  resolution_note: z.string().min(1),
});

const ackIdBody = z.object({
  alarm_id: z.coerce.number().int().positive(),
  note: z.string().optional().nullable(),
});

const resolveIdBody = z.object({
  alarm_id: z.coerce.number().int().positive(),
  resolution_note: z.string().min(1),
});

export async function registerAlarmRoutes(
  app: FastifyInstance,
  db: Queryable,
  authSecret: string,
): Promise<void> {
  app.get('/alarms/active', async (request, reply) => {
    const userId = requireUserId(request, reply, authSecret);
    if (userId === null) {
      return;
    }
    return reply.code(200).send({ data: await listActiveAlarms(db) });
  });

  app.post('/alarms/active/:deviceId/:metricKey/acknowledge', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) {
      return;
    }
    const params = request.params as { deviceId: string; metricKey: string };
    const deviceId = Number(params.deviceId);
    if (!Number.isFinite(deviceId) || !params.metricKey) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'deviceId and metricKey are required' },
      });
    }
    const ok = await acknowledgeAlarmState(
      db,
      deviceId,
      decodeURIComponent(params.metricKey),
      actor.user.name || 'admin',
    );
    if (!ok) {
      return reply.code(404).send({
        error: { code: 'NOT_FOUND', message: 'Alarm state not found' },
      });
    }
    return reply.code(200).send({ data: { status: 'success' } });
  });

  app.get('/alarm-logs/statistics', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) {
      return;
    }
    return reply.code(200).send({ data: await getAlarmLogStatistics(db) });
  });

  app.get('/alarm-logs/filters', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) {
      return;
    }
    return reply.code(200).send({ data: await getAlarmLogFilterOptions(db) });
  });

  app.get('/alarm-logs/export-preview', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) {
      return;
    }
    const query = request.query as Record<string, string | undefined>;
    const filters = filtersFromQuery(query);
    const logs = await listAlarmLogsForExport(db, filters);
    return reply.code(200).send({
      data: {
        logs,
        filters,
        stats: {
          totalRecords: logs.length,
          activeAlarms: logs.filter((l) => l.status === 'active').length,
          severityLevels: new Set(logs.map((l) => l.severity).filter(Boolean)).size,
          devices: new Set(logs.map((l) => l.deviceName).filter(Boolean)).size,
        },
      },
    });
  });

  app.get('/alarm-logs/export', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) {
      return;
    }
    const query = request.query as Record<string, string | undefined>;
    const format = (query.format || 'csv').toLowerCase();
    const filters = filtersFromQuery(query);
    const logs = await listAlarmLogsForExport(db, filters);
    const download = query.download === '1' || query.download === 'true';

    if (format === 'csv') {
      const body = buildAlarmLogsCsv(logs);
      reply.header('Content-Type', 'text/csv; charset=utf-8');
      reply.header('Content-Disposition', `attachment; filename="${exportFilename('alarm_logs', 'csv')}"`);
      return reply.send(body);
    }
    if (format === 'excel') {
      const body = await buildAlarmLogsExcelXlsx(logs);
      reply.header(
        'Content-Type',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      );
      reply.header(
        'Content-Disposition',
        `attachment; filename="${exportFilename('alarm_logs', 'xlsx')}"`,
      );
      return reply.send(body);
    }
    if (format === 'pdf') {
      const pdf = await buildAlarmLogsPdf(logs, alarmLogsPeriodLabel(filters));
      reply.header('Content-Type', 'application/pdf');
      reply.header(
        'Content-Disposition',
        `${download ? 'attachment' : 'inline'}; filename="${exportFilename('alarm_logs', 'pdf')}"`,
      );
      return reply.send(pdf);
    }
    return reply.code(400).send({
      error: { code: 'INVALID_INPUT', message: 'format must be csv, excel, or pdf' },
    });
  });

  app.get('/alarm-logs/:id', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) {
      return;
    }
    const id = Number((request.params as { id: string }).id);
    if (!Number.isFinite(id)) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Invalid alarm id' } });
    }
    const detail = await getAlarmLogDetail(db, id);
    if (!detail) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Alarm not found' } });
    }
    return reply.code(200).send({ data: detail });
  });

  app.post('/alarm-logs/acknowledge', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) {
      return;
    }
    const parsed = ackIdBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'Alarm ID is required' },
      });
    }
    const ok = await acknowledgeAlarmLog(
      db,
      parsed.data.alarm_id,
      actor.user.id,
      parsed.data.note,
    );
    if (!ok) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Alarm not found' } });
    }
    return reply.code(200).send({
      data: { success: true, message: 'Alarm acknowledged successfully' },
    });
  });

  app.post('/alarm-logs/resolve', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) {
      return;
    }
    const parsed = resolveIdBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'Alarm ID and resolution note are required' },
      });
    }
    const ok = await resolveAlarmLog(
      db,
      parsed.data.alarm_id,
      actor.user.id,
      parsed.data.resolution_note,
    );
    if (!ok) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Alarm not found' } });
    }
    return reply.code(200).send({
      data: { success: true, message: 'Alarm resolved successfully' },
    });
  });

  app.post('/alarm-logs/:id/acknowledge', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) {
      return;
    }
    const id = Number((request.params as { id: string }).id);
    const parsed = noteBody.safeParse(request.body ?? {});
    if (!Number.isFinite(id) || !parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'Alarm ID is required' },
      });
    }
    const ok = await acknowledgeAlarmLog(db, id, actor.user.id, parsed.data.note);
    if (!ok) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Alarm not found' } });
    }
    return reply.code(200).send({
      data: { success: true, message: 'Alarm acknowledged successfully' },
    });
  });

  app.post('/alarm-logs/:id/resolve', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) {
      return;
    }
    const id = Number((request.params as { id: string }).id);
    const parsed = resolveBody.safeParse(request.body ?? {});
    if (!Number.isFinite(id) || !parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'Resolution note is required' },
      });
    }
    const ok = await resolveAlarmLog(db, id, actor.user.id, parsed.data.resolution_note);
    if (!ok) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Alarm not found' } });
    }
    return reply.code(200).send({
      data: { success: true, message: 'Alarm resolved successfully' },
    });
  });

  app.post('/alarm-logs/bulk-acknowledge', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) {
      return;
    }
    const parsed = bulkAckBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'Alarm IDs are required' },
      });
    }
    const result = await bulkAcknowledgeAlarmLogs(
      db,
      parsed.data.alarm_ids,
      actor.user.id,
      parsed.data.note,
    );
    return reply.code(200).send({
      data: {
        success: true,
        message: `Bulk acknowledge completed. Success: ${result.successCount}, Failed: ${result.failedCount}`,
        successCount: result.successCount,
        failedCount: result.failedCount,
      },
    });
  });

  app.post('/alarm-logs/bulk-resolve', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) {
      return;
    }
    const parsed = bulkResolveBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'Alarm IDs and resolution note are required' },
      });
    }
    const result = await bulkResolveAlarmLogs(
      db,
      parsed.data.alarm_ids,
      actor.user.id,
      parsed.data.resolution_note,
    );
    return reply.code(200).send({
      data: {
        success: true,
        message: `Bulk resolve completed. Success: ${result.successCount}, Failed: ${result.failedCount}`,
        successCount: result.successCount,
        failedCount: result.failedCount,
      },
    });
  });
}
