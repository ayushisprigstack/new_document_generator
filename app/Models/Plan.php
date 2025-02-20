<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;

    protected $fillable = ['name'];

    // Relations
    public function modules()
    {
        return $this->belongsToMany(Module::class, 'module_plan_pricing', 'plan_id', 'module_id')
            ->withPivot('monthly_price', 'yearly_price')
            ->withTimestamps();
    }

    public function features()
    {
        return $this->hasManyThrough(Feature::class, ModulePlanPricing::class, 'plan_id', 'module_id', 'id', 'module_id');
    }

    public function pricingPlanFeatures()
    {
        return $this->hasMany(PricingPlanFeature::class, 'plan_id');
    }
    public function modulePricingPlans()
    {
        return $this->hasMany(ModulePlanPricing::class, 'plan_id');
    }
}
