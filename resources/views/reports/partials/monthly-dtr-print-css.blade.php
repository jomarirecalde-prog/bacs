<style>
    @page {
        size: A4 landscape;
        margin: 8mm 10mm;
    }

    html.dtr-print-root,
    body.dtr-print {
        width: 297mm;
        max-width: 100%;
        margin: 0 auto;
        box-sizing: border-box;
        padding: 8mm 10mm;
    }

    body.dtr-print .dtr-sheet-fit,
    body.dtr-print .dtr-sheet {
        width: 100%;
        max-width: 100%;
        margin-left: auto;
        margin-right: auto;
        overflow: hidden;
    }

    body.dtr-print *,
    body.dtr-print *::before,
    body.dtr-print *::after {
        box-sizing: border-box;
    }

    .dtr-letterhead {
        display: block;
        width: 100%;
        max-width: 100%;
        height: 40mm;
        object-fit: cover;
        object-position: center center;
        margin: 0 0 4px;
    }

    body.dtr-print .dtr-sheet > table {
        width: 100%;
        max-width: 100%;
        table-layout: fixed;
    }

    body.dtr-print .dtr-sheet > table th,
    body.dtr-print .dtr-sheet > table td {
        overflow: hidden;
        word-wrap: break-word;
    }

    .dtr-title-wrap {
        width: 100%;
        margin: 0 0 4px;
        border-collapse: collapse;
    }

    .dtr-title-wrap td {
        border: 0;
        text-align: center;
        font-weight: 700;
        font-size: 12px;
        padding: 1px 0 4px;
        letter-spacing: 0.5px;
        line-height: 1.2;
    }

    body.dtr-print .dtr-sheet .meta {
        margin: 0 0 5px;
        font-size: 9px;
        line-height: 1.35;
    }

    body.dtr-print .dtr-sheet table th,
    body.dtr-print .dtr-sheet table td {
        padding: 2px 3px;
        font-size: 8px;
        line-height: 1.25;
    }

    body.dtr-print .dtr-sheet table th {
        font-size: 7.5px;
        padding: 2px 3px;
    }

    body.dtr-print .dtr-sheet table.sign {
        margin-top: 8px;
    }

    body.dtr-print .dtr-sheet table.sign .line {
        margin-top: 16px;
        width: 70%;
        max-width: 180px;
        font-size: 8px;
        padding-top: 3px;
    }

    body.dtr-print .dtr-sheet .muted {
        font-size: 7px;
        line-height: 1.2;
    }

    @media print {
        html.dtr-print-root,
        body.dtr-print {
            width: auto;
            max-width: none;
            margin: 0;
            padding: 0;
            background: #fff;
        }

        .dtr-sheet {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .dtr-sheet.page-break {
            page-break-before: always;
            break-before: page;
        }

        .dtr-sheet-fit {
            page-break-inside: avoid;
            break-inside: avoid;
        }
    }
</style>
<script>
(function () {
    var MM_TO_PX = 96 / 25.4;
    var PAGE_MARGIN_V_MM = 16;
    var PAGE_MARGIN_H_MM = 20;
    var PAGE_W_MM = 297;
    var PAGE_H_MM = 210;
    var MIN_SCALE = 0.72;

    function pageBoxPx() {
        return {
            w: (PAGE_W_MM - PAGE_MARGIN_H_MM) * MM_TO_PX,
            h: (PAGE_H_MM - PAGE_MARGIN_V_MM) * MM_TO_PX,
        };
    }

    function ensureFitWrap(sheet) {
        var parent = sheet.parentElement;
        if (parent && parent.classList.contains('dtr-sheet-fit')) {
            return parent;
        }
        var wrap = document.createElement('div');
        wrap.className = 'dtr-sheet-fit';
        sheet.parentNode.insertBefore(wrap, sheet);
        wrap.appendChild(sheet);
        return wrap;
    }

    function resetSheet(sheet, wrap) {
        sheet.style.transform = '';
        sheet.style.zoom = '';
        sheet.style.width = '';
        sheet.style.maxWidth = '';
        sheet.style.marginBottom = '';
        if (wrap) {
            wrap.style.height = '';
            wrap.style.width = '';
            wrap.style.maxWidth = '';
            wrap.style.overflow = '';
        }
    }

    function supportsZoom() {
        return typeof CSS !== 'undefined' && CSS.supports && CSS.supports('zoom', '0.5');
    }

    function measureSheet(sheet) {
        return {
            w: sheet.scrollWidth || sheet.offsetWidth,
            h: sheet.scrollHeight || sheet.offsetHeight,
        };
    }

    function applyScale(sheet, wrap, scale) {
        if (scale >= 0.995) {
            resetSheet(sheet, wrap);
            return;
        }

        scale = Math.max(scale, MIN_SCALE);

        if (supportsZoom()) {
            sheet.style.zoom = String(scale);
            sheet.style.width = '';
            sheet.style.transform = '';
            wrap.style.height = '';
            wrap.style.overflow = '';
            return;
        }

        sheet.style.zoom = '';
        sheet.style.transform = 'scale(' + scale + ')';
        sheet.style.transformOrigin = 'top left';
        sheet.style.width = (100 / scale) + '%';
        wrap.style.overflow = 'hidden';
        wrap.style.width = '100%';
        var size = measureSheet(sheet);
        wrap.style.height = Math.ceil(size.h * scale) + 'px';
        sheet.style.marginBottom = '-' + Math.ceil(size.h * (1 - scale)) + 'px';
    }

    function fitSheet(sheet) {
        var wrap = ensureFitWrap(sheet);
        resetSheet(sheet, wrap);

        var box = pageBoxPx();
        sheet.style.maxWidth = box.w + 'px';
        wrap.style.maxWidth = box.w + 'px';
        var size = measureSheet(sheet);
        if (size.w <= 0 || size.h <= 0) {
            return;
        }

        var scale = Math.min(1, box.w / size.w, box.h / size.h);
        applyScale(sheet, wrap, scale);
    }

    function fitAll() {
        document.querySelectorAll('.dtr-sheet').forEach(fitSheet);
    }

    function resetAll() {
        document.querySelectorAll('.dtr-sheet').forEach(function (sheet) {
            var wrap = sheet.parentElement;
            resetSheet(sheet, wrap && wrap.classList.contains('dtr-sheet-fit') ? wrap : null);
        });
    }

    function printDtr() {
        fitAll();
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                window.print();
            });
        });
    }

    window.bacsDtrPrint = printDtr;

    document.documentElement.classList.add('dtr-print-root');

    window.addEventListener('beforeprint', fitAll);
    window.addEventListener('afterprint', resetAll);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fitAll);
    } else {
        fitAll();
    }
})();
</script>