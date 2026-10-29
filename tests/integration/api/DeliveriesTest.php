<?php

namespace ErnestDefoe\Herald\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\Herald\Mail\Sender;
use ErnestDefoe\Herald\Mail\Template;
use ErnestDefoe\Herald\Mailing;
use ErnestDefoe\Herald\Sending\Processor;
use ErnestDefoe\Herald\Settings;
use ErnestDefoe\Herald\Tests\integration\SeedsHerald;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

/**
 * A delivery record per recipient: never sent twice, consent checked again
 * at the last moment, failures retried on a schedule, limits shared.
 */
class DeliveriesTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsHerald;

    /** The mail server, as far as these tests are concerned. */
    private object $mail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-herald');
        $this->seedHerald();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function boot(): Processor
    {
        $this->mail = new class extends Sender {
            public array $sent = [];
            public array $refuse = [];
            public ?\Closure $after = null;

            public function __construct()
            {
            }

            public function send(string $email, string $name, Template $template, array $values, array $urlTags): void
            {
                if (in_array($email, $this->refuse, true)) {
                    throw new RuntimeException('550 Mailbox unavailable');
                }

                $this->sent[] = $email;

                if ($this->after) {
                    ($this->after)($email);
                }
            }
        };

        $this->app()->getContainer()->instance(Sender::class, $this->mail);

        return $this->app()->getContainer()->make(Processor::class);
    }

    private function start(Processor $processor): Mailing
    {
        $mailing = Mailing::query()->find(1);
        $processor->start($mailing, null);

        return $mailing;
    }

    private function drain(Processor $processor, Mailing $mailing): void
    {
        for ($i = 0; $i < 10 && in_array($processor->batch($mailing)['state'], ['more'], true); $i++);
    }

    private function deliveries(): array
    {
        return $this->database()->table('herald_deliveries')->where('mailing_id', 1)->orderBy('user_id')->pluck('status', 'user_id')->all();
    }

    #[Test]
    public function nobody_is_sent_it_twice_even_when_the_send_is_replayed()
    {
        $processor = $this->boot();
        $mailing = $this->start($processor);
        $this->drain($processor, $mailing);

        $this->assertSame([1 => 'sent', 2 => 'sent', 3 => 'sent'], $this->deliveries());

        // A driver that lost its place starts the same mailing's pass again.
        $this->database()->table('herald_mailings')->where('id', 1)->update(['status' => 'sending', 'cursor' => 0]);
        $this->drain($processor, $mailing->refresh());

        $this->assertCount(3, $this->mail->sent, 'Three people, three emails');
    }

    #[Test]
    public function a_copy_whose_process_died_is_unknown_and_never_retried()
    {
        $processor = $this->boot();
        $mailing = $this->start($processor);

        // A process handed member 2's copy over and died before writing back.
        $this->database()->table('herald_deliveries')->insert(['mailing_id' => 1, 'user_id' => 2, 'status' => 'queued', 'attempts' => 1, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);

        $this->drain($processor, $mailing);

        $this->assertSame('unknown', $this->deliveries()[2]);
        $this->assertNotContains('normal@machine.local', $this->mail->sent);
    }

    #[Test]
    public function consent_is_checked_again_just_before_each_copy()
    {
        $processor = $this->boot();
        $mailing = $this->start($processor);

        // Member 2 opts out while the batch they are in is going out.
        $this->mail->after = function (string $email) {
            if ($email === 'admin@machine.local') {
                $this->database()->table('users')->where('id', 2)->update(['herald_subscribed' => false]);
            }
        };

        $this->drain($processor, $mailing);

        $this->assertSame('skipped', $this->deliveries()[2] ?? null);
        $this->assertNotContains('normal@machine.local', $this->mail->sent);
        $this->assertSame(1, (int) $mailing->refresh()->skipped_count);
    }

    #[Test]
    public function a_failed_copy_is_retried_after_5_then_30_minutes_then_left()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-29 12:00:00', 'UTC'));
        $processor = $this->boot();
        $this->mail->refuse = ['normal@machine.local'];

        $mailing = $this->start($processor);
        $this->drain($processor, $mailing);

        $row = fn () => $this->database()->table('herald_deliveries')->where('mailing_id', 1)->where('user_id', 2)->first();
        $this->assertSame('failed', $row()->status);
        $this->assertSame('550 Mailbox unavailable', $row()->error);
        $this->assertSame('2026-10-29 12:05:00', Carbon::parse($row()->next_attempt_at)->format('Y-m-d H:i:s'));
        $this->assertSame('sent', $mailing->refresh()->status, 'The mailing is done; the retry is the scheduler\'s');

        Carbon::setTestNow('2026-10-29 12:04:00');
        $processor->batch($mailing);
        $this->assertSame(1, (int) $row()->attempts, 'Not before its time');

        Carbon::setTestNow('2026-10-29 12:05:00');
        $processor->batch($mailing);
        $this->assertSame(2, (int) $row()->attempts);
        $this->assertSame('2026-10-29 12:35:00', Carbon::parse($row()->next_attempt_at)->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-10-29 12:35:00');
        $processor->batch($mailing);
        $this->assertSame(3, (int) $row()->attempts);
        $this->assertNull($row()->next_attempt_at, 'Three attempts, then it is left for Resend to failed');

        // Resend to failed, now that the mailbox works again.
        $this->mail->refuse = [];
        $this->assertSame(200, $this->call('POST', '/api/herald/mailings/1/resend-failed', 1, [])->getStatusCode());
        $this->drain($processor, $mailing->refresh());

        $this->assertSame('sent', $row()->status);
        $this->assertSame(3, (int) $mailing->refresh()->sent_count);
        $this->assertSame(0, (int) $mailing->failed_count);
        $this->assertSame(1, count(array_keys($this->mail->sent, 'normal@machine.local')), 'Sent once, in the end');
    }

    #[Test]
    public function sending_limits_are_shared_and_pause_the_send()
    {
        $this->setting(Settings::LIMIT_MINUTE, 2);
        Carbon::setTestNow(Carbon::parse('2026-10-29 12:00:00', 'UTC'));

        $processor = $this->boot();
        $mailing = $this->start($processor);

        $this->assertSame('more', $processor->batch($mailing)['state']);
        $this->assertCount(2, $this->mail->sent);
        $this->assertSame('limited', $processor->batch($mailing)['state']);
        $this->assertCount(2, $this->mail->sent);

        Carbon::setTestNow('2026-10-29 12:01:01');
        $this->drain($processor, $mailing);
        $this->assertCount(3, $this->mail->sent);
    }

    #[Test]
    public function the_list_and_the_mailing_show_their_outcomes()
    {
        $processor = $this->boot();
        $this->mail->refuse = ['moderator@machine.local'];
        $this->drain($processor, $this->start($processor));

        $list = $this->body($this->call('GET', '/api/herald/mailings', 1))['data'];
        $this->assertEquals(['sent' => 2, 'failed' => 1], $list[0]['outcomes']);

        $page = $this->body($this->call('GET', '/api/herald/mailings/1/deliveries?status=failed', 1));
        $this->assertSame(1, $page['total']);
        $this->assertSame('moderator', $page['data'][0]['username']);
        $this->assertSame('550 Mailbox unavailable', $page['data'][0]['error']);

        $this->assertSame(403, $this->call('GET', '/api/herald/mailings/1/deliveries', 2)->getStatusCode());
    }
}
