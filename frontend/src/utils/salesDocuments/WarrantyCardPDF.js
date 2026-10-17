import { loadPdfLibs } from './sampleSupport';
import FileSaver from 'file-saver';
const saveAs = FileSaver.saveAs || FileSaver;
import { getCompanyConfig } from './sampleSupport';

let jsPDF;
let autoTable;

const ensurePdfLibs = async () => {
    ({ jsPDF, autoTable } = await loadPdfLibs());
};

// Helper to load fonts/images (similar to other composables)
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

const imageBase64Cache = new Map();
const getImageBase64 = async (url) => {
    if (!url) return null;
    if (imageBase64Cache.has(url)) return imageBase64Cache.get(url);
    try {
        const response = await fetch(url);
        const buffer = await response.arrayBuffer();
        const base64 = arrayBufferToBase64(buffer);
        const dataUrl = `data:${response.headers.get('content-type') || 'image/png'};base64,${base64}`;
        imageBase64Cache.set(url, dataUrl);
        return dataUrl;
    } catch (error) {
        console.warn(`Failed to load image from ${url}:`, error);
        return null;
    }
};

const ROBOTO_REGULAR_URL = 'https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Me5WZLCzYlKw.ttf';
const ROBOTO_BOLD_URL = 'https://fonts.gstatic.com/s/roboto/v30/KFOlCnqEu92Fr1MmWUlvAw.ttf';

const getFontBase64 = async (url) => {
    if (imageBase64Cache.has(url)) return imageBase64Cache.get(url); // Re-use cache map
    const fontResponse = await fetch(url);
    const fontBuffer = await fontResponse.arrayBuffer();
    const fontBase64 = arrayBufferToBase64(fontBuffer);
    imageBase64Cache.set(url, fontBase64);
    return fontBase64;
};

const registerFont = async (doc, url, fileName, fontName, fontStyle) => {
    try {
        const fontBase64 = await getFontBase64(url);
        doc.addFileToVFS(fileName, fontBase64);
        doc.addFont(fileName, fontName, fontStyle);
    } catch (e) {
        console.warn('Failed to register font', e);
    }
};

