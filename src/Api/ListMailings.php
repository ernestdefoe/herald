<?php

namespace ErnestDefoe\Herald\Api;

use ErnestDefoe\Herald\Mailing;
use ErnestDefoe\Herald\Starters;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The list page's three views — mailings, templates, archived templates —
 * and a series' issues. Issues and templates stay out of the mailings list.
 */
class ListMailings extends Controller
{
    public function __construct(private Starters $starters)
    {
    }

    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $params = $request->getQueryParams();
        $view = Arr::get($params, 'view');
        $query = Mailing::query()->with('creator')->orderByDesc('id')->limit(200);

        if (isset($params['series'])) {
            $query->where('series_id', (int) $params['series']);
        } elseif ($view === 'templates' || $view === 'archived') {
            $this->starters->ensure($actor);
            $query->where('is_template', true)->when($view === 'archived', fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'));
        } else {
            $query->where('is_template', false)->whereNull('series_id');
        }

        $mailings = $query->get();
        $outcomes = $this->outcomes($mailings->pluck('id')->all());

        return ['data' => $mailings->map(fn (Mailing $mailing) => $this->serialize($mailing, false, $outcomes[$mailing->id] ?? []))->all()];
    }
}
