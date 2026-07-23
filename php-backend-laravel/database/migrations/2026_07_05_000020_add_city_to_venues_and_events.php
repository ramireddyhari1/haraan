<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Venue;
use App\Support\CityResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('venues', 'city')) {
            Schema::table('venues', function (Blueprint $table): void {
                $table->string('city')->nullable()->index()->after('location');
            });
        }

        if (!Schema::hasColumn('events', 'city')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->string('city')->nullable()->index()->after('location');
            });
        }

        // Backfill from the existing free-text location fields.
        Venue::query()->select('id', 'location', 'address', 'city')->chunkById(200, function ($venues): void {
            foreach ($venues as $venue) {
                $city = CityResolver::detect($venue->location, $venue->address);
                if ($city !== null && $venue->city !== $city) {
                    $venue->newQuery()->whereKey($venue->id)->update(['city' => $city]);
                }
            }
        });

        Event::query()->select('id', 'venue', 'location', 'city')->chunkById(200, function ($events): void {
            foreach ($events as $event) {
                $city = CityResolver::detect($event->venue, $event->location);
                if ($city !== null && $event->city !== $city) {
                    $event->newQuery()->whereKey($event->id)->update(['city' => $city]);
                }
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('venues', 'city')) {
            Schema::table('venues', function (Blueprint $table): void {
                $table->dropColumn('city');
            });
        }
        if (Schema::hasColumn('events', 'city')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->dropColumn('city');
            });
        }
    }
};
