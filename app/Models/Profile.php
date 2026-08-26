<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $table = 'profiles';

    protected $fillable = [
        'name',
        'hero_title',
        'logo',
        'about_image',
        'tagline',
        'history',
        'vision',
        'mission',
        'address',
        'whatsapp',
        'email',
        'social_media',
        'established_year',
        'main_focus',
        'bank_name',
        'bank_account',
        'bank_holder',
        'bank_nominal',
    ];

    protected $casts = [
        'social_media' => 'array',
    ];
}