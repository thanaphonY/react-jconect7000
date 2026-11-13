import type { FastifyInstance } from 'fastify';
import { afterEach, describe, expect, it } from 'vitest';
import { signAccessToken } from '../src/auth/tokens.js';
import { buildApp } from '../src/app.js';
import type { Queryable } from '../src/db.js';

const SECRET = 'test-secret-value!!';

interface LayoutRow {
  id: number;
  name: string;
  description: string;
  sort_order: number | null;
  created_by: number | null;
  created_by_name: string;
}

interface ItemRow {
  id: number;
  layout_id: number;
  widget_id: number | null;
  x: number;
  y: number;
  w: number;
  h: number;
  config: Record<string, unknown>;
  widget_name: string | null;
  widget_type: string | null;
}

function makeStore() {
  let nextItemId = 100;
  const layouts: LayoutRow[] = [
    {
      id: 1,
      name: 'Home',
      description: 'main',
      sort_order: 10,
      created_by: 1,
      created_by_name: 'Supperadmin',
    },
    {
      id: 2,
      name: 'Dashboard 2',
      description: '',
      sort_order: 20,
      created_by: 1,
      created_by_name: 'Supperadmin',
    },
  ];
  let items: ItemRow[] = [
    {
      id: 10,
      layout_id: 1,
      widget_id: 16,
      x: 0,
      y: 0,
      w: 8,
      h: 5,
      config: { label: 'Alarm Summary' },
      widget_name: 'Alarm Summary',
      widget_type: 'alarm_summary',
    },
  ];
  let homeLayoutId: number | null = 1;

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

    if (text.includes('FROM user_dashboard_homes')) {
      return { rows: homeLayoutId ? [{ layout_id: homeLayoutId }] : [] };
    }

    if (text.includes('INSERT INTO user_dashboard_homes')) {
      homeLayoutId = Number(values[1]);
      return { rows: [] };
    }

    if (text.includes('FROM dashboard_layouts dl') && text.includes('WHERE dl.id')) {
      const id = Number(values[0]);
      const row = layouts.find((layout) => layout.id === id);
      return { rows: row ? [row] : [] };
    }

    if (text.includes('FROM dashboard_layouts dl') && text.includes('ORDER BY')) {
      return {
        rows: [...layouts].sort((a, b) => {
          const ao = a.sort_order ?? Number.MAX_SAFE_INTEGER;
          const bo = b.sort_order ?? Number.MAX_SAFE_INTEGER;
          if (ao !== bo) return ao - bo;
          return a.id - b.id;
        }),
      };
    }

    if (text.includes('INSERT INTO dashboard_layouts')) {
      const id = Math.max(...layouts.map((layout) => layout.id)) + 1;
      layouts.push({
        id,
        name: String(values[0]),
        description: String(values[1] ?? ''),
        sort_order: (values[3] as number | null) ?? null,
        created_by: Number(values[2]),
        created_by_name: 'Supperadmin',
      });
      return { rows: [{ id }] };
    }

    if (text.includes('UPDATE dashboard_layouts') && text.includes('sort_order = $2') && !text.includes('name =')) {
      const id = Number(values[0]);
      const sortOrder = Number(values[1]);
      const row = layouts.find((layout) => layout.id === id);
      if (row) row.sort_order = sortOrder;
      return { rows: [] };
    }

    if (text.includes('UPDATE dashboard_layouts') && text.includes('name =')) {
      const id = Number(values[0]);
      const row = layouts.find((layout) => layout.id === id);
      if (row) {
        row.name = String(values[1]);
        row.description = String(values[2] ?? '');
        row.sort_order = (values[3] as number | null) ?? null;
      }
      return { rows: [] };
    }

    if (text.includes('DELETE FROM dashboard_layout_items WHERE layout_id')) {
      const layoutId = Number(values[0]);
      items = items.filter((item) => item.layout_id !== layoutId);
      return { rows: [] };
    }

    if (text.includes('DELETE FROM user_dashboard_homes WHERE layout_id')) {
      const layoutId = Number(values[0]);
      if (homeLayoutId === layoutId) homeLayoutId = null;
      return { rows: [] };
    }

    if (text.includes('DELETE FROM dashboard_layouts WHERE id')) {
      const id = Number(values[0]);
      const index = layouts.findIndex((layout) => layout.id === id);
      if (index >= 0) layouts.splice(index, 1);
      return { rows: [] };
    }

    if (text.includes('INSERT INTO dashboard_layout_items')) {
      const layoutId = Number(values[0]);
      const widgetId = values[1] == null ? null : Number(values[1]);
      const configRaw = values[6];
      const config =
        typeof configRaw === 'string'
          ? (JSON.parse(configRaw) as Record<string, unknown>)
          : ((configRaw as Record<string, unknown>) ?? {});
      const id = nextItemId++;
      items.push({
        id,
        layout_id: layoutId,
        widget_id: widgetId,
        x: Number(values[2]),
        y: Number(values[3]),
        w: Number(values[4]),
        h: Number(values[5]),
        config,
        widget_name: widgetId === 16 ? 'Alarm Summary' : widgetId === 20 ? 'Map View' : 'Widget',
        widget_type:
          widgetId === 16 ? 'alarm_summary' : widgetId === 20 ? 'map_view' : String(config.widget_type ?? ''),
      });
      return { rows: [{ id }] };
    }

    if (text.includes('FROM dashboard_layout_items')) {
      const layoutId = Number(values[0]);
      return {
        rows: items
          .filter((item) => item.layout_id === layoutId)
          .sort((a, b) => a.y - b.y || a.x - b.x)
          .map((item) => ({
            id: item.id,
            widget_id: item.widget_id,
            x: item.x,
            y: item.y,
            w: item.w,
            h: item.h,
            config: item.config,
            widget_name: item.widget_name,
            widget_type: item.widget_type,
          })),
      };
    }

    if (text.includes('alarm_state')) {
      return { rows: [] };
    }

    return { rows: [] };
  };

  return { query, layouts, getItems: () => items };
}

