import Component from 'flarum/common/Component';
import { Mailing, percent, t } from '../util';

export default class StatusBadge extends Component<{ mailing: Mailing }> {
  view() {
    const mailing = this.attrs.mailing;
    const label =
      mailing.status === 'sending'
        ? t('status.sending_percent', { percent: percent(mailing.sentCount + mailing.failedCount, mailing.recipientTotal) })
        : t(`status.${mailing.status}`);

    return <span className={`HeraldStatus HeraldStatus--${mailing.status}`}>{label}</span>;
  }
}
