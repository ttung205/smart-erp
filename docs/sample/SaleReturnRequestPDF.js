import { loadPdfLibs } from '@/utils/lazyExportLibs';
import { formatNumber } from '@/utils';
import { getCompanyConfig } from '@/config/companyConfig';
import { createDocumentQrDataUrl, drawPdfTopRightQrBlock } from '@/utils/documentQr';
import { drawPdfCompanyLogoAndTextHeader } from '@/utils/pdfImages';
import { registerRobotoFonts } from '@/utils/pdfFonts';

let jsPDF;
let autoTable;

const ensurePdfLibs = async () => {
    ({ jsPDF, autoTable } = await loadPdfLibs());
};

const MAIN_WAREHOUSE_ID = 1;
const TECH_WAREHOUSE_ID = 2;

const safeText = (value, fallback = '') => (value && String(value).trim()) || fallback;

const safeNumber = (value, fallback = 0) => {
    const number = Number(value);
    return Number.isFinite(number) ? number : fallback;
};

const getUserName = (user) => safeText(user?.full_name || user?.name);

const getImageFormatFromDataUrl = (dataUrl) => {
    if (typeof dataUrl !== 'string') {
        return 'PNG';
    }

    if (dataUrl.startsWith('data:image/jpeg') || dataUrl.startsWith('data:image/jpg')) {
        return 'JPEG';
    }

    return 'PNG';
};

const loadImageData = async (url) => {
    const response = await fetch(url, { cache: 'no-store' });

    if (!response.ok) {
        throw new Error(`Không thể tải ảnh: ${response.status}`);
    }

    const blob = await response.blob();
    const dataUrl = await new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onloadend = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(blob);
    });

    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => {
            resolve({
                dataUrl,
                width: image.width,
                height: image.height,
            });
        };
        image.onerror = reject;
        image.src = dataUrl;
    });
};

const getWarehouseName = (warehouseNames, warehouseId) => {
    return safeText(
        warehouseNames?.[warehouseId] || warehouseNames?.[String(warehouseId)],
        `Kho ${warehouseId}`
    );
};

const formatVietnameseDate = (dateValue) => {
    if (!dateValue) {
        return '';
    }

    const date = new Date(dateValue);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return `Ngày ${date.getDate()} tháng ${date.getMonth() + 1} năm ${date.getFullYear()}`;
};

const getConditionLabel = (condition) => {
    const map = {
        good: 'Đủ điều kiện',
        damaged: 'Không đủ điều kiện',
        need_inspection: 'Cần kiểm tra thêm',
    };

    return map[condition] || condition || 'Chưa đánh giá';
};

const collectItems = (returnRequest) => {
    if (!returnRequest?.items?.length) {
        return [];
    }

    return returnRequest.items.map((item, index) => ({
        index: index + 1,
        code: safeText(item.internal_code || item.product_info?.internal_code),
        name: safeText(
            item.product_info?.item_description ||
                item.product_info?.designation ||
                item.product_info?.name ||
                item.sale_item?.designation ||
                item.sale_item_shipped?.designation
        ),
        brand: safeText(item.product_info?.brand),
        unit: safeText(item.product_info?.unit, 'Cái'),
        quantity: safeNumber(item.quantity_requested, 0),
        condition: getConditionLabel(item.condition),
    }));
};

const drawDetailRows = ({ doc, baseFont, rows, margin, startY, pageWidth }) => {
    let cursorY = startY;
    const labelX = margin;
    const valueX = margin + 42;
    const maxValueWidth = pageWidth - margin - valueX;
    const lineHeight = 5.6;

    rows.forEach((row) => {
        doc.setFont(baseFont, 'bold');
        doc.setFontSize(9.5);
        doc.text(`${row.label}:`, labelX, cursorY);

        doc.setFont(baseFont, 'normal');
        const valueLines = doc.splitTextToSize(safeText(row.value, '-'), maxValueWidth);
        doc.text(valueLines, valueX, cursorY);
        cursorY += Math.max(1, valueLines.length) * lineHeight;
    });

    return cursorY + 2;
};

