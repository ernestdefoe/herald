<?php

namespace ErnestDefoe\Herald\Api;

use Carbon\Carbon;
use ErnestDefoe\Herald\Locale;
use ErnestDefoe\Herald\Mail\Renderer;
use ErnestDefoe\Herald\Mailing;
use ErnestDefoe\Herald\Personaliser;
use ErnestDefoe\Herald\Tag\TagRegistry;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The email exactly as it would arrive, filled in with the ADMIN's own
 * details — what Invision's preview does — or a chosen member's. Works on
 * unsaved content, so the editor and the builder preview before anything is
 * saved; the builder's live preview is this, on every change.
 */
class PreviewMailing extends Controller
{
    public function __construct(
        private Renderer $renderer,
        private TagRegistry $tags,
        private Personaliser $personaliser,
        private Locale $locale,
    ) {
    }

    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $body = $this->body($request);

        if (isset($body['id']) && ! array_key_exists('content', $body) && ! array_key_exists('blocks', $body)) {
            // A saved mailing previews from what is STORED, exactly as it
            // would be sent. Re-parsing its source would run it through
            // whichever editor the forum uses today — a mailing written in
            // Markdown, previewed after a switch to a rich editor, would show
            // its asterisks.
            $mailing = Mailing::query()->findOrFail((int) $body['id']);
        } else {
            $mailing = new Mailing();
            $mailing->id = 0;
            $mailing->updated_at = Carbon::now();
            $mailing->subject = trim((string) ($body['subject'] ?? ''));
            $this->fill($mailing, array_intersect_key($body, array_flip(['type', 'blocks', 'preheader'])), $actor);

            if (! $mailing->isBuilt()) {
                $mailing->setSource((string) ($body['content'] ?? ''), $actor);
            }
        }

        $as = isset($body['userId']) ? User::query()->find((int) $body['userId']) : null;
        $as ??= $actor;

        $template = $this->renderer->template($mailing, $this->locale->for($as), false, true);
        $values = $this->tags->values(collect([$as]))[$as->id] ?? [];

        return [
            'subject' => $this->personaliser->text($template->subject, $values),
            'html' => $this->personaliser->html($template->html, $values, $this->tags->urlTags()),
            'text' => $this->personaliser->text($template->text, $values, $this->tags->urlTags()),
        ];
    }
}
