<?php

namespace ErnestDefoe\Herald\Api;

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

        if (array_key_exists('filters', $body)) {
            $mailing->filters = $this->filters($body['filters']);
        }
    }

    protected function serialize(Mailing $mailing, bool $withSource = false): array
    {
        $data = [
            'id' => $mailing->id,
            'subject' => $mailing->subject,
            'status' => $mailing->status,
            'filters' => (object) ($mailing->filters ?? []),
            'recipientTotal' => $mailing->recipient_total,
            'sentCount' => $mailing->sent_count,
            'failedCount' => $mailing->failed_count,
            'creator' => $mailing->creator?->display_name,
            'createdAt' => $mailing->created_at?->toIso8601String(),
            'updatedAt' => $mailing->updated_at?->toIso8601String(),
            'startedAt' => $mailing->started_at?->toIso8601String(),
            'completedAt' => $mailing->completed_at?->toIso8601String(),
        ];

        if ($withSource) {
            $data['content'] = $mailing->source();
        }

        return $data;
    }
}
