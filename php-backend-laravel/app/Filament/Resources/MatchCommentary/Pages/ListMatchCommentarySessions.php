<?php

declare(strict_types=1);

namespace App\Filament\Resources\MatchCommentary\Pages;

use App\Filament\Pages\CommentaryRulesPage;
use App\Filament\Resources\MatchCommentary\MatchCommentarySessionResource;
use App\Services\MatchCommentary;
use App\Support\LiveKit;
use App\Support\PlatformRules;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListMatchCommentarySessions extends ListRecords
{
    protected static string $resource = MatchCommentarySessionResource::class;

    public function getSubheading(): ?string
    {
        if (! PlatformRules::bool('commentary.enabled')) {
            return 'Live commentary is switched OFF in Commentary rules. Nobody can go on air.';
        }
        if (! LiveKit::configured()) {
            return 'The audio server isn’t set up on this server (LIVEKIT_* in .env), so nobody can go on air yet.';
        }

        return 'Going on air needs a plan with “Live commentary” (Membership → Plans). Listening is free. '
            .'Broadcasts end on their own after '.PlatformRules::int('commentary.max_minutes').' minutes, '
            .'when the match finishes, or when the commentator’s phone stops responding for '.MatchCommentary::STALE_AFTER_SECONDS.' seconds.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rules')
                ->label('Commentary rules')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(CommentaryRulesPage::getUrl()),
        ];
    }
}
