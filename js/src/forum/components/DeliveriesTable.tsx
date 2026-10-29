import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import extractText from 'flarum/common/utils/extractText';
import { api, Mailing, t } from '../util';
import { when } from '../when';

type Delivery = {
  id: number;
  userId: number | null;
  username: string | null;
  email: string | null;
  status: string;
  attempts: number;
  error: string | null;
  sentAt: string | null;
  nextAttemptAt: string | null;
};

const STATUSES = ['sent', 'failed', 'skipped', 'unknown', 'queued'];

/**
 * What happened to every copy of a mailing: one row per recipient, 50 a
 * page, filtered by outcome, with Resend to failed once it has finished.
 */
export default class DeliveriesTable extends Component<{ mailing: Mailing; onresend: (response: any) => void }> {
  rows: Delivery[] | null = null;
  total = 0;
  perPage = 50;
  page = 1;
  status = '';
  outcomes: Record<string, number> = {};
  resending = false;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.load();
  }

  load() {
    this.rows = null;

    api<any>('GET', `/mailings/${this.attrs.mailing.id}/deliveries?page=${this.page}${this.status ? `&status=${this.status}` : ''}`).then(
      (response) => {
        this.rows = response.data;
        this.total = response.total;
        this.perPage = response.perPage;
        this.outcomes = response.outcomes || {};
        m.redraw();
      }
    );
  }

  view() {
    const pages = Math.max(1, Math.ceil(this.total / this.perPage));
    const finished = ['sent', 'cancelled'].includes(this.attrs.mailing.status);

    return (
      <div className="HeraldDeliveries">
        <div className="HeraldDeliveries-head">
          <div className="HeraldOutcomes" role="tablist" aria-label={extractText(t('deliveries.filter'))}>
            {['', ...STATUSES].map((status) =>
              status && !this.outcomes[status] ? null : (
                <button
                  type="button"
                  role="tab"
                  aria-selected={this.status === status}
                  className={`HeraldOutcome HeraldOutcome--${status || 'all'} ${this.status === status ? 'active' : ''}`}
                  onclick={() => {
                    this.status = status;
                    this.page = 1;
                    this.load();
                  }}
                >
                  {t(`deliveries.${status || 'all'}`)}
                  <span className="HeraldOutcome-count">
                    {(status ? this.outcomes[status] : Object.values(this.outcomes).reduce((a, b) => a + b, 0)).toLocaleString(app.data.locale)}
                  </span>
                </button>
              )
            )}
          </div>
          {finished && this.outcomes.failed ? (
            <Button className="Button" icon="fas fa-redo" loading={this.resending} onclick={() => this.resend()}>
              {t('deliveries.resend_failed', { count: this.outcomes.failed })}
            </Button>
          ) : null}
        </div>
        <p className="helpText">{t('deliveries.help')}</p>

        {this.rows === null ? (
          <LoadingIndicator />
        ) : this.rows.length === 0 ? (
          <p className="HeraldEmpty">{t('deliveries.empty')}</p>
        ) : (
          <table className="HeraldTable HeraldDeliveries-table">
            <thead>
              <tr>
                <th>{t('deliveries.recipient')}</th>
                <th>{t('list.status')}</th>
                <th className="HeraldTable-num">{t('deliveries.attempts')}</th>
                <th>{t('deliveries.when')}</th>
              </tr>
            </thead>
            <tbody>
              {this.rows.map((row) => (
                <tr key={row.id}>
                  <td className="HeraldTable-subject">
                    {row.username ? (
                      <Link href={app.route('user', { username: row.username })}>{row.username}</Link>
                    ) : (
                      <span>{row.email || t('deliveries.removed')}</span>
                    )}
                    {row.email ? <div className="HeraldTable-by">{t('deliveries.guest')}</div> : null}
                    {row.error ? <div className="HeraldTable-failed">{row.error}</div> : null}
                  </td>
                  <td>
                    <span className={`HeraldStatus HeraldStatus--d-${row.status}`}>{t(`deliveries.${row.status}`)}</span>
                  </td>
                  <td className="HeraldTable-num">{row.attempts}</td>
                  <td className="HeraldTable-date">
                    {row.sentAt ? when(row.sentAt) : row.nextAttemptAt ? t('deliveries.retry_at', { when: when(row.nextAttemptAt) }) : '—'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {pages > 1 ? (
          <div className="HeraldPager">
            <Button className="Button" icon="fas fa-chevron-left" disabled={this.page <= 1} onclick={() => (this.page--, this.load())}>
              {t('deliveries.previous')}
            </Button>
            <span>{t('deliveries.page', { page: this.page, pages })}</span>
            <Button className="Button" disabled={this.page >= pages} onclick={() => (this.page++, this.load())}>
              {t('deliveries.next')}
            </Button>
          </div>
        ) : null}
      </div>
    );
  }

  resend() {
    this.resending = true;

    api('POST', `/mailings/${this.attrs.mailing.id}/resend-failed`)
      .then((response) => this.attrs.onresend(response))
      .finally(() => {
        this.resending = false;
        m.redraw();
      });
  }
}
