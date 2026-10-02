import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import HeraldTextEditor from './HeraldTextEditor';
import extractText from 'flarum/common/utils/extractText';
import QuickTags, { QuickTag } from './QuickTags';
import RecipientFilters from './RecipientFilters';
import PreviewModal from './PreviewModal';
import SendModal from './SendModal';
import StatusBadge from './StatusBadge';
import { api, Counts, Mailing, percent, t } from '../util';

type Meta = { tags: QuickTag[]; aliases: Record<string, string>; background: boolean };

/**
 * Compose, target, preview, send — Invision's bulk mail form on one page.
 *
 * 🚨 This lives on the FORUM, not in the admin panel, on purpose. Rich editors
 * (Scribe, FoF Rich Text) install themselves into the forum's TextEditor only;
 * the admin bundle never loads them. An admin-panel compose screen would
 * silently hand every forum a plain textarea, whatever editor it runs.
 */
export default class HeraldEditPage extends Page {
  id: number | null = null;
  mailing: Mailing | null = null;
  meta: Meta | null = null;
  loading = true;

  subject = '';
  content = '';
  filters: Record<string, any> = {};

  tab: 'content' | 'recipients' = 'content';
  dirty = false;
  saving = false;
  testing = false;

  counts: Counts | null = null;
  countTimer?: number;

  background = false;
  driving = false;
  removed = false;

  lastFocus: 'subject' | 'content' = 'content';
  subjectEl: HTMLInputElement | null = null;

  /**
   * What TextEditor expects as its `composer`. Editors and their toolbar
   * buttons (Markdown, mentions, emoji) all reach the editor through
   * `attrs.composer.editor`, so this is the whole contract.
   */
  composer: { editor: any } = { editor: null };

  oninit(vnode: any) {
    super.oninit(vnode);

    this.bodyClass = 'App--herald';
    const id = m.route.param('id');
    this.id = id ? Number(id) : null;

    app.setTitle(extractText(t('edit.title')));
    app.history.push('herald.edit', extractText(t('edit.title')));

    const loads: Promise<any>[] = [api<Meta>('GET', '/meta').then((meta) => (this.meta = meta))];

    if (this.id) {
      loads.push(
        api<{ data: Mailing }>('GET', `/mailings/${this.id}`).then(({ data }) => {
          this.mailing = data;
          this.subject = data.subject;
          this.content = data.content || '';
          this.filters = Array.isArray(data.filters) ? {} : { ...(data.filters || {}) };
        })
      );
    }

    Promise.all(loads).then(() => {
      this.loading = false;
      this.background = !!this.meta?.background;
      this.count();
      m.redraw();

      if (this.mailing?.status === 'sending') this.drive();
      else if (m.route.param('send')) this.openSend();
    });
  }

  /**
   * Closing the tab or reloading with unsaved words asks first. (Core's
   * ConfirmDocumentUnload is not exported to extensions in Flarum 2, so this
   * is the same few lines it would have been.)
   */
  unloadGuard = (e: BeforeUnloadEvent) => {
    if (!this.dirty) return;
    e.preventDefault();
    e.returnValue = '';
  };

  oncreate(vnode: any) {
    super.oncreate(vnode);
    window.addEventListener('beforeunload', this.unloadGuard);
  }

  onremove(vnode: any) {
    super.onremove(vnode);
    this.removed = true;
    clearTimeout(this.countTimer);
    window.removeEventListener('beforeunload', this.unloadGuard);
  }

