import Component from 'flarum/common/Component';

let Box: any = null;
let loading: Promise<void> | null = null;

/**
 * The signup box's shell. The box itself is a lazy chunk, fetched by a page
 * that actually shows it, so pages without the widget carry none of it.
 *
 * Place it anywhere: `m(flarum.reg.get('ernestdefoe-herald', 'forum/components/SignupWidget'), { heading: false })`.
 */
export default class SignupWidget extends Component<{ heading?: boolean }> {
  oninit(vnode: any) {
    super.oninit(vnode);

    loading ??= import('./SignupBox').then((mod) => {
      Box = mod.default;
      m.redraw();
    });
  }

  view() {
    return Box ? m(Box, this.attrs) : <div className="HeraldSignup HeraldSignup--loading" />;
  }
}
