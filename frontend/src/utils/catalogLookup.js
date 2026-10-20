import { catalogApi } from '../api/catalog.api';
import { inventoryApi } from '../api/inventory.api';

/**
 * Cache dùng chung cho danh mục sản phẩm và tồn kho bán được.
 * Một trang có thể có nhiều ItemDataTable: tất cả cùng dùng một request đang chạy
 * và kết quả được giữ ngắn hạn để mở lại dialog "Tìm & Thêm" không phải tải lại.
 */
const CACHE_TTL_MS = 60 * 1000;
const STOCK_PAGE_SIZE = 1000; // inventory-service giới hạn tối đa 1000 dòng/trang
const cache = new Map();

function cached(key, loader, force) {
  const entry = cache.get(key);
  if (!force && entry && Date.now() - entry.at < CACHE_TTL_MS) return entry.promise;

  const promise = loader().catch((err) => {
    cache.delete(key);
    throw err;
  });
  cache.set(key, { promise, at: Date.now() });
  return promise;
}

export function getCatalogProducts({ force = false } = {}) {
  return cached('catalog', async () => (await catalogApi.getProducts()).data || [], force);
}

/** Tải đủ mọi trang tồn kho (mỗi dòng là 1 cặp kho × sản phẩm nên dễ vượt 1000 dòng). */
export function getStockRows({ force = false } = {}) {
  return cached('stocks', async () => {
    const first = await inventoryApi.getStocks({ page: 1, pageSize: STOCK_PAGE_SIZE });
    const totalPages = Number(first.meta?.pagination?.total_pages || 1);
    if (totalPages <= 1) return first.data || [];

    const rest = await Promise.all(
      Array.from({ length: totalPages - 1 }, (_, i) => inventoryApi.getStocks({ page: i + 2, pageSize: STOCK_PAGE_SIZE }))
    );
    return [first, ...rest].flatMap((res) => res.data || []);
  }, force);
}

/** Gọi sau khi tạo/sửa sản phẩm hoặc nhập/xuất kho để lần mở sau lấy dữ liệu mới. */
export function invalidateCatalogLookup() {
  cache.clear();
}
