<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RemoveUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:remove {--email=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove a CDash user';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $email = $this->option('email');
        if (is_null($email)) {
            $this->error("You must specify the --email option");
            return;
        }

        $user = \App\Models\User::where('email', $email)->first();
        if (!$user) {
            $this->error("User $email does not exist");
            return;
        }
        $user->delete();
        $this->info("Deleted user $email");
    }
}
