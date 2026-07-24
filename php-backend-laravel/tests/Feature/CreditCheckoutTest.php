<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\Partner\PartnerPlanPage;
use App\Models\CreditPack;
use App\Models\PartnerCredit;
use App\Models\PartnerPlan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The partner-facing top-up flow. The thing worth testing hardest is that the
 * client can't decide what it bought: the browser sends a payment id, and the
 * server decides what that payment was for.
 */
class CreditCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'rzp_test_key';

    private const SECRET = 'rzp_test_secret';

    private User $partner;

    private CreditPack $small;

    private CreditPack $large;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::create([
            'name' => 'Checkout Partner', 'email' => 'checkout-partner@example.test',
            'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active',
            'partner_type' => 'event',
        ]);

        PartnerPlan::create([
            'code' => 'starter', 'name' => 'Starter', 'price_inr' => 0,
            'included_conversations' => 0, 'features' => [], 'is_default' => true,
        ]);

        $this->small = CreditPack::create([
            'code' => 'small', 'name' => '100 conversations',
            'conversations' => 100, 'price_inr' => 99, 'is_active' => true,
        ]);

        $this->large = CreditPack::create([
            'code' => 'large', 'name' => '5000 conversations',
            'conversations' => 5000, 'price_inr' => 3999, 'is_active' => true,
        ]);

        config([
            'services.razorpay.key' => self::KEY,
            'services.razorpay.secret' => self::SECRET,
        ]);

        $this->actingAs($this->partner);
        Filament::setCurrentPanel(Filament::getPanel('partner'));
    }

    private function signature(string $orderId, string $paymentId): string
    {
        return hash_hmac('sha256', $orderId . '|' . $paymentId, self::SECRET);
    }

    public function test_buying_a_pack_creates_an_order_and_opens_checkout(): void
    {
        Http::fake(['api.razorpay.com/*' => Http::response(['id' => 'order_ABC'], 200)]);

        Livewire::test(PartnerPlanPage::class)
            ->call('buyPack', $this->small->id)
            ->assertDispatched('open-razorpay');

        // What the order was for is remembered server-side, not in the page.
        $this->assertSame(
            ['partner_id' => $this->partner->id, 'pack_id' => $this->small->id],
            Cache::get('rzp_credit_order:order_ABC'),
        );
    }

    public function test_a_verified_payment_grants_the_pack(): void
    {
        Http::fake(['api.razorpay.com/*' => Http::response(['id' => 'order_ABC'], 200)]);

        Livewire::test(PartnerPlanPage::class)
            ->call('buyPack', $this->small->id)
            ->call('confirmPack', 'order_ABC', 'pay_ABC', $this->signature('order_ABC', 'pay_ABC'));

        $this->assertSame(100, (int) PartnerCredit::where('partner_id', $this->partner->id)->sum('conversations'));
    }

    public function test_an_invalid_signature_grants_nothing(): void
    {
        Http::fake(['api.razorpay.com/*' => Http::response(['id' => 'order_ABC'], 200)]);

        Livewire::test(PartnerPlanPage::class)
            ->call('buyPack', $this->small->id)
            ->call('confirmPack', 'order_ABC', 'pay_ABC', 'forged-signature');

        $this->assertSame(0, PartnerCredit::count());
    }

    public function test_the_client_cannot_choose_which_pack_it_paid_for(): void
    {
        // Pay for the cheap pack, then confirm hoping to be given the big one.
        Http::fake(['api.razorpay.com/*' => Http::response(['id' => 'order_CHEAP'], 200)]);

        $page = Livewire::test(PartnerPlanPage::class)->call('buyPack', $this->small->id);

        // Nothing the browser sends names a pack — the server looks it up.
        $page->call('confirmPack', 'order_CHEAP', 'pay_1', $this->signature('order_CHEAP', 'pay_1'));

        $this->assertSame(100, (int) PartnerCredit::sum('conversations'));
        $this->assertNotSame($this->large->conversations, (int) PartnerCredit::sum('conversations'));
    }

    public function test_a_payment_for_an_unknown_order_grants_nothing(): void
    {
        // Correctly signed, but we have no record of creating this order.
        Livewire::test(PartnerPlanPage::class)
            ->call('confirmPack', 'order_GHOST', 'pay_X', $this->signature('order_GHOST', 'pay_X'));

        $this->assertSame(0, PartnerCredit::count());
    }

    public function test_another_partners_order_cannot_be_claimed(): void
    {
        $other = User::create([
            'name' => 'Other', 'email' => 'other-partner@example.test',
            'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active',
            'partner_type' => 'event',
        ]);

        Cache::put('rzp_credit_order:order_THEIRS', ['partner_id' => $other->id, 'pack_id' => $this->large->id], now()->addMinutes(30));

        Livewire::test(PartnerPlanPage::class)
            ->call('confirmPack', 'order_THEIRS', 'pay_Y', $this->signature('order_THEIRS', 'pay_Y'));

        $this->assertSame(0, PartnerCredit::count());
    }

    public function test_confirming_twice_grants_once(): void
    {
        Http::fake(['api.razorpay.com/*' => Http::response(['id' => 'order_ABC'], 200)]);
        $signature = $this->signature('order_ABC', 'pay_ABC');

        $page = Livewire::test(PartnerPlanPage::class)->call('buyPack', $this->small->id);
        $page->call('confirmPack', 'order_ABC', 'pay_ABC', $signature);
        $page->call('confirmPack', 'order_ABC', 'pay_ABC', $signature);

        $this->assertSame(1, PartnerCredit::count());
    }
}