export function useWarrantyCardPdf() {
    const generateWarrantyCardPdf = async (data, options = {}) => {
        /**
         * data structure:
         * {
         *   customerName: string,
         *   address: string,
         *   items: [ { designation: string, quantity: number, origin: string } ],
         *   deliveryDate: string, // e.g. "Thường là ngày biên bản" or specific date
         *   warrantyPeriod: string, // default "12 tháng"
         *   warrantyMethod: string, // text
         *   warrantyLocation: string, // text
         *   warrantyNumber: string, // So phieu
         *   day: string, month: string, year: string // For date line
         * }
         *
         * options.showOrigin: bat/tat cot "Hang - Xuat xu" trong bang san pham
         */

        const { fileName, download = true } = options;
        const config = getCompanyConfig();
        const showOrigin = options.showOrigin === true;
        // So o content cua cac dong "label - noi dung" (2 cot mac dinh, 3 cot khi hien Hang/Xuat xu)
        const contentColSpan = showOrigin ? 3 : 2;

        await ensurePdfLibs();

        const doc = new jsPDF({
            orientation: 'p',
            unit: 'pt',
            format: 'a4',
            compress: true,
        });

        // Load fonts and images
        await Promise.all([
            registerFont(doc, ROBOTO_REGULAR_URL, 'Roboto-Regular.ttf', 'Roboto', 'normal'),
            registerFont(doc, ROBOTO_BOLD_URL, 'Roboto-Bold.ttf', 'Roboto', 'bold'),
        ]);

        doc.setFont('Roboto', 'normal');

        const leftHeaderImageUrl = config.warrantyLeftUrl || config.logoUrl;
        const leftImgBase64 = leftHeaderImageUrl ? await getImageBase64(leftHeaderImageUrl) : null;
        const rightImgBase64 = config.warrantyRightUrl ? await getImageBase64(config.warrantyRightUrl) : null;
        const pdfImageFormatForUrl = (image) => image?.startsWith('data:image/jpeg') ? 'JPEG' : image?.startsWith('data:image/webp') ? 'WEBP' : 'PNG';

        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        const margin = 30;
        let cursorY = 30;
        const centerX = pageWidth / 2;

        {
            // ================= HEADER =================
            // Layout: Left Img (20%) | Center Text (60%) | Right Img (20%)

            const headerHeight = 80;
            const maxImgHeight = 80;
            const leftImgWidth = 100;
            const rightImgWidth = 100;

            // Left Image
            if (leftImgBase64) {
                const props = doc.getImageProperties(leftImgBase64);
                const ratio = props.height / props.width;
                let w = leftImgWidth;
                let h = w * ratio;

                // Constrain height
                if (h > maxImgHeight) {
                    h = maxImgHeight;
                    w = h / ratio;
                }

                doc.addImage(
                    leftImgBase64,
                    pdfImageFormatForUrl(leftImgBase64),
                    margin,
                    cursorY,
                    w,
                    h
                );
            }

            // Right Image
            if (rightImgBase64) {
                const props = doc.getImageProperties(rightImgBase64);
                const ratio = props.height / props.width;
                let w = rightImgWidth;
                let h = w * ratio;

                // Constrain height specific for Right Image to be smaller ("thấp lại")
                // BUT keep width constant as requested ("chiều rộng vẫn giữ nguyên")
                const maxRightImgHeight = 75;
                if (h > maxRightImgHeight) {
                    h = maxRightImgHeight;
                    // w = h / ratio; // Do NOT recalculate width, allow distortion/squashing
                }

                // Center vertically relative to headerHeight if shorter?
                // Or just align top?
                // "Thấp lại" usually means size in this context if overflow was an issue.
                // Let's also push it down slightly to center it with text if it's small.
                const yOffset = (80 - h) / 2; // Center in the 80pt header space

                doc.addImage(
                    rightImgBase64,
                    pdfImageFormatForUrl(rightImgBase64),
                    pageWidth - margin - w,
                    cursorY + yOffset,
                    w,
                    h
                );
            }

            // Center Text
            const lineHeight = 13;

            // Line 1: Name (Bold)
            doc.setFontSize(10);
            doc.setFont('Roboto', 'bold');
            doc.text(config.name.toUpperCase(), centerX, cursorY + 12, { align: 'center' });

            // Line 2: NameEn (Bold)
            doc.setFontSize(9);
            doc.text(config.nameEn, centerX, cursorY + 12 + lineHeight, { align: 'center' });

            // Line 3: Address (Normal)
            doc.setFont('Roboto', 'normal');
            doc.text(config.address, centerX, cursorY + 12 + lineHeight * 2, {
                align: 'center',
            });

            // Line 4: Contact (Normal)
            const contactSegments = [`Tel: ${config.tel}`];
            if (config.fax) {
                contactSegments.push(`Fax: ${config.fax}`);
            }
            contactSegments.push(`Hotline: ${config.hotline}`);
            const contactLine = contactSegments.join('; ');
            doc.text(contactLine, centerX, cursorY + 12 + lineHeight * 3, { align: 'center' });

            // Line 5: Email/Web (Bold) - Note: User requested "last line" bold, which is this one
            doc.setFont('Roboto', 'bold');
            const websiteLine = config.websiteWarranty || config.website || '';
            const emailWebLine = `Email: ${config.email}; Website: ${websiteLine}`;
            doc.text(emailWebLine, centerX, cursorY + 12 + lineHeight * 4, { align: 'center' });

            cursorY += headerHeight + 5;

            // Red Line
            doc.setDrawColor(255, 100, 100); // Light red/pinkish
            doc.setLineWidth(1.5);
            doc.line(margin, cursorY, pageWidth - margin, cursorY);
            cursorY += 20;
        }

        // ================= BODY =================

        // Date line: "Hà nội ngày ... tháng ... năm ..."
        // Note: 'italic' style requires registering an italic font file.
        // Using 'normal' to avoid encoding issues if italic font is missing.
        doc.setFont('Roboto', 'normal');
        doc.setFontSize(11);
        const d = data.day || '...';
        const m = data.month || '...';
        const y = data.year || '...';
        doc.text(`Hà Nội ngày ${d} tháng ${m} năm ${y}`, pageWidth - margin, cursorY, {
            align: 'right',
        });
        // Add speech bubble icon square? - skip for now

        cursorY += 30;

        // Title
        doc.setFont('Roboto', 'bold');
        doc.setFontSize(16);
        doc.text('PHIẾU BẢO HÀNH SẢN PHẨM', centerX, cursorY, { align: 'center' });
        cursorY += 20;

        // Warranty Number
        const soPhieu = data.warrantyNumber
            ? `(Số: ${data.warrantyNumber})`
            : '(Số: ..............)';
        // Removed yellow highlight as requested
        doc.setFontSize(12);
        doc.setTextColor(0, 0, 0);
        doc.text(soPhieu, centerX, cursorY, { align: 'center' });

        cursorY += 20;

        // Table
        // We use autotable to simulate the layout

        // Define data rows

        // Items sub-table handling:
        // We will build a unified table where the "Mã hiệu sản phẩm" row has the headers "Mã hàng" and "Số lượng" in col 2 and 3
        // And then subsequent rows list the items.

        const tableBody = [];

        // Row 1: Customer Name
        tableBody.push([
            { content: 'Tên khách hàng:', styles: { fontStyle: 'bold' } },
            {
                content: data.customerName || 'Theo biên bản',
                colSpan: contentColSpan,
                styles: { textColor: [0, 0, 0] },
            },
        ]);

        // Row 2: Address
        tableBody.push([
            { content: 'Địa chỉ:', styles: { fontStyle: 'bold' } },
            {
                content: data.address || 'Theo biên bản',
                colSpan: contentColSpan,
                styles: { textColor: [0, 0, 0] },
            },
        ]);

        // Row 3: Product Header
        // Col 1: Title, Col 2: "Mã hàng", Col 3: "Số lượng", Col 4: "Hãng - Xuất xứ" (tùy chọn)
        // Khong dung rowSpan cho cot 1: autoTable khong the ngat trang giua mot rowSpan
        // nen se day nguyen khoi sang trang sau, de trong trang truoc. Thay vao do,
        // gia lap o gop bang cach chi ghi label o dong dau va an vien ngang noi bo cot 1.
        const itemCount = data.items?.length || 1;
        const mergedCol0Border = (isFirst, isLast) => ({
            lineWidth: {
                top: isFirst ? 0.5 : 0,
                bottom: isLast ? 0.5 : 0,
                left: 0.5,
                right: 0.5,
            },
        });
        const productHeaderRow = [
            {
                content: 'Mã hiệu sản phẩm:',
                styles: { fontStyle: 'bold', ...mergedCol0Border(true, false) },
            },
            { content: 'Mã hàng', styles: { fontStyle: 'bold', halign: 'center' } },
            { content: 'Số lượng', styles: { fontStyle: 'bold', halign: 'center' } },
        ];
        if (showOrigin) {
            productHeaderRow.push({
                content: 'Hãng - Xuất xứ',
                styles: { fontStyle: 'bold', halign: 'center' },
            });
        }
        tableBody.push(productHeaderRow);

        // Row 4+: Items
        if (data.items && data.items.length > 0) {
            data.items.forEach((item, itemIndex) => {
                const itemRow = [
                    {
                        content: '',
                        styles: mergedCol0Border(false, itemIndex === itemCount - 1),
                    },
                    {
                        content: item.designation || '',
                        styles: { textColor: [0, 0, 0], halign: 'center' },
                    },
                    {
                        content: item.quantity ? Number(item.quantity) : '',
                        styles: { textColor: [0, 0, 0], halign: 'center' },
                    },
                ];
                if (showOrigin) {
                    itemRow.push({
                        content: item.origin || '',
                        styles: { textColor: [0, 0, 0], halign: 'center' },
                    });
                }
                tableBody.push(itemRow);
            });
        } else {
            const fallbackRow = [
                { content: '', styles: mergedCol0Border(false, true) },
                { content: 'Mã hàng theo biên bản', styles: { textColor: [0, 0, 0] } },
                {
                    content: 'Số lượng theo biên bản',
                    styles: { textColor: [0, 0, 0], halign: 'center' },
                },
            ];
            if (showOrigin) {
                fallbackRow.push({
                    content: '',
                    styles: { textColor: [0, 0, 0], halign: 'center' },
                });
            }
            tableBody.push(fallbackRow);
        }

        // Row 5: Handover Date
        tableBody.push([
            { content: 'Ngày bàn giao sản phẩm:', styles: { fontStyle: 'bold' } },
            {
                content: data.deliveryDate || 'Thường là ngày biên bản',
                colSpan: contentColSpan,
                styles: { textColor: [0, 0, 0] },
            },
        ]);

        // Row 6: Warranty Period
        tableBody.push([
            { content: 'Thời gian bảo hành:', styles: { fontStyle: 'bold' } },
            { content: data.warrantyPeriod || '12 tháng', colSpan: contentColSpan },
        ]);

        // Row 7: Warranty Method
        // Split by newline to create separate rows with borders
        const methods = (data.warrantyMethod || '').split('\n').filter((s) => s.trim());
        if (methods.length > 0) {
            // First row with label rowspan
            tableBody.push([
                {
                    content: 'Phương thức bảo hành:',
                    styles: { fontStyle: 'bold', valign: 'middle' },
                    rowSpan: methods.length,
                },
                { content: methods[0], colSpan: contentColSpan },
            ]);
            // Subsequent rows
            for (let i = 1; i < methods.length; i++) {
                tableBody.push([{ content: methods[i], colSpan: contentColSpan }]);
            }
        } else {
            tableBody.push([
                { content: 'Phương thức bảo hành:', styles: { fontStyle: 'bold' } },
                { content: '', colSpan: contentColSpan },
            ]);
        }

        // Row 8: Check Warranty Location (Company Name)
        tableBody.push([
            { content: 'Địa điểm bảo hành:', styles: { fontStyle: 'bold' } },
            {
                content: data.warrantyLocation || config.name.toUpperCase(),
                colSpan: contentColSpan,
                styles: { fontStyle: 'bold', halign: 'center' },
            },
        ]);

        autoTable(doc, {
            startY: cursorY,
            body: tableBody,
            theme: 'grid',
            margin: { left: margin, right: margin },
            styles: {
                font: 'Roboto',
                fontSize: 11,
                lineColor: [0, 0, 0],
                lineWidth: 0.5,
                cellPadding: 6,
                textColor: [0, 0, 0],
                fillColor: false,
            },
            alternateRowStyles: {
                fillColor: false,
            },
            bodyStyles: {
                fillColor: false,
            },
            columnStyles: showOrigin
                ? {
                    0: { cellWidth: 150 }, // First column width
                    2: { cellWidth: 70 }, // Số lượng
                    3: { cellWidth: 110 }, // Hãng - Xuất xứ
                    // 1 auto
                }
                : {
                    0: { cellWidth: 150 }, // First column width
                    // 1 and 2 auto
                },
        });

        cursorY = doc.lastAutoTable.finalY + 40;

        const footerBottomLimit = pageHeight - 40;
        if (cursorY + 20 > footerBottomLimit) {
            doc.addPage();
            cursorY = 80;
        }

        // Footer
        doc.setFont('Roboto', 'bold');
        doc.setFontSize(14);

        // User request: "chữ thì căn giữa như ảnh" implicitly keeping "chiếm 60% dòng"
        // Position: Right side block (60% width). Alignment: Center within that block.
        const footerWidth = pageWidth * 0.6;
        const footerCenterX = pageWidth - margin - footerWidth / 2;

        doc.text(config.name.toUpperCase(), footerCenterX, cursorY, {
            align: 'center',
            maxWidth: footerWidth,
        });

        const blob = doc.output('blob');
        if (download) {
            saveAs(blob, fileName || 'PhieuBaoHanh.pdf');
        }
        return blob;
    };

    return {
        generateWarrantyCardPdf,
    };
}
