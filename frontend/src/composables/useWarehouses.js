import { ref, computed } from 'vue';
import { inventoryApi } from '../api/inventory.api';
import { DEFAULT_WAREHOUSES } from '../constants/purchase.constant';

// Shared state across components for seamless synchronization
const warehouses = ref([...DEFAULT_WAREHOUSES]);
const loading = ref(false);
const isFetched = ref(false);

export function useWarehouses() {
  const fetchWarehouses = async (force = false) => {
    if (isFetched.value && !force) {
      return warehouses.value;
    }
    loading.value = true;
    try {
      const res = await inventoryApi.getWarehouses();
      const list = res?.data || (Array.isArray(res) ? res : null);
      if (Array.isArray(list) && list.length > 0) {
        warehouses.value = list.map(w => ({
          id: Number(w.id),
          code: w.code,
          name: w.name,
          location: w.location || '',
          is_active: w.is_active ?? true,
          can_sell: w.can_sell ?? true
        }));
        isFetched.value = true;
      }
    } catch (err) {
      console.warn('[useWarehouses] Could not fetch warehouses from inventory service, using defaults:', err);
    } finally {
      loading.value = false;
    }
    return warehouses.value;
  };

  const warehouseOptions = computed(() =>
    warehouses.value.map(w => ({
      label: `${w.name} (${w.code})`,
      name: w.name,
      code: w.code,
      value: w.id,
      id: w.id
    }))
  );

  const getWarehouseName = (id) => {
    if (!id) return '—';
    const numId = Number(id);
    const found = warehouses.value.find(w => Number(w.id) === numId);
    return found ? found.name : `Kho #${id}`;
  };

  const getWarehouseCode = (id) => {
    if (!id) return '';
    const numId = Number(id);
    const found = warehouses.value.find(w => Number(w.id) === numId);
    return found ? found.code : '';
  };

  const getWarehouse = (id) => {
    if (!id) return null;
    const numId = Number(id);
    return warehouses.value.find(w => Number(w.id) === numId) || null;
  };

  return {
    warehouses,
    warehouseOptions,
    loading,
    fetchWarehouses,
    getWarehouseName,
    getWarehouseCode,
    getWarehouse
  };
}
