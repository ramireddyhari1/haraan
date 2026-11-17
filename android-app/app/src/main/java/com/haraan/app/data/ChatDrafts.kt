package com.haraan.app.data

import android.content.Context
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow

/**
 * What you'd typed in a conversation and not sent, kept per thread.
 *
 * Leaving a chat used to throw the half-written message away. Now it waits in the box when you
 * come back, and the chat list says "Draft: …" so you know it's there. Stored in the app's own
 * private preferences on this phone only — never sent anywhere.
 *
 * The [drafts] flow lets the list redraw the moment a thread is closed with something unsent.
 */
object ChatDrafts {
    private const val PREFS = "chat_drafts"
    private val _drafts = MutableStateFlow<Map<Long, String>>(emptyMap())
    val drafts: StateFlow<Map<Long, String>> = _drafts
    @Volatile private var loaded = false

    private fun ensureLoaded(context: Context) {
        if (loaded) return
        synchronized(this) {
            if (loaded) return
            val prefs = context.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            _drafts.value = prefs.all.mapNotNull { (k, v) ->
                val id = k.toLongOrNull() ?: return@mapNotNull null
                val text = (v as? String)?.takeIf { it.isNotBlank() } ?: return@mapNotNull null
                id to text
            }.toMap()
            loaded = true
        }
    }

    fun get(context: Context, conversationId: Long): String {
        ensureLoaded(context)
        return _drafts.value[conversationId].orEmpty()
    }

    fun all(context: Context): Map<Long, String> {
        ensureLoaded(context)
        return _drafts.value
    }

    /** Save (or clear, when blank) the draft for one thread. */
    fun set(context: Context, conversationId: Long, text: String) {
        ensureLoaded(context)
        if (conversationId <= 0L) return
        val current = _drafts.value[conversationId].orEmpty()
        if (current == text || (current.isEmpty() && text.isBlank())) return
        val prefs = context.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        if (text.isBlank()) {
            prefs.edit().remove(conversationId.toString()).apply()
            _drafts.value = _drafts.value - conversationId
        } else {
            prefs.edit().putString(conversationId.toString(), text).apply()
            _drafts.value = _drafts.value + (conversationId to text)
        }
    }

    /** Signing out must not leave one account's half-written messages for the next. */
    fun clearAll(context: Context) {
        context.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().clear().apply()
        _drafts.value = emptyMap()
    }
}
