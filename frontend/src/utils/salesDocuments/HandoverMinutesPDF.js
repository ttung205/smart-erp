import { loadPdfLibs } from './sampleSupport';
import FileSaver from 'file-saver';
const saveAs = FileSaver.saveAs || FileSaver;
import { formatNumber } from './sampleSupport';
import { registerRobotoFonts } from './sampleSupport';
import { getCompanyConfig } from './sampleSupport';
import { drawPdfCompanyLogoAndTextHeader } from './sampleSupport';

let jsPDF;
let autoTable;

const ensurePdfLibs = async () => {
    ({ jsPDF, autoTable } = await loadPdfLibs());
};

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

const safeNumber = (value) => {
    const number = Number(value);
    return Number.isFinite(number) ? number : 0;
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

const collectItems = (shipment) => {
    if (!shipment?.items?.length) {
        return [];
    }

    const normalizedItems = shipment.items.map((item) => ({
        name:
            safeText(item.item_description) ||
            safeText(item.designation) ||
            safeText(item.material_code),
        unit: safeText(item.unit),
        quantity: safeNumber(item.quantity_shipped ?? item.quantity),
        orderNumber:
            safeText(item.po_number) ||
            safeText(shipment?.sale?.po_number) ||
            safeText(item.sale_number) ||
            safeText(shipment?.sale?.sale_number),
        note: safeText(item.explanation),
    }));

    return normalizedItems
        .filter((item) => item.quantity !== 0)
        .map((item, index) => ({
            ...item,
            index: index + 1,
        }));
};

// Cột mặc định cho biên bản bàn giao
const DEFAULT_HANDOVER_COLUMNS = [
    { field: 'index', header: 'STT', align: 'center', width: 13, type: 'number' },
    { field: 'name', header: 'Tên hàng', align: 'left', width: 50 },
    { field: 'unit', header: 'Đơn vị tính', align: 'center', width: 22 },
    { field: 'quantity', header: 'Số lượng', align: 'center', width: 20, type: 'number' },
    { field: 'orderNumber', header: 'Số hợp đồng / Đơn hàng', align: 'center', width: 40 },
    { field: 'note', header: 'Ghi chú', align: 'center', width: 32 },
];

// Hai biến thể dùng chung bố cục: biên bản bàn giao đầy đủ thông tin các bên,
// xác nhận xuất kho lược bỏ nhân viên bán hàng và đại diện hai bên (chỉ thủ kho ký).
const DOCUMENT_VARIANTS = {
    handover: {
        title: 'BIÊN BẢN BÀN GIAO',
        tableLeadIn: 'Bên B đã bàn giao cho bên A:',
        signatureTitles: ['ĐẠI DIỆN BÊN NHẬN', 'ĐẠI DIỆN BÊN GIAO', 'THỦ KHO'],
        fileNamePrefix: 'Bien_ban_ban_giao',
        showParties: true,
    },
    'export-confirmation': {
        title: 'XÁC NHẬN XUẤT KHO',
        tableLeadIn: 'Danh sách hàng hóa xuất kho:',
        signatureTitles: ['NGƯỜI SOẠN HÀNG', 'THỦ KHO'],
        fileNamePrefix: 'Xac_nhan_xuat_kho',
        showParties: false,
    },
};

const formatCellValue = (item, column) => {
    const value = item[column.field];

    if (column.type === 'number') {
        return formatNumber(safeNumber(value), 0);
    }

    return value ?? '';
};

/**
 * Vẽ một biên bản bàn giao vào tài liệu jsPDF có sẵn.
 *
 * Phần header dùng logo neo theo mép trên của trang, nên hàm này luôn vẽ
 * từ đầu trang. Bên gọi phải tự tạo trang mới trước khi gọi.
 *
 * @returns {Promise<{cursorY: number, shipmentCode: string}>}
 */
const drawHandoverMinutes = async ({ doc, baseFont, invoice, shipment, options = {} }) => {
    // Bên gọi có thể đến từ module khác, nên tự đảm bảo autoTable đã sẵn sàng.
    await ensurePdfLibs();

    const pageWidth = doc.internal.pageSize.getWidth();
    const margin = 15;
    let cursorY = margin;

    // Lấy thông tin công ty đã lưu trong hệ thống
    const companyConfig = getCompanyConfig();
    const variant = DOCUMENT_VARIANTS[options.variant] || DOCUMENT_VARIANTS.handover;

    const shipmentCode =
        shipment?.shipment_code || invoice?.invoice_number || `INV-${invoice?.id || 'preview'}`;
    const documentNumber =
        shipment?.shipment_code || invoice?.invoice_number || `INV-${invoice?.id || 'preview'}`;
    const dateLine = formatVietnameseDate(
        invoice?.invoice_date || shipment?.shipped_date || new Date()
    );

    const headerBottomMm = await drawPdfCompanyLogoAndTextHeader(
        doc,
        baseFont,
        companyConfig,
        margin,
        margin,
        { pageWidthMm: pageWidth, allowDetailedHeader: true }
    );

    doc.setFont(baseFont, 'bold');
    doc.setFontSize(18);
    doc.text(variant.title, pageWidth / 2, headerBottomMm + 7, { align: 'center' });
    doc.setFont(baseFont, 'normal');
    const headerMetaY = headerBottomMm + 14;
    doc.setFontSize(10);
    doc.text(`Số: ${documentNumber}`, pageWidth / 2, headerMetaY, { align: 'center' });
    doc.text(dateLine || '', pageWidth - margin, headerMetaY, { align: 'right' });
    cursorY = headerBottomMm + 22;

    const customer = invoice?.customer || shipment?.sale?.customer;
    const customerName = safeText(invoice?.customer?.name || shipment?.sale?.customer?.name);
    const taxAddress = safeText(
        shipment?.sale?.tax_address || customer?.address || shipment?.sale?.delivery_address
    );
    const deliveryAddress = safeText(
        shipment?.sale?.delivery_address || customer?.address || shipment?.sale?.tax_address
    );
    const salerName = safeText(shipment?.sale?.saler?.full_name || shipment?.sale?.saler?.name);

    const dottedName =
        '..................................................................................';
    const dottedRole = '..............................................................';
    const dottedAddress =
        '............................................................................................................................................................................';

    const lineHeight = 5; // Chiều cao mỗi dòng

    const drawLabelRow = (label, value, dottedFallback = dottedAddress) => {
        const labelText = `${label}:`;
        const labelWidth = doc.getTextWidth(`${labelText} `);
        doc.setFont(baseFont, 'bold');
        doc.text(labelText, margin, cursorY);
        doc.setFont(baseFont, 'normal');

        const textValue = value || dottedFallback;
        const valueStartX = margin + labelWidth + 2;
        const maxValueWidth = pageWidth - valueStartX - margin;

        // Chia text thành nhiều dòng nếu quá dài
        const lines = doc.splitTextToSize(textValue, maxValueWidth);

        if (lines.length === 1) {
            doc.text(textValue, valueStartX, cursorY);
            cursorY += 6;
        } else {
            // Vẽ từng dòng
            lines.forEach((line, index) => {
                if (index === 0) {
                    doc.text(line, valueStartX, cursorY);
                } else {
                    // Các dòng tiếp theo căn lề với dòng đầu
                    doc.text(line, valueStartX, cursorY);
                }
                cursorY += lineHeight;
            });
            cursorY += 1; // Thêm khoảng cách sau khi kết thúc nhiều dòng
        }
    };

    const drawNameRoleRow = () => {
        const nameLabel = 'Ông (Bà):';
        const roleLabel = 'Chức vụ:';
        const nameWidth = doc.getTextWidth(`${nameLabel} `);
        doc.setFont(baseFont, 'bold');
        doc.text(nameLabel, margin, cursorY);
        doc.setFont(baseFont, 'normal');
        doc.text(dottedName, margin + nameWidth + 2, cursorY);

        const roleStart = margin + nameWidth + 2 + doc.getTextWidth(dottedName) + 6;
        const roleWidth = doc.getTextWidth(`${roleLabel} `);
        doc.setFont(baseFont, 'bold');
        doc.text(roleLabel, roleStart, cursorY);
        doc.setFont(baseFont, 'normal');
        doc.text(dottedRole, roleStart + roleWidth + 2, cursorY);
        cursorY += 6;
    };

    doc.setFontSize(10);

    if (variant.showParties) {
        const introRows = [
            { label: 'Đại diện bên nhận (Bên A)', value: customerName },
            { label: 'Địa chỉ', value: taxAddress },
        ];

        introRows.forEach((row) => drawLabelRow(row.label, row.value));
        drawNameRoleRow();

        const secondPartyRows = [{ label: 'Đại diện bên giao (Bên B)', value: companyConfig.name }];
        secondPartyRows.forEach((row) => drawLabelRow(row.label, row.value));
        drawNameRoleRow();

        // Sales rep and phone in one line similar to sample
        const salesLabel = 'Nhân viên bán hàng:';
        const salesWidth = doc.getTextWidth(`${salesLabel} `);
        doc.setFont(baseFont, 'bold');
        doc.text(salesLabel, margin, cursorY);
        doc.setFont(baseFont, 'normal');
        doc.text(salerName || dottedName, margin + salesWidth + 2, cursorY);

        cursorY += 8;
    } else {
        // Xác nhận xuất kho: giữ khách hàng và địa chỉ để thủ kho đối chiếu đơn,
        // bỏ nhân viên bán hàng và đại diện hai bên theo yêu cầu nghiệp vụ.
        drawLabelRow('Khách hàng', customerName, dottedName);
        drawLabelRow('Địa chỉ', taxAddress);
        cursorY += 2;
    }

    doc.setFont(baseFont, 'normal');
    doc.text(variant.tableLeadIn, margin, cursorY);
    cursorY += 4;

    const items = collectItems(shipment);

    // Lấy columns từ options hoặc dùng mặc định
    const columns = options.columns && options.columns.length > 0 ? options.columns : DEFAULT_HANDOVER_COLUMNS;

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

    // Dòng tổng số lượng, chỉ hiện khi bảng có hàng thật và có cột số lượng
    const quantityColIndex = columns.findIndex((col) => col.field === 'quantity');
    const totalQuantity = items.reduce((sum, item) => sum + safeNumber(item.quantity), 0);
    const tableFoot =
        items.length > 0 && quantityColIndex >= 0
            ? [
                columns.map((col, index) => {
                    if (index === quantityColIndex) {
                        return formatNumber(totalQuantity, 0);
                    }

                    return index === targetColIndex ? 'Tổng cộng' : '';
                }),
            ]
            : undefined;

    autoTable(doc, {
        startY: cursorY,
        head: [columns.map((col) => col.header)],
        body: tableBody,
        foot: tableFoot,
        showFoot: 'lastPage',
        theme: 'grid',
        tableWidth: pageWidth - margin * 2,
        styles: {
            font: baseFont,
            fontSize: 9,
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
            fillColor: [255, 255, 255],
            textColor: [0, 0, 0],
            fontStyle: 'bold',
            lineColor: [0, 0, 0],
            lineWidth: 0.2,
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
        // columnStyles chỉ áp cho phần body, nên căn lề head/foot ở đây.
        didParseCell: (data) => {
            if (data.section === 'body') {
                return;
            }

            const column = columns[data.column.index];

            if (column) {
                data.cell.styles.halign = column.align || 'left';
            }
        },
    });

    const pageHeight = doc.internal.pageSize.getHeight();

    // Check if table extended to new page, if so update cursorY properly
    cursorY = doc.lastAutoTable.finalY + 8;

    // Ghi chú - wrap text nếu quá dài
    const noteContent = shipment?.notes || invoice?.notes || '';
    const noteLabel = 'Ghi chú:';
    const noteLabelWidth = doc.getTextWidth(`${noteLabel} `);
    const noteTextWidth = pageWidth - margin * 2 - noteLabelWidth - 5;
    
    doc.setFont(baseFont, 'normal');
    
    if (noteContent && noteContent.trim()) {
        const noteLines = doc.splitTextToSize(noteContent, noteTextWidth);
        
        // Check if we need a new page for the first line of note
        if (cursorY + 10 > pageHeight - margin) {
            doc.addPage();
            cursorY = margin;
        }

        doc.text(noteLabel, margin, cursorY);
        
        noteLines.forEach((line, index) => {
            if (index === 0) {
                doc.text(line, margin + noteLabelWidth + 2, cursorY);
            } else {
                cursorY += 5;
                // Check again after increment
                if (cursorY > pageHeight - margin) {
                    doc.addPage();
                    cursorY = margin;
                }
                doc.text(line, margin + noteLabelWidth + 2, cursorY);
            }
        });
        cursorY += 15;
    } else {
        // Short note replacement
        if (cursorY + 10 > pageHeight - margin) {
            doc.addPage();
            cursorY = margin;
        }
        const dottedNote = ' .............................................................................................................................................................................';
        doc.text(`${noteLabel} ${dottedNote}`, margin, cursorY);
        cursorY += 20;
    }

    const deliveryAddressLabel = 'Địa chỉ giao hàng:';
    const deliveryAddressLabelWidth = doc.getTextWidth(`${deliveryAddressLabel} `);
    const deliveryAddressTextWidth = pageWidth - margin * 2 - deliveryAddressLabelWidth - 5;
    const deliveryAddressLines = deliveryAddress
        ? doc.splitTextToSize(deliveryAddress, deliveryAddressTextWidth)
        : ['.............................................................................................................................................................................'];

    if (cursorY + deliveryAddressLines.length * 5 + 5 > pageHeight - margin) {
        doc.addPage();
        cursorY = margin;
    }

    doc.setFont(baseFont, 'bold');
    doc.text(deliveryAddressLabel, margin, cursorY);
    doc.setFont(baseFont, 'normal');

    deliveryAddressLines.forEach((line, index) => {
        const lineY = cursorY + index * 5;
        doc.text(line, margin + deliveryAddressLabelWidth + 2, lineY);
    });
    cursorY += deliveryAddressLines.length * 5 + 10;

    // Signatures section
    // Required height for signatures ~ 40mm
    if (cursorY + 40 > pageHeight - margin) {
        doc.addPage();
        cursorY = margin;
    }

    const signatureTitles = variant.signatureTitles;
    const signatureWidth = (pageWidth - margin * 2) / signatureTitles.length;

    signatureTitles.forEach((title, index) => {
        const x = margin + index * signatureWidth;
        doc.setFont(baseFont, 'bold');
        doc.text(title, x + signatureWidth / 2, cursorY, { align: 'center' });
        doc.setFont(baseFont, 'normal');
        doc.setFontSize(9);
        doc.text('(Ký, họ tên)', x + signatureWidth / 2, cursorY + 5, { align: 'center' });
        doc.setFontSize(10);
    });

    return { cursorY, shipmentCode };
};

const createHandoverMinutesBlob = async (invoice, shipment, options = {}) => {
    await ensurePdfLibs();
    const doc = new jsPDF({ unit: 'mm', format: 'a4', compress: true });
    const baseFont = await registerRobotoFonts(doc);

    const { shipmentCode } = await drawHandoverMinutes({
        doc,
        baseFont,
        invoice,
        shipment,
        options,
    });

    const variant = DOCUMENT_VARIANTS[options.variant] || DOCUMENT_VARIANTS.handover;
    const fileName = options.fileName || `${variant.fileNamePrefix}_${shipmentCode}.pdf`;

    return { blob: doc.output('blob'), fileName };
};

export { DEFAULT_HANDOVER_COLUMNS, drawHandoverMinutes };

export function useHandoverMinutesPdf() {
    const generateHandoverMinutes = async (invoice, shipment, options = {}) => {
        ensureBrowserEnvironment();
        return createHandoverMinutesBlob(invoice, shipment, options);
    };

    const previewHandoverMinutes = async (invoice, shipment, options = {}) => {
        const { blob, fileName } = await generateHandoverMinutes(invoice, shipment, options);
        openPdfPreview(blob, fileName);
        return blob;
    };

    const downloadHandoverMinutes = async (invoice, shipment, options = {}) => {
        const { blob, fileName } = await generateHandoverMinutes(invoice, shipment, options);
        saveAs(blob, fileName);
        return blob;
    };

    return {
        generateHandoverMinutes,
        previewHandoverMinutes,
        downloadHandoverMinutes,
    };
}
