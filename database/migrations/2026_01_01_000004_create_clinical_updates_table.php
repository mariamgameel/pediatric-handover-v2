<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clinical_updates', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $patientIdLength = (int) config('hospital.patient_id_max_length', 64);
            $doctorIdLength = (int) config('hospital.doctor_id_max_length', 64);

            $table->ulid('update_id')->primary();
            $table->string('patient_id', $patientIdLength);
            $table->string('doctor_id', $doctorIdLength);
            $table->text('content');
            $table->ulid('corrects_update_id')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->foreign('patient_id')
                ->references('patient_id')
                ->on('patients')
                ->restrictOnDelete();

            $table->foreign('doctor_id')
                ->references('doctor_id')
                ->on('doctors')
                ->restrictOnDelete();

            $table->foreign('corrects_update_id')
                ->references('update_id')
                ->on('clinical_updates')
                ->restrictOnDelete();

            $table->index(['patient_id', 'created_at']);
            $table->index('doctor_id');
            $table->index('corrects_update_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinical_updates');
    }
};
