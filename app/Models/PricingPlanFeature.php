<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PricingPlanFeature extends Model
{
    //
    use HasFactory;

    // Define the table associated with the model
    protected $table = 'pricing_plan_features';

    // If your primary key is not 'id', specify it here
    protected $primaryKey = 'id';

    // Disable timestamps if you don't have `created_at` and `updated_at` columns in the table
    public $timestamps = true;

    // Specify the fields that can be mass-assigned
    protected $fillable = [
        'plan_id',
        'module_id',
        'description',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }
    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id');
    }
}
