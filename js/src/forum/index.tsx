import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import SessionDropdown from 'flarum/forum/components/SessionDropdown';
import LinkButton from 'flarum/common/components/LinkButton';
import FieldSet from 'flarum/common/components/FieldSet';
import Switch from 'flarum/common/components/Switch';
import ItemList from 'flarum/common/utils/ItemList';
import { t } from './util';

export { default as extend } from './extend';

app.initializers.add('ernestdefoe/herald', () => {
  extend(SessionDropdown.prototype, 'items', function (items: ItemList<any>) {
    if (!app.forum.attribute('canSendHeraldMail')) return;

    items.add(
      'herald',
      <LinkButton icon="fas fa-bullhorn" href={app.route('herald')}>
        {t('nav.label')}
      </LinkButton>,
      55
    );
  });

  /*
   * The member's own switch.
   *
   * 🚨 By MODULE PATH, not `SettingsPage.prototype`. The settings page is a
   * lazily loaded chunk in Flarum 2; at initializer time the class does not
   * exist yet, and extending its prototype either throws or does nothing.
   */
  extend('flarum/forum/components/SettingsPage', 'settingsItems', function (this: any, items: ItemList<any>) {
    const user = this.user;

    if (!user || user.attribute('heraldSubscribed') === undefined) return;

    items.add(
      'herald',
      <FieldSet className="Settings-herald FieldSet--min" label={t('settings.heading')}>
        <div id="herald">
          <Switch
            state={!!user.attribute('heraldSubscribed')}
            loading={this.heraldLoading}
            onchange={(value: boolean) => {
              this.heraldLoading = true;

              user.save({ heraldSubscribed: value }).finally(() => {
                this.heraldLoading = false;
                m.redraw();
              });
            }}
          >
            {t('settings.label', { forumTitle: app.forum.attribute('title') })}
          </Switch>
          <p className="helpText">{t('settings.help')}</p>
        </div>
      </FieldSet>,
      75
    );
  });
});
