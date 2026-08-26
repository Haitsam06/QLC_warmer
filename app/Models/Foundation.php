<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Foundation extends Model
{
    protected $table = 'foundations';
    protected $fillable = ['title', 'description'];
}