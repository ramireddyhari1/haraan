<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Schemas\EventForm;
use App\Models\Coupon;
use App\Models\TicketType;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    /** The Ticket Studio state lifted out of the form payload before the event saves. */
    private array $studioState = [];

    /** The Coupon Studio state lifted out of the form payload before the event saves. */
    private array $couponStudioState = [];

    /**
     * Split stored images into uploads vs pasted URLs for the two form fields, and
     * hydrate the Ticket Studio from the event's existing tiers so the edit page opens
     * with the real tickets, sessions count, seating mode and release phases loaded.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = EventForm::splitImageSources($data);
        $data['ticketStudio'] = EventForm::studioStateFromEvent($this->record);
        $data['couponStudio'] = EventForm::couponStudioFromEvent($this->record);

        return $data;
    }

    /**
     * Fold the pasted URLs back into the images column, lift the Ticket Studio out of the
     * payload (it isn't an event column — its tiers are reconciled in afterSave), and map
     * its mode/seating/phases onto the real columns. Capacity tracks the studio's tier
     * seats, shifting available_slots by the same delta so seats already sold are kept.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = EventForm::mergeImageSources($data);

        $this->studioState = is_array($data['ticketStudio'] ?? null) ? $data['ticketStudio'] : [];
        unset($data['ticketStudio']);

        $this->couponStudioState = is_array($data['couponStudio'] ?? null) ? $data['couponStudio'] : [];
        unset($data['couponStudio']);

        $data['tickets_per_slot'] = ($this->studioState['mode'] ?? 'unified') === 'per_slot';
        $data['seat_selection']   = (bool) ($this->studioState['seating'] ?? false);
        $data['release_phases']   = EventForm::studioReleasePhases($this->studioState) ?: null;

        // Only re-derive capacity when the studio actually has tickets — an empty studio
        // (mid-edit) must not blow total_slots up to the unlimited cap.
        if (EventForm::studioTicketRows($this->studioState) !== []) {
            $newCap = EventForm::studioCapacity($this->studioState);
            $delta  = $newCap - (int) $this->record->total_slots;
            $data['total_slots']     = $newCap;
            $data['available_slots'] = max(0, (int) $this->record->available_slots + $delta);
        }

        return $data;
    }

    /**
     * Reconcile the Ticket Studio's cards into the event's ticket_types after the event
     * itself has saved: update existing tiers in place (touching only studio-managed
     * fields, so `sold` and any advanced pricing survive), create new ones, and delete
     * tiers the host removed — but never one that already has sold tickets. Runs in a
     * transaction so a half-applied reconciliation can't leave orphaned inventory.
     */
    protected function afterSave(): void
    {
        $event   = $this->record;
        $tickets = is_array($this->studioState['tickets'] ?? null) ? $this->studioState['tickets'] : [];
        $keepIds = [];
        $blocked = [];

        DB::transaction(function () use ($event, $tickets, &$keepIds): void {
            $sort = 0;

            foreach ($tickets as $t) {
                $attrs = EventForm::studioRowAttributes($t, $sort);
                if ($attrs === null) {
                    continue;
                }
                $sort++;

                $id   = isset($t['id']) && (int) $t['id'] > 0 ? (int) $t['id'] : null;
                $tier = $id !== null ? $event->ticketTypes()->find($id) : null;

                if ($tier !== null) {
                    // Capacity can't drop below what's already sold, or remaining() goes negative.
                    if ($attrs['capacity'] !== null && $attrs['capacity'] < (int) $tier->sold) {
                        $attrs['capacity'] = (int) $tier->sold;
                    }
                    $tier->update($attrs);
                } else {
                    $tier = $event->ticketTypes()->create($attrs);
                }

                $keepIds[] = (int) $tier->id;
            }
        });

        // Remove tiers dropped in the studio — but keep any with sales (deleting them would
        // strand real bookings), and tell the host which ones were kept.
        $event->ticketTypes()
            ->whereNotIn('id', $keepIds === [] ? [0] : $keepIds)
            ->get()
            ->each(function (TicketType $tier) use (&$blocked): void {
                if ((int) $tier->sold > 0) {
                    $blocked[] = $tier->name;

                    return;
                }
                $tier->delete();
            });

        if ($blocked !== []) {
            Notification::make()
                ->title('Some tickets were kept')
                ->body('These already have sales, so they can’t be removed: ' . implode(', ', $blocked) . '.')
                ->warning()
                ->send();
        }

        $this->reconcileCoupons();
    }

    /**
     * Reconcile the Coupon Studio's cards into the event's coupons: update existing codes
     * in place (studio-managed columns only, so the redeemed `uses` count survives), create
     * new ones, and delete removed ones. Runs in a transaction.
     */
    private function reconcileCoupons(): void
    {
        $event   = $this->record;
        $coupons = is_array($this->couponStudioState['coupons'] ?? null) ? $this->couponStudioState['coupons'] : [];
        $keepIds = [];

        DB::transaction(function () use ($event, $coupons, &$keepIds): void {
            foreach ($coupons as $c) {
                $attrs = EventForm::couponRowAttributes($c);
                if ($attrs === null) {
                    continue;
                }

                $id     = isset($c['id']) && (int) $c['id'] > 0 ? (int) $c['id'] : null;
                $coupon = $id !== null ? $event->coupons()->find($id) : null;

                if ($coupon !== null) {
                    $coupon->update($attrs);
                } else {
                    $coupon = $event->coupons()->create($attrs);
                }

                $keepIds[] = (int) $coupon->id;
            }

            $event->coupons()->whereNotIn('id', $keepIds === [] ? [0] : $keepIds)->delete();
        });
    }

    /**
     * After saving, return to the events list. Filament's default keeps you on the
     * edit page, so "Save changes" only flashed a toast and looked like it did
     * nothing — the user expects to be taken back to the events page.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
