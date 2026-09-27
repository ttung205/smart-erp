import { ref, reactive, computed } from 'vue'

// Dữ liệu mẫu Fallback chuẩn SCM (khớp với DbInitializer.cs của C# Backend)
const FALLBACK_WAREHOUSES = [
  { id: 1, code: 'WH-HN-01', name: 'Kho Tổng Hà Nội', location: 'KCN Thăng Long, Đông Anh, Hà Nội', is_active: true },
  { id: 2, code: 'WH-HCM-01', name: 'Kho Phân Phối TP.HCM', location: 'KCN Tân Bình, Tây Thạnh, TP.HCM', is_active: true },
  { id: 3, code: 'WH-DN-01', name: 'Kho Trung Chuyển Đà Nẵng', location: 'KCN Hòa Khánh, Liên Chiểu, Đà Nẵng', is_active: true }
]

const FALLBACK_STOCKS = [
  {
    id: 1,
    product_id: 1,
    sku: 'LAP-MBP14-M3',
    product_name: 'MacBook Pro 14 M3 Max (36GB / 1TB)',
    warehouse_id: 1,
    warehouse_code: 'WH-HN-01',
    warehouse_name: 'Kho Tổng Hà Nội',
    quantity_on_hand: 50,
    quantity_reserved: 5,
    available: 45,
    min_stock: 10,
    unit: 'Chiếc',
    status: 'NORMAL',
    updated_at: new Date().toISOString()
  },
  {
    id: 2,
    product_id: 1,
    sku: 'LAP-MBP14-M3',
    product_name: 'MacBook Pro 14 M3 Max (36GB / 1TB)',
    warehouse_id: 2,
    warehouse_code: 'WH-HCM-01',
    warehouse_name: 'Kho Phân Phối TP.HCM',
    quantity_on_hand: 30,
    quantity_reserved: 0,
    available: 30,
    min_stock: 10,
    unit: 'Chiếc',
    status: 'NORMAL',
    updated_at: new Date().toISOString()
  },
  {
    id: 3,
    product_id: 2,
    sku: 'PHN-IP15PM-256',
    product_name: 'iPhone 15 Pro Max 256GB Titan Tự Nhiên',
    warehouse_id: 1,
    warehouse_code: 'WH-HN-01',
    warehouse_name: 'Kho Tổng Hà Nội',
    quantity_on_hand: 80,
    quantity_reserved: 12,
    available: 68,
    min_stock: 20,
    unit: 'Chiếc',
    status: 'NORMAL',
    updated_at: new Date().toISOString()
  },
  {
    id: 4,
    product_id: 2,
    sku: 'PHN-IP15PM-256',
    product_name: 'iPhone 15 Pro Max 256GB Titan Tự Nhiên',
    warehouse_id: 2,
    warehouse_code: 'WH-HCM-01',
    warehouse_name: 'Kho Phân Phối TP.HCM',
    quantity_on_hand: 40,
    quantity_reserved: 5,
    available: 35,
    min_stock: 15,
    unit: 'Chiếc',
    status: 'NORMAL',
    updated_at: new Date().toISOString()
  },
  {
    id: 5,
    product_id: 3,
    sku: 'MON-DELL-U2723',
    product_name: 'Màn hình Dell UltraSharp U2723QE 4K IPS Black',
    warehouse_id: 1,
    warehouse_code: 'WH-HN-01',
    warehouse_name: 'Kho Tổng Hà Nội',
    quantity_on_hand: 12,
    quantity_reserved: 8,
    available: 4,
    min_stock: 15,
    unit: 'Chiếc',
    status: 'LOW_STOCK', // Cảnh báo dưới định mức
    updated_at: new Date().toISOString()
  },
  {
    id: 6,
    product_id: 4,
    sku: 'ACC-KEY-Q1PRO',
    product_name: 'Bàn phím cơ Keychron Q1 Pro Wireless Custom',
    warehouse_id: 3,
    warehouse_code: 'WH-DN-01',
    warehouse_name: 'Kho Trung Chuyển Đà Nẵng',
    quantity_on_hand: 5,
    quantity_reserved: 5,
    available: 0,
    min_stock: 8,
    unit: 'Chiếc',
    status: 'OUT_OF_STOCK', // Hết tồn khả dụng
    updated_at: new Date().toISOString()
  }
]

