export const inventoryApi = {
  checkHealth() {
    return fetch('/api/v1/inventory/health', { signal: AbortSignal.timeout(3000) })
      .then((res) => res.ok)
      .catch(() => false);
  }
};
