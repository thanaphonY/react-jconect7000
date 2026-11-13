import type { FastifyInstance } from 'fastify';
import { z } from 'zod';
import {
  actorCan,
  resolveActor,
  sendForbidden,
  type Actor,
} from '../auth/actor.js';
import type { Queryable } from '../db.js';
import * as control from '../repositories/control.js';

function requireControl(
  actor: Actor,
  reply: { code: (n: number) => { send: (body: unknown) => unknown } },
  kind: 'read' | 'create' | 'update' | 'delete',
): boolean {
  const method =
    kind === 'read'
      ? ['own_read', 'all_read']
      : kind === 'create'
        ? ['own_create', 'all_create']
        : kind === 'update'
          ? ['own_update', 'all_update']
          : ['own_delete', 'all_delete'];
  if (method.some((m) => actorCan(actor, 'control', m))) return true;
  void sendForbidden(reply as never, 'You do not have permission to do this.');
  return false;
}

function sendLogicError(
  reply: { code: (n: number) => { send: (body: unknown) => unknown } },
  result: { ok: false; error: string; httpStatus?: number },
) {
  const status =
    result.httpStatus && result.httpStatus >= 400 && result.httpStatus < 600
      ? result.httpStatus
      : 502;
  return reply.code(status).send({
    error: { code: 'CONTROL_LOGIC_ERROR', message: result.error },
  });
}

function parseGroupParam(query: Record<string, unknown>): {
  ungrouped: boolean;
  groupId: number;
  hasGroup: boolean;
} {
  const groupParam = query.group;
  const ungrouped = groupParam === '0' || groupParam === 'ungrouped';
  const groupId =
    !ungrouped && groupParam !== undefined && groupParam !== null && String(groupParam) !== ''
      ? Number(groupParam)
      : 0;
  return { ungrouped, groupId, hasGroup: ungrouped || groupId > 0 };
}

const groupBody = z.object({
  name: z.string().trim().min(1),
  kind: z.string().trim().default('generic'),
  description: z.string().optional().nullable().default(''),
});

const commandBody = z.object({
  name: z.string().trim().min(1),
  description: z.string().optional().nullable().default(''),
  device_id: z.coerce.number().int().positive(),
  protocol: z.string().trim().default('snmp_set'),
  write_config: z.record(z.string(), z.unknown()).optional().default({}),
  on_value: z.unknown().optional(),
  off_value: z.unknown().optional(),
  feedback_on: z.string().optional(),
  feedback_off: z.string().optional(),
  feedback_on_label: z.string().optional(),
  feedback_off_label: z.string().optional(),
  feedback_metric_key: z.string().optional().nullable().default(''),
  enabled: z.boolean().optional().default(true),
  group_id: z.coerce.number().int().positive().nullable().optional(),
});

const scheduleBody = z.object({
  command_id: z.coerce.number().int().positive(),
  name: z.string().trim().optional().default(''),
  time: z.string().optional(),
  days: z.array(z.coerce.number().int()).optional().default([]),
  cron: z.string().optional(),
  action: z.enum(['on', 'off']).optional().default('on'),
  enabled: z.boolean().optional().default(true),
});

const sequenceBody = z.object({
  name: z.string().trim().min(1),
  steps: z.array(z.record(z.string(), z.unknown())).optional().default([]),
  enabled: z.boolean().optional().default(true),
  group_id: z.coerce.number().int().positive().nullable().optional(),
  loop: z.boolean().optional().default(false),
  loop_count: z.coerce.number().int().min(0).max(9999).optional().default(0),
  loop_delay_sec: z.coerce.number().int().min(0).max(86400).optional().default(0),
});

const ruleBody = z.object({
  name: z.string().trim().min(1),
  conditions: z.record(z.string(), z.unknown()).optional().default({}),
  action: z.record(z.string(), z.unknown()).optional().default({}),
  enabled: z.boolean().optional().default(true),
  group_id: z.coerce.number().int().positive().nullable().optional(),
  cooldown_sec: z.coerce.number().int().min(0).max(86400).optional().default(300),
});

