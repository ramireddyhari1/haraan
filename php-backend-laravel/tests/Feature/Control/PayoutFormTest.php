<?php

declare(strict_types=1);

namespace Tests\Feature\Control;

use App\Filament\Resources\Payouts\Pages\CreatePayout;
use App\Filament\Resources\Payouts\Pages\EditPayout;
use App\Filament\Resources\Payouts\Pages\ListPayouts;
use App\Models\AdminAction;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Payout;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * /control/finance/payouts/create was the stock scaffold: a raw booking-id
 * dropdown, free-text status, ₹0 amount and no record of how money went out.
 */
class PayoutFormTest extends TestCase
{
    use RefreshDatabase;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret123', 'role' => 'ADMIN', 'status' => 'active']);
        $partner = User::create(['name' => 'Sunset Turf Co', 'email' => 'p@example.test', 'password' => 'secret123', 'role' => 'PARTNER']);
        $customer = User::create(['name' => 'Ravi Kumar', 'email' => 'ravi@example.test', 'password' => 'secret123']);

        $event = new Event();
        $event->forceFill([
            'partner_id' => $partner->id, 'title' => 'Night Cricket Cup', 'category' => 'Sports',
            'location' => 'Stadium', 'venue' => 'Main Arena', 'date' => now()->addDays(2), 'time' => '18:00',
            'price' => 500, 'total_slots' => 100, 'available_slots' => 100, 'status' => 'published',
        ])->save();

        $this->booking = Booking::create([
            'user_id' => $customer->id, 'event_id' => $event->id, 'booking_type' => 'event',
            'quantity' => 2, 'total_amount' => 1000.00, 'host_deduction' => 80.00, 'status' => 'CONFIRMED',
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('control'));
    }

    public function test_create_page_renders(): void
    {
        Livewire::test(CreatePayout::class)->assertSuccessful()->assertSee('Pick a booking');
    }

    public function test_picking_a_booking_fills_partner_share_and_shows_summary(): void
    {
        Livewire::test(CreatePayout::class)
            ->fillForm(['booking_id' => $this->booking->id])
            ->assertFormSet(['amount' => 920.0])
            ->assertSee('Sunset Turf Co')
            ->assertSee('Partner share');
    }

    public function test_processed_payout_saves_transfer_details_and_audits(): void
    {
        Livewire::test(CreatePayout::class)
            ->fillForm([
                'booking_id' => $this->booking->id,
                'amount' => 920,
                'status' => 'processed',
                'processed_at' => now()->toDateTimeString(),
                'method' => 'upi',
                'reference' => ' 432109876543 ',
                'note' => 'Settled early on request',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $p = Payout::firstOrFail();
        $this->assertSame('processed', $p->status);
        $this->assertSame('upi', $p->method);
        $this->assertSame('432109876543', $p->reference);
        $this->assertNotNull($p->processed_at);
        $this->assertTrue(AdminAction::where('action', 'payout.created')->exists());
    }

    public function test_processed_bank_payout_needs_a_utr(): void
    {
        Livewire::test(CreatePayout::class)
            ->fillForm(['booking_id' => $this->booking->id, 'amount' => 920, 'status' => 'processed', 'processed_at' => now()->toDateTimeString(), 'method' => 'bank'])
            ->call('create')
            ->assertHasFormErrors(['reference' => 'required']);
    }

    public function test_amount_cannot_exceed_what_the_customer_paid(): void
    {
        Livewire::test(CreatePayout::class)
            ->fillForm(['booking_id' => $this->booking->id, 'amount' => 5000])
            ->call('create')
            ->assertHasFormErrors(['amount']);
    }

    public function test_a_booking_cannot_be_paid_twice_but_a_failed_one_can_be_retried(): void
    {
        $failed = Payout::create(['booking_id' => $this->booking->id, 'amount' => 920, 'status' => 'failed']);

        Livewire::test(CreatePayout::class)
            ->fillForm(['booking_id' => $this->booking->id, 'amount' => 920])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreatePayout::class)
            ->fillForm(['booking_id' => $this->booking->id, 'amount' => 920])
            ->call('create')
            ->assertHasFormErrors(['booking_id' => 'unique']);

        $this->assertSame(2, Payout::count());
        $this->assertNotNull($failed->fresh());
    }

    public function test_legacy_uppercase_pending_loads_and_switching_back_clears_paid_date(): void
    {
        $p = Payout::create(['booking_id' => $this->booking->id, 'amount' => 920, 'status' => 'PENDING']);

        Livewire::test(EditPayout::class, ['record' => $p->getRouteKey()])
            ->assertFormSet(['status' => 'pending'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('pending', $p->fresh()->status);
        $this->assertNull($p->fresh()->processed_at);
    }

    public function test_list_process_action_records_method_and_reference(): void
    {
        $p = Payout::create(['booking_id' => $this->booking->id, 'amount' => 920, 'status' => 'pending']);

        Livewire::test(ListPayouts::class)
            ->assertSee('Night Cricket Cup')
            ->callTableAction('process', $p, ['method' => 'bank', 'reference' => 'UTR998877'])
            ->assertHasNoTableActionErrors();

        $p->refresh();
        $this->assertSame('processed', $p->status);
        $this->assertSame('bank', $p->method);
        $this->assertSame('UTR998877', $p->reference);
    }
}
