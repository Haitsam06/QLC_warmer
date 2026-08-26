<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $table = 'partners';

    protected $fillable = [
        'user_id',
        'institution_name',
        'contact_person',
        'phone',
        'mou_file_url',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reports()
    {
        return $this->hasMany(MitraReport::class);
    }
}
