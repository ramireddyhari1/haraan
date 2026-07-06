<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_senders', function (Blueprint $table): void {
            $table->id();
            $table->string('label')->nullable();          // friendly name in the admin list
            $table->string('host')->default('smtp.gmail.com');
            $table->unsignedInteger('port')->default(465);
            $table->string('encryption')->default('ssl');  // ssl (465) | tls (587)
            $table->string('username');                     // the sending Gmail address
            $table->text('app_password');                   // encrypted at rest (16-char app password)
            $table->string('from_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('daily_limit')->default(450);
            $table->unsignedInteger('sent_today')->default(0);
            $table->date('sent_date')->nullable();          // day the sent_today counter belongs to
            $table->boolean('healthy')->default(true);
            $table->text('last_error')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_senders');
    }
};
