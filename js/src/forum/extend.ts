import Extend from 'flarum/common/extenders';
import HeraldListPage from './components/HeraldListPage';
import HeraldEditPage from './components/HeraldEditPage';

export default [
  new Extend.Routes()
    .add('herald', '/herald', HeraldListPage)
    .add('herald.new', '/herald/new', HeraldEditPage)
    .add('herald.edit', '/herald/:id', HeraldEditPage),
];
