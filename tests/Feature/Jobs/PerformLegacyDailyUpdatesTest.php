<?php

namespace Tests\Feature\Jobs;

use App\Enums\ProjectRole;
use App\Jobs\PerformLegacyDailyUpdates;
use App\Models\BuildGroup;
use App\Models\BuildGroupRule;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Carbon;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class PerformLegacyDailyUpdatesTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    private Project $project;
    private Site $site;
    private BuildGroup $nightly;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
        $this->site = Site::factory()->create();
        $this->nightly = $this->project->buildgroups()->where('name', 'Nightly')->firstOrFail();
    }

    /**
     * @param array<string,mixed> $attributes
     */
    private function createRule(array $attributes): BuildGroupRule
    {
        return $this->nightly->rules()->create(array_merge([
            'buildtype' => 'Nightly',
            'siteid' => $this->site->id,
            'starttime' => Carbon::create(1980),
        ], $attributes));
    }

    public function testDeletesRulesWhichEndedBeforeAutoRemoveTimeframe(): void
    {
        $this->project->autoremovetimeframe = 10;
        $this->project->save();

        $active = $this->createRule(['buildname' => 'active']);
        $recently_ended = $this->createRule(['buildname' => 'recently-ended', 'endtime' => Carbon::now()->subDays(5)]);
        $long_ended = $this->createRule(['buildname' => 'long-ended', 'endtime' => Carbon::now()->subDays(20)]);

        PerformLegacyDailyUpdates::dispatch();

        self::assertModelExists($active);
        self::assertModelExists($recently_ended);
        self::assertModelMissing($long_ended);
    }

    public function testEmailsAboutActiveExpectedBuildsWhichDidNotSubmit(): void
    {
        $maintainer = User::factory()->create();
        $this->site->maintainers()->attach($maintainer);
        $administrator = User::factory()->create();
        $this->project->users()->attach($administrator, ['role' => ProjectRole::ADMINISTRATOR]);

        $this->createRule(['buildname' => 'active', 'expected' => 1]);
        $this->createRule(['buildname' => 'ended', 'expected' => 1, 'endtime' => Carbon::now()->subDays(5)]);

        PerformLegacyDailyUpdates::dispatch();

        $transport = app('mailer')->getSymfonyTransport();
        self::assertInstanceOf(ArrayTransport::class, $transport);
        $emails = [];
        foreach ($transport->messages() as $sent_message) {
            self::assertInstanceOf(SentMessage::class, $sent_message);
            $email = $sent_message->getOriginalMessage();
            if ($email instanceof Email && str_contains((string) $email->getSubject(), "[{$this->project->name}]")) {
                $emails[(string) $email->getSubject()] = [
                    'to' => array_map(fn (Address $address) => $address->getAddress(), $email->getTo()),
                    'body' => (string) $email->getTextBody(),
                ];
            }
        }

        self::assertEqualsCanonicalizing([
            "CDash [{$this->project->name}] - Missing Build for {$this->site->name}",
            "CDash [{$this->project->name}] - Missing Builds",
        ], array_keys($emails));
        foreach ($emails as $email) {
            self::assertStringContainsString("{$this->site->name} - active (Nightly)", $email['body']);
            self::assertStringNotContainsString('ended', $email['body']);
        }
        self::assertSame([$maintainer->email], $emails["CDash [{$this->project->name}] - Missing Build for {$this->site->name}"]['to']);
        self::assertSame([$administrator->email], $emails["CDash [{$this->project->name}] - Missing Builds"]['to']);
    }
}
