import type { FastifyInstance } from 'fastify';
import { requireUserId } from '../auth/requireUser.js';
import type { Queryable } from '../db.js';
import { listAlarmLogsFiltered, type AlarmLogFilters } from '../repositories/alarms.js';
import { listEventLogs } from '../repositories/logs.js';

function pageQuery(query: { page?: string; pageSize?: string }): { page: number; pageSize: number } {
  const page = Math.max(1, Number(query.page ?? 1) || 1);
  const pageSize = Math.min(100, Math.max(1, Number(query.pageSize ?? 10) || 10));
  return { page, pageSize };
}

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

export async function registerLogRoutes(
  app: FastifyInstance,
  db: Queryable,
  authSecret: string,
): Promise<void> {
  app.get('/alarm-logs', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const query = request.query as Record<string, string | undefined>;
    const { page, pageSize } = pageQuery(query);
    const data = await listAlarmLogsFiltered(db, page, pageSize, filtersFromQuery(query));
    return reply.code(200).send({
      data: {
        logs: data.logs,
        total: data.total,
        totalPages: Math.max(1, Math.ceil(data.total / pageSize) || 1),
        page,
        pageSize,
      },
    });
  });

  app.get('/event-logs', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const { page, pageSize } = pageQuery(request.query as { page?: string; pageSize?: string });
    const data = await listEventLogs(db, page, pageSize);
    return reply.code(200).send({ data: { ...data, page, pageSize } });
  });
}
