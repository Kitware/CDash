<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

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
        'firstname', 'lastname', 'email', 'password', 'institution'
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
        return $this->hasMany('App\Password', 'userid')->orderBy('date');
    }

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
