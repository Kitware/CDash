<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Utils\LdapUtils;
use Illuminate\Console\Command;

class UpdateLdapProjectMembership extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ldap:sync_projects';

    /**
     * @var ?string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        foreach (User::all() as $user) {
            LdapUtils::syncUser($user);
        }
    }
}
