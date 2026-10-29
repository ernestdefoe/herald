import { extend } from 'flarum/common/extend';
import { fof } from '../common/fofWidgets';

export interface EditorWidget {
  key: string;
  icon: string;
  title: () => any;
  placement?: string;
}

/**
 * Offer the widgets to FoF's layout editor, which lists only widgets
 * registered on the admin side too. Registered as the editor opens; a widget
 * no saved layout places goes in the editor's list of available widgets, not
 * into a section — FoF would otherwise draw it placed, while the forum rightly
 * shows nothing until it is saved.
 */
export function registerEditorWidgets(app: any, extension: string, widgets: EditorWidget[]): void {
  const Widgets = fof('common/extend/Widgets');
  const WidgetEditor = fof('admin/components/WidgetEditor');
  if (!Widgets || !WidgetEditor) return;

  extend(WidgetEditor.prototype, 'oninit', function (this: any) {
    const manager = app.widgets;
    if (!manager) return;

    if (!manager.widgets.some((w: any) => w.extension === extension)) {
      const add = new Widgets();
      widgets.forEach((w, position) =>
        add.add({
          key: w.key,
          component: { component: () => null },
          title: w.title,
          icon: w.icon,
          isDisabled: false,
          isUnique: true,
          placement: w.placement || 'end',
          position,
        })
      );
      add.extend(app, extension);
    }

    const config = this.config || {};
    const disabled: string[] = config.disabled || [];
    const unplaced = widgets
      .map((w) => `${extension}:${w.key}`)
      .filter((id) => !disabled.includes(id) && !(config.instances || []).some((i: any) => i?.id === id));
    config.disabled = [...disabled, ...unplaced];
  });
}
