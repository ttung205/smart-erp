import { loadPdfLibs } from '@/utils/lazyExportLibs';
import { saveAs } from 'file-saver';
import { formatNumber } from '@/utils';
import {
    formatPdfCurrencyValue,
    formatPdfMoneyValue,
    resolvePdfCurrency,
} from '@/utils/pdfCurrencyFormat';
import { registerRobotoFonts } from '@/utils/pdfFonts';
import { getCompanyConfig } from '@/config/companyConfig';
import { createDocumentQrDataUrl, drawPdfTopRightQrBlock } from '@/utils/documentQr';
import { drawPdfCompanyLogoAndTextHeader } from '@/utils/pdfImages';

let jsPDF;
let autoTable;

const ensurePdfLibs = async () => {
    ({ jsPDF, autoTable } = await loadPdfLibs());
};

// Các tài khoản kế toán mặc định
const DEFAULT_ACCOUNTS = {
    debitAccount: '1311',
    creditAccount: '5111',
};

const VIETNAMESE_DIGITS = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
const VIETNAMESE_SCALE = ['', 'nghìn', 'triệu', 'tỷ', 'nghìn tỷ', 'triệu tỷ'];

const ensureBrowserEnvironment = () => {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        throw new Error('Chức năng xuất PDF chỉ khả dụng trên trình duyệt.');
    }
};

const safeText = (value, fallback = '') => (value && String(value).trim()) || fallback;

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
    return sentence.charAt(0).toUpperCase() + sentence.slice(1) + ' đồng chẵn';
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

