<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organization_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // STATE, DISTRICT, AREA, VENUE, DEPARTMENT
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('organization_units')->nullOnDelete();
            $table->index(['type', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_units');
    }
};
