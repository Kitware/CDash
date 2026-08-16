<?php

namespace Tests\Feature;

use App\Mail\TestEmail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailTestCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function testEmailTestCommand(): void
    {
        Mail::fake();

        $email = 'test@example.com';

        Artisan::call('email:test', ['--email' => $email]);

        Mail::assertQueued(TestEmail::class, fn ($mail) => $mail->hasTo($email));

        $output = trim(Artisan::output());
        $this->assertStringContainsString("Test email sent to $email", $output);
    }

    public function testEmailTestCommandRequiresEmail(): void
    {
        Mail::fake();
        Artisan::call('email:test');
        $output = trim(Artisan::output());
        $this->assertStringContainsString('You must specify the --email option', $output);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function testEmailTestCommandInvalidEmail(): void
    {
        Mail::fake();
        Artisan::call('email:test', ['--email' => 'not-an-email']);
        $output = trim(Artisan::output());
        $this->assertStringContainsString('Invalid email address: not-an-email', $output);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }
}
