<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emoji reactions on direct messages — the row that appears above a message on long press.
 *
 * One reaction per person per message (the unique index), because that is what the gesture
 * means: tapping a different emoji REPLACES yours rather than adding a second. Aggregation
 * happens at read time, so a message with forty hearts still costs forty small rows and no
 * counter that can drift out of step with them.
 *
 * The emoji is stored as the character itself rather than a name or an id: it is what the
 * sender chose, it renders anywhere, and a lookup table of "what does reaction 7 mean" is a
 * migration waiting to disagree with the client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('direct_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Four bytes covers any single emoji; some carry modifiers, so allow a little more.
            $table->string('emoji', 16);
            $table->timestamps();

            $table->unique(['direct_message_id', 'user_id']);
            $table->index('direct_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
    }
};
