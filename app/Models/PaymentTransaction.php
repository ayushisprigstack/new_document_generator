<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $casts = [
        'booking_date' => 'date',
        'payment_due_date' => 'date',
    ];
    // protected $table="unit_details";
    protected $fillable = [
        'unit_id', 'property_id', 
        'booking_date', 'payment_due_date', 
        'token_amt', 'payment_type','payment_status', 'next_payable_amt', 'leads_customers_id','entity_type',
        'transaction_notes'
    ];


    public function unit()
    {
        return $this->belongsTo(UnitDetail::class);
    }

    public function property()
    {
        return $this->belongsTo(UserProperty::class);
    }
    public function leadCustomer()
    {
        return $this->belongsTo(LeadCustomer::class, 'leads_customers_id');
    }
}
