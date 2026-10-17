import { loadPdfLibs } from './sampleSupport';
import FileSaver from 'file-saver';
const saveAs = FileSaver.saveAs || FileSaver;
import { formatNumber } from './sampleSupport';
import {
    convertAmountToVnd,
    formatPdfCurrencyValue,
    formatPdfMoneyValue,
    resolvePdfCurrency,
    resolvePdfExchangeRate,
} from './sampleSupport';
import { registerRobotoFonts } from './sampleSupport';
import { getCompanyConfig } from './sampleSupport';
import { getJsPdfImageFormatFromUrl, loadImageAsDataUrl } from './sampleSupport';

let jsPDF;
let autoTable;

const ensurePdfLibs = async () => {
    ({ jsPDF, autoTable } = await loadPdfLibs());
};

const VIETNAMESE_DIGITS = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
const VIETNAMESE_SCALE = ['', 'nghìn', 'triệu', 'tỷ', 'nghìn tỷ', 'triệu tỷ'];

const ensureBrowserEnvironment = () => {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        throw new Error('Chức năng xuất PDF chỉ khả dụng trên trình duyệt.');
    }
};

const openPdfPreview = (blob, fileName) => {
    const url = URL.createObjectURL(blob);
    const previewWindow = window.open(url, '_blank');

    if (!previewWindow) {
        saveAs(blob, fileName);
    } else {
        previewWindow.document.title = fileName;
    }

    setTimeout(() => URL.revokeObjectURL(url), 120000);
};

const safeText = (value, fallback = '') => (value && String(value).trim()) || fallback;

const safeNumber = (value, fallback = 0) => {
    const number = Number(value);
    return Number.isFinite(number) ? number : fallback;
};

const splitIntoTriples = (number) => {
    const triples = [];
    let remaining = number;

    while (remaining > 0) {
        triples.push(remaining % 1000);
        remaining = Math.floor(remaining / 1000);
    }

    return triples;
};

const readTriple = (triple, showZeroHundreds) => {
    const hundreds = Math.floor(triple / 100);
    const tens = Math.floor((triple % 100) / 10);
    const ones = triple % 10;
    const parts = [];

    if (hundreds || showZeroHundreds) {
        parts.push(`${VIETNAMESE_DIGITS[hundreds]} trăm`);
    }

    if (tens > 1) {
        parts.push(`${VIETNAMESE_DIGITS[tens]} mươi`);
        if (ones === 1) {
            parts.push('mốt');
        } else if (ones === 5) {
            parts.push('lăm');
        } else if (ones > 0) {
            parts.push(VIETNAMESE_DIGITS[ones]);
        }
    } else if (tens === 1) {
        parts.push('mười');
        if (ones === 5) {
            parts.push('lăm');
        } else if (ones > 0) {
            parts.push(VIETNAMESE_DIGITS[ones]);
        }
    } else if (ones > 0) {
        if (showZeroHundreds && hundreds) {
            parts.push('lẻ');
        }
        if (ones === 5 && triple > 5) {
            parts.push('lăm');
        } else {
            parts.push(VIETNAMESE_DIGITS[ones]);
        }
    }

    return parts.join(' ').trim();
};

const numberToVietnameseWords = (value) => {
    const number = Math.round(safeNumber(value, 0));

    if (number === 0) {
        return 'Không đồng chẵn';
    }

    const triples = splitIntoTriples(number);
    const words = [];

    for (let index = triples.length - 1; index >= 0; index -= 1) {
        const triple = triples[index];
        if (triple === 0) {
            continue;
        }

        const tripleWords = readTriple(triple, index !== triples.length - 1);
        const scale = VIETNAMESE_SCALE[index] || '';
        words.push(`${tripleWords}${scale ? ` ${scale}` : ''}`.trim());
    }

    const sentence = words.join(' ').replace(/\s+/g, ' ').trim();
    return sentence.charAt(0).toUpperCase() + sentence.slice(1) + ' đồng chẵn.';
};

const formatVietnameseDate = (dateValue) => {
    if (!dateValue) {
        return '';
    }

    const date = new Date(dateValue);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const day = date.getDate();
    const month = date.getMonth() + 1;
    const year = date.getFullYear();
    return `Ngày ${day} tháng ${month} năm ${year}`;
};

