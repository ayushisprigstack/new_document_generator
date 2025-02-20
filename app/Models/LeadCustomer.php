<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadCustomer  extends Model
{
    use HasFactory;
    protected $table = "leads_customers";
    protected $fillable = [
        'property_id',
        'user_id',
        'name',
        'email',
        'contact_no',
        'source_id',
        'type',
        'status_id',
        'notes',
        'entity_type',
        'agent_name',
        'agent_contact',
        'address',
        'city',
        'state',
        'pincode',
        'reminder_date'
    ];

    public function userproperty()
    {
        return $this->belongsTo(UserProperty::class, 'property_id', 'id');
    }

    public function leadSource()
    {
        return $this->belongsTo(LeadSource::class, 'source_id', 'id');
    }

    public function leadCustomerUnits()
    {
        return $this->hasMany(LeadCustomerUnit::class, 'leads_customers_id');
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class, 'leads_customers_id');
    }

    public function getEntityTypeLabelAttribute()
    {
        return $this->entity_type === 1 ? 'Lead' : 'Customer';
    }

    public function leadStatus()
    {
        return $this->belongsTo(LeadStatus::class, 'status_id', 'id');
    }
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'leads_customers_tags', 'leads_customers_id', 'tag_id');
    }
    public function leadCustomerTags()
    {
        return $this->belongsToMany(LeadCustomer::class,  'leads_customers_id');
    }
    public function city()
    {
        return $this->belongsTo(City::class, 'city');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state');
    }

    public function customFields()
    {
        return $this->hasMany(CustomFieldsValue::class, 'leads_customers_id');
    }
}
