<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomFieldsValue extends Model
{
    protected $table = 'custom_fields_values';
    protected $fillable = [
        'property_id',
        'leads_customers_id', 
        'custom_fields_type_values_id',
        'custom_field_id', 
        'custom_fields_structure_id', 
        'text_value', 
        'small_text_value', 
        'date_value', 
        'date_time_value', 
        'int_value'
    ];
    // Relationships
    public function leadCustomer()
    {
        return $this->belongsTo(LeadCustomer::class, 'leads_customers_id');
    }

    public function customField()
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }

    public function typeValue()
    {
        return $this->belongsTo(CustomFieldsTypeValue::class, 'custom_fields_type_values_id');
    }

    public function customFieldStructure()
    {
        return $this->belongsTo(CustomFieldsStructure::class, 'custom_fields_structure_id');
    }
}
