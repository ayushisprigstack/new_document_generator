<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name','property_id']; // Assuming the `tags` table has a `name` column.

    /**
     * Get all leads/customers associated with the tag.
     */
    public function leadsCustomers()
    {
        return $this->belongsToMany(LeadCustomer::class, 'leads_customers_tags', 'tag_id', 'leads_customer_id');
    }
}
