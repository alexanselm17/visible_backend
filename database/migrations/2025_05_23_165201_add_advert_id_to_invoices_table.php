<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('advert_id')->nullable()->after('amount');

            $table->foreign('advert_id')
                ->references('id')
                ->on('advert_images')
                ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['advert_id']);
            $table->dropColumn('advert_id');
        });
    }
};
