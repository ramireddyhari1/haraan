<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\Finance\Pages\FinanceOverview;
use App\Filament\Resources\PayoutBatches\Pages\CreatePayoutBatch;
use App\Models\Booking;
use App\Models\Event;
use App\Models\PartnerPayoutAccount;
use App\Models\PayoutBatch;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Finance Overview reads the books the way settlement does: gross is not "revenue",
 * payout batches count, and a partner owed money without a verified account is flagged.
 */
class FinanceOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin', 'email' => 'admin@haraan.test', 'password' => bcrypt('x'), 'role' => 'ADMIN', 'status' => 'active',
        ]));
        Filament::setCurrentPanel(Filament::getPanel('control'));
    }

    private function host(string $email): User
    {
        return User::create(['name' => 'Host ' . $email, 'email' => $email, 'password' => bcrypt('x'), 'role' => 'PARTNER', 'status' => 'active']);
    }

    private function sale(User $host, float $amount, float $platformFee = 0, float $hostDeduction = 0): void
    {
        $event = Event::forceCreate([
            'title' => 'Show', 'partner_id' => $host->id, 'date' => now()->addDays(3)->toDateString(),
            'time' => '19:00', 'price' => 0, 'total_slots' => 100, 'available_slots' => 90, 'status' => 'published',
        ]);
        Booking::forceCreate([
            'user_id' => $host->id, 'event_id' => $event->id, 'booking_type' => 'event', 'status' => 'CONFIRMED',
            'quantity' => 1, 'total_amount' => $amount, 'platform_fee' => $platformFee, 'host_deduction' => $hostDeduction,
        ]);
    }

    /** @return array<string, mixed> */
    private function panel(string $title): array
    {
        return collect((new FinanceOverview())->getPanels())->firstWhere('title', $title);
    }

    private function stat(array $panel, string $label): array
    {
        return collect($panel['stats'])->firstWhere('label', $label);
    }

    public function test_haraan_kept_is_fees_not_gross(): void
    {
        $this->signIn();
        $this->sale($this->host('a@h.test'), 1000, platformFee: 30, hostDeduction: 50);

        $month = $this->panel('This month');

        $this->assertSame('₹1,000', $this->stat($month, 'Paid bookings')['value']);
        $this->assertSame('₹80', $this->stat($month, 'Haraan kept')['value']);
    }

    public function test_owed_nets_payout_batches_and_flags_partners_without_an_account(): void
    {
        $this->signIn();
        $paid = $this->host('paid@h.test');
        $unpaid = $this->host('unpaid@h.test');
        $this->sale($paid, 1000);
        $this->sale($unpaid, 600);

        // $paid already received 400 of their 1000 in a batch, and has a verified account.
        PayoutBatch::create(['partner_id' => $paid->id, 'gross_amount' => 400, 'amount' => 400, 'status' => 'paid', 'processed_at' => now()]);
        PartnerPayoutAccount::create(['partner_id' => $paid->id, 'method' => 'upi', 'upi_vpa' => 'host@upi', 'verified_at' => now()]);

        $partners = $this->panel('Partners');

        $this->assertSame('₹1,200', $this->stat($partners, 'Owed now')['value']);
        $this->assertSame('1', $this->stat($partners, 'Can\'t be paid yet')['value']);
        $this->assertSame('₹400', $this->stat($partners, 'Paid out this month')['value']);

        $rows = $partners['list']['rows'];
        $this->assertSame('₹600', $rows[0]['trailing']);
        $this->assertStringContainsString('Ready to pay', $rows[0]['secondary']);
        $this->assertStringContainsString('partner=' . $paid->id, $rows[0]['url']);
        $this->assertStringContainsString('No payout account', $rows[1]['secondary']);
    }

    public function test_the_page_renders(): void
    {
        $this->signIn();

        Livewire::test(FinanceOverview::class)
            ->assertOk()
            ->assertSee('Owed the most')
            ->assertSee('Nobody is owed anything');
    }

    public function test_pay_link_opens_a_batch_with_the_partner_and_balance_filled(): void
    {
        $this->signIn();
        $host = $this->host('pay@h.test');
        $this->sale($host, 750);

        $this->get(CreatePayoutBatch::getUrl(['partner' => $host->id]))->assertOk();

        Livewire::withQueryParams(['partner' => $host->id])
            ->test(CreatePayoutBatch::class)
            ->assertSet('data.partner_id', (string) $host->id)
            ->assertSet('data.gross_amount', 750.0);
    }
}
