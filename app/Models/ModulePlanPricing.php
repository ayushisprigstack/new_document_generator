<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModulePlanPricing extends Model
{
    use SoftDeletes;

    protected $table = 'module_plan_pricing';
    protected $fillable = ['module_id', 'plan_id', 'monthly_price', 'yearly_price'];

    // Relations
    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }
}
