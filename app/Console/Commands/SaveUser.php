<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SaveUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:save {--email=} {--firstname=} {--lastname=} {--password=} {--institution=} {--admin=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update a CDash user';

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
     * Create or update a user.
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

        // Are we creating a new user or updating an existing one?
        $user = \App\Models\User::where('email', $email)->first();
        if ($user === null) {
            $user = new User();
            $user->email = $email;
            $create_new_user = true;
            $message = "Created $email";
        } else {
            $create_new_user = false;
            $message = "Updated $email";
        }

        $options = ['firstname', 'lastname', 'institution', 'password'];
        foreach ($options as $option_name) {
            $option_value = $this->option($option_name);
            if (!is_null($option_value)) {
                if ($option_name == 'password') {
                    $option_value = password_hash($option_value, PASSWORD_DEFAULT);
                }
                $user->$option_name = $option_value;
            } elseif ($create_new_user) {
                $this->error("You must specify the --$option_name option when creating a new user");
                return;
            }
        }

        // Handle admin flag.
        if ($this->option('admin')) {
            $user->admin = (bool) $this->option('admin');
        }

        $user->save();
        $this->info($message);
    }
}
