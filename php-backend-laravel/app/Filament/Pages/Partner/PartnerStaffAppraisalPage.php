<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use App\Models\Hrms\EmployeeKpi;
use App\Models\Hrms\EmployeeProfile;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class PartnerStaffAppraisalPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static ?string $title = 'Staff Performance Reviews';

    protected static ?string $navigationLabel = 'Staff Reviews';

    protected static string|\UnitEnum|null $navigationGroup = 'Team & HRMS';

    protected static ?int $navigationSort = 11;

    protected string $view = 'filament.pages.partner.appraisals';

    public bool $showReviewModal = false;
    public ?int $selectedEmployeeId = null;
    public string $reviewPeriod = '';
    public float $punctuality = 5.0;
    public float $execution = 5.0;
    public float $service = 5.0;
    public float $teamwork = 5.0;
    public string $feedback = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'partner'
            && $user !== null
            && ! $user->isDeskStaff();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->reviewPeriod = Carbon::now()->format('Y-m');
    }

    public function getStaffProperty(): array
    {
        $partnerId = auth()->user()?->effectivePartnerId();

        return EmployeeProfile::with(['user', 'kpis'])
            ->where('partner_id', $partnerId)
            ->where('employment_status', 'active')
            ->get()
            ->all();
    }

    public function openReviewModal(int $empId): void
    {
        $this->selectedEmployeeId = $empId;
        $this->showReviewModal = true;
    }

    public function closeReviewModal(): void
    {
        $this->showReviewModal = false;
    }

    public function submitReview(): void
    {
        if ($this->selectedEmployeeId === null) {
            return;
        }

        $overall = round(($this->punctuality + $this->execution + $this->service + $this->teamwork) / 4.0, 1);

        EmployeeKpi::updateOrCreate(
            [
                'employee_profile_id' => $this->selectedEmployeeId,
                'period' => $this->reviewPeriod,
            ],
            [
                'reviewer_id' => auth()->id(),
                'punctuality_rating' => $this->punctuality,
                'task_completion_rating' => $this->execution,
                'customer_service_rating' => $this->service,
                'teamwork_rating' => $this->teamwork,
                'overall_score' => $overall,
                'manager_feedback' => $this->feedback,
            ]
        );

        Notification::make()->title('Staff Review Submitted')->success()->send();
        $this->showReviewModal = false;
        $this->feedback = '';
    }
}
