import { ref, reactive, computed } from 'vue'
import { FALLBACK_CATALOG_PRODUCTS } from '../constants/catalogProducts.constant.js'

// Dữ liệu mẫu Fallback chuẩn SCM (khớp với DbInitializer.cs của C# Backend)
const FALLBACK_WAREHOUSES = [
  { id: 1, code: 'WH-HN-01', name: 'Kho Tổng Miền Bắc (Hà Nội)', location: 'Cụm Công Nghiệp Nam Từ Liêm, Hà Nội', is_active: true },
  { id: 2, code: 'WH-HCM-01', name: 'Kho Trung Tâm Miền Nam (TP.HCM)', location: 'KCN Tân Bình, TP.HCM', is_active: true },
  { id: 3, code: 'WH-DN-01', name: 'Kho Trung Chuyển Miền Trung (Đà Nẵng)', location: 'KCN Hòa Khánh, Liên Chiểu, Đà Nẵng', is_active: true }
]

// Sinh dữ liệu tồn kho mẫu đầu kỳ cho toàn bộ 100 sản phẩm Catalog (mỗi mã tồn 100 chiếc)
const generateFallbackStocks = () => {
  const list = []
  FALLBACK_CATALOG_PRODUCTS.forEach(p => {
    // 1. Kho Hà Nội (Kho chính)
    list.push({
      id: list.length + 1,
      product_id: p.id,
      sku: p.sku,
      product_name: p.name || p.description,
      warehouse_id: 1,
      warehouse_code: 'WH-HN-01',
      warehouse_name: 'Kho Tổng Hà Nội',
      quantity_on_hand: 100,
      quantity_reserved: 0,
      available: 100,
      min_stock: p.min_stock || 10,
      unit: p.unit || 'Cái',
      status: 'NORMAL',
      updated_at: new Date().toISOString()
    })

    // 2. Kho TP.HCM
    list.push({
      id: list.length + 1,
      product_id: p.id,
      sku: p.sku,
      product_name: p.name || p.description,
      warehouse_id: 2,
      warehouse_code: 'WH-HCM-01',
      warehouse_name: 'Kho Trung Tâm Miền Nam (TP.HCM)',
      quantity_on_hand: 100,
      quantity_reserved: 0,
      available: 100,
      min_stock: p.min_stock || 10,
      unit: p.unit || 'Cái',
      status: 'NORMAL',
      updated_at: new Date().toISOString()
    })

    // 3. Kho Đà Nẵng
    list.push({
      id: list.length + 1,
      product_id: p.id,
      sku: p.sku,
      product_name: p.name || p.description,
      warehouse_id: 3,
      warehouse_code: 'WH-DN-01',
      warehouse_name: 'Kho Trung Chuyển Miền Trung (Đà Nẵng)',
      quantity_on_hand: 100,
      quantity_reserved: 0,
      available: 100,
      min_stock: p.min_stock || 10,
      unit: p.unit || 'Cái',
      status: 'NORMAL',
      updated_at: new Date().toISOString()
    })
  })
  return list
}

const FALLBACK_STOCKS = generateFallbackStocks()

const FALLBACK_MOVEMENTS = []

// Module-level reactive state (Shared across all tabs)
const stocks = ref([])
const warehouses = ref([])
const movements = ref([])
const catalogProducts = ref([...FALLBACK_CATALOG_PRODUCTS])
const isUsingFallback = ref(false)

const catalogMap = computed(() => {
  const map = new Map()
  catalogProducts.value.forEach(p => {
    map.set(Number(p.id), {
      sku: p.sku || `SKU-${p.id}`,
      name: p.name || p.designation || p.description || `Sản phẩm #${p.id}`,
      unit: p.unit || 'Cái',
      min_stock: p.min_stock || 10
    })
  })
  return map
})

const loading = reactive({
  stocks: false,
  warehouses: false,
  movements: false,
  catalog: false,
  action: false
})

const filters = reactive({
  warehouseId: null,
  search: '',
  lowStockOnly: false
})

const movementFilters = reactive({
  search: '',
  type: null,
  warehouseId: null
})

