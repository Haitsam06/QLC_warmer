<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('parents')->nullOnDelete();
            $table->string('parent_name')->nullable();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->string('nama');
            $table->integer('usia')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->string('tanggal_lahir')->nullable();
            $table->string('enrollment_status')->default('pending');
            $table->string('bukti_pembayaran')->nullable();
            $table->timestamps();

            $table->index('enrollment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};