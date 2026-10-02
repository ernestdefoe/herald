import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import { api, t } from '../util';

interface PreviewModalAttrs extends IInternalModalAttrs {
  /** A saved mailing, previewed exactly as stored… */
  id?: number;
  /** …or unsaved words straight from the editor. */
  subject?: string;
  content?: string;
}

/**
 * The email as it will arrive, filled in with the viewer's own details.
 *
 * Shown in a sandboxed iframe: the email's own stylesheet must not restyle the
 * forum around it, and nothing in it may run.
 */
export default class PreviewModal extends Modal<PreviewModalAttrs> {
  preview: { subject: string; html: string } | null = null;

  oninit(vnode: any) {
    super.oninit(vnode);

    const body = this.attrs.id ? { id: this.attrs.id } : { subject: this.attrs.subject, content: this.attrs.content };

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
        <p className="helpText">{t('preview.explain')}</p>
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
    );
  }
}
