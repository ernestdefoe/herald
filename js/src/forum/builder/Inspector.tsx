import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import extractText from 'flarum/common/utils/extractText';
import type { Block } from '../util';
import { t } from '../util';
import { Field, FIELDS } from './blocks';
import { insertAt, wrap } from './markup';

export type BuilderMeta = {
  tags: { name: string; description: string }[];
  networks: string[];
  forumTags: { id: number; name: string; parentId: number | null }[];
};

interface InspectorAttrs {
  block: Block;
  meta: BuilderMeta;
  /** Change the block. `coalesce` folds a run of keystrokes into one undo step. */
  update: (change: (block: Block) => void, coalesce?: boolean) => void;
}

const tb = (key: string, params: Record<string, any> = {}) => t(`builder.${key}`, params);

/**
 * The selected block's settings: its own fields, then the spacing, colour
 * and phone visibility every block has.
 */
export default class Inspector extends Component<InspectorAttrs> {
  uploading = false;

  view() {
    const block = this.attrs.block;

    return (
      <div className="HeraldInspector" key={block.id}>
        <h4 className="HeraldInspector-title">{tb(`blocks.${block.type}`)}</h4>
        {FIELDS[block.type].map((field) => this.field(field))}
        <fieldset className="HeraldInspector-style">
          <legend>{tb('style.heading')}</legend>
          {this.range(tb('style.padding'), block.style.padding, 0, 64, (v) => this.attrs.update((b) => (b.style.padding = v), true), 'px')}
          {this.colour(tb('style.background'), block.style.background, (v) => this.attrs.update((b) => (b.style.background = v)))}
          <Switch state={block.style.hideOnMobile} onchange={(v: boolean) => this.attrs.update((b) => (b.style.hideOnMobile = v))}>
            {tb('style.hide_mobile')}
          </Switch>
        </fieldset>
      </div>
    );
  }

  field(field: Field) {
    const block = this.attrs.block;
    const value = block.data[field.key];
    const id = `herald-f-${block.id}-${field.key}`;
    const set = (v: any, coalesce = false) => this.attrs.update((b) => (b.data[field.key] = v), coalesce);
    const label = tb(`fields.${field.key}`);
    const help = field.help ? <p className="helpText">{tb(`help.${block.type}_${field.key}`)}</p> : null;
    const wrapField = (control: any) => (
      <div className="Form-group">
        <label for={id}>{label}</label>
        {control}
        {help}
      </div>
    );

    switch (field.kind) {
      case 'text':
        return wrapField(<input id={id} className="FormControl" value={value} oninput={(e: any) => set(e.target.value, true)} />);

      case 'url':
        return wrapField(
          <input
            id={id}
            className="FormControl"
            value={value}
            placeholder="https://"
            spellcheck={false}
            oninput={(e: any) => set(e.target.value, true)}
          />
        );

      case 'markup':
        return wrapField(this.markup(id, value, set));

      case 'image':
        return wrapField(this.image(id, value, set));

      case 'color':
        return this.colour(label, value, (v) => set(v));

      case 'number':
        return this.range(label, value, field.min!, field.max!, (v) => set(v, true), field.key === 'width' ? '%' : field.key === 'limit' ? '' : 'px');

      case 'toggle':
        return (
          <div className="Form-group">
            <Switch state={!!value} onchange={(v: boolean) => set(v)}>
              {label}
            </Switch>
            {help}
          </div>
        );

      case 'select':
        return wrapField(
          <select id={id} className="FormControl" value={value} onchange={(e: any) => set(e.target.value)}>
            {field.options!.map((option) => (
              <option value={option}>{tb(`options.${option}`)}</option>
            ))}
          </select>
        );

      case 'tags':
        return this.tags(label, value, set, help);

      case 'links':
        return this.list(label, value, field.max!, set, { label: '', url: '' }, (item, change) => [
          <input
            className="FormControl"
            value={item.label}
            placeholder={extractText(tb('fields.label'))}
            oninput={(e: any) => change({ label: e.target.value })}
          />,
          <input className="FormControl" value={item.url} placeholder="https://" oninput={(e: any) => change({ url: e.target.value })} />,
        ]);

      case 'social':
        return this.list(label, value, field.max!, set, { network: 'website', url: '' }, (item, change) => [
          <select className="FormControl" value={item.network} onchange={(e: any) => change({ network: e.target.value })}>
            {this.attrs.meta.networks.map((network) => (
              <option value={network}>{tb(`networks.${network}`)}</option>
            ))}
          </select>,
          <input className="FormControl" value={item.url} placeholder="https://" oninput={(e: any) => change({ url: e.target.value })} />,
        ]);
    }
  }

