<?php

/*
 * Larastan checks a view name passed as a literal (a `view-string`) by
 * calling Laravel's global view() helper, which Flarum does not define:
 * PHPStan crashed with "Call to undefined function view()" on
 * src/Http/UnsubscribeController.php. This answers with Flarum's view
 * factory, with Herald's namespace registered as extend.php does (the test
 * forum the bootstrap boots doesn't enable the extension), so a view that
 * exists passes and a mistyped name is still reported. As fof/polls does.
 */
if (! function_exists('view')) {
    function view(): Illuminate\Contracts\View\Factory
    {
        /** @var Illuminate\Contracts\View\Factory $view */
        $view = resolve(Illuminate\Contracts\View\Factory::class);
        $view->addNamespace('ernestdefoe-herald', __DIR__.'/../views');

        return $view;
    }
}