  view() {
    if (!app.forum.attribute('canSendHeraldMail')) {
      return <div className="HeraldPage container">{t('no_permission')}</div>;
    }

    if (this.loading) {
      return (
        <div className="HeraldPage container">
          <LoadingIndicator />
        </div>
      );
    }

    return (
      <div className="HeraldPage HeraldEditPage container">
        <div className="HeraldPage-header">
          <div>
            <LinkButton
              className="Button Button--link HeraldPage-back"
              icon="fas fa-arrow-left"
              href={app.route('herald')}
              onclick={(e: MouseEvent) => {
                if (this.dirty && !confirm(extractText(t('edit.unsaved_warning')))) e.preventDefault();
              }}
            >
              {t('edit.back')}
            </LinkButton>
            {/* The badge sits BESIDE the heading, not in it, or a theme's
                heading font (often condensed, sometimes uppercase) dresses
                the badge too. */}
            <div className="HeraldPage-titleRow">
              <h2 className="HeraldPage-title">{this.id ? this.subject || t('edit.untitled') : t('edit.new_title')}</h2>
              {this.mailing ? <StatusBadge mailing={this.mailing} /> : null}
            </div>
          </div>
          {this.mailing?.status === 'sending' ? null : this.actions()}
        </div>

        {this.mailing?.status === 'sending' ? this.progressView() : this.formView()}
      </div>
    );
  }

  formView() {
    return (
      <div className="HeraldEditPage-form">
        {this.mailing && this.mailing.status !== 'draft' ? this.summary() : null}

        <nav className="HeraldTabs" role="tablist">
          {this.tabButton('content', 'fas fa-pen', t('edit.tab_content'))}
          {this.tabButton('recipients', 'fas fa-users', [
            t('edit.tab_recipients'),
            this.counts ? <span className="HeraldTabs-count">{this.counts.reach.toLocaleString(app.data.locale)}</span> : null,
          ])}
        </nav>

        <div className="HeraldEditPage-tab" hidden={this.tab !== 'content'}>
          <div className="HeraldEditor">
            <div className="HeraldEditor-main">
              <div className="Form-group">
                <label for="herald-subject">{t('edit.subject')}</label>
                <input
                  id="herald-subject"
                  className="FormControl"
                  maxlength={255}
                  value={this.subject}
                  placeholder={extractText(t('edit.subject_placeholder'))}
                  oncreate={(v: any) => (this.subjectEl = v.dom)}
                  onfocus={() => (this.lastFocus = 'subject')}
                  oninput={(e: Event) => {
                    this.subject = (e.target as HTMLInputElement).value;
                    this.dirty = true;
                  }}
                />
              </div>
              <div className="Form-group">
                <label>{t('edit.content')}</label>
                <div className="HeraldEditor-editor" onfocusin={() => (this.lastFocus = 'content')}>
                  <HeraldTextEditor
                    composer={this.composer}
                    value={this.content}
                    placeholder={extractText(t('edit.content_placeholder'))}
                    submitLabel={t('edit.save')}
                    onchange={(value: string) => {
                      this.content = value;
                      this.dirty = true;
                    }}
                    onsubmit={() => this.save()}
                  />
                </div>
              </div>
            </div>
            <aside className="HeraldEditor-side">
              <QuickTags tags={this.meta?.tags || []} aliases={this.meta?.aliases || {}} oninsert={(text: string) => this.insert(text)} />
            </aside>
          </div>
        </div>

        <div className="HeraldEditPage-tab" hidden={this.tab !== 'recipients'}>
          <div className="HeraldRecipients">
            <RecipientFilters
              filters={this.filters}
              onchange={() => {
                this.dirty = true;
                this.queueCount();
              }}
            />
            <aside className="HeraldRecipients-count">{this.countCard()}</aside>
          </div>
        </div>
      </div>
    );
  }

