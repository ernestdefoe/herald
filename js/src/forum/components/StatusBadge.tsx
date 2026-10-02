import Component from 'flarum/common/Component';
import { Mailing, percent, t } from '../util';

export default class StatusBadge extends Component<{ mailing: Mailing }> {
  view() {
    const mailing = this.attrs.mailing;
    // "Sent" over a mailing that reached nobody would be a lie in green.
    const status = mailing.status === 'sent' && mailing.sentCount === 0 && mailing.failedCount > 0 ? 'failed' : mailing.status;
    const label =
      mailing.status === 'sending'
        ? t('status.sending_percent', { percent: percent(mailing.sentCount + mailing.failedCount, mailing.recipientTotal) })
        : t(`status.${status}`);

    return <span className={`HeraldStatus HeraldStatus--${status}`}>{label}</span>;
  }
}