function appWith(query: Queryable['query']): Promise<FastifyInstance> {
  return buildApp({ pool: { query }, authSecret: SECRET, logger: false });
}

describe('dashboards', () => {
  let app: FastifyInstance | undefined;

  afterEach(async () => {
    await app?.close();
  });

  it('returns the saved home widgets for a signed-in user', async () => {
    const store = makeStore();
    app = await appWith(store.query);
    const token = signAccessToken(1, SECRET);
    const response = await app.inject({
      method: 'GET',
      url: '/api/v1/home',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(response.statusCode).toBe(200);
    const body = response.json();
    expect(body.data.layout.name).toBe('Home');
    expect(body.data.items.map((item: { name: string }) => item.name)).toEqual(['Alarm Summary']);
  });

  it('rejects the home layout without a token', async () => {
    const store = makeStore();
    app = await appWith(store.query);
    const response = await app.inject({ method: 'GET', url: '/api/v1/home' });
    expect(response.statusCode).toBe(401);
  });

  it('lists dashboards with creator, isHome, and sortOrder', async () => {
    const store = makeStore();
    app = await appWith(store.query);
    const token = signAccessToken(1, SECRET);
    const response = await app.inject({
      method: 'GET',
      url: '/api/v1/dashboards',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(response.statusCode).toBe(200);
    const body = response.json();
    expect(body.data.homeLayoutId).toBe(1);
    expect(body.data.layouts[0]).toMatchObject({
      id: 1,
      creator: 'Supperadmin',
      isHome: true,
      sortOrder: 10,
    });
    expect(body.data.layouts[1].isHome).toBe(false);
  });

  it('saves layout items (DELETE+INSERT) and reads them back', async () => {
    const store = makeStore();
    app = await appWith(store.query);
    const token = signAccessToken(1, SECRET);
    const headers = { authorization: `Bearer ${token}` };

    const save = await app.inject({
      method: 'PUT',
      url: '/api/v1/dashboards/1/layout',
      headers,
      payload: {
        items: [
          {
            widget_id: 16,
            widget_type: 'alarm_summary',
            x: 0,
            y: 0,
            w: 6,
            h: 4,
            config: { label: 'Alarms', show_total: '1' },
          },
          {
            widget_id: 20,
            widget_type: 'map_view',
            x: 6,
            y: 0,
            w: 6,
            h: 8,
            config: { background: 'map_abc.png', items: [{ x: 10, y: 20 }] },
          },
        ],
      },
    });
    expect(save.statusCode).toBe(200);
    const saved = save.json();
    expect(saved.data.items).toHaveLength(2);
    expect(saved.data.items[0]).toMatchObject({
      widget_id: 16,
      type: 'alarm_summary',
      x: 0,
      y: 0,
      w: 6,
      h: 4,
      config: { label: 'Alarms', show_total: '1' },
    });
    expect(saved.data.items[1]).toMatchObject({
      widgetId: 20,
      widgetType: 'map_view',
      config: { background: 'map_abc.png', items: [{ x: 10, y: 20 }] },
    });

    const roundTrip = await app.inject({
      method: 'GET',
      url: '/api/v1/dashboards/1',
      headers,
    });
    expect(roundTrip.statusCode).toBe(200);
    const body = roundTrip.json();
    expect(body.data.items).toHaveLength(2);
    expect(body.data.items.map((item: { widget_id: number }) => item.widget_id)).toEqual([16, 20]);
    expect(body.data.items[1].config.background).toBe('map_abc.png');
  });

  it('updates sort order in steps of 10', async () => {
    const store = makeStore();
    app = await appWith(store.query);
    const token = signAccessToken(1, SECRET);
    const response = await app.inject({
      method: 'PUT',
      url: '/api/v1/dashboards/sort-order',
      headers: { authorization: `Bearer ${token}` },
      payload: { order: [2, 1] },
    });
    expect(response.statusCode).toBe(200);
    const body = response.json();
    expect(body.data.layouts.map((row: { id: number; sortOrder: number }) => [row.id, row.sortOrder])).toEqual([
      [2, 10],
      [1, 20],
    ]);
  });
});
