<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms;

use App\Filament\Resources\Hrms\Pages\CreateEmployee;
use App\Filament\Resources\Hrms\Pages\EditEmployee;
use App\Filament\Resources\Hrms\Pages\ListEmployees;
use App\Models\Hrms\EmployeeProfile;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    protected static ?string $model = EmployeeProfile::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce & HR';

    protected static ?string $navigationLabel = 'Employees & Staff';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'control'
            && (auth()->user()?->isSuperAdmin() || auth()->user()?->hasRoleEither(['OPS', 'FINANCE']));
    }

    public const EMPLOYMENT_TYPES = [
        'full_time' => 'Full-time',
        'part_time' => 'Part-time',
        'contract' => 'Contract',
        'intern' => 'Intern',
    ];

    public const EMPLOYMENT_STATUSES = [
        'active' => 'Active',
        'probation' => 'Probation',
        'on_leave' => 'On leave',
        'resigned' => 'Resigned',
        'terminated' => 'Terminated',
    ];

    /**
     * Two-column layout: the record itself on the left, the "who/when/how much"
     * facts an admin scans first on the right. Every column on employee_profiles
     * is editable here — the old form exposed 9 of 27 and the rest could only be
     * set by hand in the database.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->schema([
                Group::make([
                    self::accountSection(),
                    self::placementSection(),
                    self::personalSection(),
                    self::emergencySection(),
                    self::bankKycSection(),
                ])->columnSpan(['lg' => 2]),

                Group::make([
                    self::employmentSection(),
                    self::payAndAttendanceSection(),
                ])->columnSpan(['lg' => 1]),
            ]);
    }

    private static function accountSection(): Section
    {
        return Section::make('Login account')
            ->description('The person signs in to the staff portal with this account\'s work email (or the employee code) and password.')
            ->columns(2)
            ->schema([
                Select::make('user_id')
                    ->label('Person')
                    ->placeholder('Search by name, email or phone')
                    ->helperText('New hire without an account? Use + to create their login.')
                    ->searchable()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->validationMessages(['unique' => 'This person already has an employee record.'])
                    // Search instead of loading every user into the page; people who
                    // already have a profile are hidden (user_id is unique — picking one
                    // used to fail with a raw SQL error).
                    ->getSearchResultsUsing(fn (string $search, ?EmployeeProfile $record): array => User::query()
                        ->where(fn (Builder $q) => $q
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->where(fn (Builder $q) => $q
                            ->whereDoesntHave('employeeProfile')
                            ->when($record, fn (Builder $q) => $q->orWhere('id', $record->user_id)))
                        ->where(fn (Builder $q) => $q->whereNull('is_guest')->orWhere('is_guest', false))
                        ->orderBy('name')
                        ->limit(30)
                        ->get()
                        ->mapWithKeys(fn (User $u): array => [$u->id => self::userLabel($u)])
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => ($u = User::find($value)) ? self::userLabel($u) : null)
                    ->createOptionModalHeading('New staff account')
                    ->createOptionForm([
                        TextInput::make('name')->label('Full name')->required()->maxLength(120),
                        TextInput::make('email')
                            ->label('Work email')
                            ->email()
                            ->required()
                            ->unique('users', 'email'),
                        TextInput::make('phone')
                            ->label('Mobile')
                            ->tel()
                            ->prefix('+91')
                            ->regex('/^[6-9]\d{9}$/')
                            ->validationMessages(['regex' => 'Enter the 10-digit mobile number.'])
                            ->unique('users', 'phone'),
                        TextInput::make('password')
                            ->label('Temporary password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->helperText('Share this with them; they can change it after signing in.'),
                    ])
                    ->createOptionUsing(fn (array $data): int => User::create([
                        'name' => trim($data['name']),
                        'email' => strtolower(trim($data['email'])),
                        'phone' => filled($data['phone'] ?? null) ? $data['phone'] : null,
                        'password' => $data['password'],
                    ])->getKey())
                    ->columnSpanFull(),
                TextInput::make('employee_code')
                    ->label('Employee code')
                    ->default(fn (): string => self::nextEmployeeCode())
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state): string => strtoupper(trim((string) $state)))
                    ->helperText('Can be used instead of email to sign in.'),
            ]);
    }

    private static function placementSection(): Section
    {
        return Section::make('Role & placement')
            ->columns(2)
            ->schema([
                Select::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('designation_id')
                    ->label('Designation')
                    ->relationship('designation', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('reporting_manager_id')
                    ->label('Reports to')
                    ->placeholder('No manager')
                    ->options(fn (?EmployeeProfile $record): array => EmployeeProfile::query()
                        ->with('user:id,name')
                        ->when($record, fn (Builder $q) => $q->where('user_id', '!=', $record->user_id))
                        ->get()
                        ->filter(fn (EmployeeProfile $p) => $p->user !== null)
                        ->mapWithKeys(fn (EmployeeProfile $p): array => [$p->user_id => $p->user->name . ' · ' . $p->employee_code])
                        ->sort()
                        ->all())
                    ->searchable(),
                Select::make('venue_id')
                    ->label('Primary venue')
                    ->placeholder('HQ')
                    ->relationship('venue', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('partner_id')
                    ->label('Partner')
                    ->placeholder('Haraan (not a partner\'s staff)')
                    ->helperText('Set when this person works for a partner — they then show in that partner\'s attendance, roster and leave pages.')
                    ->options(fn (): array => User::query()
                        ->where('role', 'PARTNER')
                        ->whereNull('parent_partner_id')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->columnSpanFull(),
            ]);
    }

    private static function employmentSection(): Section
    {
        return Section::make('Employment')
            ->schema([
                Select::make('employment_status')
                    ->label('Status')
                    ->options(self::EMPLOYMENT_STATUSES)
                    ->default('active')
                    ->native(false)
                    ->required()
                    // Older rows are stored upper-case (ACTIVE); match them to an option.
                    ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : strtolower($state)),
                Select::make('employment_type')
                    ->label('Type')
                    ->options(self::EMPLOYMENT_TYPES)
                    ->default('full_time')
                    ->native(false)
                    ->required(),
                DatePicker::make('joining_date')
                    ->label('Joining date')
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->default(now())
                    // Older rows have no joining date; don't block saving them.
                    ->required(fn (string $operation): bool => $operation === 'create'),
            ]);
    }

    private static function payAndAttendanceSection(): Section
    {
        return Section::make('Pay & attendance')
            ->schema([
                TextInput::make('base_salary')
                    ->label('Monthly salary')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('₹')
                    ->default(0)
                    ->required(),
                TextInput::make('hourly_rate')
                    ->label('Hourly rate')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('₹')
                    ->default(0)
                    ->helperText('For part-time and contract staff paid by the hour.'),
                TextInput::make('geofence_radius_meters')
                    ->label('Clock-in radius')
                    ->numeric()
                    ->minValue(25)
                    ->maxValue(5000)
                    ->suffix('m')
                    ->default(200)
                    ->required()
                    ->helperText('How far from the venue they can clock in.'),
            ]);
    }

    private static function personalSection(): Section
    {
        return Section::make('Personal details')
            ->columns(2)
            ->collapsible()
            ->schema([
                DatePicker::make('dob')
                    ->label('Date of birth')
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->maxDate(now()->subYears(14)),
                ToggleButtons::make('gender')
                    ->options(['male' => 'Male', 'female' => 'Female', 'other' => 'Other'])
                    ->inline()
                    ->grouped(),
                Select::make('blood_group')
                    ->label('Blood group')
                    ->options(array_combine(
                        $groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
                        $groups,
                    ))
                    ->native(false),
                Select::make('marital_status')
                    ->label('Marital status')
                    ->options(['single' => 'Single', 'married' => 'Married', 'other' => 'Other'])
                    ->native(false),
                Textarea::make('residential_address')
                    ->label('Home address')
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ]);
    }

    private static function emergencySection(): Section
    {
        return Section::make('Emergency contact')
            ->columns(3)
            ->collapsible()
            ->schema([
                TextInput::make('emergency_contact_name')->label('Name')->maxLength(120),
                Select::make('emergency_contact_relation')
                    ->label('Relation')
                    ->options(array_combine(
                        $rel = ['Father', 'Mother', 'Spouse', 'Sibling', 'Friend', 'Other'],
                        $rel,
                    ))
                    ->native(false),
                TextInput::make('emergency_contact_phone')
                    ->label('Phone')
                    ->tel()
                    ->prefix('+91')
                    ->regex('/^[6-9]\d{9}$/')
                    ->validationMessages(['regex' => 'Enter the 10-digit mobile number.']),
            ]);
    }

    private static function bankKycSection(): Section
    {
        return Section::make('Bank & KYC')
            ->description('Used for salary payouts and statutory filings. Visible to OPS, Finance and super admins only.')
            ->columns(2)
            ->collapsible()
            ->schema([
                TextInput::make('bank_name')->label('Bank')->maxLength(120),
                TextInput::make('bank_account_no')
                    ->label('Account number')
                    ->regex('/^\d{9,18}$/')
                    ->validationMessages(['regex' => 'Account numbers are 9–18 digits.']),
                TextInput::make('bank_ifsc')
                    ->label('IFSC')
                    ->placeholder('SBIN0001234')
                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper(trim($state)) : null)
                    ->regex('/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/')
                    ->validationMessages(['regex' => 'IFSC is 11 characters, like SBIN0001234.']),
                TextInput::make('bank_upi_id')
                    ->label('UPI ID')
                    ->placeholder('name@bank')
                    ->regex('/^[\w.\-]{2,256}@[a-zA-Z]{2,64}$/')
                    ->validationMessages(['regex' => 'UPI IDs look like name@bank.']),
                TextInput::make('pan_number')
                    ->label('PAN')
                    ->placeholder('ABCDE1234F')
                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper(trim($state)) : null)
                    ->regex('/^[A-Za-z]{5}\d{4}[A-Za-z]$/')
                    ->validationMessages(['regex' => 'PAN is 10 characters, like ABCDE1234F.']),
                TextInput::make('aadhaar_number')
                    ->label('Aadhaar')
                    ->placeholder('12 digits')
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? preg_replace('/\D/', '', $state) : null)
                    ->regex('/^\d{4}\s?\d{4}\s?\d{4}$/')
                    ->validationMessages(['regex' => 'Aadhaar is 12 digits.']),
                FileUpload::make('kyc_documents')
                    ->label('Documents')
                    ->helperText('ID proof, address proof, offer letter, bank proof — PDF or image, up to 5 MB each.')
                    ->multiple()
                    ->disk('local')
                    ->directory('hrms/kyc')
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(5120)
                    ->maxFiles(10)
                    ->downloadable()
                    ->openable()
                    ->columnSpanFull(),
            ]);
    }

    private static function userLabel(User $u): string
    {
        $contact = collect([$u->email, $u->phone])->filter()->first();

        return $contact ? "{$u->name} — {$contact}" : (string) $u->name;
    }

    /** Next free HRN-EMP-#### code (sequential, not random, so codes don't collide). */
    public static function nextEmployeeCode(): string
    {
        $max = EmployeeProfile::query()
            ->where('employee_code', 'like', 'HRN-EMP-%')
            ->pluck('employee_code')
            ->map(fn (string $c): int => (int) substr($c, 8))
            ->max() ?? 1000;

        return 'HRN-EMP-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_code')->fontFamily('mono')->weight('bold')->searchable(),
                TextColumn::make('user.name')->label('Name')->searchable()->sortable(),
                TextColumn::make('department.name')->label('Department')->badge()->color('gray'),
                TextColumn::make('designation.name')->label('Designation'),
                TextColumn::make('venue.name')->label('Branch / Venue')->placeholder('HQ'),
                // Casing is mixed in the data (ACTIVE / active); compared raw, every
                // active employee showed red.
                TextColumn::make('employment_status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucfirst(str_replace('_', ' ', strtolower((string) $state))))
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'active' => 'success',
                        'on_leave', 'probation' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('base_salary')->money('INR')->sortable(),
                TextColumn::make('joining_date')->date(),
            ])
            ->filters([
                SelectFilter::make('employment_status')
                    ->label('Status')
                    ->options(self::EMPLOYMENT_STATUSES)
                    // Older rows are stored upper-case; match either.
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereRaw('lower(employment_status) = ?', [$data['value']])
                        : $query),
                SelectFilter::make('employment_type')
                    ->label('Type')
                    ->options(self::EMPLOYMENT_TYPES),
                SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }
}
