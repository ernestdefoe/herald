<?php

namespace ErnestDefoe\Herald\Tag;

use Flarum\Discussion\Discussion;
use Flarum\Http\UrlGenerator;
use Flarum\Post\CommentPost;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Support\Collection;

/**
 * The forum-wide tags. Invision calls the forum the "suite"; the names are
 * kept so an admin coming from IPS types what they already know.
 */
class SuiteTags implements TagProvider
{
    private ?array $cached = null;

    public function __construct(
        private SettingsRepositoryInterface $settings,
        private UrlGenerator $url,
    ) {
    }

    public function tags(): array
    {
        return [
            'suite_name' => 'ernestdefoe-herald.forum.tags.suite_name',
            'suite_url' => 'ernestdefoe-herald.forum.tags.suite_url',
            'reg_total' => 'ernestdefoe-herald.forum.tags.reg_total',
            'total_posts' => 'ernestdefoe-herald.forum.tags.total_posts',
            'total_discussions' => 'ernestdefoe-herald.forum.tags.total_discussions',
        ];
    }

    public function urlTags(): array
    {
        return ['suite_url'];
    }

    public function values(Collection $users): array
    {
        $this->cached ??= [
            'suite_name' => (string) $this->settings->get('forum_title'),
            'suite_url' => $this->url->to('forum')->base(),
            'reg_total' => number_format(User::query()->where('is_email_confirmed', true)->count()),
            'total_posts' => number_format(CommentPost::query()->whereNull('hidden_at')->count()),
            'total_discussions' => number_format(Discussion::query()->whereNull('hidden_at')->where('is_private', false)->count()),
        ];

        return $users->mapWithKeys(fn (User $user) => [$user->id => $this->cached])->all();
    }
}
