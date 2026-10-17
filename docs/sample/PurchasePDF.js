import { loadPdfLibs } from '@/utils/lazyExportLibs';
import { saveAs } from 'file-saver';
import { usePage } from '@inertiajs/vue3';
import { formatNumberNoTrailingZeros, formatDateForSubmit } from '@/utils';
import { getCompanyConfig, getConfigValue } from '@/config/companyConfig';
import { applyPurchasingSenderToBuyer } from '@/utils/purchasingSender';

let jsPDF;
let autoTable;

const ensurePdfLibs = async () => {
    ({ jsPDF, autoTable } = await loadPdfLibs());
};

const ROBOTO_REGULAR_URL = 'https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Me5WZLCzYlKw.ttf';
const ROBOTO_BOLD_URL = 'https://fonts.gstatic.com/s/roboto/v30/KFOlCnqEu92Fr1MmWUlvAw.ttf';

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

const fontBase64Cache = new Map();

const getFontBase64 = async (url) => {
    if (fontBase64Cache.has(url)) {
        return fontBase64Cache.get(url);
    }

    const fontResponse = await fetch(url);
    const fontBuffer = await fontResponse.arrayBuffer();
    const fontBase64 = arrayBufferToBase64(fontBuffer);
    fontBase64Cache.set(url, fontBase64);
    return fontBase64;
};

const imageDataCache = new Map();

const loadImageData = async (url) => {
    if (imageDataCache.has(url)) {
        return imageDataCache.get(url);
    }

    try {
        const response = await fetch(url, { cache: 'no-store' });
        if (!response.ok) {
            throw new Error(`Failed to load image: ${response.status}`);
        }
        const blob = await response.blob();
        const dataUrl = await new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });

        const result = await new Promise((resolve, reject) => {
            const image = new Image();
            image.onload = () => {
                const isPng = typeof dataUrl === 'string' && dataUrl.startsWith('data:image/png');
                resolve({
                    dataUrl,
                    base64: typeof dataUrl === 'string' ? dataUrl.split(',')[1] : null,
                    width: image.width,
                    height: image.height,
                    format: isPng ? 'PNG' : 'JPEG',
                });
            };
            image.onerror = reject;
            image.src = dataUrl;
        });

        imageDataCache.set(url, result);
        return result;
    } catch (error) {
        console.warn(`Failed to load image from ${url}:`, error);
        return null;
    }
};

const registerFont = async (doc, url, fileName, fontName, fontStyle) => {
    const fontBase64 = await getFontBase64(url);

    doc.addFileToVFS(fileName, fontBase64);
    doc.addFont(fileName, fontName, fontStyle);
};

