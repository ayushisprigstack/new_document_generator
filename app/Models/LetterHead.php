<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LetterHead extends Model
{
    use HasFactory;
    protected $table = "letter_heads";
    protected $fillable = ['file_path','user_id','property_id'];
    
}
