<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            ALTER TABLE advert_submissions
            MODIFY status ENUM(
                'PENDING_DESIGN',
                'DESIGN_DONE',
                'PENDING_APPROVAL',
                'REJECTED',
                'PUBLISHED'
            ) NOT NULL DEFAULT 'PENDING_DESIGN'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE advert_submissions
            MODIFY status ENUM(
                'PENDING_DESIGN',
                'PENDING_APPROVAL',
                'REJECTED',
                'PUBLISHED'
            ) NOT NULL DEFAULT 'PENDING_DESIGN'
        ");
    }
};
