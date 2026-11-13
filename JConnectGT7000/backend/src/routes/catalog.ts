import type { FastifyInstance, FastifyReply, FastifyRequest } from 'fastify';
import { z } from 'zod';
import { requestUserId, sendUnauthorized } from '../auth/requestUser.js';
import type { Queryable } from '../db.js';
import {
  DCIM_TAXONOMY,
  injectDcimIntoDeviceInformation,
  resolveDcimSelection,
} from '../dcim/taxonomy.js';
import {
  categoryUsage,
  deleteCategory,
  deleteTemplate,
  ensureCategoryIdForLabel,
  findCategory,
  findCategoryByName,
  findTemplate,
  insertCategory,
  insertTemplate,
  listCategories,
  listTemplates,
  templateDeviceCount,
  updateCategory,
  updateTemplate,
  type TemplateWriteInput,
} from '../repositories/catalog.js';
import { listChannels } from '../repositories/notifications.js';
import {
  deviceFilters,
  findDevice,
  importDevicesFromCsvRows,
  insertDevice,
  latestMetricValue,
  latestMetricsForDevice,
  listDevices,
  listGatewayDevices,
  listMetricSources,
  metricKeysFromDevice,
  metricTimeseries,
  softDeleteDevice,
  updateDevice,
  type DeviceWriteInput,
  type CsvImportRow,
} from '../repositories/devices.js';

const jsonValue = z.unknown();

const deviceBody = z
  .object({
    name: z.string().trim().min(1),
    deviceTemplateId: z.number().int().positive().optional(),
    device_template_id: z.number().int().positive().optional(),
    serialNumber: z.string().optional(),
    serial_number: z.string().optional(),
    location: z.string().optional(),
    description: z.string().optional(),
    isActive: z.boolean().optional(),
    is_active: z.union([z.boolean(), z.string(), z.number()]).optional(),
    deviceRole: z.enum(['native_device', 'gateway', 'endpoint']).optional(),
    device_role: z.enum(['native_device', 'gateway', 'endpoint']).optional(),
    gatewayDeviceId: z.number().int().positive().nullable().optional(),
    gateway_device_id: z.number().int().positive().nullable().optional(),
    communicationConfig: jsonValue.optional(),
    communication_config: jsonValue.optional(),
    metrics: jsonValue.optional(),
    customFields: z.record(z.string(), z.unknown()).optional(),
    device_information_json: jsonValue.optional(),
    snapshotTriggers: jsonValue.optional(),
    snapshot_triggers_json: jsonValue.optional(),
    image: z.string().nullable().optional(),
  })
  .transform((raw): (Partial<DeviceWriteInput> & { name: string; deviceTemplateId: number }) | null => {
    const deviceTemplateId = raw.deviceTemplateId ?? raw.device_template_id;
    if (!deviceTemplateId) {
      return null;
    }

    const result: Partial<DeviceWriteInput> & { name: string; deviceTemplateId: number } = {
      name: raw.name,
      deviceTemplateId,
      serialNumber: raw.serialNumber ?? raw.serial_number ?? '',
      location: raw.location ?? '',
      description: raw.description ?? '',
    };

    if (raw.isActive !== undefined || raw.is_active !== undefined) {
      const isActiveRaw = raw.isActive ?? raw.is_active;
      let isActive = true;
      if (typeof isActiveRaw === 'boolean') {
        isActive = isActiveRaw;
      } else if (typeof isActiveRaw === 'number') {
        isActive = isActiveRaw !== 0;
      } else if (typeof isActiveRaw === 'string') {
        isActive = !['0', 'false', 'off', ''].includes(isActiveRaw.toLowerCase());
      }
      result.isActive = isActive;
    }

    if (raw.deviceRole !== undefined || raw.device_role !== undefined) {
      result.deviceRole = raw.deviceRole ?? raw.device_role ?? 'native_device';
    }

    if (raw.gatewayDeviceId !== undefined || raw.gateway_device_id !== undefined) {
      result.gatewayDeviceId = raw.gatewayDeviceId ?? raw.gateway_device_id ?? null;
    }

    if (raw.communicationConfig !== undefined || raw.communication_config !== undefined) {
      let communicationConfig = raw.communicationConfig ?? raw.communication_config ?? {};
      if (typeof communicationConfig === 'string') {
        try {
          communicationConfig = JSON.parse(communicationConfig);
        } catch {
          communicationConfig = {};
        }
      }
      result.communicationConfig = communicationConfig;
    }

    if (raw.metrics !== undefined) {
      let metrics = raw.metrics;
      if (typeof metrics === 'string') {
        try {
          metrics = JSON.parse(metrics);
        } catch {
          metrics = [];
        }
      }
      result.metrics = metrics;
    }

    if (raw.customFields !== undefined || raw.device_information_json !== undefined) {
      let customFields: Record<string, unknown> = raw.customFields ?? {};
      if (raw.device_information_json !== undefined) {
        const parsed =
          typeof raw.device_information_json === 'string'
            ? (() => {
                try {
                  return JSON.parse(raw.device_information_json) as unknown;
                } catch {
                  return {};
                }
              })()
            : raw.device_information_json;
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
          customFields = parsed as Record<string, unknown>;
        }
      }
      result.customFields = customFields;
    }

    if (raw.snapshotTriggers !== undefined || raw.snapshot_triggers_json !== undefined) {
      let snapshotTriggers: unknown | null = raw.snapshotTriggers ?? null;
      if (raw.snapshot_triggers_json !== undefined) {
        if (typeof raw.snapshot_triggers_json === 'string') {
          try {
            snapshotTriggers = JSON.parse(raw.snapshot_triggers_json);
          } catch {
            snapshotTriggers = [];
          }
        } else {
          snapshotTriggers = raw.snapshot_triggers_json;
        }
      }
      result.snapshotTriggers = snapshotTriggers;
    }

    if (raw.image !== undefined) {
      result.image = raw.image;
    }

    return result;
  });

