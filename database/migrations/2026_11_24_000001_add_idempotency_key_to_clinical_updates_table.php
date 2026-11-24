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
        Schema::table('clinical_updates', function (Blueprint $table): void {
            $table->string('idempotency_key', 64)->nullable()->after('corrects_update_id');
            $table->unique(['doctor_id', 'idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinical_updates', function (Blueprint $table): void {
            $table->dropUnique(['doctor_id', 'idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
