<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WingDetail extends Model
{
    use HasFactory;
    protected $fillable = [
        'property_id',
        'name',
        'total_floors'
    ];
    public function userProperty()
    {
        return $this->belongsTo(UserProperty::class,'property_id','id');
    }
    public function floorDetails()
    {
        return $this->hasMany(FloorDetail::class,'wing_id','id')->with('unitDetails');
    }
    public function unitDetails()
    {
        return $this->hasMany(UnitDetail::class,'wing_id','id');
    }
}
