<?php

namespace App\Models;

use App\Traits\HasPostgresIdAlias;
use Illuminate\Database\Eloquent\Model;

class Agenda extends Model
{
    use HasPostgresIdAlias;

    protected $table = 'agenda';

    protected $fillable = [
        'user_id',
        'title',
        'event_date',
        'description',
        'location',
        'registration_link',
        'visibility',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForVisibility($query, string $visibility)
    {
        if ($visibility === 'umum') {
            return $query->whereIn('visibility', ['umum', 'keduanya']);
        }
        if ($visibility === 'mitra') {
            return $query->whereIn('visibility', ['mitra', 'keduanya']);
        }
        return $query;
    }
}
