<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->date('date')->nullable();
            $table->string('attendance')->nullable();
            $table->string('report_type')->nullable();
            $table->string('kualitas')->nullable();
            $table->string('hafalan_target')->nullable();
            $table->string('hafalan_achievement')->nullable();
            $table->text('teacher_notes')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_reports');
    }
};