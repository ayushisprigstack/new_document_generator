<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomFieldsTypeValue  extends Model
{
    use HasFactory;
    protected $fillable = ['type'];


    public function customFields()
    {
        return $this->hasMany(CustomField::class, 'custom_fields_type_values_id');
    }
}