const FALLBACK_MOVEMENTS = [
  {
    id: 101,
    reference_doc: 'PO-202609-0015',
    warehouse_id: 1,
    warehouse_name: 'Kho Tổng Hà Nội',
    product_id: 1,
    product_name: 'MacBook Pro 14 M3 Max (36GB / 1TB)',
    sku: 'LAP-MBP14-M3',
    movement_type: 'PO_RECEIPT',
    quantity_delta: 50,
    balance_on_hand_after: 50,
    balance_reserved_after: 0,
    notes: 'Nhập kho lô hàng đầu kỳ từ Nhà Cung Cấp FPT Synnex',
    created_at: '2026-09-18T08:30:00Z'
  },
  {
    id: 102,
    reference_doc: 'SO-202609-0042',
    warehouse_id: 1,
    warehouse_name: 'Kho Tổng Hà Nội',
    product_id: 1,
    product_name: 'MacBook Pro 14 M3 Max (36GB / 1TB)',
    sku: 'LAP-MBP14-M3',
    movement_type: 'SO_RESERVE',
    quantity_delta: 5,
    balance_on_hand_after: 50,
    balance_reserved_after: 5,
    notes: 'Giữ hàng tự động từ Saga Đơn Bán Hàng #SO-202609-0042',
    created_at: '2026-09-20T10:15:00Z'
  },
  {
    id: 103,
    reference_doc: 'PO-202609-0018',
    warehouse_id: 1,
    warehouse_name: 'Kho Tổng Hà Nội',
    product_id: 3,
    product_name: 'Màn hình Dell UltraSharp U2723QE 4K IPS Black',
    sku: 'MON-DELL-U2723',
    movement_type: 'PO_RECEIPT',
    quantity_delta: 20,
    balance_on_hand_after: 20,
    balance_reserved_after: 0,
    notes: 'Nhập kho bổ sung từ DigiWorld',
    created_at: '2026-09-21T09:00:00Z'
  },
  {
    id: 104,
    reference_doc: 'SO-202609-0051',
    warehouse_id: 1,
    warehouse_name: 'Kho Tổng Hà Nội',
    product_id: 3,
    product_name: 'Màn hình Dell UltraSharp U2723QE 4K IPS Black',
    sku: 'MON-DELL-U2723',
    movement_type: 'SO_RESERVE',
    quantity_delta: 8,
    balance_on_hand_after: 20,
    balance_reserved_after: 8,
    notes: 'Khóa giữ chỗ đơn hàng dự án Công ty FPT Software',
    created_at: '2026-09-21T14:20:00Z'
  },
  {
    id: 105,
    reference_doc: 'DEL-202609-0008',
    warehouse_id: 1,
    warehouse_name: 'Kho Tổng Hà Nội',
    product_id: 3,
    product_name: 'Màn hình Dell UltraSharp U2723QE 4K IPS Black',
    sku: 'MON-DELL-U2723',
    movement_type: 'SO_ISSUE',
    quantity_delta: -8,
    balance_on_hand_after: 12,
    balance_reserved_after: 0,
    notes: 'Xuất kho thực tế bàn giao cho đơn vị vận chuyển Viettel Post',
    created_at: '2026-09-22T08:00:00Z'
  }
]

// Module-level reactive state (Shared across all tabs)
const stocks = ref([])
const warehouses = ref([])
const movements = ref([])
const isUsingFallback = ref(false)

const loading = reactive({
  stocks: false,
  warehouses: false,
  movements: false,
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

// Danh mục tra cứu nhanh sản phẩm chuẩn ERP
const PRODUCT_CATALOG = {
  1: { sku: 'LAP-MBP14-M3', name: 'MacBook Pro 14 M3 Max (36GB / 1TB)', unit: 'Chiếc' },
  2: { sku: 'PHN-IP15PM-256', name: 'iPhone 15 Pro Max 256GB Titan Tự Nhiên', unit: 'Chiếc' },
  3: { sku: 'MON-DELL-U2723', name: 'Màn hình Dell UltraSharp U2723QE 4K IPS Black', unit: 'Chiếc' },
  4: { sku: 'ACC-KEY-Q1PRO', name: 'Bàn phím cơ Keychron Q1 Pro Wireless Custom', unit: 'Chiếc' }
}

  // 2. Tải danh sách Tồn Kho
  const fetchStocks = async () => {
    loading.stocks = true
    try {
      const params = new URLSearchParams()
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
        // Chuẩn hóa và làm giàu dữ liệu (Enrich) SKU & Tên Sản Phẩm từ Catalog
        stocks.value = json.data.map(item => {
          const pid = Number(item.product_id ?? item.productId ?? 0)
          const wid = Number(item.warehouse_id ?? item.warehouseId ?? 0)
          const cat = PRODUCT_CATALOG[pid] || {}
          const qOnHand = Number(item.quantity_on_hand ?? item.quantityOnHand ?? 0)
          const qReserved = Number(item.quantity_reserved ?? item.quantityReserved ?? 0)
          return {
            ...item,
            product_id: pid,
            warehouse_id: wid,
            sku: item.sku || cat.sku || `SKU-${pid}`,
            product_name: item.product_name || item.productName || cat.name || `Sản phẩm #${pid}`,
            unit: item.unit || cat.unit || 'Chiếc',
            quantity_on_hand: qOnHand,
            quantity_reserved: qReserved,
            available: Number(item.available ?? (qOnHand - qReserved)),
            min_stock: Number(item.min_stock ?? item.minStock ?? 10)
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
      const res = await fetch('/api/v1/inventory/stocks/movements', {
        headers: getHeaders(),
        signal: AbortSignal.timeout(3000)
      })
      if (!res.ok) throw new Error(`HTTP ${res.status}`)
      const json = await res.json()
      if (json.success && Array.isArray(json.data)) {
        movements.value = json.data.map(m => {
          const pid = Number(m.product_id ?? m.productId ?? 0)
          const cat = PRODUCT_CATALOG[pid] || {}
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
    isUsingFallback,
    loading,
    filters,
    movementFilters,
    kpiSummary,
    filteredStocks,
    filteredMovements,
    fetchWarehouses,
    fetchStocks,
    fetchMovements,
    receiveStock,
    reserveStock,
    releaseStock,
    issueStock,
    transferStock
  }
}
