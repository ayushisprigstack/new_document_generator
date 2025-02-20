<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomFieldsStructure extends Model
{
    protected $table = 'custom_fields_structures';
    protected $fillable = [
        'custom_field_id',
        'value',
    ];

    public function customField()
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }
}