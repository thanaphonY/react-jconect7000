import type { FastifyInstance } from 'fastify';
import { z } from 'zod';
import { requireUserId } from '../auth/requireUser.js';
import type { Queryable } from '../db.js';
import {
  buildAndSaveExport,
  createReportSchedule,
  createReportTemplate,
  deleteReportFile,
  deleteReportSchedule,
  deleteReportTemplate,
  fetchReportData,
  getReportTemplate,
  listActionTypes,
  listEntityTypes,
  listMetricKeys,
  listMetricsByDevice,
  listReportDevices,
  listReportFiles,
  listReportSchedules,
  listReportTemplates,
  listRooms,
  openReportFile,
  updateReportSchedule,
  updateReportTemplate,
  type ReportTemplateConfig,
  type RunFilters,
} from '../repositories/reports.js';

const displayColumnSchema = z.object({
  key: z.string().min(1),
  label: z.string().optional(),
});

const deviceMetricSchema = z.object({
  device_id: z.coerce.number().int().positive(),
  device_name: z.string().optional(),
  metric: z.string().min(1),
  metric_label: z.string().optional(),
});

const exportInputSchema = z.object({
  type: z.string().min(1),
  label: z.string().optional(),
  required: z.boolean().optional(),
});

const templateBodySchema = z.object({
  name: z.string().trim().min(1).max(255),
  description: z.string().optional().default(''),
  dataSource: z.enum(['device_data', 'alarm_logs', 'event_logs']).optional().default('device_data'),
  filters: z
    .object({
      time_range: z.string().optional(),
      time_interval: z.string().optional(),
      display: z.array(z.string()).optional(),
      sort_order: z.enum(['asc', 'desc']).optional(),
      selected_device_metrics: z.array(deviceMetricSchema).optional(),
      action_type: z.boolean().optional(),
      entity_type: z.boolean().optional(),
      entity: z.boolean().optional(),
      filter_time_interval: z.string().optional(),
    })
    .optional()
    .default({}),
  exportInputs: z.array(exportInputSchema).optional().default([]),
  editor: z
    .object({
      report_title: z.string().optional().default(''),
      table_layout: z.enum(['rows', 'pivot']).optional().default('rows'),
      pdf_orientation: z.enum(['portrait', 'landscape']).optional().default('portrait'),
      pdf_title: z.string().optional().default(''),
      excel_sheet_name: z.string().optional().default('Sheet1'),
      csv_delimiter: z.enum([',', ';', 'tab']).optional().default(','),
      display_columns: z.array(displayColumnSchema).optional().default([]),
    })
    .optional()
    .default({}),
});

const runFiltersSchema = z
  .object({
    device_id: z.coerce.number().int().positive().optional().nullable(),
    metric: z.string().optional().nullable(),
    room: z.string().optional().nullable(),
    date: z.string().optional().nullable(),
    month: z.string().optional().nullable(),
    year: z.string().optional().nullable(),
    start_date: z.string().optional().nullable(),
    end_date: z.string().optional().nullable(),
    action_type: z.string().optional().nullable(),
    entity_type: z.string().optional().nullable(),
  })
  .optional()
  .default({});

const scheduleBodySchema = z.object({
  templateId: z.coerce.number().int().positive(),
  name: z.string().trim().min(1).max(255),
  isActive: z.boolean().optional().default(true),
  scheduleType: z.enum(['daily', 'weekly', 'monthly']).optional().default('daily'),
  scheduleTime: z.string().optional().default('08:00'),
  scheduleWeekday: z.coerce.number().int().min(1).max(7).optional().default(1),
  scheduleDay: z.coerce.number().int().min(1).max(28).optional().default(1),
  timezone: z.string().optional().default('Asia/Bangkok'),
  exportFormat: z.enum(['pdf', 'excel', 'csv']).optional().default('pdf'),
  recipients: z.array(z.string()).optional().default([]),
});

