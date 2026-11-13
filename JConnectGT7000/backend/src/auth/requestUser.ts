import type { FastifyReply, FastifyRequest } from 'fastify';
import { readAccessToken } from './tokens.js';

export function requestUserId(request: FastifyRequest, secret: string): number | null {
  const header = request.headers.authorization;
  if (!header?.startsWith('Bearer ')) {
    return null;
  }
  const token = header.slice('Bearer '.length).trim();
  const payload = readAccessToken(token, secret);
  return payload?.sub ?? null;
}

export function sendUnauthorized(reply: FastifyReply) {
  return reply.code(401).send({
    error: { code: 'UNAUTHORIZED', message: 'Sign in required' },
  });
}
