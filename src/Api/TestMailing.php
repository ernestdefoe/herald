<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Builder\Blocks;
use ErnestDefoe\Herald\Locale;
use ErnestDefoe\Herald\Mail\Renderer;
use ErnestDefoe\Herald\Mail\Sender;
use ErnestDefoe\Herald\Tag\TagRegistry;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sends the saved mailing to the admin pressing the button, and nobody else.
 */
class TestMailing extends Controller
{
    public function __construct(
        TranslatorInterface $translator,
        Blocks $blocks,
        private Renderer $renderer,
        private Sender $sender,
        private TagRegistry $tags,
        private Locale $locale,
    ) {
        parent::__construct($translator, $blocks);
    }

    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $mailing = $this->mailing($request);

        $template = $this->renderer->template($mailing, $this->locale->for($actor));
        $values = $this->tags->values(collect([$actor]))[$actor->id] ?? [];

        $this->sender->send((string) $actor->email, (string) $actor->display_name, $template, $values, $this->tags->urlTags());

        return ['sent' => true, 'to' => $actor->email];
    }
}
