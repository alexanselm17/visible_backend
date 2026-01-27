<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('advert_images', function (Blueprint $table) {
            // Drop foreign keys first if they exist
            if (Schema::hasColumn('advert_images', 'reviewed_by')) {
                try {
                    $table->dropForeign(['reviewed_by']);
                } catch (\Throwable $e) {
                }
            }

            // Drop workflow columns
            $columnsToDrop = [
                'status',
                'reviewed_by',
                'reviewed_at',
                'rejection_reason',
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('advert_images', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('advert_images', function (Blueprint $table) {

            // Restore columns if rollback is needed
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])
                ->default('PENDING')
                ->after('campaign_id');

            $table->uuid('reviewed_by')->nullable()->after('status');
            $table->dateTime('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('rejection_reason')->nullable()->after('reviewed_at');

            // Restore FK
            $table->foreign('reviewed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};
