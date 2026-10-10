<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One thing Haraan changed on a partner's account, as told to the partner. */
final class PartnerUpdate extends Model
{
    protected $fillable = [
        'partner_id', 'kind', 'title', 'body', 'screen', 'actor_name', 'actor_kind',
        'subject_type', 'subject_id', 'seen_at', 'whatsapp_sent_at',
    ];

    protected $casts = [
        'seen_at' => 'datetime',
        'whatsapp_sent_at' => 'datetime',
    ];

    /** @return array<string, mixed> */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'screen' => $this->screen,
            'by' => $this->actor_name,
            'by_kind' => $this->actor_kind,
            'seen' => $this->seen_at !== null,
            'at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
