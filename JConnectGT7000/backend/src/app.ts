import cookie from '@fastify/cookie';
import cors from '@fastify/cors';
import multipart from '@fastify/multipart';
import Fastify, { type FastifyInstance } from 'fastify';
import type { Queryable } from './db.js';
import { registerAlarmRoutes } from './routes/alarms.js';
import { registerAuthRoutes } from './routes/auth.js';
import { registerAssetRoutes, registerBrandingRoutes } from './routes/branding.js';
import { registerCalculationRoutes } from './routes/calculations.js';
import { registerCatalogRoutes } from './routes/catalog.js';
import { registerControlRoutes } from './routes/control.js';
import { registerDashboardRoutes } from './routes/dashboards.js';
import { registerHealthRoutes } from './routes/health.js';
import { registerLogRoutes } from './routes/logs.js';
import { registerLorawanRoutes } from './routes/lorawan.js';
import { registerNotificationRoutes } from './routes/notifications.js';
import { registerProfileRoutes } from './routes/profile.js';
import { registerReportRoutes } from './routes/reports.js';
import { registerServiceRoutes } from './routes/services.js';
import { registerSettingsRoutes } from './routes/settings.js';
import { registerUploadRoutes } from './routes/uploads.js';
import { registerUserRoutes } from './routes/users.js';
import { registerWidgetRoutes } from './routes/widgets.js';

const allowedOrigins = ['http://127.0.0.1:5173', 'http://localhost:5173'];

export interface BuildAppOptions {
  pool: Queryable;
  authSecret: string;
  logger?: boolean | { level?: string };
}

export async function buildApp(options: BuildAppOptions): Promise<FastifyInstance> {
  const app = Fastify({
    logger: options.logger ?? false,
  });

  await app.register(cors, {
    origin: allowedOrigins,
    credentials: true,
  });
  await app.register(cookie);
  await app.register(multipart, {
    limits: {
      // Map images allow up to 10MB (PHP upload_map_image); SVG widgets 2MB.
      fileSize: 10 * 1024 * 1024,
    },
  });

  await app.register(
    async (instance) => {
      await registerHealthRoutes(instance, options.pool);
      await registerBrandingRoutes(instance, options.pool);
      await registerAssetRoutes(instance);
      await registerAuthRoutes(instance, options.pool, options.authSecret);
      await registerProfileRoutes(instance, options.pool, options.authSecret);
      await registerUserRoutes(instance, options.pool, options.authSecret);
      await registerSettingsRoutes(instance, options.pool, options.authSecret);
      await registerDashboardRoutes(instance, options.pool, options.authSecret);
      await registerUploadRoutes(instance, options.authSecret);
      await registerAlarmRoutes(instance, options.pool, options.authSecret);
      await registerCatalogRoutes(instance, options.pool, options.authSecret);
      await registerWidgetRoutes(instance, options.pool, options.authSecret);
      await registerLogRoutes(instance, options.pool, options.authSecret);
      await registerNotificationRoutes(instance, options.pool, options.authSecret);
      await registerReportRoutes(instance, options.pool, options.authSecret);
      await registerLorawanRoutes(instance, options.pool, options.authSecret);
      await registerCalculationRoutes(instance, options.pool, options.authSecret);
      await registerControlRoutes(instance, options.pool, options.authSecret);
      await registerServiceRoutes(instance, options.pool, options.authSecret);
    },
    { prefix: '/api/v1' },
  );

  app.setNotFoundHandler((_request, reply) => {
    return reply.code(404).send({
      error: {
        code: 'NOT_FOUND',
        message: 'Not found',
      },
    });
  });

  return app;
}
