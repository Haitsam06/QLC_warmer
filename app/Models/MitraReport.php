<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MitraReport extends Model
{
    protected $table = 'mitra_reports';

    protected $fillable = [
        'partner_id',
        'title',
        'date',
        'description',
        'file_url',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'uploaded_by',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}