function buildConfig(body: z.infer<typeof templateBodySchema>): ReportTemplateConfig {
  const dataSource = body.dataSource;
  let filters: Record<string, unknown>;
  if (dataSource === 'event_logs') {
    filters = {
      action_type: Boolean(body.filters.action_type),
      entity_type: Boolean(body.filters.entity_type),
      entity: Boolean(body.filters.entity),
      time_interval: body.filters.filter_time_interval || body.filters.time_interval || 'date_range',
    };
  } else {
    filters = {
      time_range: body.filters.time_range || 'last_24h',
      time_interval: body.filters.time_interval || '1h',
      display: Array.isArray(body.filters.display) && body.filters.display.length
        ? body.filters.display
        : ['min', 'max', 'average'],
      sort_order: body.filters.sort_order === 'asc' ? 'asc' : 'desc',
      selected_device_metrics: body.filters.selected_device_metrics ?? [],
    };
  }
  return {
    builder_version: 1,
    data_source: dataSource,
    filters,
    export_inputs: (body.exportInputs ?? []).map((item) => ({
      type: item.type,
      label: item.label || item.type,
      required: Boolean(item.required),
    })),
    editor: {
      report_title: body.editor.report_title ?? '',
      table_layout: body.editor.table_layout === 'pivot' ? 'pivot' : 'rows',
      pdf_orientation: body.editor.pdf_orientation === 'landscape' ? 'landscape' : 'portrait',
      pdf_title: body.editor.pdf_title ?? '',
      excel_sheet_name: body.editor.excel_sheet_name || 'Sheet1',
      csv_delimiter: [',', ';', 'tab'].includes(body.editor.csv_delimiter ?? ',')
        ? body.editor.csv_delimiter
        : ',',
      display_columns: (body.editor.display_columns ?? []).map((c) => ({
        key: c.key,
        label: c.label || c.key,
      })),
    },
    template_type: 'custom_builder',
  };
}

function sendZodError(
  reply: { code: (n: number) => { send: (b: unknown) => unknown } },
  error: z.ZodError,
) {
  return reply.code(400).send({
    error: {
      code: 'VALIDATION_ERROR',
      message: error.issues.map((i) => i.message).join('; ') || 'Invalid request',
    },
  });
}

