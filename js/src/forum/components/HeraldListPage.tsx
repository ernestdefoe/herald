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
import SubscribersPanel from './SubscribersPanel';
import { api, Mailing, t } from '../util';
import { when } from '../when';

type View = 'mailings' | 'templates' | 'archived' | 'subscribers';

/**
 * Invision's Bulk Mail list: every mailing, where it got to, and what can be
 * done with it.
 */
export default class HeraldListPage extends Page {
  mailings: Mailing[] | null = null;
  timer?: number;
  removed = false;
  view_: View = 'mailings';

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
    if (this.view_ === 'subscribers') return Promise.resolve();

    const query = this.view_ === 'mailings' ? '' : `?view=${this.view_}`;

    return api<{ data: Mailing[] }>('GET', `/mailings${query}`).then(({ data }) => {
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
          <div className="HeraldActions">
            <Button className="Button" icon="fas fa-th-large" onclick={() => m.route.set(app.route('herald.new', { builder: 1 }))}>
              {t('list.create_builder')}
            </Button>
            <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => m.route.set(app.route('herald.new'))}>
              {t('list.create')}
            </Button>
          </div>
        </div>

        <nav className="HeraldTabs" role="tablist">
          {this.tab('mailings', 'fas fa-paper-plane')}
          {this.tab('templates', 'far fa-copy')}
          {this.tab('subscribers', 'fas fa-envelope-open-text')}
        </nav>

        {this.view_ === 'subscribers' ? (
          <SubscribersPanel />
        ) : (
          [
            this.view_ !== 'mailings' ? (
              <p className="HeraldTemplates-help">
                <span className="helpText">{t('templates.help')}</span>{' '}
                <Button className="Button Button--link" onclick={() => this.show(this.view_ === 'archived' ? 'templates' : 'archived')}>
                  {this.view_ === 'archived' ? t('templates.show_active') : t('templates.show_archived')}
                </Button>
              </p>
            ) : null,
            this.mailings === null ? <LoadingIndicator /> : this.mailings.length ? this.table(this.mailings) : this.empty(),
          ]
        )}
      </div>
    );
  }

  tab(view: View, icon: string) {
    const active = this.view_ === view || (view === 'templates' && this.view_ === 'archived');

    return (
      <button type="button" role="tab" aria-selected={active} className={`HeraldTabs-tab ${active ? 'active' : ''}`} onclick={() => this.show(view)}>
        <i className={`${icon} HeraldTabs-icon`} aria-hidden="true" />
        {t(`list.tab_${view}`)}
      </button>
    );
  }

  show(view: View) {
    this.view_ = view;
    this.mailings = null;
    this.load();
  }

  empty() {
    return (
      <div className="HeraldEmpty">
        <i className="fas fa-bullhorn" aria-hidden="true" />
        <p>{t(this.view_ === 'mailings' ? 'list.empty' : this.view_ === 'archived' ? 'templates.empty_archived' : 'templates.empty')}</p>
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
                <Link href={app.route('herald.edit', { id: mailing.id })}>
                  {mailing.type === 'builder' ? (
                    <i
                      className="fas fa-th-large HeraldTable-type"
                      title={extractText(t('list.designed'))}
                      aria-label={extractText(t('list.designed'))}
                    />
                  ) : null}
                  {mailing.subject || <em>{t('edit.untitled')}</em>}
                </Link>
                {mailing.creator ? <div className="HeraldTable-by">{t('list.by', { name: mailing.creator })}</div> : null}
                {mailing.status === 'scheduled' && mailing.scheduledAt ? (
                  <div className="HeraldTable-next">
                    <i className="far fa-clock" aria-hidden="true" />{' '}
                    {mailing.repeat
                      ? t('list.next_repeat', {
                          when: when(mailing.scheduledAt, mailing.timezone),
                          every: extractText(t(`schedule.every_${mailing.repeat}`)),
                        })
                      : t('list.next', { when: when(mailing.scheduledAt, mailing.timezone) })}
                  </div>
                ) : null}
              </td>
              <td>
                {mailing.isTemplate ? (
                  <span className="HeraldStatus HeraldStatus--template">{t('status.template')}</span>
                ) : (
                  <StatusBadge mailing={mailing} />
                )}
              </td>
              <td className="HeraldTable-num">
                {['draft', 'scheduled'].includes(mailing.status) || mailing.isTemplate
                  ? '—'
                  : `${mailing.sentCount.toLocaleString(app.data.locale)} / ${mailing.recipientTotal.toLocaleString(app.data.locale)}`}
                {this.outcomes(mailing)}
              </td>
              <td className="HeraldTable-date">{humanTime(new Date(mailing.completedAt || mailing.startedAt || mailing.createdAt || Date.now()))}</td>
              <td className="HeraldTable-actions">{this.actions(mailing)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    );
  }

  /** Failed, skipped and unknown copies, under the sent count. */
  outcomes(mailing: Mailing) {
    const o = mailing.outcomes || {};
    const notes = ['failed', 'skipped', 'unknown'].filter((key) => o[key]);

    return notes.length ? (
      <div className="HeraldTable-outcomes">
        {notes.map((key) => (
          <span className={`HeraldTable-outcome HeraldTable-outcome--${key}`}>{t(`list.outcome_${key}`, { count: o[key] })}</span>
        ))}
      </div>
    ) : null;
  }

  actions(mailing: Mailing) {
    const sending = mailing.status === 'sending';

    if (mailing.isTemplate) {
      return (
        <Dropdown
          className="HeraldTable-dropdown"
          buttonClassName="Button Button--icon Button--flat"
          menuClassName="Dropdown-menu--right"
          icon="fas fa-ellipsis-h"
          label={t('list.actions')}
          accessibleToggleLabel={extractText(t('list.actions'))}
        >
          <Button icon="fas fa-file-import" onclick={() => this.copy(mailing, 'use')}>
            {t('templates.use')}
          </Button>
          <Button icon="fas fa-pen" onclick={() => m.route.set(app.route('herald.edit', { id: mailing.id }))}>
            {t('list.edit')}
          </Button>
          <Button icon="far fa-eye" onclick={() => this.preview(mailing)}>
            {t('edit.preview')}
          </Button>
          <Button icon="far fa-copy" onclick={() => this.copy(mailing, 'copy')}>
            {t('templates.duplicate')}
          </Button>
          <Button icon={mailing.archivedAt ? 'fas fa-box-open' : 'fas fa-archive'} onclick={() => this.archive(mailing)}>
            {mailing.archivedAt ? t('templates.restore') : t('templates.archive')}
          </Button>
          <Separator />
          <Button icon="far fa-trash-alt" className="HeraldTable-delete" onclick={() => this.delete(mailing)}>
            {t('list.delete')}
          </Button>
        </Dropdown>
      );
    }

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
        <Button icon="far fa-copy" onclick={() => this.copy(mailing, 'copy')}>
          {t('list.copy')}
        </Button>
        <Button icon="far fa-file" onclick={() => this.copy(mailing, 'template')}>
          {t('list.save_template')}
        </Button>
        {!sending ? (
          <Button icon="fas fa-paper-plane" onclick={() => m.route.set(app.route('herald.edit', { id: mailing.id, send: 1 }))}>
            {['draft', 'scheduled'].includes(mailing.status) ? t('edit.send') : t('edit.resend')}
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

  copy(mailing: Mailing, as: 'copy' | 'template' | 'use') {
    api<{ data: Mailing }>('POST', `/mailings/${mailing.id}/copy`, { as }).then(({ data }) => {
      if (as === 'template') {
        app.alerts.show({ type: 'success' }, t('templates.saved'));
        return;
      }

      m.route.set(app.route('herald.edit', { id: data.id }));
    });
  }

  archive(mailing: Mailing) {
    api('PATCH', `/mailings/${mailing.id}`, { archived: !mailing.archivedAt }).then(() => this.load());
  }

  delete(mailing: Mailing) {
    if (!confirm(extractText(t('list.delete_confirm', { subject: mailing.subject })))) return;

    api('DELETE', `/mailings/${mailing.id}`).then(() => this.load());
  }
}
