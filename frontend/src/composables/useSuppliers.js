import { ref } from 'vue';
import { catalogApi } from '../api/catalog.api';

// Danh sách nhà cung cấp dùng chung giữa các trang, lấy từ catalog-service (API composition ở frontend):
// chứng từ mua hàng chỉ lưu supplier_id nên tên phải tra từ catalog, không dùng danh sách cứng.
const suppliers = ref([]);
const loading = ref(false);
const isFetched = ref(false);

export function useSuppliers() {
  const fetchSuppliers = async (force = false) => {
    if (isFetched.value && !force) {
      return suppliers.value;
    }
    loading.value = true;
    try {
      const res = await catalogApi.getSuppliers();
      const list = res?.data || (Array.isArray(res) ? res : null);
      if (Array.isArray(list)) {
        suppliers.value = list.map(s => ({
          id: Number(s.id),
          code: s.code,
          name: s.name,
          contact_person: s.contact_person || '',
          phone: s.phone || s.contact_phone || '',
          tax_code: s.tax_code || '',
          address: s.address || ''
        }));
        isFetched.value = true;
      }
    } catch (err) {
      console.error('[useSuppliers] Không tải được danh sách nhà cung cấp từ catalog:', err);
    } finally {
      loading.value = false;
    }
    return suppliers.value;
  };

  const getSupplier = (id) => {
    if (!id) return null;
    return suppliers.value.find(s => s.id === Number(id)) || null;
  };

  const getSupplierName = (id) => {
    if (!id) return '—';
    const found = getSupplier(id);
    return found ? found.name : `Nhà cung cấp #${id}`;
  };

  return { suppliers, loading, fetchSuppliers, getSupplier, getSupplierName };
}