const categoryBody = z.object({
  name: z.string().trim().min(1),
  description: z.string().optional().default(''),
  icon: z.string().optional().default('fa-server'),
  color: z.string().optional().default('#007bff'),
  isActive: z.boolean().optional().default(true),
});

const templateBody = z
  .object({
    name: z.string().trim().min(1),
    description: z.string().optional().default(''),
    protocol: z.string().trim().min(1),
    categoryId: z.number().int().positive().optional(),
    category_id: z.number().int().positive().optional(),
    dcimCategory: z.string().optional(),
    dcim_category: z.string().optional(),
    dcimSubCategory: z.string().optional(),
    dcim_sub_category: z.string().optional(),
    communicationConfig: jsonValue.optional(),
    communication_config: jsonValue.optional(),
    deviceInformation: jsonValue.optional(),
    device_information: jsonValue.optional(),
    metrics: jsonValue.optional(),
    defaultDashboardLayout: jsonValue.optional(),
    default_dashboard_layout: jsonValue.optional(),
    isActive: z.union([z.boolean(), z.string(), z.number()]).optional(),
    is_active: z.union([z.boolean(), z.string(), z.number()]).optional(),
    image: z.string().nullable().optional(),
  })
  .transform((raw) => {
    const dcimCategory = (raw.dcimCategory ?? raw.dcim_category ?? '').trim();
    const dcimSubCategory = (raw.dcimSubCategory ?? raw.dcim_sub_category ?? '').trim();
    const categoryId = raw.categoryId ?? raw.category_id;

    let isActive = true;
    const isActiveRaw = raw.isActive ?? raw.is_active;
    if (typeof isActiveRaw === 'boolean') {
      isActive = isActiveRaw;
    } else if (typeof isActiveRaw === 'number') {
      isActive = isActiveRaw !== 0;
    } else if (typeof isActiveRaw === 'string') {
      isActive = !['0', 'false', 'off', ''].includes(isActiveRaw.toLowerCase());
    }

    const parseMaybeJson = (value: unknown, fallback: unknown): unknown => {
      if (value === undefined) return fallback;
      if (typeof value === 'string') {
        try {
          return JSON.parse(value) as unknown;
        } catch {
          return fallback;
        }
      }
      return value ?? fallback;
    };

    return {
      name: raw.name,
      description: raw.description ?? '',
      protocol: raw.protocol,
      categoryId: categoryId ?? null,
      dcimCategory,
      dcimSubCategory,
      communicationConfig: parseMaybeJson(
        raw.communicationConfig ?? raw.communication_config,
        {},
      ),
      deviceInformation: parseMaybeJson(raw.deviceInformation ?? raw.device_information, {}),
      metrics: parseMaybeJson(raw.metrics, []),
      defaultDashboardLayout: parseMaybeJson(
        raw.defaultDashboardLayout ?? raw.default_dashboard_layout,
        [],
      ),
      isActive,
      image: raw.image === undefined ? null : raw.image,
    };
  });

