<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCapability extends Model
{
    use HasFactory;

    protected $table = 'user_capabilities';

    protected $fillable = [
        'user_id',
        'plan_id',
        'feature_id',
        'module_id',
        'plan_duration',
        'limit',
        'object_name',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function feature()
    {
        return $this->belongsTo(Feature::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
