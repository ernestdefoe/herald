import app from 'flarum/admin/app';
import Admin from 'flarum/common/extenders/Admin';
import Button from 'flarum/common/components/Button';

function t(key: string, params: Record<string, any> = {}): any {
  return app.translator.trans(`ernestdefoe-herald.admin.${key}`, params);
}

const key = (name: string) => `ernestdefoe-herald.${name}`;

/*
 * 🚨 The `Admin` extender, NOT `app.extensionData` — that is Flarum 1.x and
 * absent in Flarum 2.
 *
 * Mailings themselves are written on the forum side (/herald), because that is
 * the only place the forum's own editor exists. This page holds the dials and
 * a way there.
 */
export default [
  new Admin()
    .customSetting(function () {
      return (
        <div className="Form-group HeraldAdmin-intro">
          <p>{t('intro')}</p>
          <Button
            className="Button Button--primary"
            icon="fas fa-bullhorn"
            onclick={() => window.open(app.forum.attribute('baseUrl') + '/herald', '_blank')}
          >
            {t('open')}
          </Button>
        </div>
      );
    })
    .setting(() => ({
      setting: key('batch_size'),
      label: t('batch_size_label'),
      help: t('batch_size_help'),
      type: 'number',
      min: 1,
      max: 1000,
      default: 50,
    }))
    .setting(() => ({
      setting: key('batch_delay'),
      label: t('batch_delay_label'),
      help: t('batch_delay_help'),
      type: 'number',
      min: 0,
      max: 3600,
      default: 0,
    }))
    .setting(() => ({
      setting: key('reply_to'),
      label: t('reply_to_label'),
      help: t('reply_to_help'),
      type: 'email',
      placeholder: 'news@example.com',
    }))
    .customSetting(function () {
      return (
        <div className="Form-group">
          <label>{t('sending_heading')}</label>
          <div className="helpText">{t('sending_help')}</div>
          <pre className="HeraldAdmin-cron">
            * * * * * cd /path/to/flarum &amp;&amp; php flarum schedule:run &gt;&gt; /dev/null 2&gt;&amp;1
          </pre>
        </div>
      );
    })
    .permission(
      () => ({
        icon: 'fas fa-bullhorn',
        label: t('permission'),
        permission: 'ernestdefoe-herald.send',
      }),
      'moderate'
    ),
];
