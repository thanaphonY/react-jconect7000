import type { FastifyInstance } from 'fastify';
import { afterEach, describe, expect, it } from 'vitest';
import { signAccessToken } from '../src/auth/tokens.js';
import { buildApp } from '../src/app.js';
import type { Queryable } from '../src/db.js';

const SECRET = 'test-secret-value!!';

interface TemplateRow {
  id: number;
  name: string;
  protocol: string;
  category_id: number;
  communication_config: Record<string, unknown>;
  metrics: unknown[];
  device_information: Record<string, unknown>;
  image: string | null;
}

interface DeviceRow {
  id: number;
  name: string;
  device_template_id: number;
  serial_number: string;
  location: string;
  description: string;
  is_active: boolean;
  is_deleted: boolean;
  category_id: number | null;
  communication_config: Record<string, unknown>;
  metrics: unknown[];
  config: Record<string, unknown>;
  image: string | null;
}

function makeStore() {
  const templates: TemplateRow[] = [
    {
      id: 10,
      name: 'SNMP Sensor',
      protocol: 'snmp',
      category_id: 3,
      communication_config: { host: '192.168.1.1', community: 'public', version: '2c' },
      metrics: [{ key: 'temperature', label: 'Temperature', unit: '°C', datatype: 'float', enable: true }],
      device_information: { Brand: 'Acme', Model: 'T-100' },
      image: 'template.png',
    },
    {
      id: 11,
      name: 'LoRaWAN Node',
      protocol: 'lorawan',
      category_id: 3,
      communication_config: {
        protocol: 'lorawan',
        application_server: '',
        device_profile_id: '',
        dev_eui: '',
        app_eui: '',
        app_key: '',
      },
      metrics: [],
      device_information: { Brand: 'Semtech', Model: 'LW-1' },
      image: null,
    },
  ];

  const devices: DeviceRow[] = [];
  let nextId = 1;

  const query: Queryable['query'] = async (sql, values = []) => {
    const text = sql.replace(/\s+/g, ' ');

    if (text.includes('FROM ia_users') && text.includes('WHERE ia_users_id')) {
      return {
        rows: [
          {
            ia_users_id: 1,
            name: 'Supperadmin',
            email: 'a@b.c',
            user_type: 'admin',
            profile_pic: null,
            status: 'active',
            is_deleted: '0',
          },
        ],
      };
    }

    if (text.includes('FROM device_templates WHERE id')) {
      const id = Number(values[0]);
      const row = templates.find((t) => t.id === id);
      return { rows: row ? [row] : [] };
    }

    if (text.includes('INSERT INTO devices')) {
      const [
        name,
        deviceTemplateId,
        serialNumber,
        location,
        description,
        isActive,
        categoryId,
        communicationConfig,
        metrics,
        config,
        image,
      ] = values as [
        string,
        number,
        string,
        string,
        string,
        boolean,
        number | null,
        string,
        string,
        string,
        string | null,
      ];
      const id = nextId++;
      devices.push({
        id,
        name,
        device_template_id: deviceTemplateId,
        serial_number: serialNumber,
        location,
        description,
        is_active: isActive,
        is_deleted: false,
        category_id: categoryId,
        communication_config: JSON.parse(communicationConfig) as Record<string, unknown>,
        metrics: JSON.parse(metrics) as unknown[],
        config: JSON.parse(config) as Record<string, unknown>,
        image,
      });
      return { rows: [{ id }] };
    }

    if (text.includes('UPDATE devices') && text.includes('SET name')) {
      const id = Number(values[0]);
      const row = devices.find((d) => d.id === id && !d.is_deleted);
      if (!row) return { rows: [] };
      row.name = String(values[1]);
      row.device_template_id = Number(values[2]);
      row.serial_number = String(values[3]);
      row.location = String(values[4]);
      row.description = String(values[5]);
      row.is_active = Boolean(values[6]);
      row.category_id = values[7] as number | null;
      row.communication_config = JSON.parse(String(values[8])) as Record<string, unknown>;
      row.metrics = JSON.parse(String(values[9])) as unknown[];
      row.config = JSON.parse(String(values[10])) as Record<string, unknown>;
      row.image = (values[11] as string | null) ?? null;
      return { rows: [{ id }] };
    }

    if (text.includes('UPDATE devices SET is_deleted = true')) {
      const id = Number(values[0]);
      const row = devices.find((d) => d.id === id && !d.is_deleted);
      if (!row) return { rows: [] };
      row.is_deleted = true;
      return { rows: [{ id }] };
    }

    if (text.includes('FROM devices d') && text.includes('WHERE d.id = $1')) {
      const id = Number(values[0]);
      const row = devices.find((d) => d.id === id && !d.is_deleted);
      if (!row) return { rows: [] };
      const template = templates.find((t) => t.id === row.device_template_id);
      return {
        rows: [
          {
            id: row.id,
            name: row.name,
            serial_number: row.serial_number,
            location: row.location,
            description: row.description,
            is_active: row.is_active,
            device_template_id: row.device_template_id,
            communication_config: row.communication_config,
            metrics: row.metrics,
            config: row.config,
            category_id: row.category_id,
            image: row.image,
            template_name: template?.name ?? null,
            template_image: template?.image ?? null,
            template_protocol: template?.protocol ?? null,
            brand: template?.device_information.Brand ?? null,
            model: template?.device_information.Model ?? null,
            category_name: 'Sensors',
          },
        ],
      };
    }

    if (text.includes('SELECT COUNT(*)::int AS total FROM devices d')) {
      return { rows: [{ total: devices.filter((d) => !d.is_deleted).length }] };
    }

    if (text.includes('FROM devices d') && text.includes('ORDER BY d.name ASC') && text.includes('LIMIT')) {
      const active = devices.filter((d) => !d.is_deleted);
      return {
        rows: active.map((row) => {
          const template = templates.find((t) => t.id === row.device_template_id);
          return {
            id: row.id,
            name: row.name,
            serial_number: row.serial_number,
            location: row.location,
            description: row.description,
            is_active: row.is_active,
            device_template_id: row.device_template_id,
            image: row.image,
            config: row.config,
            category_id: row.category_id,
            template_name: template?.name ?? null,
            template_image: template?.image ?? null,
            brand: template?.device_information.Brand ?? null,
            model: template?.device_information.Model ?? null,
            category_name: 'Sensors',
          };
        }),
      };
    }

    if (text.includes('SELECT DISTINCT location FROM devices')) {
      return { rows: devices.filter((d) => !d.is_deleted && d.location).map((d) => ({ location: d.location })) };
    }
    if (text.includes('SELECT DISTINCT dt.name AS template_name')) {
      return { rows: [{ template_name: 'SNMP Sensor' }] };
    }
    if (text.includes('SELECT DISTINCT dc.name AS category_name')) {
      return { rows: [{ category_name: 'Sensors' }] };
    }

    if (text.includes('SELECT id, name, metrics FROM devices WHERE is_deleted = false')) {
      return {
        rows: devices
          .filter((d) => !d.is_deleted)
          .map((d) => ({ id: d.id, name: d.name, metrics: d.metrics })),
      };
    }

    if (text.includes('SELECT id, name, config FROM devices WHERE is_deleted = false')) {
      return {
        rows: devices
          .filter((d) => !d.is_deleted)
          .map((d) => ({ id: d.id, name: d.name, config: d.config })),
      };
    }

    if (text.includes('SELECT config FROM devices WHERE id')) {
      const id = Number(values[0]);
      const row = devices.find((d) => d.id === id && !d.is_deleted);
      return { rows: row ? [{ config: row.config }] : [] };
    }

    if (text.includes('FROM notification_channels')) {
      return {
        rows: [
          {
            id: 7,
            name: 'LINE Test',
            type: 'LINE_OA',
            is_active: true,
            description: 'Test',
            devices_count: 0,
          },
        ],
      };
    }

    if (text.includes('FROM device_templates') && text.includes('dt.communication_config')) {
      const id = Number(values[0]);
      const row = templates.find((t) => t.id === id);
      if (!row) return { rows: [] };
      return {
        rows: [
          {
            id: row.id,
            name: row.name,
            description: '',
            protocol: row.protocol,
            category_id: row.category_id,
            category_name: 'Sensors',
            is_active: true,
            image: row.image,
            communication_config: row.communication_config,
            device_information: row.device_information,
            metrics: row.metrics,
            default_dashboard_layout: [],
          },
        ],
      };
    }

    if (text.includes('FROM device_data') && text.includes('DISTINCT ON')) {
      return { rows: [] };
    }
    if (text.includes('FROM device_data') && text.includes('ORDER BY timestamp DESC')) {
      return { rows: [] };
    }
    if (text.includes('FROM device_data') && text.includes('ORDER BY timestamp ASC')) {
      return { rows: [] };
    }

    if (text.includes('FROM device_categories')) {
      return {
        rows: [
          {
            id: 3,
            name: 'Sensors',
            description: '',
            icon: 'fa-server',
            color: '#007bff',
            is_active: true,
            devices_count: devices.filter((d) => !d.is_deleted).length,
            templates_count: 1,
          },
        ],
      };
    }

    throw new Error(`Unhandled SQL in wave11 test: ${text}`);
  };

  return { query, devices, templates };
}

