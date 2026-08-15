<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'electronic_signature')) {
                $table->string('electronic_signature')->nullable()->after('name');
            }
        });

        Schema::table('meeting_user', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_user', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('meeting_user', 'signature_text')) {
                $table->string('signature_text')->nullable()->after('confirmed_at');
            }
        });

        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'minutes_missing_signatures_reason')) {
                $table->string('minutes_missing_signatures_reason')->nullable()->after('approved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'electronic_signature')) {
                $table->dropColumn('electronic_signature');
            }
        });
        Schema::table('meeting_user', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('meeting_user', 'confirmed_at')) {
                $cols[] = 'confirmed_at';
            }
            if (Schema::hasColumn('meeting_user', 'signature_text')) {
                $cols[] = 'signature_text';
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
        Schema::table('meetings', function (Blueprint $table) {
            if (Schema::hasColumn('meetings', 'minutes_missing_signatures_reason')) {
                $table->dropColumn('minutes_missing_signatures_reason');
            }
        });
    }
};
