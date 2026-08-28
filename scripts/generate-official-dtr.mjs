/**
 * Overlay employee DTR data onto the official DAILY TIME RECORD.pdf template.
 *
 * Coordinates were measured from the original Word-exported A4 page
 * (595.32 x 841.92 pt, origin bottom-left). Sample values are covered with
 * white rectangles, then live data is written in the same positions.
 */
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import fontkit from '@pdf-lib/fontkit';
import { PDFDocument, rgb, StandardFonts } from 'pdf-lib';

const ROWS_PER_PAGE = 15;
const FIRST_ROW_Y = 613.18;
const ROW_STEP = 15.48;
const FONT_SIZE = 10;

const COL_DEFS = [
    { key: 'date', left: 18.48, right: 93.26 },
    { key: 'day', left: 93.26, right: 165.62 },
    { key: 'am_in', left: 165.62, right: 235.85 },
    { key: 'am_out', left: 235.85, right: 316.73 },
    { key: 'pm_in', left: 316.73, right: 385.87 },
    { key: 'pm_out', left: 385.87, right: 457.27 },
    { key: 'overtime', left: 457.27, right: 518.38 },
    { key: 'total', left: 518.38, right: 576.84 },
];

const WHITE = rgb(1, 1, 1);
const INK = rgb(0.07, 0.07, 0.07);
const GRID = rgb(0.62, 0.62, 0.62);
const SUNDAY_BG = rgb(1, 0.97, 0.82);
const SUNDAY_INK = rgb(0.72, 0.12, 0.12);

const [,, templatePath, jsonPath, outPath] = process.argv;

if (!templatePath || !jsonPath || !outPath) {
    console.error('Usage: node generate-official-dtr.mjs <template.pdf> <data.json> <out.pdf>');
    process.exit(1);
}

const data = JSON.parse(readFileSync(jsonPath, 'utf8'));
const rows = Array.isArray(data.rows) ? data.rows : [];
const pageCount = Math.max(1, Math.ceil(rows.length / ROWS_PER_PAGE));

const templateBytes = readFileSync(templatePath);
const source = await PDFDocument.load(templateBytes);
const output = await PDFDocument.create();
output.registerFontkit(fontkit);

const font = await embedBodyFont(output);

for (let pageIndex = 0; pageIndex < pageCount; pageIndex++) {
    const [page] = await output.copyPages(source, [0]);
    output.addPage(page);
    const chunk = rows.slice(pageIndex * ROWS_PER_PAGE, (pageIndex + 1) * ROWS_PER_PAGE);
    fillPage(page, font, data, chunk, pageIndex, pageCount);
}

writeFileSync(outPath, await output.save({ useObjectStreams: false }));

function fillPage(page, font, payload, chunk, pageIndex, pageCount) {
    coverHeaderFields(page);
    coverAndRedrawTable(page, Math.max(chunk.length, ROWS_PER_PAGE));

    const cutoff = pageCount > 1
        ? `${payload.cutoff || ''} (${pageIndex + 1}/${pageCount})`
        : (payload.cutoff || '');

    drawText(page, font, String(payload.employee_name || ''), 116.66, 684.70, 268, INK);
    drawText(page, font, cutoff, 445.51, 684.70, 128, INK);
    drawText(page, font, String(payload.department || ''), 87.26, 670.30, 300, INK);

    chunk.forEach((row, index) => {
        const y = FIRST_ROW_Y - index * ROW_STEP;
        const sunday = String(row.day || '') === 'Sunday';
        if (sunday) {
            highlightSunday(page, index);
        }
        const ink = sunday ? SUNDAY_INK : INK;
        drawCentered(page, font, row.date || '', COL_DEFS[0], y, ink);
        drawCentered(page, font, row.day || '', COL_DEFS[1], y, ink);
        drawCentered(page, font, row.am_in || '', COL_DEFS[2], y, INK);
        drawCentered(page, font, row.am_out || '', COL_DEFS[3], y, INK);
        drawCentered(page, font, row.pm_in || '', COL_DEFS[4], y, INK);
        drawCentered(page, font, row.pm_out || '', COL_DEFS[5], y, INK);
        drawCentered(page, font, row.overtime || '', COL_DEFS[6], y, INK);
        drawCentered(page, font, row.total || '', COL_DEFS[7], y, INK);
    });
}

