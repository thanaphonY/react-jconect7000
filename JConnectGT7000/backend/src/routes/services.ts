import type { FastifyInstance } from 'fastify';
import type { Queryable } from '../db.js';

/** Legacy service routes. Control moved to routes/control.ts; calculations to routes/calculations.ts. */
export async function registerServiceRoutes(
  _app: FastifyInstance,
  _db: Queryable,
  _authSecret: string,
): Promise<void> {
  // Intentionally empty — kept so older imports do not break mid-wave.
}
