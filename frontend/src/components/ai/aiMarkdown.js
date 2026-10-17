// Render markdown tối giản cho câu trả lời của trợ lý AI.
// Luôn escape HTML trước rồi mới thêm thẻ, nên nội dung từ model không thể chèn script.

const escapeHtml = (s) =>
  s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

const inline = (s) =>
  s
    .replace(/`([^`]+)`/g, '<code class="px-1 rounded bg-slate-100 text-[12px]">$1</code>')
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');

const isTableRow = (line) => /^\s*\|.*\|\s*$/.test(line);
const isDivider = (line) => /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/.test(line);
const cells = (line) => line.trim().replace(/^\||\|$/g, '').split('|').map((c) => inline(c.trim()));

export function renderMarkdown(text) {
  const lines = escapeHtml(text || '').split('\n');
  const out = [];
  let i = 0;
  while (i < lines.length) {
    const line = lines[i];
    if (isTableRow(line) && i + 1 < lines.length && isDivider(lines[i + 1])) {
      const head = cells(line);
      i += 2;
      const body = [];
      while (i < lines.length && isTableRow(lines[i])) body.push(cells(lines[i++]));
      out.push(
        '<div class="overflow-x-auto my-2"><table class="w-full text-[12px] border-collapse">' +
          `<thead><tr>${head.map((h) => `<th class="text-left font-semibold bg-slate-100 border border-slate-200 px-2 py-1">${h}</th>`).join('')}</tr></thead>` +
          `<tbody>${body.map((r) => `<tr>${r.map((c) => `<td class="border border-slate-200 px-2 py-1 align-top">${c}</td>`).join('')}</tr>`).join('')}</tbody>` +
          '</table></div>'
      );
      continue;
    }
    if (/^\s*[-*]\s+/.test(line) || /^\s*\d+\.\s+/.test(line)) {
      const ordered = /^\s*\d+\.\s+/.test(line);
      const items = [];
      while (i < lines.length && (ordered ? /^\s*\d+\.\s+/ : /^\s*[-*]\s+/).test(lines[i])) {
        items.push(`<li>${inline(lines[i].replace(/^\s*([-*]|\d+\.)\s+/, ''))}</li>`);
        i++;
      }
      out.push(`<${ordered ? 'ol' : 'ul'} class="${ordered ? 'list-decimal' : 'list-disc'} pl-5 my-1 space-y-0.5">${items.join('')}</${ordered ? 'ol' : 'ul'}>`);
      continue;
    }
    const heading = line.match(/^(#{1,4})\s+(.*)$/);
    if (heading) {
      out.push(`<div class="font-semibold mt-2 mb-1">${inline(heading[2])}</div>`);
    } else if (line.trim()) {
      out.push(`<p class="my-1">${inline(line)}</p>`);
    }
    i++;
  }
  return out.join('');
}
