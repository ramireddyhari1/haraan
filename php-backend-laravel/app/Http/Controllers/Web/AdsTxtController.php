<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\NetworkAds;
use Illuminate\Http\Response;

/**
 * /ads.txt (AdSense) and /app-ads.txt (AdMob — Google reads it from the developer website on
 * the Play listing). Written from /control → Marketing → Ad networks, so a new publisher ID
 * needs no file upload on the server. Without these Google limits or withholds ad serving.
 */
final class AdsTxtController extends Controller
{
    public function web(): Response
    {
        return $this->plain(NetworkAds::adsTxt(false));
    }

    public function app(): Response
    {
        return $this->plain(NetworkAds::adsTxt(true));
    }

    private function plain(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
