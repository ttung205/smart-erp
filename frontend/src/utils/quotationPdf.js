import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';
import FileSaver from 'file-saver';
import { loadCompanyConfig, getSavedQuotationTerms } from '../config/companyConfig';
import { getJsPdfImageFormatFromUrl, loadImageAsDataUrl } from './salesDocuments/sampleSupport';
import { formatDate, formatNumber } from './format';

const saveAs = FileSaver.saveAs || FileSaver;

const ROBOTO_REGULAR_URL = 'https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Me5WZLCzYlKw.ttf';
const ROBOTO_BOLD_URL = 'https://fonts.gstatic.com/s/roboto/v30/KFOlCnqEu92Fr1MmWUlvAw.ttf';

const fontBase64Cache = new Map();

const arrayBufferToBase64 = (buffer) => {
  const bytes = new Uint8Array(buffer);
  const chunkSize = 0x4000;
  let binary = '';
  for (let index = 0; index < bytes.length; index += chunkSize) {
    const chunk = bytes.subarray(index, index + chunkSize);
    binary += String.fromCharCode(...chunk);
  }
  return btoa(binary);
};

const getFontBase64 = async (url) => {
  if (fontBase64Cache.has(url)) {
    return fontBase64Cache.get(url);
  }
  try {
    const fontResponse = await fetch(url);
    const fontBuffer = await fontResponse.arrayBuffer();
    const fontBase64 = arrayBufferToBase64(fontBuffer);
    fontBase64Cache.set(url, fontBase64);
    return fontBase64;
  } catch (err) {
    console.warn('Không thể tải font Roboto trực tuyến:', err);
    return null;
  }
};

const registerFont = async (doc, url, fileName, fontName, fontStyle) => {
  const fontBase64 = await getFontBase64(url);
  if (fontBase64) {
    doc.addFileToVFS(fileName, fontBase64);
    doc.addFont(fileName, fontName, fontStyle);
    return true;
  }
  return false;
};

export const defaultPdfColumns = [
  { field: 'sku', header: 'Ký hiệu', selected: true, type: 'text', align: 'center', width: 65 },
  { field: 'product_name', header: 'Mô tả sản phẩm', selected: true, type: 'text', align: 'left' },
  { field: 'brand', header: 'Hãng', selected: true, type: 'text', align: 'center', width: 45 },
  { field: 'origin', header: 'Xuất xứ', selected: true, type: 'text', align: 'center', width: 45 },
  { field: 'unit', header: 'Đơn vị', selected: true, type: 'text', align: 'center', width: 35 },
  { field: 'quantity', header: 'SL', selected: true, type: 'number', align: 'center', width: 32 },
  { field: 'unit_price', header: 'Giá bán (VNĐ)', selected: true, type: 'currency', align: 'right', width: 70 },
  { field: 'vat_rate', header: 'VAT (%)', selected: true, type: 'number', align: 'center', width: 42 },
  { field: 'lead_time', header: 'Thời gian giao hàng', selected: true, type: 'text', align: 'center', width: 65 },
  { field: 'sub_total', header: 'Thành tiền (VNĐ)', selected: true, type: 'currency', align: 'right', width: 75 }
];

