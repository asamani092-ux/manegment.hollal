<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partnerships', function (Blueprint $table) {
            if (! Schema::hasColumn('partnerships', 'portal_features')) {
                $table->json('portal_features')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partnerships', function (Blueprint $table) {
            if (Schema::hasColumn('partnerships', 'portal_features')) {
                $table->dropColumn('portal_features');
            }
        });
    }
};
