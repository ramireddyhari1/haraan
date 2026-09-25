<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Operations;
use App\Support\PlatformRules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side enforcement of two /control → Operations switches for every public request:
 *
 *  - Maintenance mode: the app API answers 503 `maintenance`, the website shows the message.
 *  - Minimum app version: an app that reports an older X-App-Version gets 426
 *    `update_required` on every API call, so it cannot keep booking or scoring.
 *
 * Always reachable, whatever the switches say: /control, /partner and /employee (staff run
 * the platform from there, including turning maintenance off), the partner app's API,
 * payment and messaging webhooks (money already moving must land), /api/config (it's how
 * clients learn about the switches), health checks, and the public account-deletion page.
 */
final class EnforcePlatformOperations
{
    /** Path prefixes never blocked. */
    private const ALWAYS_OPEN = [
        'control', 'partner', 'employee', 'livewire', 'filament', 'up',
        'api/config', 'api/partner', 'api/webhooks', 'broadcasting', 'storage', 'build', 'css', 'js',
        'account/delete', 'favicon.ico', 'robots.txt',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->alwaysOpen($request)) {
            return $next($request);
        }

        $isApi = $request->is('api/*');

        if (Operations::maintenance()) {
            $message = PlatformRules::string('ops.maintenance_message');

            if ($isApi || $request->expectsJson()) {
                return response()->json(['error' => 'maintenance', 'message' => $message], 503, ['Retry-After' => '300']);
            }

            return response()->view('site.maintenance', ['message' => $message], 503, ['Retry-After' => '300']);
        }

        if ($isApi) {
            $version = $request->header('X-App-Version');
            if (Operations::appNeedsUpdate(is_string($version) ? $version : null)) {
                return response()->json([
                    'error' => 'update_required',
                    'message' => PlatformRules::string('app.update_message'),
                    'min_version' => Operations::minimumAppVersion(),
                    'url' => PlatformRules::string('app.update_url'),
                ], 426);
            }
        }

        return $next($request);
    }

    private function alwaysOpen(Request $request): bool
    {
        $path = trim($request->path(), '/');

        foreach (self::ALWAYS_OPEN as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
