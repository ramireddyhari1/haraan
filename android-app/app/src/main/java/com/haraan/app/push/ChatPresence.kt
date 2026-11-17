package com.haraan.app.push

import android.content.Context

/**
 * Which conversation is on screen right now, if any.
 *
 * Read by [HaraanMessagingService] so a push for the thread you are already reading is
 * dropped — the message is in front of you over the socket, and a buzz for it is noise.
 * Opening a thread also clears its notification from the shade.
 */
object ChatPresence {
    @Volatile
    var openConversationId: Long = 0L
        private set

    fun open(context: Context, conversationId: Long) {
        openConversationId = conversationId
        PushNotifications.cancelChat(context, conversationId)
    }

    fun close(conversationId: Long) {
        if (openConversationId == conversationId) openConversationId = 0L
    }
}
