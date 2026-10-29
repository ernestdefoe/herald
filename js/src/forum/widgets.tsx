import app from 'flarum/forum/app';
import SignupWidget from './components/SignupWidget';
import { fof, hasFofWidgets, placed } from '../common/fofWidgets';

const EXT = 'ernestdefoe-herald';

/**
 * The signup box, offered to both widget hosts.
 *
 * 🚨 Bespoke AND fof/forum-widgets-core: not every forum runs Bespoke. Each is
 * resolved at runtime through its own registry or queue, never imported, so
 * the bundle is the same with both, either or neither installed.
 *
 * 🚨 Registering is not placing: neither host shows it until an admin puts it
 * somewhere.
 */
export default function registerWidgets(): void {
  const flarum = (globalThis as any).flarum;

  if (hasFofWidgets() && (app as any).widgets) {
    const Widgets = fof('common/extend/Widgets');
    const WidgetBase = fof('common/components/Widget');

    // A version whose internals moved: decline quietly rather than throw out
    // of an initializer and take the forum's JS down with it.
    if (Widgets && WidgetBase) {
      class HeraldSignupWidget extends WidgetBase {
        className() {
          return 'HeraldFofWidget';
        }
        icon() {
          return 'fas fa-envelope-open-text';
        }
        title() {
          return (app.forum.attribute('heraldSignup') as any)?.title || app.translator.trans(`${EXT}.forum.signup.title`);
        }
        content() {
          return <SignupWidget heading={false} />;
        }
      }

      new Widgets()
        .add({
          key: 'heraldSignup',
          component: HeraldSignupWidget,
          // 🚨 Opt-in: only where the admin placed it in FoF's layout editor.
          isDisabled: () => !placed(app, EXT, 'heraldSignup'),
          isUnique: true,
          placement: 'end',
          position: 1,
        })
        .extend(app, EXT);
    }
  }

  if (flarum?.extensions && 'ernestdefoe-bespoke' in flarum.extensions) {
    const g = globalThis as any;
    g.BespokeWidgetQueue = g.BespokeWidgetQueue || [];
    g.BespokeWidgetQueue.push({
      type: 'herald-signup',
      // 🚨 A translation KEY, not translated text: Bespoke resolves it itself,
      // and its editor calls string methods on the label.
      label: `${EXT}.forum.signup.widget_name`,
      icon: 'fas fa-envelope-open-text',
      zones: ['sidebar', 'above-list', 'below-list', 'footer'],
      schema: [],
      component: {
        view() {
          return (
            <div className="HeraldBespokeWidget">
              <SignupWidget />
            </div>
          );
        },
      },
    });
  }
}
