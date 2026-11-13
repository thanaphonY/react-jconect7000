import type { FastifyInstance } from 'fastify';
import { afterEach, describe, expect, it } from 'vitest';
import { signAccessToken } from '../src/auth/tokens.js';
import { buildApp } from '../src/app.js';
import type { Queryable } from '../src/db.js';

const SECRET = 'test-secret-value!!';

interface TemplateRow {
  id: number;
  name: string;
  description: string;
  protocol: string;
  category_id: number;
  is_active: boolean;
  image: string | null;
  communication_config: Record<string, unknown>;
  device_information: Record<string, unknown>;
  metrics: unknown[];
  default_dashboard_layout: unknown[];
}

interface CategoryRow {
  id: number;
  name: string;
  description: string;
  icon: string;
  color: string;
  is_active: boolean;
}

function makeStore() {
  const categories: CategoryRow[] = [
    {
      id: 1,
      name: 'Power / UPS System',
      description: 'DCIM device category',
      icon: 'fa-server',
      color: '#007bff',
      is_active: true,
    },
  ];
  const templates: TemplateRow[] = [];
  let nextTemplateId = 1;
  let nextCategoryId = 2;

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

    if (text.includes('FROM device_categories WHERE name')) {
      const name = String(values[0]);
      const row = categories.find((c) => c.name === name);
      return { rows: row ? [{ id: row.id, is_active: row.is_active }] : [] };
    }

    if (text.includes('INSERT INTO device_categories')) {
      const row: CategoryRow = {
        id: nextCategoryId++,
        name: String(values[0]),
        description: String(values[1] ?? ''),
        icon: String(values[2] ?? 'fa-server'),
        color: String(values[3] ?? '#007bff'),
        is_active: Boolean(values[4] ?? true),
      };
      categories.push(row);
      return { rows: [{ id: row.id }] };
    }

    if (text.includes('FROM device_categories dc') && text.includes('WHERE dc.id')) {
      const id = Number(values[0]);
      const row = categories.find((c) => c.id === id);
      if (!row) return { rows: [] };
      return {
        rows: [
          {
            ...row,
            devices_count: 0,
            templates_count: 0,
          },
        ],
      };
    }

    if (text.includes('FROM device_templates dt') && text.includes('ORDER BY dt.id DESC')) {
      return {
        rows: templates.map((t) => ({
          ...t,
          category_name: categories.find((c) => c.id === t.category_id)?.name ?? '',
        })),
      };
    }

    if (text.includes('FROM device_templates dt') && text.includes('WHERE dt.id')) {
      const id = Number(values[0]);
      const row = templates.find((t) => t.id === id);
      if (!row) return { rows: [] };
      return {
        rows: [
          {
            ...row,
            category_name: categories.find((c) => c.id === row.category_id)?.name ?? '',
          },
        ],
      };
    }

    if (text.includes('INSERT INTO device_templates')) {
      const row: TemplateRow = {
        id: nextTemplateId++,
        name: String(values[0]),
        description: String(values[1] ?? ''),
        protocol: String(values[2]),
        category_id: Number(values[3]),
        is_active: Boolean(values[4]),
        metrics: JSON.parse(String(values[5] ?? '[]')) as unknown[],
        communication_config: JSON.parse(String(values[6] ?? '{}')) as Record<string, unknown>,
        device_information: JSON.parse(String(values[7] ?? '{}')) as Record<string, unknown>,
        default_dashboard_layout: JSON.parse(String(values[8] ?? '[]')) as unknown[],
        image: (values[9] as string | null) ?? null,
      };
      templates.push(row);
      return { rows: [{ id: row.id }] };
    }

    if (text.includes('UPDATE device_templates')) {
      const id = Number(values[0]);
      const row = templates.find((t) => t.id === id);
      if (!row) return { rows: [] };
      row.name = String(values[1]);
      row.description = String(values[2] ?? '');
      row.protocol = String(values[3]);
      row.category_id = Number(values[4]);
      row.is_active = Boolean(values[5]);
      row.metrics = JSON.parse(String(values[6] ?? '[]')) as unknown[];
      row.communication_config = JSON.parse(String(values[7] ?? '{}')) as Record<string, unknown>;
      row.device_information = JSON.parse(String(values[8] ?? '{}')) as Record<string, unknown>;
      row.default_dashboard_layout = JSON.parse(String(values[9] ?? '[]')) as unknown[];
      if (values[10] != null) row.image = String(values[10]);
      return { rows: [{ id }] };
    }

    if (text.includes('DELETE FROM device_templates')) {
      const id = Number(values[0]);
      const idx = templates.findIndex((t) => t.id === id);
      if (idx >= 0) templates.splice(idx, 1);
      return { rows: [] };
    }

    if (text.includes('FROM devices WHERE device_template_id')) {
      return { rows: [{ total: 0 }] };
    }

    if (text.includes('UPDATE device_categories SET is_active')) {
      const id = Number(values[0]);
      const row = categories.find((c) => c.id === id);
      if (row) row.is_active = true;
      return { rows: [] };
    }

    return { rows: [] };
  };

  return { query, templates, categories };
}

