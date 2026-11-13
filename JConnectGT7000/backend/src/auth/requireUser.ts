import type { FastifyReply, FastifyRequest } from 'fastify';
import { readAccessToken } from './tokens.js';

export function requireUserId(request: FastifyRequest, reply: FastifyReply, secret: string): number | null {
  const header = request.headers.authorization;
  const token = header?.startsWith('Bearer ') ? header.slice('Bearer '.length).trim() : '';
  const payload = token ? readAccessToken(token, secret) : null;
  if (!payload) {
    void reply.code(401).send({
      error: { code: 'UNAUTHORIZED', message: 'Sign in required' },
    });
    return null;
  }
  return payload.sub;
}
