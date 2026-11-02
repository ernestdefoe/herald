import Button from 'flarum/common/components/Button';
import { t } from '../util';

/** Previous / page X of Y / Next under a paged table; nothing when it all fits on one page. */
export default function pager(page: number, total: number, perPage: number, go: (page: number) => void) {
  const pages = Math.max(1, Math.ceil(total / perPage));

  if (pages <= 1) return null;

  return (
    <div className="HeraldPager">
      <Button className="Button" icon="fas fa-chevron-left" disabled={page <= 1} onclick={() => go(page - 1)}>
        {t('deliveries.previous')}
      </Button>
      <span>{t('deliveries.page', { page, pages })}</span>
      <Button className="Button" disabled={page >= pages} onclick={() => go(page + 1)}>
        {t('deliveries.next')}
      </Button>
    </div>
  );
}
