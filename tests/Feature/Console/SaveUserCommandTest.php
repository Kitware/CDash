<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SaveUserCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function testRequiresEmail(): void
    {
        Artisan::call('user:save', ['--firstname' => 'Jane']);

        self::assertSame("You must specify the --email option\n", Artisan::output());
    }

    public function testCreatesUser(): void
    {
        $email = Str::uuid()->toString() . '@example.com';

        Artisan::call('user:save', [
            '--email' => $email,
            '--firstname' => 'Jane',
            '--lastname' => 'Doe',
            '--institution' => 'Kitware',
            '--password' => 'correct horse battery staple',
        ]);

        self::assertSame("Created $email\n", Artisan::output());
        $user = User::where('email', $email)->firstOrFail();
        self::assertSame([
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'institution' => 'Kitware',
            'admin' => false,
        ], $user->only(['firstname', 'lastname', 'institution', 'admin']));
        self::assertTrue(Hash::check('correct horse battery staple', $user->password));
    }

    /**
     * @return array<string,array{string}>
     */
    public static function requiredNewUserOptionCases(): array
    {
        return [
            'firstname' => ['firstname'],
            'lastname' => ['lastname'],
            'institution' => ['institution'],
            'password' => ['password'],
        ];
    }

    #[DataProvider('requiredNewUserOptionCases')]
    public function testCreateRequiresOption(string $option): void
    {
        $email = Str::uuid()->toString() . '@example.com';
        $options = [
            '--email' => $email,
            '--firstname' => 'Jane',
            '--lastname' => 'Doe',
            '--institution' => 'Kitware',
            '--password' => 'correct horse battery staple',
        ];
        unset($options["--$option"]);

        Artisan::call('user:save', $options);

        self::assertSame("You must specify the --$option option when creating a new user\n", Artisan::output());
        self::assertDatabaseMissing(User::class, ['email' => $email]);
    }

    public function testUpdatesOnlyGivenOptions(): void
    {
        $user = User::factory()->create([
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'institution' => 'Kitware',
        ]);
        $password = $user->password;

        Artisan::call('user:save', ['--email' => $user->email, '--institution' => 'CTest']);

        self::assertSame("Updated {$user->email}\n", Artisan::output());
        $user->refresh();
        self::assertSame([
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'institution' => 'CTest',
            'admin' => false,
        ], $user->only(['firstname', 'lastname', 'institution', 'admin']));
        self::assertSame($password, $user->password);
    }

    public function testGrantsAdmin(): void
    {
        $user = User::factory()->create();

        Artisan::call('user:save', ['--email' => $user->email, '--admin' => '1']);

        self::assertSame("Updated {$user->email}\n", Artisan::output());
        self::assertTrue($user->refresh()->admin);
    }
}