describe('wave 11 device-full APIs', () => {
  let app: FastifyInstance | undefined;

  afterEach(async () => {
    if (app) {
      await app.close();
      app = undefined;
    }
  });

  async function authed() {
    const store = makeStore();
    app = await buildApp({ pool: store, authSecret: SECRET });
    const token = signAccessToken(1, SECRET);
    return { store, token, headers: { authorization: `Bearer ${token}` } };
  }

  it('saves and round-trips communication_config, metrics, and image path', async () => {
    const { headers } = await authed();

    const communicationConfig = {
      host: '10.0.0.50',
      community: 'private',
      version: '2c',
      protocol: 'snmp',
    };
    const metrics = [
      {
        key: 'humidity',
        label: 'Humidity',
        unit: '%',
        datatype: 'float',
        enable: true,
        triggers: [{ enable: true, operator: '>', value: 80, message: 'High humidity' }],
      },
    ];

    const create = await app!.inject({
      method: 'POST',
      url: '/api/v1/devices',
      headers,
      payload: {
        name: 'Room Sensor A',
        device_template_id: 10,
        serial_number: 'SN-001',
        location: 'Rack-1',
        description: 'Wave 11 device',
        is_active: true,
        device_role: 'native_device',
        communication_config: communicationConfig,
        metrics,
        device_information_json: { Zone: 'A', Floor: '2' },
        snapshot_triggers_json: [{ metric_key: 'humidity', camera_id: 1 }],
        image: 'device_abc123.png',
      },
    });

    expect(create.statusCode).toBe(201);
    const created = create.json().data as {
      id: number;
      location: string;
      communicationConfig: typeof communicationConfig;
      metrics: typeof metrics;
      image: string;
      imageUrl: string;
      customFields: Record<string, string>;
      snapshotTriggers: unknown[];
      categoryId: number;
      deviceRole: string;
    };

    expect(created.communicationConfig).toEqual(communicationConfig);
    expect(created.metrics).toEqual(metrics);
    expect(created.image).toBe('device_abc123.png');
    expect(created.imageUrl).toBe('/api/v1/uploads/devices/device_abc123.png');
    expect(created.customFields).toEqual({ Zone: 'A', Floor: '2' });
    expect(created.snapshotTriggers).toEqual([{ metric_key: 'humidity', camera_id: 1 }]);
    expect(created.categoryId).toBe(3);
    expect(created.deviceRole).toBe('native_device');

    const read = await app!.inject({
      method: 'GET',
      url: `/api/v1/devices/${created.id}`,
      headers,
    });
    expect(read.statusCode).toBe(200);
    const detail = read.json().data as typeof created;
    expect(detail.communicationConfig).toEqual(communicationConfig);
    expect(detail.metrics).toEqual(metrics);
    expect(detail.image).toBe('device_abc123.png');

    const updatedComm = { ...communicationConfig, host: '10.0.0.99' };
    const put = await app!.inject({
      method: 'PUT',
      url: `/api/v1/devices/${created.id}`,
      headers,
      payload: {
        name: 'Room Sensor A',
        deviceTemplateId: 10,
        serialNumber: 'SN-001',
        location: 'Rack-1',
        description: 'Updated',
        isActive: true,
        deviceRole: 'native_device',
        communicationConfig: updatedComm,
        metrics,
        customFields: { Zone: 'B' },
        image: 'device_updated.png',
      },
    });
    expect(put.statusCode).toBe(200);
    const afterPut = put.json().data as typeof created;
    expect(afterPut.communicationConfig).toEqual(updatedComm);
    expect(afterPut.image).toBe('device_updated.png');
    expect(afterPut.customFields).toEqual({ Zone: 'B' });

    const patchBasic = await app!.inject({
      method: 'PATCH',
      url: `/api/v1/devices/${created.id}`,
      headers,
      payload: {
        name: 'Room Sensor A',
        deviceTemplateId: 10,
        serialNumber: 'SN-001',
        location: 'Rack-2',
        description: 'Basic patch',
        isActive: true,
      },
    });
    expect(patchBasic.statusCode).toBe(200);
    const afterPatch = patchBasic.json().data as typeof created;
    expect(afterPatch.communicationConfig).toEqual(updatedComm);
    expect(afterPatch.metrics).toEqual(metrics);
    expect(afterPatch.image).toBe('device_updated.png');
    expect(afterPatch.location).toBe('Rack-2');
  });

  it('lists devices with card image fields and filter-options', async () => {
    const { headers } = await authed();

    await app!.inject({
      method: 'POST',
      url: '/api/v1/devices',
      headers,
      payload: {
        name: 'Card Device',
        deviceTemplateId: 10,
        serialNumber: 'SN-2',
        location: 'Lab',
        image: 'card.png',
      },
    });

    const list = await app!.inject({
      method: 'GET',
      url: '/api/v1/devices?page=1&length=10&status=active&location=Lab',
      headers,
    });
    expect(list.statusCode).toBe(200);
    const body = list.json().data as {
      devices: Array<{ image: string; imageUrl: string; brand: string; model: string }>;
      pageSize: number;
      length: number;
    };
    expect(body.length).toBe(10);
    expect(body.pageSize).toBe(10);
    expect(body.devices[0].image).toBe('card.png');
    expect(body.devices[0].imageUrl).toBe('/api/v1/uploads/devices/card.png');
    expect(body.devices[0].brand).toBe('Acme');
    expect(body.devices[0].model).toBe('T-100');

    const filters = await app!.inject({
      method: 'GET',
      url: '/api/v1/devices/filter-options',
      headers,
    });
    expect(filters.statusCode).toBe(200);
    expect(filters.json().data.locations).toContain('Lab');

    const sources = await app!.inject({
      method: 'GET',
      url: '/api/v1/devices/metric-sources',
      headers,
    });
    expect(sources.statusCode).toBe(200);
    expect(sources.json().data.devices[0].metrics[0].key).toBe('temperature');
  });

  it('returns metric keys for a device', async () => {
    const { headers } = await authed();
    const create = await app!.inject({
      method: 'POST',
      url: '/api/v1/devices',
      headers,
      payload: {
        name: 'Metric Device',
        deviceTemplateId: 10,
        metrics: [{ key: 'temp', label: 'Temp', unit: 'C', datatype: 'float', enable: true }],
      },
    });
    const id = create.json().data.id as number;
    const metrics = await app!.inject({
      method: 'GET',
      url: `/api/v1/devices/${id}/metrics`,
      headers,
    });
    expect(metrics.statusCode).toBe(200);
    const data = metrics.json().data as { keys: Array<{ key: string }>; metrics: unknown[] };
    expect(data.keys[0].key).toBe('temp');
    expect(Array.isArray(data.metrics)).toBe(true);
  });

  it('round-trips metric wizard payload: channels, duration, calc, snapshot, LoRaWAN', async () => {
    const { headers } = await authed();

    const communicationConfig = {
      protocol: 'lorawan',
      application_server: 'https://chirp.example',
      device_profile_id: '82b307a1-3ffd-4840-a0af-6e4587d43e9b',
      dev_eui: 'A84041AABBCCDDEE',
      app_eui: 'A840410000000100',
      app_key: '00112233445566778899AABBCCDDEEFF',
      netskey: '',
      appskey: '',
    };
    const metrics = [
      {
        key: 'calc_avg',
        label: 'Avg Temp',
        unit: 'C',
        datatype: 'float',
        enable: true,
        type: 'calc',
        calc: {
          expression: '{10.temperature}',
          inputs: [{ device_id: 10, metric_key: 'temperature' }],
        },
        triggers: [
          {
            enable: true,
            operator: '>',
            value: 40,
            message: 'Hot',
            severity: 'warning',
            repeat_interval: 15,
            channels: [7, '8'],
            duration: [{ days: [1, 2, 3, 4, 5], start: '08:00', end: '18:00' }],
            clear_condition: { operator: '<', value: 35, message: 'OK' },
          },
        ],
        normal_condition: { operator: '<=', value: 40, channels: [7] },
      },
    ];
    const snapshotTriggers = [
      {
        enable: true,
        source_device_id: null,
        source_metric_key: 'calc_avg',
        source_trigger_index: 0,
        cooldown_sec: 60,
      },
    ];

    const create = await app!.inject({
      method: 'POST',
      url: '/api/v1/devices',
      headers,
      payload: {
        name: 'LoRa Wizard Device',
        deviceTemplateId: 11,
        serialNumber: 'LW-9',
        location: 'Roof',
        description: 'wizard',
        isActive: true,
        deviceRole: 'native_device',
        communicationConfig,
        metrics,
        customFields: { Site: 'A' },
        snapshotTriggers,
        image: 'lora.png',
      },
    });
    expect(create.statusCode).toBe(201);
    const created = create.json().data as {
      id: number;
      protocol: string;
      brand: string;
      model: string;
      imageUrl: string;
      templateImage: string | null;
      deviceRole: string;
      communicationConfig: typeof communicationConfig;
      metrics: typeof metrics;
      snapshotTriggers: typeof snapshotTriggers;
      customFields: Record<string, string>;
    };
    expect(created.protocol).toBe('lorawan');
    expect(created.brand).toBe('Semtech');
    expect(created.model).toBe('LW-1');
    expect(created.imageUrl).toBe('/api/v1/uploads/devices/lora.png');
    expect(created.deviceRole).toBe('native_device');
    expect(created.communicationConfig).toMatchObject(communicationConfig);
    expect(created.metrics).toEqual(metrics);
    expect(created.snapshotTriggers).toEqual(snapshotTriggers);
    expect(created.customFields).toEqual({ Site: 'A' });

    const read = await app!.inject({
      method: 'GET',
      url: `/api/v1/devices/${created.id}`,
      headers,
    });
    expect(read.statusCode).toBe(200);
    const detail = read.json().data as typeof created;
    expect(detail.communicationConfig.application_server).toBe('https://chirp.example');
    expect(detail.communicationConfig.device_profile_id).toBe(
      '82b307a1-3ffd-4840-a0af-6e4587d43e9b',
    );
    expect((detail.metrics[0] as { triggers: Array<{ channels: unknown }> }).triggers[0].channels).toEqual([
      7,
      '8',
    ]);
    expect(detail.snapshotTriggers).toEqual(snapshotTriggers);

    const sources = await app!.inject({
      method: 'GET',
      url: '/api/v1/devices/metric-sources',
      headers,
    });
    expect(sources.statusCode).toBe(200);
    const sourceBody = sources.json().data as {
      devices: Array<{ metrics: Array<{ key: string; triggers?: Array<{ channels?: unknown }> }> }>;
      channels: Array<{ id: number; name: string }>;
    };
    const wizardMetric = sourceBody.devices
      .flatMap((d) => d.metrics)
      .find((m) => m.key === 'calc_avg');
    expect(wizardMetric?.triggers?.[0]?.channels).toEqual([7, '8']);
    expect(sourceBody.channels[0].id).toBe(7);
  });

  it('maps gateway endpoint metrics and soft-deletes', async () => {
    const { headers } = await authed();

    const gateway = await app!.inject({
      method: 'POST',
      url: '/api/v1/devices',
      headers,
      payload: {
        name: 'Gateway A',
        deviceTemplateId: 10,
        deviceRole: 'gateway',
        metrics: [{ key: 'gw_temp', label: 'GW Temp', enable: true }],
      },
    });
    expect(gateway.statusCode).toBe(201);
    const gatewayId = gateway.json().data.id as number;

    const endpoint = await app!.inject({
      method: 'POST',
      url: '/api/v1/devices',
      headers,
      payload: {
        name: 'Endpoint B',
        deviceTemplateId: 10,
        deviceRole: 'endpoint',
        gatewayDeviceId: gatewayId,
        metrics: [
          {
            key: 'room_temp',
            label: 'Room',
            enable: true,
            source: { gateway_metric_key: 'gw_temp' },
          },
        ],
      },
    });
    expect(endpoint.statusCode).toBe(201);
    const ep = endpoint.json().data as {
      id: number;
      deviceRole: string;
      gatewayDeviceId: number;
      communicationConfig: { protocol: string; source: { gateway_device_id: number } };
      metrics: Array<{
        protocol: string;
        type: string;
        source: { gateway_device_id: number; gateway_metric_key: string };
      }>;
    };
    expect(ep.deviceRole).toBe('endpoint');
    expect(ep.gatewayDeviceId).toBe(gatewayId);
    expect(ep.communicationConfig.protocol).toBe('gateway_endpoint');
    expect(ep.communicationConfig.source.gateway_device_id).toBe(gatewayId);
    expect(ep.metrics[0].protocol).toBe('gateway_endpoint');
    expect(ep.metrics[0].type).toBe('gateway_mapped');
    expect(ep.metrics[0].source.gateway_device_id).toBe(gatewayId);
    expect(ep.metrics[0].source.gateway_metric_key).toBe('gw_temp');

    const card = await app!.inject({
      method: 'GET',
      url: '/api/v1/devices?page=1&pageSize=12',
      headers,
    });
    expect(card.statusCode).toBe(200);
    expect(card.json().data.pageSize).toBe(12);
    expect(card.json().data.length).toBe(12);

    const del = await app!.inject({
      method: 'DELETE',
      url: `/api/v1/devices/${ep.id}`,
      headers,
    });
    expect(del.statusCode).toBe(200);
    const gone = await app!.inject({
      method: 'GET',
      url: `/api/v1/devices/${ep.id}`,
      headers,
    });
    expect(gone.statusCode).toBe(404);
  });
});
