<?php
namespace App\Providers;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;

/**
 * Database login provider that checks for old passwords hashed by md5.
 */
class CDashDatabaseUserProvider extends EloquentUserProvider
{
    public function __construct(HasherContract $hasher, $model)
    {
        parent::__construct($hasher, $model);
    }

    /**
     * Validate a user against the given credentials.
     * Update old md5 password hashes as necessary.
     */
    public function validateCredentials(UserContract $user, array $credentials)
    {
        // First check if standard Eloquent authentication is successful.
        if (parent::validateCredentials($user, $credentials)) {
            return true;
        }

        // Check if md5 was used to hash the user's password.
        $plain = $credentials['password'];
        if (md5($plain) == $user->password) {
            // Re-hash this password if the database has already been upgraded
            // to accommodate the increased length of the password field.
            $column = \DB::connection()->getDoctrineColumn('user', 'password');
            if ($column->getLength() > 254) {
                $user->password = password_hash($plain, PASSWORD_DEFAULT);
                $user->save();
            }
            return true;
        }
        return false;
    }
}