describe('wave 12 device-templates + dcim', () => {
  let app: FastifyInstance | undefined;

  afterEach(async () => {
    await app?.close();
  });

  it('GET /dcim-taxonomy returns PHP taxonomy entries', async () => {
    const store = makeStore();
    app = await buildApp({ pool: { query: store.query }, authSecret: SECRET, logger: false });
    const token = signAccessToken(1, SECRET);

    const response = await app.inject({
      method: 'GET',
      url: '/api/v1/dcim-taxonomy',
      headers: { authorization: `Bearer ${token}` },
    });

    expect(response.statusCode).toBe(200);
    const body = response.json() as { data: Array<{ category: string; subs: string[] }> };
    expect(body.data[0]?.category).toBe('Power');
    expect(body.data[0]?.subs).toContain('UPS System');
  });

  it('POST/GET/PATCH device-templates round-trips full fields', async () => {
    const store = makeStore();
    app = await buildApp({ pool: { query: store.query }, authSecret: SECRET, logger: false });
    const token = signAccessToken(1, SECRET);

    const create = await app.inject({
      method: 'POST',
      url: '/api/v1/device-templates',
      headers: { authorization: `Bearer ${token}` },
      payload: {
        name: 'UPS Template',
        description: 'Full form',
        protocol: 'modbus_tcp',
        dcimCategory: 'Power',
        dcimSubCategory: 'UPS System',
        communicationConfig: { host: '10.0.0.1', port: '502' },
        deviceInformation: { Brand: 'Eaton', Model: '9PX' },
        metrics: [{ key: 'load', label: 'Load', unit: '%' }],
        defaultDashboardLayout: [{ title: 'Load', type: 'gauge', metric_key: 'load', w: 4, h: 2 }],
        isActive: true,
        image: 'abc.png',
      },
    });

    expect(create.statusCode).toBe(201);
    const created = create.json().data as {
      id: number;
      imageUrl: string;
      selectedDcimCategory: string;
      selectedDcimSubCategory: string;
      deviceInformation: Record<string, unknown>;
      communicationConfig: Record<string, unknown>;
      metrics: unknown[];
      defaultDashboardLayout: unknown[];
    };
    expect(created.imageUrl).toContain('/api/v1/uploads/device-templates/abc.png');
    expect(created.selectedDcimCategory).toBe('Power');
    expect(created.selectedDcimSubCategory).toBe('UPS System');
    expect(created.deviceInformation.dcim_category).toBe('Power');
    expect(created.deviceInformation.Brand).toBe('Eaton');
    expect(created.communicationConfig).toEqual({ host: '10.0.0.1', port: '502' });
    expect(created.metrics).toHaveLength(1);
    expect(created.defaultDashboardLayout).toHaveLength(1);

    const get = await app.inject({
      method: 'GET',
      url: `/api/v1/device-templates/${created.id}`,
      headers: { authorization: `Bearer ${token}` },
    });
    expect(get.statusCode).toBe(200);
    expect(get.json().data.name).toBe('UPS Template');

    const patch = await app.inject({
      method: 'PATCH',
      url: `/api/v1/device-templates/${created.id}`,
      headers: { authorization: `Bearer ${token}` },
      payload: {
        name: 'UPS Template v2',
        description: 'Updated',
        protocol: 'snmp',
        dcimCategory: 'Environment',
        dcimSubCategory: 'Temp/Humidity Combo Sensor',
        communicationConfig: { host: '10.0.0.2', community: 'public' },
        deviceInformation: { Brand: 'Sensaphone' },
        metrics: [],
        defaultDashboardLayout: [],
        isActive: false,
      },
    });
    expect(patch.statusCode).toBe(200);
    const updated = patch.json().data as {
      name: string;
      protocol: string;
      isActive: boolean;
      selectedDcimCategory: string;
      deviceInformation: Record<string, unknown>;
    };
    expect(updated.name).toBe('UPS Template v2');
    expect(updated.protocol).toBe('snmp');
    expect(updated.isActive).toBe(false);
    expect(updated.selectedDcimCategory).toBe('Environment');
    expect(updated.deviceInformation.dcim_sub_category).toBe('Temp/Humidity Combo Sensor');
  });

  it('rejects invalid dcim selection', async () => {
    const store = makeStore();
    app = await buildApp({ pool: { query: store.query }, authSecret: SECRET, logger: false });
    const token = signAccessToken(1, SECRET);

    const response = await app.inject({
      method: 'POST',
      url: '/api/v1/device-templates',
      headers: { authorization: `Bearer ${token}` },
      payload: {
        name: 'Bad',
        protocol: 'mqtt',
        dcimCategory: 'Power',
        dcimSubCategory: 'Not A Real Sub',
      },
    });
    expect(response.statusCode).toBe(400);
    expect(response.json().error.code).toBe('INVALID_INPUT');
  });
});

