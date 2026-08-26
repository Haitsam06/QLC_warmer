<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgressReport extends Model
{
    protected $table = 'progress_reports';

    protected $fillable = [
        'student_id',
        'teacher_id',
        'date',
        'attendance',
        'report_type',
        'kualitas',
        'hafalan_target',
        'hafalan_achievement',
        'teacher_notes',
        'created_by',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
}