async function resolveTemplateWrite(
  db: Queryable,
  body: z.infer<typeof templateBody>,
): Promise<{ ok: true; input: TemplateWriteInput } | { ok: false; message: string }> {
  let taxonomy = body.dcimCategory && body.dcimSubCategory
    ? resolveDcimSelection(body.dcimCategory, body.dcimSubCategory)
    : null;

  let categoryId = body.categoryId;

  if (taxonomy) {
    categoryId = await ensureCategoryIdForLabel(db, taxonomy.label);
  } else if (categoryId) {
    const category = await findCategory(db, categoryId);
    if (!category) {
      return { ok: false, message: 'Category not found' };
    }
    // Try to infer DCIM from existing category label "Cat / Sub"
    const parts = category.name.split(' / ');
    if (parts.length >= 2) {
      taxonomy = resolveDcimSelection(parts[0]!, parts.slice(1).join(' / '));
    }
    if (!taxonomy) {
      taxonomy = {
        category: body.dcimCategory || category.name,
        subCategory: body.dcimSubCategory || '',
        domain: 'unknown',
        pueRole: 'unknown',
        label: category.name,
      };
    }
  } else {
    return {
      ok: false,
      message: 'Please select a valid Device Category and Sub-Category.',
    };
  }

  const infoRaw =
    body.deviceInformation &&
    typeof body.deviceInformation === 'object' &&
    !Array.isArray(body.deviceInformation)
      ? (body.deviceInformation as Record<string, unknown>)
      : {};
  const deviceInformation = injectDcimIntoDeviceInformation(infoRaw, taxonomy);

  return {
    ok: true,
    input: {
      name: body.name,
      description: body.description,
      protocol: body.protocol,
      categoryId: categoryId!,
      isActive: body.isActive,
      communicationConfig: body.communicationConfig,
      deviceInformation,
      metrics: body.metrics,
      defaultDashboardLayout: body.defaultDashboardLayout,
      image: body.image,
    },
  };
}

function userId(
  request: Parameters<typeof requestUserId>[0],
  reply: Parameters<typeof sendUnauthorized>[0],
  secret: string,
): number | null {
  const id = requestUserId(request, secret);
  if (!id) {
    void sendUnauthorized(reply);
    return null;
  }
  return id;
}

function parseDeviceBody(
  body: unknown,
): (Partial<DeviceWriteInput> & { name: string; deviceTemplateId: number }) | null {
  const parsed = deviceBody.safeParse(body ?? {});
  if (!parsed.success || !parsed.data) {
    return null;
  }
  return parsed.data;
}

function toWriteInput(
  partial: Partial<DeviceWriteInput> & { name: string; deviceTemplateId: number },
  existing?: {
    isActive: boolean;
    deviceRole: string;
    gatewayDeviceId: number | null;
    communicationConfig: unknown;
    metrics: unknown;
    customFields: Record<string, unknown>;
    snapshotTriggers: unknown;
    image: string | null;
  },
): DeviceWriteInput {
  const role = (partial.deviceRole ??
    existing?.deviceRole ??
    'native_device') as DeviceWriteInput['deviceRole'];
  return {
    name: partial.name,
    deviceTemplateId: partial.deviceTemplateId,
    serialNumber: partial.serialNumber ?? '',
    location: partial.location ?? '',
    description: partial.description ?? '',
    isActive: partial.isActive ?? existing?.isActive ?? true,
    deviceRole: role === 'gateway' || role === 'endpoint' ? role : 'native_device',
    gatewayDeviceId:
      partial.gatewayDeviceId !== undefined
        ? partial.gatewayDeviceId
        : (existing?.gatewayDeviceId ?? null),
    communicationConfig:
      partial.communicationConfig !== undefined
        ? partial.communicationConfig
        : (existing?.communicationConfig ?? null),
    metrics: partial.metrics !== undefined ? partial.metrics : (existing?.metrics ?? null),
    customFields: partial.customFields ?? existing?.customFields ?? {},
    snapshotTriggers:
      partial.snapshotTriggers !== undefined
        ? partial.snapshotTriggers
        : (existing?.snapshotTriggers ?? null),
    image: partial.image !== undefined ? partial.image : (existing?.image ?? null),
  };
}

