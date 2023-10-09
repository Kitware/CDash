<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * All of these methods are accessed through reflection.  Only the ones currently necessary are
 * listed to encourage future developers to move User logic to this class.
 *
 * @method bool IsAdmin()
 * @method GetEmail()
 * @method int|false GetIdFromName($name)
 *
 * @property int $id
 * @property string $firstname
 * @property string $lastname
 * @property string $email
 * @property string $password
 * @property string $institution
 *
 * @mixin Builder<User>
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    protected $user;

    /**
     * @var string
     */
    protected $table = 'user';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'firstname',
        'lastname',
        'email',
        'password',
        'institution',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public function passwords()
    {
        return $this->hasMany('App\Models\Password', 'userid')->orderBy('date');
    }

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        return trim("{$this->firstname} {$this->lastname}");
    }

    /**
     * Passthrough to call legacy User model method.
     **/
    public function __call($method, $parameters)
    {
        $class = \CDash\Model\User::class;
        $class_methods = get_class_methods($class);
        if (in_array($method, $class_methods)) {
            $user = new $class();
            $user->Id = $this->id;
            return $user->$method(...$parameters);
        }

        return parent::__call($method, $parameters);
    }
}
