<?php

namespace ErnestDefoe\Herald\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\Herald\Sending\Schedule;
use ErnestDefoe\Herald\Sending\Scheduler;
use ErnestDefoe\Herald\Tests\integration\SeedsHerald;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Send on a date, once or repeating, from Flarum's scheduler.
 */
class SchedulingTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsHerald;

    protected function setUp(): void
    {
        parent::setUp();

        // Monday 26 October 2026, 08:00 in New York (EDT, UTC-4).
        Carbon::setTestNow(Carbon::parse('2026-10-26 12:00:00', 'UTC'));

        $this->extension('ernestdefoe-herald');
        $this->seedHerald();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function schedule(array $body, int $status = 200): array
    {
        $response = $this->call('POST', '/api/herald/mailings/1/schedule', 1, $body);
        $this->assertSame($status, $response->getStatusCode(), (string) $response->getBody());

        return $this->body($response);
    }

    private function mailing(int $id = 1): object
    {
        return $this->database()->table('herald_mailings')->find($id);
    }

    private function runScheduler(): array
    {
        return $this->app()->getContainer()->make(Scheduler::class)->run();
    }

    #[Test]
    public function a_time_too_soon_too_far_or_in_no_real_zone_is_refused()
    {
        $this->schedule(['at' => '2026-10-26T08:02', 'timezone' => 'America/New_York'], 422);
        $this->schedule(['at' => '2027-12-01T09:00', 'timezone' => 'America/New_York'], 422);
        $this->schedule(['at' => '2026-10-27T09:00', 'timezone' => 'Mars/Olympus'], 422);
        $this->schedule(['at' => 'tomorrow', 'timezone' => 'UTC'], 422);
        $this->schedule(['at' => '2026-10-27T09:00', 'timezone' => 'UTC', 'repeat' => 'hourly'], 422);

        $this->assertSame('draft', $this->mailing()->status);
    }

    #[Test]
    public function a_scheduled_mailing_goes_at_its_time_in_its_zone()
    {
        $data = $this->schedule(['at' => '2026-10-27T09:00', 'timezone' => 'America/New_York'])['data'];

        $this->assertSame('scheduled', $data['status']);
        $this->assertSame('2026-10-27T13:00:00+00:00', $data['scheduledAt'], 'Stored as UTC');
        $this->assertSame('America/New_York', $data['timezone']);

        // Still editable until it starts.
        $this->assertSame(200, $this->call('PATCH', '/api/herald/mailings/1', 1, ['subject' => 'Edited'])->getStatusCode());

        Carbon::setTestNow(Carbon::parse('2026-10-27 12:59:00', 'UTC'));
        $this->assertSame([], $this->runScheduler());

        Carbon::setTestNow(Carbon::parse('2026-10-27 13:00:00', 'UTC'));
        $this->assertCount(1, $this->runScheduler());
        $this->assertSame('sending', $this->mailing()->status);
        $this->assertSame(3, (int) $this->mailing()->recipient_total, 'The audience is counted when it goes');

        $this->assertSame([], $this->runScheduler(), 'Never started twice');
    }

    #[Test]
    public function a_scheduled_mailing_can_be_unscheduled()
    {
        $this->schedule(['at' => '2026-10-27T09:00', 'timezone' => 'UTC']);

        $this->assertSame(200, $this->call('DELETE', '/api/herald/mailings/1/schedule', 1)->getStatusCode());
        $this->assertSame('draft', $this->mailing()->status);
        $this->assertNull($this->mailing()->scheduled_at);

        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00', 'UTC'));
        $this->assertSame([], $this->runScheduler());
    }

    #[Test]
    public function a_weekly_series_keeps_its_local_time_across_a_dst_change()
    {
        // Mondays at 09:00 New York. Clocks go back on Sunday 1 November.
        $data = $this->schedule(['at' => '2026-10-26T09:00', 'timezone' => 'America/New_York', 'repeat' => 'weekly'])['data'];
        $this->assertSame('2026-10-26T13:00:00+00:00', $data['scheduledAt']);

        Carbon::setTestNow(Carbon::parse('2026-10-26 13:00:30', 'UTC'));
        $first = $this->runScheduler();
        $this->assertCount(1, $first);

        $series = $this->mailing();
        $this->assertSame('scheduled', $series->status, 'The series itself is never sent');
        $this->assertSame('2026-11-02 14:00:00', Carbon::parse($series->scheduled_at)->format('Y-m-d H:i:s'), '09:00 EST is 14:00 UTC');

        $issue = $this->mailing($first[0]->id);
        $this->assertSame(1, (int) $issue->series_id);
        $this->assertSame('sending', $issue->status);

        $this->assertSame([], $this->runScheduler(), 'One issue per run, however often the scheduler fires');

        Carbon::setTestNow(Carbon::parse('2026-11-02 14:00:00', 'UTC'));
        $second = $this->runScheduler();
        $this->assertCount(1, $second);
        $this->assertNotSame($first[0]->id, $second[0]->id, 'Each issue is its own mailing, with its own deliveries');
        $this->assertSame('2026-11-09 14:00:00', Carbon::parse($this->mailing()->scheduled_at)->format('Y-m-d H:i:s'));

        $issues = $this->body($this->call('GET', '/api/herald/mailings?series=1', 1))['data'];
        $this->assertCount(2, $issues);

        $list = $this->body($this->call('GET', '/api/herald/mailings', 1))['data'];
        $this->assertSame([1], array_column($list, 'id'), 'Issues are listed on their series, not in the main list');
    }

    #[Test]
    public function the_next_time_counts_from_the_first_and_never_drifts()
    {
        $anchor = Carbon::parse('2026-01-31 14:00:00', 'UTC'); // 09:00 New York

        $this->assertSame('2026-02-28 14:00', Schedule::next($anchor, 'America/New_York', 'monthly', $anchor)->format('Y-m-d H:i'));
        $this->assertSame('2026-03-31 13:00', Schedule::next($anchor, 'America/New_York', 'monthly', Carbon::parse('2026-02-28 14:00:00', 'UTC'))->format('Y-m-d H:i'), 'Back to the 31st, at 09:00 EDT');
        $this->assertSame('2026-03-08 13:00', Schedule::next($anchor, 'America/New_York', 'daily', Carbon::parse('2026-03-07 14:00:00', 'UTC'))->format('Y-m-d H:i'), 'The day the clocks go forward');
    }
}
