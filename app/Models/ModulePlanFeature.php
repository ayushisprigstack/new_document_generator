<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModulePlanFeature extends Model
{
    use HasFactory;

    protected $table = 'module_plan_features';

    protected $fillable = [
        'name',
        'module_id',
        'plan_id',
        'feature_id',
        'limit',
    ];

    // Relations
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function feature()
    {
        return $this->belongsTo(Feature::class);
    }
}