const collectInvoiceItems = (shipment, shipments = []) => {
    // Nếu có nhiều shipments (trường hợp gộp hóa đơn), merge tất cả items
    if (Array.isArray(shipments) && shipments.length > 1) {
        const allItems = [];
        shipments.forEach(ship => {
            if (ship?.items?.length) {
                ship.items.forEach(item => {
                    const quantity = safeNumber(item.quantity_shipped ?? item.quantity, 0);
                    const unitPrice = safeNumber(item.selling_price, 0);
                    const subTotal = quantity * unitPrice;

                    allItems.push({
                        name:
                            safeText(item.item_description) ||
                            safeText(item.designation) ||
                            safeText(item.material_code),
                        unit: safeText(item.unit),
                        quantity,
                        unitPrice,
                        subTotal,
                        vat: safeNumber(item.vat, 0),
                    });
                });
            }
        });

        return allItems
            .filter((item) => item.quantity !== 0)
            .map((item, index) => ({
                ...item,
                index: index + 1,
            }));
    }

    // Trường hợp chỉ có 1 shipment (logic cũ)
    if (!shipment?.items?.length) {
        return [];
    }

    const normalizedItems = shipment.items.map((item) => {
        const quantity = safeNumber(item.quantity_shipped ?? item.quantity, 0);
        const unitPrice = safeNumber(item.selling_price, 0);
        const subTotal = quantity * unitPrice;

        return {
            name:
                safeText(item.item_description) ||
                safeText(item.designation) ||
                safeText(item.material_code),
            unit: safeText(item.unit),
            quantity,
            unitPrice,
            subTotal,
            vat: safeNumber(item.vat, 0),
        };
    });

    return normalizedItems
        .filter((item) => item.quantity !== 0)
        .map((item, index) => ({
            ...item,
            index: index + 1,
        }));
};

// Cột mặc định cho hóa đơn GTGT
const DEFAULT_VAT_COLUMNS = [
    { field: 'index', header: 'STT', align: 'center', width: 12, type: 'number' },
    { field: 'name', header: 'Tên hàng hóa, dịch vụ', align: 'left', width: 70 },
    { field: 'unit', header: 'Đơn vị tính', align: 'center', width: 22 },
    { field: 'quantity', header: 'Số lượng', align: 'right', width: 22, type: 'number' },
    { field: 'unitPrice', header: 'Đơn giá', align: 'right', width: 28, type: 'currency' },
    { field: 'subTotal', header: 'Thành tiền', align: 'right', width: 32, type: 'currency' },
];

const createFormatCellValue = (currency) => (item, column) => {
    const value = item[column.field];

    if (column.type === 'number') {
        return formatNumber(safeNumber(value, 0), 0);
    }

    if (column.type === 'currency') {
        return formatPdfCurrencyValue(safeNumber(value, 0), currency, column.field);
    }

    return value ?? '';
};

