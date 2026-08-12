<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round-1 DOC-2: amendment request status on meeting_amendments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_amendments', function (Blueprint $table) {
            $table->string('status')->default('معتمد')->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_amendments', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