function writeError(reply: FastifyReply, code: string) {
  if (code === 'TEMPLATE_REQUIRED') {
    return reply.code(400).send({
      error: { code: 'INVALID_INPUT', message: 'Please select a Device Template before proceeding.' },
    });
  }
  if (code === 'INVALID_GATEWAY') {
    return reply.code(400).send({
      error: { code: 'INVALID_GATEWAY', message: 'Please select a valid Gateway Collector.' },
    });
  }
  return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Device not found' } });
}

function parseCsvText(text: string): CsvImportRow[] {
  const lines = text
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter((line) => line.length > 0);
  if (lines.length < 2) {
    return [];
  }
  const header = lines[0].split(',').map((cell) => cell.trim().replace(/^"|"$/g, ''));
  const rows: CsvImportRow[] = [];
  for (const line of lines.slice(1)) {
    const cells = line.split(',').map((cell) => cell.trim().replace(/^"|"$/g, ''));
    const record: Record<string, string> = {};
    header.forEach((key, index) => {
      record[key] = cells[index] ?? '';
    });
    rows.push({
      name: record.name ?? '',
      serial_number: record.serial_number,
      location: record.location,
      description: record.description,
      app_key: record.app_key,
      dev_eui: record.dev_eui,
      application_server: record.application_server,
      device_template_id: record.device_template_id ? Number(record.device_template_id) : undefined,
    });
  }
  return rows;
}

