import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import type { Block } from '../util';
import { api, t } from '../util';
import { clone, create, ICONS, problems, TYPES } from './blocks';
import Inspector, { BuilderMeta } from './Inspector';
import { paragraphs } from './markup';

interface BuilderAttrs {
  blocks: Block[];
  meta: BuilderMeta;
  subject: () => string;
  preheader: () => string;
  onchange: (blocks: Block[]) => void;
}

const tb = (key: string, params: Record<string, any> = {}) => t(`builder.${key}`, params);
const typing = (el: EventTarget | null) => el instanceof HTMLElement && !!el.closest('input, textarea, select, [contenteditable]');

/**
 * The drag-and-drop newsletter builder. A lazy chunk: only staff who press
 * "Design with the builder" ever download it.
 *
 * Library on the left, the email on the canvas, the selected block's
 * settings on the right. Drag from the library or click to add; drag cards
 * to reorder. Keyboard: focus a card and press Alt+↑/↓ to move it, Delete to
 * remove it; Ctrl/Cmd+Z and Shift+Ctrl/Cmd+Z undo and redo.
 *
 * The canvas is a quick drawing for arranging; Desktop and Mobile show the
 * REAL email, rendered by the server with the same code that sends it, in a
 * sandboxed frame.
 */
export default class Builder extends Component<BuilderAttrs> {
  blocks: Block[] = [];
  selected: string | null = null;
  past: string[] = [];
  future: string[] = [];
  lastTyped = 0;

  mode: 'design' | 'desktop' | 'mobile' = 'design';
  html: string | null = null;
  previewTimer?: number;

  drag: { type?: string; from?: number } | null = null;
  dropAt: number | null = null;
  focusId: string | null = null;
  announcement = '';

  oninit(vnode: Mithril.Vnode<BuilderAttrs, this>) {
    super.oninit(vnode);
    this.blocks = JSON.parse(JSON.stringify(this.attrs.blocks || []));
    this.selected = this.blocks[0]?.id || null;
  }

  /** Undo and redo work wherever focus is on the page, except inside a text field (which has its own). */
  keys = (e: KeyboardEvent) => this.shortcuts(e);

  oncreate(vnode: Mithril.VnodeDOM<BuilderAttrs, this>) {
    super.oncreate(vnode);
    document.addEventListener('keydown', this.keys);
  }

  onremove() {
    clearTimeout(this.previewTimer);
    document.removeEventListener('keydown', this.keys);
  }

  view() {
    const issues = problems(this.blocks, this.attrs.subject());
    const block = this.blocks.find((b) => b.id === this.selected);

    return (
      <div className="HeraldBuilder">
        <div className="HeraldBuilder-toolbar">
          <div className="HeraldBuilder-history">
            <Button
              className="Button Button--icon"
              icon="fas fa-undo"
              disabled={!this.past.length}
              aria-label={extractText(tb('undo'))}
              title={extractText(tb('undo'))}
              onclick={() => this.undo()}
            />
            <Button
              className="Button Button--icon"
              icon="fas fa-redo"
              disabled={!this.future.length}
              aria-label={extractText(tb('redo'))}
              title={extractText(tb('redo'))}
              onclick={() => this.redo()}
            />
          </div>
          <div className="HeraldBuilder-modes" role="tablist" aria-label={extractText(tb('view'))}>
            {(['design', 'desktop', 'mobile'] as const).map((mode) => (
              <button
                type="button"
                role="tab"
                aria-selected={this.mode === mode}
                className={`HeraldBuilder-mode ${this.mode === mode ? 'active' : ''}`}
                onclick={() => this.setMode(mode)}
              >
                <i className={{ design: 'fas fa-th-large', desktop: 'fas fa-desktop', mobile: 'fas fa-mobile-alt' }[mode]} aria-hidden="true" />
                {tb(`mode_${mode}`)}
              </button>
            ))}
          </div>
          <span className={`HeraldBuilder-attention ${issues.length ? 'HeraldBuilder-attention--on' : ''}`}>
            <i className={issues.length ? 'fas fa-exclamation-circle' : 'fas fa-check-circle'} aria-hidden="true" />
            {issues.length ? tb('attention_count', { count: issues.length }) : tb('attention_none')}
          </span>
        </div>

        <div className="HeraldBuilder-body">
          <aside className="HeraldLibrary" aria-label={extractText(tb('library'))}>
            <h4 className="HeraldBuilder-heading">{tb('library')}</h4>
            <ul>
              {TYPES.map((type) => (
                <li key={type}>
                  <button
                    type="button"
                    className="HeraldLibrary-item"
                    draggable={true}
                    title={extractText(tb(`describe.${type}`))}
                    ondragstart={(e: DragEvent) => this.dragStart(e, { type })}
                    ondragend={() => this.dragEnd()}
                    onclick={() => this.insert(type, this.insertionPoint())}
                  >
                    <i className={ICONS[type]} aria-hidden="true" />
                    <span>{tb(`blocks.${type}`)}</span>
                  </button>
                </li>
              ))}
            </ul>
            <p className="helpText">{tb('library_help')}</p>
          </aside>

          <div className="HeraldBuilder-stage">{this.mode === 'design' ? this.canvas() : this.preview()}</div>

          <aside className="HeraldBuilder-side">
            {block ? (
              <Inspector
                block={block}
                meta={this.attrs.meta}
                update={(change: (b: Block) => void, coalesce?: boolean) => this.commit(() => change(block), coalesce)}
              />
            ) : null}
            {this.attention(issues)}
          </aside>
        </div>

        <div className="visually-hidden HeraldBuilder-live" aria-live="polite">
          {this.announcement}
        </div>
      </div>
    );
  }

