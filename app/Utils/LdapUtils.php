<?php

declare(strict_types=1);

namespace App\Utils;

use App\Models\Project;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;

class LdapUtils
{
    public static function syncUser(User $user): void
    {
        $ldap_provider = match (env('LDAP_PROVIDER', 'openldap')) {
            'openldap' => \LdapRecord\Models\OpenLDAP\User::class,
            'activedirectory' => \LdapRecord\Models\ActiveDirectory\User::class,
            'freeipa' => \LdapRecord\Models\FreeIPA\User::class,
            default => false, // this case should never happen
        };

        if ($ldap_provider === false) {
            throw new Exception('Invalid LDAP provider: ' . env('LDAP_PROVIDER'));
        }

        // If this user doesn't have a GUID assigned already (for example, if the user was added
        // via OAuth or SAML), try to look it up so we can sync the user's projects properly.
        if ($user->ldapguid === null) {
            $sync_attribute = (string) config('auth.providers.ldap.database.sync_attributes.email');

            $ldap_user = $ldap_provider::findBy($sync_attribute, $user->email);

            if ($ldap_user === null) {
                Log::debug('Unable to find LDAP GUID for user: ' . $user->email);
                return;
            } else {
                $user->ldapguid = $ldap_user->getConvertedGuid();
                $user->save();
            }
        }

        $projects = Project::with('users')->get();

        foreach ($projects as $project) {
            if ($project->ldapfilter === null) {
                continue;
            }

            $matches_ldap_filter = $user->ldapguid !== null && $ldap_provider::rawFilter($project->ldapfilter)->findByGuid($user->ldapguid) !== null;
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
