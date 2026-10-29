/**
 * fof/forum-widgets-core, shared by the forum (which renders the widgets) and
 * the admin (whose layout editor places them).
 *
 * 🚨 OPT-IN. FoF shows any registered widget that no saved layout mentions, in
 * its default section, so a widget registered only on the forum side appeared
 * in a right-hand column on every forum with FoF installed, even one whose
 * layout had no widgets at all, and FoF's editor could not take it out (the
 * same bug Cadence had). Now a widget shows only once the
 * admin has dragged it into a section and saved; until then it waits in the
 * editor's list of available widgets.
 */

/** A module of fof/forum-widgets-core, at runtime — never a build-time import. */
export function fof(path: string): any {
  const mod = (globalThis as any).flarum?.reg?.get?.('fof-forum-widgets-core', path);
  return mod?.default ?? mod;
}

export function hasFofWidgets(): boolean {
  const flarum = (globalThis as any).flarum;
  return !!flarum?.extensions && 'fof-forum-widgets-core' in flarum.extensions;
}

/** Whether the saved layout places this widget in a section. */
export function placed(app: any, extension: string, key: string): boolean {
  return (app.widgets?.config?.instances || []).some((i: any) => i?.id === `${extension}:${key}`);
}
