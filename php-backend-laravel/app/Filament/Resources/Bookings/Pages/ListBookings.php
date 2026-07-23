<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\AdminAction;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportBookingsCsv()),
            CreateAction::make(),
        ];
    }

    private function exportBookingsCsv(): StreamedResponse
    {
        AdminAction::log('bookings.exported');

        $headers = ['ID', 'Customer', 'Event', 'Quantity', 'Amount', 'Status', 'Coupon', 'Created'];

        return response()->streamDownload(function () use ($headers): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            Booking::with(['user', 'event'])->orderByDesc('created_at')->chunk(200, function ($rows) use ($out): void {
                foreach ($rows as $b) {
                    fputcsv($out, [
                        $b->id,
                        $b->user?->name,
                        $b->event?->title,
                        $b->quantity,
                        $b->total_amount,
                        $b->status,
                        $b->coupon_code,
                        $b->created_at,
                    ]);
                }
            });
            fclose($out);
        }, 'bookings-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }
}
