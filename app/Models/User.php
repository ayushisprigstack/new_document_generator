<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use SoftDeletes;
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'parent_user_id',
        'is_active',
        'name',
        'email',
        'contact_no',
        'client_id',
        'client_secret_key'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function userProfile()
    {
        return $this->hasOne(UserProfile::class,'user_id','id');
    }

    public function properties()
    {
        return $this->hasMany(UserProperty::class,'user_id','id');
    }


    public function companyDetail()
    {
        return $this->hasOne(CompanyDetail::class,'user_id','id');
    }
    public function planDetails()
    {
        return $this->hasOne(PlanUsageLog::class,'user_id','id');
    }
    public function parentUser()
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function subUsers()
    {
        return $this->hasMany(User::class, 'parent_user_id');
    }

    public function menuAccesses()
    {
        return $this->hasMany(MenuAccess::class, 'user_id');
    }
}
