<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workforce_audit_ledger', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->timestamp('occurred_at', 6)->useCurrent();
            $table->unsignedBigInteger('tenant_id')->default(1);
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 64)->default('SYSTEM');
            $table->foreignId('impersonated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entity_type', 128);
            $table->unsignedBigInteger('entity_id');
            $table->string('event_name', 128);
            $table->json('payload_before')->nullable();
            $table->json('payload_after')->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_fingerprint', 128)->nullable();
            $table->decimal('geo_latitude', 10, 8)->nullable();
            $table->decimal('geo_longitude', 11, 8)->nullable();
            $table->json('telemetry_metadata')->nullable();
            $table->char('previous_event_hash', 64)->default(str_repeat('0', 64));
            $table->char('signature_hash', 64);
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['actor_id', 'occurred_at']);
            $table->index(['venue_id', 'occurred_at']);
            $table->index('event_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workforce_audit_ledger');
    }
};