export async function registerControlRoutes(
  app: FastifyInstance,
  db: Queryable,
  authSecret: string,
): Promise<void> {
  app.get('/control/health', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'read')) return;
    return reply.code(200).send({ data: await control.controlHealth() });
  });

  app.get('/control/bootstrap', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'read')) return;

    const engine = await control.controlHealth();
    const devices = await control.listControlDevices(db);
    const caps = {
      can_create: actor.isAdmin || actorCan(actor, 'control', 'own_create') || actorCan(actor, 'control', 'all_create'),
      can_update: actor.isAdmin || actorCan(actor, 'control', 'own_update') || actorCan(actor, 'control', 'all_update'),
      can_delete: actor.isAdmin || actorCan(actor, 'control', 'own_delete') || actorCan(actor, 'control', 'all_delete'),
    };

    const empty = {
      engine,
      groups: [] as unknown[],
      ungrouped_count: 0,
      current_group: null as Record<string, unknown> | null,
      devices,
      commands: [] as unknown[],
      logs: [] as unknown[],
      schedules: [] as unknown[],
      sequences: [] as unknown[],
      rules: [] as unknown[],
      ...caps,
    };

    if (!engine.ok) {
      return reply.code(200).send({ data: empty });
    }

    const groupsRes = await control.listGroups();
    if (!groupsRes.ok) {
      return reply.code(200).send({
        data: { ...empty, engine: { ok: false, message: 'control-logic offline' } },
      });
    }

    const groups = control.asRecordArray(groupsRes.data.groups);
    const ungroupedCount = Number(groupsRes.data.ungrouped_count ?? 0);
    const { ungrouped, groupId, hasGroup } = parseGroupParam(
      request.query as Record<string, unknown>,
    );

    let currentGroup: Record<string, unknown> | null = null;
    let commands: Record<string, unknown>[] = [];
    let logs: Record<string, unknown>[] = [];
    let schedules: Record<string, unknown>[] = [];
    let sequences: Record<string, unknown>[] = [];
    let rules: Record<string, unknown>[] = [];

    if (hasGroup) {
      if (ungrouped) {
        currentGroup = {
          id: 0,
          name: 'Ungrouped',
          kind: 'generic',
          description: 'Commands not yet assigned to a building, room, or system.',
        };
        const [cmdRes, logRes, schRes, seqRes, ruleRes] = await Promise.all([
          control.listCommands(null, true),
          control.listLogs(50, null, true),
          control.listSchedules(null, true),
          control.listSequences(null, true),
          control.listRules(null, true),
        ]);
        if (cmdRes.ok) commands = control.asRecordArray(cmdRes.data.commands);
        if (logRes.ok) logs = control.asRecordArray(logRes.data.logs);
        if (schRes.ok) schedules = control.asRecordArray(schRes.data.schedules);
        if (seqRes.ok) sequences = control.asRecordArray(seqRes.data.sequences);
        if (ruleRes.ok) rules = control.asRecordArray(ruleRes.data.rules);
      } else if (groupId > 0) {
        currentGroup = groups.find((g) => Number(g.id) === groupId) ?? {
          id: groupId,
          name: `#${groupId}`,
          kind: 'generic',
          description: '',
        };
        const [cmdRes, logRes, schRes, seqRes, ruleRes] = await Promise.all([
          control.listCommands(groupId),
          control.listLogs(50, groupId),
          control.listSchedules(groupId),
          control.listSequences(groupId),
          control.listRules(groupId),
        ]);
        if (cmdRes.ok) commands = control.asRecordArray(cmdRes.data.commands);
        if (logRes.ok) logs = control.asRecordArray(logRes.data.logs);
        if (schRes.ok) schedules = control.asRecordArray(schRes.data.schedules);
        if (seqRes.ok) sequences = control.asRecordArray(seqRes.data.sequences);
        if (ruleRes.ok) rules = control.asRecordArray(ruleRes.data.rules);
      }
    }

    return reply.code(200).send({
      data: {
        engine,
        groups,
        ungrouped_count: ungroupedCount,
        current_group: currentGroup,
        devices,
        commands,
        logs,
        schedules,
        sequences,
        rules,
        ...caps,
      },
    });
  });

  app.get('/control/overview-status', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'read')) return;

    const { ungrouped, groupId, hasGroup } = parseGroupParam(
      request.query as Record<string, unknown>,
    );
    if (!hasGroup) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'group required' },
      });
    }

    const [cmdRes, logRes, seqRes, ruleRes] = await Promise.all([
      control.listCommands(ungrouped ? null : groupId, ungrouped),
      control.listLogs(50, ungrouped ? null : groupId, ungrouped),
      control.listSequences(ungrouped ? null : groupId, ungrouped),
      control.listRules(ungrouped ? null : groupId, ungrouped),
    ]);

    if (!cmdRes.ok) return sendLogicError(reply, cmdRes);

    const commands = control.asRecordArray(cmdRes.data.commands);
    const logs = logRes.ok ? control.asRecordArray(logRes.data.logs) : [];
    const sequences = seqRes.ok ? control.asRecordArray(seqRes.data.sequences) : [];
    const rules = ruleRes.ok ? control.asRecordArray(ruleRes.data.rules) : [];
    const devices = await control.listControlDevices(db);
    const live = await control.commandLiveStatus(db, commands, logs);

    return reply.code(200).send({
      data: {
        items: live.items,
        summary: live.summary,
        sequences: control.runningSequenceDiagrams(sequences, commands),
        rules: control.activeRuleCards(rules, commands, sequences, devices),
      },
    });
  });

  app.get('/control/devices/:deviceId/context', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    const canWrite =
      actor.isAdmin ||
      actorCan(actor, 'control', 'own_create') ||
      actorCan(actor, 'control', 'all_create') ||
      actorCan(actor, 'control', 'own_update') ||
      actorCan(actor, 'control', 'all_update');
    if (!canWrite) {
      return sendForbidden(reply, 'You do not have permission to do this.');
    }

    const deviceId = Number((request.params as { deviceId: string }).deviceId);
    const writeDeviceId = Number(
      (request.query as { write_device_id?: string }).write_device_id ?? 0,
    );
    const ctx = await control.getDeviceControlContext(db, deviceId, writeDeviceId);
    if (!ctx) {
      return reply.code(404).send({
        error: { code: 'NOT_FOUND', message: 'Device not found' },
      });
    }
    return reply.code(200).send({ data: ctx });
  });

  // Groups
  app.post('/control/groups', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'create')) return;
    const parsed = groupBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: parsed.error.issues[0]?.message ?? 'Invalid input' },
      });
    }
    const result = await control.createGroup(parsed.data);
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(201).send({ data: result.data });
  });

  app.put('/control/groups/:id', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'update')) return;
    const id = Number((request.params as { id: string }).id);
    const parsed = groupBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: parsed.error.issues[0]?.message ?? 'Invalid input' },
      });
    }
    const result = await control.updateGroup(id, parsed.data);
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(200).send({ data: result.data });
  });

  app.delete('/control/groups/:id', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'delete')) return;
    const id = Number((request.params as { id: string }).id);
    const result = await control.deleteGroup(id);
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(200).send({ data: result.data });
  });

  // Commands
  app.post('/control/commands', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'create')) return;
    const parsed = commandBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: parsed.error.issues[0]?.message ?? 'Invalid input' },
      });
    }
    const writeConfig = control.mergeWriteConfigFields(parsed.data.write_config ?? {}, parsed.data);
    const payload = {
      name: parsed.data.name,
      description: parsed.data.description ?? '',
      device_id: parsed.data.device_id,
      protocol: parsed.data.protocol,
      write_config: writeConfig,
      feedback_metric_key: parsed.data.feedback_metric_key ?? '',
      enabled: parsed.data.enabled ?? true,
      group_id: parsed.data.group_id ?? null,
    };
    const result = await control.createCommand(payload);
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(201).send({ data: result.data });
  });

  app.put('/control/commands/:id', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'update')) return;
    const id = Number((request.params as { id: string }).id);
    const parsed = commandBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: parsed.error.issues[0]?.message ?? 'Invalid input' },
      });
    }
    const writeConfig = control.mergeWriteConfigFields(parsed.data.write_config ?? {}, parsed.data);
    const payload = {
      name: parsed.data.name,
      description: parsed.data.description ?? '',
      device_id: parsed.data.device_id,
      protocol: parsed.data.protocol,
      write_config: writeConfig,
      feedback_metric_key: parsed.data.feedback_metric_key ?? '',
      enabled: parsed.data.enabled ?? true,
      group_id: parsed.data.group_id ?? null,
    };
    const result = await control.updateCommand(id, payload);
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(200).send({ data: result.data });
  });

  app.delete('/control/commands/:id', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'delete')) return;
    const id = Number((request.params as { id: string }).id);
    const result = await control.deleteCommand(id);
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(200).send({ data: result.data });
  });

  app.post('/control/commands/:id/execute', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'update')) return;
    const id = Number((request.params as { id: string }).id);
    const value = String((request.body as { value?: string })?.value ?? '').trim();
    const result = await control.executeCommand(id, value, actor.user.id);
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(200).send({ data: result.data });
  });

  app.post('/control/commands/:id/auto', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'update')) return;
    const id = Number((request.params as { id: string }).id);
    const result = await control.releaseHold(id, actor.user.id);
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(200).send({ data: result.data });
  });

  // Schedules
  app.post('/control/schedules', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'create')) return;
    const parsed = scheduleBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: parsed.error.issues[0]?.message ?? 'Invalid input' },
      });
    }
    const cron =
      parsed.data.cron?.trim() ||
      control.buildCron(parsed.data.time ?? '08:00', parsed.data.days ?? []);
    const result = await control.createSchedule({
      command_id: parsed.data.command_id,
      name: parsed.data.name ?? '',
      cron,
      action: parsed.data.action === 'off' ? 'off' : 'on',
      enabled: parsed.data.enabled ?? true,
    });
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(201).send({ data: result.data });
  });

  app.put('/control/schedules/:id', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'update')) return;
    const id = Number((request.params as { id: string }).id);
    const parsed = scheduleBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: parsed.error.issues[0]?.message ?? 'Invalid input' },
      });
    }
    const cron =
      parsed.data.cron?.trim() ||
      control.buildCron(parsed.data.time ?? '08:00', parsed.data.days ?? []);
    const result = await control.updateSchedule(id, {
      command_id: parsed.data.command_id,
      name: parsed.data.name ?? '',
      cron,
      action: parsed.data.action === 'off' ? 'off' : 'on',
      enabled: parsed.data.enabled ?? true,
    });
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(200).send({ data: result.data });
  });

  app.delete('/control/schedules/:id', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'delete')) return;
    const id = Number((request.params as { id: string }).id);
    const result = await control.deleteSchedule(id);
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(200).send({ data: result.data });
  });

  // Sequences
  app.post('/control/sequences', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'create')) return;
    const parsed = sequenceBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: parsed.error.issues[0]?.message ?? 'Invalid input' },
      });
    }
    const result = await control.createSequence({
      name: parsed.data.name,
      steps: control.normalizeSequenceSteps(parsed.data.steps ?? []),
      enabled: parsed.data.enabled ?? true,
      group_id: parsed.data.group_id ?? null,
      loop: parsed.data.loop ?? false,
      loop_count: parsed.data.loop_count ?? 0,
      loop_delay_sec: parsed.data.loop_delay_sec ?? 0,
    });
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(201).send({ data: result.data });
  });

  app.put('/control/sequences/:id', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'update')) return;
    const id = Number((request.params as { id: string }).id);
    const parsed = sequenceBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: parsed.error.issues[0]?.message ?? 'Invalid input' },
      });
    }
    const result = await control.updateSequence(id, {
      name: parsed.data.name,
      steps: control.normalizeSequenceSteps(parsed.data.steps ?? []),
      enabled: parsed.data.enabled ?? true,
      group_id: parsed.data.group_id ?? null,
      loop: parsed.data.loop ?? false,
      loop_count: parsed.data.loop_count ?? 0,
      loop_delay_sec: parsed.data.loop_delay_sec ?? 0,
    });
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(200).send({ data: result.data });
  });

  app.delete('/control/sequences/:id', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'delete')) return;
    const id = Number((request.params as { id: string }).id);
    const result = await control.deleteSequence(id);
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(200).send({ data: result.data });
  });

  app.post('/control/sequences/:id/execute', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'update')) return;
    const id = Number((request.params as { id: string }).id);
    const result = await control.executeSequence(id, actor.user.id);
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(200).send({ data: result.data });
  });

  app.post('/control/sequences/:id/stop', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'update')) return;
    const id = Number((request.params as { id: string }).id);
    const result = await control.stopSequence(id);
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(200).send({ data: result.data });
  });

  app.post('/control/sequences/:id/pause', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'update')) return;
    const id = Number((request.params as { id: string }).id);
    const result = await control.pauseSequence(id);
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(200).send({ data: result.data });
  });

  app.post('/control/sequences/:id/resume', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'update')) return;
    const id = Number((request.params as { id: string }).id);
    const result = await control.resumeSequence(id);
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(200).send({ data: result.data });
  });

  // Rules
  app.post('/control/rules', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'create')) return;
    const parsed = ruleBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: parsed.error.issues[0]?.message ?? 'Invalid input' },
      });
    }
    const result = await control.createRule({
      name: parsed.data.name,
      conditions: control.normalizeRuleConditions(parsed.data.conditions ?? {}),
      action: control.normalizeRuleAction(parsed.data.action ?? {}),
      enabled: parsed.data.enabled ?? true,
      group_id: parsed.data.group_id ?? null,
      cooldown_sec: parsed.data.cooldown_sec ?? 300,
    });
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(201).send({ data: result.data });
  });

  app.put('/control/rules/:id', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'update')) return;
    const id = Number((request.params as { id: string }).id);
    const parsed = ruleBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: parsed.error.issues[0]?.message ?? 'Invalid input' },
      });
    }
    const result = await control.updateRule(id, {
      name: parsed.data.name,
      conditions: control.normalizeRuleConditions(parsed.data.conditions ?? {}),
      action: control.normalizeRuleAction(parsed.data.action ?? {}),
      enabled: parsed.data.enabled ?? true,
      group_id: parsed.data.group_id ?? null,
      cooldown_sec: parsed.data.cooldown_sec ?? 300,
    });
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(200).send({ data: result.data });
  });

  app.delete('/control/rules/:id', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!requireControl(actor, reply, 'delete')) return;
    const id = Number((request.params as { id: string }).id);
    const result = await control.deleteRule(id);
    if (!result.ok) return sendLogicError(reply, result);
    return reply.code(200).send({ data: result.data });
  });
}
