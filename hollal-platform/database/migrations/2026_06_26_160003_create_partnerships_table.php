<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnerships', function (Blueprint $table) {
            $table->id();
            $table->string('entity_name');
            $table->string('contact_person')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('magic_link_token')->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('type_quantity')->nullable();
            $table->text('halal_commitments')->nullable();
            $table->text('partner_commitments')->nullable();
            $table->decimal('pricing_amount', 10, 2)->nullable();
            $table->string('contract_pdf')->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['pending_form', 'negotiation', 'active', 'completed'])->default('pending_form');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partnerships');
    }
};
