<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_drops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shift_session_id')->constrained('shift_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('category', 50)->default('expense'); // expense, fuel, supplies, owner_draw, bank_deposit, other
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['shift_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_drops');
    }
};
