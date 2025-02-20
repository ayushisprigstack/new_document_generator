<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    use HasFactory;
    public function cities()
    {
        return $this->hasMany(City::class,'state_id','id');
    }
    public function userProperties()
    {
        return $this->hasMany(UserProperty::class, 'state_id');
    }
}
