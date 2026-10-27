import { timingSafeEqual } from 'node:crypto';
import { bearerToken, verifyToken } from '../auth/jwt.js';

/**
 * Phân quyền API tra cứu email (không còn API gửi: email chỉ được gửi qua event). Hai loại người gọi:
 * - Người dùng: JWT hợp lệ + ít nhất một quyền nghiệp vụ có nhu cầu gửi mail (báo giá, hỏi hàng).
 * - Internal: service khác trong mạng Docker gửi X-Internal-Service-Key (Kong luôn xoá header này từ client bên ngoài).
 */

/** Xem trạng thái một email job (purchase-service đọc lại kết quả gửi hỏi hàng bằng token người dùng). */
export const EMAIL_STATUS_PERMISSIONS = [
  'quotation.create',
  'quotation.update',
  'quotation.manage',
  'item-inquiry.create',
  'item-inquiry.process',
  'item-inquiry.view',
];

/** Danh sách toàn bộ email đã gửi (lộ người nhận của người khác) chỉ dành cho người quản trị nghiệp vụ. */
export const EMAIL_LOG_LIST_PERMISSIONS = ['quotation.manage', 'item-inquiry.process', 'users.manage'];

function safeEqual(a, b) {
  const left = Buffer.from(String(a));
  const right = Buffer.from(String(b));
  return left.length === right.length && timingSafeEqual(left, right);
}

/** Gắn req.emailCaller = { type: 'internal' } | { type: 'user', actor } hoặc trả 401. */
export function authenticateEmailCaller({ jwtSecret, internalServiceKey }) {
  return (req, res, next) => {
    const presentedKey = req.headers['x-internal-service-key'];
    if (presentedKey) {
      // Header có mặt thì chỉ chấp nhận nếu khớp; không "rơi" sang JWT để tránh nhập nhằng
      if (internalServiceKey && safeEqual(presentedKey, internalServiceKey)) {
        req.emailCaller = { type: 'internal' };
        return next();
      }
      return res.status(401).json({ success: false, error: { code: 'AUTH_TOKEN_INVALID', message: 'Thông tin xác thực không hợp lệ.' } });
    }

    const actor = verifyToken(bearerToken(req.headers.authorization), jwtSecret);
    if (!actor) {
      return res.status(401).json({ success: false, error: { code: 'AUTH_TOKEN_INVALID', message: 'Vui lòng đăng nhập để tiếp tục.' } });
    }
    req.emailCaller = { type: 'user', actor };
    return next();
  };
}

/** Người dùng phải có ít nhất một trong các quyền; người gọi internal luôn được phép. */
export function requireAnyEmailPermission(permissions) {
  return (req, res, next) => {
    const caller = req.emailCaller;
    if (caller?.type === 'internal') return next();
    if (caller?.type === 'user' && caller.actor.permissions.some((code) => permissions.includes(code))) return next();
    return res.status(403).json({ success: false, error: { code: 'FORBIDDEN', message: 'Bạn không có quyền thực hiện thao tác này.' } });
  };
}
