<?php

namespace ErnestDefoe\Herald\Mail;

use ErnestDefoe\Herald\Personaliser;
use ErnestDefoe\Herald\Settings;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Illuminate\Support\HtmlString;

class Sender
{
    public function __construct(
        private Mailer $mailer,
        private Personaliser $personaliser,
        private SettingsRepositoryInterface $settings,
    ) {
    }

    /**
     * @param array<string, string> $values
     * @param string[] $urlTags
     */
    public function send(string $email, string $name, Template $template, array $values, array $urlTags): void
    {
        $subject = $this->personaliser->text($template->subject, $values, $urlTags);
        $html = $this->personaliser->html($template->html, $values, $urlTags);
        $text = $this->personaliser->text($template->text, $values, $urlTags);
        $unsubscribe = $values['unsubscribe_url'] ?? null;
        $replyTo = trim((string) $this->settings->get(Settings::REPLY_TO));

        $this->mailer->send(
            ['html' => new HtmlString($html), 'text' => new HtmlString($text)],
            // Flarum's mailer reads these to say WHO a failed send was for.
            ['userEmail' => $email, 'username' => $name],
            function (Message $message) use ($email, $name, $subject, $unsubscribe, $replyTo) {
                $message->to($email, $name !== '' ? $name : null)->subject($subject);

                if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                    $message->replyTo($replyTo);
                }

                if ($unsubscribe) {
                    // RFC 8058 one-click unsubscribe. Gmail and Yahoo require
                    // it from bulk senders, and without it a forum's mail
                    // lands in spam.
                    $headers = $message->getSymfonyMessage()->getHeaders();
                    $headers->addTextHeader('List-Unsubscribe', '<'.$unsubscribe.'>');
                    $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                }
            }
        );
    }
}
