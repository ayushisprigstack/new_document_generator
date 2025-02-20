<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanDetail extends Model
{
    use HasFactory;
 
    protected $fillable = ['name', 'property_count', 'unit_count', 'lead_count', 'email_count', 'whatsapp_count', 'cheque_scan_count', 'price', 'extra_details'];

    public function PlanusageLogs()
    {
        return $this->hasMany(PlanUsageLog::class, 'plan_id');
    }
}