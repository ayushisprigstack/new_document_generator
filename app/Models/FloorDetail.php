<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FloorDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'wing_id',
        'floor_size',
        'pent_house_status'
    ];
    public function userProperty()
    {
        return $this->belongsTo(UserProperty::class,'user_property_id','id');
    }
    public function wingDetail()
    {
        return $this->belongsTo(WingDetail::class,'wing_id','id');
    }
    public function unitDetails()
    {
        return $this->hasMany(UnitDetail::class,'floor_id','id');
    }
}
