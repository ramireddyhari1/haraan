<?php

declare(strict_types=1);

namespace App\Services\Hrms;

use App\Models\DeviceToken;
use App\Models\Hrms\EmployeeProfile;
use App\Models\User;
use App\Services\Fcm\FcmClient;
use App\Services\WhatsAppService;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

class HrmsNotifier
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly FcmClient $fcm
    ) {}

    /**
     * Dispatch notification across in-app, push, and WhatsApp.
     */
    public function notify(
        User $user,
        string $title,
        string $message,
        string $channelType = 'general',
        ?string $url = null
    ): void {
        // 1. In-App Notification
        try {
            $notification = FilamentNotification::make()
                ->title($title)
                ->body($message);

            if ($channelType === 'success') {
                $notification->success();
            } elseif ($channelType === 'warning') {
                $notification->warning();
            } elseif ($channelType === 'danger') {
                $notification->danger();
            } else {
                $notification->info();
            }

            if ($url) {
                $notification->actions([
                    \Filament\Actions\Action::make('view')
                        ->label('View')
                        ->url($url),
                ]);
            }

            $notification->sendToDatabase($user);
        } catch (Throwable $e) {
            Log::warning("HRMS In-App notification failed: " . $e->getMessage());
        }

        // 2. Push Notification via FCM
        try {
            $tokens = DeviceToken::where('user_id', $user->id)->pluck('token')->all();
            foreach ($tokens as $token) {
                if (! empty($token)) {
                    $this->fcm->sendToToken($token, $title, $message, [
                        'type' => 'hrms',
                        'channel_type' => $channelType,
                        'url' => $url ?? '',
                    ]);
                }
            }
        } catch (Throwable $e) {
            Log::warning("HRMS FCM push notification failed: " . $e->getMessage());
        }

        // 3. WhatsApp (if user has phone and urgent/payroll alert)
        if (! empty($user->phone) && in_array($channelType, ['payroll', 'urgent', 'roster'], true)) {
            try {
                $text = "*{$title}*\n{$message}" . ($url ? "\n\nView here: {$url}" : '');
                $this->whatsapp->sendMessage((string) $user->phone, $text);
            } catch (Throwable $e) {
                Log::warning("HRMS WhatsApp message failed: " . $e->getMessage());
            }
        }
    }

    public function notifyShiftAssigned(EmployeeProfile $employee, string $shiftName, string $date): void
    {
        if ($employee->user) {
            $this->notify(
                $employee->user,
                "New Shift Scheduled",
                "You have been assigned to {$shiftName} on {$date}.",
                'roster',
                url('/employee')
            );
        }
    }

    public function notifyLeaveStatus(EmployeeProfile $employee, string $status, string $leaveType, string $dates): void
    {
        if ($employee->user) {
            $type = ($status === 'approved') ? 'success' : 'danger';
            $this->notify(
                $employee->user,
                "Leave Request " . ucfirst($status),
                "Your {$leaveType} request for {$dates} has been {$status}.",
                $type,
                url('/employee/my-leaves')
            );
        }
    }

    public function notifyPayslipReady(EmployeeProfile $employee, string $month): void
    {
        if ($employee->user) {
            $this->notify(
                $employee->user,
                "Payslip Ready: {$month}",
                "Your payslip for {$month} is now available for review and download.",
                'payroll',
                url('/employee/my-payslips')
            );
        }
    }
}
