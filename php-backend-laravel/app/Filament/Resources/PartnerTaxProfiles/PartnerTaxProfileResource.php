<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerTaxProfiles;

use App\Filament\Resources\PartnerTaxProfiles\Pages\ListPartnerTaxProfiles;
use App\Http\Controllers\PartnerInvoicesController;
use App\Models\AdminAction;
use App\Models\PartnerTaxProfile;
use App\Models\PartnerTdsCertificate;
use App\Models\User;
use App\Services\SettlementTax;
use App\Support\BusinessClock;
use App\Support\GstStates;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Partner tax & TDS: who each partner's invoices are billed to (legal name, GSTIN, PAN,
 * address), and the official Form 16A certificates finance uploads each quarter.
 *
 * The PAN decides the TDS rate a new settlement starts with and the GSTIN decides whether
 * GST TCS applies, so this sits next to Partner settlements in Finance. Partners can fill
 * it in themselves from the app or /partner → Invoices; both sides write through
 * PartnerInvoicesController::writeTaxProfile().
 */
class PartnerTaxProfileResource extends Resource
{
    protected static ?string $model = PartnerTaxProfile::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $cluster = \App\Filament\Clusters\Finance\FinanceCluster::class;

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'partner tax details';

    protected static ?string $pluralModelLabel = 'partner tax & TDS';

