<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadCustomerUnitData extends Model
{
    use HasFactory;

    protected $table="leads_customers_unit_data";
    protected $fillable = [
        'leads_customers_unit_id', 'leads_customers_id', 'budget'
    ];

    // Define the relationship to LeadUnit
    public function leadCustomerUnit()
    {
        return $this->belongsTo(LeadCustomerUnit::class, 'leads_customers_unit_id');
    }

    // Define the relationship to Lead
    public function leadCustomer()
    {
        return $this->belongsTo(LeadCustomer::class, 'leads_customers_id');
    }
}