  /**
   * In the header, not a bar pinned to the bottom of the screen: that corner
   * is where themes and extensions float their own buttons (scroll-to-top,
   * chat, who's online), and a pinned Send button ends up underneath one.
   */
  actions() {
    return (
      <div className="HeraldActions">
        {/* The button carries the saved state itself, so nothing appears
            beside it and the header never reflows while someone types. */}
        <Button
          className="Button HeraldActions-save"
          icon={!this.dirty && this.id ? 'fas fa-check' : 'far fa-save'}
          loading={this.saving}
          disabled={!this.dirty && !!this.id}
          aria-live="polite"
          onclick={() => this.save()}
        >
          {!this.dirty && this.id ? t('edit.saved') : t('edit.save')}
        </Button>
        <Button className="Button" icon="far fa-eye" onclick={() => this.preview()}>
          {t('edit.preview')}
        </Button>
        <Button className="Button HeraldActions-test" icon="fas fa-vial" loading={this.testing} onclick={() => this.test()}>
          {t('edit.test')}
        </Button>
        <Button className="Button Button--primary" icon="fas fa-paper-plane" onclick={() => this.openSend()}>
          {this.mailing && this.mailing.status !== 'draft' ? t('edit.resend') : t('edit.send')}
        </Button>
      </div>
    );
  }

  tabButton(key: 'content' | 'recipients', icon: string, label: any) {
    return (
      <button
        type="button"
        role="tab"
        aria-selected={this.tab === key}
        className={`HeraldTabs-tab ${this.tab === key ? 'active' : ''}`}
        onclick={() => (this.tab = key)}
      >
        <i className={`${icon} HeraldTabs-icon`} aria-hidden="true" />
        {label}
      </button>
    );
  }

  summary() {
    const mailing = this.mailing!;

    return (
      <div className={`HeraldSummary HeraldSummary--${mailing.status}`}>
        {t(`edit.summary_${mailing.status}`, {
          sent: mailing.sentCount.toLocaleString(app.data.locale),
          total: mailing.recipientTotal.toLocaleString(app.data.locale),
          failed: mailing.failedCount.toLocaleString(app.data.locale),
        })}
      </div>
    );
  }

  countCard() {
    const counts = this.counts;

    if (!counts) return <LoadingIndicator size="small" />;

    return (
      <div className="HeraldCount">
        <div className="HeraldCount-number">{counts.reach.toLocaleString(app.data.locale)}</div>
        <div className="HeraldCount-label">{t('edit.count_label', { count: counts.reach })}</div>
        {counts.optedOut || counts.unconfirmed ? (
          <p className="helpText">
            {t('send.excluded', {
              optedOut: counts.optedOut.toLocaleString(app.data.locale),
              unconfirmed: counts.unconfirmed.toLocaleString(app.data.locale),
            })}
          </p>
        ) : null}
        <p className="helpText">{t('edit.consent_note')}</p>
      </div>
    );
  }

  progressView() {
    const mailing = this.mailing!;
    const done = mailing.sentCount + mailing.failedCount;
    const pct = percent(done, mailing.recipientTotal);

    return (
      <div className="HeraldProgress">
        <div className="HeraldProgress-numbers">
          {t('progress.count', {
            done: done.toLocaleString(app.data.locale),
            total: mailing.recipientTotal.toLocaleString(app.data.locale),
          })}
          {mailing.failedCount ? (
            <span className="HeraldProgress-failed"> · {t('progress.failed', { count: mailing.failedCount })}</span>
          ) : null}
        </div>
        <div className="HeraldProgress-bar" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={pct}>
          <div className="HeraldProgress-fill" style={{ width: pct + '%' }} />
        </div>
        <p className="helpText">{this.background ? t('progress.background') : t('progress.keep_open')}</p>
        <Button className="Button" icon="fas fa-ban" onclick={() => this.cancel()}>
          {t('progress.cancel')}
        </Button>
      </div>
    );
  }

  insert(text: string) {
    if (this.lastFocus === 'subject' && this.subjectEl) {
      const el = this.subjectEl;
      const start = el.selectionStart ?? el.value.length;
      const end = el.selectionEnd ?? start;

      this.subject = el.value.slice(0, start) + text + el.value.slice(end);
      el.value = this.subject;
      el.setSelectionRange(start + text.length, start + text.length);
      el.focus();
    } else if (this.composer.editor) {
      // escape = true: a tag is literal TEXT. Rich editors treat an
      // unescaped insert as Markdown to parse, and FoF Rich Text turns
      // "{member_name}" into new paragraphs with the brace left behind.
      this.composer.editor.insertAtCursor(text, true);
    }

    this.dirty = true;
  }

