import app from 'flarum/admin/app';
import { registerEditorWidgets } from './fofEditor';

export { default as extend } from './extend';

app.initializers.add('ernestdefoe-herald', () => {
  // FoF's layout editor lists only widgets registered on the admin side too.
  registerEditorWidgets(app, 'ernestdefoe-herald', [
    { key: 'heraldSignup', icon: 'fas fa-envelope-open-text', title: () => app.translator.trans('ernestdefoe-herald.admin.widget_name') },
  ]);
});