export async function registerCatalogRoutes(app: FastifyInstance, db: Queryable, authSecret: string): Promise<void> {
  app.get('/devices', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    const query = request.query as Record<string, string | undefined>;
    const page = Math.max(1, Number(query.page) || 1);
    const lengthRaw = Number(query.length ?? query.pageSize) || 10;
    const pageSize = Math.min(100, Math.max(1, lengthRaw));
    const result = await listDevices(db, {
      search: (query.search ?? query.search_custom)?.trim() || undefined,
      status: query.status || undefined,
      template: query.template || undefined,
      location: query.location || undefined,
      page,
      pageSize,
    });
    return reply.send({
      data: {
        ...result,
        page,
        pageSize,
        length: pageSize,
        totalPages: Math.max(1, Math.ceil(result.total / pageSize)),
      },
    });
  });

  app.get('/devices/filters', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    return reply.send({ data: await deviceFilters(db) });
  });

  app.get('/devices/filter-options', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    return reply.send({ data: await deviceFilters(db) });
  });

  app.get('/devices/metric-sources', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    const [devices, gateways, channels] = await Promise.all([
      listMetricSources(db),
      listGatewayDevices(db),
      listChannels(db),
    ]);
    // channels[] = notification channel IDs for metric trigger / normal_condition pickers
    return reply.send({
      data: {
        devices,
        gateways,
        channels: channels.map((c) => ({ id: c.id, name: c.name, type: c.type, isActive: c.isActive })),
      },
    });
  });

  app.post('/devices/import-csv', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    const contentType = request.headers['content-type'] ?? '';
    let rows: CsvImportRow[] = [];

    if (contentType.includes('multipart/form-data')) {
      const { parseBodyFields } = await import('../http/multipart.js');
      const { files, fields } = await parseBodyFields(request);
      const file = files.csv_file ?? files.file ?? files.csv;
      if (file) {
        const buffer = await file.toBuffer();
        rows = parseCsvText(buffer.toString('utf8'));
      } else if (fields.csv) {
        rows = parseCsvText(fields.csv);
      }
    } else {
      const body = (request.body ?? {}) as { rows?: CsvImportRow[]; csv?: string };
      if (Array.isArray(body.rows)) {
        rows = body.rows;
      } else if (typeof body.csv === 'string') {
        rows = parseCsvText(body.csv);
      }
    }

    if (rows.length === 0) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'CSV file or rows are required' },
      });
    }

    const result = await importDevicesFromCsvRows(db, rows, { registerChirpStack: true });
    return reply.code(201).send({
      data: {
        ...result,
        note: 'ChirpStack registration is stubbed; configure chirpstack service when available (PHP Device_model::registerDevice).',
      },
    });
  });

  app.get('/devices/:id/metrics/latest', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const device = Number.isInteger(id) ? await findDevice(db, id) : null;
    if (!device) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Device not found' } });
    }
    const latest = await latestMetricsForDevice(db, id);
    return reply.send({ data: { deviceId: id, latest } });
  });

  app.get('/devices/:id/metrics/:key/latest', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    const params = request.params as { id: string; key: string };
    const id = Number(params.id);
    const device = Number.isInteger(id) ? await findDevice(db, id) : null;
    if (!device) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Device not found' } });
    }
    const row = await latestMetricValue(db, id, params.key);
    return reply.send({
      data: {
        deviceId: id,
        key: params.key,
        value: row?.value ?? null,
        valueText: row?.valueText ?? null,
        valueBool: row?.valueBool ?? null,
        valueJson: row?.valueJson ?? null,
        timestamp: row?.timestamp ?? null,
      },
    });
  });

  app.get('/devices/:id/metrics/:key/timeseries', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    const params = request.params as { id: string; key: string };
    const id = Number(params.id);
    const device = Number.isInteger(id) ? await findDevice(db, id) : null;
    if (!device) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Device not found' } });
    }
    const range = ((request.query as { range?: string }).range ?? '24h').trim() || '24h';
    const points = await metricTimeseries(db, id, params.key, range);
    return reply.send({
      data: {
        deviceId: id,
        key: params.key,
        range,
        points,
        series: points.map((point) => point.value),
        timestamps: points.map((point) => point.timestamp),
      },
    });
  });

  /** Metric keys / definitions from devices.metrics JSON (metric builder + form). */
  app.get('/devices/:id/metrics', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const device = Number.isInteger(id) ? await findDevice(db, id) : null;
    if (!device) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Device not found' } });
    }
    const metrics = Array.isArray(device.metrics) ? device.metrics : [];
    return reply.send({
      data: {
        deviceId: device.id,
        name: device.name,
        metrics,
        keys: metricKeysFromDevice(metrics),
      },
    });
  });

  app.get('/devices/:id', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const device = Number.isInteger(id) ? await findDevice(db, id) : null;
    if (!device) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Device not found' } });
    }
    return reply.send({ data: device });
  });

  app.post('/devices', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    const partial = parseDeviceBody(request.body);
    if (!partial) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Name and template are required' } });
    }
    const result = await insertDevice(db, toWriteInput(partial));
    if (!result.ok) {
      return writeError(reply, result.code);
    }
    return reply.code(201).send({ data: await findDevice(db, result.id) });
  });

  const saveDevice = async (request: FastifyRequest, reply: FastifyReply) => {
    if (userId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const partial = parseDeviceBody(request.body);
    if (!Number.isInteger(id) || !partial) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Name and template are required' } });
    }
    const existing = await findDevice(db, id);
    if (!existing) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Device not found' } });
    }
    const result = await updateDevice(
      db,
      id,
      toWriteInput(partial, {
        isActive: existing.isActive,
        deviceRole: existing.deviceRole,
        gatewayDeviceId: existing.gatewayDeviceId,
        communicationConfig: existing.communicationConfig,
        metrics: existing.metrics,
        customFields: existing.customFields,
        snapshotTriggers: existing.snapshotTriggers,
        image: existing.image,
      }),
    );
    if (!result.ok) {
      return writeError(reply, result.code);
    }
    return reply.send({ data: await findDevice(db, id) });
  };

  app.put('/devices/:id', saveDevice);
  app.patch('/devices/:id', saveDevice);

  app.delete('/devices/:id', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const removed = Number.isInteger(id) ? await softDeleteDevice(db, id) : false;
    if (!removed) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Device not found' } });
    }
    return reply.send({ data: { id } });
  });

  app.get('/device-categories', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    return reply.send({ data: await listCategories(db) });
  });

  app.get('/device-categories/:id', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const category = Number.isInteger(id) ? await findCategory(db, id) : null;
    if (!category) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Category not found' } });
    }
    return reply.send({ data: category });
  });

  app.post('/device-categories', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    const parsed = categoryBody.safeParse(request.body ?? {});
    if (!parsed.success) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Name is required' } });
    }
    const existing = await findCategoryByName(db, parsed.data.name);
    if (existing) {
      return reply.code(409).send({ error: { code: 'DUPLICATE', message: 'Category name already exists' } });
    }
    const id = await insertCategory(db, parsed.data);
    return reply.code(201).send({ data: await findCategory(db, id) });
  });

  const saveCategory = async (request: FastifyRequest, reply: FastifyReply) => {
    if (userId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const parsed = categoryBody.safeParse(request.body ?? {});
    if (!Number.isInteger(id) || !parsed.success) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Name is required' } });
    }
    const named = await findCategoryByName(db, parsed.data.name);
    if (named && named !== id) {
      return reply.code(409).send({ error: { code: 'DUPLICATE', message: 'Category name already exists' } });
    }
    const updated = await updateCategory(db, id, parsed.data);
    if (!updated) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Category not found' } });
    }
    return reply.send({ data: await findCategory(db, id) });
  };

  app.put('/device-categories/:id', saveCategory);
  app.patch('/device-categories/:id', saveCategory);

  app.delete('/device-categories/:id', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    if (!Number.isInteger(id)) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Category id is required' } });
    }
    const usage = await categoryUsage(db, id);
    if (usage.devices > 0 || usage.templates > 0) {
      return reply.code(409).send({
        error: {
          code: 'IN_USE',
          message: `Cannot delete category. It is being used by ${usage.devices} devices and ${usage.templates} templates.`,
        },
      });
    }
    await deleteCategory(db, id);
    return reply.send({ data: { id } });
  });

  app.get('/device-templates', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    return reply.send({ data: await listTemplates(db) });
  });

  app.get('/dcim-taxonomy', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    return reply.send({ data: DCIM_TAXONOMY });
  });

  app.get('/device-templates/:id', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const template = Number.isInteger(id) ? await findTemplate(db, id) : null;
    if (!template) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Template not found' } });
    }
    return reply.send({ data: template });
  });

  /** Alias used by device create form (wave 11). */
  app.get('/device-templates/:id/preview', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const template = Number.isInteger(id) ? await findTemplate(db, id) : null;
    if (!template) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Template not found' } });
    }
    return reply.send({ data: template });
  });

  app.post('/device-templates', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    const parsed = templateBody.safeParse(request.body ?? {});
    if (!parsed.success) {
      return reply
        .code(400)
        .send({ error: { code: 'INVALID_INPUT', message: 'Name, protocol, and category are required' } });
    }
    const resolved = await resolveTemplateWrite(db, parsed.data);
    if (!resolved.ok) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: resolved.message } });
    }
    const id = await insertTemplate(db, resolved.input);
    return reply.code(201).send({ data: await findTemplate(db, id) });
  });

  app.patch('/device-templates/:id', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const parsed = templateBody.safeParse(request.body ?? {});
    if (!Number.isInteger(id) || !parsed.success) {
      return reply
        .code(400)
        .send({ error: { code: 'INVALID_INPUT', message: 'Name, protocol, and category are required' } });
    }
    const resolved = await resolveTemplateWrite(db, parsed.data);
    if (!resolved.ok) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: resolved.message } });
    }
    const updated = await updateTemplate(db, id, resolved.input);
    if (!updated) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Template not found' } });
    }
    return reply.send({ data: await findTemplate(db, id) });
  });

  app.delete('/device-templates/:id', async (request, reply) => {
    if (userId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    if (!Number.isInteger(id)) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Template id is required' } });
    }
    const used = await templateDeviceCount(db, id);
    if (used > 0) {
      return reply.code(409).send({
        error: { code: 'IN_USE', message: `Cannot delete template. It is used by ${used} devices.` },
      });
    }
    await deleteTemplate(db, id);
    return reply.send({ data: { id } });
  });
}
