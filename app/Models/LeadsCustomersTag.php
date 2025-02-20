<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadsCustomersTag extends Model
{
    use HasFactory;

    protected $table = 'leads_customers_tags';

    protected $fillable = ['leads_customers_id', 'tag_id'];

    /**
     * Get the lead/customer associated with this tag relation.
     */
    public function leadsCustomer()
    {
        return $this->belongsTo(LeadCustomer::class, 'leads_customers_id');
    }

    /**
     * Get the tag associated with this tag relation.
     */
    public function tag()
    {
        return $this->belongsTo(Tag::class, 'tag_id');
    }
}
