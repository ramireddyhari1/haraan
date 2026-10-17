<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partner invoices (2026-10-17). A settlement now records every deduction it made —
 * commission, GST on that commission, TDS (194-O) and GST TCS — and the details it was
 * issued with, so the statement, tax invoice and TDS certificate a partner downloads
 * never change when the rates or Haraan's details change later.
 *
 * `gross_amount` is what left the partner's available balance; `amount` stays the net
 * that reaches their bank, so every existing reader of `amount` keeps its meaning.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('payout_batches', function (Blueprint $table): void {
            $table->decimal('gross_amount', 12, 2)->nullable()->after('partner_id');
            $table->decimal('commission_gst_percent', 5, 2)->default(0)->after('commission_amount');
            $table->decimal('commission_gst_amount', 10, 2)->default(0)->after('commission_gst_percent');
            $table->decimal('tds_percent', 5, 2)->default(0)->after('commission_gst_amount');
            $table->decimal('tds_amount', 10, 2)->default(0)->after('tds_percent');
            $table->decimal('tcs_percent', 5, 2)->default(0)->after('tds_amount');
            $table->decimal('tcs_amount', 10, 2)->default(0)->after('tcs_percent');
            $table->string('invoice_number', 40)->nullable()->unique()->after('reference');
            $table->json('tax_snapshot')->nullable()->after('invoice_number');
        });

        // Settlements made before this held no tax: gross is what was paid plus any commission.
        DB::table('payout_batches')->update(['gross_amount' => DB::raw('amount + commission_amount')]);

        Schema::create('partner_tax_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('legal_name', 160)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('pan', 10)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('state_code', 2)->nullable();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('partner_tds_certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained('users')->cascadeOnDelete();
            $table->string('financial_year', 7); // "2026-27"
            $table->unsignedTinyInteger('quarter');  // 1 = Apr–Jun … 4 = Jan–Mar
            $table->string('certificate_no', 40)->nullable();
            $table->string('file_path');
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['partner_id', 'financial_year', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_tds_certificates');
        Schema::dropIfExists('partner_tax_profiles');
        Schema::table('payout_batches', function (Blueprint $table): void {
            $table->dropUnique(['invoice_number']);
            $table->dropColumn([
                'gross_amount', 'commission_gst_percent', 'commission_gst_amount',
                'tds_percent', 'tds_amount', 'tcs_percent', 'tcs_amount', 'invoice_number', 'tax_snapshot',
            ]);
        });
    }
};
