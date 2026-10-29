import app from 'flarum/forum/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import extractText from 'flarum/common/utils/extractText';
import { api, Counts, Mailing, t } from '../util';

interface SendModalAttrs extends IInternalModalAttrs {
  mailingId: number;
  subject: string;
  filters: Record<string, any>;
  resend: boolean;
  mailing: Mailing | null;
  onsend: (response: any) => void;
  onschedule: (mailing: Mailing) => void;
}

const pad = (n: number) => String(n).padStart(2, '0');

/** The browser's own zone, which is what the admin means by "9am". */
const localZone = () => {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
  } catch (e) {
    return 'UTC';
  }
};

const zones = (): string[] => {
  try {
    return (Intl as any).supportedValuesOf('timeZone');
  } catch (e) {
    return [localZone(), 'UTC'];
  }
};

/**
 * The last step: send now, or schedule — once, or repeating. Either way it
 * says exactly how many people it will reach, because an email cannot be
 * recalled.
 */
export default class SendModal extends Modal<SendModalAttrs> {
  counts: Counts | null = null;
  sending = false;
  mode: 'now' | 'schedule' = 'now';

  date = '';
  time = '';
  zone = localZone();
  repeat = '';

  oninit(vnode: any) {
    super.oninit(vnode);

    const existing = this.attrs.mailing;

    if (existing?.status === 'scheduled' && existing.scheduledAt) {
      // Editing a schedule: start from what is set, in its own zone.
      this.mode = 'schedule';
      this.zone = existing.timezone || this.zone;
      this.repeat = existing.repeat || '';
      this.setFrom(new Date(existing.scheduledAt), this.zone);
    } else {
      // An hour from now, on the hour: a sensible first suggestion.
      const soon = new Date(Date.now() + 60 * 60 * 1000);
      soon.setMinutes(0, 0, 0);
      this.setFrom(soon, this.zone);
    }

    api<Counts>('POST', '/count', { filters: this.attrs.filters }).then((counts) => {
      this.counts = counts;
      m.redraw();
    });
  }

  /** The date and time fields, as the wall clock reads in `zone`. */
  setFrom(date: Date, zone: string) {
    try {
      const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-CA', {
          timeZone: zone,
          year: 'numeric',
          month: '2-digit',
          day: '2-digit',
          hour: '2-digit',
          minute: '2-digit',
          hourCycle: 'h23',
        })
          .formatToParts(date)
          .map((p) => [p.type, p.value])
      );
      this.date = `${parts.year}-${parts.month}-${parts.day}`;
      this.time = `${parts.hour}:${parts.minute}`;
    } catch (e) {
      this.date = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
      this.time = `${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }
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
            <div className="HeraldSendModes" role="tablist">
              {(['now', 'schedule'] as const).map((mode) => (
                <button
                  type="button"
                  role="tab"
                  aria-selected={this.mode === mode}
                  className={`HeraldSendModes-tab ${this.mode === mode ? 'active' : ''}`}
                  onclick={() => (this.mode = mode)}
                >
                  <i className={mode === 'now' ? 'fas fa-paper-plane' : 'far fa-clock'} aria-hidden="true" /> {t(`send.mode_${mode}`)}
                </button>
              ))}
            </div>
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
            {this.mode === 'now' ? this.now(counts) : this.schedule()}
          </div>
        )}
      </div>
    );
  }

  now(counts: Counts) {
    return [
      <p className="helpText">{t('send.cannot_recall')}</p>,
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
      </div>,
    ];
  }

  schedule() {
    return (
      <div className="HeraldSchedule">
        <div className="HeraldSchedule-row">
          <label>
            {t('schedule.date')}
            <input className="FormControl" type="date" value={this.date} oninput={(e: any) => (this.date = e.target.value)} />
          </label>
          <label>
            {t('schedule.time')}
            <input className="FormControl" type="time" value={this.time} oninput={(e: any) => (this.time = e.target.value)} />
          </label>
        </div>
        <label>
          {t('schedule.zone')}
          <select className="FormControl" value={this.zone} onchange={(e: any) => (this.zone = e.target.value)}>
            {zones().map((zone) => (
              <option value={zone}>{zone.replace(/_/g, ' ')}</option>
            ))}
          </select>
        </label>
        <label>
          {t('schedule.repeat')}
          <select className="FormControl" value={this.repeat} onchange={(e: any) => (this.repeat = e.target.value)}>
            <option value="">{t('schedule.repeat_none')}</option>
            {['daily', 'weekly', 'monthly'].map((every) => (
              <option value={every}>{t(`schedule.every_${every}`)}</option>
            ))}
          </select>
        </label>
        <p className="helpText">{this.repeat ? t('schedule.repeat_help') : t('schedule.help')}</p>
        <Button
          className="Button Button--primary Button--block"
          icon="far fa-clock"
          loading={this.sending}
          disabled={!this.date || !this.time}
          onclick={() => this.saveSchedule()}
        >
          {this.repeat ? t('schedule.confirm_repeat', { every: extractText(t(`schedule.every_${this.repeat}`)) }) : t('schedule.confirm')}
        </Button>
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

  saveSchedule() {
    this.sending = true;

    api<{ data: Mailing }>('POST', `/mailings/${this.attrs.mailingId}/schedule`, {
      at: `${this.date}T${this.time}`,
      timezone: this.zone,
      repeat: this.repeat || null,
    })
      .then(({ data }) => {
        this.hide();
        this.attrs.onschedule(data);
      })
      .catch(() => {
        this.sending = false;
        m.redraw();
      });
  }
}
