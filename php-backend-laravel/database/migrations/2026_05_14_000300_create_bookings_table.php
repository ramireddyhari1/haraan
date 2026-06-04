<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->integer('quantity');
            $table->decimal('total_amount', 10, 2);
            $table->string('status')->default('CONFIRMED');
            $table->json('seat_numbers')->nullable();
            $table->string('coupon_code')->nullable();
            $table->decimal('discount', 10, 2)->default(0);
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
