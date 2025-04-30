<?php

namespace Tests\Feature;

use App\Enums\GlobalRole;
use App\Models\GlobalInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class GlobalInvitationAcceptanceTest extends TestCase
{
    use CreatesUsers;
    use DatabaseTruncation;

    /**
     * @var array<User>
     */
    private array $users;

    /**
     * @var array<GlobalInvitation>
     */
    private array $invitations = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = [
            'admin' => $this->makeAdminUser(),
            'normal' => $this->makeNormalUser(),
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->users as $user) {
            $user->delete();
        }
        $this->users = [];

        foreach ($this->invitations as $invitation) {
            $invitation->delete();
        }
        $this->invitations = [];

        parent::tearDown();
    }

    private function createInvitation(?string $password = null): GlobalInvitation
    {
        if ($password === null) {
            $password = Str::password();
        }

        /** @var GlobalInvitation $invitation */
        $invitation = GlobalInvitation::create([
            'email' => fake()->email(),
            'invited_by_id' => $this->users['admin']->id,
            'role' => GlobalRole::USER,
            'invitation_timestamp' => Carbon::now(),
            'password' => Hash::make($password),
        ]);
        $this->invitations[] = $invitation;

        return $invitation;
    }

    public function testAcceptValidRegularUserInvitation(): void
    {
        $invitation = $this->createInvitation();
        self::assertTrue($invitation->refresh()->exists());
        self::assertCount(2, User::all());

        $this->get($invitation->invitation_url)
            ->assertRedirect('/profile?password_expired=1');

        $user = User::where('email', $invitation->email)->firstOrFail();

        self::assertNull(GlobalInvitation::find($invitation->id));
        self::assertCount(3, User::all());
        self::assertEquals($invitation->email, $user->email);
        self::assertEquals($invitation->password, $user->password);
        self::assertFalse($user->admin);
    }

    public function testAcceptValidAdminInvitation(): void
    {
        $invitation = $this->createInvitation();
        $invitation->role = GlobalRole::ADMINISTRATOR;
        $invitation->save();
        self::assertTrue($invitation->refresh()->exists());
        self::assertCount(2, User::all());

        $this->get($invitation->invitation_url)
            ->assertRedirect('/profile?password_expired=1');

        $user = User::where('email', $invitation->email)->firstOrFail();

        self::assertNull(GlobalInvitation::find($invitation->id));
        self::assertCount(3, User::all());
        self::assertEquals($invitation->email, $user->email);
        self::assertEquals($invitation->password, $user->password);
        self::assertTrue($user->admin);
    }

    public function testCannotAcceptMissingInvitation(): void
    {
        $realInvitation = $this->createInvitation();
        self::assertTrue($realInvitation->refresh()->exists());
        self::assertCount(2, User::all());

        $deletedInvitation = $this->createInvitation();
        $deletedInvitation->delete();

        $this->get($deletedInvitation->invitation_url)
            ->assertNotFound();

        self::assertTrue($realInvitation->refresh()->exists());
        self::assertCount(2, User::all());
    }

    public function testCannotAcceptInvitationTwice(): void
    {
        $invitation = $this->createInvitation();
        self::assertTrue($invitation->refresh()->exists());
        self::assertCount(2, User::all());

        $this->get($invitation->invitation_url)
            ->assertRedirect('/profile?password_expired=1');

        self::assertNull(GlobalInvitation::find($invitation->id));
        self::assertCount(3, User::all());

        $this->get($invitation->invitation_url)
            ->assertNotFound();

        self::assertNull(GlobalInvitation::find($invitation->id));
        self::assertCount(3, User::all());
    }

    public function testCannotAcceptInvitationWhenAlreadyAMember(): void
    {
        $invitation = $this->createInvitation();

        User::forceCreate([
            'admin' => false,
            'email' => $invitation->email,
            'password' => Hash::make('12345'),
            'password_updated_at' => Carbon::now(),
        ]);

        self::assertTrue($invitation->refresh()->exists());
        self::assertCount(3, User::all());

        $this->get($invitation->invitation_url)
            ->assertUnauthorized();

        self::assertNull(GlobalInvitation::find($invitation->id));
        self::assertCount(3, User::all());
    }

    public function testCannotAcceptInvitationWhenPasswordAuthProhibited(): void
    {
        Config::set('cdash.username_password_authentication_enabled', false);

        $invitation = $this->createInvitation();
        self::assertTrue($invitation->refresh()->exists());
        self::assertCount(2, User::all());

        $this->get($invitation->invitation_url)
            ->assertUnauthorized();

        self::assertTrue($invitation->refresh()->exists());
        self::assertCount(2, User::all());

        Config::set('cdash.username_password_authentication_enabled', true);

        $this->get($invitation->invitation_url)
            ->assertRedirect('/profile?password_expired=1');

        self::assertNotNull(User::where('email', $invitation->email)->first());
        self::assertNull(GlobalInvitation::find($invitation->id));
        self::assertCount(3, User::all());
    }
}
