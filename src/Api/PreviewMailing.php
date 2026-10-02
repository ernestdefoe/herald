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
 * details — what Invision's preview does. Works on unsaved content, so the
 * editor can preview before anything is saved.
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

        if (isset($body['id']) && ! array_key_exists('content', $body)) {
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
            $mailing->setSource((string) ($body['content'] ?? ''), $actor);
        }

        $template = $this->renderer->template($mailing, $this->locale->for($actor));
        $values = $this->tags->values(collect([$actor]))[$actor->id] ?? [];

        return [
            'subject' => $this->personaliser->text($template->subject, $values),
            'html' => $this->personaliser->html($template->html, $values, $this->tags->urlTags()),
        ];
    }
}
