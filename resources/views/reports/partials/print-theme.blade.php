{{--
    Shared print/PDF stylesheet.

    PDF rendering (dompdf) does not support CSS custom properties, so the theme
    palette is written literally here — this file is the single place the print
    documents get their colors from.

    Emerald #047857 (primary) · Blue #1D4ED8 (secondary) · Gold #D4AF37 (accent)
--}}
<style>
    @page { margin: 24px; }

    body {
        font-family: {{ ($font ?? 'sans') === 'serif' ? 'Georgia, "Times New Roman", serif' : 'DejaVu Sans, sans-serif' }};
        font-size: 11px;
        color: #10201b;
        margin: 0;
    }

    .doc-header {
        border-bottom: 2px solid #047857;
        padding-bottom: 8px;
        margin-bottom: 4px;
    }
    .doc-rule-gold {
        height: 3px;
        background: #d4af37;
        width: 96px;
        margin-bottom: 14px;
    }

    h1 { font-size: 16px; margin: 0; color: #047857; letter-spacing: 0.4px; }
    h2 { font-size: 13px; margin: 6px 0 12px; font-weight: normal; color: #3f4d48; }
    h3 { font-size: 12px; margin: 0 0 6px; color: #10201b; letter-spacing: 1px; }

    .muted { color: #6b7975; font-size: 10px; }
    .meta { margin: 0 0 12px; line-height: 1.6; }
    .meta strong { color: #10201b; }

    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #cfd6d3; padding: 5px 6px; text-align: left; }
    th {
        background: #d1fae5;
        color: #065f46;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    tbody tr:nth-child(even) td { background: #f6f8f7; }
    td.num { text-align: right; }

    .sign { margin-top: 36px; display: table; width: 100%; }
    .sign div { display: table-cell; width: 50%; }
    .line {
        margin-top: 40px;
        border-top: 1px solid #10201b;
        width: 220px;
        padding-top: 4px;
        font-size: 10px;
        color: #3f4d48;
    }

    .certify {
        margin-top: 16px;
        border-left: 3px solid #d4af37;
        padding-left: 8px;
        color: #6b7975;
        font-size: 10px;
    }

    .no-print button {
        background: #047857;
        color: #ffffff;
        border: 0;
        border-radius: 6px;
        padding: 8px 16px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
    }
    .no-print button:hover { background: #065f46; }

    @media print {
        .no-print { display: none !important; }
        tbody tr:nth-child(even) td { background: #ffffff; }
    }
</style>
