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
import com.haraan.app.MainActivity
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

        val tapIntent = Intent(context, MainActivity::class.java).apply {
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

    private fun hasPermission(context: Context): Boolean {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) return true
        return ContextCompat.checkSelfPermission(
            context,
            Manifest.permission.POST_NOTIFICATIONS,
        ) == PackageManager.PERMISSION_GRANTED
    }
}
