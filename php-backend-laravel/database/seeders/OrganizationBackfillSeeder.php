<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Event;
use App\Models\LiveMatch;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1b groundwork — backfill organization_id on existing domain records and
 * build the canonical STATE > DISTRICT org tree from the geography that already
 * lives on users/live_matches. Idempotent: re-running only fills gaps.
 *
 * Tenant scoping stays OFF — this just populates the future tenant key so it can
 * be switched on later. Records with no reliable district signal stay null
 * (= platform-wide / unassigned), which is the intended default.
 */
class OrganizationBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $districtUnits = $this->buildOrgTree();

        $matches = $this->backfillFromGeo(LiveMatch::query(), $districtUnits);
        $users = $this->backfillFromGeo(User::query(), $districtUnits, mapPrimary: true);

        // Venues/events inherit their owning partner's home org when one exists.
        $events = $this->backfillFromPartner(Event::query());
        $venues = $this->backfillFromPartner(Venue::query());

        // Bookings inherit the org of the event they belong to.
        $bookings = 0;
        Booking::query()->whereNull('organization_id')->with('event:id,organization_id')
            ->chunkById(200, function ($rows) use (&$bookings) {
                foreach ($rows as $b) {
                    if ($b->event?->organization_id) {
                        $b->update(['organization_id' => $b->event->organization_id]);
                        $bookings++;
                    }
                }
            });

        $this->command?->info("Backfill: live_matches={$matches}, users={$users}, events={$events}, venues={$venues}, bookings={$bookings}.");
    }

    /**
     * Ensure a STATE>DISTRICT unit exists for every (state, district) pair found
     * on users + live_matches. Returns a lookup keyed "state|district" => unit id.
     *
     * @return array<string, int>
     */
    private function buildOrgTree(): array
    {
        $pairs = collect();
        foreach (['users', 'live_matches'] as $table) {
            $pairs = $pairs->merge(
                DB::table($table)->select('state', 'district')->distinct()->get()
            );
        }

        $lookup = [];
        foreach ($pairs as $row) {
            $state = trim((string) ($row->state ?? ''));
            $district = trim((string) ($row->district ?? ''));
            if ($state === '' || $district === '') {
                continue; // no geography → leave records unassigned
            }

            $stateUnit = OrganizationUnit::firstOrCreate(
                ['type' => 'STATE', 'name' => $state, 'parent_id' => null],
                ['active' => true],
            );
            $districtUnit = OrganizationUnit::firstOrCreate(
                ['type' => 'DISTRICT', 'name' => $district, 'parent_id' => $stateUnit->id],
                ['active' => true],
            );

            $lookup[$state.'|'.$district] = $districtUnit->id;
        }

        return $lookup;
    }

    /**
     * Set organization_id from a record's own state/district columns.
     *
     * @param  array<string, int>  $lookup
     */
    private function backfillFromGeo($query, array $lookup, bool $mapPrimary = false): int
    {
        $count = 0;
        $query->whereNull('organization_id')
            ->whereNotNull('state')->whereNotNull('district')
            ->chunkById(200, function ($rows) use (&$count, $lookup, $mapPrimary) {
                foreach ($rows as $rec) {
                    $key = trim((string) $rec->state).'|'.trim((string) $rec->district);
                    $orgId = $lookup[$key] ?? null;
                    if ($orgId === null) {
                        continue;
                    }
                    $rec->update(['organization_id' => $orgId]);
                    $count++;

                    // Mirror a user's home org into the membership pivot.
                    if ($mapPrimary && $rec instanceof User && ! $rec->organizations()->where('organization_id', $orgId)->exists()) {
                        $rec->organizations()->attach($orgId, ['is_primary' => true, 'designation' => null]);
                    }
                }
            });

        return $count;
    }

    /** Inherit organization_id from the owning partner user's home org. */
    private function backfillFromPartner($query): int
    {
        $count = 0;
        $query->whereNull('organization_id')->whereNotNull('partner_id')
            ->chunkById(200, function ($rows) use (&$count) {
                $partnerOrgs = User::query()
                    ->whereIn('id', $rows->pluck('partner_id')->unique())
                    ->whereNotNull('organization_id')
                    ->pluck('organization_id', 'id');

                foreach ($rows as $rec) {
                    $orgId = $partnerOrgs[$rec->partner_id] ?? null;
                    if ($orgId === null) {
                        continue;
                    }
                    $rec->update(['organization_id' => $orgId]);
                    $count++;
                }
            });

        return $count;
    }
}
