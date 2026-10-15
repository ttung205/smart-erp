import { defineStore } from 'pinia'
import { ref } from 'vue'
import { catalogApi } from '../api/catalog.api'

export const useCatalogStore = defineStore('catalog', () => {
  const products = ref([])
  const loading = ref(false)
  const isLoaded = ref(false)

  const fetchProducts = async (force = false) => {
    if (isLoaded.value && !force && products.value.length > 0) {
      return products.value
    }
    loading.value = true
    try {
      const res = await catalogApi.getProducts({ per_page: 1000 })
      products.value = res.data || (Array.isArray(res) ? res : [])
      isLoaded.value = true
      return products.value
    } catch (err) {
      console.error('[CatalogStore] Lỗi tải sản phẩm:', err)
      return []
    } finally {
      loading.value = false
    }
  }

  const addProduct = (newProduct) => {
    if (!newProduct) return
    const id = Number(newProduct.product_id || newProduct.id)
    const existingIndex = products.value.findIndex(p => Number(p.id) === id || p.sku === newProduct.sku)

    const normalized = {
      id,
      sku: newProduct.sku,
      designation: newProduct.name || newProduct.designation,
      item_description: newProduct.name || newProduct.designation,
      com: newProduct.origin || newProduct.com || 'Việt Nam',
      unit: newProduct.unit || 'Cái',
      gross_price: Number(newProduct.gross_price || 0),
      min_stock: Number(newProduct.min_stock || 0),
      brand: newProduct.brand || '',
      category: newProduct.category || '',
      status: 'ACTIVE'
    }

    if (existingIndex >= 0) {
      products.value[existingIndex] = { ...products.value[existingIndex], ...normalized }
    } else {
      products.value.unshift(normalized)
    }
  }

  return {
    products,
    loading,
    isLoaded,
    fetchProducts,
    addProduct
  }
})
