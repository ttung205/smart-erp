/**
 * Cấu hình thông tin Công Ty & Mẫu Điều khoản Báo Giá
 */
export const defaultCompanyConfig = {
  name: 'CÔNG TY TNHH THƯƠNG MẠI & KỸ THUẬT THANH TÙNG',
  shortName: 'Thanh Tùng',
  taxCode: '0109876543',
  address: 'Số 123 Đường Cầu Giấy, Quận Cầu Giấy, TP. Hà Nội',
  officeAddress: 'Tầng 5, Tòa nhà Thanh Tùng Plaza, Cầu Giấy, Hà Nội',
  tel: '024.3768.9999',
  hotline: '0988.123.456',
  email: 'sales@thanhtung.vn',
  website: 'www.thanhtung.vn',
  logoUrl: '',
  stampUrl: '',
  bankAccounts: [
    {
      beneficiary: 'CÔNG TY TNHH THƯƠNG MẠI & KỸ THUẬT THANH TÙNG',
      accountNumber: '22028888888',
      bankName: 'Ngân hàng TMCP Đầu tư và Phát triển Việt Nam (BIDV)',
      branch: 'Chi nhánh Cầu Giấy'
    }
  ]
};

export const defaultQuotationTerms = {
  leftTerms: [
    { id: 'l1', label: 'Địa điểm giao hàng:', value: 'Tại Kho Quý Công ty.' },
    { id: 'l2', label: 'Thời gian cấp hàng:', value: 'Theo Báo giá, tính từ thời điểm đặt hàng.' },
    { id: 'l3', label: 'Báo giá có giá trị:', value: '30 ngày kể từ ngày phát hành.' },
    { id: 'l4', label: 'Lưu ý:', value: 'Kiểm tra mô tả, ký hiệu hàng hóa trước khi xác nhận đặt hàng. Hàng hóa khi đã giao nhận, Thanh Tùng không đổi trả lại hàng hóa.' },
    { id: 'l5', label: 'Chất lượng:', value: 'Hàng mới 100%, Chất lượng hàng hóa và Bảo hành 12 tháng theo tiêu chuẩn nhà sản xuất.' },
    { id: 'l6', label: 'Cam kết:', value: 'Chúng tôi cam kết rằng các sản phẩm chúng tôi cung cấp là sản phẩm chính hãng 100%. Nếu bị phát hiện là hàng giả, hàng nhái, chúng tôi sẽ bồi thường gấp 10 lần giá trị đơn hàng.' }
  ],
  rightTerms: [
    { id: 'r1', label: 'Thanh toán:', value: '100% giá trị đơn hàng khi xác nhận đặt hàng.' },
    { id: 'r2', label: 'Phương thức thanh toán:', value: 'Bằng tiền mặt hoặc chuyển khoản.' }
  ],
  bankAccount: {
    beneficiary: 'Công Ty TNHH Thương Mại & Kỹ Thuật Thanh Tùng',
    accountNumber: '22028888888',
    bankName: 'Ngân hàng Thương mại Cổ phần Đầu tư và Phát triển Việt Nam (BIDV)'
  }
};

const STORAGE_COMPANY_KEY = 'smart_erp_company_config';
const STORAGE_TERMS_KEY = 'smart_erp_quotation_terms';
const STORAGE_TEMPLATES_KEY = 'smart_erp_terms_templates';

export function getCompanyConfig() {
  try {
    const saved = localStorage.getItem(STORAGE_COMPANY_KEY);
    if (saved) return { ...defaultCompanyConfig, ...JSON.parse(saved) };
  } catch (e) {
    console.error('Lỗi đọc company config:', e);
  }
  return { ...defaultCompanyConfig };
}

export function saveCompanyConfig(cfg) {
  localStorage.setItem(STORAGE_COMPANY_KEY, JSON.stringify(cfg));
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
  } catch (e) {}
  return [
    {
      name: 'Mặc định (Thanh Tùng B2B)',
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