  markup(id: string, value: string, set: (v: any, coalesce?: boolean) => void) {
    let el: HTMLTextAreaElement | null = null;
    const apply = (next: string) => set(next);

    return (
      <div className="HeraldMarkup">
        <div className="HeraldMarkup-tools" role="toolbar" aria-label={extractText(tb('markup.toolbar'))}>
          <Button
            className="Button Button--icon Button--flat"
            icon="fas fa-bold"
            aria-label={extractText(tb('markup.bold'))}
            onclick={() => el && apply(wrap(el, '**', '**', extractText(tb('markup.bold'))))}
          />
          <Button
            className="Button Button--icon Button--flat"
            icon="fas fa-italic"
            aria-label={extractText(tb('markup.italic'))}
            onclick={() => el && apply(wrap(el, '*', '*', extractText(tb('markup.italic'))))}
          />
          <Button
            className="Button Button--icon Button--flat"
            icon="fas fa-link"
            aria-label={extractText(tb('markup.link'))}
            onclick={() => {
              if (!el) return;
              const url = window.prompt(extractText(tb('markup.link_prompt')), 'https://');
              if (url) apply(wrap(el, '[', `](${url.trim()})`, extractText(tb('markup.link_text'))));
            }}
          />
          <select
            className="FormControl HeraldMarkup-tags"
            aria-label={extractText(tb('markup.insert_tag'))}
            onchange={(e: any) => {
              if (el && e.target.value) apply(insertAt(el, `{${e.target.value}}`));
              e.target.value = '';
            }}
          >
            <option value="">{tb('markup.insert_tag')}</option>
            {this.attrs.meta.tags
              .filter((tag) => tag.name !== 'latest_discussions')
              .map((tag) => (
                <option value={tag.name}>{`{${tag.name}}`}</option>
              ))}
          </select>
        </div>
        <textarea
          id={id}
          className="FormControl"
          rows={6}
          value={value}
          oncreate={(v: any) => (el = v.dom)}
          onupdate={(v: any) => (el = v.dom)}
          oninput={(e: any) => set(e.target.value, true)}
        />
        <p className="helpText">{tb('markup.help')}</p>
      </div>
    );
  }

  image(id: string, value: string, set: (v: any) => void) {
    return (
      <div className="HeraldImageField">
        {value ? <img className="HeraldImageField-preview" src={value} alt="" /> : null}
        <input id={id} className="FormControl" value={value} placeholder="https://" spellcheck={false} oninput={(e: any) => set(e.target.value)} />
        <div className="HeraldImageField-actions">
          <label className="Button Button--block HeraldImageField-upload">
            <i className={`icon ${this.uploading ? 'fas fa-spinner fa-spin' : 'fas fa-upload'} Button-icon`} aria-hidden="true" />
            <span className="Button-label">{tb('upload')}</span>
            <input type="file" accept="image/png,image/jpeg,image/gif,image/webp" hidden onchange={(e: any) => this.upload(e.target, set)} />
          </label>
        </div>
        <p className="helpText">{tb('help.image')}</p>
      </div>
    );
  }

  upload(input: HTMLInputElement, set: (v: any) => void) {
    const file = input.files?.[0];
    if (!file) return;

    const body = new FormData();
    body.append('image', file);
    this.uploading = true;
    m.redraw();

    app
      .request<{ url: string }>({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/herald/images',
        serialize: (raw: any) => raw,
        body,
      })
      .then((response) => set(response.url))
      .finally(() => {
        this.uploading = false;
        input.value = '';
        m.redraw();
      });
  }

  colour(label: any, value: string, set: (v: string) => void) {
    return (
      <div className="Form-group HeraldColour">
        <label>{label}</label>
        <div className="HeraldColour-row">
          <input type="color" value={value || '#ffffff'} aria-label={extractText(label)} oninput={(e: any) => set(e.target.value)} />
          <span className="HeraldColour-value">{value || tb('default_colour')}</span>
          {value ? (
            <Button className="Button Button--link" onclick={() => set('')}>
              {tb('reset')}
            </Button>
          ) : null}
        </div>
      </div>
    );
  }

  range(label: any, value: number, min: number, max: number, set: (v: number) => void, unit: string) {
    return (
      <div className="Form-group HeraldRange">
        <label>
          {label} <span className="HeraldRange-value">{value + unit}</span>
        </label>
        <input type="range" min={min} max={max} value={value} aria-valuetext={value + unit} oninput={(e: any) => set(Number(e.target.value))} />
      </div>
    );
  }

  tags(label: any, value: number[], set: (v: any) => void, help: any) {
    const tags = this.attrs.meta.forumTags;
    const toggle = (id: number, on: boolean) => set(on ? [...value, id] : value.filter((v) => v !== id));
    const row = (tag: { id: number; name: string; parentId: number | null }) => (
      <label className={`HeraldTagPick ${tag.parentId ? 'HeraldTagPick--child' : ''}`}>
        <input type="checkbox" checked={value.includes(tag.id)} onchange={(e: any) => toggle(tag.id, e.target.checked)} />
        {tag.name}
      </label>
    );

    return (
      <fieldset className="Form-group HeraldTagPicks">
        <legend>{label}</legend>
        {tags.length
          ? tags.filter((tag) => !tag.parentId).map((parent) => [row(parent), tags.filter((tag) => tag.parentId === parent.id).map(row)])
          : null}
        {help}
      </fieldset>
    );
  }

  list(
    label: any,
    items: any[],
    max: number,
    set: (v: any, coalesce?: boolean) => void,
    blank: any,
    controls: (item: any, change: (patch: any) => void) => any[]
  ) {
    return (
      <fieldset className="Form-group HeraldItems">
        <legend>{label}</legend>
        {items.map((item, index) => (
          <div className="HeraldItems-row">
            {controls(item, (patch) =>
              set(
                items.map((it, i) => (i === index ? { ...it, ...patch } : it)),
                true
              )
            )}
            <Button
              className="Button Button--icon Button--flat"
              icon="fas fa-times"
              aria-label={extractText(tb('remove_item'))}
              onclick={() => set(items.filter((_, i) => i !== index))}
            />
          </div>
        ))}
        {items.length < max ? (
          <Button className="Button Button--link" icon="fas fa-plus" onclick={() => set([...items, { ...blank }])}>
            {tb('add_item')}
          </Button>
        ) : null}
      </fieldset>
    );
  }
}
