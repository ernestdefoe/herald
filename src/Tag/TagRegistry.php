<?php

namespace ErnestDefoe\Herald\Tag;

use ErnestDefoe\Herald\Subscriber;
use ErnestDefoe\Herald\Unsubscribe\Token;
use Flarum\User\User;
use Illuminate\Contracts\Container\Container;
use Symfony\Contracts\Translation\TranslatorInterface;
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

    /**
     * A guest subscriber's values. They are not a member, so the member tags
     * are blank — except {member_name}, which reads "there" ("Hi there") —
     * and the forum-wide tags are what every member gets.
     *
     * @return array<string, string>
     */
    public function guestValues(Subscriber $subscriber): array
    {
        $values = array_fill_keys(array_keys($this->all()), '');

        foreach ($this->providers() as $provider) {
            if ($provider instanceof SuiteTags) {
                $values = array_merge($values, array_values($provider->values(collect([new User()])))[0] ?? []);
            }
        }

        $unsubscribe = $this->container->make(Token::class)->subscriberUrl($subscriber);
        $values['member_name'] = $this->container->make(TranslatorInterface::class)->trans('ernestdefoe-herald.email.guest_name');
        $values['unsubscribe_url'] = $unsubscribe;
        $values['settings_url'] = $unsubscribe;

        foreach (self::ALIASES as $alias => $canonical) {
            $values[$alias] = $values[$canonical] ?? '';
        }

        return $values;
    }
}