const drawSignatures = ({
    doc,
    baseFont,
    signatures,
    margin,
    startY,
    pageWidth,
    showSignerNames = true,
}) => {
    const signatureWidth = (pageWidth - margin * 2) / signatures.length;
    const titleLineHeight = 4.2;

    signatures.forEach((signature, index) => {
        const x = margin + index * signatureWidth;
        const centerX = x + signatureWidth / 2;
        const titleLines = String(signature.title)
            .split('\n')
            .flatMap((line) => doc.splitTextToSize(line, signatureWidth - 4));

        doc.setFont(baseFont, 'bold');
        doc.setFontSize(9);
        titleLines.forEach((line, lineIndex) => {
            doc.text(line, centerX, startY + lineIndex * titleLineHeight, { align: 'center' });
        });

        doc.setFont(baseFont, 'normal');
        doc.setFontSize(8);
        doc.text('(Ký, họ tên)', centerX, startY + titleLines.length * titleLineHeight, {
            align: 'center',
        });

        if (signature.signatureData?.dataUrl) {
            const aspectRatio = signature.signatureData.height
                ? signature.signatureData.width / signature.signatureData.height
                : 2;
            const imageMaxWidth = signatureWidth - 8;
            const imageMaxHeight = 18;
            const imageWidth = Math.min(imageMaxWidth, imageMaxHeight * aspectRatio);
            const imageHeight = imageWidth / aspectRatio;
            const imageX = centerX - imageWidth / 2;
            const imageY = startY + 11;

            doc.addImage(
                signature.signatureData.dataUrl,
                getImageFormatFromDataUrl(signature.signatureData.dataUrl),
                imageX,
                imageY,
                imageWidth,
                imageHeight
            );
        }

        const signerName = showSignerNames ? safeText(signature.name) : '';
        if (signerName) {
            doc.setFont(baseFont, 'bold');
            doc.setFontSize(8.5);
            doc.text(doc.splitTextToSize(signerName, signatureWidth - 6), centerX, startY + 34, {
                align: 'center',
            });
        }
    });
};

const buildSignatureColumns = (returnRequest, type) => {
    const requesterName = getUserName(returnRequest?.requester);
    const inspectorName = getUserName(returnRequest?.inspector);
    const techReceiverName = getUserName(returnRequest?.tech_receiver);
    const mainApproverName = getUserName(returnRequest?.main_approver);
    const warehouseConfirmerName = getUserName(returnRequest?.warehouse_confirmer);

    if (type === 'tech_receipt') {
        return [
            {
                title: 'Người yêu cầu',
                name: requesterName,
                signatureUrl: returnRequest?.requester?.signature_url,
            },
            {
                title: 'Người kiểm tra',
                name: inspectorName,
                signatureUrl: returnRequest?.inspector?.signature_url,
            },
            {
                title: 'Người nhập kho',
                name: techReceiverName,
                signatureUrl: returnRequest?.tech_receiver?.signature_url,
            },
        ];
    }

    return [
        {
            title: 'Người yêu cầu',
            name: techReceiverName,
            signatureUrl: returnRequest?.tech_receiver?.signature_url,
        },
        {
            title: 'Người duyệt',
            name: mainApproverName,
            signatureUrl: returnRequest?.main_approver?.signature_url,
        },
        {
            title: 'Thủ kho xuất',
            name: techReceiverName,
            signatureUrl: returnRequest?.tech_receiver?.signature_url,
        },
        {
            title: 'Thủ kho nhập',
            name: warehouseConfirmerName,
            signatureUrl: returnRequest?.warehouse_confirmer?.signature_url,
        },
    ];
};

const loadSignatureImages = async (signatures) => {
    const cache = new Map();

    return Promise.all(
        signatures.map(async (signature) => {
            const signatureUrlRaw = safeText(signature.signatureUrl);

            if (!signatureUrlRaw) {
                return signature;
            }

            try {
                const signatureUrl = new URL(signatureUrlRaw, window.location.origin).href;

                if (!cache.has(signatureUrl)) {
                    cache.set(signatureUrl, loadImageData(signatureUrl));
                }

                return {
                    ...signature,
                    signatureData: await cache.get(signatureUrl),
                };
            } catch (error) {
                console.warn('Không thể tải chữ ký cho PDF:', error.message);

                return signature;
            }
        })
    );
};

