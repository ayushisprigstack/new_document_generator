<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadCustomerUnit extends Model
{
    use HasFactory;

    protected $table="leads_customers_unit";
    protected $fillable = ['interested_lead_id', 'unit_id', 'booking_status','leads_customers_id'];

  

    public function unit()
    {
        return $this->belongsTo(UnitDetail::class, 'unit_id'); // Ensure unit_id is used here
    }

    public function paymentTransaction()
    {
        return $this->hasOne(PaymentTransaction::class, 'unit_id', 'unit_id');
    }
    // public function allocatedLead()
    // {
    //     return $this->belongsTo(Lead::class, 'allocated_lead_id');
    // }

    // public function allocatedCustomer()
    // {
    //     return $this->belongsTo(Customer::class, 'allocated_customer_id');
    // }
    public function leadCustomer()
    {
        return $this->belongsTo(LeadCustomer::class, 'leads_customers_id');
    }

    public function interestedLeads()
    {
        return leadCustomer::whereIn('id', explode(',', $this->interested_lead_id))->get();
    }
    public function leadCustomerUnitData()
    {
        return $this->hasMany(LeadCustomerUnitData::class, 'leads_customers_unit_id');
    }
}
