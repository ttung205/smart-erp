/**
 * Xác định danh sách user nhận thông báo từ khai báo của rule:
 *   { userIds: [..], permissions: [..], excludeUserIds: [..] }
 * Người nhận theo quyền được tra ở identity-service và cache theo bộ quyền.
 * identity-service lỗi thì ném lỗi để message được retry, không âm thầm bỏ thông báo.
 */
export class RecipientResolver {
  constructor({ identityUrl, internalServiceKey, cacheTtlMs, fetchImpl = fetch }) {
    this.identityUrl = identityUrl;
    this.internalServiceKey = internalServiceKey;
    this.cacheTtlMs = cacheTtlMs;
    this.fetch = fetchImpl;
    this.cache = new Map();
  }

  async resolve({ userIds = [], permissions = [], excludeUserIds = [] }) {
    const ids = new Set(userIds.filter((id) => Number.isInteger(id) && id > 0));
    if (permissions.length > 0) {
      for (const id of await this.usersWithPermissions(permissions)) ids.add(id);
    }
    for (const id of excludeUserIds) ids.delete(id);
    return [...ids].sort((a, b) => a - b);
  }

  async usersWithPermissions(permissions) {
    const key = [...permissions].sort().join(',');
    const cached = this.cache.get(key);
    if (cached && cached.expiresAt > Date.now()) return cached.ids;

    const url = `${this.identityUrl}/api/users/by-permission?permissions=${encodeURIComponent(key)}`;
    // Consumer chạy nền, không có token người dùng nên xác thực với identity bằng khóa nội bộ
    const headers = { Accept: 'application/json', 'X-Internal-Service-Key': this.internalServiceKey };
    const response = await this.fetch(url, { headers, signal: AbortSignal.timeout(5000) });
    if (!response.ok) {
      throw new Error(`identity-service trả về ${response.status} khi tra người nhận theo quyền [${key}]`);
    }
    const body = await response.json();
    const ids = (body.data || []).map((user) => Number(user.id)).filter((id) => id > 0);
    this.cache.set(key, { ids, expiresAt: Date.now() + this.cacheTtlMs });
    return ids;
  }
}