  canvas() {
    const logo = app.forum.attribute<string>('logoUrl');

    return (
      <div className="HeraldCanvas" ondragover={(e: DragEvent) => this.dragOver(e, this.blocks.length, true)} ondrop={(e: DragEvent) => this.drop(e)}>
        <p className="helpText HeraldCanvas-help">{tb('canvas_help')}</p>
        <div className="HeraldCanvas-email">
          <div className="HeraldCanvas-header">{logo ? <img src={logo} alt="" /> : <strong>{app.forum.attribute('title')}</strong>}</div>
          <ol className="HeraldCanvas-blocks" aria-label={extractText(tb('canvas'))}>
            {this.canvasItems()}
          </ol>
          <div className="HeraldCanvas-footer" title={extractText(tb('footer_locked'))}>
            <i className="fas fa-lock" aria-hidden="true" /> {tb('footer_note')}
          </div>
        </div>
      </div>
    );
  }

  /** Every child keyed: the drop line moves between cards as you drag. */
  canvasItems(): Mithril.Children[] {
    const drop = <li key="drop" className="HeraldCanvas-drop" aria-hidden="true" />;

    if (!this.blocks.length) {
      return [
        <li key="empty" className={`HeraldCanvas-empty ${this.drag ? 'is-target' : ''}`}>
          {tb('canvas_empty')}
        </li>,
      ];
    }

    const items: Mithril.Children[] = [];

    this.blocks.forEach((block, index) => {
      if (this.dropAt === index) items.push(drop);
      items.push(this.card(block, index));
    });

    if (this.dropAt === this.blocks.length) items.push(drop);

    return items;
  }