// Tổng Hợp Số Liệu KPI Toàn Hệ Thống
const kpiSummary = computed(() => {
  const list = stocks.value
  const uniqueSkus = new Set(list.map(s => s.sku || s.product_id)).size
  const totalOnHand = list.reduce((acc, cur) => acc + (cur.quantity_on_hand || 0), 0)
  const totalReserved = list.reduce((acc, cur) => acc + (cur.quantity_reserved || 0), 0)
  const totalAvailable = list.reduce((acc, cur) => acc + (cur.available || 0), 0)
  const lowStockCount = list.filter(s => (s.available || 0) <= (s.min_stock || 0)).length
  const totalStockRecords = list.length
  const reservedPercentage = totalOnHand > 0 ? Math.round((totalReserved / totalOnHand) * 100) : 0

  return {
    uniqueSkus,
    totalOnHand,
    totalReserved,
    totalAvailable,
    lowStockCount,
    totalStockRecords,
    reservedPercentage
  }
})

// Danh Sách Tồn Kho Đã Qua Bộ Lọc Tìm Kiếm
const filteredStocks = computed(() => {
  let result = stocks.value || []
  if (filters.warehouseId) {
    result = result.filter(s => Number(s.warehouse_id ?? s.warehouseId) === Number(filters.warehouseId))
  }
  if (filters.lowStockOnly) {
    result = result.filter(s => {
      const avail = s.available ?? ((s.quantity_on_hand ?? 0) - (s.quantity_reserved ?? 0))
      const minStk = s.min_stock ?? s.minStock ?? 0
      return avail <= minStk
    })
  }
  if (filters.search) {
    const q = filters.search.toLowerCase().trim()
    result = result.filter(s => {
      const pid = String(s.product_id ?? s.productId ?? '')
      const sku = String(s.sku || `SKU-${pid}`).toLowerCase()
      const prodName = String(s.product_name || `Sản phẩm #${pid}`).toLowerCase()
      const whName = String(s.warehouse_name || s.warehouseName || '').toLowerCase()
      const whCode = String(s.warehouse_code || s.warehouseCode || '').toLowerCase()
      const skuFormatted = `sku-${pid}`

      return sku.includes(q) ||
             prodName.includes(q) ||
             whName.includes(q) ||
             whCode.includes(q) ||
             skuFormatted.includes(q) ||
             pid === q
    })
  }
  return result
})

// Danh Sách Biến Động Thẻ Kho Đã Qua Bộ Lọc
const filteredMovements = computed(() => {
  let list = movements.value || []
  if (movementFilters.warehouseId) {
    list = list.filter(m => Number(m.warehouse_id ?? m.warehouseId) === Number(movementFilters.warehouseId))
  }
  if (movementFilters.type) {
    list = list.filter(m => m.movement_type === movementFilters.type)
  }
  if (movementFilters.search) {
    const q = movementFilters.search.toLowerCase().trim()
    list = list.filter(m => {
      const pid = String(m.product_id ?? m.productId ?? '')
      const doc = String(m.reference_doc || '').toLowerCase()
      const sku = String(m.sku || `SKU-${pid}`).toLowerCase()
      const prodName = String(m.product_name || `Sản phẩm #${pid}`).toLowerCase()
      const notes = String(m.notes || '').toLowerCase()
      const skuFormatted = `sku-${pid}`

      return doc.includes(q) ||
             sku.includes(q) ||
             prodName.includes(q) ||
             notes.includes(q) ||
             skuFormatted.includes(q) ||
             pid === q
    })
  }
  return list
})

