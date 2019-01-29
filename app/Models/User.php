<?php

namespace App\Models;

use Cuatromedios\Kusikusi\Models\EntityModel;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Support\Facades\Hash;
use Cuatromedios\Kusikusi\Models\DataModel;
use Cuatromedios\Kusikusi\Models\Authtoken;

class User extends DataModel implements AuthenticatableContract, AuthorizableContract
{
  use Authenticatable, Authorizable;

  const PROFILE_ADMIN = 'admin';
  const PROFILE_EDITOR = 'editor';
  const PROFILE_USER = 'user';

  protected $table = 'users';

  public static $dataFields = ['username', 'name', 'password', 'email', 'profile'];

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
      'username', 'name', 'email', 'password', 'profile'
  ];

  /**
   * The attributes excluded from the model's JSON form.
   *
   * @var array
   */
  protected $hidden = [
      'password', 'relatedEntity', 'relatedContents'
  ];

  public static function authenticate($username, $password, $ip = '', $use_email = false)
  {
    if (!$use_email) {
      $user = User::where('username', $username)->first();
    } else {
      $user = User::where('email', $username)->first();
    }
    if ($user && Hash::check($password, $user->password)) {
      $apikey = base64_encode(str_random(40));
      $token = new Authtoken([
          'token' => $apikey,
          'created_ip' => $ip
      ]);
      // TODO: If the user is inactive or deleted don't allow login
      $user->authtokens()->save($token);
      $entityUser = Entity::select('id', 'model', 'active')
          ->with(['user' => function($q) { $q->with('permissions'); }])
          ->with(['relations' => function($q) {
            $q->select('id', 'model')
              ->where('relations.kind', '!=', 'ancestor'); }])
          ->find($user->id);
      return ([
          "token" => $apikey,
          "entity" => $entityUser
      ]);
    } else {
      return FALSE;
    }
  }

  /**
   * Get the authokens of the User.
   */
  public function authtokens()
  {
    return $this->hasMany('Cuatromedios\Kusikusi\Models\Authtoken', 'user_id');
  }

  /**
   * Get the permissions of the User
   */
  public function permissions()
  {
    return $this->hasMany('Cuatromedios\Kusikusi\Models\Permission', 'user_id');
  }

  /**
   * Get the activity related to the User.
   */
  public function activity()
  {
    return $this->hasMany('Cuatromedios\Kusikusi\Models\Activity', 'user_id');
  }

  public static function boot($preset = [])
  {
    parent::boot();

    self::saving(function ($user) {
      if (isset($user['password'])) {
        $user['password'] = Hash::make($user['password']);
      }
    });
  }
}