const createVatInvoiceBlob = async (invoice, shipment, options = {}) => {
    await ensurePdfLibs();
    const doc = new jsPDF({ unit: 'mm', format: 'a4', compress: true });
    const baseFont = await registerRobotoFonts(doc);
    const pageWidth = doc.internal.pageSize.getWidth();
    const margin = 12;
    let cursorY = margin;

    // Lấy thông tin công ty đã lưu trong hệ thống
    const companyConfig = getCompanyConfig();

    // Hỗ trợ merge items từ nhiều shipments (trường hợp gộp hóa đơn)
    const shipments = options.shipments || (shipment ? [shipment] : []);
    const sourceCurrency = resolvePdfCurrency(options, invoice, shipment?.sale);
    const exchangeRate = resolvePdfExchangeRate(options, invoice, shipment?.sale);
    const currency = 'VND';
    const formatCellValue = createFormatCellValue(currency);
    const items = collectInvoiceItems(shipment, shipments).map((item) => ({
        ...item,
        unitPrice: convertAmountToVnd(item.unitPrice, sourceCurrency, exchangeRate),
        subTotal: convertAmountToVnd(item.subTotal, sourceCurrency, exchangeRate),
    }));

    const goodsTotal = items.reduce((sum, item) => sum + item.subTotal, 0);
    const vatRates = Array.from(new Set(items.map((item) => item.vat).filter((rate) => rate)));
    const vatRate = vatRates.length === 1 ? vatRates[0] : (vatRates.length === 0 ? 0 : 10);
    const vatTotal = items.reduce((sum, item) => sum + (item.subTotal * item.vat) / 100, 0);
    const grandTotal = goodsTotal + vatTotal;
    const documentNumber =
        shipment?.shipment_code || invoice?.invoice_number || `INV-${invoice?.id || 'preview'}`;

    const customer = invoice?.customer || shipment?.sale?.customer;
    // Hóa đơn GTGT: dùng địa chỉ thuế (tax_address), không dùng địa chỉ giao hàng
    const buyerTaxAddress =
        safeText(shipment?.sale?.tax_address) ||
        safeText(customer?.address) ||
        safeText(shipment?.sale?.delivery_address) ||
        '';

    const lineHeight = 6;

    const logoDataUrl = await loadImageAsDataUrl(companyConfig.logoUrl);

    // ==================== HEADER CÔNG TY (Logo trái - Thông tin phải) ====================
    const logoWidth = 35;
    const logoHeight = 25;
    const headerInfoX = margin + logoWidth + 8;
    const headerStartY = cursorY;

    // Vẽ logo hoặc placeholder
    if (logoDataUrl) {
        doc.addImage(
            logoDataUrl,
            getJsPdfImageFormatFromUrl(companyConfig.logoUrl, logoDataUrl),
            margin,
            cursorY,
            logoWidth,
            logoHeight
        );
    } else {
        doc.setDrawColor(200, 200, 200);
        doc.setLineWidth(0.3);
        doc.rect(margin, cursorY, logoWidth, logoHeight);
        doc.setFont(baseFont, 'normal');
        doc.setFontSize(8);
        doc.setTextColor(150, 150, 150);
        doc.text('LOGO', margin + logoWidth / 2, cursorY + logoHeight / 2 + 2, { align: 'center' });
        doc.setTextColor(0, 0, 0);
    }

    // Thông tin công ty nằm bên phải logo như file mẫu.
    let infoY = headerStartY;

    // Tên công ty
    doc.setFont(baseFont, 'bold');
    doc.setFontSize(13);
    doc.text(companyConfig.name || '', headerInfoX, infoY);
    infoY += 5;

    // Mã số thuế
    doc.setFont(baseFont, 'normal');
    doc.setFontSize(9);
    doc.text(`Mã số thuế: ${companyConfig.taxCode || ''}`, headerInfoX, infoY);
    infoY += 4;

    // Địa chỉ
    doc.text(`Địa chỉ: ${companyConfig.address || ''}`, headerInfoX, infoY);
    infoY += 4;

    // Điện thoại
    doc.text(`Điện thoại: ${companyConfig.hotline || companyConfig.tel || ''}`, headerInfoX, infoY);
    infoY += 4;

    // Website
    doc.text(`Website: ${companyConfig.website || ''}`, headerInfoX, infoY);
    infoY += 4;

    // Email
    const displayEmail = companyConfig.accountingEmail || companyConfig.email || '';
    doc.text(`Email: ${displayEmail}`, headerInfoX, infoY);
    infoY += 4;

    // Số tài khoản
    doc.text(`Số Tài Khoản: ${companyConfig.bankAccountVnd || ''} ${companyConfig.bankName || ''}`, headerInfoX, infoY);
    infoY += 6;

    // Cập nhật cursorY sau header
    cursorY = Math.max(headerStartY + logoHeight, infoY);

    // ==================== TIÊU ĐỀ HÓA ĐƠN ====================
    const titleStartY = cursorY;

    // Đường border trên (màu nhạt)
    doc.setDrawColor(180, 180, 180);
    doc.setLineWidth(0.3);
    doc.line(margin, cursorY, pageWidth - margin, cursorY);
    cursorY += 8;

    doc.setFont(baseFont, 'bold');
    doc.setFontSize(16);
    doc.text('HÓA ĐƠN GIÁ TRỊ GIA TĂNG', pageWidth / 2, cursorY, { align: 'center' });
    cursorY += 6;

    // Ký hiệu và Số (bên phải)
    doc.setFont(baseFont, 'normal');
    doc.setFontSize(9);
    const rightColX = pageWidth - margin - 40;
    doc.text('Ký hiệu:', rightColX, cursorY - 3);
    doc.text('Số: <Chưa cấp số>', rightColX, cursorY + 2);

    // Ngày tháng năm
    doc.setFont(baseFont, 'normal');
    doc.setFontSize(10);
    const dateLine = formatVietnameseDate(invoice?.invoice_date || shipment?.shipped_date || new Date());
    doc.text(dateLine, pageWidth / 2, cursorY, { align: 'center' });
    cursorY += 5;

    // Mã CQT
    doc.setFont(baseFont, 'normal');
    doc.setFontSize(9);
    doc.text('Mã CQT:', pageWidth / 2, cursorY, { align: 'center' });
    cursorY += 4;

    // Đường border dưới (màu nhạt)
    doc.setDrawColor(180, 180, 180);
    doc.setLineWidth(0.3);
    doc.line(margin, cursorY, pageWidth - margin, cursorY);

    cursorY += 5;

    // ==================== THÔNG TIN KHÁCH HÀNG ====================
    doc.setFontSize(10);

    doc.setFontSize(10);
    const maxTextWidth = pageWidth - margin * 2;

    const drawInfoRow = (label, value) => {
        const labelText = `${label}: `;
        const labelWidth = doc.getTextWidth(labelText);
        const valueStartX = margin + labelWidth;
        const maxValueWidth = maxTextWidth - labelWidth;

        doc.text(labelText, margin, cursorY);

        const textValue = safeText(value);
        if (textValue) {
            const lines = doc.splitTextToSize(textValue, maxValueWidth);
            lines.forEach((line, index) => {
                doc.text(line, valueStartX, cursorY);
                if (index < lines.length - 1) {
                    cursorY += lineHeight;
                }
            });
        }
        cursorY += lineHeight;
    };

    drawInfoRow('Họ tên người mua hàng', '');
    drawInfoRow('Tên đơn vị', customer?.name || '');
    drawInfoRow('Mã số thuế', customer?.tax_code || '');
    drawInfoRow('MSĐVCQHVNS', '');
    drawInfoRow('Căn cước công dân', '');
    drawInfoRow('Địa chỉ', buyerTaxAddress);

    // Hình thức thanh toán và Số tài khoản trên cùng một dòng
    doc.text('Hình thức thanh toán: TM/CK', margin, cursorY);
    doc.text('Số tài khoản:', margin + 80, cursorY);
    cursorY += lineHeight;

    doc.text('Đồng tiền thanh toán: VND', margin, cursorY);
    cursorY += 6;

    // ==================== BẢNG HÀNG HÓA ====================
    // Lấy columns từ options hoặc dùng mặc định
    const columns = options.columns && options.columns.length > 0 ? options.columns : DEFAULT_VAT_COLUMNS;

    // Thêm các dòng nội dung bổ sung (customLines) vào cuối bảng
    const customLines = Array.isArray(options.customLines) ? options.customLines.filter(line => line && line.trim()) : [];

    // Tạo body data: items + custom lines (dựa trên columns đã chọn)
    let tableBody = items.length
        ? items.map((item) => columns.map((col) => formatCellValue(item, col)))
        : [];

    // Thêm custom lines vào cuối bảng (tìm cột "name" để hiển thị)
    const nameColIndex = columns.findIndex((col) => col.field === 'name');
    const targetColIndex = nameColIndex >= 0 ? nameColIndex : 1;
    customLines.forEach((line) => {
        const emptyRow = columns.map(() => '');
        emptyRow[targetColIndex] = line;
        tableBody.push(emptyRow);
    });

    // Nếu không có gì thì thêm dòng trống
    if (tableBody.length === 0) {
        tableBody = [columns.map(() => '')];
    }

    autoTable(doc, {
        startY: cursorY,
        head: [columns.map((col) => col.header)],
        body: tableBody,
        theme: 'grid',
        tableWidth: pageWidth - margin * 2,
        styles: {
            font: baseFont,
            fontSize: 9,
            cellPadding: 1.5,
            lineColor: [0, 0, 0],
            lineWidth: 0.2,
            valign: 'middle',
        },
        headStyles: {
            fillColor: [255, 255, 255],
            textColor: [0, 0, 0],
            fontStyle: 'bold',
            halign: 'center',
        },
        bodyStyles: {
            fillColor: [255, 255, 255],
            textColor: [0, 0, 0],
        },
        columnStyles: (() => {
            // Tính tổng width gốc của các cột được chọn
            const totalOriginalWidth = columns.reduce((sum, col) => sum + (col.width || 20), 0);
            const availableWidth = pageWidth - margin * 2;
            const scaleFactor = availableWidth / totalOriginalWidth;
            
            return columns.reduce((acc, col, index) => {
                acc[index] = {
                    cellWidth: (col.width || 20) * scaleFactor,
                    halign: col.align || 'left',
                };
                return acc;
            }, {});
        })(),
        margin: { left: margin, right: margin },
    });

    cursorY = doc.lastAutoTable.finalY;

    // ==================== TỔNG KẾT ====================
    const amountInWords = numberToVietnameseWords(grandTotal);
    const vatRateDisplay = `${vatRate}%`;

    // Sử dụng cùng cấu trúc 6 cột như bảng hàng hóa: 12 + 70 + 22 + 22 + 28 + 32 = 186
    // Dòng thuế suất: cột 0-1-2, space-between
    // Các dòng khác: cột 3-4 (title căn trái), cột 5 (số tiền căn phải)
    autoTable(doc, {
        startY: cursorY,
        body: [
            [
                { content: '', styles: { halign: 'left' } },
                { content: '', styles: { halign: 'left' } },
                { content: '', styles: { halign: 'left' } },
                { content: 'Cộng tiền hàng:', colSpan: 2, styles: { halign: 'left' } },
                { content: formatPdfMoneyValue(goodsTotal, currency), styles: { halign: 'right', fontStyle: 'bold' } },
            ],
            [
                { content: 'Thuế suất GTGT:', colSpan: 2, styles: { halign: 'left' } },
                { content: vatRateDisplay, styles: { halign: 'center', fontStyle: 'bold' } },
                { content: 'Tiền thuế GTGT:', colSpan: 2, styles: { halign: 'left' } },
                { content: formatPdfMoneyValue(vatTotal, currency), styles: { halign: 'right', fontStyle: 'bold' } },
            ],
            [
                { content: '', styles: { halign: 'left' } },
                { content: '', styles: { halign: 'left' } },
                { content: '', styles: { halign: 'left' } },
                { content: 'Tổng tiền thanh toán:', colSpan: 2, styles: { halign: 'left' } },
                { content: formatPdfMoneyValue(grandTotal, currency), styles: { halign: 'right', fontStyle: 'bold' } },
            ],
            [
                { content: `Số tiền viết bằng chữ: ${amountInWords}`, colSpan: 5, styles: { halign: 'left' } },
            ],
        ],
        theme: 'grid',
        styles: {
            font: baseFont,
            fontSize: 9,
            cellPadding: 1.5,
            lineColor: [0, 0, 0],
            lineWidth: 0.2,
        },
        bodyStyles: {
            fillColor: [255, 255, 255],
            textColor: [0, 0, 0],
        },
        columnStyles: {
            0: { cellWidth: 12 },
            1: { cellWidth: 70 },
            2: { cellWidth: 22 },
            3: { cellWidth: 22 },
            4: { cellWidth: 28 },
            5: { cellWidth: 32 },
        },
        margin: { left: margin, right: margin },
        didParseCell: (data) => {
            // Ẩn viền dọc bên trong, chỉ giữ viền ngoài trái/phải và viền ngang
            const lineWidth = { top: 0.2, right: 0, bottom: 0.2, left: 0 };

            // Viền trái cho cột đầu tiên
            if (data.column.index === 0) {
                lineWidth.left = 0.2;
            }

            // Viền phải cho cột cuối cùng
            if (data.column.index === 5) {
                lineWidth.right = 0.2;
            }

            data.cell.styles.lineWidth = lineWidth;
        },
    });

    cursorY = doc.lastAutoTable.finalY + 10;

    // ==================== CHỮ KÝ ====================
    const signatureWidth = (pageWidth - margin * 2) / 2;

    doc.setFont(baseFont, 'bold');
    doc.setFontSize(10);
    doc.text('Người mua hàng', margin + signatureWidth / 2, cursorY, { align: 'center' });
    doc.text('Người bán hàng', margin + signatureWidth + signatureWidth / 2, cursorY, { align: 'center' });
    cursorY += 4;

    doc.setFont(baseFont, 'normal');
    doc.setFontSize(9);
    doc.text('(Ký, ghi rõ họ, tên)', margin + signatureWidth / 2, cursorY, { align: 'center' });
    doc.text('(Ký, ghi rõ họ, tên)', margin + signatureWidth + signatureWidth / 2, cursorY, { align: 'center' });

    const fileName = options.fileName || `Hoa_don_GTGT_${documentNumber}.pdf`;
    const blob = doc.output('blob');

    return { blob, fileName };
};

export function useVatInvoicePdf() {
    const generateVatInvoice = async (invoice, shipment, options = {}) => {
        ensureBrowserEnvironment();
        return createVatInvoiceBlob(invoice, shipment, options);
    };

    const previewVatInvoice = async (invoice, shipment, options = {}) => {
        const { blob, fileName } = await generateVatInvoice(invoice, shipment, options);
        openPdfPreview(blob, fileName);
        return blob;
    };

    const downloadVatInvoice = async (invoice, shipment, options = {}) => {
        const { blob, fileName } = await generateVatInvoice(invoice, shipment, options);
        saveAs(blob, fileName);
        return blob;
    };

    return {
        generateVatInvoice,
        previewVatInvoice,
        downloadVatInvoice,
    };
}