export async function registerReportRoutes(
  app: FastifyInstance,
  db: Queryable,
  authSecret: string,
): Promise<void> {
  app.get('/report-templates', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    return reply.code(200).send({ data: await listReportTemplates(db) });
  });

  app.get('/report-templates/lookups/devices', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    return reply.code(200).send({ data: await listReportDevices(db) });
  });

  app.get('/report-templates/lookups/metrics', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    return reply.code(200).send({ data: await listMetricKeys(db) });
  });

  app.get('/report-templates/lookups/metrics-by-device/:deviceId', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const deviceId = Number((request.params as { deviceId: string }).deviceId);
    return reply.code(200).send({ data: await listMetricsByDevice(db, deviceId) });
  });

  app.get('/report-templates/lookups/rooms', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    return reply.code(200).send({ data: await listRooms(db) });
  });

  app.get('/report-templates/lookups/action-types', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    return reply.code(200).send({ data: await listActionTypes(db) });
  });

  app.get('/report-templates/lookups/entity-types', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    return reply.code(200).send({ data: await listEntityTypes(db) });
  });

  app.get('/report-templates/:id', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const template = await getReportTemplate(db, id);
    if (!template) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Template not found' } });
    }
    return reply.code(200).send({ data: template });
  });

  app.post('/report-templates', async (request, reply) => {
    const userId = requireUserId(request, reply, authSecret);
    if (userId === null) return;
    const parsed = templateBodySchema.safeParse(request.body);
    if (!parsed.success) return sendZodError(reply, parsed.error);
    const template = await createReportTemplate(db, {
      name: parsed.data.name,
      description: parsed.data.description,
      config: buildConfig(parsed.data),
      createdBy: userId,
    });
    return reply.code(201).send({ data: template });
  });

  app.put('/report-templates/:id', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const parsed = templateBodySchema.safeParse(request.body);
    if (!parsed.success) return sendZodError(reply, parsed.error);
    const template = await updateReportTemplate(db, id, {
      name: parsed.data.name,
      description: parsed.data.description,
      config: buildConfig(parsed.data),
    });
    if (!template) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Template not found' } });
    }
    return reply.code(200).send({ data: template });
  });

  app.delete('/report-templates/:id', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const ok = await deleteReportTemplate(db, id);
    if (!ok) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Template not found' } });
    }
    return reply.code(200).send({ data: { ok: true } });
  });

  app.post('/report-templates/:id/fetch', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const template = await getReportTemplate(db, id);
    if (!template) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Template not found' } });
    }
    const parsed = z.object({ filters: runFiltersSchema }).safeParse(request.body ?? {});
    if (!parsed.success) return sendZodError(reply, parsed.error);
    const result = await fetchReportData(db, template, (parsed.data.filters ?? {}) as RunFilters);
    return reply.code(200).send({
      data: {
        rows: result.rows,
        count: result.count,
        dataSource: result.dataSource,
      },
    });
  });

  app.post('/report-templates/:id/export', async (request, reply) => {
    const userId = requireUserId(request, reply, authSecret);
    if (userId === null) return;
    const id = Number((request.params as { id: string }).id);
    const template = await getReportTemplate(db, id);
    if (!template) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Template not found' } });
    }
    const parsed = z
      .object({
        filters: runFiltersSchema,
        format: z.enum(['pdf', 'excel', 'csv']).optional().default('csv'),
        saveToFileManager: z.boolean().optional().default(true),
        preview: z.boolean().optional().default(false),
      })
      .safeParse(request.body ?? {});
    if (!parsed.success) return sendZodError(reply, parsed.error);

    const result = await buildAndSaveExport(
      db,
      template,
      (parsed.data.filters ?? {}) as RunFilters,
      parsed.data.format,
      {
        exportedBy: parsed.data.saveToFileManager ? userId : null,
        persist: parsed.data.saveToFileManager,
      },
    );

    if (!result.success) {
      return reply.code(400).send({
        error: { code: 'EXPORT_FAILED', message: result.message },
      });
    }

    if (!parsed.data.saveToFileManager && result.buffer) {
      const contentType =
        parsed.data.format === 'pdf'
          ? 'application/pdf'
          : parsed.data.format === 'excel'
            ? 'application/vnd.ms-excel'
            : 'text/csv; charset=utf-8';
      const disposition = parsed.data.preview && parsed.data.format === 'pdf' ? 'inline' : 'attachment';
      return reply
        .code(200)
        .header('Content-Type', contentType)
        .header(
          'Content-Disposition',
          `${disposition}; filename="${result.fileName.replaceAll('"', '')}"`,
        )
        .send(result.buffer);
    }

    return reply.code(200).send({
      data: {
        fileId: result.fileId,
        fileName: result.fileName,
        downloadUrl: result.downloadUrl,
      },
    });
  });

  app.get('/report-schedules', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    return reply.code(200).send({ data: await listReportSchedules(db) });
  });

  app.post('/report-schedules', async (request, reply) => {
    const userId = requireUserId(request, reply, authSecret);
    if (userId === null) return;
    const parsed = scheduleBodySchema.safeParse(request.body);
    if (!parsed.success) return sendZodError(reply, parsed.error);
    const template = await getReportTemplate(db, parsed.data.templateId);
    if (!template) {
      return reply.code(400).send({
        error: { code: 'VALIDATION_ERROR', message: 'Template not found' },
      });
    }
    const schedule = await createReportSchedule(db, {
      templateId: parsed.data.templateId,
      name: parsed.data.name,
      isActive: parsed.data.isActive,
      scheduleType: parsed.data.scheduleType,
      scheduleConfig: {
        time: parsed.data.scheduleTime,
        weekday: parsed.data.scheduleWeekday,
        day: parsed.data.scheduleDay,
      },
      timezone: parsed.data.timezone,
      exportFormat: parsed.data.exportFormat,
      recipients: parsed.data.recipients,
      createdBy: userId,
    });
    return reply.code(201).send({ data: schedule });
  });

  app.put('/report-schedules/:id', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const parsed = scheduleBodySchema.safeParse(request.body);
    if (!parsed.success) return sendZodError(reply, parsed.error);
    const schedule = await updateReportSchedule(db, id, {
      templateId: parsed.data.templateId,
      name: parsed.data.name,
      isActive: parsed.data.isActive,
      scheduleType: parsed.data.scheduleType,
      scheduleConfig: {
        time: parsed.data.scheduleTime,
        weekday: parsed.data.scheduleWeekday,
        day: parsed.data.scheduleDay,
      },
      timezone: parsed.data.timezone,
      exportFormat: parsed.data.exportFormat,
      recipients: parsed.data.recipients,
    });
    if (!schedule) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Schedule not found' } });
    }
    return reply.code(200).send({ data: schedule });
  });

  app.delete('/report-schedules/:id', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const ok = await deleteReportSchedule(db, id);
    if (!ok) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Schedule not found' } });
    }
    return reply.code(200).send({ data: { ok: true } });
  });

  app.get('/report-files', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const query = request.query as {
      page?: string;
      pageSize?: string;
      templateId?: string;
      format?: string;
      dateFrom?: string;
      dateTo?: string;
    };
    const page = Math.max(1, Number(query.page ?? 1) || 1);
    const pageSize = Math.min(100, Math.max(1, Number(query.pageSize ?? 15) || 15));
    const data = await listReportFiles(db, page, pageSize, {
      templateId: query.templateId ? Number(query.templateId) : null,
      format: query.format || null,
      dateFrom: query.dateFrom || null,
      dateTo: query.dateTo || null,
    });
    return reply.code(200).send({ data: { ...data, page, pageSize } });
  });

  app.get('/report-files/:id', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const file = await openReportFile(db, id);
    if (!file) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'File not found' } });
    }
    return reply
      .header('Content-Disposition', `attachment; filename="${file.fileName.replaceAll('"', '')}"`)
      .send(file.stream);
  });

  app.delete('/report-files/:id', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const ok = await deleteReportFile(db, id);
    if (!ok) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'File not found' } });
    }
    return reply.code(200).send({ data: { ok: true } });
  });
}
