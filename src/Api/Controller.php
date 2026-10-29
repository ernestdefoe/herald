<?php

namespace ErnestDefoe\Herald\Api;

use Carbon\Carbon;
use ErnestDefoe\Herald\Builder\Blocks;
use ErnestDefoe\Herald\Mailing;
use ErnestDefoe\Herald\Settings;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

abstract class Controller implements RequestHandlerInterface
{
    abstract protected function respond(ServerRequestInterface $request, User $actor): mixed;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $actor->assertCan(Settings::PERMISSION);

        return new JsonResponse($this->respond($request, $actor));
    }

    protected function body(ServerRequestInterface $request): array
    {
        return (array) $request->getParsedBody();
    }

    protected function mailing(ServerRequestInterface $request): Mailing
    {
        return Mailing::query()->findOrFail((int) Arr::get($request->getQueryParams(), 'id'));
    }

    protected function filters(mixed $filters): array
    {
        return is_array($filters) ? $filters : [];
    }

    /**
     * @throws ValidationException
     */
    protected function fill(Mailing $mailing, array $body, User $actor): void
    {
        if (array_key_exists('subject', $body)) {
            $subject = trim((string) $body['subject']);

            if (mb_strlen($subject) > 255) {
                throw new ValidationException(['subject' => resolve('translator')->trans('ernestdefoe-herald.forum.errors.subject_too_long')]);
            }

            $mailing->subject = $subject;
        }

        if (array_key_exists('content', $body)) {
            $mailing->setSource((string) $body['content'], $actor);
        }

        if (in_array($body['type'] ?? null, [Mailing::EDITOR, Mailing::BUILDER], true)) {
            $mailing->type = $body['type'];
        }

        if (array_key_exists('blocks', $body)) {
            $mailing->blocks = $body['blocks'] === null ? null : resolve(Blocks::class)->normalise($body['blocks']);
        }

        if (array_key_exists('preheader', $body)) {
            $preheader = mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) $body['preheader'])), 0, 255);
            $mailing->preheader = $preheader === '' ? null : $preheader;
        }

        if (array_key_exists('filters', $body)) {
            $mailing->filters = $this->filters($body['filters']);
        }

        if (array_key_exists('archived', $body) && $mailing->is_template) {
            $mailing->archived_at = $body['archived'] ? ($mailing->archived_at ?? Carbon::now()) : null;
        }
    }

    /**
     * Delivery outcomes for many mailings, in ONE grouped query.
     *
     * @param int[] $ids
     * @return array<int, array<string, int>>
     */
    protected function outcomes(array $ids): array
    {
        $out = [];

        if (! $ids) {
            return $out;
        }

        $rows = resolve('db.connection')->table('herald_deliveries')
            ->whereIn('mailing_id', $ids)
            ->groupBy('mailing_id', 'status')
            ->selectRaw('mailing_id, status, COUNT(*) as total')
            ->get();

        foreach ($rows as $row) {
            $out[(int) $row->mailing_id][$row->status] = (int) $row->total;
        }

        return $out;
    }

    /**
     * @param array<string, int>|null $outcomes delivery rows by status
     */
    protected function serialize(Mailing $mailing, bool $withSource = false, ?array $outcomes = null): array
    {
        $data = [
            'id' => $mailing->id,
            'subject' => $mailing->subject,
            'status' => $mailing->status,
            'type' => $mailing->type,
            'preheader' => (string) $mailing->preheader,
            'isTemplate' => (bool) $mailing->is_template,
            'archivedAt' => $mailing->archived_at?->toIso8601String(),
            'scheduledAt' => $mailing->scheduled_at?->toIso8601String(),
            'timezone' => $mailing->timezone,
            'repeat' => $mailing->repeat_every,
            'seriesId' => $mailing->series_id,
            'skippedCount' => (int) $mailing->skipped_count,
            'filters' => (object) ($mailing->filters ?? []),
            'recipientTotal' => (int) $mailing->recipient_total,
            'sentCount' => (int) $mailing->sent_count,
            'failedCount' => (int) $mailing->failed_count,
            'creator' => $mailing->creator?->display_name,
            'createdAt' => $mailing->created_at?->toIso8601String(),
            'updatedAt' => $mailing->updated_at?->toIso8601String(),
            'startedAt' => $mailing->started_at?->toIso8601String(),
            'completedAt' => $mailing->completed_at?->toIso8601String(),
        ];

        if ($outcomes !== null) {
            $data['outcomes'] = (object) $outcomes;
        }

        if ($withSource) {
            $data['content'] = $mailing->source();
            $data['blocks'] = $mailing->blocks ?? [];
        }

        return $data;
    }
}
