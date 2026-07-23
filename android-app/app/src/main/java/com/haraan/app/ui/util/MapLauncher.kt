package com.haraan.app.ui.util

import android.content.Context
import android.content.Intent
import android.net.Uri

/**
 * Open a location in the user's maps app. When the admin pasted a Google Maps
 * link ([mapLink]) we open it directly so directions land on the exact place;
 * otherwise we fall back to a `geo:` text search on [fallbackQuery] (venue name
 * / address). Safe on any device — failures are swallowed.
 */
fun openMap(context: Context, mapLink: String?, fallbackQuery: String) {
    val target = mapLink?.trim()?.takeIf { it.startsWith("http", ignoreCase = true) }
        ?.let { Uri.parse(it) }
        ?: Uri.parse("geo:0,0?q=" + Uri.encode(fallbackQuery))

    val intent = Intent(Intent.ACTION_VIEW, target).apply {
        addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
    }
    runCatching { context.startActivity(intent) }
}
