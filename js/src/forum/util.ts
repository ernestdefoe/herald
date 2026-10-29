import app from 'flarum/forum/app';

export function t(key: string, params: Record<string, any> = {}): any {
  return app.translator.trans(`ernestdefoe-herald.forum.${key}`, params);
}

export function api<T = any>(method: string, path: string, body?: any): Promise<T> {
  return app.request<T>({
    method,
    url: `${app.forum.attribute('apiUrl')}/herald${path}`,
    body,
  });
}

export function percent(sent: number, total: number): number {
  if (!total) return 100;

  return Math.min(100, Math.floor((sent / total) * 100));
}

export type Block = { id: string; type: string; data: Record<string, any>; style: { padding: number; background: string; hideOnMobile: boolean } };

export type Mailing = {
  id: number;
  subject: string;
  status: 'draft' | 'sending' | 'sent' | 'cancelled' | 'scheduled' | 'skipped';
  type: 'editor' | 'builder';
  preheader: string;
  isTemplate: boolean;
  archivedAt: string | null;
  scheduledAt: string | null;
  timezone: string | null;
  repeat: 'daily' | 'weekly' | 'monthly' | null;
  seriesId: number | null;
  skippedCount: number;
  outcomes?: Record<string, number>;
  blocks?: Block[];
  filters: Record<string, any>;
  recipientTotal: number;
  sentCount: number;
  failedCount: number;
  creator: string | null;
  createdAt: string | null;
  updatedAt: string | null;
  startedAt: string | null;
  completedAt: string | null;
  content?: string;
};

export type Counts = { reach: number; optedOut: number; unconfirmed: number; guests: number; guestPool: number };
