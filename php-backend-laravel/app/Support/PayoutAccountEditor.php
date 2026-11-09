<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AdminAction;
use App\Models\PartnerManager;
use App\Models\PartnerPayoutAccount;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The one door through which a settlement destination changes, whoever walks
 * through it: the partner (app or /partner), their Haraan manager, or Haraan
 * finance/admin in /control.
 *
 * Every change clears verification (a changed destination is one nobody has
 * checked), records who made it, and — when it wasn't the partner owner — pushes
 * the owner a heads-up, because "someone else changed where my money goes" is
 * exactly what an owner must never find out about later.
 */
final class PayoutAccountEditor
{
    public const KIND_PARTNER = 'partner';
    public const KIND_MANAGER = 'manager';
    public const KIND_HARAAN = 'haraan';

    /** Platform rule payouts.partner_change_mode — what a partner's own save does. */
    public const MODE_DIRECT = 'direct';
    public const MODE_APPROVE = 'approve';
    public const MODE_HARAAN_ONLY = 'haraan_only';

    public const RESULT_SAVED = 'saved';
    public const RESULT_PENDING = 'pending';

    public static function partnerMode(): string
    {
        $mode = PlatformRules::string('payouts.partner_change_mode');

        return in_array($mode, [self::MODE_DIRECT, self::MODE_APPROVE, self::MODE_HARAAN_ONLY], true) ? $mode : self::MODE_APPROVE;
    }

    /** May the venue owner change (or ask to change) their destination at all? */
    public static function partnerMayChange(User $actor): bool
    {
        return $actor->parent_partner_id === null && self::partnerMode() !== self::MODE_HARAAN_ONLY;
    }

    /**
     * The partner's own save, from the app, the web app or /partner.
     *
     * In "approve" mode a change to a VERIFIED destination doesn't overwrite it: it
     * waits for finance, and settlements keep going to the verified account until then.
     * A destination nobody has verified yet is replaced directly — no money moves to
     * an unverified account anyway, so finance's verify step is the approval.
     *
     * @param  array{method:string, account_holder:string, bank_name?:?string, account_number?:?string, ifsc_code?:?string, upi_vpa?:?string}  $data
     * @return array{0:string, 1:PartnerPayoutAccount}  [RESULT_SAVED|RESULT_PENDING, account]
     */
    public function submitByPartner(int $partnerId, array $data, User $actor): array
    {
        $mode = self::partnerMode();
        if ($mode === self::MODE_HARAAN_ONLY || $actor->parent_partner_id !== null) {
            throw new AuthorizationException('Your settlement account is managed by Haraan. Call your Haraan manager to change it.');
        }

        $existing = PartnerPayoutAccount::query()->where('partner_id', $partnerId)->first();
        if ($mode === self::MODE_APPROVE && $existing !== null && $existing->isVerified()) {
            $existing->update([
                'pending_payload' => self::normalise($data),
                'pending_at' => now(),
                'pending_by_id' => $actor->id,
                'pending_note' => null,
            ]);
            AdminAction::log('payout_account.change_requested', [
                'account_id' => $existing->id,
                'partner_id' => $partnerId,
                'to' => $existing->pendingAsAccount()?->summaryLine(),
            ], $existing);

            return [self::RESULT_PENDING, $existing];
        }

        return [self::RESULT_SAVED, $this->save($partnerId, $data, $actor)];
    }