  card(block: Block, index: number) {
    const selected = block.id === this.selected;

    return (
      <li
        key={block.id}
        className={`HeraldCard HeraldCard--${block.type} ${selected ? 'is-selected' : ''} ${block.style.hideOnMobile ? 'is-desktopOnly' : ''} ${this.drag?.from === index ? 'is-dragging' : ''}`}
        tabindex={0}
        draggable={true}
        aria-label={extractText(tb('card_label', { type: extractText(tb(`blocks.${block.type}`)), position: index + 1, count: this.blocks.length }))}
        aria-current={selected ? 'true' : undefined}
        style={{ background: block.style.background || undefined, padding: `${Math.round(block.style.padding / 2) + 6}px 18px` }}
        oncreate={(v: any) => this.maybeFocus(v.dom, block.id)}
        onupdate={(v: any) => this.maybeFocus(v.dom, block.id)}
        onclick={() => (this.selected = block.id)}
        onkeydown={(e: KeyboardEvent) => this.cardKeys(e, block, index)}
        ondragstart={(e: DragEvent) => this.dragStart(e, { from: index })}
        ondragend={() => this.dragEnd()}
        ondragover={(e: DragEvent) => {
          const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
          this.dragOver(e, e.clientY < rect.top + rect.height / 2 ? index : index + 1);
        }}
      >
        <div className="HeraldCard-chrome">
          <span className="HeraldCard-handle" aria-hidden="true">
            <i className="fas fa-grip-vertical" />
          </span>
          <span className="HeraldCard-type">
            <i className={ICONS[block.type]} aria-hidden="true" /> {tb(`blocks.${block.type}`)}
            {block.style.hideOnMobile ? <span className="HeraldCard-flag">{tb('desktop_only')}</span> : null}
          </span>
          <span className="HeraldCard-actions">
            <Button
              className="Button Button--icon Button--flat"
              icon="far fa-copy"
              aria-label={extractText(tb('duplicate'))}
              title={extractText(tb('duplicate'))}
              onclick={(e: Event) => (e.stopPropagation(), this.duplicate(block))}
            />
            <Button
              className="Button Button--icon Button--flat"
              icon="far fa-trash-alt"
              aria-label={extractText(tb('remove'))}
              title={extractText(tb('remove'))}
              onclick={(e: Event) => (e.stopPropagation(), this.remove(block))}
            />
          </span>
        </div>
        <div className="HeraldCard-body">{this.sketch(block)}</div>
      </li>
    );
  }

  /** A quick drawing of the block. The real thing is the Desktop / Mobile view. */
  sketch(block: Block): Mithril.Children {
    const d = block.data;
    const empty = <span className="HeraldCard-placeholder">{tb(`describe.${block.type}`)}</span>;
    const button = (label: string, colour?: string, secondary?: boolean) =>
      label ? (
        <span
          className={`HeraldCard-button ${secondary ? 'HeraldCard-button--secondary' : ''}`}
          style={colour ? { '--herald-button': colour } : undefined}
        >
          {label}
        </span>
      ) : null;

    switch (block.type) {
      case 'hero':
        return d.title || d.text || d.image ? (
          <div style={{ textAlign: d.align }}>
            {d.image ? <img className="HeraldCard-image" src={d.image} alt="" /> : null}
            {d.title ? <h2>{d.title}</h2> : null}
            {paragraphs(d.text)}
            {button(d.label, d.color)}
          </div>
        ) : (
          empty
        );
      case 'text':
      case 'signoff':
        return d.text || d.name ? (
          <div style={{ textAlign: d.align }}>
            {paragraphs(d.text)}
            {d.name ? <p className="HeraldCard-name">{d.name}</p> : null}
          </div>
        ) : (
          empty
        );
      case 'image':
        return d.src ? <img className="HeraldCard-image" src={d.src} alt="" style={{ width: `${d.width}%` }} /> : empty;
      case 'imageText':
        return (
          <div className={`HeraldCard-split HeraldCard-split--${d.side}`}>
            {d.src ? (
              <img className="HeraldCard-image" src={d.src} alt="" />
            ) : (
              <span className="HeraldCard-imageSlot">
                <i className="far fa-image" />
              </span>
            )}
            <div>
              {d.title ? <h3>{d.title}</h3> : null}
              {d.text ? paragraphs(d.text) : empty}
            </div>
          </div>
        );
      case 'button':
        return <div style={{ textAlign: d.align }}>{button(d.label, d.color) || empty}</div>;
      case 'buttons':
        return (
          <div style={{ textAlign: d.align }}>
            {d.items.some((i: any) => i.label) ? d.items.map((i: any, n: number) => button(i.label, d.color, n > 0)) : empty}
          </div>
        );
      case 'divider':
        return <hr style={{ borderTopWidth: `${d.thickness}px`, borderTopColor: d.color || undefined }} />;
      case 'spacer':
        return <div className="HeraldCard-spacer" style={{ height: `${d.height}px` }} />;
      case 'social':
        return (
          <div style={{ textAlign: d.align }}>
            {d.items.length ? d.items.map((i: any) => <span className="HeraldCard-chip">{tb(`networks.${i.network}`)}</span>) : empty}
          </div>
        );
      case 'discussions':
        return (
          <div>
            {d.heading ? <h3>{d.heading}</h3> : null}
            <p className="HeraldCard-summary">
              <i className="far fa-comments" aria-hidden="true" />{' '}
              {tb('discussions_summary', { sort: extractText(tb(`options.${d.sort}`)), count: d.limit })}
              {d.tags.length ? ` · ${extractText(tb('discussions_tags', { count: d.tags.length }))}` : ''}
            </p>
            {Array.from({ length: Math.min(3, d.limit) }).map(() => (
              <div className="HeraldCard-row" />
            ))}
          </div>
        );
    }

    return null;
  }

