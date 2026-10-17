import { companyApi } from '../api/company.api';

/** Thông tin công ty được lưu tập trung trong identity-service. */
export const defaultCompanyConfig = {
  name: '', shortName: '', taxCode: '', address: '', officeAddress: '',
  tel: '', hotline: '', email: '', accountingEmail: '', website: '',
  logoUrl: '',
  bankAccounts: []
};

export const defaultQuotationTerms = {
  leftTerms: [
    { id: 'l1', label: 'Địa điểm giao hàng:', value: 'Tại Kho Quý Công ty.' },
    { id: 'l2', label: 'Thời gian cấp hàng:', value: 'Theo Báo giá, tính từ thời điểm đặt hàng.' },
    { id: 'l3', label: 'Báo giá có giá trị:', value: '30 ngày kể từ ngày phát hành.' },
    { id: 'l4', label: 'Lưu ý:', value: 'Kiểm tra mô tả, ký hiệu hàng hóa trước khi xác nhận đặt hàng. Hàng hóa khi đã giao nhận, chúng tôi không đổi trả lại hàng hóa.' },
    { id: 'l5', label: 'Chất lượng:', value: 'Hàng mới 100%, Chất lượng hàng hóa và Bảo hành 12 tháng theo tiêu chuẩn nhà sản xuất.' },
    { id: 'l6', label: 'Cam kết:', value: 'Chúng tôi cam kết rằng các sản phẩm chúng tôi cung cấp là sản phẩm chính hãng 100%. Nếu bị phát hiện là hàng giả, hàng nhái, chúng tôi sẽ bồi thường gấp 10 lần giá trị đơn hàng.' }
  ],
  rightTerms: [
    { id: 'r1', label: 'Thanh toán:', value: '100% giá trị đơn hàng khi xác nhận đặt hàng.' },
    { id: 'r2', label: 'Phương thức thanh toán:', value: 'Bằng tiền mặt hoặc chuyển khoản.' }
  ],
  bankAccount: {}
};

const STORAGE_TERMS_KEY = 'smart_erp_quotation_terms';
const STORAGE_TEMPLATES_KEY = 'smart_erp_terms_templates';

let companyCache = null;
let pendingLoad = null;

export function setCompanyConfig(data) {
  companyCache = {
    ...defaultCompanyConfig,
    name: data.name || '', shortName: data.short_name || '', taxCode: data.tax_code || '',
    address: data.address || '', officeAddress: data.office_address || '',
    tel: data.tel || '', hotline: data.hotline || '', email: data.email || '',
    accountingEmail: data.accounting_email || '', website: data.website || '',
    logoUrl: data.logo_url || '',
    bankAccounts: data.bank_account_number ? [{
      beneficiary: data.bank_beneficiary || '', accountNumber: data.bank_account_number,
      bankName: data.bank_name || '', branch: data.bank_branch || ''
    }] : []
  };
  return companyCache;
}

export async function loadCompanyConfig({ refresh = false } = {}) {
  if (companyCache && !refresh) return companyCache;
  if (!pendingLoad) {
    pendingLoad = companyApi.get().then(response => setCompanyConfig(response.data)).finally(() => { pendingLoad = null; });
  }
  return pendingLoad;
}

export function getCompanyConfig() {
  return companyCache || { ...defaultCompanyConfig };
}

export function getSavedQuotationTerms() {
  try {
    const saved = localStorage.getItem(STORAGE_TERMS_KEY);
    if (saved) return JSON.parse(saved);
  } catch (e) {
    console.error('Lỗi đọc quotation terms:', e);
  }
  return JSON.parse(JSON.stringify(defaultQuotationTerms));
}

export function saveQuotationTerms(terms) {
  localStorage.setItem(STORAGE_TERMS_KEY, JSON.stringify(terms));
}

export function getSavedTermsTemplates() {
  try {
    const saved = localStorage.getItem(STORAGE_TEMPLATES_KEY);
    if (saved) return JSON.parse(saved);
  } catch (e) { /* Use default templates when local storage is unavailable. */ }
  return [
    {
      name: 'Mặc định',
      terms: JSON.parse(JSON.stringify(defaultQuotationTerms))
    },
    {
      name: 'Thanh toán sau khi giao hàng (Công nợ 30 ngày)',
      terms: {
        ...defaultQuotationTerms,
        rightTerms: [
          { id: 'r1', label: 'Thanh toán:', value: 'Thanh toán trong vòng 30 ngày kể từ ngày nhận đủ hàng hóa và hóa đơn GTGT hợp lệ.' },
          { id: 'r2', label: 'Phương thức thanh toán:', value: 'Chuyển khoản theo thông tin ngân hàng bên dưới.' }
        ]
      }
    }
  ];
}

export function saveTermsTemplates(templates) {
  localStorage.setItem(STORAGE_TEMPLATES_KEY, JSON.stringify(templates));
}