export function usePurchasePdfExport() {
    const page = usePage();

    const generatePurchasePdf = async (purchase, items, supplier, buyer, columns, options = {}) => {
        const { orientation = 'landscape', download = true, fileName, companySlug = 'AC' } = options;
        
        // Get company config based on slug
        const companyConfig = getCompanyConfig(companySlug);

        if (typeof window === 'undefined' || typeof document === 'undefined') {
            throw new Error('PDF export chỉ khả dụng trên trình duyệt.');
        }

        const normalizedOrientation = orientation === 'portrait' ? 'portrait' : 'landscape';
        await ensurePdfLibs();
        const doc = new jsPDF({
            orientation: normalizedOrientation,
            unit: 'pt',
            format: 'a4',
            compress: true,
        });

        const columnCount = (Array.isArray(columns) ? columns.length : 0) + 1; // +1 for numbering column
        const isPortrait = normalizedOrientation === 'portrait';

        const layoutScale = (() => {
            if (columnCount <= 6) return 1.12;
            if (columnCount <= 8) return 1.08;
            if (columnCount <= 10) return 1.04;
            if (columnCount >= 18) return 0.86;
            if (columnCount >= 16) return 0.88;
            if (columnCount >= 14) return 0.92;
            if (columnCount >= 12) return 0.96;
            return 1;
        })();

        const clamp = (value, min, max) => {
            let result = value;
            if (typeof min === 'number') result = Math.max(min, result);
            if (typeof max === 'number') result = Math.min(max, result);
            return result;
        };

        const scaleValue = (value, { min, max } = {}) => {
            const scaled = Number((value * layoutScale).toFixed(2));
            return clamp(scaled, min, max);
        };

        const scaleFontSize = (size, options = {}) => {
            return scaleValue(size, { min: options.min ?? 6, max: options.max });
        };

        const scaleSpacing = (value, options = {}) => {
            return scaleValue(value, options);
        };

        let customFontLoaded = false;
        let signatureData = null;

        try {
            const [, , signature] = await Promise.all([
                registerFont(doc, ROBOTO_REGULAR_URL, 'Roboto-Regular.ttf', 'Roboto', 'normal'),
                registerFont(doc, ROBOTO_BOLD_URL, 'Roboto-Bold.ttf', 'Roboto', 'bold'),
                loadImageData(companyConfig.signatureUrl),
            ]);
            doc.setFont('Roboto', 'normal');
            customFontLoaded = true;
            signatureData = signature;
        } catch (error) {
            console.warn('Không thể tải font Roboto cho PDF, sử dụng font mặc định.', error);
            doc.setFont('helvetica', 'normal');
        }

        const baseFont = customFontLoaded ? 'Roboto' : 'helvetica';

        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        const margin = layoutScale < 1 ? Math.max(30, Math.round(40 * layoutScale)) : 40;

        const poNumber = purchase?.po_number;

        let cursorY = margin;

        const ensureSpace = (height = 0) => {
            if (cursorY + height > pageHeight - margin) {
                doc.addPage();
                doc.setFont(baseFont, 'normal');
                doc.setFontSize(scaleFontSize(10));
                cursorY = margin;
            }
        };

        // PURCHASE ORDER title - centered
        ensureSpace(scaleSpacing(50));
        doc.setFont(baseFont, 'bold');
        doc.setFontSize(scaleFontSize(16));
        doc.setTextColor(0, 0, 0);
        doc.text('PURCHASE ORDER', pageWidth / 2, cursorY, { align: 'center' });
        cursorY += scaleSpacing(20);

        // PO Number - centered
        doc.setFont(baseFont, 'normal');
        doc.setFontSize(scaleFontSize(12));
        doc.text(`No: ${poNumber || ''}`, pageWidth / 2, cursorY, { align: 'center' });
        cursorY += scaleSpacing(18);

        // Date - right aligned
        const today = new Date();
        const day = String(today.getDate()).padStart(2, '0');
        const month = today.toLocaleString('en-US', { month: 'long' });
        const year = today.getFullYear();
        const dateStr = `${day}th ${month}, ${year}`;
        doc.setFontSize(scaleFontSize(11));
        doc.text(dateStr, pageWidth - margin, cursorY, { align: 'right' });
        cursorY += scaleSpacing(25);

        // Supplier and Buyer section - two columns
        ensureSpace(scaleSpacing(150));

        doc.setFontSize(scaleFontSize(10));
        const columnGap = scaleSpacing(20);
        const columnWidth = (pageWidth - margin * 2 - columnGap) / 2;
        const leftColumnX = margin;
        const rightColumnX = margin + columnWidth + columnGap;
        const lineHeight = scaleSpacing(14, { min: 10 });

        // SUPPLIER header
        doc.setFont(baseFont, 'bold');
        doc.setFontSize(scaleFontSize(11));
        doc.text('SUPPLIER', leftColumnX, cursorY);

        // THE BUYER header
        doc.text('THE BUYER', rightColumnX, cursorY);

        cursorY += lineHeight + scaleSpacing(5);
        doc.setFontSize(scaleFontSize(10));

        // Supplier info - support both formats (name/company, contact_name/contact)
        const supplierName = supplier?.name || supplier?.company || '';
        const supplierAddress = supplier?.address || '';
        const supplierContact = supplier?.contact_name || supplier?.contact || '';
        const supplierEmail = supplier?.email || '';
        const supplierPhone = supplier?.phone || '';

        // Buyer info from company config
        const buyerName = getConfigValue(companyConfig, 'nameEn', 'en') || companyConfig.nameEn;
        const buyerAddress = getConfigValue(companyConfig, 'addressEn', 'en') || companyConfig.addressEn;
        const buyerPurchaser = buyer?.purchaser || buyer?.name || '';
        const buyerEmail = buyer?.email || companyConfig.email || '';
        const buyerPhone = buyer?.phone || companyConfig.tel || '';

        let leftY = cursorY;
        let rightY = cursorY;

        // Find the longest label width for both supplier and buyer
        doc.setFont(baseFont, 'normal');
        const supplierLabels = ['Name', 'Address', 'Person Contact', 'Email', 'Tell/Fax'];
        const buyerLabels = ['Name', 'Address', 'Purchaser', 'Email', 'Tell/Fax'];

        const maxSupplierLabelWidth = Math.max(
            ...supplierLabels.map((label) => doc.getTextWidth(label))
        );
        const maxBuyerLabelWidth = Math.max(...buyerLabels.map((label) => doc.getTextWidth(label)));

        // Helper function to render label-value pair with aligned colons
        const renderField = (label, value, x, y, maxLabelWidth) => {
            doc.setFont(baseFont, 'normal');

            // Remove colon from label if present
            const labelWithoutColon = label.replace(':', '').trim();

            // Draw label left-aligned at x
            doc.text(labelWithoutColon, x, y);

            // Draw colon at fixed position based on longest label
            const colonX = x + maxLabelWidth + scaleSpacing(2);
            doc.text(':', colonX, y);

            // Value starts after colon
            const valueX = colonX + scaleSpacing(5);

            // Wrap text if too long
            const availableWidth = columnWidth - maxLabelWidth - scaleSpacing(10);
            const valueLines = doc.splitTextToSize(value, availableWidth);

            if (valueLines.length === 1) {
                doc.text(valueLines[0], valueX, y);
                return y + lineHeight;
            } else {
                doc.text(valueLines[0], valueX, y);
                let newY = y + lineHeight;
                for (let i = 1; i < valueLines.length; i++) {
                    doc.text(valueLines[i], valueX, newY);
                    newY += lineHeight;
                }
                return newY;
            }
        };

        // Render supplier fields
        leftY = renderField('Name:', supplierName, leftColumnX, leftY, maxSupplierLabelWidth);
        leftY = renderField('Address:', supplierAddress, leftColumnX, leftY, maxSupplierLabelWidth);
        leftY = renderField(
            'Person Contact:',
            supplierContact,
            leftColumnX,
            leftY,
            maxSupplierLabelWidth
        );
        leftY = renderField('Email:', supplierEmail, leftColumnX, leftY, maxSupplierLabelWidth);
        leftY = renderField('Tell/Fax:', supplierPhone, leftColumnX, leftY, maxSupplierLabelWidth);

        // Render buyer fields
        rightY = renderField('Name:', buyerName, rightColumnX, rightY, maxBuyerLabelWidth);
        rightY = renderField('Address:', buyerAddress, rightColumnX, rightY, maxBuyerLabelWidth);
        rightY = renderField(
            'Purchaser:',
            buyerPurchaser,
            rightColumnX,
            rightY,
            maxBuyerLabelWidth
        );
        rightY = renderField('Email:', buyerEmail, rightColumnX, rightY, maxBuyerLabelWidth);
        rightY = renderField('Tell/Fax:', buyerPhone, rightColumnX, rightY, maxBuyerLabelWidth);

        cursorY = Math.max(leftY, rightY) + scaleSpacing(15);

        // Items table
        const currency = purchase?.currency || 'USD';
        const headers = [
            'No.',
            ...columns.map((col) => {
                // Add currency to price-related columns
                if (col.field === 'unit_price' || col.field === 'original_unit_price') {
                    return `${col.header} (${currency})`;
                }
                if (col.field === 'sub_total') {
                    return `${col.header} (${currency})`;
                }
                return col.header;
            }),
        ];
        const tableRows = items.map((item, index) => {
            const row = [String(index + 1)];
            columns.forEach((col) => {
                let value = item[col.field];

                // Calculate sub_total for each row (without VAT for individual items)
                if (col.field === 'sub_total' || col.type === 'calculated') {
                    const quantity = parseFloat(item.quantity) || 0;
                    const unitPrice =
                        parseFloat(String(item.unit_price || '').replace(/[^0-9.-]/g, '')) || 0;
                    value = formatNumberNoTrailingZeros(quantity * unitPrice, 2);
                }
                // Use column's format function if available
                else if (col.format && typeof col.format === 'function') {
                    value = col.format(value);
                } else if (
                    (col.type === 'currency' || col.type === 'number') &&
                    value !== undefined &&
                    value !== null &&
                    value !== ''
                ) {
                    const numeric = parseFloat(String(value).replace(/[^0-9.-]/g, ''));
                    if (!Number.isNaN(numeric)) {
                        value =
                            col.type === 'currency'
                                ? numeric.toLocaleString('en-US')
                                : formatNumberNoTrailingZeros(numeric, col.round || 0);
                    }
                }

                row.push(value == null ? '' : String(value));
            });
            return row;
        });

        // Calculate summary values
        let subtotalSum = 0;
        let vatSum = 0;
        const vatTotalsByRate = new Map();

        // Check if currency is VND to decide whether to include VAT
        const purchaseCurrency = (purchase?.currency || 'VND').toUpperCase();
        const includeVat =
            purchaseCurrency.includes('VND') || purchaseCurrency.includes('VNĐ');

        items.forEach((item) => {
            const quantity = parseFloat(item.quantity) || 0;
            const unitPrice =
                parseFloat(String(item.unit_price || '').replace(/[^0-9.-]/g, '')) || 0;
            const vat = parseFloat(item.vat) || 0;
            const itemSubtotal = quantity * unitPrice;
            const itemVat = includeVat ? itemSubtotal * (vat / 100) : 0;

            subtotalSum += itemSubtotal;
            vatSum += itemVat;

            if (includeVat && vat > 0) {
                const currentVatTotal = vatTotalsByRate.get(vat) || 0;
                vatTotalsByRate.set(vat, currentVatTotal + itemVat);
            }
        });

        const grandTotal = subtotalSum + vatSum;

        // Helper function to create summary row
        const createSummaryRow = (label, value, showInColumn = true) => {
            const row = [''];
            columns.forEach((col, colIndex) => {
                if (colIndex === 0) {
                    row.push(label);
                } else if (
                    showInColumn &&
                    (col.field === 'sub_total' || col.type === 'calculated')
                ) {
                    row.push(formatNumberNoTrailingZeros(value, 2));
                } else {
                    row.push('');
                }
            });
            return row;
        };

        // Add Total rows with delivery info
        const deliveryTerm = items[0]?.delivery_term || '';
        const deliveryPoint = items[0]?.delivery_point || '';
        const totalLabel = `TOTAL  ${deliveryTerm}  ${deliveryPoint}`.trim();

        if (includeVat) {
            tableRows.push(createSummaryRow('TOTAL (BEFORE VAT)', subtotalSum, true));

            const vatRates = Array.from(vatTotalsByRate.keys()).sort((a, b) => a - b);
            vatRates.forEach((rate) => {
                const vatTotal = vatTotalsByRate.get(rate) || 0;
                tableRows.push(createSummaryRow(`VAT (${rate}%)`, vatTotal, true));
            });

            tableRows.push(createSummaryRow(`${totalLabel} (AFTER VAT)`, grandTotal, true));
        } else {
            tableRows.push(createSummaryRow(totalLabel, grandTotal, true));
        }

        // Find the sub_total column index for styling
        let subTotalColIndex = -1;
        columns.forEach((col, colIndex) => {
            if (col.field === 'sub_total' || col.type === 'calculated') {
                subTotalColIndex = colIndex + 1; // +1 because column 0 is the row number
            }
        });

        ensureSpace(scaleSpacing(40));

        // Auto-scale settings based on column count and orientation
        let fontSize = 9;
        let cellPaddingTop = 5;
        let cellPaddingRight = 6;
        let cellPaddingBottom = 5;
        let cellPaddingLeft = 6;
        let minCellHeight = 18;
        let rowNumberWidth = 32;
        let minDesignationWidth = 75;

        // Scale down for portrait with many columns
        if (isPortrait) {
            if (columnCount >= 12) {
                fontSize = 6.5;
                cellPaddingTop = 2;
                cellPaddingRight = 2;
                cellPaddingBottom = 2;
                cellPaddingLeft = 2;
                minCellHeight = 14;
                rowNumberWidth = 22;
                minDesignationWidth = 45;
            } else if (columnCount >= 10) {
                fontSize = 7;
                cellPaddingTop = 3;
                cellPaddingRight = 3;
                cellPaddingBottom = 3;
                cellPaddingLeft = 3;
                minCellHeight = 15;
                rowNumberWidth = 25;
                minDesignationWidth = 50;
            } else if (columnCount >= 8) {
                fontSize = 7.5;
                cellPaddingTop = 3;
                cellPaddingRight = 4;
                cellPaddingBottom = 3;
                cellPaddingLeft = 4;
                minCellHeight = 16;
                rowNumberWidth = 28;
                minDesignationWidth = 60;
            }
        } else {
            // Landscape mode - more generous but still scale for very many columns
            if (columnCount >= 15) {
                fontSize = 7.5;
                cellPaddingTop = 4;
                cellPaddingRight = 4;
                cellPaddingBottom = 4;
                cellPaddingLeft = 4;
                minCellHeight = 16;
                rowNumberWidth = 28;
                minDesignationWidth = 65;
            } else if (columnCount >= 12) {
                fontSize = 8;
                cellPaddingTop = 4;
                cellPaddingRight = 5;
                cellPaddingBottom = 4;
                cellPaddingLeft = 5;
                minCellHeight = 17;
                rowNumberWidth = 30;
                minDesignationWidth = 70;
            }
        }

        fontSize = scaleValue(fontSize, { min: 6, max: 11 });
        cellPaddingTop = scaleValue(cellPaddingTop, { min: 2, max: 9 });
        cellPaddingRight = scaleValue(cellPaddingRight, { min: 2, max: 9 });
        cellPaddingBottom = scaleValue(cellPaddingBottom, { min: 2, max: 9 });
        cellPaddingLeft = scaleValue(cellPaddingLeft, { min: 2, max: 9 });
        minCellHeight = scaleValue(minCellHeight, { min: 12, max: 28 });
        rowNumberWidth = scaleValue(rowNumberWidth, { min: 20, max: 45 });
        minDesignationWidth = scaleValue(minDesignationWidth, { min: 45, max: 140 });

        autoTable(doc, {
            startY: cursorY,
            head: [headers],
            body: tableRows,
            margin: { left: margin, right: margin },
            styles: {
                font: baseFont,
                fontSize: fontSize,
                cellPadding: {
                    top: cellPaddingTop,
                    right: cellPaddingRight,
                    bottom: cellPaddingBottom,
                    left: cellPaddingLeft,
                },
                textColor: [0, 0, 0],
                lineColor: [210, 210, 210],
                lineWidth: 0.4,
                overflow: 'linebreak',
                cellWidth: 'wrap',
            },
            headStyles: {
                fillColor: [255, 255, 255],
                textColor: [0, 0, 0],
                halign: 'center',
                fontStyle: 'bold',
                minCellHeight: minCellHeight,
            },
            alternateRowStyles: {
                fillColor: [255, 255, 255],
            },
            columnStyles: columns.reduce(
                (accumulator, col, index) => {
                    const colIndex = index + 1;
                    const isNumeric =
                        col.type === 'currency' ||
                        col.type === 'number' ||
                        col.type === 'calculated';
                    const shouldAlignRight = isNumeric || col.field === 'lead_time_days';
                    accumulator[colIndex] = {
                        halign: shouldAlignRight ? 'right' : 'left',
                        cellWidth: 'auto',
                        minCellWidth:
                            col.field === 'designation' || col.field === 'item_description'
                                ? minDesignationWidth
                                : undefined,
                        minCellHeight: minCellHeight,
                    };
                    return accumulator;
                },
                {
                    0: {
                        cellWidth: rowNumberWidth,
                        halign: 'center',
                        minCellHeight: minCellHeight,
                    },
                }
            ),
            didParseCell: (data) => {
                // Style the last summary rows (Total)
                const summaryRowCount = includeVat
                    ? 2 + Array.from(vatTotalsByRate.keys()).length
                    : 1;
                const isSummaryRow =
                    data.section === 'body' &&
                    data.row.index >= tableRows.length - summaryRowCount;

                if (isSummaryRow) {
                    // Apply bold style and background color
                    data.cell.styles.fontStyle = 'bold';
                    data.cell.styles.fillColor = [240, 240, 240];

                    // Span the label across columns until sub_total column
                    if (data.column.index === 1 && subTotalColIndex > 0) {
                        const colSpan = subTotalColIndex - 1;
                        data.cell.colSpan = colSpan;
                        data.cell.styles.halign = 'center'; // Center-align the label
                    } else if (data.column.index > 1 && data.column.index < subTotalColIndex) {
                        // Hide cells that are spanned over
                        data.cell.styles.fillColor = false;
                        data.cell.styles.lineWidth = 0;
                    }
                }
            },
        });

        cursorY = doc.lastAutoTable ? doc.lastAutoTable.finalY : cursorY + scaleSpacing(120);

        cursorY += scaleSpacing(20);

        // Terms/Notes section
        ensureSpace(scaleSpacing(200));

        doc.setFont(baseFont, 'normal');
        doc.setFontSize(scaleFontSize(10));
        const termsLineHeight = scaleSpacing(12, { min: 8 });
        const labelWidth = scaleSpacing(140, { min: 110 });
        const valueX = margin + labelWidth;
        const valueWidth = pageWidth - margin * 2 - labelWidth;

        // Get custom terms from options or use defaults
        const customTerms = options.customTerms || {};
        const note = customTerms.note || purchase?.description || '';
        const paymentTerm = customTerms.paymentTerm || 'T/T in advance';
        const billingTo =
            customTerms.billingTo ||
            `${buyerName}\nAddress: ${buyerAddress}\nTax code: ${companyConfig.taxCode}`;
        const documentShippingTo =
            customTerms.documentShippingTo ||
            `${buyerName}\nAddress: ${getConfigValue(companyConfig, 'officeAddressEn', 'en') || companyConfig.officeAddressEn}\nZip Code: 100000\nEmail: ${buyerEmail}\nPhone number: ${buyerPhone}`;
        const documentsRequested =
            customTerms.documentsRequested ||
            'Original Invoice, COO, COC, Form E CO, Packing list and all documents involved.';

        // Note
        if (note) {
            doc.setFont(baseFont, 'bold');
            doc.text('Note:', margin, cursorY);
            doc.setFont(baseFont, 'normal');
            const noteLines = doc.splitTextToSize(note, valueWidth);
            doc.text(noteLines, valueX, cursorY);
            cursorY += noteLines.length * termsLineHeight + scaleSpacing(5);
        }

        // Payment Term
        if (paymentTerm) {
            doc.setFont(baseFont, 'bold');
            doc.text('Payment Term:', margin, cursorY);
            doc.setFont(baseFont, 'normal');
            const paymentLines = doc.splitTextToSize(paymentTerm, valueWidth);
            doc.text(paymentLines, valueX, cursorY);
            cursorY += paymentLines.length * termsLineHeight + scaleSpacing(5);
        }

        // Billing to
        if (billingTo) {
            doc.setFont(baseFont, 'bold');
            doc.text('Billing to:', margin, cursorY);
            doc.setFont(baseFont, 'normal');
            const billingLines = doc.splitTextToSize(billingTo, valueWidth);
            doc.text(billingLines, valueX, cursorY);
            cursorY += billingLines.length * termsLineHeight + scaleSpacing(5);
        }

        // Document Shipping to
        if (documentShippingTo) {
            doc.setFont(baseFont, 'bold');
            doc.text('Document Shipping to:', margin, cursorY);
            doc.setFont(baseFont, 'normal');
            const shippingLines = doc.splitTextToSize(documentShippingTo, valueWidth);
            doc.text(shippingLines, valueX, cursorY);
            cursorY += shippingLines.length * termsLineHeight + scaleSpacing(5);
        }

        // Documents Requested (in red)
        if (documentsRequested) {
            doc.setFont(baseFont, 'bold');
            doc.setTextColor(0, 0, 0);
            doc.text('Documents Requested:', margin, cursorY);
            doc.setFont(baseFont, 'normal');
            doc.setTextColor(255, 0, 0);
            const docsLines = doc.splitTextToSize(documentsRequested, valueWidth);
            doc.text(docsLines, valueX, cursorY);
            doc.setTextColor(0, 0, 0);
            cursorY += docsLines.length * termsLineHeight + scaleSpacing(15);
        }

        cursorY += scaleSpacing(20);

        // Signature section
        ensureSpace(scaleSpacing(185));

        const signatureY = cursorY;
        const signatureGap = scaleSpacing(40);
        const signatureWidth = (pageWidth - margin * 2 - signatureGap) / 2;

        // Supplier signature
        const supplierX = margin;
        doc.setFont(baseFont, 'bold');
        doc.setFontSize(scaleFontSize(10));
        doc.text('FOR THE SUPPLIER', supplierX + signatureWidth / 2, signatureY, {
            align: 'center',
        });

        // Buyer signature
        const buyerX = supplierX + signatureWidth + signatureGap;
        doc.text('FOR BUYER', buyerX + signatureWidth / 2, signatureY, { align: 'center' });

        // Add signature image for buyer
        if (signatureData?.dataUrl) {
            try {
                const aspectRatio = signatureData.width && signatureData.height
                    ? signatureData.width / signatureData.height
                    : 1.5;

                const isAC = (companySlug || '').toUpperCase() === 'AC';
                const maxBoxWidth = Math.min(
                    signatureWidth,
                    scaleSpacing(isAC ? 240 : 250, { min: 180, max: signatureWidth })
                );
                const maxBoxHeight = scaleSpacing(isAC ? 165 : 130, { min: 90, max: isAC ? 185 : 150 });

                let signatureImgWidth = maxBoxWidth;
                let signatureImgHeight = signatureImgWidth / aspectRatio;

                if (signatureImgHeight > maxBoxHeight) {
                    signatureImgHeight = maxBoxHeight;
                    signatureImgWidth = signatureImgHeight * aspectRatio;
                }

                const signatureImgX = buyerX + (signatureWidth - signatureImgWidth) / 2;
                const signatureImgY = signatureY + scaleSpacing(10);

                doc.addImage(
                    signatureData.dataUrl,
                    signatureData.format,
                    signatureImgX,
                    signatureImgY,
                    signatureImgWidth,
                    signatureImgHeight
                );
            } catch (error) {
                console.warn('Could not add signature image:', error);
            }
        }

        const filename =
            fileName || `${poNumber || 'preview'}_${formatDateForSubmit(new Date())}.pdf`;
        const blob = doc.output('blob');

        if (download) {
            saveAs(blob, filename);
        }

        return blob;
    };

    // Get company slug from page props
    const getCompanySlug = () => page.props.company?.slug || 'AC';
    
    // Build buyer info from company config and creator
    const buildBuyerInfo = (creator = null) => {
        const companySlug = getCompanySlug();
        const config = getCompanyConfig(companySlug);

        const buyer = {
            name: config.nameEn,
            address: config.addressEn,
            purchaser: creator?.name || '',
            email: creator?.email || config.email || '',
            tel_fax: creator?.phone || config.tel || '',
        };

        return applyPurchasingSenderToBuyer(buyer, page.props.purchasingSender);
    };
    
    // Wrapper that auto-injects companySlug if not provided in options
    const generatePdf = async (purchase, items, supplier, buyer = null, columns = null, options = {}) => {
        const companySlug = options.companySlug || getCompanySlug();
        // If buyer is not provided, build it from company config
        const effectiveBuyer = buyer || buildBuyerInfo();
        return await generatePurchasePdf(purchase, items, supplier, effectiveBuyer, columns, { ...options, companySlug });
    };

    return {
        generatePurchasePdf: generatePdf,
        buildBuyerInfo,
        getCompanySlug,
    };
}
