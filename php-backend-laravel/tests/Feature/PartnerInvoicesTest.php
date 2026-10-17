<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PartnerTaxProfile;
use App\Models\PartnerTdsCertificate;
use App\Models\PayoutBatch;
use App\Models\User;
use App\Services\PartnerSettlement;
use App\Services\SettlementTax;
use App\Support\JwtService;
use App\Support\PlatformRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Partner invoices: the deductions a settlement makes, the documents built from it, and
 * who may open them.
 */
final class PartnerInvoicesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private function as(User $u)
    {
        $secret = config('app.jwt_secret') ?: env('JWT_SECRET', 'change_me');

        return $this->withHeader('Authorization', 'Bearer '.JwtService::issueForUser($u, $secret));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active', 'name' => 'Vadi Sports']);
        PlatformRules::save([
            'invoicing.legal_name' => 'Haraan Technologies Pvt Ltd',
            'invoicing.gstin' => '37ABCDE1234F1Z5',
            'invoicing.pan' => 'ABCDE1234F',
            'invoicing.state_code' => '37',
            'invoicing.commission_gst_percent' => '18',
            'invoicing.tds_percent' => '0.1',
            'invoicing.tds_no_pan_percent' => '5',
            'invoicing.tcs_percent' => '0.5',
        ]);
    }

    private function settle(float $gross = 10000, float $commission = 10): PayoutBatch
    {
        $tax = app(SettlementTax::class);
        $r = $tax->defaultRates($this->owner->id);

        return PayoutBatch::create($tax->compute($this->owner->id, $gross, $commission, $r['commission_gst_percent'], $r['tds_percent'], $r['tcs_percent']) + [
            'partner_id' => $this->owner->id,
            'status' => 'paid',
            'reference' => 'UTR123',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-07',
            'processed_at' => now(),
        ]);
    }

    public function test_partner_with_pan_and_gstin_gets_normal_rates_and_every_deduction(): void
    {
        PartnerTaxProfile::create(['partner_id' => $this->owner->id, 'gstin' => '37PQRSX5678K1Z2', 'pan' => 'PQRSX5678K', 'state_code' => '37']);
        $b = $this->settle();

        $this->assertSame(1000.0, $b->commission_amount);
        $this->assertSame(180.0, $b->commission_gst_amount);
        $this->assertSame(10.0, $b->tds_amount);
        $this->assertSame(50.0, $b->tcs_amount);
        $this->assertSame(8760.0, $b->amount);
        $this->assertMatchesRegularExpression('#^HRN/\d{2}-\d{2}/00001$#', $b->invoice_number);
        $this->assertSame('37PQRSX5678K1Z2', $b->tax_snapshot['partner']['gstin']);
    }

    public function test_no_pan_means_higher_tds_and_no_gstin_means_no_tcs(): void
    {
        $b = $this->settle();

        $this->assertSame(500.0, $b->tds_amount);   // 5% without PAN
        $this->assertSame(0.0, $b->tcs_amount);     // TCS only for GST-registered partners
        $this->assertSame(10000.0 - 1000 - 180 - 500, $b->amount);
    }

    public function test_no_haraan_gstin_means_no_gst_whatever_the_form_says(): void
    {
        PlatformRules::save(['invoicing.gstin' => '']);
        $r = app(SettlementTax::class)->compute($this->owner->id, 10000, 10, 18, 0, 0);

        $this->assertSame(0.0, $r['commission_gst_amount']);
        $this->assertSame(9000.0, $r['amount']);
    }

    public function test_the_whole_gross_leaves_the_available_balance(): void
    {
        $b = $this->settle(4000, 10);

        $this->assertSame(4000.0, app(PartnerSettlement::class)->settled($this->owner->id));
        $this->assertLessThan(4000, $b->amount);
    }

    public function test_invoice_numbers_run_in_sequence(): void
    {
        $a = $this->settle();
        $b = $this->settle();
        $none = $this->settle(1000, 0);

        $this->assertStringEndsWith('/00001', $a->invoice_number);
        $this->assertStringEndsWith('/00002', $b->invoice_number);
        $this->assertNull($none->invoice_number); // nothing kept, nothing to invoice
    }

    public function test_index_lists_documents_and_quarters(): void
    {
        $b = $this->settle();

        $res = $this->as($this->owner)->getJson('/api/partner/invoices')->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('settlements.0.id', $b->id)
            ->assertJsonPath('settlements.0.docs', ['settlement', 'invoice', 'tds', 'csv'])
            ->assertJsonPath('settlements.0.net', 8320)
            ->assertJsonPath('can_edit_tax', true);

        $this->assertSame(500.0, (float) $res->json('quarters.0.tds'));
    }

    public function test_documents_render_with_real_figures(): void
    {
        PartnerTaxProfile::create(['partner_id' => $this->owner->id, 'gstin' => '29PQRSX5678K1Z2', 'pan' => 'PQRSX5678K', 'legal_name' => 'Vadi Sports LLP']);
        $b = $this->settle();

        $this->as($this->owner)->get("/api/partner/invoices/{$b->id}/invoice")->assertOk()
            ->assertHeader('X-Document-Name', 'Haraan-Tax-Invoice-' . str_replace('/', '-', $b->invoice_number) . '.pdf')
            ->assertSee('Tax invoice')
            ->assertSee('IGST')               // Karnataka partner, Andhra Haraan
            ->assertSee('₹1,180.00', false)
            ->assertSee('Vadi Sports LLP');

        $this->as($this->owner)->get("/api/partner/invoices/{$b->id}/settlement")->assertOk()
            ->assertSee('₹8,760.00', false)
            ->assertSee('UTR123');

        $this->as($this->owner)->get("/api/partner/invoices/{$b->id}/tds")->assertOk()
            ->assertSee('194-O')
            ->assertSee('₹10.00', false);

        $this->as($this->owner)->get("/api/partner/invoices/{$b->id}/csv")->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        [$fy] = [SettlementTax::financialYear(now())];
        $this->as($this->owner)->get('/api/partner/invoices/quarter/' . $fy . '/' . SettlementTax::quarter(now()))->assertOk()
            ->assertSee('Quarterly TDS');
    }

    public function test_another_partner_cannot_open_my_documents(): void
    {
        $b = $this->settle();
        $other = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);

        $this->as($other)->get("/api/partner/invoices/{$b->id}/settlement")->assertNotFound();
    }

    public function test_desk_staff_without_reports_are_kept_out_and_cannot_change_tax_details(): void
    {
        $desk = User::factory()->create(['role' => 'partner', 'parent_partner_id' => $this->owner->id, 'status' => 'active', 'staff_permissions' => ['bookings']]);
        $this->as($desk)->getJson('/api/partner/invoices')->assertForbidden();

        $reports = User::factory()->create(['role' => 'partner', 'parent_partner_id' => $this->owner->id, 'status' => 'active', 'staff_permissions' => ['reports']]);
        $this->as($reports)->getJson('/api/partner/invoices')->assertOk()->assertJsonPath('can_edit_tax', false);
        $this->as($reports)->postJson('/api/partner/tax-profile', ['pan' => 'ABCDE1234F'])->assertForbidden();
    }

    public function test_tax_profile_validates_and_refreshes_open_settlements(): void
    {
        $open = PayoutBatch::create(['partner_id' => $this->owner->id, 'gross_amount' => 1000, 'amount' => 1000, 'status' => 'processing']);

        $this->as($this->owner)->postJson('/api/partner/tax-profile', ['gstin' => '37PQRSX5678K1Z2', 'pan' => 'ZZZZZ9999Z'])
            ->assertStatus(422)->assertJsonValidationErrors('pan');

        $this->as($this->owner)->postJson('/api/partner/tax-profile', ['gstin' => '37pqrsx5678k1z2', 'legal_name' => 'Vadi Sports LLP'])
            ->assertOk()
            ->assertJsonPath('tax_profile.pan', 'PQRSX5678K')    // taken from the GSTIN
            ->assertJsonPath('tax_profile.state_code', '37');

        $this->assertSame('Vadi Sports LLP', $open->fresh()->tax_snapshot['partner']['legal_name']);
    }

    public function test_form16a_is_served_only_to_its_partner(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('tds-certificates/x.pdf', '%PDF-1.4 test');
        $cert = PartnerTdsCertificate::create(['partner_id' => $this->owner->id, 'financial_year' => '2026-27', 'quarter' => 2, 'file_path' => 'tds-certificates/x.pdf']);

        $this->as($this->owner)->get("/api/partner/invoices/form16a/{$cert->id}")->assertOk();

        $other = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);
        $this->as($other)->get("/api/partner/invoices/form16a/{$cert->id}")->assertNotFound();
    }

    public function test_switching_invoices_off_hides_everything(): void
    {
        $b = $this->settle();
        PlatformRules::save(['invoicing.enabled' => '0']);

        $this->as($this->owner)->getJson('/api/partner/invoices')->assertOk()->assertJsonPath('enabled', false)->assertJsonPath('settlements', []);
        $this->as($this->owner)->get("/api/partner/invoices/{$b->id}/settlement")->assertNotFound();
    }

    public function test_financial_year_and_quarter(): void
    {
        $this->assertSame('2026-27', SettlementTax::financialYear(\Illuminate\Support\Carbon::parse('2026-04-01')));
        $this->assertSame('2025-26', SettlementTax::financialYear(\Illuminate\Support\Carbon::parse('2026-03-31')));
        $this->assertSame(1, SettlementTax::quarter(\Illuminate\Support\Carbon::parse('2026-06-30')));
        $this->assertSame(3, SettlementTax::quarter(\Illuminate\Support\Carbon::parse('2026-10-17')));
        $this->assertSame(4, SettlementTax::quarter(\Illuminate\Support\Carbon::parse('2027-02-01')));
    }

    public function test_payouts_shows_what_reached_the_bank_not_the_gross(): void
    {
        $b = $this->settle(10000, 10); // no PAN: net 8,320

        $this->as($this->owner)->getJson('/api/partner/payouts')->assertOk()
            ->assertJsonPath('balance.settled', 8320)
            ->assertJsonPath('balance.withheld', 1680)
            ->assertJsonPath('batches.0.gross', 10000);
    }

    public function test_web_console_page_and_document_links(): void
    {
        $b = $this->settle();

        $this->actingAs($this->owner)->get('/partner/invoices')->assertOk()
            ->assertSee('Billed to')
            ->assertSee(route('partner.docs.batch', ['id' => $b->id, 'doc' => 'invoice']), false);

        $this->actingAs($this->owner)->get("/partner-docs/settlement/{$b->id}/invoice")->assertOk()->assertSee('Tax invoice');

        $other = User::factory()->create(['role' => 'partner', 'partner_type' => 'venue', 'status' => 'active']);
        $this->actingAs($other)->get("/partner-docs/settlement/{$b->id}/invoice")->assertNotFound();
    }
}