export function useInventory() {

  // Hàm tạo Headers chuẩn hóa Microservices
  const getHeaders = () => {
    const token = localStorage.getItem('smart_erp_token') || ''
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': token ? `Bearer ${token}` : '',
      'X-Correlation-ID': `req_corr_${crypto.randomUUID ? crypto.randomUUID().replace(/-/g, '') : Date.now()}`
    }
  }

  // 1. Tải danh mục Kho Bãi
  const fetchWarehouses = async () => {
    loading.warehouses = true
    try {
      const res = await fetch('/api/v1/inventory/warehouses', {
        headers: getHeaders(),
        signal: AbortSignal.timeout(3000)
      })
      if (!res.ok) throw new Error(`HTTP ${res.status}`)
      const json = await res.json()
      if (json.success && Array.isArray(json.data)) {
        warehouses.value = json.data
        return
      }
      throw new Error('Dữ liệu không hợp lệ')
    } catch (err) {
      console.warn('[useInventory] Không thể kết nối Backend :8004, sử dụng Seed Data mẫu:', err.message)
      warehouses.value = FALLBACK_WAREHOUSES
      isUsingFallback.value = true
    } finally {
      loading.warehouses = false
    }
  }

  // Tải danh mục Sản Phẩm Master Data từ Catalog Service
  const fetchCatalogProducts = async () => {
    loading.catalog = true
    try {
      const res = await fetch('/api/v1/catalog/products?limit=500', {
        headers: getHeaders(),
        signal: AbortSignal.timeout(3000)
      })
      if (!res.ok) throw new Error(`HTTP ${res.status}`)
      const json = await res.json()
      const items = Array.isArray(json.data) ? json.data : (Array.isArray(json) ? json : null)
      if (items && items.length > 0) {
        catalogProducts.value = items.map(p => ({
          id: Number(p.id),
          sku: p.sku || p.code || `SKU-${p.id}`,
          name: p.name || p.designation || p.title || '',
          description: p.description || '',
          unit: p.unit || 'Cái',
          min_stock: Number(p.min_stock ?? p.minimum_stock ?? 10)
        }))
        return
      }
    } catch (err) {
      console.warn('[useInventory] Không thể tải danh mục Catalog từ API, dùng Fallback 100 sản phẩm:', err.message)
    } finally {
      loading.catalog = false
    }
  }

  // 2. Tải danh sách Tồn Kho
  const fetchStocks = async () => {
    loading.stocks = true
    try {
      const params = new URLSearchParams()
      params.append('pageSize', '1000')
      if (filters.warehouseId) params.append('warehouseId', filters.warehouseId)
      if (filters.lowStockOnly) params.append('lowStock', 'true')

      const url = `/api/v1/inventory/stocks${params.toString() ? `?${params.toString()}` : ''}`
      const res = await fetch(url, {
        headers: getHeaders(),
        signal: AbortSignal.timeout(3000)
      })
      if (!res.ok) throw new Error(`HTTP ${res.status}`)
      const json = await res.json()
      if (json.success && Array.isArray(json.data)) {
        // Chuẩn hóa và làm giàu dữ liệu (Enrich) SKU & Tên Sản Phẩm từ Catalog Map
        stocks.value = json.data.map(item => {
          const pid = Number(item.product_id ?? item.productId ?? 0)
          const wid = Number(item.warehouse_id ?? item.warehouseId ?? 0)
          const cat = catalogMap.value.get(pid) || {}
          const qOnHand = Number(item.quantity_on_hand ?? item.quantityOnHand ?? 0)
          const qReserved = Number(item.quantity_reserved ?? item.quantityReserved ?? 0)
          return {
            ...item,
            product_id: pid,
            warehouse_id: wid,
            sku: item.sku || cat.sku || `SKU-${pid}`,
            product_name: item.product_name || item.productName || cat.name || `Sản phẩm #${pid}`,
            unit: item.unit || cat.unit || 'Cái',
            quantity_on_hand: qOnHand,
            quantity_reserved: qReserved,
            available: Number(item.available ?? (qOnHand - qReserved)),
            min_stock: Number(item.min_stock ?? item.minStock ?? (cat.min_stock || 10))
          }
        })
        isUsingFallback.value = false
        return
      }
      throw new Error('Dữ liệu không hợp lệ')
    } catch (err) {
      console.warn('[useInventory] Không thể kết nối Backend :8004, sử dụng Stock Data mẫu:', err.message)
      // Lọc cục bộ trên Fallback Data
      let list = [...FALLBACK_STOCKS]
      if (filters.warehouseId) {
        list = list.filter(s => s.warehouse_id === Number(filters.warehouseId))
      }
      if (filters.lowStockOnly) {
        list = list.filter(s => s.available <= s.min_stock)
      }
      stocks.value = list
      isUsingFallback.value = true
    } finally {
      loading.stocks = false
    }
  }

  // 3. Tải Sổ Cái Thẻ Kho (Audit Movements)
  const fetchMovements = async () => {
    loading.movements = true
    try {
      const params = new URLSearchParams()
      params.append('pageSize', '500')
      const res = await fetch(`/api/v1/inventory/stocks/movements?${params.toString()}`, {
        headers: getHeaders(),
        signal: AbortSignal.timeout(3000)
      })
      if (!res.ok) throw new Error(`HTTP ${res.status}`)
      const json = await res.json()
      if (json.success && Array.isArray(json.data)) {
        movements.value = json.data.map(m => {
          const pid = Number(m.product_id ?? m.productId ?? 0)
          const cat = catalogMap.value.get(pid) || {}
          return {
            ...m,
            product_id: pid,
            sku: m.sku || cat.sku || `SKU-${pid}`,
            product_name: m.product_name || m.productName || cat.name || `Sản phẩm #${pid}`
          }
        })
        return
      }
      throw new Error('Dữ liệu không hợp lệ')
    } catch (err) {
      console.warn('[useInventory] Dùng Movement Fallback mẫu:', err.message)
      movements.value = FALLBACK_MOVEMENTS
    } finally {
      loading.movements = false
    }
  }

  // 4. Thao Tác Nghiệp Vụ: Nhập Kho Thực Tế (Goods Receipt)
  const receiveStock = async ({ referenceDoc, warehouseId, productId, quantity, userId = 1 }) => {
    loading.action = true
    try {
      const res = await fetch('/api/v1/inventory/stocks/receive', {
        method: 'POST',
        headers: getHeaders(),
        body: JSON.stringify({
          reference_doc: referenceDoc,
          warehouse_id: Number(warehouseId),
          product_id: Number(productId),
          quantity: Number(quantity),
          user_id: Number(userId)
        })
      })
      const json = await res.json()
      if (!res.ok || !json.success) {
        throw new Error(json.error?.message || 'Nhập kho thất bại')
      }
      await fetchStocks()
      await fetchMovements()
      return { success: true, data: json.data }
    } catch (err) {
      // Giả lập trực quan trên Mock data nếu backend offline
      if (isUsingFallback.value) {
        const target = stocks.value.find(s => s.warehouse_id === Number(warehouseId) && s.product_id === Number(productId))
        if (target) {
          target.quantity_on_hand += Number(quantity)
          target.available = target.quantity_on_hand - target.quantity_reserved
        }
        movements.value.unshift({
          id: Date.now(),
          reference_doc: referenceDoc,
          warehouse_name: warehouses.value.find(w => w.id === Number(warehouseId))?.name || 'Kho',
          product_name: target?.product_name || `Sản phẩm #${productId}`,
          movement_type: 'PO_RECEIPT',
          quantity_delta: Number(quantity),
          balance_on_hand_after: target ? target.quantity_on_hand : Number(quantity),
          balance_reserved_after: target ? target.quantity_reserved : 0,
          notes: 'Nhập kho thành công (Client Simulated)',
          created_at: new Date().toISOString()
        })
        return { success: true, message: 'Nhập kho thành công (Mock)' }
      }
      throw err
    } finally {
      loading.action = false
    }
  }

  // 5. Thao Tác Nghiệp Vụ: Giữ Chỗ Tồn Kho (Reserve)
  const reserveStock = async ({ orderId, warehouseId, productId, quantity }) => {
    loading.action = true
    try {
      const res = await fetch('/api/v1/inventory/stocks/reserve', {
        method: 'POST',
        headers: getHeaders(),
        body: JSON.stringify({
          order_id: orderId,
          warehouse_id: Number(warehouseId),
          product_id: Number(productId),
          quantity: Number(quantity)
        })
      })
      const json = await res.json()
      if (!res.ok || !json.success) {
        throw new Error(json.error?.message || 'Giữ kho thất bại (Có thể do thiếu tồn khả dụng)')
      }
      await fetchStocks()
      return { success: true, data: json.data }
    } catch (err) {
      if (isUsingFallback.value) {
        const target = stocks.value.find(s => s.warehouse_id === Number(warehouseId) && s.product_id === Number(productId))
        if (!target || target.available < Number(quantity)) {
          throw new Error('Số lượng tồn khả dụng (Available) không đủ để giữ hàng!')
        }
        target.quantity_reserved += Number(quantity)
        target.available = target.quantity_on_hand - target.quantity_reserved
        return { success: true, message: 'Giữ kho thành công (Mock)' }
      }
      throw err
    } finally {
      loading.action = false
    }
  }

  // 6. Thao Tác Nghiệp Vụ: Xuất Kho Thực Tế (Goods Issue)
  const issueStock = async ({ orderId, deliveryId, warehouseId, productId, quantity, notes }) => {
    loading.action = true
    try {
      const res = await fetch('/api/v1/inventory/stocks/issue', {
        method: 'POST',
        headers: getHeaders(),
        body: JSON.stringify({
          reference_doc: deliveryId,
          order_id: orderId || null,
          warehouse_id: Number(warehouseId),
          product_id: Number(productId),
          quantity: Number(quantity),
          notes: notes || null
        })
      })
      const json = await res.json()
      if (!res.ok || !json.success) {
        throw new Error(json.error?.message || 'Xuất kho thất bại')
      }
      await fetchStocks()
      await fetchMovements()
      return { success: true, data: json.data }
    } catch (err) {
      if (isUsingFallback.value) {
        const target = stocks.value.find(s => s.warehouse_id === Number(warehouseId) && s.product_id === Number(productId))
        if (target) {
          target.quantity_on_hand -= Number(quantity)
          target.quantity_reserved = Math.max(0, target.quantity_reserved - Number(quantity))
          target.available = target.quantity_on_hand - target.quantity_reserved
        }
        return { success: true, message: 'Xuất kho thành công (Mock)' }
      }
      throw err
    } finally {
      loading.action = false
    }
  }

  // 7. Thao Tác Nghiệp Vụ: Hủy Khóa Giữ Chỗ / Giải Phóng Giữ Kho (Release Stock Reservation)
  const releaseStock = async ({ orderId, warehouseId, productId, quantity }) => {
    loading.action = true
    try {
      const res = await fetch('/api/v1/inventory/stocks/release', {
        method: 'POST',
        headers: getHeaders(),
        body: JSON.stringify({
          order_id: orderId,
          warehouse_id: Number(warehouseId),
          product_id: Number(productId),
          quantity: Number(quantity)
        })
      })
      const json = await res.json()
      if (!res.ok || !json.success) {
        throw new Error(json.error?.message || 'Hủy giữ chỗ thất bại')
      }
      await fetchStocks()
      await fetchMovements()
      return { success: true, data: json.data }
    } catch (err) {
      if (isUsingFallback.value) {
        const target = stocks.value.find(s => s.warehouse_id === Number(warehouseId) && s.product_id === Number(productId))
        if (!target || target.quantity_reserved < Number(quantity)) {
          throw new Error('Số lượng hủy giữ chỗ vượt quá lượng đang giữ!')
        }
        target.quantity_reserved = Math.max(0, target.quantity_reserved - Number(quantity))
        target.available = target.quantity_on_hand - target.quantity_reserved
        return { success: true, message: 'Hủy giữ chỗ thành công (Mock)' }
      }
      throw err
    } finally {
      loading.action = false
    }
  }



  // 7. Thao Tác Nghiệp Vụ: Điều Chuyển Kho Nội Bộ (Transfer)
  const transferStock = async ({ transferDoc, sourceWarehouseId, targetWarehouseId, productId, quantity, notes }) => {
    loading.action = true
    try {
      // Ghi nhận nhập vào kho đích
      await receiveStock({
        referenceDoc: `${transferDoc}-IN`,
        warehouseId: Number(targetWarehouseId),
        productId: Number(productId),
        quantity: Number(quantity),
        notes: `Điều chuyển từ kho #${sourceWarehouseId}: ${notes || ''}`
      })
      await fetchStocks()
      await fetchMovements()
      return { success: true }
    } finally {
      loading.action = false
    }
  }

  return {
    stocks,
    warehouses,
    movements,
    catalogProducts,
    catalogMap,
    isUsingFallback,
    loading,
    filters,
    movementFilters,
    kpiSummary,
    filteredStocks,
    filteredMovements,
    fetchWarehouses,
    fetchCatalogProducts,
    fetchStocks,
    fetchMovements,
    receiveStock,
    reserveStock,
    releaseStock,
    issueStock,
    transferStock
  }
}
