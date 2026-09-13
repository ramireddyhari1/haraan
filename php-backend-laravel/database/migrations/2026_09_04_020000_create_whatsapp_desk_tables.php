<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. WhatsApp Conversations
        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('phone_number', 32); // E.164 format, e.g. +919876543210
            $table->string('customer_name', 120)->nullable();
            $table->string('status', 32)->default('active'); // active, needs_action, hold_active, converted, archived
            $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview', 255)->nullable();
            $table->string('last_message_sender', 32)->default('customer'); // customer, partner, system
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('window_expires_at')->nullable(); // 24-hr service window from last customer inbound
            $table->foreignId('active_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->timestamps();

            $table->index(['venue_id', 'status'], 'wa_conv_venue_status_idx');
            $table->index(['venue_id', 'phone_number'], 'wa_conv_venue_phone_idx');
            $table->index('last_message_at', 'wa_conv_last_msg_idx');
        });

        // 2. WhatsApp Messages
        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->string('direction', 16); // inbound, outbound
            $table->string('sender_type', 32)->default('customer'); // customer, partner, system, bot
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('message_type', 32)->default('text'); // text, image, document, audio, video, location, template, payment_link, ticket_card
            $table->text('body');
            $table->string('media_url', 500)->nullable();
            $table->string('provider_message_id', 128)->nullable(); // wamid or MSG91 id
            $table->string('delivery_status', 32)->default('pending'); // pending, sent, delivered, read, failed
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at'], 'wa_msg_conv_created_idx');
            $table->index('provider_message_id', 'wa_msg_provider_id_idx');
        });

        // 3. WhatsApp Intent Extractions
        Schema::create('whatsapp_intent_extractions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();
            $table->string('intent_type', 48)->default('booking_enquiry'); // booking_enquiry, pricing_query, availability_check, cancellation, reschedule, general_faq
            $table->string('detected_sport', 48)->nullable();
            $table->date('detected_date')->nullable();
            $table->time('detected_start_time')->nullable();
            $table->time('detected_end_time')->nullable();
            $table->unsignedInteger('detected_duration_minutes')->nullable()->default(60);
            $table->string('detected_court_name', 100)->nullable();
            $table->foreignId('resolved_court_id')->nullable()->constrained('venue_courts')->nullOnDelete();
            $table->foreignId('resolved_slot_id')->nullable()->constrained('venue_slots')->nullOnDelete();
            $table->decimal('calculated_rate', 10, 2)->nullable();
            $table->float('confidence_score')->default(0.0);
            $table->string('action_state', 32)->default('suggested'); // suggested, hold_created, converted, dismissed
            $table->timestamps();

            $table->index(['conversation_id', 'action_state'], 'wa_intent_conv_state_idx');
        });

        // 4. WhatsApp Internal Notes
        Schema::create('whatsapp_internal_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('note');
            $table->timestamps();

            $table->index(['conversation_id', 'created_at'], 'wa_notes_conv_idx');
        });

        // 5. WhatsApp Tags
        Schema::create('whatsapp_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('name', 64);
            $table->string('color_hex', 16)->default('#3B82F6');
            $table->timestamps();

            $table->unique(['venue_id', 'name'], 'wa_tags_venue_name_uniq');
        });

        // 6. WhatsApp Conversation Tag Pivot
        Schema::create('whatsapp_conversation_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('whatsapp_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['conversation_id', 'tag_id'], 'wa_conv_tags_uniq');
        });

        // 7. WhatsApp Quick Replies
        Schema::create('whatsapp_quick_replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->cascadeOnDelete(); // null = system default
            $table->string('shortcut', 48); // e.g. /pricing, /rules, /location
            $table->string('category', 48)->default('General'); // Pricing, Rules, Directions, Payment, General
            $table->string('title', 120);
            $table->text('body');
            $table->timestamps();

            $table->index(['venue_id', 'category'], 'wa_qr_venue_cat_idx');
        });

        // 8. WhatsApp Payment Links
        Schema::create('whatsapp_payment_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('razorpay_payment_link_id', 128)->nullable();
            $table->string('short_url', 255);
            $table->decimal('amount', 10, 2);
            $table->string('status', 32)->default('issued'); // issued, paid, expired, cancelled
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'status'], 'wa_pay_conv_status_idx');
            $table->index('razorpay_payment_link_id', 'wa_pay_link_id_idx');
        });

        // 9. WhatsApp Audit Logs
        Schema::create('whatsapp_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('whatsapp_conversations')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 120)->default('System');
            $table->string('action', 64); // hold_created, hold_released, payment_link_sent, booking_converted, manual_paid, note_added, tag_assigned
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['venue_id', 'created_at'], 'wa_audit_venue_created_idx');
        });

        // 10. Operations Center: Revenue Leakage & Operational Alerts
        Schema::create('venue_operations_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('alert_type', 64); // drawer_discrepancy, expired_hold_uncontacted, unutilized_peak_slot, recurring_payment_overdue, high_cancellation_rate
            $table->string('severity', 16)->default('medium'); // low, medium, high, critical
            $table->string('title', 180);
            $table->text('description');
            $table->json('metrics_payload')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['venue_id', 'is_resolved', 'severity'], 'ops_alerts_venue_idx');
        });

        // 11. Operations Center: AI Business Suggestions
        Schema::create('venue_business_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('category', 48); // pricing, occupancy, retention, leakage
            $table->string('title', 180);
            $table->text('rationale');
            $table->decimal('projected_revenue_impact', 10, 2)->default(0.00);
            $table->json('action_payload')->nullable(); // structured configuration parameters to apply
            $table->string('status', 32)->default('pending'); // pending, applied, dismissed
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['venue_id', 'status', 'category'], 'ops_sugg_venue_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_business_suggestions');
        Schema::dropIfExists('venue_operations_alerts');
        Schema::dropIfExists('whatsapp_audit_logs');
        Schema::dropIfExists('whatsapp_payment_links');
        Schema::dropIfExists('whatsapp_quick_replies');
        Schema::dropIfExists('whatsapp_conversation_tags');
        Schema::dropIfExists('whatsapp_tags');
        Schema::dropIfExists('whatsapp_internal_notes');
        Schema::dropIfExists('whatsapp_intent_extractions');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
    }
};
