import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';
import { saveAs } from 'file-saver';
import { getCompanyConfig, getSavedQuotationTerms } from '../config/companyConfig';
import { formatDate } from './format';

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
    console.warn('Không thể tải font trực tuyến, fallback font mặc định:', err);
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
  { field: 'sku', header: 'Mã Hàng', selected: true, type: 'text', width: 65, align: 'center' },
  { field: 'product_name', header: 'Tên Sản Phẩm / Mô Tả Hàng Hóa', selected: true, type: 'text', align: 'left' },
  { field: 'unit', header: 'ĐVT', selected: true, type: 'text', width: 35, align: 'center' },
  { field: 'quantity', header: 'SL', selected: true, type: 'number', width: 35, align: 'center' },
  { field: 'unit_price', header: 'Đơn Giá (VNĐ)', selected: true, type: 'currency', width: 75, align: 'right' },
  { field: 'sub_total', header: 'Thành Tiền (VNĐ)', selected: true, type: 'currency', width: 85, align: 'right' },
  { field: 'lead_time', header: 'Giao Hàng', selected: true, type: 'text', width: 55, align: 'center' }
];

export async function createQuotationPdfDoc(quotation, options = {}) {
  const {
    orientation = 'landscape',
    columns = defaultPdfColumns.filter(c => c.selected),
    company = getCompanyConfig(),
    terms = getSavedQuotationTerms(),
    currentUser = null
  } = options;

  const normalizedOrientation = orientation === 'portrait' ? 'portrait' : 'landscape';
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
    console.warn('Lỗi nạp font:', e);
  }

  const pageWidth = doc.internal.pageSize.getWidth();
  const pageHeight = doc.internal.pageSize.getHeight();
  const margin = 36;
  const primaryBlue = [22, 119, 255];
  let cursorY = margin;

  // 1. Header Công Ty
  doc.setFont(baseFont, 'bold');
  doc.setFontSize(orientation === 'landscape' ? 14 : 13);
  doc.setTextColor(...primaryBlue);
  doc.text(company.name, margin, cursorY + 12);

  cursorY += 26;
  doc.setFont(baseFont, 'normal');
  doc.setFontSize(9);
  doc.setTextColor(70, 70, 70);

  doc.text(`Địa chỉ: ${company.address}`, margin, cursorY);
  cursorY += 13;

  doc.text(`MST: ${company.taxCode}   |   Tel: ${company.tel}   |   Hotline: ${company.hotline}   |   Email: ${company.email}   |   Website: ${company.website}`, margin, cursorY);
  cursorY += 20;

  // Đường kẻ ngang mỏng phân cách Header
  doc.setDrawColor(220, 226, 235);
  doc.setLineWidth(0.75);
  doc.line(margin, cursorY, pageWidth - margin, cursorY);
  cursorY += 18;

  // 2. Tiêu Đề BÁO GIÁ
  doc.setFont(baseFont, 'bold');
  doc.setFontSize(orientation === 'landscape' ? 20 : 18);
  doc.setTextColor(...primaryBlue);
  doc.text('BÁO GIÁ THƯƠNG MẠI', pageWidth / 2, cursorY, { align: 'center' });
  cursorY += 16;

  // 3. Khung Thông Tin Khách Hàng (Box 2 cột)
  const boxStartY = cursorY;
  const boxPadding = 8;
  const boxInnerWidth = pageWidth - margin * 2 - boxPadding * 2;
  const leftColWidth = boxInnerWidth * 0.68;
  const rightColWidth = boxInnerWidth * 0.32;
  const leftX = margin + boxPadding;
  const rightX = margin + boxPadding + leftColWidth + 8;

  const custName = quotation.customer_name || quotation.customer?.name || 'Quý Khách Hàng';
  const contactName = quotation.contact_name || quotation.contact?.name || 'Phòng Mua Hàng / Dự Án';
  const custPhone = quotation.customer_phone || quotation.contact_phone || '-';
  const custEmail = quotation.customer_email || quotation.contact_email || '-';
  const custAddress = quotation.customer_address || quotation.address || 'Tại trụ sở Quý Công ty';

  const quoteNo = quotation.quotation_number || quotation.id || 'QT-DRAFT';
  const quoteDate = formatDate(quotation.created_at || quotation.quotation_date || new Date());
  const validUntil = quotation.valid_until ? formatDate(quotation.valid_until) : '30 ngày kể từ ngày phát hành';

  let lY = boxStartY + boxPadding + 6;
  doc.setFontSize(8.5);

  // Kính gửi
  doc.setFont(baseFont, 'bold');
  doc.setTextColor(0, 0, 0);
  doc.text('Kính gửi:', leftX, lY);
  doc.setFont(baseFont, 'bold');
  doc.setTextColor(...primaryBlue);
  doc.text(custName, leftX + 56, lY);
  lY += 12;

  // Người nhận
  doc.setFont(baseFont, 'bold');
  doc.setTextColor(0, 0, 0);
  doc.text('Người nhận:', leftX, lY);
  doc.setFont(baseFont, 'normal');
  doc.text(`${contactName}   (SĐT: ${custPhone}   |   Email: ${custEmail})`, leftX + 56, lY);
  lY += 12;

  // Địa chỉ
  doc.setFont(baseFont, 'bold');
  doc.text('Địa chỉ:', leftX, lY);
  doc.setFont(baseFont, 'normal');
  doc.text(custAddress, leftX + 56, lY, { maxWidth: leftColWidth - 60 });
  lY += 12;

  // Cột phải: Số báo giá & Ngày báo giá
  let rY = boxStartY + boxPadding + 6;
  doc.setFont(baseFont, 'bold');
  doc.setTextColor(0, 0, 0);
  doc.text('Số báo giá:', rightX, rY);
  doc.setFont(baseFont, 'bold');
  doc.setTextColor(220, 38, 38);
  doc.text(String(quoteNo), rightX + 65, rY);
  rY += 12;

  doc.setFont(baseFont, 'bold');
  doc.setTextColor(0, 0, 0);
  doc.text('Ngày báo giá:', rightX, rY);
  doc.setFont(baseFont, 'normal');
  doc.text(String(quoteDate), rightX + 65, rY);
  rY += 12;

  doc.setFont(baseFont, 'bold');
  doc.text('Hiệu lực:', rightX, rY);
  doc.setFont(baseFont, 'normal');
  doc.text(String(validUntil), rightX + 65, rY);

  const boxHeight = Math.max(lY, rY) - boxStartY + 4;
  doc.setDrawColor(210, 215, 225);
  doc.setLineWidth(0.5);
  doc.rect(margin, boxStartY, pageWidth - margin * 2, boxHeight);

  cursorY = boxStartY + boxHeight + 10;

  // Lời cảm ơn mở đầu
  doc.setFont(baseFont, 'normal');
  doc.setFontSize(8.5);
  doc.setTextColor(60, 60, 60);
  doc.text(`${company.shortName || 'Thanh Tùng'} xin chân thành cảm ơn sự quan tâm của Quý khách hàng đối với sản phẩm và dịch vụ của chúng tôi. Chúng tôi xin trân trọng gửi tới Quý khách bảng báo giá chi tiết như sau:`, margin, cursorY, { maxWidth: pageWidth - margin * 2 });
  cursorY += 14;

  // 4. Bảng Vật Tư & Chi Tiết Sản Phẩm (AutoTable)
  const activeCols = columns.filter(c => c.selected !== false);
  const headers = ['STT', ...activeCols.map(c => c.header)];

  const rawItems = quotation.items || [];
  let calcSubTotal = 0;
  let calcVatTotal = 0;

  const tableRows = rawItems.map((item, idx) => {
    const qty = Number(item.quantity) || 0;
    const price = Number(item.unit_price || item.selling_price) || 0;
    const rowSubTotal = Number(item.total_amount || item.sub_total) || (qty * price);
    const vatRate = Number(item.vat_rate !== undefined ? item.vat_rate : (item.vat || 10));
    const vatAmount = rowSubTotal * (vatRate / 100);

    calcSubTotal += rowSubTotal;
    calcVatTotal += vatAmount;

    const row = [String(idx + 1)];
    activeCols.forEach(col => {
      if (col.field === 'sku') row.push(item.sku || item.product_code || '-');
      else if (col.field === 'product_name') row.push(item.product_name || item.name || '-');
      else if (col.field === 'unit') row.push(item.unit || item.uom || 'Cái');
      else if (col.field === 'quantity') row.push(new Intl.NumberFormat('vi-VN').format(qty));
      else if (col.field === 'unit_price') row.push(new Intl.NumberFormat('vi-VN').format(price));
      else if (col.field === 'sub_total') row.push(new Intl.NumberFormat('vi-VN').format(rowSubTotal));
      else if (col.field === 'vat_rate') row.push(`${vatRate}%`);
      else if (col.field === 'vat_amount') row.push(new Intl.NumberFormat('vi-VN').format(vatAmount));
      else if (col.field === 'lead_time') row.push(item.lead_time || item.delivery_time || 'Có sẵn');
      else row.push(String(item[col.field] || ''));
    });
    return row;
  });

  autoTable(doc, {
    startY: cursorY,
    head: [headers],
    body: tableRows,
    margin: { left: margin, right: margin },
    styles: {
      font: baseFont,
      fontSize: 8.5,
      cellPadding: { top: 4, right: 5, bottom: 4, left: 5 },
      textColor: [0, 0, 0],
      lineColor: [215, 220, 230],
      lineWidth: 0.4
    },
    headStyles: {
      fillColor: [235, 242, 253],
      textColor: [15, 23, 42],
      halign: 'center',
      fontStyle: 'bold'
    },
    columnStyles: {
      0: { cellWidth: 28, halign: 'center' },
      ...activeCols.reduce((acc, col, i) => {
        const colIdx = i + 1;
        acc[colIdx] = {
          halign: col.align || (col.type === 'currency' ? 'right' : (col.type === 'number' ? 'center' : 'left')),
          cellWidth: col.width || 'auto'
        };
        return acc;
      }, {})
    }
  });

  cursorY = doc.lastAutoTable ? doc.lastAutoTable.finalY : cursorY + 80;

  // 5. Bảng Tổng Kết Số Tiền (Summary Table)
  const grandTotal = calcSubTotal + calcVatTotal;
  const numCols = headers.length;
  const emptyColsCount = Math.max(1, numCols - 2);

  const createSummaryRow = (label, valStr) => {
    const row = new Array(emptyColsCount).fill('');
    row.push(label, valStr);
    return row;
  };

  autoTable(doc, {
    startY: cursorY,
    margin: { left: margin, right: margin },
    body: [
      createSummaryRow('TỔNG TIỀN HÀNG (CHƯA VAT):', `${new Intl.NumberFormat('vi-VN').format(calcSubTotal)} VNĐ`),
      createSummaryRow('TIỀN THUẾ GTGT (VAT):', `${new Intl.NumberFormat('vi-VN').format(calcVatTotal)} VNĐ`),
      createSummaryRow('TỔNG CỘNG THANH TOÁN:', `${new Intl.NumberFormat('vi-VN').format(grandTotal)} VNĐ`)
    ],
    theme: 'grid',
    styles: {
      font: baseFont,
      fontSize: 8.5,
      cellPadding: 3,
      lineColor: [215, 220, 230],
      lineWidth: 0.4
    },
    columnStyles: {
      [emptyColsCount]: { halign: 'right', fontStyle: 'bold', cellWidth: 'auto' },
      [emptyColsCount + 1]: { halign: 'right', fontStyle: 'bold', cellWidth: 100 }
    },
    didParseCell: (data) => {
      if (data.row.index === 2 && data.column.index >= emptyColsCount) {
        data.cell.styles.fontStyle = 'bold';
        data.cell.styles.textColor = [16, 185, 129]; // Emerald bold for grand total
        data.cell.styles.fontSize = 9.5;
      }
    }
  });

  cursorY = doc.lastAutoTable ? doc.lastAutoTable.finalY + 12 : cursorY + 50;

  // 6. Điều Khoản Thương Mại (Chia 2 Cột Linh Hoạt - Left Terms & Right Terms)
  const leftTerms = terms.leftTerms || [];
  const rightTerms = terms.rightTerms || [];
  const bankAcc = terms.bankAccount || company.bankAccounts?.[0] || {};

  const termsColGap = 16;
  const termsColWidth = (pageWidth - margin * 2 - termsColGap) / 2;
  const tLeftX = margin;
  const tRightX = margin + termsColWidth + termsColGap;

  let termsLeftY = cursorY;
  let termsRightY = cursorY;

  // Render Cột Trái Điều Khoản
  leftTerms.forEach(tItem => {
    doc.setFont(baseFont, 'bold');
    doc.setFontSize(8);
    doc.setTextColor(30, 41, 59);
    doc.text(`• ${tItem.label}`, tLeftX, termsLeftY);
    const labelW = doc.getTextWidth(`• ${tItem.label} `);

    doc.setFont(baseFont, 'normal');
    doc.setTextColor(71, 85, 105);
    const lines = doc.splitTextToSize(tItem.value || '', termsColWidth - labelW);
    doc.text(lines, tLeftX + labelW, termsLeftY);
    termsLeftY += Math.max(1, lines.length) * 10 + 2;
  });

  // Render Cột Phải Điều Khoản
  rightTerms.forEach(tItem => {
    doc.setFont(baseFont, 'bold');
    doc.setFontSize(8);
    doc.setTextColor(30, 41, 59);
    doc.text(`• ${tItem.label}`, tRightX, termsRightY);
    const labelW = doc.getTextWidth(`• ${tItem.label} `);

    doc.setFont(baseFont, 'normal');
    doc.setTextColor(71, 85, 105);
    const lines = doc.splitTextToSize(tItem.value || '', termsColWidth - labelW);
    doc.text(lines, tRightX + labelW, termsRightY);
    termsRightY += Math.max(1, lines.length) * 10 + 2;
  });

  // Box Thông tin chuyển khoản ở Cột Phải
  if (bankAcc.accountNumber || bankAcc.beneficiary) {
    termsRightY += 4;
    const bankBoxStartY = termsRightY;
    doc.setFillColor(248, 250, 252);
    doc.setDrawColor(203, 213, 225);
    doc.roundedRect(tRightX, bankBoxStartY, termsColWidth, 42, 3, 3, 'FD');

    doc.setFont(baseFont, 'bold');
    doc.setFontSize(8);
    doc.setTextColor(...primaryBlue);
    doc.text('Thông tin chuyển khoản ngân hàng:', tRightX + 6, bankBoxStartY + 10);

    doc.setFont(baseFont, 'normal');
    doc.setFontSize(7.5);
    doc.setTextColor(51, 65, 85);
    doc.text(`Đơn vị nhận: ${bankAcc.beneficiary || company.name}`, tRightX + 6, bankBoxStartY + 20);
    doc.text(`Số tài khoản: ${bankAcc.accountNumber}   |   ${bankAcc.bankName || 'BIDV'}`, tRightX + 6, bankBoxStartY + 30);
    doc.text(`Chi nhánh: ${bankAcc.branch || 'Hà Nội'}`, tRightX + 6, bankBoxStartY + 38);

    termsRightY += 48;
  }

  cursorY = Math.max(termsLeftY, termsRightY) + 12;

  // Kiểm tra tràn trang trước khi vẽ chữ ký
  if (cursorY + 60 > pageHeight - margin) {
    doc.addPage();
    cursorY = margin + 10;
  }

  // 7. Chữ Ký & Xác Nhận (2 Cột Bên Mua & Bên Bán)
  const signColWidth = (pageWidth - margin * 2) / 2;
  const leftSignX = margin + signColWidth / 2;
  const rightSignX = margin + signColWidth + signColWidth / 2;

  doc.setFont(baseFont, 'bold');
  doc.setFontSize(8.5);
  doc.setTextColor(15, 23, 42);

  doc.text('ĐẠI DIỆN KHÁCH HÀNG', leftSignX, cursorY, { align: 'center' });
  doc.text(`ĐẠI DIỆN ${company.shortName || 'THANH TÙNG'}`, rightSignX, cursorY, { align: 'center' });

  cursorY += 10;
  doc.setFont(baseFont, 'italic');
  doc.setFontSize(7.5);
  doc.setTextColor(100, 116, 139);
  doc.text('(Ký, ghi rõ họ tên và đóng dấu)', leftSignX, cursorY, { align: 'center' });
  doc.text('(Người lập báo giá)', rightSignX, cursorY, { align: 'center' });

  // Tên người lập báo giá phía dưới
  const creatorName = quotation.created_by_name || currentUser?.name || 'Nguyễn Thanh Tùng';
  doc.setFont(baseFont, 'bold');
  doc.setFontSize(8.5);
  doc.setTextColor(...primaryBlue);
  doc.text(creatorName, rightSignX, cursorY + 45, { align: 'center' });

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
