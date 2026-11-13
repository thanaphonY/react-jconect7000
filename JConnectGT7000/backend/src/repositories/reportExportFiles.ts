import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

export type ExportColumn = { key: string; label: string };
export type ExportRow = Record<string, unknown>;

function csvEscape(value: unknown, delimiter: string): string {
  const text = value == null ? '' : String(value);
  if (text.includes('"') || text.includes('\n') || text.includes('\r') || text.includes(delimiter)) {
    return `"${text.replaceAll('"', '""')}"`;
  }
  return text;
}

export function buildCsvContent(
  cols: ExportColumn[],
  rows: ExportRow[],
  delimiterRaw: string,
): Buffer {
  const delimiter = delimiterRaw === 'tab' ? '\t' : delimiterRaw || ',';
  const lines: string[] = [];
  lines.push(cols.map((c) => csvEscape(c.label, delimiter)).join(delimiter));
  for (const row of rows) {
    lines.push(cols.map((c) => csvEscape(row[c.key], delimiter)).join(delimiter));
  }
  return Buffer.from(`\uFEFF${lines.join('\n')}`, 'utf8');
}

function xmlEscape(value: unknown): string {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;');
}

/** SpreadsheetML 2003 (.xls) — opens in Excel without extra deps. */
export function buildExcelXml(
  cols: ExportColumn[],
  rows: ExportRow[],
  sheetName: string,
): Buffer {
  const safeSheet = (sheetName || 'Sheet1').slice(0, 31).replace(/[\\/*?[\]:]/g, '_');
  const header = cols
    .map((c) => `<Cell><Data ss:Type="String">${xmlEscape(c.label)}</Data></Cell>`)
    .join('');
  const body = rows
    .map((row) => {
      const cells = cols
        .map((c) => {
          const v = row[c.key];
          if (v == null || v === '') {
            return '<Cell><Data ss:Type="String"></Data></Cell>';
          }
          if (typeof v === 'number' || (typeof v === 'string' && v !== '' && !Number.isNaN(Number(v)) && /^-?\d+(\.\d+)?$/.test(String(v).trim()))) {
            return `<Cell><Data ss:Type="Number">${xmlEscape(v)}</Data></Cell>`;
          }
          return `<Cell><Data ss:Type="String">${xmlEscape(v)}</Data></Cell>`;
        })
        .join('');
      return `<Row>${cells}</Row>`;
    })
    .join('');
  const xml = `<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 <Worksheet ss:Name="${xmlEscape(safeSheet)}">
  <Table>
   <Row>${header}</Row>
   ${body}
  </Table>
 </Worksheet>
</Workbook>`;
  return Buffer.from(xml, 'utf8');
}

function pdfEscape(text: string): string {
  return text.replaceAll('\\', '\\\\').replaceAll('(', '\\(').replaceAll(')', '\\)');
}

/** Minimal landscape/portrait text-table PDF. */
export function buildSimplePdf(
  title: string,
  cols: ExportColumn[],
  rows: ExportRow[],
  orientation: 'portrait' | 'landscape',
): Buffer {
  const pageW = orientation === 'landscape' ? 842 : 595;
  const pageH = orientation === 'landscape' ? 595 : 842;
  const margin = 40;
  const fontSize = 8;
  const lineH = 11;
  const usableW = pageW - margin * 2;
  const colW = Math.max(40, Math.floor(usableW / Math.max(1, cols.length)));

  type PageLines = string[];
  const pages: PageLines[] = [];
  let current: PageLines = [];
  let y = pageH - margin;

  const pushLine = (parts: string[]) => {
    if (y < margin + lineH) {
      pages.push(current);
      current = [];
      y = pageH - margin;
    }
    let x = margin;
    const cmds: string[] = [];
    for (let i = 0; i < parts.length; i += 1) {
      const cell = (parts[i] ?? '').slice(0, Math.max(4, Math.floor(colW / 4.5)));
      cmds.push(`BT /F1 ${fontSize} Tf ${x} ${y} Td (${pdfEscape(cell)}) Tj ET`);
      x += colW;
    }
    current.push(...cmds);
    y -= lineH;
  };

  pushLine([title.slice(0, 80)]);
  y -= 4;
  pushLine(cols.map((c) => c.label));
  for (const row of rows.slice(0, 2000)) {
    pushLine(cols.map((c) => (row[c.key] == null ? '' : String(row[c.key]))));
  }
  if (current.length) pages.push(current);

  const objects: string[] = [];
  objects.push('1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n');
  const kids: string[] = [];
  let objNum = 3;
  const pageObjs: { num: number; contentNum: number; content: string }[] = [];

  for (const page of pages) {
    const content = page.join('\n');
    const contentNum = objNum + 1;
    pageObjs.push({ num: objNum, contentNum, content });
    kids.push(`${objNum} 0 R`);
    objNum += 2;
  }
  const fontNum = objNum;
  objects.push(
    `2 0 obj<< /Type /Pages /Kids [${kids.join(' ')}] /Count ${pages.length} >>endobj\n`,
  );

  const bodyParts: string[] = [];
  bodyParts.push('%PDF-1.4\n');
  const offsets: number[] = [0];
  let offset = Buffer.byteLength(bodyParts[0]!, 'utf8');

  const addObj = (text: string) => {
    offsets.push(offset);
    bodyParts.push(text);
    offset += Buffer.byteLength(text, 'utf8');
  };

  addObj(objects[0]!);
  addObj(objects[1]!);

  for (const p of pageObjs) {
    addObj(
      `${p.num} 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${pageW} ${pageH}] /Contents ${p.contentNum} 0 R /Resources << /Font << /F1 ${fontNum} 0 R >> >> >>endobj\n`,
    );
    const stream = p.content;
    addObj(
      `${p.contentNum} 0 obj<< /Length ${Buffer.byteLength(stream, 'utf8')} >>stream\n${stream}\nendstream\nendobj\n`,
    );
  }
  addObj(`${fontNum} 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj\n`);

  const xrefStart = offset;
  let xref = `xref\n0 ${offsets.length}\n0000000000 65535 f \n`;
  for (let i = 1; i < offsets.length; i += 1) {
    xref += `${String(offsets[i]).padStart(10, '0')} 00000 n \n`;
  }
  bodyParts.push(xref);
  bodyParts.push(
    `trailer<< /Size ${offsets.length} /Root 1 0 R >>\nstartxref\n${xrefStart}\n%%EOF\n`,
  );
  return Buffer.from(bodyParts.join(''), 'utf8');
}

export async function ensureExportDir(exportRoot: string, subdir: string): Promise<string> {
  const dir = path.join(exportRoot, subdir);
  await mkdir(dir, { recursive: true });
  return dir;
}

export async function writeExportBuffer(
  exportRoot: string,
  relPath: string,
  content: Buffer,
): Promise<number> {
  const full = path.join(exportRoot, relPath);
  await mkdir(path.dirname(full), { recursive: true });
  await writeFile(full, content);
  return content.length;
}

export function slugifyTemplateName(name: string): string {
  return (name || 'report').replace(/[^a-zA-Z0-9_-]/g, '_') || 'report';
}
