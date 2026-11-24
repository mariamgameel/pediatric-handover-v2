<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_clinical_updates_before_update
            BEFORE UPDATE ON clinical_updates
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Clinical updates are immutable';
            END;
        SQL);

        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_clinical_updates_before_delete
            BEFORE DELETE ON clinical_updates
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Clinical updates are immutable';
            END;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS trg_clinical_updates_before_update;');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_clinical_updates_before_delete;');
    }
};
