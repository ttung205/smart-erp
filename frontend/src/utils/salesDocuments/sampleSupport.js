import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';
import { getCompanyConfig as getSavedCompanyConfig } from '../../config/companyConfig';

export const loadPdfLibs = async () => ({ jsPDF, autoTable });
export const formatNumber = (value, digits = 0) => new Intl.NumberFormat('vi-VN', {
  minimumFractionDigits: digits,
  maximumFractionDigits: digits,
}).format(Number(value) || 0);

export const getCompanyConfig = () => {
  const config = getSavedCompanyConfig();
  const bank = config.bankAccounts?.[0] || {};
  return {
    ...config,
    nameEn: config.nameEn || '',
    bankAccountVnd: config.bankAccountVnd || bank.accountNumber || '',
    bankName: config.bankName || bank.bankName || '',
  };
};

const fontUrls = {
  normal: 'https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Me5WZLCzYlKw.ttf',
  bold: 'https://fonts.gstatic.com/s/roboto/v30/KFOlCnqEu92Fr1MmWUlvAw.ttf',
};
const fontCache = new Map();
const fontBase64 = async (url) => {
  if (!fontCache.has(url)) {
    const response = await fetch(url);
    if (!response.ok) throw new Error('Không tải được phông chữ PDF');
    const bytes = new Uint8Array(await response.arrayBuffer());
    let binary = '';
    for (let i = 0; i < bytes.length; i += 0x4000) binary += String.fromCharCode(...bytes.subarray(i, i + 0x4000));
    fontCache.set(url, btoa(binary));
  }
  return fontCache.get(url);
};
export async function registerRobotoFonts(doc) {
  for (const [style, url] of Object.entries(fontUrls)) {
    const filename = `Roboto-${style}.ttf`;
    doc.addFileToVFS(filename, await fontBase64(url));
    doc.addFont(filename, 'Roboto', style);
  }
  doc.setFont('Roboto', 'normal');
  return 'Roboto';
}

export const resolvePdfCurrency = (options, invoice, sale) => options.currency || invoice?.currency || sale?.currency || 'VND';
export const resolvePdfExchangeRate = (options, invoice, sale) => Number(options.exchangeRate || invoice?.exchange_rate || sale?.exchange_rate || 1);
export const convertAmountToVnd = (amount, currency, rate) => Number(amount || 0) * (currency === 'VND' ? 1 : Number(rate || 1));
export const formatPdfMoneyValue = (amount, currency = 'VND') => formatNumber(amount, currency === 'VND' ? 0 : 2);
export const formatPdfCurrencyValue = (amount, currency = 'VND') => formatPdfMoneyValue(amount, currency);

export const getJsPdfImageFormatFromUrl = (url = '', dataUrl = '') => {
  if (/^data:image\/png/i.test(dataUrl) || /\.png(?:\?|$)/i.test(url)) return 'PNG';
  if (/^data:image\/webp/i.test(dataUrl) || /\.webp(?:\?|$)/i.test(url)) return 'WEBP';
  return 'JPEG';
};
export async function loadImageAsDataUrl(url) {
  if (!url) return null;
  try {
    const response = await fetch(url);
    if (!response.ok) return null;
    const blob = await response.blob();
    return await new Promise((resolve) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.onerror = () => resolve(null);
      reader.readAsDataURL(blob);
    });
  } catch { return null; }
}

export async function drawPdfCompanyLogoAndTextHeader(doc, baseFont, config, x, y, { pageWidthMm } = {}) {
  const logo = await loadImageAsDataUrl(config.logoUrl);
  if (logo) {
    const size = doc.getImageProperties(logo);
    const scale = Math.min(23 / size.width, 15 / size.height);
    const width = size.width * scale;
    const height = size.height * scale;
    doc.addImage(logo, getJsPdfImageFormatFromUrl(config.logoUrl, logo), x + (23 - width) / 2, y + (15 - height) / 2, width, height);
  }
  const textWidth = pageWidthMm - x * 2 - (logo ? 30 : 0);
  const centerX = pageWidthMm / 2;
  doc.setFont(baseFont, 'bold');
  doc.setFontSize(10);
  doc.text(doc.splitTextToSize(config.name || '', textWidth), centerX, y + 5, { align: 'center' });
  doc.setFont(baseFont, 'normal');
  doc.setFontSize(8);
  doc.text(doc.splitTextToSize(config.address || '', textWidth), centerX, y + 11, { align: 'center' });
  return y + 19;
}