  queueCount() {
    clearTimeout(this.countTimer);
    this.countTimer = window.setTimeout(() => this.count(), 400);
  }

  count() {
    api<Counts>('POST', '/count', { filters: this.filters }).then((counts) => {
      this.counts = counts;
      m.redraw();
    });
  }

  payload() {
    return { subject: this.subject, content: this.content, filters: this.filters };
  }

  save(): Promise<void> {
    this.saving = true;
    m.redraw();

    const request = this.id ? api('PATCH', `/mailings/${this.id}`, this.payload()) : api('POST', '/mailings', this.payload());

    return request
      .then(({ data }: { data: Mailing }) => {
        const created = !this.id;

        this.id = data.id;
        this.mailing = data;
        this.dirty = false;

        // A new mailing gets its own address without remounting the page —
        // a route change would rebuild the editor mid-sentence.
        if (created) window.history.replaceState(null, '', app.route('herald.edit', { id: data.id }));
      })
      .finally(() => {
        this.saving = false;
        m.redraw();
      });
  }

  preview() {
    // Unchanged since it was saved: preview what is stored, which is what
    // would be sent. Otherwise preview the words on screen.
    app.modal.show(PreviewModal, this.id && !this.dirty ? { id: this.id } : { subject: this.subject, content: this.content });
  }

  /**
   * Whether there is something to send, and if not, says what is missing and
   * shows the tab where it goes.
   */
  ready(): boolean {
    if (!this.subject.trim()) {
      this.tab = 'content';
      app.alerts.show({ type: 'error' }, t('errors.subject_required'));
      return false;
    }

    if (!this.content.trim()) {
      this.tab = 'content';
      app.alerts.show({ type: 'error' }, t('errors.content_required'));
      return false;
    }

    return true;
  }

  test() {
    if (!this.ready()) return;

    this.testing = true;

    this.save()
      .then(() => api('POST', `/mailings/${this.id}/test`))
      .then((response: any) => app.alerts.show({ type: 'success' }, t('edit.test_sent', { email: response.to })))
      .finally(() => {
        this.testing = false;
        m.redraw();
      });
  }

  openSend() {
    if (!this.ready()) return;

    this.save().then(() =>
      app.modal.show(SendModal, {
        mailingId: this.id,
        subject: this.subject,
        filters: this.filters,
        resend: !!this.mailing && this.mailing.status !== 'draft',
        onsend: (response: any) => {
          this.mailing = response.data;
          this.background = !!response.background;
          m.redraw();
          this.drive();
        },
      })
    );
  }

  /**
   * While this page is open, it sends — one batch per request, the way
   * Invision's progress screen does. On a forum with a queue or a cron job
   * the background is sending too; the lock on the server keeps the two from
   * ever mailing the same member twice.
   */
  drive() {
    if (this.driving) return;
    this.driving = true;

    const step = () => {
      if (this.removed || !this.mailing || this.mailing.status !== 'sending') {
        this.driving = false;
        return;
      }

      api(`POST`, `/mailings/${this.mailing.id}/process`)
        .then((result: any) => {
          this.mailing = result.data;
          m.redraw();

          const wait = result.state === 'busy' ? Math.max(2, result.wait || 0) : result.wait || 0;
          window.setTimeout(step, wait * 1000);
        })
        .catch(() => window.setTimeout(step, 5000));
    };

    step();
  }

  cancel() {
    if (!this.mailing || !confirm(extractText(t('progress.cancel_confirm')))) return;

    api('POST', `/mailings/${this.mailing.id}/cancel`).then(({ data }: any) => {
      this.mailing = data;
      m.redraw();
    });
  }
}
