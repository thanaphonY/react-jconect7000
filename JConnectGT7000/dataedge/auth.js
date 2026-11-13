// Shared auth for dataedge — must match backend JWT (same secret + claim shape).
// Token claims: { sub, username, roles, site_ids: number[], iat }
const jwt = require('jsonwebtoken');

const JWT_SECRET = String(process.env.JWT_SECRET || 'dev-secret');

// When true, WS connections without a valid token are rejected.
// Default ON (secure). Set DATAEDGE_WS_AUTH_DISABLED=true only for isolated local dev.
const WS_AUTH_DISABLED =
  String(process.env.DATAEDGE_WS_AUTH_DISABLED || '').toLowerCase() === 'true';

/** Pull `token` from a WS upgrade request URL (?token=...). */
function tokenFromRequest(req) {
  try {
    const url = new URL(req.url || '', 'http://localhost');
    const t = url.searchParams.get('token');
    if (t) return t;
  } catch (_e) {
    /* ignore */
  }
  const h = req.headers?.authorization;
  if (h) {
    const m = String(h).match(/^Bearer\s+(.+)$/i);
    if (m) return m[1];
  }
  return null;
}

/** Verify a JWT and return its payload, or null if invalid. */
function verifyToken(token) {
  if (!token) return null;
  try {
    return jwt.verify(token, JWT_SECRET);
  } catch (_e) {
    return null;
  }
}

function userSiteIds(user) {
  const raw = user?.site_ids;
  return Array.isArray(raw) ? raw.map((x) => Number(x)).filter((n) => Number.isFinite(n)) : [];
}

/**
 * Site-scope check matching backend RBAC:
 * - empty site_ids claim = superuser (v1 dev), allow all
 * - devices with null siteId are global (allowed)
 * - otherwise device.siteId must be in the user's site_ids
 */
function canAccessSite(user, siteId) {
  const ids = userSiteIds(user);
  if (ids.length === 0) return true;
  if (siteId == null) return true;
  return ids.includes(Number(siteId));
}

module.exports = {
  JWT_SECRET,
  WS_AUTH_DISABLED,
  tokenFromRequest,
  verifyToken,
  userSiteIds,
  canAccessSite,
};
