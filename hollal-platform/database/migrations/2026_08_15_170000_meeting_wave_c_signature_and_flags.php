<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2 wave C — profile-stored signature image (frozen per confirmation via the
 * meeting_user pivot copy), plus idempotent "minutes ready" notification flag
 * and the manually-uploaded signed-PDF archive link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'signature_image_path')) {
                $table->string('signature_image_path')->nullable()->after('electronic_signature');
            }
        });

        Schema::table('meeting_user', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_user', 'signature_image_path')) {
                $table->string('signature_image_path')->nullable()->after('signature_text');
            }
        });

        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'minutes_notified_at')) {
                $table->timestamp('minutes_notified_at')->nullable()->after('minutes_missing_signatures_reason');
            }
            if (! Schema::hasColumn('meetings', 'signed_document_id')) {
                $table->unsignedBigInteger('signed_document_id')->nullable()->after('archived_document_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'signature_image_path')) {
                $table->dropColumn('signature_image_path');
            }
        });

        Schema::table('meeting_user', function (Blueprint $table) {
            if (Schema::hasColumn('meeting_user', 'signature_image_path')) {
                $table->dropColumn('signature_image_path');
            }
        });

        Schema::table('meetings', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('meetings', 'minutes_notified_at')) {
                $cols[] = 'minutes_notified_at';
            }
            if (Schema::hasColumn('meetings', 'signed_document_id')) {
                $cols[] = 'signed_document_id';
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
