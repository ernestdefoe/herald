<?php

namespace ErnestDefoe\Herald\Filter;

use Illuminate\Contracts\Container\Container;

class FilterRegistry
{
    /** @var Filter[]|null */
    private ?array $resolved = null;

    /**
     * @param array<class-string<Filter>|callable> $filters
     */
    public function __construct(
        private Container $container,
        private array $filters,
    ) {
    }

    /**
     * @return Filter[]
     */
    public function all(): array
    {
        return $this->resolved ??= array_map(
            fn ($filter) => is_callable($filter) ? $filter($this->container) : $this->container->make($filter),
            $this->filters
        );
    }
}
