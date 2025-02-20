<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPlanDetail extends Model
{
    use HasFactory;
    protected $fillable = [ 'user_id', 'plan_id', 'payment', 'status'];

    public function planDetails()
    {
            return $this->belongsTo(PlanDetail::class, 'plan_id');
    }

    public function userDetails()
    {
            return $this->belongsTo(User::class, 'user_id');
    }
}