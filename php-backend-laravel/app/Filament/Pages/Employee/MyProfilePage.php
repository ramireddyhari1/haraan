<?php

declare(strict_types=1);

namespace App\Filament\Pages\Employee;

use App\Models\Hrms\EmployeeProfile;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class MyProfilePage extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'My Profile & KYC';

    protected static ?string $title = 'My Profile & Secure KYC';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.employee.my-profile-page';

    public ?string $emergencyContactName = '';
    public ?string $emergencyContactPhone = '';
    public ?string $emergencyContactRelation = '';
    public ?string $residentialAddress = '';
    public ?string $bloodGroup = '';
    public ?string $maritalStatus = '';
    public ?string $bankName = '';
    public ?string $bankAccountNo = '';
    public ?string $bankIfsc = '';
    public ?string $bankUpiId = '';

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'employee';
    }

    public function mount(): void
    {
        $profile = auth()->user()?->employeeProfile;
        if ($profile !== null) {
            $this->emergencyContactName = $profile->emergency_contact_name;
            $this->emergencyContactPhone = $profile->emergency_contact_phone;
            $this->emergencyContactRelation = $profile->emergency_contact_relation;
            $this->residentialAddress = $profile->residential_address;
            $this->bloodGroup = $profile->blood_group;
            $this->maritalStatus = $profile->marital_status;
            $this->bankName = $profile->bank_name;
            $this->bankAccountNo = $profile->bank_account_no;
            $this->bankIfsc = $profile->bank_ifsc;
            $this->bankUpiId = $profile->bank_upi_id;
        }
    }

    public function getProfileProperty(): ?EmployeeProfile
    {
        return auth()->user()?->employeeProfile;
    }

    public function saveDetails(): void
    {
        $profile = $this->profile;
        if ($profile === null) {
            return;
        }

        $profile->update([
            'emergency_contact_name' => $this->emergencyContactName,
            'emergency_contact_phone' => $this->emergencyContactPhone,
            'emergency_contact_relation' => $this->emergencyContactRelation,
            'residential_address' => $this->residentialAddress,
            'blood_group' => $this->bloodGroup,
            'marital_status' => $this->maritalStatus,
            'bank_name' => $this->bankName,
            'bank_account_no' => $this->bankAccountNo,
            'bank_ifsc' => $this->bankIfsc,
            'bank_upi_id' => $this->bankUpiId,
        ]);

        Notification::make()
            ->title('Profile Updated')
            ->body('Your emergency contacts and banking details have been securely saved.')
            ->success()
            ->send();
    }
}
