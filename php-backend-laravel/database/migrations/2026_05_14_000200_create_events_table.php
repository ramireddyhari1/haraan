<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->default('');
            $table->string('category')->default('GENERAL');
            $table->string('booking_format')->default('HYBRID');
            $table->string('visibility')->default('PUBLIC');
            $table->string('access_code')->nullable();
            $table->string('location')->default('');
            $table->string('venue')->default('');
            $table->dateTime('date');
            $table->string('time')->default('');
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('total_slots')->default(0);
            $table->integer('available_slots')->default(0);
            $table->json('images')->nullable();
            $table->string('status')->default('DRAFT');
            $table->unsignedBigInteger('partner_id')->index();
            $table->integer('seat_rows')->nullable();
            $table->integer('seats_per_row')->nullable();
            $table->boolean('seat_selection')->default(true);
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
