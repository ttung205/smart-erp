import { loadPdfLibs } from '@/utils/lazyExportLibs';
import { saveAs } from 'file-saver';
import translations from '@/lang/sale.json';
import { formatNumber, formatDateForSubmit } from '@/utils';
import { resolveSaleItemSubTotalVnd } from '@/constants/orderItemColumns';
import { getCompanyConfig, getConfigValue, getBankAccounts } from '@/config/companyConfig';

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

const registerFont = async (doc, url, fileName, fontName, fontStyle) => {
    const fontBase64 = await getFontBase64(url);

    doc.addFileToVFS(fileName, fontBase64);
    doc.addFont(fileName, fontName, fontStyle);
};

const getImageFormatFromDataUrl = (dataUrl) => {
    if (typeof dataUrl !== 'string') {
        return 'PNG';
    }

    if (dataUrl.startsWith('data:image/jpeg') || dataUrl.startsWith('data:image/jpg')) {
        return 'JPEG';
    }

    if (dataUrl.startsWith('data:image/png')) {
        return 'PNG';
    }

    return 'PNG';
};

const loadImageData = async (url) => {
    const response = await fetch(url, { cache: 'no-store' });

    if (!response.ok) {
        throw new Error(`Không thể tải logo: ${response.status}`);
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

const getSaleSalerEmail = (order, companyConfig) => {
    const salerEmail =
        order?.saler?.email ||
        order?.created_by?.email ||
        order?.createdBy?.email ||
        order?.user?.email;

    if (typeof salerEmail === 'string' && salerEmail.trim() !== '') {
        return salerEmail.trim();
    }

    return companyConfig.email;
};

export function useSalePdfExport() {
    const generateOrderPdf = async (order, customer, contacts, items, columns, options = {}) => {
        const {
            language = 'vi',
            orientation = 'landscape',
            download = true,
            fileName,
            customTerms = {},
            companySlug = 'AC',
        } = options;

        // Get company config based on slug
        const companyConfig = getCompanyConfig(companySlug);
        const saleSalerEmail = getSaleSalerEmail(order, companyConfig);

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

        let customFontLoaded = false;

        try {
            await Promise.all([
                registerFont(doc, ROBOTO_REGULAR_URL, 'Roboto-Regular.ttf', 'Roboto', 'normal'),
                registerFont(doc, ROBOTO_BOLD_URL, 'Roboto-Bold.ttf', 'Roboto', 'bold'),
            ]);
            doc.setFont('Roboto', 'normal');
            customFontLoaded = true;
        } catch (error) {
            console.warn('Không thể tải font Roboto cho PDF, sử dụng font mặc định.', error);
            doc.setFont('helvetica', 'normal');
        }

        const baseFont = customFontLoaded ? 'Roboto' : 'helvetica';

        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        const margin = 40;
        const primaryBlue = [22, 119, 255];

        const t = (vi) => (language === 'en' ? translations[vi] || vi : vi);
        const orderNumber = order?.sale_number;

        let cursorY = margin;

        // Load logo from company config
        const logoUrl = new URL(companyConfig.logoUrl, window.location.origin).href;
        let logoData = null;

        try {
            logoData = await loadImageData(logoUrl);
        } catch (error) {
            console.warn('Không thể tải logo công ty cho PDF:', error.message);
        }

        // Load stamp/seal for landscape orientation from company config
        const stampUrl = new URL(companyConfig.stampUrl, window.location.origin).href;
        let stampData = null;

        if (orientation === 'landscape') {
            try {
                stampData = await loadImageData(stampUrl);
            } catch (error) {
                console.warn('Không thể tải ảnh dấu treo cho PDF:', error.message);
            }
        }

        // Load user signature
        const signatureUrlRaw =
            order?.saler?.signature_url ||
            order?.created_by?.signature_url ||
            order?.createdBy?.signature_url ||
            order?.user?.signature_url;
        let signatureData = null;
        if (signatureUrlRaw) {
            try {
                const signatureUrl = new URL(signatureUrlRaw, window.location.origin).href;
                signatureData = await loadImageData(signatureUrl);
            } catch (error) {
                console.warn('Không thể tải chữ ký cho PDF:', error.message);
            }
        }

        const headerTop = cursorY;
        const desiredLogoHeight = orientation === 'landscape' ? 90 : 80;
        const maxLogoWidth = orientation === 'landscape' ? 140 : 120;
        let logoWidth = 0;
        let logoHeight = 0;

        // Draw logo on left
        if (logoData?.dataUrl) {
            const aspectRatio = logoData.height ? logoData.width / logoData.height : 1;
            logoHeight = desiredLogoHeight;
            logoWidth = Math.min(maxLogoWidth, Math.max(48, logoHeight * aspectRatio));
            doc.addImage(
                logoData.dataUrl,
                getImageFormatFromDataUrl(logoData.dataUrl),
                margin,
                headerTop,
                logoWidth,
                logoHeight
            );
        }

        // Draw stamp on right (landscape only)
        if (orientation === 'landscape' && stampData?.dataUrl) {
            const stampWidth = 100;
            const stampHeight = 95;
            const stampX = pageWidth - margin - stampWidth;
            const stampY = headerTop;

            doc.addImage(
                stampData.dataUrl,
                getImageFormatFromDataUrl(stampData.dataUrl),
                stampX,
                stampY,
                stampWidth,
                stampHeight
            );
        }

        // Company info - aligned with logo vertically
        const companyInfoX = margin + logoWidth + (orientation === 'landscape' ? 25 : 20);
        let infoY = headerTop + (orientation === 'landscape' ? 26 : 23);

        // Title company - reduce font size for TS company in portrait mode (long name)
        let companyNameFontSize = orientation === 'landscape' ? 16 : 15;
        if (companySlug === 'TS' && orientation === 'portrait') {
            companyNameFontSize = 12; // Smaller font for TST's long company name in portrait
        }
        doc.setFont(baseFont, 'bold');
        doc.setFontSize(companyNameFontSize);
        doc.setTextColor(...primaryBlue);
        doc.text(getConfigValue(companyConfig, 'name', language), companyInfoX, infoY, {
            align: 'left',
            maxWidth: 999,
        });

        infoY += orientation === 'landscape' ? 15 : 14;

        // Address line - keep on one line with bold label
        doc.setFontSize(orientation === 'landscape' ? 10.5 : 10);
        const addressLabel = `${t('Địa chỉ:')} `;
        const addressValue = getConfigValue(companyConfig, 'address', language);

        doc.setFont(baseFont, 'bold');
        doc.setTextColor(80, 80, 80);
        const addressLabelWidth = doc.getTextWidth(addressLabel);

        doc.setFont(baseFont, 'bold');
        doc.setTextColor(80, 80, 80);
        doc.text(addressLabel, companyInfoX, infoY, { maxWidth: 999 });
        doc.setFont(baseFont, 'normal');
        doc.setTextColor(80, 80, 80);
        doc.text(addressValue, companyInfoX + addressLabelWidth, infoY, { maxWidth: 999 });

        infoY += orientation === 'landscape' ? 13 : 12;

        // Office address and MST - keep on one line with bold labels
        doc.setFontSize(orientation === 'landscape' ? 10.5 : 10);
        const officeLabel = `${t('Địa chỉ văn phòng:')} `;
        const officeValue = `${getConfigValue(companyConfig, 'officeAddress', language)} `;
        const separator = '| ';
        const mstLabel = `${t('MST:')} `;
        const mstValue = companyConfig.taxCode;

        doc.setFont(baseFont, 'bold');
        doc.setTextColor(80, 80, 80);
        const officeLabelWidth = doc.getTextWidth(officeLabel);
        doc.setFont(baseFont, 'normal');
        doc.setTextColor(80, 80, 80);
        const officeValueWidth = doc.getTextWidth(officeValue);
        const separatorWidth = doc.getTextWidth(separator);
        doc.setFont(baseFont, 'bold');
        doc.setTextColor(80, 80, 80);
        const mstLabelWidth = doc.getTextWidth(mstLabel);

        let officeStartX = companyInfoX;

        doc.setFont(baseFont, 'bold');
        doc.setTextColor(80, 80, 80);
        doc.text(officeLabel, officeStartX, infoY, { maxWidth: 999 });
        officeStartX += officeLabelWidth;

        doc.setFont(baseFont, 'normal');
        doc.setTextColor(80, 80, 80);
        doc.text(officeValue, officeStartX, infoY, { maxWidth: 999 });
        officeStartX += officeValueWidth;

        doc.text(separator, officeStartX, infoY, { maxWidth: 999 });
        officeStartX += separatorWidth;

        doc.setFont(baseFont, 'bold');
        doc.setTextColor(80, 80, 80);
        doc.text(mstLabel, officeStartX, infoY, { maxWidth: 999 });
        officeStartX += mstLabelWidth;

        doc.setFont(baseFont, 'normal');
        doc.setTextColor(80, 80, 80);
        doc.text(mstValue, officeStartX, infoY, { maxWidth: 999 });

        infoY += orientation === 'landscape' ? 13 : 12;

        // Contact info line 1: Tel and Hotline
        doc.setFontSize(orientation === 'landscape' ? 10.5 : 10);
        const telLabel = `${t('Tel:')} `;
        const telValue = `${companyConfig.tel} `;
        const sep1 = '| ';
        const hotlineLabel = `${t('Hotline:')} `;
        const hotlineValue = companyConfig.hotline;

        // Calculate widths for line 1
        doc.setFont(baseFont, 'bold');
        doc.setTextColor(80, 80, 80);
        const telLabelWidth = doc.getTextWidth(telLabel);
        const hotlineLabelWidth = doc.getTextWidth(hotlineLabel);

        doc.setFont(baseFont, 'normal');
        doc.setTextColor(80, 80, 80);
        const telValueWidth = doc.getTextWidth(telValue);
        const sep1Width = doc.getTextWidth(sep1);

        let contactStartX = companyInfoX;

        // Render Tel
        doc.setFont(baseFont, 'bold');
        doc.setTextColor(80, 80, 80);
        doc.text(telLabel, contactStartX, infoY, { maxWidth: 999 });
        contactStartX += telLabelWidth;
        doc.setFont(baseFont, 'normal');
        doc.setTextColor(80, 80, 80);
        doc.text(telValue, contactStartX, infoY, { maxWidth: 999 });
        contactStartX += telValueWidth;

        // Separator
        doc.text(sep1, contactStartX, infoY, { maxWidth: 999 });
        contactStartX += sep1Width;

        // Render Hotline
        doc.setFont(baseFont, 'bold');
        doc.setTextColor(80, 80, 80);
        doc.text(hotlineLabel, contactStartX, infoY, { maxWidth: 999 });
        contactStartX += hotlineLabelWidth;
        doc.setFont(baseFont, 'normal');
        doc.setTextColor(80, 80, 80);
        doc.text(hotlineValue, contactStartX, infoY, { maxWidth: 999 });

        infoY += orientation === 'landscape' ? 13 : 12;

        // Contact info line 2: Email and Website
        const emailLabel = `${t('Email:')} `;
        const emailValue = `${saleSalerEmail} `;
        const sep2 = '| ';
        const websiteLabel = `${t('Website:')} `;
        const websiteValue = companyConfig.website;

        // Calculate widths for line 2
        doc.setFont(baseFont, 'bold');
        doc.setTextColor(80, 80, 80);
        const emailLabelWidth = doc.getTextWidth(emailLabel);
        const websiteLabelWidth = doc.getTextWidth(websiteLabel);

        doc.setFont(baseFont, 'normal');
        doc.setTextColor(80, 80, 80);
        const emailValueWidth = doc.getTextWidth(emailValue);
        const sep2Width = doc.getTextWidth(sep2);

        let emailWebsiteStartX = companyInfoX;

        // Render Email
        doc.setFont(baseFont, 'bold');
        doc.setTextColor(80, 80, 80);
        doc.text(emailLabel, emailWebsiteStartX, infoY, { maxWidth: 999 });
        emailWebsiteStartX += emailLabelWidth;
        doc.setFont(baseFont, 'normal');
        doc.setTextColor(80, 80, 80);
        doc.text(emailValue, emailWebsiteStartX, infoY, { maxWidth: 999 });
        emailWebsiteStartX += emailValueWidth;

        // Separator
        doc.text(sep2, emailWebsiteStartX, infoY, { maxWidth: 999 });
        emailWebsiteStartX += sep2Width;

        // Render Website
        doc.setFont(baseFont, 'bold');
        doc.setTextColor(80, 80, 80);
        doc.text(websiteLabel, emailWebsiteStartX, infoY, { maxWidth: 999 });
        emailWebsiteStartX += websiteLabelWidth;
        doc.setFont(baseFont, 'normal');
        doc.setTextColor(...primaryBlue);
        doc.text(websiteValue, emailWebsiteStartX, infoY, { maxWidth: 999 });

        doc.setTextColor(0, 0, 0);
        cursorY = headerTop + logoHeight + (orientation === 'landscape' ? 22 : 20);

        const ensureSpace = (height = 0) => {
            if (cursorY + height > pageHeight - margin) {
                doc.addPage();
                doc.setFont(baseFont, 'normal');
                doc.setFontSize(10);
                cursorY = margin;
            }
        };

        const addParagraph = (
            text,
            { size = 9, bold = false, color = [0, 0, 0], lineHeight = 1.45 } = {}
        ) => {
            if (!text) {
                return;
            }
            const availableWidth = pageWidth - margin * 2;
            const lines = Array.isArray(text) ? text : doc.splitTextToSize(text, availableWidth);
            const blockHeight = lines.length * size * lineHeight;
            ensureSpace(blockHeight);
            doc.setFont(baseFont, bold ? 'bold' : 'normal');
            doc.setFontSize(size);
            doc.setTextColor(...color);
            doc.text(lines, margin, cursorY, { lineHeightFactor: lineHeight });
            cursorY += blockHeight;
            doc.setTextColor(0, 0, 0);
        };

        // Add spacing after header
        cursorY += orientation === 'landscape' ? 18 : 15;

        // ĐƠN ĐẶT HÀNG title - centered
        ensureSpace(50);
        doc.setFont(baseFont, 'bold');
        doc.setFontSize(orientation === 'landscape' ? 22 : 20);
        doc.setTextColor(...primaryBlue);
        doc.text(t('ĐƠN ĐẶT HÀNG'), pageWidth / 2, cursorY, { align: 'center' });

        cursorY += orientation === 'landscape' ? 22 : 20;

        const pickFirst = (...values) => {
            for (const value of values) {
                if (value === undefined || value === null) {
                    continue;
                }

                const stringValue = String(value).trim();
                if (stringValue !== '') {
                    return value;
                }
            }

            return '';
        };

        const primaryContact =
            Array.isArray(contacts) && contacts.length
                ? (contacts.find((contact) => contact?.email || contact?.phone) ?? contacts[0])
                : null;

        const recipientDetails = {
            company: pickFirst(customer?.company, customer?.name),
            recipient: pickFirst(customer?.recipient, customer?.contact_name, primaryContact?.name),
            phone: pickFirst(
                customer?.phone,
                customer?.contact_phone,
                primaryContact?.phone,
                customer?.mobile,
                primaryContact?.mobile
            ),
            email: pickFirst(customer?.email, customer?.contact_email, primaryContact?.email),
            address: pickFirst(
                customer?.address,
                customer?.company_address,
                customer?.customer_address
            ),
        };

        // Customer info section - in a box
        ensureSpace(80);

        const boxStartY = cursorY;
        const boxPadding = 8;

        // Calculate box height dynamically based on all fields
        doc.setFontSize(9);

        // Define two-column layout
        const boxInnerWidth = pageWidth - margin * 2 - boxPadding * 2;
        const leftColumnWidth = boxInnerWidth * 0.7; // 70% for left column
        const rightColumnWidth = boxInnerWidth * 0.3; // 30% for right column
        const columnGap = 10; // Gap between columns

        // Pre-calculate all field widths and wrapped lines for LEFT COLUMN
        doc.setFont(baseFont, 'bold');
        const kinhGuiText = `${t('Kính gửi:')} `;
        const kinhGuiWidth = doc.getTextWidth(kinhGuiText);

        const nguoiNhanText = `${t('Người nhận:')} `;
        const nguoiNhanWidth = doc.getTextWidth(nguoiNhanText);
        const soDienThoaiText = `${t('Điện thoại:')} `;
        const soDienThoaiWidth = doc.getTextWidth(soDienThoaiText);
        const emailText = `${t('Email:')} `;
        const emailWidth = doc.getTextWidth(emailText);
        const diaChiText = `${t('Địa chỉ:')} `;
        const diaChiWidth = doc.getTextWidth(diaChiText);

        // Find max label width in LEFT COLUMN to align all values
        const maxLeftLabelWidth = Math.max(
            kinhGuiWidth,
            nguoiNhanWidth,
            soDienThoaiWidth,
            emailWidth,
            diaChiWidth
        );

        // Available widths for LEFT COLUMN content (all use same max width)
        const companyAvailableWidth = leftColumnWidth - maxLeftLabelWidth;
        const recipientAvailableWidth = leftColumnWidth - maxLeftLabelWidth;
        const phoneAvailableWidth = leftColumnWidth - maxLeftLabelWidth;
        const emailAvailableWidth = leftColumnWidth - maxLeftLabelWidth;
        const addressAvailableWidth = leftColumnWidth - maxLeftLabelWidth;

        doc.setFont(baseFont, 'normal');
        const companyLines = doc.splitTextToSize(
            recipientDetails.company || '',
            companyAvailableWidth
        );
        const recipientLines = doc.splitTextToSize(
            recipientDetails.recipient || '',
            recipientAvailableWidth
        );
        const phoneLines = doc.splitTextToSize(recipientDetails.phone || '', phoneAvailableWidth);
        const emailLines = doc.splitTextToSize(recipientDetails.email || '', emailAvailableWidth);
        const addressLines = doc.splitTextToSize(
            recipientDetails.address || '',
            addressAvailableWidth
        );

        // Pre-calculate field widths and wrapped lines for RIGHT COLUMN
        doc.setFont(baseFont, 'bold');
        const ngayDatHangText = `${t('Ngày đặt hàng:')} `;
        const ngayDatHangWidth = doc.getTextWidth(ngayDatHangText);
        const soDonHangText = `${t('Số đơn hàng:')} `;
        const soDonHangWidth = doc.getTextWidth(soDonHangText);

        const dateAvailableWidth = rightColumnWidth - ngayDatHangWidth;
        const orderNumberAvailableWidth = rightColumnWidth - soDonHangWidth;

        const orderDate = order?.order_date
            ? new Date(order.order_date).toLocaleDateString('vi-VN', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            })
            : new Date().toLocaleDateString('vi-VN', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            });
        doc.setFont(baseFont, 'normal');
        const dateLines = doc.splitTextToSize(orderDate, dateAvailableWidth);
        const orderNumberLines = doc.splitTextToSize(orderNumber || '', orderNumberAvailableWidth);

        // Calculate total height: max of left and right columns
        const lineSpacing = 9; // mm per line

        // Left column height calculation
        const kinhGuiHeight = companyLines.length * lineSpacing + lineSpacing;
        const recipientHeight = recipientLines.length * lineSpacing + lineSpacing;
        const phoneHeight = phoneLines.length * lineSpacing + lineSpacing;
        const emailHeight = emailLines.length * lineSpacing + lineSpacing;
        const addressHeight = addressLines.length * lineSpacing;

        const leftColumnHeight =
            kinhGuiHeight + recipientHeight + phoneHeight + emailHeight + addressHeight;

        // Right column height calculation
        const dateHeight = dateLines.length * lineSpacing + lineSpacing;
        const orderNumberHeight = orderNumberLines.length * lineSpacing;

        const rightColumnHeight = dateHeight + orderNumberHeight;

        // Box height: padding + baseline offset + content + padding
        const boxHeight = boxPadding + Math.max(leftColumnHeight, rightColumnHeight) + boxPadding;

        // Draw box border
        doc.setDrawColor(200, 200, 200);
        doc.setLineWidth(0.5);
        doc.rect(margin, boxStartY, pageWidth - margin * 2, boxHeight);

        // Content inside box - TWO COLUMN LAYOUT
        doc.setFontSize(9);
        doc.setTextColor(0, 0, 0);

        // Define column positions
        const leftColumnX = margin + boxPadding;
        const rightColumnX = margin + boxPadding + leftColumnWidth + columnGap;

        // Render LEFT COLUMN
        let leftY = boxStartY + boxPadding + 6;

        // Kính gửi
        doc.setFont(baseFont, 'bold');
        doc.text(kinhGuiText, leftColumnX, leftY);
        doc.setFont(baseFont, 'normal');
        doc.text(companyLines[0] || '', leftColumnX + maxLeftLabelWidth, leftY);
        leftY += 9;

        // Remaining customer name lines (indented)
        if (companyLines.length > 1) {
            for (let i = 1; i < companyLines.length; i++) {
                doc.text(companyLines[i], leftColumnX + maxLeftLabelWidth, leftY);
                leftY += 9;
            }
        }
        leftY += 9; // Space after Kính gửi field

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
        leftY += 9; // Space after Người nhận field

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
        leftY += 9; // Space after Số điện thoại field

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
        leftY += 9; // Space after Email field

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

        // Render RIGHT COLUMN
        let rightY = boxStartY + boxPadding + 6;

        // Ngày đặt hàng
        doc.setFont(baseFont, 'bold');
        doc.setTextColor(0, 0, 0);
        doc.text(ngayDatHangText, rightColumnX, rightY);
        doc.setFont(baseFont, 'normal');
        doc.setTextColor(255, 0, 0);
        doc.text(dateLines[0] || '', rightColumnX + ngayDatHangWidth, rightY);
        rightY += 9;

        if (dateLines.length > 1) {
            for (let i = 1; i < dateLines.length; i++) {
                doc.text(dateLines[i], rightColumnX + ngayDatHangWidth, rightY);
                rightY += 9;
            }
        }
        rightY += 9; // Space after Ngày đặt hàng field

        // Số đơn hàng
        doc.setFont(baseFont, 'bold');
        doc.setTextColor(0, 0, 0);
        doc.text(soDonHangText, rightColumnX, rightY);
        doc.setFont(baseFont, 'normal');
        doc.setTextColor(255, 0, 0);
        doc.text(orderNumberLines[0] || '', rightColumnX + soDonHangWidth, rightY);
        rightY += 9;

        if (orderNumberLines.length > 1) {
            for (let i = 1; i < orderNumberLines.length; i++) {
                doc.text(orderNumberLines[i], rightColumnX + soDonHangWidth, rightY);
                rightY += 9;
            }
        }

        // Reset text color
        doc.setTextColor(0, 0, 0);

        cursorY = boxStartY + boxHeight + 15;

        // Disclaimer paragraph
        const companyName = getConfigValue(companyConfig, 'name', language);
        const disclaimer = t(
            `${companyName} gửi lời cảm ơn sự tin tưởng và luôn đồng hành của Quý Khách hàng đối với Công Ty Chúng Tôi. Chúng tôi xin gửi tới Quý Công Ty đơn đặt hàng với các thông tin chi tiết như sau:`
        );
        addParagraph(disclaimer, { size: 9 });

        cursorY += 15;

        const headers = [language === 'en' ? 'No.' : t('STT'), ...columns.map((col) => col.header)];
        const tableRows = items.map((item, index) => {
            const row = [String(index + 1)];
            columns.forEach((col) => {
                let value = item[col.field];

                if (col.field === 'sub_total') {
                    value = resolveSaleItemSubTotalVnd(item, order);
                }

                // Use column's format function if available, otherwise use default formatting
                if (col.format && typeof col.format === 'function') {
                    // Pass context including sale data for columns that need it (currency, exchange_rate, etc.)
                    const context = {
                        sale: order,
                    };
                    value = col.format(value, context);
                } else if (col.field === 'vat') {
                    // Special handling for VAT column to ensure 2 decimal places
                    const numeric = parseFloat(value);
                    if (!Number.isNaN(numeric)) {
                        value = numeric.toFixed(2);
                    }
                } else if (
                    (col.type === 'currency' || col.type === 'number') &&
                    value !== undefined &&
                    value !== null &&
                    value !== ''
                ) {
                    // If value is already a number, use it directly; otherwise parse it
                    const numeric =
                        typeof value === 'number'
                            ? value
                            : parseFloat(String(value).replace(/[^0-9.-]/g, ''));
                    if (!Number.isNaN(numeric)) {
                        // Use formatNumber for both currency and number to match preview formatting
                        if (col.type === 'currency') {
                            value = formatNumber(numeric, -2); // Round to nearest 100 for VND currency
                        } else {
                            value = formatNumber(numeric, col.round || 0);
                        }
                    }
                }

                row.push(value == null ? '' : String(value));
            });
            return row;
        });

        ensureSpace(40);

        // Auto-scale settings based on column count and orientation
        const columnCount = columns.length + 1; // +1 for row number column
        const isPortrait = normalizedOrientation === 'portrait';

        // Calculate dynamic settings
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
            },
            headStyles: {
                fillColor: [226, 234, 246],
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
                    const isNumeric = col.type === 'currency' || col.type === 'number';
                    const shouldAlignRight =
                        isNumeric || col.type === 'calculated' || col.field === 'lead_time_days';
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
                { 0: { cellWidth: rowNumberWidth, halign: 'center', minCellHeight: minCellHeight } }
            ),
        });

        cursorY = doc.lastAutoTable ? doc.lastAutoTable.finalY : cursorY + 120;

        let subtotal = 0;
        let vatTotal = 0;

        items.forEach((item) => {
            const vat = item.vat != null ? parseFloat(item.vat) || 0 : 0;
            const currency = (order?.currency || item.currency || 'VND').toUpperCase();
            const itemSubtotal = resolveSaleItemSubTotalVnd(item, order);

            let itemVat = 0;
            if (currency === 'VND') {
                itemVat = itemSubtotal * (vat / 100);
            }

            subtotal += itemSubtotal;
            vatTotal += itemVat;
        });

        const grandTotal = subtotal + vatTotal;

        // Format functions for display
        const formatSubtotal = (value) => formatNumber(value, 0);
        const formatVat = (value) => formatNumber(value, 0);
        const formatTotal = (value) => formatNumber(value, 0);

        // Get the number of columns from the main table to calculate empty cells
        const mainTableColumns = columns.length + 1; // +1 for STT column
        const summaryLabelCols = mainTableColumns - 2; // Number of empty cells before label

        // Create rows with empty cells at the start, then label and value
        const createSummaryRow = (label, value) => {
            const row = new Array(summaryLabelCols).fill('');
            row.push(label, value);
            return row;
        };

        // Summary table - full width but content only in last 2 columns
        doc.autoTable({
            startY: cursorY,
            margin: { left: margin, right: margin },
            body: [
                createSummaryRow(t('TẠM TÍNH:'), `${formatSubtotal(subtotal)} VND`),
                createSummaryRow(t('TỔNG VAT:'), `${formatVat(vatTotal)} VND`),
                createSummaryRow(t('TỔNG THÀNH TIỀN:'), `${formatTotal(grandTotal)} VND`),
            ],
            theme: 'grid',
            styles: {
                font: 'Roboto',
                fontSize: 9,
                cellPadding: 3,
                lineColor: [210, 210, 210],
                lineWidth: 0.5,
            },
            columnStyles: {
                // Empty cells - only top and bottom borders
                ...Array.from({ length: summaryLabelCols }).reduce((acc, _, i) => {
                    acc[i] = {
                        cellWidth: 'auto',
                        lineWidth: {
                            top: 0.5,
                            right: 0,
                            bottom: 0.5,
                            left: i === 0 ? 0.5 : 0,
                        },
                    };
                    return acc;
                }, {}),
                // Label column (second to last)
                [summaryLabelCols]: {
                    halign: 'right',
                    fontStyle: 'bold',
                    cellWidth: 'auto',
                    lineWidth: { top: 0.5, right: 0.5, bottom: 0.5, left: 0 },
                },
                // Value column (last)
                [summaryLabelCols + 1]: {
                    halign: 'right',
                    cellWidth: 'auto',
                    lineWidth: { top: 0.5, right: 0.5, bottom: 0.5, left: 0.5 },
                },
            },
            bodyStyles: {
                textColor: [0, 0, 0],
            },
            didParseCell: function (data) {
                // Make last row (grand total) bold and slightly larger
                if (data.row.index === 2 && data.column.index >= summaryLabelCols) {
                    data.cell.styles.fontStyle = 'bold';
                    data.cell.styles.fontSize = 10;
                }
            },
        });

        cursorY = doc.lastAutoTable.finalY + 18;

        // Responsive column width based on orientation
        const notesColumnGap = orientation === 'portrait' ? 20 : 40;
        const availableWidth = pageWidth - margin * 2;
        const notesColumnWidth = (availableWidth - notesColumnGap) / 2;

        const notesLeftX = margin + 10;
        const notesRightX = margin + notesColumnWidth + notesColumnGap;
        const bulletSize = 1.5;
        const notesFontSize = 9.5;
        const lineHeight = 12.5;
        const maxWidth = notesColumnWidth - 20;

        // Helper function to normalize old default note to new wording
        const normalizeNoteValue = (value) => {
            if (!value || typeof value !== 'string') return value;
            const trimmed = value.trim();
            if (
                trimmed.includes('Kiểm tra trước khi xác nhận đặt hàng') ||
                trimmed.includes('không nhận đổi trả lại hàng hóa') ||
                trimmed.includes('Kiểm tra mô tả, ký hiệu trước khi xác nhận đặt hàng')
            ) {
                return `Kiểm tra mô tả, ký hiệu hàng hóa trước khi xác nhận đặt hàng. Hàng hóa khi đã giao nhận, ${companyConfig.shortName || 'Á Châu'} không đổi trả lại hàng hóa.`;
            }
            return trimmed;
        };

        // Helper function to ensure text ends with a period
        const ensureTrailingPeriod = (text) => {
            if (!text || typeof text !== 'string') return text;
            const trimmed = text.trim();
            if (!trimmed) return trimmed;
            if (!/[.!?:]$/.test(trimmed)) {
                return trimmed + '.';
            }
            return trimmed;
        };

        // Helper function to replace any company short name with current company's short name
        const replaceCompanyShortName = (text) => {
            if (!text || typeof text !== 'string') return text;
            const currentShortName = companyConfig.shortName || 'Á Châu';
            // Replace all known company short names (with optional 'Việt Nam') with current one
            return text.replace(/(Á Châu|TST|LDT)(?: Việt Nam)?/g, currentShortName);
        };

        // Smart text wrapping helper that uses the full column width on lines 2+
        const wrapNoteText = (label, text, colMaxWidth, fontSize = 9.5) => {
            doc.setFont(baseFont, 'bold');
            doc.setFontSize(fontSize);
            const labelWidth = doc.getTextWidth(label);

            const firstLineWidth = Math.max(colMaxWidth - labelWidth - 3, 40);
            const subsequentLineWidth = colMaxWidth;

            doc.setFont(baseFont, 'normal');
            doc.setFontSize(fontSize);

            const trimmed = text ? text.trim() : '';
            if (!trimmed) {
                return { labelWidth, lines: [] };
            }

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
                            subLines.slice(0, -1).forEach((sl) => lines.push(sl));
                            currentLine = subLines[subLines.length - 1];
                        } else {
                            currentLine = word;
                        }
                    } else {
                        const subLines = doc.splitTextToSize(word, allowedWidth);
                        subLines.slice(0, -1).forEach((sl) => lines.push(sl));
                        currentLine = subLines[subLines.length - 1];
                        isFirst = false;
                    }
                }
            }
            if (currentLine) {
                lines.push(currentLine);
            }

            return { labelWidth, lines };
        };

        // Use custom terms if provided, otherwise use defaults
        const defaultLeftTerms = [
            { label: 'Địa điểm giao hàng:', value: 'Tại Kho Quý Công ty.' },
            { label: 'Thời gian giao hàng:', value: 'Theo thỏa thuận, tính từ thời điểm đặt hàng.' },
            { label: 'Đơn hàng có giá trị:', value: '30 ngày.' },
            {
                label: 'Lưu ý:',
                value: `Kiểm tra mô tả, ký hiệu hàng hóa trước khi xác nhận đặt hàng. Hàng hóa khi đã giao nhận, ${companyConfig.shortName || 'Á Châu'} không đổi trả lại hàng hóa.`,
            },
            {
                label: 'Chất lượng:',
                value: 'Hàng mới 100%, Chất lượng hàng hóa và Bảo hành 12 tháng theo tiêu chuẩn nhà sản xuất.',
            },
            {
                label: 'Cam kết:',
                value: 'Chúng tôi cam kết rằng các sản phẩm chúng tôi cung cấp là sản phẩm chính hãng 100%. Nếu bị phát hiện là hàng giả, hàng nhái, chúng tôi sẽ bồi thường gấp 10 lần giá trị hàng hóa bị phát hiện.',
            },
        ];

        const defaultRightTerms = [
            { label: 'Thanh toán:', value: '100% giá trị đơn hàng khi xác nhận đặt hàng.' },
            { label: 'Phương thức thanh toán:', value: 'Bằng tiền mặt hoặc chuyển khoản.' },
        ];

        // Check if customTerms uses new array format or old object format
        let leftTermsArray, rightTermsArray;

        if (customTerms?.leftTerms && customTerms?.rightTerms) {
            // New array format - replace company names in values
            leftTermsArray = customTerms.leftTerms.map((term) => ({
                ...term,
                value: replaceCompanyShortName(term.value),
            }));
            rightTermsArray = customTerms.rightTerms.map((term) => ({
                ...term,
                value: replaceCompanyShortName(term.value),
            }));
        } else if (
            customTerms &&
            typeof customTerms === 'object' &&
            Object.keys(customTerms).length > 0
        ) {
            // Old object format - convert to array format for backward compatibility
            const defaultNote = `Kiểm tra mô tả, ký hiệu hàng hóa trước khi xác nhận đặt hàng. Hàng hóa khi đã giao nhận, ${companyConfig.shortName || 'Á Châu'} không đổi trả lại hàng hóa.`;
            // Always use replaceCompanyShortName for note field, fallback to defaultNote if empty
            const noteValue = customTerms.note
                ? replaceCompanyShortName(customTerms.note)
                : defaultNote;
            leftTermsArray = [
                {
                    label: customTerms.deliveryLocationLabel || 'Địa điểm giao hàng:',
                    value: customTerms.deliveryLocation || 'Tại Kho Quý Công ty.',
                },
                {
                    label: customTerms.deliveryTimeLabel || 'Thời gian giao hàng:',
                    value:
                        customTerms.deliveryTime || 'Theo thỏa thuận, tính từ thời điểm đặt hàng.',
                },
                {
                    label: customTerms.validityPeriodLabel || 'Đơn hàng có giá trị:',
                    value: customTerms.validityPeriod || '30 ngày.',
                },
                { label: customTerms.noteLabel || 'Lưu ý:', value: noteValue },
                {
                    label: customTerms.qualityLabel || 'Chất lượng:',
                    value: customTerms.quality || '',
                },
                {
                    label: customTerms.commitmentLabel || 'Cam kết:',
                    value: customTerms.commitment || '',
                },
            ].filter((item) => item.label && item.value);

            rightTermsArray = [
                {
                    label: customTerms.paymentLabel || 'Thanh toán:',
                    value: customTerms.payment || '',
                },
                {
                    label: customTerms.paymentMethodLabel || 'Phương thức thanh toán:',
                    value: customTerms.paymentMethod || '',
                },
            ].filter((item) => item.label && item.value);
        } else {
            // No custom terms, use defaults
            leftTermsArray = defaultLeftTerms;
            rightTermsArray = defaultRightTerms;
        }

        // Left column notes - map to the format expected by rendering code
        const leftNotes = leftTermsArray
            .filter((term) => term.label && term.value)
            .map((term) => ({
                label: t(term.label),
                text: ensureTrailingPeriod(t(normalizeNoteValue(term.value))),
            }));

        // Right column notes - map to the format expected by rendering code
        const rightNotes = rightTermsArray
            .filter((term) => term.label && term.value)
            .map((term) => ({
                label: t(term.label),
                text: ensureTrailingPeriod(t(normalizeNoteValue(term.value))),
            }));

        // Add bank transfer info if we have payment terms
        const showBankInfo = rightNotes.length > 0;
        const preparedBankLines = [];
        if (showBankInfo) {
            rightNotes.push({ label: t('Thông tin chuyển khoản:'), text: '', isBankHeader: true });

            doc.setFont(baseFont, 'normal');
            doc.setFontSize(notesFontSize);
            const beneficiary = getConfigValue(companyConfig, 'bankBeneficiary', language);
            const bankAccounts = getBankAccounts(companyConfig, language);
            const rawBankLines = [];
            if (beneficiary) {
                rawBankLines.push(`${t('Đơn vị nhận:')} ${beneficiary}`);
            }
            bankAccounts.forEach((bankAccount) => {
                if (bankAccount.accountNumber) {
                    rawBankLines.push(`${t('Số tài khoản:')} ${bankAccount.accountNumber}`);
                }
                if (bankAccount.bankName) {
                    rawBankLines.push(bankAccount.bankName);
                }
            });
            rawBankLines.forEach((line) => {
                const wrapped = doc.splitTextToSize(line, maxWidth);
                wrapped.forEach((wl) => preparedBankLines.push(wl));
            });
        }

        // Pre-wrap left notes
        const preparedLeftNotes = leftNotes.map((term) => ({
            ...term,
            ...wrapNoteText(term.label, term.text, maxWidth, notesFontSize),
        }));

        // Pre-wrap right notes
        const preparedRightNotes = rightNotes.map((term) => {
            if (term.isBankHeader) {
                doc.setFont(baseFont, 'bold');
                doc.setFontSize(notesFontSize);
                return {
                    ...term,
                    labelWidth: doc.getTextWidth(term.label),
                    lines: [],
                };
            }
            return {
                ...term,
                ...wrapNoteText(term.label, term.text, maxWidth, notesFontSize),
            };
        });

        // Calculate dynamic height for notes section
        const leftTotalLines = preparedLeftNotes.reduce(
            (sum, n) => sum + Math.max(n.lines.length, 1),
            0
        );
        let rightTotalLines = 0;
        preparedRightNotes.forEach((n) => {
            if (n.isBankHeader) {
                rightTotalLines += 1 + preparedBankLines.length;
            } else {
                rightTotalLines += Math.max(n.lines.length, 1);
            }
        });

        const maxContentLines = Math.max(leftTotalLines, rightTotalLines);
        const requiredNotesHeight = 18 + maxContentLines * lineHeight + 10;
        const requiredSignatureHeight = 90;

        // Ensure notes and signatures do not get cut off at page bottom
        if (
            cursorY + requiredNotesHeight > pageHeight - margin ||
            (cursorY + requiredNotesHeight + requiredSignatureHeight > pageHeight - margin &&
                cursorY > pageHeight / 2)
        ) {
            doc.addPage();
            doc.setFont(baseFont, 'normal');
            doc.setFontSize(notesFontSize);
            cursorY = margin;
        }

        // Notes section
        const notesStartY = cursorY;

        // "GHI CHÚ:" title
        doc.setFont(baseFont, 'bold');
        doc.setFontSize(11);
        doc.setTextColor(...primaryBlue);
        doc.text(t('GHI CHÚ:'), margin + 10, notesStartY + 3);

        doc.setTextColor(0, 0, 0);
        let notesLeftY = notesStartY + 18;
        let notesRightY = notesStartY + 18;

        // Render left column notes
        preparedLeftNotes.forEach((note) => {
            // Draw bullet
            doc.setFillColor(0, 0, 0);
            doc.circle(notesLeftX + 2, notesLeftY - 2, bulletSize, 'F');

            // Draw label in bold
            doc.setFont(baseFont, 'bold');
            doc.setFontSize(notesFontSize);
            doc.text(note.label, notesLeftX + 8, notesLeftY);

            // Draw text
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
                note.lines.slice(1).forEach((line) => {
                    doc.text(line, notesLeftX + 8, notesLeftY);
                    notesLeftY += lineHeight;
                });
            }
        });

        // Render right column notes
        preparedRightNotes.forEach((note) => {
            // Draw bullet
            doc.setFillColor(0, 0, 0);
            doc.circle(notesRightX + 2, notesRightY - 2, bulletSize, 'F');

            // Draw label in bold
            doc.setFont(baseFont, 'bold');
            doc.setFontSize(notesFontSize);
            doc.text(note.label, notesRightX + 8, notesRightY);

            if (note.isBankHeader) {
                notesRightY += lineHeight;
                doc.setFont(baseFont, 'normal');
                doc.setFontSize(notesFontSize);
                preparedBankLines.forEach((line) => {
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
                note.lines.slice(1).forEach((line) => {
                    doc.text(line, notesRightX + 8, notesRightY);
                    notesRightY += lineHeight;
                });
            }
        });

        cursorY = Math.max(notesLeftY, notesRightY) + 10;

        // =====================
        // SIGNATURE SECTION
        // =====================
        ensureSpace(80);
        cursorY += 20;

        const signatureBoxWidth = (pageWidth - margin * 2 - 40) / 2; // Two columns with gap
        const signatureBoxHeight = 60;
        const signatureLeftX = margin;
        const signatureRightX = pageWidth - margin - signatureBoxWidth;

        // Left signature box - Xác nhận đơn hàng
        doc.setFont(baseFont, 'bold');
        doc.setFontSize(10);
        doc.setTextColor(0, 0, 0);
        doc.text(t('XÁC NHẬN ĐƠN HÀNG'), signatureLeftX + signatureBoxWidth / 2, cursorY, {
            align: 'center',
        });

        // Right signature box - ĐẠI DIỆN BÊN BÁN HÀNG
        doc.setFont(baseFont, 'bold');
        doc.setFontSize(10);
        doc.setTextColor(0, 0, 0);
        doc.text(t('ĐẠI DIỆN BÊN BÁN HÀNG'), signatureRightX + signatureBoxWidth / 2, cursorY, {
            align: 'center',
        });

        if (signatureData?.dataUrl) {
            const aspectRatio = signatureData.height
                ? signatureData.width / signatureData.height
                : 1.2;
            const signatureImgHeight = 70;
            const signatureImgWidth = signatureImgHeight * aspectRatio;
            const sigX = signatureRightX + signatureBoxWidth / 2 - signatureImgWidth / 2;
            const sigY = cursorY + 8;
            doc.addImage(
                signatureData.dataUrl,
                getImageFormatFromDataUrl(signatureData.dataUrl),
                sigX,
                sigY,
                signatureImgWidth,
                signatureImgHeight
            );
        }

        const salerName = pickFirst(
            order?.saler?.full_name,
            order?.saler?.name,
            order?.created_by?.full_name,
            order?.created_by?.name,
            order?.createdBy?.full_name,
            order?.createdBy?.name,
            order?.user?.full_name,
            order?.user?.name
        );
        const salerPhone = pickFirst(
            order?.saler?.phone,
            order?.created_by?.phone,
            order?.createdBy?.phone,
            order?.user?.phone
        );

        doc.setFont(baseFont, 'bold');
        doc.setFontSize(10);
        doc.setTextColor(...primaryBlue);

        if (salerName) {
            doc.text(salerName, signatureRightX + signatureBoxWidth / 2, cursorY + 90, {
                align: 'center',
            });
        }
        if (salerPhone) {
            doc.text(salerPhone, signatureRightX + signatureBoxWidth / 2, cursorY + 104, {
                align: 'center',
            });
        }

        cursorY += signatureBoxHeight + 50;

        const filename =
            fileName ||
            `Don_dat_hang_${orderNumber || 'preview'}_${formatDateForSubmit(new Date())}.pdf`;
        const blob = doc.output('blob');

        if (download) {
            saveAs(blob, filename);
        }

        return blob;
    };

    const generatePdfBlob = async (order, language = 'vi') => {
        return await generateOrderPdf(order, language, false);
    };

    return {
        generateSalePdf: generateOrderPdf,
        generatePdfBlob,
    };
}
