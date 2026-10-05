import Extend from 'flarum/common/extenders';

export default [
  /*
   * Staff-only pages, so they are lazy chunks: members and guests never
   * download the composer, the mailing list or their modals.
   */
  new Extend.Routes()
    .add('herald', '/herald', () => import('./components/HeraldListPage'))
    .add('herald.new', '/herald/new', () => import('./components/HeraldEditPage'))
    .add('herald.edit', '/herald/:id', () => import('./components/HeraldEditPage')),
];
