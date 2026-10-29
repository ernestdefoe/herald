<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Builder\Blocks;
use ErnestDefoe\Herald\Builder\Discussions;
use ErnestDefoe\Herald\Sending\Drivers;
use ErnestDefoe\Herald\Tag\TagRegistry;
use Flarum\Extension\ExtensionManager;
use Flarum\User\Guest;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

/**
 * What the compose screen and the builder need to draw themselves: the quick
 * tags, whether a send carries on without the page open, the social networks
 * a block can link to, and the tags a Latest discussions block can list from.
 */
class Meta extends Controller
{
    public function __construct(
        private TagRegistry $tags,
        private Drivers $drivers,
        private ExtensionManager $extensions,
    ) {
    }

    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $tags = [];

        foreach ($this->tags->all() as $name => $description) {
            $tags[] = ['name' => $name, 'description' => $description];
        }

        // Filled once per mailing by the renderer, not per member, so it is
        // not a provider's tag. Editor mailings only: the builder has a block.
        $tags[] = ['name' => Discussions::TAG, 'description' => 'ernestdefoe-herald.forum.tags.latest_discussions'];

        return [
            'tags' => $tags,
            'aliases' => TagRegistry::ALIASES,
            'background' => $this->drivers->background(),
            'networks' => Blocks::NETWORKS,
            'forumTags' => $this->forumTags(),
        ];
    }

    /**
     * Only the tags a GUEST can see: they are what the block can list from,
     * because that is how it lists.
     */
    private function forumTags(): array
    {
        $class = 'Flarum\\Tags\\Tag';

        if (! $this->extensions->isEnabled('flarum-tags') || ! class_exists($class)) {
            return [];
        }

        return $class::query()
            ->whereVisibleTo(new Guest())
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'position'])
            ->map(fn ($tag) => ['id' => (int) $tag->id, 'name' => (string) $tag->name, 'parentId' => $tag->parent_id ? (int) $tag->parent_id : null])
            ->all();
    }
}
