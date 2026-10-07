<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A partner's Haraan manager — the employee they call or chat with. One row per
 * partner, edited in /control on the partner's page.
 */
final class PartnerManager extends Model
{
    use AuditsAdminChanges;

    /** Card title when the admin leaves it blank. */
    public const DEFAULT_TITLE = 'Your Haraan manager';

    protected $fillable = [
        'partner_id',
        'manager_id',
        'title',
        'intro',
        'hours',
        'phone',
        'show_call',
        'show_whatsapp',
        'show_chat',
        'is_visible',
    ];

    protected $casts = [
        'show_call' => 'boolean',
        'show_whatsapp' => 'boolean',
        'show_chat' => 'boolean',
        'is_visible' => 'boolean',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * What the partner's card shows, or null when nobody is assigned or the admin
     * has hidden the card. The phone falls back to the shared support WhatsApp.
     *
     * @return array<string, mixed>|null
     */
    public function toCard(): ?array
    {
        $manager = $this->manager;

        if (! $this->is_visible || $manager === null || ! $manager->isAccountActive()) {
            return null;
        }

        $phone = self::digits($this->phone) ?? self::digits(AppSetting::get('support_whatsapp'));

        return [
            'name' => (string) $manager->name,
            'photo_url' => MediaUrl::resolve($manager->avatar),
            'title' => trim((string) $this->title) ?: self::DEFAULT_TITLE,
            'intro' => trim((string) $this->intro) ?: null,
            'hours' => trim((string) $this->hours) ?: null,
            'phone' => $phone,
            'show_call' => $this->show_call && $phone !== null,
            'show_whatsapp' => $this->show_whatsapp && $phone !== null,
            'show_chat' => $this->show_chat,
        ];
    }

    /** Keeps a leading + and the digits; null when nothing dialable is left. */
    private static function digits(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        $clean = ($raw !== '' && $raw[0] === '+' ? '+' : '') . preg_replace('/\D+/', '', $raw);

        return strlen(ltrim($clean, '+')) >= 6 ? $clean : null;
    }
}
