import type { Block } from '../util';

/**
 * The builder's side of the block allow-list (src/Builder/Blocks.php is the
 * one that counts): what each block starts as, and which settings its panel
 * shows. Every label comes from the locale file, keyed by these names.
 */

export type FieldKind = 'text' | 'markup' | 'url' | 'image' | 'color' | 'select' | 'number' | 'toggle' | 'tags' | 'links' | 'social';

export type Field = { key: string; kind: FieldKind; options?: string[]; min?: number; max?: number; help?: boolean };

export const TYPES = ['hero', 'text', 'image', 'imageText', 'button', 'buttons', 'divider', 'spacer', 'social', 'discussions', 'signoff'] as const;

export const ICONS: Record<string, string> = {
  hero: 'fas fa-heading',
  text: 'fas fa-align-left',
  image: 'far fa-image',
  imageText: 'fas fa-columns',
  button: 'fas fa-hand-pointer',
  buttons: 'fas fa-grip-lines',
  divider: 'fas fa-minus',
  spacer: 'fas fa-arrows-alt-v',
  social: 'fas fa-share-alt',
  discussions: 'far fa-comments',
  signoff: 'fas fa-signature',
};

const align = (...options: string[]): Field => ({ key: 'align', kind: 'select', options });

export const FIELDS: Record<string, Field[]> = {
  hero: [
    { key: 'title', kind: 'text' },
    { key: 'text', kind: 'markup' },
    { key: 'image', kind: 'image' },
    { key: 'alt', kind: 'text', help: true },
    { key: 'label', kind: 'text' },
    { key: 'url', kind: 'url' },
    align('center', 'left'),
    { key: 'color', kind: 'color' },
  ],
  text: [{ key: 'text', kind: 'markup' }, align('left', 'center')],
  image: [
    { key: 'src', kind: 'image' },
    { key: 'alt', kind: 'text', help: true },
    { key: 'link', kind: 'url' },
    { key: 'width', kind: 'number', min: 10, max: 100 },
  ],
  imageText: [
    { key: 'src', kind: 'image' },
    { key: 'alt', kind: 'text', help: true },
    { key: 'title', kind: 'text' },
    { key: 'text', kind: 'markup' },
    { key: 'link', kind: 'url' },
    { key: 'side', kind: 'select', options: ['left', 'right'] },
  ],
  button: [{ key: 'label', kind: 'text' }, { key: 'url', kind: 'url' }, align('center', 'left', 'right'), { key: 'color', kind: 'color' }],
  buttons: [{ key: 'items', kind: 'links', max: 4 }, align('center', 'left', 'right'), { key: 'color', kind: 'color' }],
  divider: [
    { key: 'color', kind: 'color' },
    { key: 'thickness', kind: 'number', min: 1, max: 4 },
  ],
  spacer: [{ key: 'height', kind: 'number', min: 8, max: 96 }],
  social: [{ key: 'items', kind: 'social', max: 14 }, align('center', 'left', 'right')],
  discussions: [
    { key: 'heading', kind: 'text' },
    { key: 'sort', kind: 'select', options: ['newest', 'active', 'trending'] },
    { key: 'limit', kind: 'number', min: 1, max: 10 },
    { key: 'layout', kind: 'select', options: ['compact', 'excerpt'] },
    { key: 'tags', kind: 'tags', help: true },
    { key: 'children', kind: 'toggle' },
    { key: 'skipEarlier', kind: 'toggle', help: true },
  ],
  signoff: [
    { key: 'text', kind: 'markup' },
    { key: 'name', kind: 'text' },
  ],
};

const DEFAULTS: Record<string, Record<string, any>> = {
  hero: { title: '', text: '', image: '', alt: '', label: '', url: '', align: 'center', color: '' },
  text: { text: '', align: 'left' },
  image: { src: '', alt: '', link: '', width: 100 },
  imageText: { src: '', alt: '', title: '', text: '', link: '', side: 'left' },
  button: { label: '', url: '{suite_url}', align: 'center', color: '' },
  buttons: { items: [{ label: '', url: '' }], align: 'center', color: '' },
  divider: { color: '', thickness: 1 },
  spacer: { height: 24 },
  social: { items: [{ network: 'website', url: '{suite_url}' }], align: 'center' },
  discussions: { heading: '', tags: [], children: true, sort: 'active', limit: 5, layout: 'compact', skipEarlier: false },
  signoff: { text: '', name: '' },
};

export function uid(): string {
  return 'b' + Math.random().toString(36).slice(2, 10);
}

export function create(type: string): Block {
  return {
    id: uid(),
    type,
    data: JSON.parse(JSON.stringify(DEFAULTS[type])),
    style: { padding: type === 'spacer' || type === 'divider' ? 8 : 16, background: '', hideOnMobile: false },
  };
}

export function clone(block: Block): Block {
  return { ...JSON.parse(JSON.stringify(block)), id: uid() };
}

/**
 * What the "Needs attention" panel lists: things that would go out broken or
 * not at all. Each points at its block.
 */
export function problems(blocks: Block[], subject: string): { id: string | null; key: string }[] {
  const out: { id: string | null; key: string }[] = [];

  if (!subject.trim()) out.push({ id: null, key: 'no_subject' });
  if (!blocks.length) out.push({ id: null, key: 'no_blocks' });

  blocks.forEach((b) => {
    const d = b.data;
    const add = (key: string) => out.push({ id: b.id, key });

    if ((b.type === 'image' || b.type === 'imageText') && !d.src) add('no_image');
    if (((b.type === 'image' || b.type === 'imageText') && d.src && !d.alt) || (b.type === 'hero' && d.image && !d.alt)) add('no_alt');
    if ((b.type === 'text' || b.type === 'signoff') && !String(d.text || '').trim() && !d.name) add('empty_text');
    if (b.type === 'hero' && !d.title && !d.text && !d.image) add('empty_hero');
    if (b.type === 'button' && (!d.label || !d.url)) add('button_incomplete');
    if ((b.type === 'hero' && !!d.label !== !!d.url) || (b.type === 'buttons' && d.items.some((i: any) => !i.label || !i.url)))
      add('button_incomplete');
    if (b.type === 'social' && (!d.items.length || d.items.some((i: any) => !i.url))) add('social_incomplete');
  });

  return out;
}
