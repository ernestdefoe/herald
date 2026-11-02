<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Audience;
use ErnestDefoe\Herald\Builder\Blocks;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CountRecipients extends Controller
{
    public function __construct(
        TranslatorInterface $translator,
        Blocks $blocks,
        private Audience $audience,
    ) {
        parent::__construct($translator, $blocks);
    }

    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        return $this->audience->count($this->filters($this->body($request)['filters'] ?? []));
    }
}
