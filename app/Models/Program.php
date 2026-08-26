<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $table = 'programs';

    protected $fillable = [
        'name',
        'description',
        'target_audience',
        'image_url',
        'hero_image_url',
        'about_image_url',
        'advantages',
        'gallery'
    ];

    protected $casts = [
        'advantages' => 'array',
        'gallery' => 'array',
    ];
}