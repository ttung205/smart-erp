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
          sku: s.sku || p?.sku || `IC-${s.product_id}`,
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

    // Dòng tồn kho do inventory-service tạo bất đồng bộ khi nhận event ProductCreated (có thể chậm hoặc chưa chạy
    // nếu inventory đang tắt), nên giao diện không tự vẽ dòng tồn giả mà chỉ tải lại từ backend.
    for (const delayMs of [1500, 6000]) {
      setTimeout(() => {
        fetchStocks().catch(err => console.warn('[StockStore] Không tải lại được tồn kho sau khi có sản phẩm mới:', err))
      }, delayMs)
    }
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
