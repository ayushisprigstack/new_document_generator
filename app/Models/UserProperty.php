<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProperty extends Model
{
    use HasFactory;


    protected $appends = ['state_name', 'city_name'];
    
    protected $fillable = [
        'user_id',
        'property_id',
        'name',
        'description',
        'rera_registered_no',
        'address',
        'pincode',
        // 'property_step_status',
        // 'state_id',
        // 'city_id',
        // 'area',
        // 'property_img'
    ];
    public function user()
    {
        return $this->belongsTo(User::class,'user_id','id');
    }
    public function property()
    {
        return $this->belongsTo(Property::class,'property_id','id');
    }
    public function wingDetails()
    {
        return $this->hasMany(WingDetail::class,'property_id','id');
    }

    public function propertyDetails()
    {
        return $this->hasMany(PropertyDetail::class,'user_property_id','id');
    }

    public function floorDetails()
    {
        return $this->hasMany(FloorDetail::class,'user_property_id','id');
    }
    public function unitDetails()
    {
        return $this->hasMany(UnitDetail::class,'property_id','id');
    }
    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    // Define the relationship to the City model
    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    // Accessor for state name
    public function getStateNameAttribute()
    {
        return $this->state ? $this->state->name : null;
    }

    // Accessor for city name
    public function getCityNameAttribute()
    {
        return $this->city ? $this->city->name : null;
    }

}
