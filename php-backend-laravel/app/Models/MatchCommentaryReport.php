<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A listener's report against a live commentary broadcast. One per listener per session. */
class MatchCommentaryReport extends Model
{
    public const REASONS = [
        'abusive' => 'Abusive or hateful',
        'sexual' => 'Sexual content',
        'spam' => 'Ads or spam',
        'not_match' => 'Not about the match',
        'other' => 'Something else',
    ];

    protected $fillable = ['session_id', 'reporter_id', 'reporter_key', 'reason', 'details'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(MatchCommentarySession::class, 'session_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
