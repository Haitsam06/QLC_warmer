<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $table = 'students';

    protected $fillable = [
        'parent_id',
        'parent_name',
        'program_id',
        'nama',
        'usia',
        'tempat_lahir',
        'tanggal_lahir',
        'enrollment_status',
        'bukti_pembayaran',
    ];

    public function parent()
    {
        return $this->belongsTo(Parents::class, 'parent_id', 'id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function progressReports()
    {
        return $this->hasMany(ProgressReport::class);
    }
}
