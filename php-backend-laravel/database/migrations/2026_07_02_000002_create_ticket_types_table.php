<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            // null capacity = unlimited within the event's overall slots.
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('sold')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'sort']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            // Nullable + SET NULL so deleting a tier never erases revenue history.
            $table->foreignId('ticket_type_id')
                ->nullable()
                ->after('event_id')
                ->constrained('ticket_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ticket_type_id');
        });

        Schema::dropIfExists('ticket_types');
    }
};
