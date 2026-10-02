<?php

namespace ErnestDefoe\Herald\Tag;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;

class TagRegistry
{
    /**
     * Friendlier names that mean the same thing. The IPS names stay canonical.
     */
    public const ALIASES = [
        'username' => 'member_name',
        'board_title' => 'suite_name',
        'board_name' => 'suite_name',
        'board_url' => 'suite_url',
    ];

    /** @var TagProvider[]|null */
    private ?array $resolved = null;

    /**
     * @param class-string<TagProvider>[] $providers
     */
    public function __construct(
        private Container $container,
        private array $providers,
    ) {
    }

    /**
     * @return TagProvider[]
     */
    public function providers(): array
    {
        return $this->resolved ??= array_map(fn ($class) => $this->container->make($class), $this->providers);
    }

    /**
     * @return array<string, string> name => description translation key
     */
    public function all(): array
    {
        $tags = [];

        foreach ($this->providers() as $provider) {
            $tags += $provider->tags();
        }

        return $tags;
    }

    /**
     * @return string[]
     */
    public function urlTags(): array
    {
        $urls = [];

        foreach ($this->providers() as $provider) {
            array_push($urls, ...$provider->urlTags());
        }

        return $urls;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function values(Collection $users): array
    {
        $values = [];

        foreach ($this->providers() as $provider) {
            foreach ($provider->values($users) as $userId => $userValues) {
                $values[$userId] = ($values[$userId] ?? []) + $userValues;
            }
        }

        foreach ($values as $userId => $userValues) {
            foreach (self::ALIASES as $alias => $canonical) {
                if (array_key_exists($canonical, $userValues)) {
                    $values[$userId][$alias] = $userValues[$canonical];
                }
            }
        }

        return $values;
    }
}
