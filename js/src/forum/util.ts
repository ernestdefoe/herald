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

export type Mailing = {
  id: number;
  subject: string;
  status: 'draft' | 'sending' | 'sent' | 'cancelled';
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

export type Counts = { reach: number; optedOut: number; unconfirmed: number };
