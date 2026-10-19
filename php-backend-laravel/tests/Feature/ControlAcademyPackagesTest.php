<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\AcademyBatches\AcademyBatchResource;
use App\Filament\Resources\AcademyBatches\Pages\EditAcademyBatch;
use App\Filament\Resources\AcademyBatches\Pages\ListAcademyBatches;
use App\Filament\Resources\AcademyBatches\RelationManagers\FeePaymentsRelationManager;
use App\Filament\Resources\AcademyBatches\RelationManagers\StudentsRelationManager;
use App\Filament\Resources\VenuePackages\Pages\EditVenuePackage;
use App\Filament\Resources\VenuePackages\Pages\ListVenuePackages;
use App\Filament\Resources\VenuePackages\RelationManagers\PassHoldersRelationManager;
use App\Filament\Resources\VenuePackages\VenuePackageResource;
use App\Models\AcademyFeePayment;
use App\Models\AdminAction;
use App\Models\BatchEnrollment;
use App\Models\CustomerPackage;
use App\Models\PackageRedemption;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBatch;
use App\Models\VenuePackage;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * /control can see and correct every partner's academy batches and prepaid
 * passes, every correction is audited, and none of it leaks into /partner.
 * Rendered with real rows: per-row closures only run when there is a row.
 */
class ControlAcademyPackagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $owner;
    private Venue $venue;
    private VenueBatch $batch;
    private BatchEnrollment $student;
    private VenuePackage $offer;
    private CustomerPackage $pass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@haraan.test', 'password' => Hash::make('secret123'),
            'role' => 'ADMIN', 'status' => 'active',
        ]);
        $this->owner = User::create([
            'name' => 'Ravi Kumar', 'email' => 'owner@haraan.test', 'password' => Hash::make('secret123'),
            'role' => 'PARTNER', 'partner_type' => 'venue', 'status' => 'active',
        ]);
        $this->venue = Venue::create([
            'name' => 'Kadapa Arena', 'location' => 'Kadapa', 'price' => 600,
            'is_active' => true, 'is_bookable' => true, 'partner_id' => $this->owner->id,
        ]);

        $this->batch = VenueBatch::create([
            'partner_id' => $this->owner->id, 'venue_id' => $this->venue->id, 'name' => 'Juniors Badminton',
            'coach_name' => 'Ravi', 'sport' => 'Badminton', 'days' => ['Mon', 'Wed'],
            'start_time' => '06:00', 'end_time' => '07:30', 'monthly_fee' => 1500, 'capacity' => 8, 'is_active' => true,
        ]);
        $this->student = BatchEnrollment::create([
            'venue_batch_id' => $this->batch->id, 'partner_id' => $this->owner->id,
            'student_name' => 'Kabir Naidu', 'student_phone' => '9000000103',
            'amount_paid' => 3000, 'paid_until' => today()->subDays(6)->toDateString(), 'is_active' => true,
        ]);
        AcademyFeePayment::create([
            'batch_enrollment_id' => $this->student->id, 'partner_id' => $this->owner->id, 'venue_id' => $this->venue->id,
            'amount' => 3000, 'method' => 'cash', 'months' => 2, 'paid_until' => today()->subDays(6)->toDateString(),
            'collected_by' => $this->owner->id, 'collected_at' => now()->subDays(60),
        ]);

        $this->offer = VenuePackage::create([
            'partner_id' => $this->owner->id, 'venue_id' => null, 'name' => '10 Hour Pass',
            'price' => 4000, 'sessions' => 10, 'validity_days' => 90, 'is_active' => true,
        ]);
        $this->pass = CustomerPackage::create([
            'venue_package_id' => $this->offer->id, 'partner_id' => $this->owner->id, 'sold_at_venue_id' => $this->venue->id,
            'customer_phone' => '9000000301', 'customer_name' => 'Dev Malhotra', 'sessions_total' => 10,
            'amount_paid' => 4000, 'payment_method' => 'upi', 'expires_at' => now()->addDays(30),
        ]);
        PackageRedemption::create(['customer_package_id' => $this->pass->id, 'booking_id' => null, 'redeemed_at_venue_id' => $this->venue->id]);
        PackageRedemption::create(['customer_package_id' => $this->pass->id, 'booking_id' => null, 'redeemed_at_venue_id' => $this->venue->id]);
    }

    private function asAdmin(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('control'));
        $this->actingAs($this->admin);
    }

    public function test_admin_sees_every_batch_with_its_fee_health(): void
    {
        $this->asAdmin();

        Livewire::test(ListAcademyBatches::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$this->batch])
            ->assertSee('Juniors Badminton')
            ->assertSee('1 behind');

        Livewire::test(StudentsRelationManager::class, ['ownerRecord' => $this->batch, 'pageClass' => EditAcademyBatch::class])
            ->assertOk()
            ->assertCanSeeTableRecords([$this->student])
            ->assertSee('Overdue since');

        Livewire::test(FeePaymentsRelationManager::class, ['ownerRecord' => $this->batch, 'pageClass' => EditAcademyBatch::class])
            ->assertOk()
            ->assertSee('Kabir Naidu')
            ->assertSee('3,000');
    }

    public function test_admin_edits_and_pauses_a_batch_and_both_are_audited(): void
    {
        $this->asAdmin();

        Livewire::test(EditAcademyBatch::class, ['record' => $this->batch->getRouteKey()])
            ->assertOk()
            ->fillForm(['monthly_fee' => 1800, 'days' => ['Mon', 'Wed', 'Fri']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->batch->refresh();
        $this->assertSame(1800, $this->batch->monthly_fee);
        $this->assertSame(['Mon', 'Wed', 'Fri'], $this->batch->days);
        $this->assertTrue(AdminAction::query()->where('action', 'academy_batch.updated')->exists());

        Livewire::test(EditAcademyBatch::class, ['record' => $this->batch->getRouteKey()])
            ->callAction('togglePause');

        $this->assertFalse($this->batch->refresh()->is_active);
        $this->assertTrue(AdminAction::query()->where('action', 'academy_batch.paused')->exists());
    }

    public function test_a_batch_cannot_be_moved_to_another_partners_venue(): void
    {
        $this->asAdmin();
        $other = Venue::create(['name' => 'Rival Turf', 'location' => 'Kondapur', 'is_active' => true, 'is_bookable' => true]);

        Livewire::test(EditAcademyBatch::class, ['record' => $this->batch->getRouteKey()])
            ->fillForm(['venue_id' => $other->id])
            ->call('save');

        $this->assertSame($this->venue->id, $this->batch->refresh()->venue_id);
    }

    public function test_removing_a_student_needs_a_reason_and_keeps_their_fees(): void
    {
        $this->asAdmin();

        Livewire::test(StudentsRelationManager::class, ['ownerRecord' => $this->batch, 'pageClass' => EditAcademyBatch::class])
            ->callTableAction('toggleSeat', $this->student, data: ['reason' => 'Moved to Hyderabad']);

        $this->assertFalse($this->student->refresh()->is_active);
        $this->assertSame(1, AcademyFeePayment::query()->where('batch_enrollment_id', $this->student->id)->count());
        $log = AdminAction::query()->where('action', 'academy_student.removed')->first();
        $this->assertNotNull($log);
        $this->assertSame('Moved to Hyderabad', $log->meta['reason']);
    }

    public function test_admin_sees_offers_and_holders_and_can_pause_sales(): void
    {
        $this->asAdmin();

        Livewire::test(ListVenuePackages::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$this->offer])
            ->assertSee('10 Hour Pass');

        Livewire::test(PassHoldersRelationManager::class, ['ownerRecord' => $this->offer, 'pageClass' => EditVenuePackage::class])
            ->assertOk()
            ->assertCanSeeTableRecords([$this->pass])
            ->assertSee('8 of 10');

        Livewire::test(EditVenuePackage::class, ['record' => $this->offer->getRouteKey()])
            ->assertOk()
            ->callAction('togglePause');

        $this->assertFalse($this->offer->refresh()->is_active);
        $this->assertTrue(AdminAction::query()->where('action', 'package.paused')->exists());
    }

    public function test_undoing_a_session_removes_one_redemption_row_and_is_audited(): void
    {
        $this->asAdmin();

        Livewire::test(PassHoldersRelationManager::class, ['ownerRecord' => $this->offer, 'pageClass' => EditVenuePackage::class])
            ->callTableAction('undoSession', $this->pass, data: ['reason' => 'Desk spent it twice']);

        $this->assertSame(1, $this->pass->redemptions()->count());
        $this->assertSame(9, $this->pass->refresh()->remaining());
        $this->assertTrue(AdminAction::query()->where('action', 'package_pass.session_undone')->exists());
    }

    public function test_changing_a_pass_expiry_is_audited(): void
    {
        $this->asAdmin();
        $target = now()->addDays(60)->toDateString();

        Livewire::test(PassHoldersRelationManager::class, ['ownerRecord' => $this->offer, 'pageClass' => EditVenuePackage::class])
            ->callTableAction('extend', $this->pass, data: ['expires_at' => $target, 'reason' => 'Venue shut for rain']);

        $this->assertSame($target, $this->pass->refresh()->expires_at->timezone('Asia/Kolkata')->toDateString());
        $this->assertTrue(AdminAction::query()->where('action', 'package_pass.expiry_changed')->exists());
    }

    public function test_none_of_this_is_reachable_from_the_partner_console(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('partner'));
        $this->actingAs($this->owner);

        $this->assertFalse(AcademyBatchResource::canAccess());
        $this->assertFalse(VenuePackageResource::canAccess());
    }
}