  preview() {
    const mobile = this.mode === 'mobile';

    return (
      <div className={`HeraldBuilder-preview ${mobile ? 'is-mobile' : ''}`}>
        {this.html === null ? (
          <LoadingIndicator />
        ) : (
          <iframe
            // A fresh frame per width: its height is measured once, on load.
            key={this.mode}
            className="HeraldBuilder-frame"
            title={extractText(tb(`mode_${this.mode}`))}
            sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
            srcdoc={this.html}
            onload={(e: Event) => {
              const frame = e.target as HTMLIFrameElement;
              const doc = frame.contentDocument;
              if (!doc) return;
              frame.style.height = '0px';
              frame.style.height = Math.max(400, doc.documentElement.scrollHeight) + 'px';
            }}
          />
        )}
      </div>
    );
  }

  attention(issues: { id: string | null; key: string }[]) {
    return (
      <div className="HeraldAttention">
        <h4 className="HeraldBuilder-heading">{tb('attention')}</h4>
        {issues.length ? (
          <ul>
            {issues.map((issue) => {
              const index = this.blocks.findIndex((b) => b.id === issue.id);

              return (
                <li>
                  <button type="button" className="HeraldAttention-item" disabled={!issue.id} onclick={() => issue.id && this.select(issue.id)}>
                    <i className="fas fa-exclamation-circle" aria-hidden="true" />
                    <span>
                      {tb(`problems.${issue.key}`)}
                      {index >= 0 ? (
                        <span className="HeraldAttention-where">
                          {tb('problem_where', { type: extractText(tb(`blocks.${this.blocks[index].type}`)), position: index + 1 })}
                        </span>
                      ) : null}
                    </span>
                  </button>
                </li>
              );
            })}
          </ul>
        ) : (
          <p className="helpText">{tb('attention_none_help')}</p>
        )}
      </div>
    );
  }

  // ---- changes ------------------------------------------------------------

  /**
   * Every change goes through here, so every change can be undone. A run of
   * keystrokes within a second is one step, not one per letter.
   */
  commit(change: () => void, coalesce = false) {
    const now = Date.now();

    if (!coalesce || now - this.lastTyped > 1000) {
      this.past.push(JSON.stringify(this.blocks));
      if (this.past.length > 100) this.past.shift();
    }

    this.lastTyped = coalesce ? now : 0;
    this.future = [];
    change();
    this.changed();
  }

  changed() {
    this.attrs.onchange(JSON.parse(JSON.stringify(this.blocks)));
    if (this.mode !== 'design') this.queuePreview();
  }

  undo() {
    const previous = this.past.pop();
    if (previous === undefined) return;

    this.future.push(JSON.stringify(this.blocks));
    this.restore(previous, 'undone');
  }

  redo() {
    const next = this.future.pop();
    if (next === undefined) return;

    this.past.push(JSON.stringify(this.blocks));
    this.restore(next, 'redone');
  }

  restore(json: string, said: string) {
    this.blocks = JSON.parse(json);
    this.lastTyped = 0;
    if (!this.blocks.some((b) => b.id === this.selected)) this.selected = this.blocks[0]?.id || null;
    this.say(tb(said));
    this.changed();
  }

  insert(type: string, at: number) {
    const block = create(type);

    this.commit(() => this.blocks.splice(at, 0, block));
    this.selected = block.id;
    this.say(tb('added', { type: extractText(tb(`blocks.${type}`)), position: at + 1 }));
  }

  move(from: number, to: number) {
    if (to < 0 || to > this.blocks.length || to === from || to === from + 1) return;

    const block = this.blocks[from];

    this.commit(() => {
      this.blocks.splice(from, 1);
      this.blocks.splice(to > from ? to - 1 : to, 0, block);
    });

    const position = this.blocks.indexOf(block) + 1;
    this.say(tb('moved', { type: extractText(tb(`blocks.${block.type}`)), position, count: this.blocks.length }));
  }

