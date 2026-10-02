<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Sending\Drivers;
use ErnestDefoe\Herald\Tag\TagRegistry;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

/**
 * What the compose screen needs to draw itself: the quick tags, and whether a
 * send carries on without the page open.
 */
class Meta extends Controller
{
    public function __construct(
        private TagRegistry $tags,
        private Drivers $drivers,
    ) {
    }

    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $tags = [];

        foreach ($this->tags->all() as $name => $description) {
            $tags[] = ['name' => $name, 'description' => $description];
        }

        return [
            'tags' => $tags,
            'aliases' => TagRegistry::ALIASES,
            'background' => $this->drivers->background(),
        ];
    }
}