const collectShipmentItems = (shipment) => {
    if (!shipment?.items?.length) {
        return [];
    }

    const normalizedItems = shipment.items.map((item) => {
        const quantity = safeNumber(item.quantity_shipped ?? item.quantity, 0);
        const unitPrice = safeNumber(item.selling_price, 0);
        const subTotal = quantity * unitPrice;
        const itemDescription =
            safeText(item.item_description) ||
            safeText(item.designation) ||
            safeText(item.material_code);
        const explanation = safeText(item.explanation);

        return {
            code: item.internal_code || item.material_code || '',
            name: itemDescription,
            note: explanation,
            unit: item.unit || '',
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

// Cột mặc định cho phiếu xuất kho
const DEFAULT_WAREHOUSE_COLUMNS = [
    { field: 'index', header: 'STT', align: 'center', width: 12, type: 'number' },
    { field: 'code', header: 'Mã hàng', align: 'left', width: 28 },
    { field: 'name', header: 'Tên hàng', align: 'left', width: 35 },
    { field: 'note', header: 'Ghi chú', align: 'left', width: 30 },
    { field: 'unit', header: 'ĐVT', align: 'left', width: 13 },
    { field: 'quantity', header: 'Số lượng', align: 'right', width: 16, type: 'number' },
    { field: 'unitPrice', header: 'Đơn giá', align: 'right', width: 22, type: 'currency' },
    { field: 'subTotal', header: 'Thành tiền', align: 'right', width: 25, type: 'currency' },
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

const createWarehouseNoteBlob = async (invoice, shipment, options = {}) => {
    await ensurePdfLibs();
    const doc = new jsPDF({ unit: 'mm', format: 'a4', compress: true });
    const baseFont = await registerRobotoFonts(doc);
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const margin = 14;
    let cursorY = margin;

    // Lấy company config từ options hoặc fallback về AC
    const companyConfig = getCompanyConfig(options.companySlug || 'AC');

    const currency = resolvePdfCurrency(options, invoice, shipment?.sale);
    const formatCellValue = createFormatCellValue(currency);

    const items = collectShipmentItems(shipment);
    const goodsTotal = items.reduce((sum, item) => sum + item.subTotal, 0);
    const totalQuantity = items.reduce((sum, item) => sum + item.quantity, 0);
    const vatTotal = items.reduce((sum, item) => sum + (item.subTotal * item.vat) / 100, 0);
    const vatRates = Array.from(new Set(items.map((item) => item.vat).filter((rate) => rate)));
    const vatRateDisplay =
        vatRates.length === 1 ? `${vatRates[0]}%` : vatRates.length === 0 ? '0%' : 'Theo bảng';
    const grandTotal = goodsTotal + vatTotal;

    const shipmentCode =
        shipment?.shipment_code || invoice?.invoice_number || `INV-${invoice?.id || 'preview'}`;
    const documentNumber =
        shipment?.shipment_code || invoice?.invoice_number || `INV-${invoice?.id || 'preview'}`;
    const requestOrdering = String(options.requestOrdering || '').trim();
    const dateLine = formatVietnameseDate(
        invoice?.invoice_date || shipment?.shipped_date || new Date()
    );
    const qrPayload = String(options.qrPayload || '').trim() || requestOrdering || documentNumber;
    const qrDataUrl = await createDocumentQrDataUrl(qrPayload);

    const headerBottomMm = await drawPdfCompanyLogoAndTextHeader(
        doc,
        baseFont,
        companyConfig,
        margin,
        margin,
        { pageWidthMm: pageWidth, headerRightReservedMm: 30, allowDetailedHeader: true }
    );

    doc.setFont(baseFont, 'bold');
    doc.setFontSize(16);
    doc.text('PHIẾU XUẤT KHO BÁN HÀNG', pageWidth / 2, headerBottomMm + 7, { align: 'center' });
    doc.setFont(baseFont, 'normal');
    const headerMetaY = headerBottomMm + 14;
    doc.setFontSize(10);
    doc.text(`Số: ${documentNumber}`, pageWidth / 2, headerMetaY, { align: 'center' });
    doc.text(dateLine || '', pageWidth - margin, headerMetaY, { align: 'right' });
    drawPdfTopRightQrBlock({
        doc,
        qrDataUrl,
        topLabel: '',
        value: requestOrdering,
        x: pageWidth - margin - 18,
        y: margin - 4,
        qrSize: 18,
        textWidth: 24,
        baseFont,
        labelFontSize: 8,
        valueFontSize: 8,
        lineHeight: 3.4,
        textPosition: 'below',
    });
    cursorY = headerBottomMm + 22;

    const accountInfoX = pageWidth / 2 + 50;
    const accountSegments = [
        { label: 'Nợ', value: options.debitAccount || DEFAULT_ACCOUNTS.debitAccount },
        { label: 'Có', value: options.creditAccount || DEFAULT_ACCOUNTS.creditAccount },
        { label: 'Loại tiền', value: currency },
    ];

    const customer = invoice?.customer || shipment?.sale?.customer;
    const taxAddress = safeText(
        shipment?.sale?.tax_address || customer?.address || shipment?.sale?.delivery_address
    );
    const deliveryAddress = safeText(
        shipment?.sale?.delivery_address || customer?.address || shipment?.sale?.tax_address
    );
    const detailRows = [
        {
            label: 'Người mua',
            value: safeText(shipment?.sale?.buyer),
            dotted: true,
            accountSegmentIndex: 0,
        },
        { label: 'Tên khách hàng', value: safeText(customer?.name), accountSegmentIndex: 1 },
        {
            label: 'Địa chỉ',
            value: taxAddress,
            accountSegmentIndex: 2,
        },
        {
            label: 'Điện thoại',
            value: safeText(customer?.phone || shipment?.sale?.customer?.phone),
        },
        { label: 'Mã số thuế', value: safeText(customer?.tax_code) },
        {
            label: 'Nhân viên bán hàng',
            value: safeText(shipment?.sale?.saler?.full_name || shipment?.sale?.saler?.name),
        },
    ];

    doc.setFontSize(10);
    const dottedLine = '............................................................';
    const baseValueX = margin + 35;
    const lineHeight = 6;
    const accountBaseY = cursorY;
    const hasFooterNote = options.footerNote && options.footerNote.trim();

    // Tính noteBoxX động: đo chiều rộng thực tế của 3 dòng không có accountSegmentIndex
    let noteBoxX = pageWidth / 2 - 25; // giá trị mặc định
    if (hasFooterNote) {
        const bottomRows = detailRows.filter((row) => typeof row.accountSegmentIndex !== 'number');
        doc.setFont(baseFont, 'normal');
        doc.setFontSize(10);
        let maxTextEndX = 0;
        bottomRows.forEach((row) => {
            const rowValue = row.value || '';
            if (rowValue) {
                const textWidth = doc.getTextWidth(rowValue);
                maxTextEndX = Math.max(maxTextEndX, baseValueX + textWidth);
            }
        });
        if (maxTextEndX > 0) {
            noteBoxX = Math.max(noteBoxX, maxTextEndX + 4);
        }
        // Không cho noteBoxX vượt quá giới hạn (ít nhất 40mm cho ô ghi chú)
        noteBoxX = Math.min(noteBoxX, pageWidth - margin - 40);
    }

    detailRows.forEach((row) => {
        const rowStartY = cursorY;
        doc.setFont(baseFont, 'bold');
        doc.text(`${row.label}:`, margin, rowStartY);

        const rowValue = row.value || (row.dotted ? dottedLine : '');
        let maxWidth;
        if (typeof row.accountSegmentIndex === 'number') {
            maxWidth = Math.max(40, accountInfoX - baseValueX - 8);
        } else {
            maxWidth = pageWidth - margin - baseValueX;
        }
        const valueLines = row.dotted
            ? [rowValue]
            : doc.splitTextToSize(rowValue, maxWidth) || [''];

        doc.setFont(baseFont, 'normal');
        doc.text(valueLines[0] || '', baseValueX, rowStartY);

        if (typeof row.accountSegmentIndex === 'number') {
            const segment = accountSegments[row.accountSegmentIndex];
            if (segment) {
                doc.setFont(baseFont, 'bold');
                const labelText = `${segment.label}:`;
                const accountY = accountBaseY + row.accountSegmentIndex * lineHeight;
                doc.text(labelText, accountInfoX, accountY);
                const labelWidth = doc.getTextWidth(`${labelText} `);
                doc.setFont(baseFont, 'normal');
                doc.text(segment.value || dottedLine, accountInfoX + labelWidth, accountY);
            }
        }

        let currentY = rowStartY;
        for (let i = 1; i < valueLines.length; i += 1) {
            currentY += lineHeight;
            doc.setFont(baseFont, 'normal');
            doc.text(valueLines[i], baseValueX, currentY);
        }

        cursorY = currentY + lineHeight;
    });

    cursorY += 2;

    // Render footer note ở bên phải, song song với Điện thoại/MST/NV bán hàng
    if (hasFooterNote) {
        const noteBoxStartY = accountBaseY + 3 * lineHeight;
        const noteBoxWidth = pageWidth - margin - noteBoxX;

        doc.setFont(baseFont, 'bold');
        doc.setFontSize(9);
        doc.text('Ghi chú:', noteBoxX + 2, noteBoxStartY + 4);

        doc.setFont(baseFont, 'normal');
        doc.setFontSize(9);
        const footerNoteTextWidth = noteBoxWidth - 4;
        const footerNoteLines = doc.splitTextToSize(options.footerNote, footerNoteTextWidth);
        doc.text(footerNoteLines, noteBoxX + 2, noteBoxStartY + 9);

        const noteLineHeight = doc.getTextDimensions('A').h * 1.15;
        const noteBoxHeight = 9 + footerNoteLines.length * noteLineHeight + 2;
        doc.setDrawColor(0);
        doc.setLineWidth(0.3);
        doc.rect(noteBoxX, noteBoxStartY, noteBoxWidth, noteBoxHeight);
        doc.setFontSize(10);

        // Đẩy cursorY xuống nếu ô ghi chú dài hơn phần detail rows
        const noteBoxEndY = noteBoxStartY + noteBoxHeight + 2;
        if (noteBoxEndY > cursorY) {
            cursorY = noteBoxEndY;
        }
    }

    // Lấy columns từ options hoặc dùng mặc định
    const columns = options.columns && options.columns.length > 0 ? options.columns : DEFAULT_WAREHOUSE_COLUMNS;

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
        const emptyRow = columns.map(() => '');
        emptyRow[targetColIndex] = 'Không có dữ liệu';
        tableBody = [emptyRow];
    }

    // Tính toán footer dựa trên columns có chọn quantity và subTotal không
    const hasQuantityCol = columns.some((col) => col.field === 'quantity');
    const hasSubTotalCol = columns.some((col) => col.field === 'subTotal');
    
    // Tìm vị trí các cột trong columns đã chọn
    const quantityColIndex = columns.findIndex((col) => col.field === 'quantity');
    // Tạo footer row nếu có items
    let footerRow = [];
    if (items.length && (hasQuantityCol || hasSubTotalCol)) {
        footerRow = columns.map((col, idx) => {
            if (idx === 0) {
                return { content: 'Cộng', colSpan: quantityColIndex > 0 ? quantityColIndex : 1, styles: { fontStyle: 'bold' } };
            }
            if (idx > 0 && idx < quantityColIndex) {
                return null; // sẽ bị merge bởi colSpan
            }
            if (col.field === 'quantity') {
                return { content: formatNumber(totalQuantity, 0), styles: { halign: 'right', fontStyle: 'bold' } };
            }
            if (col.field === 'subTotal') {
                return {
                    content: formatPdfMoneyValue(goodsTotal, currency),
                    styles: { halign: 'right', fontStyle: 'bold' },
                };
            }
            return { content: '', styles: { lineColor: [0, 0, 0] } };
        }).filter(cell => cell !== null);
    }

    autoTable(doc, {
        startY: cursorY,
        head: [columns.map((col) => col.header)],
        body: tableBody,
        foot: footerRow.length > 0 ? [footerRow] : [],
        theme: 'grid',
        tableWidth: pageWidth - margin * 2,
        styles: {
            font: baseFont,
            fontSize: 8,
            cellPadding: 1.5,
            lineColor: [0, 0, 0],
            lineWidth: 0.2,
        },
        headStyles: {
            fillColor: [255, 255, 255],
            textColor: [0, 0, 0],
            fontStyle: 'bold',
        },
        bodyStyles: {
            fillColor: [255, 255, 255],
            textColor: [0, 0, 0],
        },
        footStyles: {
            fontStyle: 'bold',
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
    });

    cursorY = doc.lastAutoTable.finalY;

    // Helper function để kiểm tra và thêm trang mới nếu cần
    const checkPageOverflow = (requiredHeight) => {
        if (cursorY + requiredHeight > pageHeight - margin) {
            doc.addPage();
            cursorY = margin;
            return true;
        }
        return false;
    };

    // Kiểm tra có đủ chỗ cho summary section (~50mm)
    checkPageOverflow(50);

    doc.setLineWidth(0.5);
    doc.line(margin, cursorY, pageWidth - margin, cursorY);
    doc.setLineWidth(0.2);

    const summaryRows = [
        ['Cộng tiền hàng:', '', '', formatPdfMoneyValue(goodsTotal, currency)],
        ['Thuế suất GTGT:', vatRateDisplay, 'Tiền thuế GTGT:', formatPdfMoneyValue(vatTotal, currency)],
        ['Tổng tiền thanh toán:', '', '', formatPdfMoneyValue(grandTotal, currency)],
    ];

    autoTable(doc, {
        startY: cursorY,
        body: summaryRows,
        theme: 'grid',
        styles: {
            font: baseFont,
            fontSize: 8,
            cellPadding: 1.5,
            lineColor: [0, 0, 0],
            lineWidth: 0.2,
        },
        bodyStyles: {
            fillColor: [255, 255, 255],
            textColor: [0, 0, 0],
            fontStyle: 'bold',
        },
        columnStyles: {
            0: { halign: 'left', cellWidth: 70 },
            1: { halign: 'center', cellWidth: 20 },
            2: { halign: 'left', cellWidth: 60 },
            3: { halign: 'right', cellWidth: 32 },
        },
        tableWidth: pageWidth - margin * 2,
        margin: { left: margin, right: margin },
        didParseCell: (data) => {
            const lineWidth = { top: 0.2, right: 0, bottom: 0.2, left: 0 };
            const lineColor = {
                top: [0, 0, 0],
                right: [255, 255, 255],
                bottom: [0, 0, 0],
                left: [255, 255, 255],
            };

            if (data.column.index === 0) {
                lineWidth.left = 0.2;
                lineColor.left = [0, 0, 0];
            }

            if (data.column.index === summaryRows[0].length - 1) {
                lineWidth.right = 0.2;
                lineColor.right = [0, 0, 0];
            }

            data.cell.styles.lineWidth = lineWidth;
            data.cell.styles.lineColor = lineColor;

            if (data.row.index === 1 && data.column.index === 1) {
                data.cell.styles.fontStyle = 'bold';
            }
            if (data.row.index === 2) {
                data.cell.styles.fontStyle = 'bold';
            }
        },
    });

    cursorY = doc.lastAutoTable.finalY + 8;

    // Kiểm tra có đủ chỗ cho phần text và chữ ký (~60mm)
    checkPageOverflow(60);

    doc.setFont(baseFont, 'normal');
    doc.text(`Số tiền viết bằng chữ: ${numberToVietnameseWords(grandTotal)}`, margin, cursorY);
    cursorY += 8;

    // Số chứng từ gốc kèm theo - tự điền số PO hoặc số đơn hàng
    const poNumber = shipment?.sale?.po_number || shipment?.sale?.sale_number || '';
    const originalDocText = poNumber 
        ? `Số chứng từ gốc kèm theo: ${poNumber}`
        : 'Số chứng từ gốc kèm theo: ......................................................................................................................................................';
    doc.text(originalDocText, margin, cursorY);
    cursorY += 8;

    // Ghi chú - wrap text nếu quá dài (tương tự biên bản bàn giao)
    const noteContent = shipment?.notes || invoice?.notes || '';
    const noteLabel = 'Diễn giải chung:';
    const noteLabelWidth = doc.getTextWidth(`${noteLabel} `);
    const noteTextWidth = pageWidth - margin * 2 - noteLabelWidth - 5;
    
    if (noteContent && noteContent.trim()) {
        const noteLines = doc.splitTextToSize(noteContent, noteTextWidth);
        
        doc.text(noteLabel, margin, cursorY);
        noteLines.forEach((line, index) => {
            if (index === 0) {
                doc.text(line, margin + noteLabelWidth + 2, cursorY);
            } else {
                cursorY += 5;
                doc.text(line, margin + noteLabelWidth + 2, cursorY);
            }
        });
        cursorY += 12;
    } else {
        const dottedNote = ' ......................................................................................................................................................';
        doc.text(`${noteLabel}${dottedNote}`, margin, cursorY);
        cursorY += 12;
    }

    const deliveryAddressLabel = 'Địa chỉ giao hàng:';
    const deliveryAddressLabelWidth = doc.getTextWidth(`${deliveryAddressLabel} `);
    const deliveryAddressTextWidth = pageWidth - margin * 2 - deliveryAddressLabelWidth - 5;
    const deliveryAddressLines = deliveryAddress
        ? doc.splitTextToSize(deliveryAddress, deliveryAddressTextWidth)
        : ['......................................................................................................................................................'];

    checkPageOverflow(deliveryAddressLines.length * 5 + 8);

    doc.setFont(baseFont, 'bold');
    doc.text(deliveryAddressLabel, margin, cursorY);
    doc.setFont(baseFont, 'normal');

    deliveryAddressLines.forEach((line, index) => {
        const lineY = cursorY + index * 5;
        doc.text(line, margin + deliveryAddressLabelWidth + 2, lineY);
    });
    cursorY += deliveryAddressLines.length * 5 + 10;

    // Kiểm tra có đủ chỗ cho phần chữ ký (~40mm)
    checkPageOverflow(40);

    const signatureWidth = (pageWidth - margin * 2) / 5;
    const signatureTitles = [
        'THỦ KHO',
        'KẾ TOÁN KHO',
        'TP LOGISTIC',
        'NV ĐÓNG HÀNG',
        'ĐẠI DIỆN BÊN NHẬN',
    ];

    signatureTitles.forEach((title, index) => {
        const x = margin + index * signatureWidth;
        doc.setFont(baseFont, 'bold');
        doc.text(title, x + signatureWidth / 2, cursorY, { align: 'center' });
        doc.setFont(baseFont, 'normal');
        doc.setFontSize(9);
        doc.text('(Ký, họ tên)', x + signatureWidth / 2, cursorY + 5, { align: 'center' });
        doc.setFontSize(10);
    });

    const fileName = options.fileName || `Phieu_xuat_kho_${shipmentCode}.pdf`;
    const blob = doc.output('blob');

    return { blob, fileName };
};

export function useWarehouseDeliveryNotePdf() {
    const generateWarehouseNote = async (invoice, shipment, options = {}) => {
        ensureBrowserEnvironment();
        return createWarehouseNoteBlob(invoice, shipment, options);
    };

    const previewWarehouseNote = async (invoice, shipment, options = {}) => {
        const { blob, fileName } = await generateWarehouseNote(invoice, shipment, options);
        openPdfPreview(blob, fileName);
        return blob;
    };

    const downloadWarehouseNote = async (invoice, shipment, options = {}) => {
        const { blob, fileName } = await generateWarehouseNote(invoice, shipment, options);
        saveAs(blob, fileName);
        return blob;
    };

    return {
        generateWarehouseNote,
        previewWarehouseNote,
        downloadWarehouseNote,
    };
}
