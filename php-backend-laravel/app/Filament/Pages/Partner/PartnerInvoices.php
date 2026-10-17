<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use App\Http\Controllers\PartnerInvoicesController;
use App\Models\PartnerTaxProfile;
use App\Services\PartnerDocuments;
use App\Support\GstStates;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;

/**
 * Invoices — every document for the partner's settlements (statement, commission tax
 * invoice, TDS / TCS certificate, bookings CSV), the quarterly TDS summaries with Form 16A,
 * and the tax details they're billed to. Same data as the app's Invoices screen
 * ({@see PartnerDocuments}); documents open in a new tab ready to save as PDF.
 */
class PartnerInvoices extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $title = 'Invoices';

    protected static ?string $navigationLabel = 'Invoices';

    protected static ?string $slug = 'invoices';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.partner.invoices';

    public string $filter = 'all';

    public bool $editingTax = false;

    public ?string $legal_name = null;

    public ?string $gstin = null;

    public ?string $pan = null;

    public ?string $state_code = null;

    public ?string $address = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'partner'
            && $user !== null
            && $user->hasPartnerPermission('reports')
            && PartnerDocuments::enabled();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $p = $this->profile();
        $this->legal_name = $p?->legal_name;
        $this->gstin = $p?->gstin;
        $this->pan = $p?->pan;
        $this->state_code = $p?->state_code;
        $this->address = $p?->address;
    }

    private function partnerId(): int
    {
        return (int) auth()->user()->effectivePartnerId();
    }

    public function profile(): ?PartnerTaxProfile
    {
        return PartnerTaxProfile::query()->where('partner_id', $this->partnerId())->first();
    }

    public function canEditTax(): bool
    {
        return auth()->user()->parent_partner_id === null;
    }

    /** @return array{settlements: list<array<string, mixed>>, quarters: list<array<string, mixed>>} */
    public function documents(): array
    {
        return app(PartnerDocuments::class)->index($this->partnerId());
    }

    /** @return array<string, string> */
    public function states(): array
    {
        return GstStates::OPTIONS;
    }

    public function setFilter(string $f): void
    {
        $this->filter = in_array($f, ['all', 'invoice', 'tds', 'settlement'], true) ? $f : 'all';
    }

    public function saveTax(): void
    {
        abort_unless($this->canEditTax(), 403);

        try {
            PartnerInvoicesController::writeTaxProfile($this->partnerId(), [
                'legal_name' => $this->legal_name,
                'gstin' => $this->gstin,
                'pan' => $this->pan,
                'state_code' => $this->state_code,
                'address' => $this->address,
            ], auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->resetErrorBag();
        $this->mount();
        $this->editingTax = false;
        Notification::make()->title('Tax details saved')->success()->send();
    }

    public static function money(float $v): string
    {
        $paise = (int) round($v * 100) % 100;

        return \App\Services\PartnerSettlement::inr(floor($v)) . ($paise !== 0 ? '.' . str_pad((string) $paise, 2, '0', STR_PAD_LEFT) : '');
    }
}
