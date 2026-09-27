export const DEFAULT_PRODUCT_FORM = {
  sku: '',
  name: '',
  gross_price: 26000000,
  cost_price: 22000000,
  min_stock: 10,
  category_id: 1,
  unit_id: 1
};

export const DEFAULT_SALES_ORDER_FORM = {
  order_number: '',
  customer_id: null,
  order_date: new Date().toISOString().split('T')[0],
  notes: '',
  items: [
    {
      product_id: null,
      sku: '',
      name: '',
      quantity: 1,
      unit_price: 0,
      subtotal: 0
    }
  ]
};
