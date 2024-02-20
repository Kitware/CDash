<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\MigrationTest;
use Tests\Traits\CreatesUsers;

use App\Models\User;

class UniqueEmailsMigration extends MigrationTest
{
    use CreatesUsers;

    protected function tearDown() : void
    {
        parent::tearDown();
    }

    /**
     * Test case for the migration that adds a unique constraint on the users.email field.
     *
     * @return void
     */
    public function testUniqueEmailsMigration()
    {
        // Clear out any matching users from previous tests.
        User::where('email', 'jane@smith')->delete();

        // Rollback the relevant migration.
        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2023_12_19_211314_user_unique_email.php',
            '--force' => true]);

        // Create two users with the same email address.
        $user1 = $this->makeNormalUser();
        $user2 = $this->makeNormalUser();

        // Verify that they both exist and have different ids.
        self::assertIsNumeric($user1->id);
        self::assertIsNumeric($user2->id);
        self::assertNotEquals($user1->id, $user2->id);
        $this->assertDatabaseHas('user', ['id' => $user1->id]);
        $this->assertDatabaseHas('user', ['id' => $user2->id]);

        // Run the migration under test.
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2023_12_19_211314_user_unique_email.php',
            '--force' => true]);

        // Verify that user1 still exists and user2 does not.
        $this->assertDatabaseHas('user', ['id' => $user1->id]);
        $this->assertDatabaseMissing('user', ['id' => $user2->id]);

        // Clean up.
        User::where('email', $user1->email)->delete();
    }
}
