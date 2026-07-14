import QRCode from 'qrcode';
import { jsPDF } from 'jspdf';

const ready = new Promise((resolve) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', resolve, { once: true });
        return;
    }

    resolve();
});

ready.then(async () => {
    const canvas = document.querySelector('[data-swafi-qr]');

    if (!(canvas instanceof HTMLCanvasElement)) {
        return;
    }

    const value = canvas.dataset.swafiQr || '';
    const status = document.querySelector('[data-qr-status]');
    const downloadButton = document.querySelector('[data-download-qr]');
    const pdfButton = document.querySelector('[data-download-pdf]');
    const printButton = document.querySelector('[data-print-label]');
    const logo = document.querySelector('[data-label-logo]');
    const auditUrl = canvas.dataset.auditUrl || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const downloadName = canvas.dataset.downloadName || 'etiqueta_swafi.png';
    const pdfName = canvas.dataset.pdfName || 'etiqueta_swafi.pdf';

    const setStatus = (message, isError = false) => {
        if (!status) {
            return;
        }

        status.textContent = message;
        status.classList.toggle('is-error', isError);
    };

    const dataValue = (name, fallback = 'Sin dato') => {
        const content = canvas.dataset[name];

        return typeof content === 'string' && content.trim() !== ''
            ? content.trim()
            : fallback;
    };

    const audit = async (eventName) => {
        if (!auditUrl || !csrfToken) {
            return;
        }

        try {
            await fetch(auditUrl, {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ evento: eventName }),
            });
        } catch (error) {
            // La descarga o impresión no debe bloquearse por un error de auditoría.
        }
    };

    const drawField = (pdf, label, content, x, y, maxWidth = 74) => {
        pdf.setFont('helvetica', 'bold');
        pdf.setFontSize(7.5);
        pdf.setTextColor(82, 103, 125);
        pdf.text(label.toUpperCase(), x, y);

        pdf.setFont('helvetica', 'normal');
        pdf.setFontSize(10);
        pdf.setTextColor(19, 36, 58);
        const lines = pdf.splitTextToSize(content, maxWidth);
        pdf.text(lines.slice(0, 2), x, y + 5);
    };

    const downloadPdf = () => {
        const pdf = new jsPDF({
            orientation: 'landscape',
            unit: 'mm',
            format: 'a4',
            compress: true,
        });

        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const left = 9;
        const top = 9;
        const width = pageWidth - 18;
        const height = pageHeight - 18;
        const separatorX = 203;

        pdf.setProperties({
            title: `Etiqueta SWAFI ${dataValue('assetNumber')}`,
            subject: 'Identificación física de activo fijo',
            author: 'SWAFI - Bimbo SA de CV',
            creator: 'SWAFI',
        });

        pdf.setDrawColor(23, 79, 130);
        pdf.setLineWidth(1.2);
        pdf.roundedRect(left, top, width, height, 3, 3);
        pdf.line(separatorX, top, separatorX, top + height);

        if (logo instanceof HTMLImageElement && logo.complete && logo.naturalWidth > 0) {
            try {
                pdf.addImage(logo, 'JPEG', 15, 14, 38, 20, 'swafi-bimbo-logo', 'FAST');
            } catch (error) {
                // El texto SWAFI mantiene identificable el PDF si el navegador no permite incrustar el logo.
            }
        }

        pdf.setFont('helvetica', 'bold');
        pdf.setTextColor(19, 36, 58);
        pdf.setFontSize(20);
        pdf.text('SWAFI', 58, 23);
        pdf.setFont('helvetica', 'normal');
        pdf.setTextColor(82, 103, 125);
        pdf.setFontSize(8.5);
        pdf.text('Sistema Web de Gestión de Facturas de Activo Fijo', 58, 29);

        pdf.setDrawColor(216, 228, 239);
        pdf.setLineWidth(0.5);
        pdf.line(15, 38, 196, 38);

        pdf.setFont('helvetica', 'bold');
        pdf.setTextColor(23, 79, 130);
        pdf.setFontSize(29);
        const assetNumber = pdf.splitTextToSize(dataValue('assetNumber'), 178);
        pdf.text(assetNumber.slice(0, 1), 15, 53);

        pdf.setFontSize(14);
        pdf.setTextColor(19, 36, 58);
        const description = pdf.splitTextToSize(dataValue('assetDescription'), 178);
        pdf.text(description.slice(0, 2), 15, 63);

        drawField(pdf, 'Tipo de activo', dataValue('assetType'), 15, 83);
        drawField(pdf, 'Planta', dataValue('plant'), 105, 83);
        drawField(pdf, 'Área / ubicación', dataValue('location'), 15, 105);
        drawField(pdf, 'Responsable', dataValue('responsible'), 105, 105);
        drawField(pdf, 'Marca / modelo', dataValue('brandModel'), 15, 127);
        drawField(pdf, 'Serie', dataValue('serial'), 105, 127);

        const statusLabels = [
            `Operativo: ${dataValue('operationalStatus')}`,
            `Documental: ${dataValue('documentStatus')}`,
            `Inventario: ${dataValue('inventoryStatus')}`,
        ];

        let statusX = 15;
        statusLabels.forEach((label) => {
            const pillWidth = Math.min(Math.max(pdf.getTextWidth(label) + 8, 36), 58);
            pdf.setFillColor(232, 241, 249);
            pdf.roundedRect(statusX, 151, pillWidth, 10, 4, 4, 'F');
            pdf.setFont('helvetica', 'bold');
            pdf.setFontSize(7.5);
            pdf.setTextColor(23, 79, 130);
            pdf.text(pdf.splitTextToSize(label, pillWidth - 5).slice(0, 1), statusX + 4, 157.3);
            statusX += pillWidth + 4;
        });

        const qrImage = canvas.toDataURL('image/png');
        pdf.addImage(qrImage, 'PNG', 220, 28, 62, 62, 'swafi-qr', 'FAST');

        pdf.setFont('helvetica', 'bold');
        pdf.setTextColor(19, 36, 58);
        pdf.setFontSize(14);
        pdf.text('Escanear para consultar', 251, 101, { align: 'center' });

        pdf.setFont('helvetica', 'normal');
        pdf.setTextColor(82, 103, 125);
        pdf.setFontSize(8.5);
        const help = pdf.splitTextToSize(
            'El código dirige a la búsqueda autenticada del activo en SWAFI.',
            72,
        );
        pdf.text(help, 251, 108, { align: 'center' });
        pdf.text(`Generado: ${dataValue('generatedAt')}`, 251, 122, { align: 'center' });

        pdf.setFontSize(7.5);
        pdf.setTextColor(90, 109, 128);
        const note = pdf.splitTextToSize(
            'La etiqueta no sustituye el número físico del activo ni los controles corporativos de Bimbo SA de CV.',
            72,
        );
        pdf.text(note, 251, 151, { align: 'center' });

        pdf.setFontSize(7);
        pdf.text('HU-049 · Generación controlada y trazable', 251, 180, { align: 'center' });

        pdf.save(pdfName);
    };

    try {
        if (!value) {
            throw new Error('No se recibió el contenido del código QR.');
        }

        await QRCode.toCanvas(canvas, value, {
            errorCorrectionLevel: 'H',
            width: 300,
            margin: 2,
            color: {
                dark: '#111111',
                light: '#ffffff',
            },
        });

        setStatus('Código QR generado correctamente.');

        if (downloadButton instanceof HTMLButtonElement) {
            downloadButton.disabled = false;
            downloadButton.addEventListener('click', async () => {
                const link = document.createElement('a');
                link.href = canvas.toDataURL('image/png');
                link.download = downloadName;
                document.body.appendChild(link);
                link.click();
                link.remove();

                await audit('descargar_png');
            });
        }

        if (pdfButton instanceof HTMLButtonElement) {
            pdfButton.disabled = false;
            pdfButton.addEventListener('click', async () => {
                downloadPdf();
                await audit('descargar_pdf');
            });
        }

        if (printButton instanceof HTMLButtonElement) {
            printButton.disabled = false;
            printButton.addEventListener('click', async () => {
                await audit('imprimir');
                window.print();
            });
        }
    } catch (error) {
        setStatus(error instanceof Error ? error.message : 'No fue posible generar el código QR.', true);
    }
});