function coverHeaderFields(page) {
    page.drawRectangle({ x: 99, y: 678.8, width: 292, height: 16.2, color: WHITE });
    page.drawRectangle({ x: 440, y: 678.8, width: 140, height: 16.2, color: WHITE });
    page.drawRectangle({ x: 78, y: 664.4, width: 310, height: 16.2, color: WHITE });
    page.drawLine({ start: { x: 116.5, y: 682.4 }, end: { x: 386, y: 682.4 }, thickness: 0.6, color: INK });
    page.drawLine({ start: { x: 445.5, y: 682.4 }, end: { x: 576, y: 682.4 }, thickness: 0.6, color: INK });
    page.drawLine({ start: { x: 87.2, y: 668.0 }, end: { x: 386, y: 668.0 }, thickness: 0.6, color: INK });
}

function coverAndRedrawTable(page, rowCount) {
    const rows = Math.max(rowCount, ROWS_PER_PAGE);
    const top = FIRST_ROW_Y + 10.2;
    const bottom = FIRST_ROW_Y - (rows - 1) * ROW_STEP - 5.2;
    const left = COL_DEFS[0].left;
    const right = COL_DEFS[COL_DEFS.length - 1].right;

    page.drawRectangle({
        x: left,
        y: bottom,
        width: right - left,
        height: top - bottom,
        color: WHITE,
        borderColor: GRID,
        borderWidth: 0.5,
    });

    for (let i = 1; i < rows; i++) {
        const y = FIRST_ROW_Y - (i - 1) * ROW_STEP - 5.2;
        page.drawLine({
            start: { x: left, y },
            end: { x: right, y },
            thickness: 0.4,
            color: GRID,
        });
    }

    for (let c = 1; c < COL_DEFS.length; c++) {
        page.drawLine({
            start: { x: COL_DEFS[c].left, y: top },
            end: { x: COL_DEFS[c].left, y: bottom },
            thickness: 0.4,
            color: GRID,
        });
    }
}

function highlightSunday(page, index) {
    const cellBottom = FIRST_ROW_Y - index * ROW_STEP - 5.2;
    const cellTop = index === 0 ? FIRST_ROW_Y + 10.2 : FIRST_ROW_Y - (index - 1) * ROW_STEP - 5.2;

    page.drawRectangle({
        x: COL_DEFS[0].left + 0.4,
        y: cellBottom + 0.3,
        width: COL_DEFS[1].right - COL_DEFS[0].left - 0.8,
        height: cellTop - cellBottom - 0.5,
        color: SUNDAY_BG,
    });
}

function drawCentered(page, font, text, col, y, color) {
    const width = col.right - col.left - 4;
    const value = fitText(font, String(text || ''), width);
    if (!value) {
        return;
    }
    const textWidth = font.widthOfTextAtSize(value, FONT_SIZE);
    const x = col.left + (col.right - col.left - textWidth) / 2;
    page.drawText(value, { x, y, size: FONT_SIZE, font, color });
}

function drawText(page, font, text, x, y, maxWidth, color = INK) {
    const value = fitText(font, String(text || ''), maxWidth);
    if (!value) {
        return;
    }
    page.drawText(value, { x, y, size: FONT_SIZE, font, color });
}

function fitText(font, text, maxWidth) {
    if (!text) {
        return '';
    }
    if (font.widthOfTextAtSize(text, FONT_SIZE) <= maxWidth) {
        return text;
    }
    let value = text;
    while (value.length > 1 && font.widthOfTextAtSize(`${value}…`, FONT_SIZE) > maxWidth) {
        value = value.slice(0, -1);
    }
    return `${value}…`;
}

async function embedBodyFont(pdf) {
    const candidates = [
        'C:/Windows/Fonts/calibri.ttf',
        'C:/Windows/Fonts/arial.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
    ];

    for (const file of candidates) {
        if (existsSync(file)) {
            try {
                return await pdf.embedFont(readFileSync(file), { subset: true });
            } catch {
                // Try the next installed face.
            }
        }
    }

    return pdf.embedFont(StandardFonts.Helvetica);
}
