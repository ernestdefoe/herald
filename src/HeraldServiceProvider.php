<?php

namespace ErnestDefoe\Herald;

use ErnestDefoe\Herald\Filter\CountFilter;
use ErnestDefoe\Herald\Filter\FilterRegistry;
use ErnestDefoe\Herald\Filter\GroupFilter;
use ErnestDefoe\Herald\Filter\JoinedFilter;
use ErnestDefoe\Herald\Filter\LastVisitFilter;
use ErnestDefoe\Herald\Filter\SuspendedFilter;
use ErnestDefoe\Herald\Tag\LinkTags;
use ErnestDefoe\Herald\Tag\MemberTags;
use ErnestDefoe\Herald\Tag\SuiteTags;
use ErnestDefoe\Herald\Tag\TagRegistry;
use Flarum\Formatter\Formatter;
use Flarum\Foundation\AbstractServiceProvider;
use Illuminate\Contracts\Container\Container;

class HeraldServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton('herald.tags', fn () => [
            MemberTags::class,
            SuiteTags::class,
            LinkTags::class,
        ]);

        $this->container->singleton('herald.filters', fn () => [
            GroupFilter::class,
            JoinedFilter::class,
            LastVisitFilter::class,
            fn () => CountFilter::posts(),
            fn () => CountFilter::discussions(),
            SuspendedFilter::class,
        ]);

        $this->container->singleton(TagRegistry::class, fn (Container $c) => new TagRegistry($c, $c->make('herald.tags')));
        $this->container->singleton(FilterRegistry::class, fn (Container $c) => new FilterRegistry($c, $c->make('herald.filters')));
    }

    public function boot(Formatter $formatter): void
    {
        Mailing::setFormatter($formatter);
    }
}
