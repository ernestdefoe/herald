import type Mithril from 'mithril';

/**
 * The builder's text markup (**bold**, *italic*, [link](url)) drawn on the
 * canvas as vnodes. Every piece of text is a text node, so nothing typed can
 * become markup in the forum — the canvas never uses m.trust.
 */
const INLINE = /\[([^\]\n]+)\]\(((?:[^()\s]|\([^()\s]*\))+)\)|\*\*(?!\s)(.+?)\*\*|(?<![*\w])\*(?!\s)(.+?)\*(?![*\w])|\n/g;

export function inline(text: string): Mithril.Children[] {
  const out: Mithril.Children[] = [];
  let last = 0;

  for (const match of text.matchAll(INLINE)) {
    out.push(text.slice(last, match.index));

    if (match[1] !== undefined) out.push(<u>{inline(match[1])}</u>);
    else if (match[3] !== undefined) out.push(<strong>{inline(match[3])}</strong>);
    else if (match[4] !== undefined) out.push(<em>{inline(match[4])}</em>);
    else out.push(<br />);

    last = (match.index || 0) + match[0].length;
  }

  out.push(text.slice(last));

  return out;
}

export function paragraphs(text: string): Mithril.Children {
  return String(text || '')
    .split(/\n\s*\n/)
    .filter((p) => p.trim())
    .map((p) => <p>{inline(p.trim())}</p>);
}

/**
 * Wraps the textarea's selection in markup ("**", "*"), or makes it a link.
 */
export function wrap(el: HTMLTextAreaElement, before: string, after: string, placeholder: string): string {
  const { selectionStart: start, selectionEnd: end, value } = el;
  const selected = value.slice(start, end) || placeholder;
  const next = value.slice(0, start) + before + selected + after + value.slice(end);

  requestAnimationFrame(() => {
    el.focus();
    el.setSelectionRange(start + before.length, start + before.length + selected.length);
  });

  return next;
}

export function insertAt(el: HTMLTextAreaElement, text: string): string {
  const { selectionStart: start, selectionEnd: end, value } = el;

  requestAnimationFrame(() => {
    el.focus();
    el.setSelectionRange(start + text.length, start + text.length);
  });

  return value.slice(0, start) + text + value.slice(end);
}
