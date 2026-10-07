/**
 * Tiện ích định dạng tiền tệ và số liệu bảng biểu theo chuẩn Việt Nam
 */
export function formatCurrency(amount) {
  if (amount === null || amount === undefined || isNaN(amount)) {
    return '0 ₫';
  }
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0
  }).format(amount);
}

export function formatNumber(val) {
  if (val === null || val === undefined || isNaN(val)) {
    return '0';
  }
  return new Intl.NumberFormat('vi-VN').format(val);
}

export function formatDate(dateString) {
  if (!dateString) return '-';
  const str = String(dateString).trim();
  if (str === '-' || str === 'null' || str === 'undefined' || str === '') return '-';
  
  // Nếu đã ở dạng DD/MM/YYYY
  if (/^\d{2}\/\d{2}\/\d{4}/.test(str)) {
    return str.substring(0, 10);
  }
  
  // Nếu ở dạng YYYY-MM-DD hoặc ISO string (tránh lệch timezone)
  const match = str.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
  if (match) {
    const y = match[1];
    const m = match[2].padStart(2, '0');
    const d = match[3].padStart(2, '0');
    return `${d}/${m}/${y}`;
  }
  
  const d = new Date(dateString);
  if (isNaN(d.getTime())) return dateString;
  const day = String(d.getDate()).padStart(2, '0');
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const year = d.getFullYear();
  return `${day}/${month}/${year}`;
}
