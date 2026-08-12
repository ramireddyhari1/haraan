<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account deletion requests — the audit trail behind Play's deletion requirement.
 *
 * Google Play requires two doors to deletion for any app that lets people create
 * an account: one inside the app, and a public web URL reachable by someone who
 * has already uninstalled it. Both land here, which is why `email` is the anchor
 * and `user_id` is nullable — a request can arrive from a signed-out stranger
 * whose email may not match any row, and we still owe them an answer.
 *
 * The row is deliberately kept AFTER the account is erased. It is the only proof
 * that we honoured the request and when, which is exactly what a regulator or a
 * Play policy review asks for. It holds no personal data beyond the email that
 * was asked about.
 *
 * @see \App\Http\Controllers\Web\AccountDeletionController  the public web door
 * @see \App\Services\AccountEraser                          what "deleted" means
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_deletion_requests', function (Blueprint $table): void {
            $table->id();

            // Null when the email matches no account, or once the user row is gone.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // The identity the request was made about. Lower-cased on write so the
            // same address can't queue twice under different casing.
            $table->string('email');

            // in_app | web — which door it came through. Play asks us to support
            // both, so it is worth being able to prove both are actually used.
            $table->string('source', 8)->default('web');

            // pending | completed | rejected
            $table->string('status', 12)->default('pending');

            // Free text from the person. Never required: making someone justify
            // deleting their own account is the pattern this rule exists to stop.
            $table->text('reason')->nullable();

            // Kept for abuse triage only — the web form is public and unauthenticated,
            // so someone could otherwise queue deletions for an address they don't own.
            $table->string('ip_address', 45)->nullable();

            // Set when we emailed the confirmation link, and when they clicked it.
            // An unverified web request must never erase an account on its own.
            $table->string('verify_token', 64)->nullable()->unique();
            $table->timestamp('verified_at')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_note')->nullable();

            $table->timestamps();

            // "What is still waiting on us" — the admin queue's only real query.
            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
    }
};
