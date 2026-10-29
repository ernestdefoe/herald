import app from 'flarum/forum/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import extractText from 'flarum/common/utils/extractText';
import { api, t } from '../util';

interface PreviewModalAttrs extends IInternalModalAttrs {
  /** A saved mailing, previewed exactly as stored… */
  id?: number;
  /** …or unsaved work straight from the page. */
  body?: Record<string, any>;
}

/**
 * The email as it will arrive, filled in with the viewer's own details — or
 * a chosen member's — at desktop or phone width.
 *
 * Shown in a sandboxed iframe: the email's own stylesheet must not restyle the
 * forum around it, and nothing in it may run.
 */
export default class PreviewModal extends Modal<PreviewModalAttrs> {
  preview: { subject: string; html: string } | null = null;
  width: 'desktop' | 'mobile' = 'desktop';
  as = '';
  asId: number | null = null;
  asError = false;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.load();
  }

  load() {
    const body = { ...(this.attrs.id ? { id: this.attrs.id } : this.attrs.body), userId: this.asId || undefined };

    api('POST', '/preview', body)
      .then((preview: any) => {
        this.preview = preview;
        m.redraw();
      })
      .catch(() => this.hide());
  }

  className() {
    return 'HeraldPreviewModal Modal--large';
  }

  title() {
    return t('preview.title');
  }

  content() {
    if (!this.preview) {
      return (
        <div className="Modal-body">
          <LoadingIndicator />
        </div>
      );
    }

    return (
      <div className="Modal-body">
        <div className="HeraldPreview-subject">
          <span className="HeraldPreview-label">{t('preview.subject')}</span> {this.preview.subject || <em>{t('preview.no_subject')}</em>}
        </div>
        <div className="HeraldPreview-controls">
          <div className="ButtonGroup" role="group" aria-label={extractText(t('preview.width'))}>
            {(['desktop', 'mobile'] as const).map((width) => (
              <Button
                className={`Button ${this.width === width ? 'active' : ''}`}
                icon={width === 'desktop' ? 'fas fa-desktop' : 'fas fa-mobile-alt'}
                aria-pressed={this.width === width}
                onclick={() => (this.width = width)}
              >
                {t(`preview.${width}`)}
              </Button>
            ))}
          </div>
          <form className="HeraldPreview-as" onsubmit={(e: Event) => this.previewAs(e)}>
            <input
              className="FormControl"
              value={this.as}
              placeholder={extractText(t('preview.as_placeholder'))}
              aria-label={extractText(t('preview.as_label'))}
              oninput={(e: any) => (this.as = e.target.value)}
            />
            <Button type="submit" className="Button">
              {t('preview.as_button')}
            </Button>
          </form>
        </div>
        <p className="helpText">
          {this.asError ? t('preview.as_not_found') : this.asId ? t('preview.explain_member', { name: this.as }) : t('preview.explain')}
        </p>
        <div className={`HeraldPreview-stage ${this.width === 'mobile' ? 'is-mobile' : ''}`}>
          <iframe
            className="HeraldPreview-frame"
            sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
            srcdoc={this.preview.html}
            title={t('preview.title')}
            onload={(e: Event) => {
              // Size the frame to the email, so the modal scrolls rather than
              // a frame within a frame. Collapse it first: a document's
              // scrollHeight is never less than the frame it sits in, so
              // measuring at full height would always answer "full height".
              const frame = e.target as HTMLIFrameElement;
              const doc = frame.contentDocument;
              if (!doc) return;
              frame.style.height = '0px';
              frame.style.height = Math.max(160, doc.documentElement.scrollHeight) + 'px';
            }}
          />
        </div>
      </div>
    );
  }

  previewAs(e: Event) {
    e.preventDefault();
    const username = this.as.trim();

    if (!username) {
      this.asId = null;
      this.asError = false;
      this.load();
      return;
    }

    app.store.find<any[]>('users', { filter: { q: username }, page: { limit: 20 } } as any).then(
      (users: any) => {
        const user = (Array.isArray(users) ? users : [users]).find((u: any) => u && u.username().toLowerCase() === username.toLowerCase());
        this.asError = !user;
        this.asId = user ? Number(user.id()) : null;
        user ? this.load() : m.redraw();
      },
      () => {
        this.asError = true;
        m.redraw();
      }
    );
  }
}