const buildDocumentRows = (returnRequest, type, warehouseNames) => {
    const invoiceNumber = returnRequest?.sale_invoice?.invoice_number;
    const requesterName = getUserName(returnRequest?.requester);
    const techReceiverName =
        getUserName(returnRequest?.tech_receiver) || getUserName(returnRequest?.inspector);
    const customerName = returnRequest?.customer?.name;
    const techWarehouseName = getWarehouseName(warehouseNames, TECH_WAREHOUSE_ID);
    const mainWarehouseName = getWarehouseName(warehouseNames, MAIN_WAREHOUSE_ID);

    if (type === 'tech_receipt') {
        return [
            { label: 'Người yêu cầu', value: requesterName },
            { label: 'Khách hàng', value: customerName },
            { label: 'Chứng từ gốc', value: invoiceNumber },
            { label: 'Nhập tại kho', value: techWarehouseName },
            { label: 'Lý do trả hàng', value: returnRequest?.reason },
        ];
    }

    return [
        { label: 'Kỹ thuật nhập kho', value: techReceiverName },
        { label: 'Khách hàng', value: customerName },
        { label: 'Chứng từ gốc', value: invoiceNumber },
        { label: 'Kho xuất', value: techWarehouseName },
        { label: 'Kho nhập', value: mainWarehouseName },
        { label: 'Lý do trả hàng', value: returnRequest?.reason },
    ];
};

