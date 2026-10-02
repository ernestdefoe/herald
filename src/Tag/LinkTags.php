<?php

namespace ErnestDefoe\Herald\Tag;

use ErnestDefoe\Herald\Unsubscribe\Token;
use Flarum\Http\UrlGenerator;
use Illuminate\Support\Collection;

class LinkTags implements TagProvider
{
    public function __construct(
        private Token $token,
        private UrlGenerator $url,
    ) {
    }

    public function tags(): array
    {
        return [
            'unsubscribe_url' => 'ernestdefoe-herald.forum.tags.unsubscribe_url',
            'settings_url' => 'ernestdefoe-herald.forum.tags.settings_url',
        ];
    }

    public function urlTags(): array
    {
        return ['unsubscribe_url', 'settings_url'];
    }

    public function values(Collection $users): array
    {
        $settings = $this->url->to('forum')->route('settings');
        $values = [];

        foreach ($users as $user) {
            $values[$user->id] = [
                'unsubscribe_url' => $this->token->url($user),
                'settings_url' => $settings,
            ];
        }

        return $values;
    }
}
