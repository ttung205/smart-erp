import { loadPdfLibs } from '@/utils/lazyExportLibs';
import { saveAs } from 'file-saver';
import translations from '@/lang/quotation.json';
import { formatNumber, formatDateForSubmit } from '@/utils';
import { getCompanyConfig, getConfigValue, getBankAccounts } from '@/config/companyConfig';
import { createDocumentQrDataUrl } from '@/utils/documentQr';

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

const getQuotationOwnerEmail = (quotation, companyConfig) => {
    const ownerEmail = quotation?.created_by?.email || quotation?.user?.email;

    if (typeof ownerEmail === 'string' && ownerEmail.trim() !== '') {
        return ownerEmail.trim();
    }

    return companyConfig.email;
};

export function useQuotationPdfExport() {
    const generateQuotationPdf = async (
        quotation,
        customer,
        contacts,
        items,
        columns,
        options = {}
    ) => {
        const {
            language = 'vi',
            orientation = 'landscape',
            download = true,
            fileName,
            companySlug = 'AC',
            currency = 'VND',
            exchangeRate = null,
        } = options;

        // Currency conversion helper
        const convertCurrency = (vndValue) => {
            if (currency !== 'VND' && exchangeRate && exchangeRate > 0) {
                return vndValue / exchangeRate;
            }
            return vndValue;
        };

        // Get decimal places based on currency
        const getCurrencyDecimals = () => (currency !== 'VND' ? 2 : -2);

        // Get company config based on slug
        const companyConfig = getCompanyConfig(companySlug);
        const quotationOwnerEmail = getQuotationOwnerEmail(quotation, companyConfig);

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
        const quotationNumber = quotation?.quotation_number;

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

        // Load transparent stamp for portrait orientation (top-left corner)
        let stampNoBgData = null;
        if (orientation === 'portrait' && companyConfig.stampNoBgUrl) {
            const stampNoBgUrl = new URL(companyConfig.stampNoBgUrl, window.location.origin).href;
            try {
                stampNoBgData = await loadImageData(stampNoBgUrl);
            } catch (error) {
                console.warn('Không thể tải ảnh dấu trong suốt cho PDF:', error.message);
            }
        }

        // Load user signature
        const signatureUrlRaw = quotation?.created_by?.signature_url;
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
            const stampWidth = 120;
            const stampHeight = 115;
            const stampX = pageWidth - margin - stampWidth;
            const stampY = headerTop + 8;

            doc.addImage(
                stampData.dataUrl,
                getImageFormatFromDataUrl(stampData.dataUrl),
                stampX,
                stampY,
                stampWidth,
                stampHeight
            );
        }

        // Draw transparent stamp on top-right corner (portrait only)
        if (orientation === 'portrait' && stampNoBgData?.dataUrl) {
            const stampWidth = 105;
            const stampHeight = 105;
            const stampX = pageWidth - margin - stampWidth + 10;
            const stampY = headerTop - 10;

            doc.addImage(
                stampNoBgData.dataUrl,
                getImageFormatFromDataUrl(stampNoBgData.dataUrl),
                stampX,
                stampY,
                stampWidth,
                stampHeight
            );
        }

        // Company info - aligned with logo vertically
        const companyInfoX = margin + logoWidth + (orientation === 'landscape' ? 10 : 8);
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

        // LD company tagline (portrait only)
        if (companySlug === 'LD' && orientation === 'portrait') {
            doc.setFont(baseFont, 'bold');
            doc.setFontSize(10);
            doc.setTextColor(80, 80, 80);
            doc.text(
                t('Đối tác tin cậy trong lĩnh vực thiết bị công nghiệp'),
                companyInfoX,
                infoY,
                {
                    maxWidth: 999,
                }
            );
            infoY += 12;
        }

        // Address line - keep on one line with bold label
        doc.setFontSize(orientation === 'landscape' ? 10.5 : 10);
        const addressLabel = `${t('Địa chỉ:')} `;
        const addressValue = getConfigValue(companyConfig, 'address', language);

        doc.setFont(baseFont, 'bold');
        doc.setTextColor(80, 80, 80); // Dark gray for label
        const addressLabelWidth = doc.getTextWidth(addressLabel);

        doc.setFont(baseFont, 'bold');
        doc.setTextColor(80, 80, 80);
        doc.text(addressLabel, companyInfoX, infoY, { maxWidth: 999 });
        doc.setFont(baseFont, 'normal');
        doc.setTextColor(80, 80, 80);
        doc.text(addressValue, companyInfoX + addressLabelWidth, infoY, { maxWidth: 999 });

        infoY += orientation === 'landscape' ? 13 : 12;

        // Office address - on its own line (skip for LD company in portrait only)
        if (companySlug !== 'LD' || orientation === 'landscape') {
            doc.setFontSize(orientation === 'landscape' ? 10.5 : 10);
            const officeLabel = `${t('Địa chỉ văn phòng:')} `;
            const officeValue = getConfigValue(companyConfig, 'officeAddress', language);

            doc.setFont(baseFont, 'bold');
            doc.setTextColor(80, 80, 80);
            const officeLabelWidth = doc.getTextWidth(officeLabel);

            doc.setFont(baseFont, 'bold');
            doc.setTextColor(80, 80, 80);
            doc.text(officeLabel, companyInfoX, infoY, { maxWidth: 999 });
            doc.setFont(baseFont, 'normal');
            doc.setTextColor(80, 80, 80);
            doc.text(officeValue, companyInfoX + officeLabelWidth, infoY, { maxWidth: 999 });

            infoY += orientation === 'landscape' ? 13 : 12;
        }

        // Contact info line: MST | Tel | Hotline
        doc.setFontSize(orientation === 'landscape' ? 10.5 : 10);
        const separator = '| ';
        const mstLabel = `${t('MST:')} `;
        const mstValue = `${companyConfig.taxCode} `;
        const telLabel = `${t('Tel:')} `;
        const telValue = `${companyConfig.tel} `;
        const hotlineLabel = `${t('Hotline:')} `;
        const hotlineValue = companyConfig.hotline;

        // Calculate widths
        doc.setFont(baseFont, 'bold');
        doc.setTextColor(80, 80, 80);
        const mstLabelWidth = doc.getTextWidth(mstLabel);
        const telLabelWidth = doc.getTextWidth(telLabel);
        const hotlineLabelWidth = doc.getTextWidth(hotlineLabel);

        doc.setFont(baseFont, 'normal');
        doc.setTextColor(80, 80, 80);
        const mstValueWidth = doc.getTextWidth(mstValue);
        const telValueWidth = doc.getTextWidth(telValue);
        const separatorWidth = doc.getTextWidth(separator);

        let contactStartX = companyInfoX;

        // Render MST
        doc.setFont(baseFont, 'bold');
        doc.setTextColor(80, 80, 80);
        doc.text(mstLabel, contactStartX, infoY, { maxWidth: 999 });
        contactStartX += mstLabelWidth;
        doc.setFont(baseFont, 'normal');
        doc.setTextColor(80, 80, 80);
        doc.text(mstValue, contactStartX, infoY, { maxWidth: 999 });
        contactStartX += mstValueWidth;

        if (companySlug === 'LD' && orientation === 'portrait') {
            // LD portrait layout: MST | Website on this line
            doc.text(separator, contactStartX, infoY, { maxWidth: 999 });
            contactStartX += separatorWidth;
            const wsLabel = `${t('Website:')} `;
            doc.setFont(baseFont, 'bold');
            doc.text(wsLabel, contactStartX, infoY, { maxWidth: 999 });
            contactStartX += doc.getTextWidth(wsLabel);
            doc.setFont(baseFont, 'normal');
            doc.text(companyConfig.website, contactStartX, infoY, { maxWidth: 999 });

            // Next line: Tel | Hotline | Email
            infoY += orientation === 'landscape' ? 13 : 12;
            contactStartX = companyInfoX;

            doc.setFont(baseFont, 'bold');
            doc.setTextColor(80, 80, 80);
            doc.text(telLabel, contactStartX, infoY, { maxWidth: 999 });
            contactStartX += telLabelWidth;
            doc.setFont(baseFont, 'normal');
            doc.setTextColor(80, 80, 80);
            doc.text(telValue, contactStartX, infoY, { maxWidth: 999 });
            contactStartX += telValueWidth;

            doc.text(separator, contactStartX, infoY, { maxWidth: 999 });
            contactStartX += separatorWidth;

            doc.setFont(baseFont, 'bold');
            doc.setTextColor(80, 80, 80);
            doc.text(hotlineLabel, contactStartX, infoY, { maxWidth: 999 });
            contactStartX += hotlineLabelWidth;
            doc.setFont(baseFont, 'normal');
            doc.setTextColor(80, 80, 80);
            const hotlineValLD = `${hotlineValue} `;
            doc.text(hotlineValLD, contactStartX, infoY, { maxWidth: 999 });
            contactStartX += doc.getTextWidth(hotlineValLD);

            doc.text(separator, contactStartX, infoY, { maxWidth: 999 });
            contactStartX += separatorWidth;

            const emailLabelLD = `${t('Email:')} `;
            doc.setFont(baseFont, 'bold');
            doc.setTextColor(80, 80, 80);
            doc.text(emailLabelLD, contactStartX, infoY, { maxWidth: 999 });
            contactStartX += doc.getTextWidth(emailLabelLD);
            doc.setFont(baseFont, 'normal');
            doc.setTextColor(80, 80, 80);
            doc.text(quotationOwnerEmail, contactStartX, infoY, { maxWidth: 999 });
        } else {
            // Non-LD layout: MST | Tel | Hotline on this line
            doc.text(separator, contactStartX, infoY, { maxWidth: 999 });
            contactStartX += separatorWidth;

            doc.setFont(baseFont, 'bold');
            doc.setTextColor(80, 80, 80);
            doc.text(telLabel, contactStartX, infoY, { maxWidth: 999 });
            contactStartX += telLabelWidth;
            doc.setFont(baseFont, 'normal');
            doc.setTextColor(80, 80, 80);
            doc.text(telValue, contactStartX, infoY, { maxWidth: 999 });
            contactStartX += telValueWidth;

            doc.text(separator, contactStartX, infoY, { maxWidth: 999 });
            contactStartX += separatorWidth;

            doc.setFont(baseFont, 'bold');
            doc.setTextColor(80, 80, 80);
            doc.text(hotlineLabel, contactStartX, infoY, { maxWidth: 999 });
            contactStartX += hotlineLabelWidth;
            doc.setFont(baseFont, 'normal');
            doc.setTextColor(80, 80, 80);
            doc.text(hotlineValue, contactStartX, infoY, { maxWidth: 999 });

            // Next line: Email | Website
            infoY += orientation === 'landscape' ? 13 : 12;

            const emailLabel = `${t('Email:')} `;
            const emailValue = `${quotationOwnerEmail} `;
            const sep2 = '| ';
            const websiteLabel = `${t('Website:')} `;
            const websiteValue = companyConfig.website;

            doc.setFont(baseFont, 'bold');
            doc.setTextColor(80, 80, 80);
            const emailLabelWidth = doc.getTextWidth(emailLabel);
            const websiteLabelWidth = doc.getTextWidth(websiteLabel);

            doc.setFont(baseFont, 'normal');
            doc.setTextColor(80, 80, 80);
            const emailValueWidth = doc.getTextWidth(emailValue);
            const sep2Width = doc.getTextWidth(sep2);

            let emailWebsiteStartX = companyInfoX;

            doc.setFont(baseFont, 'bold');
            doc.setTextColor(80, 80, 80);
            doc.text(emailLabel, emailWebsiteStartX, infoY, { maxWidth: 999 });
            emailWebsiteStartX += emailLabelWidth;
            doc.setFont(baseFont, 'normal');
            doc.setTextColor(80, 80, 80);
            doc.text(emailValue, emailWebsiteStartX, infoY, { maxWidth: 999 });
            emailWebsiteStartX += emailValueWidth;

            doc.text(sep2, emailWebsiteStartX, infoY, { maxWidth: 999 });
            emailWebsiteStartX += sep2Width;
            doc.setFont(baseFont, 'bold');
            doc.setTextColor(80, 80, 80);
            doc.text(websiteLabel, emailWebsiteStartX, infoY, { maxWidth: 999 });
            emailWebsiteStartX += websiteLabelWidth;
            doc.setFont(baseFont, 'normal');
            doc.setTextColor(...primaryBlue);
            doc.text(websiteValue, emailWebsiteStartX, infoY, { maxWidth: 999 });
        }

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

        //BÁO GIÁ title - centered
        ensureSpace(50);
        doc.setFont(baseFont, 'bold');
        doc.setFontSize(orientation === 'landscape' ? 22 : 20);
        doc.setTextColor(...primaryBlue);
        doc.text(t('BÁO GIÁ'), pageWidth / 2, cursorY, { align: 'center' });

        const quotationQrDataUrl = await createDocumentQrDataUrl(options.qrPayload || '');
        if (quotationQrDataUrl) {
            const qrSize = orientation === 'landscape' ? 48 : 44;
            doc.addImage(
                quotationQrDataUrl,
                'PNG',
                pageWidth - margin - qrSize,
                cursorY - 2,
                qrSize,
                qrSize
            );
        }

        cursorY += quotationQrDataUrl ? 52 : orientation === 'landscape' ? 22 : 20;

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
        const ngayBaoGiaText = `${t('Ngày báo giá:')} `;
        const ngayBaoGiaWidth = doc.getTextWidth(ngayBaoGiaText);
        const soBaoGiaText = `${t('Số báo giá:')} `;
        const soBaoGiaWidth = doc.getTextWidth(soBaoGiaText);

        const dateAvailableWidth = rightColumnWidth - ngayBaoGiaWidth;
        const quotationNumberAvailableWidth = rightColumnWidth - soBaoGiaWidth;

        const formattedDate = new Date().toLocaleDateString('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
        doc.setFont(baseFont, 'normal');
        const dateLines = doc.splitTextToSize(formattedDate, dateAvailableWidth);
        const quotationNumberLines = doc.splitTextToSize(
            quotation?.quotation_number || '',
            quotationNumberAvailableWidth
        );

        // Calculate total height: max of left and right columns
        const lineSpacing = 9; // mm per line (same as in rendering logic)

        // Left column height calculation:
        // Kính gửi: first line + additional lines + spacing after
        const kinhGuiHeight = companyLines.length * lineSpacing + lineSpacing;
        // Người nhận: first line + additional lines + spacing after
        const recipientHeight = recipientLines.length * lineSpacing + lineSpacing;
        // Số điện thoại: first line + additional lines + spacing after
        const phoneHeight = phoneLines.length * lineSpacing + lineSpacing;
        // Email: first line + additional lines + spacing after
        const emailHeight = emailLines.length * lineSpacing + lineSpacing;
        // Địa chỉ: first line + additional lines (NO spacing after last field)
        const addressHeight = addressLines.length * lineSpacing;

        const leftColumnHeight =
            kinhGuiHeight + recipientHeight + phoneHeight + emailHeight + addressHeight;

        // Right column height calculation:
        // Ngày báo giá: first line + additional lines + spacing after
        const dateHeight = dateLines.length * lineSpacing + lineSpacing;
        // Số báo giá: first line + additional lines (NO spacing after last field)
        const quotationNumberHeight = quotationNumberLines.length * lineSpacing;

        const rightColumnHeight = dateHeight + quotationNumberHeight;

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
        rightY += 9; // Space after Ngày báo giá field

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

        // Reset text color
        doc.setTextColor(0, 0, 0);

        cursorY = boxStartY + boxHeight + 15;

        // Disclaimer paragraph
        const companyName = getConfigValue(companyConfig, 'name', language);
        const disclaimerTemplate = t(
            'gửi lời cảm ơn sự tin tưởng và luôn đồng hành của Quý Khách hàng đối với Công Ty Chúng Tôi. Chúng tôi xin gửi tới Quý Công Ty bản báo giá các sản phẩm, kèm theo các điều kiện thương mại cụ thể của gói hàng.'
        );
        const disclaimer = `${companyName} ${disclaimerTemplate}`;
        addParagraph(disclaimer, { size: 9 });

        cursorY += 15;

        const headers = [language === 'en' ? 'No.' : t('STT'), ...columns.map((col) => col.header)];
        const tableRows = items.map((item, index) => {
            const row = [String(index + 1)];
            columns.forEach((col) => {
                let value = item[col.field];

                if (col.field === 'sub_total' && !value) {
                    const quantity = parseFloat(item.quantity) || 0;
                    const sellingPrice = parseFloat(item.selling_price) || 0;
                    value = quantity * sellingPrice;
                }

                // Use column's format function if available, otherwise use default formatting
                if (col.format && typeof col.format === 'function') {
                    value = col.format(value);
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
                            // Apply currency conversion and proper decimals
                            const convertedValue = convertCurrency(numeric);
                            value = formatNumber(convertedValue, getCurrencyDecimals());
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
                lineWidth: 0.4,
            },
            headStyles: {
                fillColor: [226, 234, 246],
                textColor: [0, 0, 0],
                halign: 'center',
                fontStyle: 'bold',
            },
            alternateRowStyles: {
                fillColor: [255, 255, 255],
            },
            columnStyles: columns.reduce(
                (accumulator, col, index) => {
                    const colIndex = index + 1;
                    const isNumeric = col.type === 'currency' || col.type === 'number';
                    // Căn phải cho số, currency, và cột delivery_time
                    const shouldAlignRight = isNumeric || col.field === 'lead_time_days';
                    accumulator[colIndex] = {
                        halign: shouldAlignRight ? 'right' : 'left',
                        cellWidth: 'auto',
                        minCellWidth: col.field === 'designation' ? 75 : undefined,
                        minCellHeight: 18,
                    };
                    return accumulator;
                },
                { 0: { cellWidth: 32, halign: 'center', minCellHeight: 18 } }
            ),
        });

        cursorY = doc.lastAutoTable ? doc.lastAutoTable.finalY : cursorY + 120;

        let subtotal = 0;
        let vatTotal = 0;

        items.forEach((item) => {
            const quantity = parseFloat(item.quantity) || 0;
            const sellingPrice = parseFloat(item.selling_price) || 0;
            // Use nullish coalescing to preserve 0 values, only default to 0 for null/undefined
            const vat = item.vat != null ? parseFloat(item.vat) || 0 : 0;
            const itemSubtotal = quantity * sellingPrice;

            // Calculate VAT
            const itemVat = itemSubtotal * (vat / 100);

            subtotal += itemSubtotal;
            vatTotal += itemVat;
        });

        // Convert totals to selected currency
        const convertedSubtotal = convertCurrency(subtotal);
        const convertedVatTotal = convertCurrency(vatTotal);
        const convertedGrandTotal = convertedSubtotal + convertedVatTotal;

        // Format functions for display based on currency
        const summaryDecimals = currency !== 'VND' ? 2 : 0;
        const formatSubtotal = (value) => formatNumber(value, summaryDecimals);
        const formatVat = (value) => formatNumber(value, summaryDecimals);
        const formatTotal = (value) => formatNumber(value, summaryDecimals);

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
                createSummaryRow(
                    t('TỔNG THÀNH TIỀN:'),
                    `${formatSubtotal(convertedSubtotal)} ${currency}`
                ),
                createSummaryRow(t('TỔNG VAT:'), `${formatVat(convertedVatTotal)} ${currency}`),
                createSummaryRow(
                    t('TỔNG CỘNG:'),
                    `${formatTotal(convertedGrandTotal)} ${currency}`
                ),
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
                return 'Kiểm tra mô tả, ký hiệu hàng hóa trước khi xác nhận đặt hàng. Hàng hóa khi đã giao nhận, Á Châu không đổi trả lại hàng hóa.';
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

        // Helper function to normalize text to use 'Á Châu' for translation lookup
        const normalizeForTranslation = (text) => {
            if (!text || typeof text !== 'string') return text;
            // Replace all company short names back to 'Á Châu' so translation key matches
            return text.replace(/(TST|LDT)(?: Việt Nam)?/g, 'Á Châu');
        };

        // Helper function to replace company short name in both Vietnamese and English text
        const replaceCompanyShortName = (text) => {
            if (!text || typeof text !== 'string') return text;
            const currentShortName = companyConfig.shortName || 'Á Châu';
            // Replace Vietnamese company short names and English 'Asia' with current one
            return text
                .replace(/(Á Châu|TST|LDT)(?: Việt Nam)?/g, currentShortName)
                .replace(/\bAsia\b/g, currentShortName);
        };

        // Helper function to translate with company name replacement
        // 1. Normalize to 'Á Châu' for translation lookup
        // 2. Translate
        // 3. Replace with current company short name
        const translateAndReplace = (text) => {
            const normalized = normalizeForTranslation(text);
            const translated = t(normalized);
            return replaceCompanyShortName(translated);
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
        // Note: Use 'Á Châu' in defaults so translation lookup works, then replace after
        const defaultLeftTerms = [
            { label: 'Địa điểm giao hàng:', value: 'Tại Kho Quý Công ty.' },
            { label: 'Thời gian cấp hàng:', value: 'Theo Báo giá, tính từ thời điểm đặt hàng.' },
            { label: 'Báo giá có giá trị:', value: '30 ngày.' },
            {
                label: 'Lưu ý:',
                value: 'Kiểm tra mô tả, ký hiệu hàng hóa trước khi xác nhận đặt hàng. Hàng hóa khi đã giao nhận, Á Châu không đổi trả lại hàng hóa.',
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

        if (options.customTerms?.leftTerms && options.customTerms?.rightTerms) {
            // New array format - replace company names in values
            leftTermsArray = options.customTerms.leftTerms.map((term) => ({
                ...term,
                value: replaceCompanyShortName(term.value),
            }));
            rightTermsArray = options.customTerms.rightTerms.map((term) => ({
                ...term,
                value: replaceCompanyShortName(term.value),
            }));
        } else if (options.customTerms && typeof options.customTerms === 'object') {
            // Old object format - convert to array format for backward compatibility
            const terms = options.customTerms;
            const defaultNote =
                'Kiểm tra mô tả, ký hiệu hàng hóa trước khi xác nhận đặt hàng. Hàng hóa khi đã giao nhận, Á Châu không đổi trả lại hàng hóa.';
            // Always use replaceCompanyShortName for note field, fallback to defaultNote if empty
            const noteValue = terms.note ? replaceCompanyShortName(terms.note) : defaultNote;
            leftTermsArray = [
                {
                    label: terms.deliveryLocationLabel || 'Địa điểm giao hàng:',
                    value: terms.deliveryLocation || 'Tại Kho Quý Công ty.',
                },
                {
                    label: terms.deliveryTimeLabel || 'Thời gian cấp hàng:',
                    value: terms.deliveryTime || 'Theo Báo giá, tính từ thời điểm đặt hàng.',
                },
                {
                    label: terms.validityPeriodLabel || 'Báo giá có giá trị:',
                    value: terms.validityPeriod || '30 ngày.',
                },
                { label: terms.noteLabel || 'Lưu ý:', value: noteValue },
                { label: terms.qualityLabel || 'Chất lượng:', value: terms.quality || '' },
                { label: terms.commitmentLabel || 'Cam kết:', value: terms.commitment || '' },
            ].filter((item) => item.label && item.value);

            rightTermsArray = [
                { label: terms.paymentLabel || 'Thanh toán:', value: terms.payment || '' },
                {
                    label: terms.paymentMethodLabel || 'Phương thức thanh toán:',
                    value: terms.paymentMethod || '',
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
                text: ensureTrailingPeriod(translateAndReplace(normalizeNoteValue(term.value))),
            }));

        // Right column notes - map to the format expected by rendering code
        const rightNotes = rightTermsArray
            .filter((term) => term.label && term.value)
            .map((term) => ({
                label: t(term.label),
                text: ensureTrailingPeriod(translateAndReplace(normalizeNoteValue(term.value))),
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

        // Left signature box - Đại diện bên bán hàng (Seller Representative)
        doc.setFont(baseFont, 'bold');
        doc.setFontSize(10);
        doc.setTextColor(0, 0, 0);
        doc.text(t('XÁC NHẬN ĐẶT HÀNG'), signatureLeftX + signatureBoxWidth / 2, cursorY, {
            align: 'center',
        });

        // Right signature box - Xác nhận đặt hàng (Order Confirmation / Buyer)
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

        const creatorName = quotation?.created_by?.full_name || quotation?.created_by?.name || '';
        const creatorPhone = quotation?.created_by?.phone || '';

        doc.setFont(baseFont, 'bold');
        doc.setFontSize(10);
        doc.setTextColor(...primaryBlue);

        if (creatorName) {
            doc.text(creatorName, signatureRightX + signatureBoxWidth / 2, cursorY + 90, {
                align: 'center',
            });
        }
        if (creatorPhone) {
            doc.text(creatorPhone, signatureRightX + signatureBoxWidth / 2, cursorY + 104, {
                align: 'center',
            });
        }

        cursorY += signatureBoxHeight + 50;

        const filename =
            fileName ||
            `Bao_gia_${quotationNumber || 'preview'}_${formatDateForSubmit(new Date())}.pdf`;
        const blob = doc.output('blob');

        if (download) {
            saveAs(blob, filename);
        }

        return blob;
    };

    const generatePdfBlob = async (quotation, language = 'vi') => {
        return await generateQuotationPdf(quotation, language, false);
    };

    return {
        generateQuotationPdf,
        generatePdfBlob,
    };
}
