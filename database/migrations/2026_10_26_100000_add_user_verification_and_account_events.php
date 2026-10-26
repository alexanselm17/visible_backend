<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_verified')) {
            Schema::table('users', fn (Blueprint $table) => $table->boolean('is_verified')->default(false));
            if (Schema::hasColumn('users', 'phone_verified_at')) {
                DB::table('users')->whereNotNull('phone_verified_at')->update(['is_verified' => true]);
            }
        }
        if (! Schema::hasColumn('users', 'email_verified_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('email_verified_at')->nullable());
        }
        if (! Schema::hasTable('user_account_events')) {
            Schema::create('user_account_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('performed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 80);
                $table->text('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        // Preserve verification state and account audit history on compatibility rollback.
    }
};
