import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import humanTime from 'flarum/common/helpers/humanTime';
import extractText from 'flarum/common/utils/extractText';
import { api, t } from '../util';

type Subscriber = { id: number; email: string; status: 'pending' | 'confirmed' | 'unsubscribed'; createdAt: string; confirmedAt: string | null };

/**
 * Guests who signed up from the widget: search, export, remove. Members are
 * not here — their newsletter switch is on their own Settings page.
 */
export default class SubscribersPanel extends Component {
  rows: Subscriber[] | null = null;
  total = 0;
  active = 0;
  perPage = 50;
  page = 1;
  q = '';
  timer?: number;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.load();
  }

  load() {
    api<any>('GET', `/subscribers?page=${this.page}&q=${encodeURIComponent(this.q)}`).then((response) => {
      this.rows = response.data;
      this.total = response.total;
      this.active = response.active;
      this.perPage = response.perPage;
      m.redraw();
    });
  }

  view() {
    const pages = Math.max(1, Math.ceil(this.total / this.perPage));

    return (
      <div className="HeraldSubscribers">
        <div className="HeraldSubscribers-head">
          <p className="helpText">{t('subscribers.intro', { count: this.active, formatted: this.active.toLocaleString(app.data.locale) })}</p>
          <div className="HeraldSubscribers-tools">
            <input
              className="FormControl"
              type="search"
              value={this.q}
              placeholder={extractText(t('subscribers.search'))}
              aria-label={extractText(t('subscribers.search'))}
              oninput={(e: any) => {
                this.q = e.target.value;
                this.page = 1;
                clearTimeout(this.timer);
                this.timer = window.setTimeout(() => this.load(), 300);
              }}
            />
            <Button
              className="Button"
              icon="fas fa-file-csv"
              onclick={() => (window.location.href = app.forum.attribute('apiUrl') + '/herald/subscribers/export')}
            >
              {t('subscribers.export')}
            </Button>
          </div>
        </div>

        {this.rows === null ? (
          <LoadingIndicator />
        ) : this.rows.length === 0 ? (
          <div className="HeraldEmpty">
            <i className="fas fa-envelope-open-text" aria-hidden="true" />
            <p>{t(this.q ? 'subscribers.none_found' : 'subscribers.empty')}</p>
          </div>
        ) : (
          <table className="HeraldTable">
            <thead>
              <tr>
                <th>{t('subscribers.email')}</th>
                <th>{t('list.status')}</th>
                <th>{t('subscribers.since')}</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {this.rows.map((row) => (
                <tr key={row.id}>
                  <td className="HeraldTable-subject">{row.email}</td>
                  <td>
                    <span className={`HeraldStatus HeraldStatus--s-${row.status}`}>{t(`subscribers.${row.status}`)}</span>
                  </td>
                  <td className="HeraldTable-date">{humanTime(new Date(row.createdAt))}</td>
                  <td className="HeraldTable-actions">
                    <Button
                      className="Button Button--icon Button--flat HeraldTable-delete"
                      icon="far fa-trash-alt"
                      aria-label={extractText(t('subscribers.remove'))}
                      title={extractText(t('subscribers.remove'))}
                      onclick={() => this.remove(row)}
                    />
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

  remove(row: Subscriber) {
    if (!confirm(extractText(t('subscribers.remove_confirm', { email: row.email })))) return;

    api('DELETE', `/subscribers/${row.id}`).then(() => this.load());
  }
}
