import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import { t } from '../util';

export type QuickTag = { name: string; description: string };

interface QuickTagsAttrs {
  tags: QuickTag[];
  aliases: Record<string, string>;
  oninsert: (text: string) => void;
}

/**
 * Invision's "Quick Tags" panel: every tag with what it becomes, one click to
 * put it where the cursor is.
 */
export default class QuickTags extends Component<QuickTagsAttrs> {
  view() {
    const aliases = Object.keys(this.attrs.aliases || {});

    return (
      <div className="HeraldQuickTags">
        <h4 className="HeraldQuickTags-title">{t('tags.title')}</h4>
        <p className="helpText">{t('tags.help')}</p>
        <ul className="HeraldQuickTags-list">
          {this.attrs.tags.map((tag) => (
            <li key={tag.name}>
              <button
                type="button"
                className="HeraldQuickTags-tag"
                // Keep the editor's selection: a mousedown that takes focus
                // would move the cursor before the click inserts at it.
                onmousedown={(e: MouseEvent) => e.preventDefault()}
                onclick={() => this.attrs.oninsert(`{${tag.name}}`)}
              >
                <code>{`{${tag.name}}`}</code>
                <span className="HeraldQuickTags-description">{app.translator.trans(tag.description)}</span>
              </button>
            </li>
          ))}
        </ul>
        {aliases.length ? (
          <p className="helpText HeraldQuickTags-aliases">{t('tags.aliases', { aliases: aliases.map((a) => `{${a}}`).join(', ') })}</p>
        ) : null}
      </div>
    );
  }
}
