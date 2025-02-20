<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanUsageLog extends Model
{
    use HasFactory;
   
    protected $fillable = [ 'user_id', 'plan_id', 'property_count', 'unit_count','lead_count', 'email_count', 'whatsapp_count', 'cheque_scan_count', 'status'];


    public function planDetails()
    {
            return $this->belongsTo(PlanDetail::class, 'plan_id');
    }
}