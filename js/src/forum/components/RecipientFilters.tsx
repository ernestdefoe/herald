import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Switch from 'flarum/common/components/Switch';
import Group from 'flarum/common/models/Group';
import { t } from '../util';

interface RecipientFiltersAttrs {
  filters: Record<string, any>;
  onchange: () => void;
}

const GUEST_ID = '2';
const MEMBER_ID = '3';

/**
 * Invision's recipient filters, stacked: every one that is set narrows the
 * list further.
 *
 * Writes straight into the filters object it is given and calls onchange, so
 * the page owns the state and can count and save it.
 */
export default class RecipientFilters extends Component<RecipientFiltersAttrs> {
  view() {
    const groups = (app.store.all('groups') as Group[]).filter((g) => g.id() !== GUEST_ID);
    const suspend = 'flarum-suspend' in ((window as any).flarum?.extensions || {});

    return (
      <div className="HeraldFilters">
        <fieldset className="HeraldFilter">
          <legend>{t('filters.groups')}</legend>
          <p className="helpText">{t('filters.groups_include_help')}</p>
          <div className="HeraldFilter-checks">{groups.map((g) => this.groupCheck('include', g))}</div>
          <p className="helpText">{t('filters.groups_exclude_help')}</p>
          <div className="HeraldFilter-checks">{groups.filter((g) => g.id() !== MEMBER_ID).map((g) => this.groupCheck('exclude', g))}</div>
        </fieldset>

        <fieldset className="HeraldFilter">
          <legend>{t('filters.joined')}</legend>
          <div className="HeraldFilter-row">
            <label>
              {t('filters.after')}
              {this.input('joined', 'after', 'date')}
            </label>
            <label>
              {t('filters.before')}
              {this.input('joined', 'before', 'date')}
            </label>
          </div>
        </fieldset>

        <fieldset className="HeraldFilter">
          <legend>{t('filters.last_visit')}</legend>
          <div className="HeraldFilter-row">
            <select
              className="FormControl"
              value={this.section('lastVisit').mode || 'any'}
              onchange={(e: Event) => this.set('lastVisit', 'mode', (e.target as HTMLSelectElement).value)}
            >
              <option value="any">{t('filters.last_visit_any')}</option>
              <option value="active">{t('filters.last_visit_active')}</option>
              <option value="inactive">{t('filters.last_visit_inactive')}</option>
            </select>
            {['active', 'inactive'].includes(this.section('lastVisit').mode) ? (
              <label className="HeraldFilter-inline">
                {this.input('lastVisit', 'days', 'number')}
                {t('filters.days')}
              </label>
            ) : null}
          </div>
        </fieldset>

        <fieldset className="HeraldFilter">
          <legend>{t('filters.posts')}</legend>
          <div className="HeraldFilter-row">
            <label>
              {t('filters.at_least')}
              {this.input('posts', 'min', 'number')}
            </label>
            <label>
              {t('filters.at_most')}
              {this.input('posts', 'max', 'number')}
            </label>
          </div>
        </fieldset>

        <fieldset className="HeraldFilter">
          <legend>{t('filters.discussions')}</legend>
          <div className="HeraldFilter-row">
            <label>
              {t('filters.at_least')}
              {this.input('discussions', 'min', 'number')}
            </label>
            <label>
              {t('filters.at_most')}
              {this.input('discussions', 'max', 'number')}
            </label>
          </div>
        </fieldset>

        {suspend ? (
          <fieldset className="HeraldFilter">
            <legend>{t('filters.suspended')}</legend>
            <Switch state={!!this.section('suspended').include} onchange={(value: boolean) => this.set('suspended', 'include', value)}>
              {t('filters.suspended_include')}
            </Switch>
          </fieldset>
        ) : null}
      </div>
    );
  }

  section(key: string): Record<string, any> {
    const filters = this.attrs.filters;

    if (!filters[key] || typeof filters[key] !== 'object' || Array.isArray(filters[key])) filters[key] = {};

    return filters[key];
  }

  set(key: string, field: string, value: any) {
    const section = this.section(key);

    if (value === '' || value === null || value === undefined || value === false || value === 'any') delete section[field];
    else section[field] = value;

    this.attrs.onchange();
  }

  input(key: string, field: string, type: 'date' | 'number') {
    return (
      <input
        className="FormControl"
        type={type}
        min={type === 'number' ? 0 : undefined}
        value={this.section(key)[field] ?? ''}
        oninput={(e: Event) => this.set(key, field, (e.target as HTMLInputElement).value)}
      />
    );
  }

  groupCheck(list: 'include' | 'exclude', group: Group) {
    const section = this.section('groups');
    const ids: number[] = Array.isArray(section[list]) ? section[list] : [];
    const id = Number(group.id());
    const checked = ids.includes(id);

    return (
      <label className="HeraldFilter-check" key={`${list}-${group.id()}`}>
        <input
          type="checkbox"
          checked={checked}
          onchange={() => this.set('groups', list, checked ? ids.filter((x) => x !== id) : [...ids, id])}
        />
        <span className="HeraldFilter-swatch" style={{ background: group.color() || 'var(--muted-color)' }} />
        {group.namePlural()}
      </label>
    );
  }
}
