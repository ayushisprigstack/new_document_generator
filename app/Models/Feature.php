<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feature extends Model
{
    use SoftDeletes;

    protected $fillable = ['module_id', 'description', 'action_name','Basic','Standard','Premium','Enterprise'];

    // Relations
    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id');
    }
}
