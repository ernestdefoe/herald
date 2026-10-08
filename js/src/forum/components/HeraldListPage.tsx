import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import Dropdown from 'flarum/common/components/Dropdown';
import Separator from 'flarum/common/components/Separator';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import humanTime from 'flarum/common/helpers/humanTime';
import extractText from 'flarum/common/utils/extractText';
import StatusBadge from './StatusBadge';
import PreviewModal from './PreviewModal';
import { api, Mailing, t } from '../util';

/**
 * Invision's Bulk Mail list: every mailing, where it got to, and what can be
 * done with it.
 */
export default class HeraldListPage extends Page {
  mailings: Mailing[] | null = null;
  timer?: number;
  removed = false;

  oninit(vnode: any) {
    super.oninit(vnode);

    this.bodyClass = 'App--herald';
    app.setTitle(extractText(t('list.title')));
    app.history.push('herald', extractText(t('list.title')));

    // No request a guest or member would only have refused (and then throw).
    if (app.forum.attribute('canSendHeraldMail')) this.load();
  }

  onremove(vnode: any) {
    super.onremove(vnode);
    this.removed = true;
    clearTimeout(this.timer);
  }

  load() {
    clearTimeout(this.timer);

    return api<{ data: Mailing[] }>('GET', '/mailings').then(({ data }) => {
      this.mailings = data;
      m.redraw();

      // Keep a sending row's progress moving while the list is open.
      if (!this.removed && data.some((mailing) => mailing.status === 'sending')) {
        this.timer = window.setTimeout(() => this.load(), 4000);
      }
    });
  }

  view() {
    if (!app.forum.attribute('canSendHeraldMail')) {
      return <div className="HeraldPage container">{t('no_permission')}</div>;
    }

    return (
      <div className="HeraldPage HeraldListPage container">
        <div className="HeraldPage-header">
          <div>
            <h2>{t('list.title')}</h2>
            <p className="helpText">{t('list.intro')}</p>
          </div>
          {/* A Button, not a LinkButton: a theme's `a { color }` rule can
              out-rank .Button--primary on an anchor and paint the label the
              colour of its own background. */}
          <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => m.route.set(app.route('herald.new'))}>
            {t('list.create')}
          </Button>
        </div>

        {this.mailings === null ? <LoadingIndicator /> : this.mailings.length ? this.table(this.mailings) : this.empty()}
      </div>
    );
  }

  empty() {
    return (
      <div className="HeraldEmpty">
        <i className="fas fa-bullhorn" aria-hidden="true" />
        <p>{t('list.empty')}</p>
      </div>
    );
  }

  table(mailings: Mailing[]) {
    return (
      <table className="HeraldTable">
        <thead>
          <tr>
            <th>{t('list.subject')}</th>
            <th>{t('list.status')}</th>
            <th className="HeraldTable-num">{t('list.sent')}</th>
            <th>{t('list.date')}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          {mailings.map((mailing) => (
            <tr key={mailing.id}>
              <td className="HeraldTable-subject">
                <Link href={app.route('herald.edit', { id: mailing.id })}>{mailing.subject || <em>{t('edit.untitled')}</em>}</Link>
                {mailing.creator ? <div className="HeraldTable-by">{t('list.by', { name: mailing.creator })}</div> : null}
              </td>
              <td>
                <StatusBadge mailing={mailing} />
              </td>
              <td className="HeraldTable-num">
                {mailing.status === 'draft'
                  ? '—'
                  : `${mailing.sentCount.toLocaleString(app.data.locale)} / ${mailing.recipientTotal.toLocaleString(app.data.locale)}`}
                {mailing.failedCount ? <div className="HeraldTable-failed">{t('progress.failed', { count: mailing.failedCount })}</div> : null}
              </td>
              <td className="HeraldTable-date">{humanTime(new Date(mailing.completedAt || mailing.startedAt || mailing.createdAt || Date.now()))}</td>
              <td className="HeraldTable-actions">{this.actions(mailing)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    );
  }

  actions(mailing: Mailing) {
    const sending = mailing.status === 'sending';

    return (
      <Dropdown
        className="HeraldTable-dropdown"
        buttonClassName="Button Button--icon Button--flat"
        menuClassName="Dropdown-menu--right"
        icon="fas fa-ellipsis-h"
        label={t('list.actions')}
        accessibleToggleLabel={extractText(t('list.actions'))}
      >
        <Button icon="fas fa-pen" onclick={() => m.route.set(app.route('herald.edit', { id: mailing.id }))}>
          {sending ? t('list.view_progress') : t('list.edit')}
        </Button>
        <Button icon="far fa-eye" onclick={() => this.preview(mailing)}>
          {t('edit.preview')}
        </Button>
        <Button icon="far fa-copy" onclick={() => this.copy(mailing)}>
          {t('list.copy')}
        </Button>
        {!sending ? (
          <Button icon="fas fa-paper-plane" onclick={() => m.route.set(app.route('herald.edit', { id: mailing.id, send: 1 }))}>
            {mailing.status === 'draft' ? t('edit.send') : t('edit.resend')}
          </Button>
        ) : null}
        {!sending ? <Separator /> : null}
        {!sending ? (
          <Button icon="far fa-trash-alt" className="HeraldTable-delete" onclick={() => this.delete(mailing)}>
            {t('list.delete')}
          </Button>
        ) : null}
      </Dropdown>
    );
  }

  preview(mailing: Mailing) {
    app.modal.show(PreviewModal, { id: mailing.id });
  }

  copy(mailing: Mailing) {
    api<{ data: Mailing }>('POST', `/mailings/${mailing.id}/copy`).then(({ data }) => m.route.set(app.route('herald.edit', { id: data.id })));
  }

  delete(mailing: Mailing) {
    if (!confirm(extractText(t('list.delete_confirm', { subject: mailing.subject })))) return;

    api('DELETE', `/mailings/${mailing.id}`).then(() => this.load());
  }
}