  duplicate(block: Block) {
    const copy = clone(block);

    this.commit(() => this.blocks.splice(this.blocks.indexOf(block) + 1, 0, copy));
    this.selected = copy.id;
  }

  remove(block: Block) {
    const index = this.blocks.indexOf(block);

    this.commit(() => this.blocks.splice(index, 1));
    this.selected = (this.blocks[index] || this.blocks[index - 1])?.id || null;
    this.focusId = this.selected;
    this.say(tb('removed', { type: extractText(tb(`blocks.${block.type}`)) }));
  }

  select(id: string) {
    this.selected = id;
    this.focusId = id;
    if (this.mode !== 'design') this.mode = 'design';
  }

  insertionPoint(): number {
    const index = this.blocks.findIndex((b) => b.id === this.selected);

    return index >= 0 ? index + 1 : this.blocks.length;
  }

  // ---- drag and drop ------------------------------------------------------

  dragStart(e: DragEvent, drag: { type?: string; from?: number }) {
    this.drag = drag;
    // Firefox starts no drag without data.
    e.dataTransfer?.setData('text/plain', drag.type || String(drag.from));
    if (e.dataTransfer) e.dataTransfer.effectAllowed = drag.type ? 'copy' : 'move';
  }

  dragOver(e: DragEvent & { redraw?: boolean }, at: number, fallback = false) {
    if (!this.drag) return;
    e.preventDefault();

    // The canvas itself only sets "at the end" when no card claimed the spot.
    if (fallback && e.target !== e.currentTarget && (e.target as HTMLElement).closest('.HeraldCard')) {
      e.redraw = false;
      return;
    }

    if (this.dropAt === at) {
      e.redraw = false;
      return;
    }

    this.dropAt = at;
  }

  drop(e: DragEvent) {
    if (!this.drag || this.dropAt === null) return;
    e.preventDefault();

    const { type, from } = this.drag;
    const at = this.dropAt;
    this.dragEnd();

    if (type) this.insert(type, at);
    else if (from !== undefined) this.move(from, at);
  }

  dragEnd() {
    this.drag = null;
    this.dropAt = null;
  }

  // ---- keyboard -----------------------------------------------------------

  cardKeys(e: KeyboardEvent, block: Block, index: number) {
    if (e.target !== e.currentTarget) return;

    if (e.altKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) {
      e.preventDefault();
      this.focusId = block.id;
      this.move(index, e.key === 'ArrowUp' ? index - 1 : index + 2);
    } else if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
      e.preventDefault();
      const next = this.blocks[index + (e.key === 'ArrowUp' ? -1 : 1)];
      if (next) this.select(next.id);
    } else if (e.key === 'Delete' || e.key === 'Backspace') {
      e.preventDefault();
      this.remove(block);
    } else if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      this.selected = block.id;
    }
  }

  shortcuts(e: KeyboardEvent) {
    if (!(e.ctrlKey || e.metaKey) || typing(e.target)) return;

    const key = e.key.toLowerCase();

    if (key === 'z' || key === 'y') {
      e.preventDefault();
      key === 'y' || e.shiftKey ? this.redo() : this.undo();
      m.redraw();
    }
  }

  maybeFocus(dom: HTMLElement, id: string) {
    if (this.focusId === id) {
      this.focusId = null;
      dom.focus();
    }
  }

  say(text: any) {
    this.announcement = extractText(text);
  }

  // ---- preview ------------------------------------------------------------

  setMode(mode: 'design' | 'desktop' | 'mobile') {
    this.mode = mode;
    if (mode !== 'design') this.queuePreview(0);
  }

  queuePreview(delay = 500) {
    clearTimeout(this.previewTimer);
    this.previewTimer = window.setTimeout(() => {
      api<{ html: string }>('POST', '/preview', {
        subject: this.attrs.subject(),
        preheader: this.attrs.preheader(),
        type: 'builder',
        blocks: this.blocks,
      }).then((preview) => {
        this.html = preview.html;
        m.redraw();
      });
    }, delay);
  }
}