    /** Finance approves a requested change: it goes live, already verified. */
    public function approvePending(PartnerPayoutAccount $account, User $actor): PartnerPayoutAccount
    {
        abort_unless(self::staffMayVerify($actor) && $account->hasPendingChange(), 403);

        $data = self::normalise($account->pending_payload);
        $account->update([
            ...$data,
            'verified_at' => now(),
            'verified_by_id' => $actor->id,
            // The partner made this change; finance only vouched for it.
            'updated_by_id' => $account->pending_by_id ?: $account->partner_id,
            'updated_by_kind' => self::KIND_PARTNER,
            'pending_payload' => null,
            'pending_at' => null,
            'pending_by_id' => null,
            'pending_note' => null,
        ]);
        AdminAction::log('payout_account.change_approved', ['account_id' => $account->id, 'partner_id' => $account->partner_id], $account);
        PartnerUpdates::record(
            (int) $account->partner_id, 'payout_account.change_approved',
            'Your new settlement account is live',
            'Haraan checked it. Settlements now go to ' . $account->summaryLine() . '.',
            'payouts', $account, $actor,
        );

        return $account;
    }

    /** Finance turns a requested change down; the verified account stays. */
    public function rejectPending(PartnerPayoutAccount $account, User $actor, string $reason): void
    {
        abort_unless(self::staffMayVerify($actor) && $account->hasPendingChange(), 403);

        $reason = trim($reason) !== '' ? mb_substr(trim($reason), 0, 240) : 'We could not confirm the new account belongs to you.';
        $account->update(['pending_payload' => null, 'pending_at' => null, 'pending_by_id' => null, 'pending_note' => $reason]);
        AdminAction::log('payout_account.change_rejected', ['account_id' => $account->id, 'partner_id' => $account->partner_id, 'reason' => $reason], $account);
        PartnerUpdates::record(
            (int) $account->partner_id, 'payout_account.change_rejected',
            'Settlement account change not approved',
            $reason . ' Money still goes to ' . $account->summaryLine() . '.',
            'payouts', $account, $actor,
        );
    }

    /** The partner withdraws their own request. */
    public function cancelPending(PartnerPayoutAccount $account): void
    {
        if (! $account->hasPendingChange()) {
            return;
        }
        $account->update(['pending_payload' => null, 'pending_at' => null, 'pending_by_id' => null]);
        AdminAction::log('payout_account.change_cancelled', ['account_id' => $account->id, 'partner_id' => $account->partner_id], $account);
    }

    /**
     * The stored shape of a destination: only the chosen method's fields survive, so a
     * switched destination never keeps a stale bank or UPI on file.
     *
     * @param  array<string, mixed>  $data
     * @return array{method:string, account_holder:string, bank_name:?string, account_number:?string, ifsc_code:?string, upi_vpa:?string}
     */
    public static function normalise(array $data): array
    {
        $bank = ($data['method'] ?? '') === 'bank';

        return [
            'method' => $bank ? 'bank' : 'upi',
            'account_holder' => trim((string) ($data['account_holder'] ?? '')),
            'bank_name' => $bank ? (trim((string) ($data['bank_name'] ?? '')) ?: null) : null,
            'account_number' => $bank ? preg_replace('/\s+/', '', (string) ($data['account_number'] ?? '')) : null,
            'ifsc_code' => $bank ? strtoupper(trim((string) ($data['ifsc_code'] ?? ''))) : null,
            'upi_vpa' => $bank ? null : strtolower(trim((string) ($data['upi_vpa'] ?? ''))),
        ];
    }

    /** Is [actor] this partner's assigned Haraan manager? */
    public static function isManagerOf(User $actor, int $partnerId): bool
    {
        return PartnerManager::query()
            ->where('partner_id', $partnerId)
            ->where('manager_id', $actor->id)
            ->exists();
    }

    /** Partner ids [actor] manages for Haraan. */
    public static function managedPartnerIds(User $actor): array
    {
        return PartnerManager::query()->where('manager_id', $actor->id)->pluck('partner_id')->map(fn ($id) => (int) $id)->all();
    }

    /** May an internal user (in /control) see and edit this partner's destination? */
    public static function staffMayEdit(User $actor, int $partnerId): bool
    {
        return $actor->canManage('finance') || self::isManagerOf($actor, $partnerId);
    }

    /** Only finance/admin vouch for a destination — a manager who edits can't also verify it. */
    public static function staffMayVerify(User $actor): bool
    {
        return $actor->canManage('finance');
    }

