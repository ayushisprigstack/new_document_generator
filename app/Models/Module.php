<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use SoftDeletes;

    protected $fillable = ['name'];

    // Relations
    public function plans()
    {
        return $this->belongsToMany(Plan::class, 'module_plan_pricing', 'module_id', 'plan_id')
            ->withPivot('monthly_price', 'yearly_price')
            ->withTimestamps();
    }

    public function features()
    {
        return $this->hasMany(Feature::class, 'module_id');
    }

    public function pricingPlanFeatures()
    {
        return $this->hasMany(PricingPlanFeature::class, 'module_id');
    }

    public function modulePricingPlans()
    {
        return $this->hasMany(ModulePlanPricing::class, 'module_id');
    }
}
