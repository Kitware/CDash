<?php

namespace App\Console\Commands;

use App\Mail\TestEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class EmailTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test {--email=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $email = $this->option('email');

        $validator = Validator::make([
            'email' => $email,
        ], [
            'email' => 'required|email',
        ], [
            'email.required' => 'You must specify the --email option',
            'email.email' => "Invalid email address: $email",
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return;
        }

        Mail::to($email)->send(new TestEmail());

        $this->info("Test email sent to $email");
    }
}
