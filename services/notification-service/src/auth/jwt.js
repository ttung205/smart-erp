import { createHmac, timingSafeEqual } from 'node:crypto';

const decode = (part) => JSON.parse(Buffer.from(part, 'base64url').toString('utf8'));

/**
 * Xác thực JWT HS256 do identity-service cấp (cùng quy tắc với AuthenticateJwt bên các service PHP).
 * Trả về actor { id, name, email, roles, permissions } hoặc null nếu token không hợp lệ.
 */
export function verifyToken(token, secret) {
  if (!token || !secret) return null;
  const parts = token.split('.');
  if (parts.length !== 3) return null;

  const [encodedHeader, encodedPayload, signature] = parts;
  let header;
  let payload;
  try {
    header = decode(encodedHeader);
    payload = decode(encodedPayload);
  } catch {
    return null;
  }
  if (header.alg !== 'HS256') return null;

  const expected = createHmac('sha256', secret).update(`${encodedHeader}.${encodedPayload}`).digest();
  const actual = Buffer.from(signature, 'base64url');
  if (actual.length !== expected.length || !timingSafeEqual(actual, expected)) return null;

  if (payload.iss !== 'identity-service' || !payload.sub || Number(payload.exp || 0) <= Math.floor(Date.now() / 1000)) {
    return null;
  }

  return {
    id: Number(payload.sub),
    name: String(payload.name || ''),
    email: String(payload.email || ''),
    roles: Array.isArray(payload.roles) ? payload.roles : [],
    permissions: Array.isArray(payload.permissions) ? payload.permissions : [],
  };
}

export function bearerToken(header) {
  const match = /^Bearer\s+(\S+)$/i.exec(header || '');
  return match ? match[1] : null;
}

/** Express middleware: gắn req.actor hoặc trả 401. */
export function requireAuth(secret) {
  return (req, res, next) => {
    const actor = verifyToken(bearerToken(req.headers.authorization), secret);
    if (!actor) {
      return res.status(401).json({ success: false, error: { code: 'AUTH_TOKEN_INVALID', message: 'Vui lòng đăng nhập để tiếp tục.' } });
    }
    req.actor = actor;
    return next();
  };
}
