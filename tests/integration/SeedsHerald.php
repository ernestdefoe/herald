<?php

namespace ErnestDefoe\Herald\Tests\integration;

use Carbon\Carbon;
use Flarum\Group\Group;
use Flarum\User\User;

/**
 * Users: 1 admin, 2 member, 3 moderator (confirmed, subscribed), 4 opted out,
 * 5 unconfirmed. Mail goes to the log driver, so nothing leaves the machine.
 */
trait SeedsHerald
{
    protected function seedHerald(): void
    {
        $this->setting('mail_driver', 'log');

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'moderator', 'email' => 'moderator@machine.local', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'optedout', 'email' => 'optedout@machine.local', 'is_email_confirmed' => 1, 'herald_subscribed' => false],
                ['id' => 5, 'username' => 'unconfirmed', 'email' => 'unconfirmed@machine.local', 'is_email_confirmed' => 0],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => Group::MODERATOR_ID],
                ['user_id' => 4, 'group_id' => Group::MEMBER_ID],
            ],
            'herald_mailings' => [
                ['id' => 1, 'subject' => 'Hello {member_name}', 'content' => '<t><p>News for the forum</p></t>', 'status' => 'draft', 'created_by' => 1, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ],
        ]);
    }

    /** A request that gets past CSRF for a guest, and authenticates anyone else. */
    protected function call(string $method, string $path, ?int $actor = null, ?array $json = null)
    {
        $options = $json === null ? [] : ['json' => $json];

        if ($actor) {
            return $this->send($this->request($method, $path, $options + ['authenticatedAs' => $actor]));
        }

        $request = $this->request($method, $path, $options);

        return $this->send($method === 'GET' ? $request : $this->requestWithCsrfToken($request));
    }

    protected function body($response): array
    {
        return json_decode((string) $response->getBody(), true) ?? [];
    }
}
