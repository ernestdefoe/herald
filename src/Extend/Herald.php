<?php

namespace ErnestDefoe\Herald\Extend;

use ErnestDefoe\Herald\Filter\Filter;
use ErnestDefoe\Herald\Tag\TagProvider;
use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Illuminate\Contracts\Container\Container;

/**
 * How another extension adds quick tags or recipient filters:
 *
 *     (new \ErnestDefoe\Herald\Extend\Herald())
 *         ->tags(MyTags::class)
 *         ->filter(MyFilter::class),
 *
 * Wrap it in `Extend\Conditional` keyed on `ernestdefoe-herald` so your
 * extension still boots when Herald is not installed.
 */
class Herald implements ExtenderInterface
{
    private array $tags = [];
    private array $filters = [];

    /**
     * @param class-string<TagProvider> $provider
     */
    public function tags(string $provider): self
    {
        $this->tags[] = $provider;

        return $this;
    }

    /**
     * @param class-string<Filter> $filter
     */
    public function filter(string $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    public function extend(Container $container, ?Extension $extension = null): void
    {
        $container->extend('herald.tags', fn (array $tags) => array_merge($tags, $this->tags));
        $container->extend('herald.filters', fn (array $filters) => array_merge($filters, $this->filters));
    }
}
