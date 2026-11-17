package com.haraan.app.push

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import com.haraan.app.HomeActivity
import com.haraan.app.R

/**
 * Builds and posts the system notifications delivered by [HaraanMessagingService]
 * (i.e. when the app is backgrounded/killed and FCM hands us a data message, or a
 * notification message that we want to render ourselves).
 *
 * The bell inbox inside the app is a separate surface (see NotificationRepository);
 * this only covers the OS-level shade notification.
 */
object PushNotifications {
    /** Single default channel. Kept in sync with the manifest default-channel meta-data. */
    const val CHANNEL_ID = "haraan_default"
    private const val CHANNEL_NAME = "General"
    private const val CHANNEL_DESC = "Bookings, tickets, matches and updates from Haraan."

    /** Extra carried on the tap intent so the app can route to a deep link when opened. */
    const val EXTRA_DEEP_LINK = "haraan.push.deep_link"

    /**
     * Create the notification channel. Idempotent and safe to call repeatedly — the
     * service calls it before every post so the channel exists even on a cold start
     * where the app process was never otherwise initialised.
     */
    fun ensureChannel(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val manager = context.getSystemService(NotificationManager::class.java) ?: return
        if (manager.getNotificationChannel(CHANNEL_ID) != null) return
        val channel = NotificationChannel(
            CHANNEL_ID,
            CHANNEL_NAME,
            NotificationManager.IMPORTANCE_HIGH,
        ).apply { description = CHANNEL_DESC }
        manager.createNotificationChannel(channel)
    }

    /**
     * Post a notification to the shade. No-ops when the runtime POST_NOTIFICATIONS
     * permission is absent (Android 13+) — the OS would drop it anyway.
     */
    fun show(context: Context, title: String, body: String, deepLink: String?) {
        if (!hasPermission(context)) return
        ensureChannel(context)

        val tapIntent = Intent(context, HomeActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            if (!deepLink.isNullOrBlank()) putExtra(EXTRA_DEEP_LINK, deepLink)
        }
        val pendingIntent = android.app.PendingIntent.getActivity(
            context,
            deepLink?.hashCode() ?: 0,
            tapIntent,
            android.app.PendingIntent.FLAG_UPDATE_CURRENT or android.app.PendingIntent.FLAG_IMMUTABLE,
        )

        val notification = NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_stat_notification)
            .setContentTitle(title.ifBlank { context.getString(R.string.app_name) })
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(pendingIntent)
            .build()

        // A time-derived id so distinct pushes stack instead of replacing each other.
        val id = (System.currentTimeMillis() and 0x7FFFFFFF).toInt()
        NotificationManagerCompat.from(context).notify(id, notification)
    }

    // ── Chat ────────────────────────────────────────────────────────────────────────────
    //
    // Messages get their own channel so a player can silence chat in system settings without
    // losing booking and ticket alerts, and one notification PER CONVERSATION (tag + id) that
    // grows as messages arrive, instead of a new card for every line.

    const val CHAT_CHANNEL_ID = "haraan_chat"
    const val CHAT_TAG = "dm"

    private fun ensureChatChannel(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val manager = context.getSystemService(NotificationManager::class.java) ?: return
        if (manager.getNotificationChannel(CHAT_CHANNEL_ID) != null) return
        manager.createNotificationChannel(
            NotificationChannel(CHAT_CHANNEL_ID, "Chat messages", NotificationManager.IMPORTANCE_HIGH)
                .apply { description = "Messages from players on ActionBoard." },
        )
    }

    /**
     * Post (or extend) the notification for one conversation. [title] is the sender, or
     * "Sender · Group"; earlier lines from the same thread stay in the card as a conversation.
     */
    fun showChat(context: Context, conversationId: Long, title: String, body: String, deepLink: String?) {
        if (!hasPermission(context)) return
        ensureChatChannel(context)

        val manager = NotificationManagerCompat.from(context)
        val id = conversationId.toInt()

        // Pick up the lines already showing for this thread, so three messages read as three
        // messages in one card rather than the last one alone.
        val existing = context.getSystemService(NotificationManager::class.java)
            ?.activeNotifications
            ?.firstOrNull { it.id == id && it.tag == CHAT_TAG }
            ?.notification
        val me = androidx.core.app.Person.Builder().setName("You").build()
        val style = existing?.let { NotificationCompat.MessagingStyle.extractMessagingStyleFromNotification(it) }
            ?: NotificationCompat.MessagingStyle(me)
        val sender = title.substringBefore(" · ").ifBlank { context.getString(R.string.app_name) }
        val group = title.substringAfter(" · ", "").takeIf { it.isNotBlank() }
        style.conversationTitle = group
        style.isGroupConversation = group != null
        style.addMessage(body, System.currentTimeMillis(), androidx.core.app.Person.Builder().setName(sender).build())

        val tapIntent = Intent(context, HomeActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            if (!deepLink.isNullOrBlank()) putExtra(EXTRA_DEEP_LINK, deepLink)
        }
        val pendingIntent = android.app.PendingIntent.getActivity(
            context,
            ("dm$conversationId").hashCode(),
            tapIntent,
            android.app.PendingIntent.FLAG_UPDATE_CURRENT or android.app.PendingIntent.FLAG_IMMUTABLE,
        )

        val notification = NotificationCompat.Builder(context, CHAT_CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_stat_notification)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(style)
            .setCategory(NotificationCompat.CATEGORY_MESSAGE)
            .setAutoCancel(true)
            .setOnlyAlertOnce(false)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(pendingIntent)
            .build()

        manager.notify(CHAT_TAG, id, notification)
    }

    /** Clear a conversation's notification — called when the thread is opened. */
    fun cancelChat(context: Context, conversationId: Long) {
        NotificationManagerCompat.from(context).cancel(CHAT_TAG, conversationId.toInt())
    }

    private fun hasPermission(context: Context): Boolean {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) return true
        return ContextCompat.checkSelfPermission(
            context,
            Manifest.permission.POST_NOTIFICATIONS,
        ) == PackageManager.PERMISSION_GRANTED
    }
}
