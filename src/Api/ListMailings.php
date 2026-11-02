<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Builder\Blocks;
use ErnestDefoe\Herald\Mailing;
use ErnestDefoe\Herald\Starters;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The list page's three views — mailings, templates, archived templates —
 * and a series' issues. Issues and templates stay out of the mailings list.
 * 50 a page, newest first, with the total so every older mailing stays reachable.
 */
class ListMailings extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(
        TranslatorInterface $translator,
        Blocks $blocks,
        private Starters $starters,
    ) {
        parent::__construct($translator, $blocks);
    }

    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $params = $request->getQueryParams();
        $view = Arr::get($params, 'view');
        $page = max(1, (int) Arr::get($params, 'page', 1));
        $query = Mailing::query();

        if (isset($params['series'])) {
            $query->where('series_id', (int) $params['series']);
        } elseif ($view === 'templates' || $view === 'archived') {
            $this->starters->ensure($actor);
            $query->where('is_template', true)->when($view === 'archived', fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'));
        } else {
            $query->where('is_template', false)->whereNull('series_id');
        }

        $total = (clone $query)->count();
        $mailings = $query->with('creator')->orderByDesc('id')->forPage($page, self::PER_PAGE)->get();
        $outcomes = $this->outcomes($mailings->pluck('id')->all());

        return [
            'data' => $mailings->map(fn (Mailing $mailing) => $this->serialize($mailing, false, $outcomes[$mailing->id] ?? []))->all(),
            'total' => $total,
            'perPage' => self::PER_PAGE,
        ];
    }
}
