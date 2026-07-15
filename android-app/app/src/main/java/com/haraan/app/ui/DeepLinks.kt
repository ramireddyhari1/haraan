package com.haraan.app.ui

import android.content.Context
import android.content.Intent
import android.net.Uri

/**
 * Central deep-link routing for server-driven content — feed cards, ad CTAs, and future block
 * CTAs all resolve their target here, so the set of link kinds lives in one place.
 *
 * A link is either a typed in-app target (`link_type` + `link_id`) or a web URL. Unknown types
 * are a no-op, so /control can introduce new link kinds that an old app safely ignores (same
 * forward-compatible philosophy as the home block dispatch).
 *
 * Supported today:
 *  - `match`           → in-app match details (`link_id` = match id)
 *  - `url`/`external`/`web`/`link` → open in the browser (`link_id` = the URL)
 *
 * Not yet routable by id: `venue`, `event` — their nav keys need full display data, not just an
 * id. Add a case here (and a by-id load path) once that exists; callers won't change.
 */
fun openContentLink(
    context: Context,
    linkType: String?,
    linkId: String?,
    onMatch: (String) -> Unit,
) {
    when (linkType?.trim()?.lowercase()) {
        "match" -> linkId?.takeIf { it.isNotBlank() }?.let(onMatch)
        "url", "external", "web", "link" -> openExternalUrl(context, linkId)
        else -> Unit // unknown / unset type → no-op (forward-compatible)
    }
}

/** Open a web URL in the browser. Blank/malformed targets are ignored, never crash. */
fun openExternalUrl(context: Context, url: String?) {
    val target = url?.trim()?.takeIf { it.isNotBlank() } ?: return
    runCatching {
        context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(target)))
    }
}
