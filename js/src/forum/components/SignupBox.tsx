import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import extractText from 'flarum/common/utils/extractText';
import { t } from '../util';

let uid = 0;

/**
 * The newsletter signup box, drawn by SignupWidget for Bespoke, FoF and
 * any page that places it.
 *
 * Members get one button: it flips the SAME switch as their Settings page,
 * so there is one consent per member and never a second list. Guests get an
 * address field and a confirmation email (double opt-in).
 *
 * A lazy chunk of its own (see SignupWidget): a page without the widget
 * never downloads it.
 */
export default class SignupBox extends Component<{ heading?: boolean }> {
  id = `herald-signup-${++uid}`;
  email = '';
  website = '';
  loading = false;
  done = false;
  error = '';

  view() {
    const config = (app.forum.attribute('heraldSignup') || {}) as { title?: string; text?: string; button?: string; guests?: boolean };
    const user = app.session.user;

    if (user ? user.attribute('heraldSubscribed') === undefined : !config.guests) return null;

    return (
      <div className="HeraldSignup">
        {this.attrs.heading === false ? null : (
          <h4 className="HeraldSignup-title">
            <i className="fas fa-envelope-open-text" aria-hidden="true" />
            {config.title || t('signup.title')}
          </h4>
        )}
        <p className="HeraldSignup-text">{config.text || t('signup.text')}</p>
        {user ? this.member(user, config.button) : this.guest(config.button)}
      </div>
    );
  }

  member(user: any, label?: string) {
    const on = !!user.attribute('heraldSubscribed');

    return (
      <div className="HeraldSignup-member">
        {on ? (
          <p className="HeraldSignup-state" role="status">
            <i className="fas fa-check-circle" aria-hidden="true" /> {t('signup.subscribed')}
          </p>
        ) : null}
        <Button
          className={on ? 'Button Button--block' : 'Button Button--primary Button--block'}
          loading={this.loading}
          onclick={() => {
            this.loading = true;
            user.save({ heraldSubscribed: !on }).finally(() => {
              this.loading = false;
              m.redraw();
            });
          }}
        >
          {on ? t('signup.unsubscribe') : label || t('signup.button')}
        </Button>
      </div>
    );
  }

  guest(label?: string) {
    if (this.done) {
      return (
        <p className="HeraldSignup-done" role="status">
          <i className="fas fa-paper-plane" aria-hidden="true" /> {t('signup.check_inbox')}
        </p>
      );
    }

    return (
      <form className="HeraldSignup-form" onsubmit={(e: Event) => this.submit(e)}>
        <input
          id={this.id}
          className="FormControl"
          type="email"
          required
          autocomplete="email"
          aria-label={extractText(t('signup.email_label'))}
          placeholder={extractText(t('signup.email_placeholder'))}
          value={this.email}
          oninput={(e: InputEvent) => (this.email = (e.target as HTMLInputElement).value)}
        />
        {/* A field only bots fill in. Off-screen, out of the tab order, hidden from screen readers. */}
        <input
          className="HeraldSignup-trap"
          type="text"
          name="website"
          tabindex={-1}
          autocomplete="off"
          aria-hidden="true"
          value={this.website}
          oninput={(e: InputEvent) => (this.website = (e.target as HTMLInputElement).value)}
        />
        <Button type="submit" className="Button Button--primary Button--block" loading={this.loading}>
          {label || t('signup.button')}
        </Button>
        {this.error ? (
          <p className="HeraldSignup-error" role="alert">
            {this.error}
          </p>
        ) : null}
        <p className="HeraldSignup-note">{t('signup.note')}</p>
      </form>
    );
  }

  submit(e: Event) {
    e.preventDefault();
    this.loading = true;
    this.error = '';

    app
      .request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/herald/subscribe',
        body: { email: this.email, website: this.website },
        errorHandler: (error: any) => {
          this.error = error?.response?.errors?.[0]?.detail || extractText(t('signup.failed'));
        },
      })
      .then(
        () => (this.done = true),
        () => {}
      )
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }
}