    /**
     * @param  array{method:string, account_holder:string, bank_name?:?string, account_number?:?string, ifsc_code?:?string, upi_vpa?:?string}  $data
     */
    public function save(int $partnerId, array $data, User $actor): PartnerPayoutAccount
    {
        $kind = match (true) {
            $actor->effectivePartnerId() === $partnerId && $actor->hasRoleEither(['PARTNER']) => self::KIND_PARTNER,
            self::isManagerOf($actor, $partnerId) && ! $actor->canManage('finance') => self::KIND_MANAGER,
            default => self::KIND_HARAAN,
        };

        $account = PartnerPayoutAccount::updateOrCreate(
            ['partner_id' => $partnerId],
            [
                ...self::normalise($data),
                'verified_at' => null,
                'verified_by_id' => null,
                'updated_by_id' => $actor->id,
                'updated_by_kind' => $kind,
                // A direct save supersedes any request still waiting.
                'pending_payload' => null,
                'pending_at' => null,
                'pending_by_id' => null,
                'pending_note' => null,
            ],
        );

        if ($kind !== self::KIND_PARTNER) {
            AdminAction::log('payout_account.edited', [
                'account_id' => $account->id,
                'partner_id' => $partnerId,
                'as' => $kind,
            ], $account);
        }

        // Tell the owner — live in the app, push, WhatsApp — whenever Haraan made the change.
        PartnerUpdates::record(
            $partnerId, 'payout_account.changed',
            'Your settlement account was changed',
            'Money now goes to ' . $account->summaryLine() . ' (' . ($account->account_holder ?: 'no name') . '). Haraan checks it before the next transfer. Not expected? Call your Haraan manager.',
            'payouts', $account, $actor,
        );

        return $account;
    }

    public function verify(PartnerPayoutAccount $account, User $actor): void
    {
        $account->update(['verified_at' => now(), 'verified_by_id' => $actor->id]);
        AdminAction::log('payout_account.verified', ['account_id' => $account->id, 'partner_id' => $account->partner_id], $account);
        PartnerUpdates::record(
            (int) $account->partner_id, 'payout_account.verified',
            'Your settlement account is verified',
            'Settlements go to ' . $account->summaryLine() . '.',
            'payouts', $account, $actor,
        );
    }

    public function unverify(PartnerPayoutAccount $account, ?User $actor = null): void
    {
        $account->update(['verified_at' => null, 'verified_by_id' => null]);
        AdminAction::log('payout_account.unverified', ['account_id' => $account->id, 'partner_id' => $account->partner_id], $account);
        PartnerUpdates::record(
            (int) $account->partner_id, 'payout_account.unverified',
            'Settlement account needs checking again',
            'Haraan removed the verification on ' . $account->summaryLine() . '. Transfers wait until it is checked.',
            'payouts', $account, $actor,
        );
    }

    /**
     * "Priya · your Haraan manager", "Haraan finance", "You". What the partner sees
     * next to the last change — Haraan staff below manager level are named as the team,
     * never by their personal name.
     *
     * @return array{name:string, kind:string, at:?string}|null
     */
    public static function changeLine(PartnerPayoutAccount $a, ?User $viewer): ?array
    {
        if ($a->updated_by_kind === null) {
            return null;
        }
        $by = $a->updated_by_id ? User::find($a->updated_by_id) : null;
        $name = match ($a->updated_by_kind) {
            self::KIND_PARTNER => ($viewer !== null && $by !== null && $viewer->id === $by->id) ? 'You' : (string) ($by?->name ?: 'Your team'),
            self::KIND_MANAGER => (string) ($by?->name ?: 'Your Haraan manager'),
            default => 'Haraan finance',
        };

        return ['name' => $name, 'kind' => (string) $a->updated_by_kind, 'at' => $a->updated_at?->toIso8601String()];
    }
}
