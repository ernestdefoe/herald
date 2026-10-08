import app from 'flarum/forum/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import { api, Counts, t } from '../util';

interface SendModalAttrs extends IInternalModalAttrs {
  mailingId: number;
  subject: string;
  filters: Record<string, any>;
  resend: boolean;
  onsend: (response: any) => void;
}

/**
 * The last step: a number, then a button. An email cannot be recalled, so the
 * confirmation says exactly how many people it is about to reach.
 */
export default class SendModal extends Modal<SendModalAttrs> {
  counts: Counts | null = null;
  sending = false;

  oninit(vnode: any) {
    super.oninit(vnode);

    api<Counts>('POST', '/count', { filters: this.attrs.filters }).then((counts) => {
      this.counts = counts;
      m.redraw();
    });
  }

  className() {
    return 'HeraldSendModal Modal--small';
  }

  title() {
    return this.attrs.resend ? t('send.resend_title') : t('send.title');
  }

  content() {
    const counts = this.counts;

    return (
      <div className="Modal-body">
        {!counts ? (
          <LoadingIndicator />
        ) : (
          <div className="Form Form--centered">
            <p className="HeraldSend-reach">{t('send.reach', { count: counts.reach, formatted: counts.reach.toLocaleString(app.data.locale) })}</p>
            <p className="HeraldSend-subject">“{this.attrs.subject}”</p>
            {counts.optedOut || counts.unconfirmed ? (
              <p className="helpText">
                {t('send.excluded', {
                  optedOut: counts.optedOut.toLocaleString(app.data.locale),
                  unconfirmed: counts.unconfirmed.toLocaleString(app.data.locale),
                })}
              </p>
            ) : null}
            <p className="helpText">{t('send.cannot_recall')}</p>
            <div className="Form-group">
              <Button
                className="Button Button--primary Button--block"
                icon="fas fa-paper-plane"
                loading={this.sending}
                disabled={counts.reach === 0}
                onclick={() => this.send()}
              >
                {counts.reach === 0 ? t('send.nobody') : t('send.confirm', { count: counts.reach })}
              </Button>
            </div>
          </div>
        )}
      </div>
    );
  }

  send() {
    this.sending = true;

    api('POST', `/mailings/${this.attrs.mailingId}/send`)
      .then((response) => {
        this.hide();
        this.attrs.onsend(response);
      })
      .catch(() => {
        this.sending = false;
        m.redraw();
      });
  }
}