export async function createQuotationPdfDoc(quotation, options = {}) {
  const {
    orientation = 'portrait',
    columns = defaultPdfColumns.filter(c => c.selected),
    company: suppliedCompany = null,
    terms = getSavedQuotationTerms(),
    currentUser = null
  } = options;
  const company = suppliedCompany || await loadCompanyConfig({ refresh: true });
  if (!company.name) throw new Error('Chưa thiết lập tên công ty. Vui lòng cập nhật tại trang Công ty.');

  const normalizedOrientation = orientation === 'landscape' ? 'landscape' : 'portrait';
  const doc = new jsPDF({
    orientation: normalizedOrientation,
    unit: 'pt',
    format: 'a4',
    compress: true
  });

  // Load UTF-8 Vietnamese Roboto Font
  let baseFont = 'helvetica';
  try {
    const regOk = await registerFont(doc, ROBOTO_REGULAR_URL, 'Roboto-Regular.ttf', 'Roboto', 'normal');
    const boldOk = await registerFont(doc, ROBOTO_BOLD_URL, 'Roboto-Bold.ttf', 'Roboto', 'bold');
    if (regOk && boldOk) {
      doc.setFont('Roboto', 'normal');
      baseFont = 'Roboto';
    }
  } catch (e) {
    console.warn('Fallback font helvetica:', e);
  }

  const pageWidth = doc.internal.pageSize.getWidth();
  const pageHeight = doc.internal.pageSize.getHeight();
  const margin = 40;
  const primaryBlue = [22, 119, 255];
  const darkGray = [80, 80, 80];

  let cursorY = margin;

  const ensureSpace = (height = 0) => {
    if (cursorY + height > pageHeight - margin) {
      doc.addPage();
      doc.setFont(baseFont, 'normal');
      doc.setFontSize(10);
      cursorY = margin;
    }
  };

  const addParagraph = (text, { size = 9, bold = false, color = [0, 0, 0], lineHeight = 1.45 } = {}) => {
    if (!text) return;
    const availW = pageWidth - margin * 2;
    const lines = Array.isArray(text) ? text : doc.splitTextToSize(text, availW);
    const blockHeight = lines.length * size * lineHeight;
    ensureSpace(blockHeight);
    doc.setFont(baseFont, bold ? 'bold' : 'normal');
    doc.setFontSize(size);
    doc.setTextColor(...color);
    doc.text(lines, margin, cursorY, { lineHeightFactor: lineHeight });
    cursorY += blockHeight;
    doc.setTextColor(0, 0, 0);
  };

  // ==========================================
  // 1. HEADER CÔNG TY (Chuẩn 100% QuotationPDF.js)
  // ==========================================
  const headerTop = cursorY;
  const logo = await loadImageAsDataUrl(company.logoUrl);
  let logoWidth = 0;
  let logoHeight = 0;
  if (logo) {
    const size = doc.getImageProperties(logo);
    const desiredHeight = orientation === 'landscape' ? 90 : 80;
    const maxWidth = orientation === 'landscape' ? 140 : 120;
    const aspectRatio = size.width / size.height;
    logoHeight = desiredHeight;
    logoWidth = Math.min(maxWidth, Math.max(48, logoHeight * aspectRatio));
    doc.addImage(logo, getJsPdfImageFormatFromUrl(company.logoUrl, logo), margin, headerTop, logoWidth, logoHeight);
  }
  const companyInfoX = margin + logoWidth + (orientation === 'landscape' ? 10 : 8);
  let infoY = headerTop + (orientation === 'landscape' ? 26 : 23);

  // Tên công ty
  const companyNameFontSize = orientation === 'landscape' ? 16 : 15;
  doc.setFont(baseFont, 'bold');
  doc.setFontSize(companyNameFontSize);
  doc.setTextColor(...primaryBlue);
  doc.text(company.name, companyInfoX, infoY, { align: 'left', maxWidth: 999 });

  infoY += orientation === 'landscape' ? 15 : 14;

  // Địa chỉ
  doc.setFontSize(orientation === 'landscape' ? 10.5 : 10);
  const addressLabel = 'Địa chỉ: ';
  const addressValue = company.address || '';
  doc.setFont(baseFont, 'bold');
  doc.setTextColor(...darkGray);
  const addressLabelWidth = doc.getTextWidth(addressLabel);
  doc.text(addressLabel, companyInfoX, infoY, { maxWidth: 999 });
  doc.setFont(baseFont, 'normal');
  doc.text(addressValue, companyInfoX + addressLabelWidth, infoY, { maxWidth: 999 });

  infoY += orientation === 'landscape' ? 13 : 12;

  // Địa chỉ văn phòng (nếu có)
  if (company.officeAddress) {
    doc.setFontSize(orientation === 'landscape' ? 10.5 : 10);
    const officeLabel = 'Địa chỉ văn phòng: ';
    const officeValue = company.officeAddress;
    doc.setFont(baseFont, 'bold');
    doc.setTextColor(...darkGray);
    const officeLabelWidth = doc.getTextWidth(officeLabel);
    doc.text(officeLabel, companyInfoX, infoY, { maxWidth: 999 });
    doc.setFont(baseFont, 'normal');
    doc.text(officeValue, companyInfoX + officeLabelWidth, infoY, { maxWidth: 999 });
    infoY += orientation === 'landscape' ? 13 : 12;
  }

  // Dòng MST | Tel | Hotline
  doc.setFontSize(orientation === 'landscape' ? 10.5 : 10);
  const separator = '| ';
  const mstLabel = 'MST: ';
  const mstValue = `${company.taxCode || ''} `;
  const telLabel = 'Tel: ';
  const telValue = `${company.tel || ''} `;
  const hotlineLabel = 'Hotline: ';
  const hotlineValue = company.hotline || '';

  doc.setFont(baseFont, 'bold');
  doc.setTextColor(...darkGray);
  const mstLabelWidth = doc.getTextWidth(mstLabel);
  const telLabelWidth = doc.getTextWidth(telLabel);
  const hotlineLabelWidth = doc.getTextWidth(hotlineLabel);

  doc.setFont(baseFont, 'normal');
  doc.setTextColor(...darkGray);
  const mstValueWidth = doc.getTextWidth(mstValue);
  const telValueWidth = doc.getTextWidth(telValue);
  const separatorWidth = doc.getTextWidth(separator);

  let contactStartX = companyInfoX;

  doc.setFont(baseFont, 'bold');
  doc.setTextColor(...darkGray);
  doc.text(mstLabel, contactStartX, infoY, { maxWidth: 999 });
  contactStartX += mstLabelWidth;
  doc.setFont(baseFont, 'normal');
  doc.text(mstValue, contactStartX, infoY, { maxWidth: 999 });
  contactStartX += mstValueWidth;

  doc.text(separator, contactStartX, infoY, { maxWidth: 999 });
  contactStartX += separatorWidth;

  doc.setFont(baseFont, 'bold');
  doc.setTextColor(...darkGray);
  doc.text(telLabel, contactStartX, infoY, { maxWidth: 999 });
  contactStartX += telLabelWidth;
  doc.setFont(baseFont, 'normal');
  doc.text(telValue, contactStartX, infoY, { maxWidth: 999 });
  contactStartX += telValueWidth;

  doc.text(separator, contactStartX, infoY, { maxWidth: 999 });
  contactStartX += separatorWidth;

  doc.setFont(baseFont, 'bold');
  doc.setTextColor(...darkGray);
  doc.text(hotlineLabel, contactStartX, infoY, { maxWidth: 999 });
  contactStartX += hotlineLabelWidth;
  doc.setFont(baseFont, 'normal');
  doc.text(hotlineValue, contactStartX, infoY, { maxWidth: 999 });

  // Dòng Email | Website
  infoY += orientation === 'landscape' ? 13 : 12;

  const emailLabel = 'Email: ';
  const emailValue = `${company.email || ''} `;
  const websiteLabel = 'Website: ';
  const websiteValue = company.website || '';

  doc.setFont(baseFont, 'bold');
  doc.setTextColor(...darkGray);
  const emailLabelWidth = doc.getTextWidth(emailLabel);
  const websiteLabelWidth = doc.getTextWidth(websiteLabel);

  doc.setFont(baseFont, 'normal');
  doc.setTextColor(...darkGray);
  const emailValueWidth = doc.getTextWidth(emailValue);

  let emailWebsiteStartX = companyInfoX;

  doc.setFont(baseFont, 'bold');
  doc.setTextColor(...darkGray);
  doc.text(emailLabel, emailWebsiteStartX, infoY, { maxWidth: 999 });
  emailWebsiteStartX += emailLabelWidth;
  doc.setFont(baseFont, 'normal');
  doc.text(emailValue, emailWebsiteStartX, infoY, { maxWidth: 999 });
  emailWebsiteStartX += emailValueWidth;

  doc.text(separator, emailWebsiteStartX, infoY, { maxWidth: 999 });
  emailWebsiteStartX += separatorWidth;

  doc.setFont(baseFont, 'bold');
  doc.setTextColor(...darkGray);
  doc.text(websiteLabel, emailWebsiteStartX, infoY, { maxWidth: 999 });
  emailWebsiteStartX += websiteLabelWidth;
  doc.setFont(baseFont, 'normal');
  doc.setTextColor(...primaryBlue);
  doc.text(websiteValue, emailWebsiteStartX, infoY, { maxWidth: 999 });

  doc.setTextColor(0, 0, 0);
  cursorY = Math.max(
    headerTop + logoHeight + (orientation === 'landscape' ? 22 : 20),
    logo ? 0 : infoY + 12
  );
  cursorY += orientation === 'landscape' ? 18 : 15;

  // ==========================================
  // 2. TIÊU ĐỀ BÁO GIÁ
  // ==========================================
  ensureSpace(50);
  doc.setFont(baseFont, 'bold');
  doc.setFontSize(orientation === 'landscape' ? 22 : 20);
  doc.setTextColor(...primaryBlue);
  doc.text('BÁO GIÁ', pageWidth / 2, cursorY, { align: 'center' });
  cursorY += orientation === 'landscape' ? 24 : 22;

  // ==========================================
  // 3. KHUNG THÔNG TIN KHÁCH HÀNG (2 CỘT CHUẨN MẪU)
  // ==========================================
  ensureSpace(80);
  const boxStartY = cursorY;
  const boxPadding = 8;
  const boxInnerWidth = pageWidth - margin * 2 - boxPadding * 2;
  const leftColumnWidth = boxInnerWidth * 0.7;
  const rightColumnWidth = boxInnerWidth * 0.3;
  const columnGap = 10;

  doc.setFontSize(9);

  doc.setFont(baseFont, 'bold');
  const kinhGuiText = 'Kính gửi: ';
  const kinhGuiWidth = doc.getTextWidth(kinhGuiText);
  const nguoiNhanText = 'Người nhận: ';
  const nguoiNhanWidth = doc.getTextWidth(nguoiNhanText);
  const soDienThoaiText = 'Điện thoại: ';
  const soDienThoaiWidth = doc.getTextWidth(soDienThoaiText);
  const emailText = 'Email: ';
  const emailWidth = doc.getTextWidth(emailText);
  const diaChiText = 'Địa chỉ: ';
  const diaChiWidth = doc.getTextWidth(diaChiText);

  const maxLeftLabelWidth = Math.max(
    kinhGuiWidth,
    nguoiNhanWidth,
    soDienThoaiWidth,
    emailWidth,
    diaChiWidth
  );

  const companyAvailableWidth = leftColumnWidth - maxLeftLabelWidth;
  const recipientAvailableWidth = leftColumnWidth - maxLeftLabelWidth;
  const phoneAvailableWidth = leftColumnWidth - maxLeftLabelWidth;
  const emailAvailableWidth = leftColumnWidth - maxLeftLabelWidth;
  const addressAvailableWidth = leftColumnWidth - maxLeftLabelWidth;

  const custCompany = quotation.customer_name || quotation.customer?.company || quotation.customer?.name || '';
  const custRecipient = quotation.contact_name || quotation.recipient || quotation.customer?.contact_name || '';
  const custPhone = quotation.customer_phone || quotation.contact_phone || quotation.customer?.phone || '';
  const custEmail = quotation.customer_email || quotation.contact_email || quotation.customer?.email || '';
  const custAddress = quotation.customer_address || quotation.address || quotation.customer?.address || '';

  doc.setFont(baseFont, 'normal');
  const companyLines = doc.splitTextToSize(custCompany, companyAvailableWidth);
  const recipientLines = doc.splitTextToSize(custRecipient, recipientAvailableWidth);
  const phoneLines = doc.splitTextToSize(custPhone, phoneAvailableWidth);
  const emailLines = doc.splitTextToSize(custEmail, emailAvailableWidth);
  const addressLines = doc.splitTextToSize(custAddress, addressAvailableWidth);

  doc.setFont(baseFont, 'bold');
  const ngayBaoGiaText = 'Ngày báo giá: ';
  const ngayBaoGiaWidth = doc.getTextWidth(ngayBaoGiaText);
  const soBaoGiaText = 'Số báo giá: ';
  const soBaoGiaWidth = doc.getTextWidth(soBaoGiaText);

  const dateAvailableWidth = rightColumnWidth - ngayBaoGiaWidth;
  const quotationNumberAvailableWidth = rightColumnWidth - soBaoGiaWidth;

  const quoteNo = quotation.quotation_number || quotation.id || 'QT-DRAFT';
  const formattedDate = quotation.created_at || quotation.quotation_date
    ? formatDate(quotation.created_at || quotation.quotation_date)
    : new Date().toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });

  doc.setFont(baseFont, 'normal');
  const dateLines = doc.splitTextToSize(formattedDate, dateAvailableWidth);
  const quotationNumberLines = doc.splitTextToSize(String(quoteNo), quotationNumberAvailableWidth);

  // Line spacing = 9pt
  const lineSpacing = 9;

  const kinhGuiHeight = companyLines.length * lineSpacing + lineSpacing;
  const recipientHeight = recipientLines.length * lineSpacing + lineSpacing;
  const phoneHeight = phoneLines.length * lineSpacing + lineSpacing;
  const emailHeight = emailLines.length * lineSpacing + lineSpacing;
  const addressHeight = addressLines.length * lineSpacing;

  const leftColumnHeight = kinhGuiHeight + recipientHeight + phoneHeight + emailHeight + addressHeight;

  const dateHeight = dateLines.length * lineSpacing + lineSpacing;
  const quotationNumberHeight = quotationNumberLines.length * lineSpacing;

  const rightColumnHeight = dateHeight + quotationNumberHeight;

  const boxHeight = boxPadding + Math.max(leftColumnHeight, rightColumnHeight) + boxPadding;

  doc.setDrawColor(200, 200, 200);
  doc.setLineWidth(0.5);
  doc.rect(margin, boxStartY, pageWidth - margin * 2, boxHeight);

  doc.setFontSize(9);
  doc.setTextColor(0, 0, 0);

  const leftColumnX = margin + boxPadding;
  const rightColumnX = margin + boxPadding + leftColumnWidth + columnGap;

  let leftY = boxStartY + boxPadding + 6;

  // Kính gửi
  doc.setFont(baseFont, 'bold');
  doc.text(kinhGuiText, leftColumnX, leftY);
  doc.setFont(baseFont, 'normal');
  doc.text(companyLines[0] || '', leftColumnX + maxLeftLabelWidth, leftY);
  leftY += 9;
  if (companyLines.length > 1) {
    for (let i = 1; i < companyLines.length; i++) {
      doc.text(companyLines[i], leftColumnX + maxLeftLabelWidth, leftY);
      leftY += 9;
    }
  }
  leftY += 9;

  // Người nhận
  doc.setFont(baseFont, 'bold');
  doc.text(nguoiNhanText, leftColumnX, leftY);
  doc.setFont(baseFont, 'normal');
  doc.text(recipientLines[0] || '', leftColumnX + maxLeftLabelWidth, leftY);
  leftY += 9;
  if (recipientLines.length > 1) {
    for (let i = 1; i < recipientLines.length; i++) {
      doc.text(recipientLines[i], leftColumnX + maxLeftLabelWidth, leftY);
      leftY += 9;
    }
  }
  leftY += 9;

  // Số điện thoại
  doc.setFont(baseFont, 'bold');
  doc.text(soDienThoaiText, leftColumnX, leftY);
  doc.setFont(baseFont, 'normal');
  doc.text(phoneLines[0] || '', leftColumnX + maxLeftLabelWidth, leftY);
  leftY += 9;
  if (phoneLines.length > 1) {
    for (let i = 1; i < phoneLines.length; i++) {
      doc.text(phoneLines[i], leftColumnX + maxLeftLabelWidth, leftY);
      leftY += 9;
    }
  }
  leftY += 9;

  // Email
  doc.setFont(baseFont, 'bold');
  doc.text(emailText, leftColumnX, leftY);
  doc.setFont(baseFont, 'normal');
  doc.text(emailLines[0] || '', leftColumnX + maxLeftLabelWidth, leftY);
  leftY += 9;
  if (emailLines.length > 1) {
    for (let i = 1; i < emailLines.length; i++) {
      doc.text(emailLines[i], leftColumnX + maxLeftLabelWidth, leftY);
      leftY += 9;
    }
  }
  leftY += 9;

  // Địa chỉ
  doc.setFont(baseFont, 'bold');
  doc.text(diaChiText, leftColumnX, leftY);
  doc.setFont(baseFont, 'normal');
  doc.text(addressLines[0] || '', leftColumnX + maxLeftLabelWidth, leftY);
  leftY += 9;
  if (addressLines.length > 1) {
    for (let i = 1; i < addressLines.length; i++) {
      doc.text(addressLines[i], leftColumnX + maxLeftLabelWidth, leftY);
      leftY += 9;
    }
  }

  // Render Right Column
  let rightY = boxStartY + boxPadding + 6;

  // Ngày báo giá
  doc.setFont(baseFont, 'bold');
  doc.setTextColor(0, 0, 0);
  doc.text(ngayBaoGiaText, rightColumnX, rightY);
  doc.setFont(baseFont, 'normal');
  doc.setTextColor(255, 0, 0);
  doc.text(dateLines[0] || '', rightColumnX + ngayBaoGiaWidth, rightY);
  rightY += 9;
  if (dateLines.length > 1) {
    for (let i = 1; i < dateLines.length; i++) {
      doc.text(dateLines[i], rightColumnX + ngayBaoGiaWidth, rightY);
      rightY += 9;
    }
  }
  rightY += 9;

  // Số báo giá
  doc.setFont(baseFont, 'bold');
  doc.setTextColor(0, 0, 0);
  doc.text(soBaoGiaText, rightColumnX, rightY);
  doc.setFont(baseFont, 'normal');
  doc.setTextColor(255, 0, 0);
  doc.text(quotationNumberLines[0] || '', rightColumnX + soBaoGiaWidth, rightY);
  rightY += 9;
  if (quotationNumberLines.length > 1) {
    for (let i = 1; i < quotationNumberLines.length; i++) {
      doc.text(quotationNumberLines[i], rightColumnX + soBaoGiaWidth, rightY);
      rightY += 9;
    }
  }

  doc.setTextColor(0, 0, 0);
  cursorY = boxStartY + boxHeight + 15;

  // ==========================================
  // 4. LỜI CẢM ƠN MỞ ĐẦU (size 9)
  // ==========================================
  const disclaimer = `${company.name || 'Công ty chúng tôi'} gửi lời cảm ơn sự tin tưởng và luôn đồng hành của Quý Khách hàng đối với Công Ty Chúng Tôi. Chúng tôi xin gửi tới Quý Công Ty bản báo giá các sản phẩm, kèm theo các điều kiện thương mại cụ thể của gói hàng.`;
  addParagraph(disclaimer, { size: 9 });
  cursorY += 15;

  // ==========================================
  // 5. BẢNG CHI TIẾT SẢN PHẨM (AutoTable)
  // ==========================================
  const activeCols = columns.filter(c => c.selected !== false);
  const headers = ['STT', ...activeCols.map(c => c.header)];

  const rawItems = quotation.items || [];
  let subtotal = 0;
  let vatTotal = 0;

  const tableRows = rawItems.map((item, index) => {
    const qty = parseFloat(item.quantity) || 0;
    const price = parseFloat(item.selling_price || item.unit_price) || 0;
    const vat = item.vat != null ? parseFloat(item.vat || item.vat_rate) || 0 : (item.vat_rate ? parseFloat(item.vat_rate) : 0);
    const itemSubtotal = qty * price;
    const itemVat = itemSubtotal * (vat / 100);

    subtotal += itemSubtotal;
    vatTotal += itemVat;

    const row = [String(index + 1)];
    activeCols.forEach(col => {
      if (col.field === 'sku') row.push(item.sku || item.product_code || item.part_number || '-');
      else if (col.field === 'product_name') row.push(item.product_name || item.name || '-');
      else if (col.field === 'brand') row.push(item.brand || item.manufacturer || '-');
      else if (col.field === 'origin') row.push(item.origin || item.country_of_origin || '-');
      else if (col.field === 'unit') row.push(item.unit || item.uom || 'cái');
      else if (col.field === 'quantity') row.push(formatNumber(qty, 0));
      else if (col.field === 'unit_price') row.push(formatNumber(price, 0));
      else if (col.field === 'vat_rate') row.push(vat.toFixed(2));
      else if (col.field === 'lead_time') row.push(item.lead_time || item.delivery_time || 'Giao ngay');
      else if (col.field === 'sub_total') row.push(formatNumber(itemSubtotal, 0));
      else row.push(String(item[col.field] != null ? item[col.field] : ''));
    });
    return row;
  });

  ensureSpace(40);

  autoTable(doc, {
    startY: cursorY,
    head: [headers],
    body: tableRows,
    margin: { left: margin, right: margin },
    styles: {
      font: baseFont,
      fontSize: 9,
      cellPadding: { top: 5, right: 6, bottom: 5, left: 6 },
      textColor: [0, 0, 0],
      lineColor: [210, 210, 210],
      lineWidth: 0.4
    },
    headStyles: {
      fillColor: [226, 234, 246],
      textColor: [0, 0, 0],
      halign: 'center',
      fontStyle: 'bold'
    },
    alternateRowStyles: {
      fillColor: [255, 255, 255]
    },
    columnStyles: activeCols.reduce((acc, col, index) => {
      const colIndex = index + 1;
      const isNumeric = col.type === 'currency' || col.type === 'number';
      acc[colIndex] = {
        halign: isNumeric ? 'right' : (col.align || 'center'),
        cellWidth: 'auto',
        minCellWidth: col.field === 'product_name' ? 75 : undefined,
        minCellHeight: 18
      };
      return acc;
    }, { 0: { cellWidth: 32, halign: 'center', minCellHeight: 18 } })
  });

  cursorY = doc.lastAutoTable ? doc.lastAutoTable.finalY : cursorY + 120;

  // ==========================================
  // 6. BẢNG TỔNG TIỀN (Summary Table)
  // ==========================================
  const grandTotal = subtotal + vatTotal;
  const mainTableColumns = activeCols.length + 1;
  const summaryLabelCols = Math.max(1, mainTableColumns - 2);

  const createSummaryRow = (label, value) => {
    const row = new Array(summaryLabelCols).fill('');
    row.push(label, value);
    return row;
  };

  autoTable(doc, {
    startY: cursorY,
    margin: { left: margin, right: margin },
    body: [
      createSummaryRow('TỔNG THÀNH TIỀN:', `${formatNumber(subtotal, 0)} VND`),
      createSummaryRow('TỔNG VAT:', `${formatNumber(vatTotal, 0)} VND`),
      createSummaryRow('TỔNG CỘNG:', `${formatNumber(grandTotal, 0)} VND`)
    ],
    theme: 'grid',
    styles: {
      font: baseFont,
      fontSize: 9,
      cellPadding: 3,
      lineColor: [210, 210, 210],
      lineWidth: 0.5
    },
    columnStyles: {
      ...Array.from({ length: summaryLabelCols }).reduce((acc, _, i) => {
        acc[i] = {
          cellWidth: 'auto',
          lineWidth: {
            top: 0.5,
            right: 0,
            bottom: 0.5,
            left: i === 0 ? 0.5 : 0
          }
        };
        return acc;
      }, {}),
      [summaryLabelCols]: {
        halign: 'right',
        fontStyle: 'bold',
        cellWidth: 'auto',
        lineWidth: { top: 0.5, right: 0.5, bottom: 0.5, left: 0 }
      },
      [summaryLabelCols + 1]: {
        halign: 'right',
        cellWidth: 'auto',
        lineWidth: { top: 0.5, right: 0.5, bottom: 0.5, left: 0.5 }
      }
    },
    bodyStyles: {
      textColor: [0, 0, 0]
    },
    didParseCell: function (data) {
      if (data.row.index === 2 && data.column.index >= summaryLabelCols) {
        data.cell.styles.fontStyle = 'bold';
        data.cell.styles.fontSize = 10;
      }
    }
  });

  cursorY = doc.lastAutoTable.finalY + 18;

  // ==========================================
  // 7. GHI CHÚ / ĐIỀU KHOẢN (2 Cột 100% theo QuotationPDF.js)
  // ==========================================
  const notesColumnGap = orientation === 'portrait' ? 20 : 40;
  const availableWidth = pageWidth - margin * 2;
  const notesColumnWidth = (availableWidth - notesColumnGap) / 2;

  const notesLeftX = margin + 10;
  const notesRightX = margin + notesColumnWidth + notesColumnGap;
  const bulletSize = 1.5;
  const notesFontSize = 9.5;
  const lineHeight = 12.5;
  const maxWidth = notesColumnWidth - 20;

  const wrapNoteText = (label, text, colMaxWidth, fontSize = 9.5) => {
    doc.setFont(baseFont, 'bold');
    doc.setFontSize(fontSize);
    const labelWidth = doc.getTextWidth(label);

    const firstLineWidth = Math.max(colMaxWidth - labelWidth - 3, 40);
    const subsequentLineWidth = colMaxWidth;

    doc.setFont(baseFont, 'normal');
    doc.setFontSize(fontSize);

    const trimmed = text ? text.trim() : '';
    if (!trimmed) return { labelWidth, lines: [] };

    const words = trimmed.split(/\s+/);
    const lines = [];
    let currentLine = '';
    let isFirst = true;

    for (let i = 0; i < words.length; i++) {
      const word = words[i];
      const testLine = currentLine ? currentLine + ' ' + word : word;
      const allowedWidth = isFirst ? firstLineWidth : subsequentLineWidth;

      if (doc.getTextWidth(testLine) <= allowedWidth) {
        currentLine = testLine;
      } else {
        if (currentLine) {
          lines.push(currentLine);
          isFirst = false;
          if (doc.getTextWidth(word) > subsequentLineWidth) {
            const subLines = doc.splitTextToSize(word, subsequentLineWidth);
            subLines.slice(0, -1).forEach(sl => lines.push(sl));
            currentLine = subLines[subLines.length - 1];
          } else {
            currentLine = word;
          }
        } else {
          const subLines = doc.splitTextToSize(word, allowedWidth);
          subLines.slice(0, -1).forEach(sl => lines.push(sl));
          currentLine = subLines[subLines.length - 1];
          isFirst = false;
        }
      }
    }
    if (currentLine) lines.push(currentLine);

    return { labelWidth, lines };
  };

  const leftTermsList = terms.leftTerms || [
    { label: 'Địa điểm giao hàng:', value: 'Tại Kho Quý Công ty.' },
    { label: 'Thời gian cấp hàng:', value: 'Theo Báo giá, tính từ thời điểm đặt hàng.' },
    { label: 'Báo giá có giá trị:', value: '30 ngày.' },
    { label: 'Lưu ý:', value: `Kiểm tra mô tả, ký hiệu hàng hóa trước khi xác nhận đặt hàng. Hàng hóa khi đã giao nhận, ${company.shortName || 'chúng tôi'} không đổi trả lại hàng hóa.` },
    { label: 'Chất lượng:', value: 'Hàng mới 100%, Chất lượng hàng hóa và Bảo hành 12 tháng theo tiêu chuẩn nhà sản xuất.' },
    { label: 'Cam kết:', value: 'Chúng tôi cam kết rằng các sản phẩm chúng tôi cung cấp là sản phẩm chính hãng 100%. Nếu bị phát hiện là hàng giả, hàng nhái, chúng tôi sẽ bồi thường gấp 10 lần giá trị hàng hóa bị phát hiện.' }
  ];

  const rightTermsList = terms.rightTerms || [
    { label: 'Thanh toán:', value: '100% giá trị đơn hàng khi xác nhận đặt hàng.' },
    { label: 'Phương thức thanh toán:', value: 'Bằng tiền mặt hoặc chuyển khoản.' }
  ];

  const leftNotes = leftTermsList.filter(t => t.label && t.value).map(t => ({
    label: t.label,
    text: t.value.endsWith('.') ? t.value : `${t.value}.`
  }));

  const rightNotes = rightTermsList.filter(t => t.label && t.value).map(t => ({
    label: t.label,
    text: t.value.endsWith('.') ? t.value : `${t.value}.`
  }));

  // Bank Info
  const bankAcc = company.bankAccounts?.[0] || {};
  const preparedBankLines = [];
  if (bankAcc.accountNumber || bankAcc.beneficiary) {
    rightNotes.push({ label: 'Thông tin chuyển khoản:', text: '', isBankHeader: true });
    doc.setFont(baseFont, 'normal');
    doc.setFontSize(notesFontSize);
    const rawBankLines = [];
    if (bankAcc.beneficiary) rawBankLines.push(`Đơn vị nhận: ${bankAcc.beneficiary}`);
    if (bankAcc.accountNumber) rawBankLines.push(`Số tài khoản: ${bankAcc.accountNumber}`);
    if (bankAcc.bankName) {
      const bFull = `${bankAcc.bankName}${bankAcc.branch ? ` - ${bankAcc.branch}` : ''}`;
      rawBankLines.push(bFull);
    }
    rawBankLines.forEach(l => {
      const wrapped = doc.splitTextToSize(l, maxWidth);
      wrapped.forEach(wl => preparedBankLines.push(wl));
    });
  }

  const preparedLeftNotes = leftNotes.map(term => ({
    ...term,
    ...wrapNoteText(term.label, term.text, maxWidth, notesFontSize)
  }));

  const preparedRightNotes = rightNotes.map(term => {
    if (term.isBankHeader) {
      doc.setFont(baseFont, 'bold');
      doc.setFontSize(notesFontSize);
      return {
        ...term,
        labelWidth: doc.getTextWidth(term.label),
        lines: []
      };
    }
    return {
      ...term,
      ...wrapNoteText(term.label, term.text, maxWidth, notesFontSize)
    };
  });

  const leftTotalLines = preparedLeftNotes.reduce((sum, n) => sum + Math.max(n.lines.length, 1), 0);
  let rightTotalLines = 0;
  preparedRightNotes.forEach(n => {
    if (n.isBankHeader) {
      rightTotalLines += 1 + preparedBankLines.length;
    } else {
      rightTotalLines += Math.max(n.lines.length, 1);
    }
  });

  const maxContentLines = Math.max(leftTotalLines, rightTotalLines);
  const requiredNotesHeight = 18 + maxContentLines * lineHeight + 10;
  const requiredSignatureHeight = 90;

  if (
    cursorY + requiredNotesHeight > pageHeight - margin ||
    (cursorY + requiredNotesHeight + requiredSignatureHeight > pageHeight - margin && cursorY > pageHeight / 2)
  ) {
    doc.addPage();
    doc.setFont(baseFont, 'normal');
    doc.setFontSize(notesFontSize);
    cursorY = margin;
  }

  const notesStartY = cursorY;

  // "GHI CHÚ:"
  doc.setFont(baseFont, 'bold');
  doc.setFontSize(11);
  doc.setTextColor(...primaryBlue);
  doc.text('GHI CHÚ:', margin + 10, notesStartY + 3);

  doc.setTextColor(0, 0, 0);
  let notesLeftY = notesStartY + 18;
  let notesRightY = notesStartY + 18;

  // Render Cột Trái Ghi Chú
  preparedLeftNotes.forEach(note => {
    doc.setFillColor(0, 0, 0);
    doc.circle(notesLeftX + 2, notesLeftY - 2, bulletSize, 'F');

    doc.setFont(baseFont, 'bold');
    doc.setFontSize(notesFontSize);
    doc.text(note.label, notesLeftX + 8, notesLeftY);

    doc.setFont(baseFont, 'normal');
    doc.setFontSize(notesFontSize);
    const textX = notesLeftX + 8 + note.labelWidth + 3;

    if (note.lines.length === 0) {
      notesLeftY += lineHeight;
    } else if (note.lines.length === 1) {
      doc.text(note.lines[0], textX, notesLeftY);
      notesLeftY += lineHeight;
    } else {
      doc.text(note.lines[0], textX, notesLeftY);
      notesLeftY += lineHeight;
      note.lines.slice(1).forEach(line => {
        doc.text(line, notesLeftX + 8, notesLeftY);
        notesLeftY += lineHeight;
      });
    }
  });

  // Render Cột Phải Ghi Chú
  preparedRightNotes.forEach(note => {
    doc.setFillColor(0, 0, 0);
    doc.circle(notesRightX + 2, notesRightY - 2, bulletSize, 'F');

    doc.setFont(baseFont, 'bold');
    doc.setFontSize(notesFontSize);
    doc.text(note.label, notesRightX + 8, notesRightY);

    if (note.isBankHeader) {
      notesRightY += lineHeight;
      doc.setFont(baseFont, 'normal');
      doc.setFontSize(notesFontSize);
      preparedBankLines.forEach(line => {
        doc.text(line, notesRightX + 8, notesRightY);
        notesRightY += lineHeight;
      });
    } else if (note.lines.length === 0) {
      notesRightY += lineHeight;
    } else if (note.lines.length === 1) {
      doc.setFont(baseFont, 'normal');
      doc.setFontSize(notesFontSize);
      const textX = notesRightX + 8 + note.labelWidth + 3;
      doc.text(note.lines[0], textX, notesRightY);
      notesRightY += lineHeight;
    } else {
      doc.setFont(baseFont, 'normal');
      doc.setFontSize(notesFontSize);
      const textX = notesRightX + 8 + note.labelWidth + 3;
      doc.text(note.lines[0], textX, notesRightY);
      notesRightY += lineHeight;
      note.lines.slice(1).forEach(line => {
        doc.text(line, notesRightX + 8, notesRightY);
        notesRightY += lineHeight;
      });
    }
  });

  cursorY = Math.max(notesLeftY, notesRightY) + 10;

  // ==========================================
  // 8. CHỮ KÝ (XÁC NHẬN ĐẶT HÀNG / ĐẠI DIỆN BÊN BÁN HÀNG)
  // ==========================================
  ensureSpace(80);
  cursorY += 20;

  const signatureBoxWidth = (pageWidth - margin * 2 - 40) / 2;
  const signatureLeftX = margin;
  const signatureRightX = pageWidth - margin - signatureBoxWidth;

  doc.setFont(baseFont, 'bold');
  doc.setFontSize(10);
  doc.setTextColor(0, 0, 0);
  doc.text('XÁC NHẬN ĐẶT HÀNG', signatureLeftX + signatureBoxWidth / 2, cursorY, { align: 'center' });
  doc.text('ĐẠI DIỆN BÊN BÁN HÀNG', signatureRightX + signatureBoxWidth / 2, cursorY, { align: 'center' });

  const creatorName = quotation.created_by_name || currentUser?.name || '';
  if (creatorName) {
    doc.setFont(baseFont, 'bold');
    doc.setFontSize(10);
    doc.setTextColor(...primaryBlue);
    doc.text(creatorName, signatureRightX + signatureBoxWidth / 2, cursorY + 90, { align: 'center' });
  }

  return doc;
}

export async function exportQuotationPdf(quotation, options = {}) {
  const doc = await createQuotationPdfDoc(quotation, options);
  const quoteNo = quotation.quotation_number || quotation.id || 'Bao_Gia';
  const fileName = options.fileName || `Bao_Gia_${quoteNo}.pdf`;
  const blob = doc.output('blob');
  saveAs(blob, fileName);
  return blob;
}

export async function getQuotationPdfBlob(quotation, options = {}) {
  const doc = await createQuotationPdfDoc(quotation, options);
  return doc.output('blob');
}

export async function getQuotationPdfBase64(quotation, options = {}) {
  const doc = await createQuotationPdfDoc(quotation, options);
  return doc.output('datauristring');
}
