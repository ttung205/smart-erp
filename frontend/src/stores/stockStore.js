import { defineStore } from 'pinia'
import { ref } from 'vue'
import { inventoryApi } from '../api/inventory.api'
import { useCatalogStore } from './catalogStore'

export const useStockStore = defineStore('stock', () => {
  const stocks = ref([])
  const warehouses = ref([])
  const loading = ref(false)
  const isLoaded = ref(false)

  const fetchWarehouses = async () => {
    if (warehouses.value.length > 0) return warehouses.value
    try {
      const res = await inventoryApi.getWarehouses()
      warehouses.value = res.data || []
      return warehouses.value
    } catch (err) {
      console.error('[StockStore] Lỗi tải kho:', err)
      return []
    }
  }

  const fetchStocks = async (warehouseId = null) => {
    loading.value = true
    try {
      const catalogStore = useCatalogStore()
      await Promise.all([
        catalogStore.fetchProducts(),
        fetchWarehouses()
      ])

      const productMap = new Map()
      catalogStore.products.forEach(p => productMap.set(Number(p.id), p))

      const params = { pageSize: 1000 }
      if (warehouseId) params.warehouseId = warehouseId
      const res = await inventoryApi.getStocks(params)
      const rawStocks = res.data || []

      stocks.value = rawStocks.map(s => {
        const p = productMap.get(Number(s.product_id))
        return {
          ...s,
          sku: s.sku || p?.sku || `SKU-${s.product_id}`,
          product_name: p?.item_description || p?.designation || p?.name || `Sản phẩm #${s.product_id}`,
          unit: p?.unit || 'Chiếc',
          brand: p?.brand || '',
          category: p?.category || '',
          origin: s.origin || p?.com || 'Việt Nam'
        }
      })
      isLoaded.value = true
      return stocks.value
    } catch (err) {
      console.error('[StockStore] Lỗi tải danh sách tồn kho:', err)
      return []
    } finally {
      loading.value = false
    }
  }

  const syncNewProduct = (product) => {
    if (!product) return
    const catalogStore = useCatalogStore()
    catalogStore.addProduct(product)

    const productId = Number(product.product_id || product.id)
    const sku = product.sku
    const name = product.name || product.designation
    const origin = product.origin || product.com || 'Việt Nam'

    // Tự động khởi tạo ngay lập tức các dòng tồn kho trên giao diện cho từng kho
    const targetWarehouses = warehouses.value.length > 0
      ? warehouses.value
      : [
          { id: 1, code: 'WH-HN-01', name: 'Kho Tổng Miền Bắc (Hà Nội)', can_sell: true },
          { id: 2, code: 'WH-HCM-01', name: 'Kho Trung Tâm Miền Nam (TP.HCM)', can_sell: true },
          { id: 3, code: 'WH-DN-01', name: 'Kho Trung Chuyển Miền Trung (Đà Nẵng)', can_sell: true }
        ]

    for (const w of targetWarehouses) {
      const exists = stocks.value.some(
        s => Number(s.product_id) === productId && Number(s.warehouse_id) === Number(w.id)
      )
      if (!exists) {
        stocks.value.unshift({
          id: `realtime_${productId}_${w.id}`,
          product_id: productId,
          sku,
          product_name: name,
          warehouse_id: w.id,
          warehouse_code: w.code,
          warehouse_name: w.name,
          origin,
          quantity_on_hand: 0,
          quantity_reserved: 0,
          available: 0,
          sales_available: 0,
          min_stock: Number(product.min_stock || 0),
          is_low_stock: false,
          can_sell: w.can_sell !== false,
          updated_at: new Date().toISOString()
        })
      }
    }

    // Đồng bộ lại với backend sau 1.5s để có ID chính thức từ DB
    setTimeout(() => {
      fetchStocks().catch(() => {})
    }, 1500)
  }

  const applyStockUpdated = (payload) => {
    if (!payload) return
    // Cập nhật khi có biến động giữ chỗ hoặc xuất kho
    if (Array.isArray(payload.reservations)) {
      payload.reservations.forEach(r => {
        const item = stocks.value.find(
          s => Number(s.product_id) === Number(r.product_id) && Number(s.warehouse_id) === Number(r.warehouse_id)
        )
        if (item) {
          item.quantity_reserved = (item.quantity_reserved || 0) + Number(r.quantity_reserved || 0)
          item.available = Math.max(0, (item.quantity_on_hand || 0) - item.quantity_reserved)
          if (item.can_sell) item.sales_available = item.available
        }
      })
    }
  }

  return {
    stocks,
    warehouses,
    loading,
    isLoaded,
    fetchWarehouses,
    fetchStocks,
    syncNewProduct,
    applyStockUpdated
  }
})
