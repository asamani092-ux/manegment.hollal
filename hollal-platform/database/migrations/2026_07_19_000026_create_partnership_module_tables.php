<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 05-B1..05-B7 — the partnerships module.
 *
 * · stage logs for the seven-stage journey (05-B2)
 * · quotes + items, versioned (05-B3)
 * · contracts + payment schedule, signed-copy confirmation (05-B4)
 * · partner links + portal activity log (05-B5)
 * · recorded payments → confirmed revenue, once (05-B6)
 * · project generation requests handed to 06B-B1 (05-B7)
 * · organization impact records aggregated from projects (05-B1)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partnerships', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('organization_id')->constrained('users')->nullOnDelete();
            $table->string('stalled_reason')->nullable()->after('stage');
            $table->string('closed_reason')->nullable()->after('stalled_reason');
            $table->foreignId('renewed_from_id')->nullable()->after('closed_reason')->constrained('partnerships')->nullOnDelete();
            $table->decimal('expected_value', 12, 2)->nullable()->after('renewed_from_id');
            $table->timestamp('stage_entered_at')->nullable()->after('expected_value');
        });

        Schema::create('partnership_stage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partnership_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('from_stage')->nullable();
            $table->unsignedTinyInteger('to_stage');
            $table->string('note')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tax_number')->nullable(); // الرقم الضريبي
            $table->string('commercial_register')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('iban')->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partnership_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('supersedes_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->string('status')->default('مسودة'); // مسودة|معتمد|مرسل|بملاحظات|مقبول|مرفوض
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 4)->default(0.15);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('entity_notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['partnership_id', 'version']);
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_type'); // حقيبة|تدريب|زيارة|استشارة|قياس
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('partnership_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partnership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('مسودة'); // مسودة|بانتظار التوقيع|موقّع|مؤكد|ملغى
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->text('hollal_commitments')->nullable();
            $table->text('partner_commitments')->nullable();
            $table->decimal('total_value', 12, 2)->default(0);
            $table->boolean('requires_first_payment')->default(true);
            $table->string('unsigned_pdf_path')->nullable();
            $table->string('signed_pdf_path')->nullable();
            $table->string('signed_pdf_hash')->nullable(); // sha-256 of the uploaded signed copy
            $table->string('signature_name')->nullable();
            $table->string('signature_device')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contract_payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partnership_contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->string('label')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('due_on');
            $table->timestamps();
        });

        Schema::create('partnership_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partnership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_payment_schedule_id')->nullable()
                ->constrained('contract_payment_schedules')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('paid_on')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('status')->default('بانتظار تأكيد المالية'); // بانتظار تأكيد المالية|مؤكدة|مرفوضة
            $table->string('recorded_via')->default('داخلي'); // داخلي|رابط الجهة
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('revenue_id')->nullable();
            $table->unsignedBigInteger('tax_invoice_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('partner_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partnership_id')->constrained()->cascadeOnDelete();
            $table->string('token', 80)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_revoked')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('partner_portal_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partnership_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('project_generation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partnership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->json('included_services');
            $table->date('launch_date');
            $table->foreignId('project_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('معلق'); // معلق|مولّد|فشل
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('failure_reason')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('organization_impact_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('beneficiaries')->default(0);
            $table->decimal('improvement_percent', 5, 2)->nullable();
            $table->decimal('satisfaction_percent', 5, 2)->nullable();
            $table->string('summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_impact_records');
        Schema::dropIfExists('project_generation_requests');
        Schema::dropIfExists('partner_portal_activities');
        Schema::dropIfExists('partner_links');
        Schema::dropIfExists('partnership_payments');
        Schema::dropIfExists('contract_payment_schedules');
        Schema::dropIfExists('partnership_contracts');
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('company_profiles');
        Schema::dropIfExists('partnership_stage_logs');

        Schema::table('partnerships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropConstrainedForeignId('renewed_from_id');
            $table->dropColumn(['stalled_reason', 'closed_reason', 'expected_value', 'stage_entered_at']);
        });
    }
};
