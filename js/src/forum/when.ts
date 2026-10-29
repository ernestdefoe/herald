import app from 'flarum/forum/app';

/** "Tue, 27 Oct 2026, 09:00 (America/New_York)": the time in the zone it was scheduled in. */
export function when(iso: string, timeZone?: string | null): string {
  const options: Intl.DateTimeFormatOptions = { dateStyle: 'medium', timeStyle: 'short' };

  try {
    return (
      new Intl.DateTimeFormat(app.data.locale, { ...options, timeZone: timeZone || undefined }).format(new Date(iso)) +
      (timeZone ? ` (${timeZone})` : '')
    );
  } catch (e) {
    return new Date(iso).toLocaleString();
  }
}
