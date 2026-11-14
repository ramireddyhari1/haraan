<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Services\Membership\MemberEntitlements;
use App\Support\Membership\MemberFeature;

/**
 * Google ads in the ActionBoard — AdMob in the app, AdSense on the website — resolved from the
 * `network_ads` section of /control → Marketing → Ad networks.
 *
 * A spot is served only when every switch on its path is on and its unit ID is filled in:
 * the emergency stop, the surface's master switch, the spot's own switch. A member whose plan
 * hides ads gets nothing, exactly like the house ads in /api/ads. Haraan's own sponsors
 * (App\Models\Ad) are decided by the clients and pages first; these only fill an empty spot.
 */
final class NetworkAds
{
    /** Google's ads.txt certification authority ID — the same for every publisher. */
    private const GOOGLE_TAG_ID = 'f08c47fec0942fa0';

    public static function hiddenFor(?User $viewer): bool
    {
        return PlatformRules::bool('ops.network_ads_disabled')
            || app(MemberEntitlements::class)->allows($viewer, MemberFeature::ADS_HIDDEN);
    }

    /**
     * The `ads` block of GET /api/config. A spot the app should not fill is null, so an older
     * or newer app can never show an ad the console didn't switch on.
     *
     * @return array{feed: array{unit_id: string, every: int}|null, match: array{unit_id: string}|null}
     */
    public static function appConfig(?User $viewer): array
    {
        $off = ['feed' => null, 'match' => null];
        if (self::hiddenFor($viewer) || ! PlatformRules::bool('network_ads.app_enabled')) {
            return $off;
        }

        $feedUnit = PlatformRules::string('network_ads.admob_feed_unit_id');
        $matchUnit = PlatformRules::string('network_ads.admob_match_unit_id');

        return [
            'feed' => PlatformRules::bool('network_ads.app_feed_enabled') && $feedUnit !== ''
                ? ['unit_id' => $feedUnit, 'every' => PlatformRules::int('network_ads.app_feed_every')]
                : null,
            'match' => PlatformRules::bool('network_ads.app_match_enabled') && $matchUnit !== ''
                ? ['unit_id' => $matchUnit]
                : null,
        ];
    }

    /**
     * One AdSense unit for a website spot (`feed` or `match`), or null when it must stay empty.
     *
     * @return array{client: string, slot: string}|null
     */
    public static function webSlot(string $spot, ?User $viewer): ?array
    {
        if (! in_array($spot, ['feed', 'match'], true)
            || self::hiddenFor($viewer)
            || ! PlatformRules::bool('network_ads.web_enabled')
            || ! PlatformRules::bool("network_ads.web_{$spot}_enabled")) {
            return null;
        }

        $client = PlatformRules::string('network_ads.adsense_client');
        $slot = PlatformRules::string("network_ads.adsense_{$spot}_slot");

        return $client !== '' && $slot !== '' ? ['client' => $client, 'slot' => $slot] : null;
    }

    /**
     * ads.txt (AdSense, on the website) and app-ads.txt (AdMob, on the developer website listed
     * in Play). Written from the IDs in the console so the two can't drift apart; the extra
     * lines carry mediation partners such as Meta.
     */
    public static function adsTxt(bool $forApps): string
    {
        $publishers = [];
        if ($forApps) {
            foreach (['network_ads.admob_feed_unit_id', 'network_ads.admob_match_unit_id', 'rewards.admob_ad_unit_id'] as $key) {
                if (preg_match('#^ca-app-(pub-\d+)/#', PlatformRules::string($key), $m)) {
                    $publishers[] = $m[1];
                }
            }
        } elseif (preg_match('#^ca-(pub-\d+)$#', PlatformRules::string('network_ads.adsense_client'), $m)) {
            $publishers[] = $m[1];
        }

        $lines = array_map(
            fn (string $pub): string => 'google.com, '.$pub.', DIRECT, '.self::GOOGLE_TAG_ID,
            array_values(array_unique($publishers)),
        );
        foreach (preg_split('/\R/', PlatformRules::string('network_ads.ads_txt_extra')) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines === [] ? "# No ad networks configured yet.\n" : implode("\n", $lines)."\n";
    }
}
