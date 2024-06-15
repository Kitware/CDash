<?php

declare(strict_types=1);

namespace App\Utils;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class LdapUtils
{
    public static function syncUser(User $user): void
    {
        if ($user->ldapguid === null) {
            $ldap_user = null;
        } elseif (env('LDAP_PROVIDER', 'openldap') === 'activedirectory') {
            $ldap_user = \LdapRecord\Models\ActiveDirectory\User::findByGuid($user->ldapguid);
        } else {
            $ldap_user = \LdapRecord\Models\OpenLDAP\User::findByGuid($user->ldapguid);
        }

        $projects = Project::with('users')->get();

        foreach ($projects as $project) {
            if ($project->ldapfilter === null) {
                continue;
            }

            $matches_ldap_filter = $ldap_user !== null && $ldap_user->groups()
                ->recursive()
                ->exists(\LdapRecord\Models\Entry::find($project->ldapfilter));

            $relationship_already_exists = $project->users->contains($user);

            if ($matches_ldap_filter && !$relationship_already_exists) {
                $project->users()->attach($user->id, ['role' => Project::PROJECT_USER]);
                Log::info("Added user $user->email to project $project->name.");
            } elseif (!$matches_ldap_filter && $relationship_already_exists) {
                $project->users()->detach($user->id);
                Log::info("Removed user $user->email from project $project->name.");
            }
        }
    }
}
