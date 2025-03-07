<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadSource extends Model
{
    use HasFactory;
    protected $fillable = ['name'];

    public function leadscustomers()
    {
        return $this->hasMany(LeadCustomer::class, 'source_id', 'id');
    }
}
