<?php

use App\Support\LegalPolicyTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_settings')) {
            Schema::create('site_settings', function (Blueprint $table) {
                $table->unsignedTinyInteger('id')->primary();
                $table->string('app_name')->default('Visible');
                $table->string('operator_name')->nullable();
                $table->string('support_email')->nullable();
                $table->string('privacy_email')->nullable();
                $table->string('support_phone', 40)->nullable();
                $table->text('postal_address')->nullable();
                $table->string('logo_path')->nullable();
                $table->string('accessibility_mode', 30)->default('unconfirmed');
                $table->text('accessibility_disclosure')->nullable();
                $table->text('retention_policy')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestamps();
            });
        }
        DB::table('site_settings')->insertOrIgnore(['id' => 1, 'app_name' => 'Visible', 'created_at' => now(), 'updated_at' => now()]);

        if (! Schema::hasTable('legal_documents')) {
            Schema::create('legal_documents', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('title');
                $table->longText('draft_content');
                $table->date('effective_on')->nullable();
                $table->string('published_title')->nullable();
                $table->longText('published_content')->nullable();
                $table->date('published_effective_on')->nullable();
                $table->unsignedInteger('version')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->uuid('published_by')->nullable();
                $table->timestamps();
            });
        }
        foreach (LegalPolicyTemplates::documents() as $slug => $document) {
            DB::table('legal_documents')->insertOrIgnore([
                'slug' => $slug, ...$document, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('legal_document_revisions')) {
            Schema::create('legal_document_revisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('legal_document_id')->constrained()->restrictOnDelete();
                $table->unsignedInteger('version');
                $table->string('title');
                $table->longText('content');
                $table->date('effective_on');
                $table->json('identity_snapshot');
                $table->uuid('published_by');
                $table->timestamp('created_at');
                $table->unique(['legal_document_id', 'version']);
            });
        }

        if (! Schema::hasTable('privacy_requests')) {
            Schema::create('privacy_requests', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('reference', 30)->unique();
                $table->string('type', 30);
                $table->string('contact');
                $table->text('message')->nullable();
                $table->string('status', 30)->default('new')->index();
                $table->text('staff_notes')->nullable();
                $table->uuid('processed_by')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Policy revisions and privacy requests are audit records. Preserve them on rollback.
    }
};