describe('wave 12 widgets config normalize', () => {
  let app: FastifyInstance | undefined;

  afterEach(async () => {
    await app?.close();
  });

  it('GET/PATCH widgets expose string DB configs as JSON objects', async () => {
    const widgets: Array<{
      id: number;
      name: string;
      widget_type: string;
      config: unknown;
    }> = [
      { id: 16, name: 'Alarm Summary', widget_type: 'alarm_summary', config: '{}' },
      {
        id: 17,
        name: 'Value Box',
        widget_type: 'value_box',
        config: '{"title":"Load","unit":"%"}',
      },
    ];
    let nextId = 18;

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
      if (text.includes('FROM widgets ORDER BY')) {
        return { rows: [...widgets] };
      }
      if (text.includes('FROM widgets WHERE id')) {
        const id = Number(values[0]);
        const row = widgets.find((w) => w.id === id);
        return { rows: row ? [row] : [] };
      }
      if (text.includes('INSERT INTO widgets')) {
        const row = {
          id: nextId++,
          name: String(values[0]),
          widget_type: String(values[1]),
          config: JSON.parse(String(values[2])),
        };
        widgets.push(row);
        return { rows: [row] };
      }
      if (text.includes('UPDATE widgets')) {
        const id = Number(values[0]);
        const row = widgets.find((w) => w.id === id);
        if (!row) return { rows: [] };
        row.name = String(values[1]);
        row.widget_type = String(values[2]);
        row.config = JSON.parse(String(values[3]));
        return { rows: [row] };
      }
      return { rows: [] };
    };

    app = await buildApp({ pool: { query }, authSecret: SECRET, logger: false });
    const token = signAccessToken(1, SECRET);

    const list = await app.inject({
      method: 'GET',
      url: '/api/v1/widgets',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(list.statusCode).toBe(200);
    const listed = list.json().data as Array<{ id: number; config: unknown }>;
    const alarm = listed.find((w) => w.id === 16);
    expect(alarm?.config).toEqual({});
    expect(typeof alarm?.config).toBe('object');

    const get = await app.inject({
      method: 'GET',
      url: '/api/v1/widgets/17',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(get.statusCode).toBe(200);
    expect(get.json().data.config).toEqual({ title: 'Load', unit: '%' });

    const patch = await app.inject({
      method: 'PATCH',
      url: '/api/v1/widgets/16',
      headers: { authorization: `Bearer ${token}` },
      payload: {
        name: 'Alarm Summary',
        widgetType: 'alarm_summary',
        config: { severity: 'critical' },
      },
    });
    expect(patch.statusCode).toBe(200);
    expect(patch.json().data.config).toEqual({ severity: 'critical' });
    expect(typeof widgets.find((w) => w.id === 16)?.config).toBe('object');
  });
});