    protected static ?string $navigationLabel = 'Partner tax & TDS';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('finance') ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // "Add for a partner" goes through the shared validator.
    }

    /** @return array<int, mixed> */
    public static function taxFields(): array
    {
        return [
            TextInput::make('legal_name')->label('Legal name on invoices')->maxLength(160),
            TextInput::make('gstin')->label('GSTIN')->maxLength(15)->placeholder('37ABCDE1234F1Z5')
                ->helperText('Leave empty if the partner isn’t GST-registered. No GSTIN: no GST TCS.'),
            TextInput::make('pan')->label('PAN')->maxLength(10)->placeholder('ABCDE1234F')
                ->helperText('Without a PAN, TDS is withheld at the higher no-PAN rate.'),
            Select::make('state_code')->label('State')->options(GstStates::OPTIONS)->searchable()->native(false)
                ->helperText('Taken from the GSTIN when there is one.'),
            Textarea::make('address')->label('Billing address')->rows(2)->maxLength(300)->columnSpanFull(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($q) => $q->with('partner'))
            ->columns([
                TextColumn::make('partner.name')->label('Partner')->weight('bold')->searchable()->sortable()
                    ->description(fn (PartnerTaxProfile $r): ?string => $r->legal_name && $r->legal_name !== $r->partner?->name ? $r->legal_name : null),
                TextColumn::make('gstin')->label('GSTIN')->placeholder('Not registered')->copyable()->searchable(),
                TextColumn::make('pan')->label('PAN')->placeholder('No PAN — higher TDS')->color(fn (?string $state): ?string => $state ? null : 'warning')->copyable()->searchable(),
                TextColumn::make('state_code')->label('State')->formatStateUsing(fn (?string $state): string => GstStates::name($state) ?? '—'),
                TextColumn::make('certificates')->label('Form 16A on file')
                    ->state(fn (PartnerTaxProfile $r): HtmlString => static::certificateLinks((int) $r->partner_id)),
                TextColumn::make('updated_at')->label('Last changed')->dateTime('d M Y H:i')->sortable()
                    ->description(fn (PartnerTaxProfile $r): ?string => $r->updated_by_id === $r->partner_id ? 'by the partner' : ($r->updated_by_id ? 'by ' . (User::find($r->updated_by_id)?->name ?? 'Haraan') : null)),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-m-pencil-square')
                    ->modalHeading(fn (PartnerTaxProfile $r): string => 'Tax details · ' . ($r->partner?->name ?? 'partner'))
                    ->modalDescription('Settlements not yet paid pick up the new details. Paid ones keep what they were issued with.')
                    ->fillForm(fn (PartnerTaxProfile $r): array => $r->only(['legal_name', 'gstin', 'pan', 'state_code', 'address']))
                    ->schema(static::taxFields())
                    ->action(function (PartnerTaxProfile $r, array $data): void {
                        PartnerInvoicesController::writeTaxProfile((int) $r->partner_id, $data, auth()->user());
                        AdminAction::log('partner_tax_profile.updated', ['partner_id' => $r->partner_id]);
                        Notification::make()->title('Tax details saved')->success()->send();
                    }),
                Action::make('removeCertificate')
                    ->label('Remove a Form 16A')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->visible(fn (PartnerTaxProfile $r): bool => PartnerTdsCertificate::query()->where('partner_id', $r->partner_id)->exists())
                    ->schema(fn (PartnerTaxProfile $r): array => [
                        Select::make('certificate_id')->label('Certificate')->required()->native(false)
                            ->options(PartnerTdsCertificate::query()->where('partner_id', $r->partner_id)->orderByDesc('financial_year')->orderByDesc('quarter')->get()
                                ->mapWithKeys(fn (PartnerTdsCertificate $c): array => [$c->id => SettlementTax::quarterLabel($c->financial_year, $c->quarter) . ' (FY ' . $c->financial_year . ')'])->all()),
                    ])
                    ->requiresConfirmation()
                    ->modalDescription('The partner can no longer download it.')
                    ->action(function (PartnerTaxProfile $r, array $data): void {
                        $cert = PartnerTdsCertificate::query()->where('partner_id', $r->partner_id)->findOrFail((int) $data['certificate_id']);
                        Storage::disk('local')->delete($cert->file_path);
                        $cert->delete();
                        AdminAction::log('tds_certificate.removed', ['partner_id' => $r->partner_id, 'certificate_id' => $cert->id]);
                        Notification::make()->title('Form 16A removed')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No partner tax details yet')
            ->emptyStateDescription('Partners add these in the app or /partner → Invoices, or use “Add for a partner” above.');
    }

    /** "Q1 26-27 · Q2 26-27" — each opens the stored PDF. */
    private static function certificateLinks(int $partnerId): HtmlString
    {
        $certs = PartnerTdsCertificate::query()->where('partner_id', $partnerId)->orderByDesc('financial_year')->orderByDesc('quarter')->limit(8)->get();
        if ($certs->isEmpty()) {
            return new HtmlString('<span style="color:#94a3b8">None yet</span>');
        }

        return new HtmlString($certs->map(fn (PartnerTdsCertificate $c): string => '<a href="' . e(route('partner.docs.form16a', $c->id)) . '" target="_blank" rel="noopener" style="color:#2563eb;font-weight:600;margin-right:8px">Q' . $c->quarter . ' ' . e(substr($c->financial_year, 2)) . '</a>')->implode(''));
    }

    /** @return array<int|string, string> partner owners, by name */
    private static function partnerOptions(bool $withoutProfile = false): array
    {
        $q = User::query()->whereNull('parent_partner_id')
            ->where(fn ($w) => $w->whereRaw('UPPER(role) = ?', ['PARTNER'])->orWhereHas('roles', fn ($r) => $r->where('name', 'PARTNER')));
        if ($withoutProfile) {
            $q->whereNotIn('id', PartnerTaxProfile::query()->pluck('partner_id'));
        }

        return $q->orderBy('name')->limit(500)->pluck('name', 'id')->all();
    }

    public static function addAction(): Action
    {
        return Action::make('addForPartner')
            ->label('Add for a partner')
            ->icon('heroicon-m-plus')
            ->modalHeading('Add partner tax details')
            ->schema([
                Select::make('partner_id')->label('Partner')->required()->searchable()->options(fn (): array => static::partnerOptions(true)),
                ...static::taxFields(),
            ])
            ->action(function (array $data): void {
                PartnerInvoicesController::writeTaxProfile((int) $data['partner_id'], $data, auth()->user());
                AdminAction::log('partner_tax_profile.updated', ['partner_id' => (int) $data['partner_id']]);
                Notification::make()->title('Tax details saved')->success()->send();
            });
    }

    /** Upload the official Form 16A from TRACES for a partner and quarter. */
    public static function uploadAction(): Action
    {
        $now = BusinessClock::now();
        $fy = SettlementTax::financialYear($now);
        $years = collect([0, 1, 2])->mapWithKeys(function (int $back) use ($now): array {
            $y = SettlementTax::financialYear($now->copy()->subYears($back));

            return [$y => 'FY ' . $y];
        })->all();

        return Action::make('uploadForm16a')
            ->label('Upload Form 16A')
            ->icon('heroicon-m-arrow-up-tray')
            ->color('gray')
            ->modalHeading('Upload a Form 16A')
            ->modalDescription('The partner sees it in their Invoices list straight away. Uploading again for the same quarter replaces it.')
            ->schema([
                Select::make('partner_id')->label('Partner')->required()->searchable()->options(fn (): array => static::partnerOptions()),
                Select::make('financial_year')->label('Financial year')->options($years)->default($fy)->required()->native(false),
                Select::make('quarter')->label('Quarter')->required()->native(false)
                    ->options([1 => 'Q1 · Apr–Jun', 2 => 'Q2 · Jul–Sep', 3 => 'Q3 · Oct–Dec', 4 => 'Q4 · Jan–Mar'])
                    ->default(SettlementTax::quarter($now) === 1 ? 4 : SettlementTax::quarter($now) - 1),
                TextInput::make('certificate_no')->label('Certificate number')->maxLength(40),
                FileUpload::make('file_path')->label('Form 16A (PDF)')->required()
                    ->disk('local')->directory('tds-certificates')->visibility('private')
                    ->acceptedFileTypes(['application/pdf'])->maxSize(5120),
            ])
            ->action(function (array $data): void {
                $old = PartnerTdsCertificate::query()->where([
                    'partner_id' => (int) $data['partner_id'], 'financial_year' => $data['financial_year'], 'quarter' => (int) $data['quarter'],
                ])->first();
                if ($old !== null && $old->file_path !== $data['file_path']) {
                    Storage::disk('local')->delete($old->file_path);
                }

                $cert = PartnerTdsCertificate::query()->updateOrCreate(
                    ['partner_id' => (int) $data['partner_id'], 'financial_year' => $data['financial_year'], 'quarter' => (int) $data['quarter']],
                    ['certificate_no' => $data['certificate_no'] ?? null, 'file_path' => $data['file_path'], 'uploaded_by_id' => auth()->id()],
                );
                // So the partner shows up in this list even before anyone filled in their details.
                PartnerTaxProfile::query()->firstOrCreate(['partner_id' => $cert->partner_id], ['updated_by_id' => auth()->id()]);
                AdminAction::log('tds_certificate.uploaded', ['partner_id' => $cert->partner_id, 'fy' => $cert->financial_year, 'quarter' => $cert->quarter]);
                Notification::make()->title('Form 16A uploaded')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartnerTaxProfiles::route('/'),
        ];
    }
}
