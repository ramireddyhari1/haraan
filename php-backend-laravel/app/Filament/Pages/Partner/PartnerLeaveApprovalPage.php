<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use App\Models\Hrms\EmployeeLeaveRequest;
use App\Services\Hrms\LeaveService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;

class PartnerLeaveApprovalPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-right-start-on-rectangle';

    protected static ?string $title = 'Staff Leave Requests';

    protected static ?string $navigationLabel = 'Leave Approvals';

    protected static string|\UnitEnum|null $navigationGroup = 'Team & HRMS';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.partner.leaves';

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

    public function getRequestsProperty(): array
    {
        $partnerId = auth()->user()?->effectivePartnerId();

        return EmployeeLeaveRequest::with(['employee.user', 'leaveType'])
            ->whereHas('employee', fn ($q) => $q->where('partner_id', $partnerId))
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    public function approveRequest(int $id): void
    {
        $request = EmployeeLeaveRequest::findOrFail($id);
        try {
            $service = app(LeaveService::class);
            $service->approve($request, auth()->user(), 'Approved by Venue Partner');
            Notification::make()->title('Leave Request Approved')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function rejectRequest(int $id): void
    {
        $request = EmployeeLeaveRequest::findOrFail($id);
        try {
            $service = app(LeaveService::class);
            $service->reject($request, auth()->user(), 'Declined by Venue Partner');
            Notification::make()->title('Leave Request Rejected')->info()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }
}
