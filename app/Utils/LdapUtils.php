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
        $projects = Project::with('users')->get();

        foreach ($projects as $project) {
            if ($project->ldapfilter === null) {
                continue;
            }

            if ($user->ldapguid === null) {
                $matches_ldap_filter  = false;
            } elseif (env('LDAP_PROVIDER', 'openldap') === 'activedirectory') {
                $matches_ldap_filter = \LdapRecord\Models\ActiveDirectory\User::rawFilter($project->ldapfilter)->findByGuid($user->ldapguid) instanceof \LdapRecord\Models\ActiveDirectory\User;
            } else {
                $matches_ldap_filter = \LdapRecord\Models\OpenLDAP\User::rawFilter($project->ldapfilter)->findByGuid($user->ldapguid) instanceof \LdapRecord\Models\OpenLDAP\User;
            }

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
