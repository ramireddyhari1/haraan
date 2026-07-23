<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds a content ETag to successful GET/JSON API responses and short-circuits to
 * 304 Not Modified when the client's If-None-Match matches. This makes the app's
 * auto-refresh polls cheap: when nothing changed the client re-downloads nothing
 * (a few header bytes instead of the full payload) and skips re-parsing/re-render.
 *
 * Fully backward-compatible — a client that doesn't send If-None-Match just gets the
 * normal 200 with an extra ETag header, so older app builds are unaffected.
 *
 * Note: the response body is still rendered server-side (this saves bandwidth + client
 * work, not DB/CPU). A version-key check before querying would save CPU too — a later
 * optimization; the body-hash ETag is the safe, correct first step.
 */
class SetConditionalHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only revalidate safe reads that returned a real, hashable body.
        $content = $request->isMethod('GET') && $response->getStatusCode() === 200
            ? $response->getContent()
            : false;

        if ($content !== false && $content !== '') {
            $response->setEtag(md5($content));
            // Per-user responses must never be shared-cached; always revalidate.
            $response->headers->set('Cache-Control', 'private, must-revalidate, max-age=0');
            // Rewrites the response to a bodyless 304 when If-None-Match matches.
            $response->isNotModified($request);
        }

        return $response;
    }
}
