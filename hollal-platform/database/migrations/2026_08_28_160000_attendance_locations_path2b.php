<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Path-2ب: fixed site barcode (settings) + multi geofence locations for punch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters')->default(150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreignId('attendance_location_id')
                ->nullable()
                ->after('field_proof_path')
                ->constrained('attendance_locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_location_id');
        });
        Schema::dropIfExists('attendance_locations');
    }
};
