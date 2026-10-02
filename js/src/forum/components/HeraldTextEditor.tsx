import TextEditor from 'flarum/common/components/TextEditor';

/**
 * The forum's own editor, minus its submit button — the page has its own
 * Save / Preview / Send bar, and a second Save inside the editor would just
 * be the same button twice.
 *
 * A subclass, so everything other extensions patch onto TextEditor.prototype
 * (Scribe's buildEditor, Markdown's toolbar, mentions, emoji, uploads) is
 * still inherited.
 */
export default class HeraldTextEditor extends TextEditor {
  controlItems() {
    const items = super.controlItems();

    items.remove('submit');

    return items;
  }
}
