<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'total_wings',
        'amenities_id',
        'status_id',
        'min_price',
        'max_price',
        'property_plan'

    ];
    public function userProperty()
    {
        return $this->belongsTo(UserProperty::class,'user_property_id','id');
    }
}