const createDocumentPdfBlob = async (returnRequest, options = {}) => {
    const type = options.type || 'tech_receipt';
    const includeSignatureImages = options.includeSignatureImages !== false;
    const includeSignerNames = options.includeSignerNames !== false;
    await ensurePdfLibs();
    const doc = new jsPDF({ unit: 'mm', format: 'a4', compress: true });
    const baseFont = await registerRobotoFonts(doc);
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const margin = 14;
    const companyConfig = getCompanyConfig(options.companySlug || 'AC');
    const requestNumber = returnRequest?.request_number || `SRR-${returnRequest?.id || 'preview'}`;
    const warehouseNames = options.warehouseNames || {};
    const documentDate =
        type === 'tech_receipt'
            ? returnRequest?.tech_received_at ||
              returnRequest?.inspected_at ||
              returnRequest?.created_at
            : returnRequest?.main_approved_at ||
              returnRequest?.warehouse_confirmed_at ||
              returnRequest?.created_at;
    const title =
        type === 'tech_receipt' ? 'PHIẾU NHẬP KHO HÀNG TRẢ' : 'PHIẾU ĐIỀU CHUYỂN HÀNG NHẬP TRẢ';
    const qrDataUrl = await createDocumentQrDataUrl(
        options.qrPayload || options.requestUrl || requestNumber
    );

    const headerBottom = await drawPdfCompanyLogoAndTextHeader(
        doc,
        baseFont,
        companyConfig,
        margin,
        margin,
        { pageWidthMm: pageWidth, headerRightReservedMm: 32 }
    );

    drawPdfTopRightQrBlock({
        doc,
        qrDataUrl,
        topLabel: '',
        value: requestNumber,
        x: pageWidth - margin - 18,
        y: margin - 4,
        qrSize: 18,
        textWidth: 28,
        baseFont,
        textPosition: 'below',
    });

    doc.setFont(baseFont, 'bold');
    doc.setFontSize(type === 'tech_receipt' ? 16 : 14);
    doc.text(title, pageWidth / 2, headerBottom + 8, { align: 'center' });

    doc.setFont(baseFont, 'normal');
    doc.setFontSize(10);
    doc.text(`Số: ${requestNumber}`, pageWidth / 2, headerBottom + 15, { align: 'center' });
    doc.text(formatVietnameseDate(documentDate), pageWidth - margin, headerBottom + 15, {
        align: 'right',
    });

    let cursorY = drawDetailRows({
        doc,
        baseFont,
        rows: buildDocumentRows(returnRequest, type, warehouseNames),
        margin,
        startY: headerBottom + 24,
        pageWidth,
    });

    const items = collectItems(returnRequest);
    const totalQuantity = items.reduce((sum, item) => sum + item.quantity, 0);

    const head =
        type === 'tech_receipt'
            ? [
                [
                    'STT',
                    'Mã hàng',
                    'Tên hàng',
                    'Thương hiệu',
                    'ĐVT',
                    'Theo yêu cầu',
                    'Thực nhập',
                    'Chất lượng',
                ],
            ]
            : [['STT', 'Mã hàng', 'Tên hàng', 'ĐVT', 'Số lượng', 'Chất lượng']];

    const body =
        type === 'tech_receipt'
            ? items.map((item) => [
                item.index,
                item.code,
                item.name,
                item.brand,
                item.unit,
                formatNumber(item.quantity, 0),
                formatNumber(item.quantity, 0),
                item.condition,
            ])
            : items.map((item) => [
                item.index,
                item.code,
                item.name,
                item.unit,
                formatNumber(item.quantity, 0),
                item.condition,
            ]);
    const emptyRow =
        type === 'tech_receipt'
            ? ['', '', 'Không có dữ liệu', '', '', '', '', '']
            : ['', '', 'Không có dữ liệu', '', '', ''];
    const foot =
        type === 'tech_receipt'
            ? [['', 'Cộng', '', '', '', '', formatNumber(totalQuantity, 0), '']]
            : [['', 'Cộng', '', '', formatNumber(totalQuantity, 0), '']];
    const quantityColumnIndex = type === 'tech_receipt' ? 6 : 4;
    const columnStyles =
        type === 'tech_receipt'
            ? {
                0: { halign: 'center', cellWidth: 9 },
                1: { cellWidth: 24 },
                2: { cellWidth: 48 },
                3: { cellWidth: 22 },
                4: { halign: 'center', cellWidth: 12 },
                5: { halign: 'center', cellWidth: 18 },
                6: { halign: 'right', cellWidth: 18 },
                7: { halign: 'center', cellWidth: 24 },
            }
            : {
                0: { halign: 'center', cellWidth: 9 },
                1: { cellWidth: 34 },
                2: { cellWidth: 68 },
                3: { halign: 'center', cellWidth: 16 },
                4: { halign: 'right', cellWidth: 22 },
                5: { halign: 'center', cellWidth: 30 },
            };

    autoTable(doc, {
        startY: cursorY,
        head,
        body: body.length ? body : [emptyRow],
        foot,
        theme: 'grid',
        styles: {
            font: baseFont,
            fontSize: 7.8,
            cellPadding: 1.5,
            lineColor: [0, 0, 0],
            lineWidth: 0.2,
            textColor: [0, 0, 0],
        },
        headStyles: {
            fillColor: [255, 255, 255],
            textColor: [0, 0, 0],
            fontStyle: 'bold',
            halign: 'center',
        },
        footStyles: {
            fillColor: [255, 255, 255],
            textColor: [0, 0, 0],
            fontStyle: 'bold',
        },
        columnStyles,
        didParseCell: (data) => {
            if (data.section === 'foot' && [1, quantityColumnIndex].includes(data.column.index)) {
                data.cell.styles.fontStyle = 'bold';
            }
            if (data.section === 'foot' && data.column.index === quantityColumnIndex) {
                data.cell.styles.halign = 'right';
            }
        },
    });

    if (doc.lastAutoTable.finalY + 48 > pageHeight) {
        doc.addPage();
        cursorY = margin + 8;
    } else {
        cursorY = doc.lastAutoTable.finalY + 16;
    }

    const signatures = includeSignatureImages
        ? await loadSignatureImages(buildSignatureColumns(returnRequest, type))
        : buildSignatureColumns(returnRequest, type);

    drawSignatures({
        doc,
        baseFont,
        signatures,
        margin,
        startY: cursorY,
        pageWidth,
        showSignerNames: includeSignerNames,
    });

    const prefix =
        type === 'tech_receipt' ? 'Phieu_nhap_kho_hang_tra' : 'Phieu_chuyen_kho_ky_thuat';
    const signatureSuffix = includeSignatureImages ? '' : '_khong_chu_ky_anh';
    const fileName = `${prefix}${signatureSuffix}_${requestNumber}.pdf`;
    const blob = doc.output('blob');

    return { blob, fileName };
};

export function useSaleReturnRequestWarehouseDocumentsPdf() {
    const generateSaleReturnRequestWarehouseDocumentPdf = async (returnRequest, options = {}) => {
        if (typeof window === 'undefined' || typeof document === 'undefined') {
            throw new Error('Chức năng xuất PDF chỉ khả dụng trên trình duyệt.');
        }

        return createDocumentPdfBlob(returnRequest, options);
    };

    return {
        generateSaleReturnRequestWarehouseDocumentPdf,
    };
}
