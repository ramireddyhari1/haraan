package com.haraan.app.commentary

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.ServiceInfo
import android.os.Build
import android.os.IBinder
import androidx.core.app.NotificationCompat
import androidx.core.app.ServiceCompat
import androidx.core.content.ContextCompat
import com.haraan.app.R

/**
 * Keeps the commentator's mic alive while the phone is locked or another app is open. Android
 * stops a background app hearing the mic unless a microphone foreground service is running,
 * so this is what lets a scorer keep calling the match with the screen off. The notification's
 * Mute and End act on [CommentaryBroadcaster] directly.
 */
class CommentaryBroadcastService : Service() {

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        when (intent?.action) {
            ACTION_END -> { CommentaryBroadcaster.stop(); return START_NOT_STICKY }
            ACTION_MUTE -> { CommentaryBroadcaster.toggleMute(); return START_NOT_STICKY }
        }
        val title = intent?.getStringExtra(EXTRA_TITLE).orEmpty()
        val muted = intent?.getBooleanExtra(EXTRA_MUTED, false) ?: false
        val type = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) ServiceInfo.FOREGROUND_SERVICE_TYPE_MICROPHONE else 0
        runCatching {
            ServiceCompat.startForeground(this, NOTIFICATION_ID, notification(this, title, muted), type)
        }.onFailure {
            // Android refused (started from the background, or no mic permission): don't
            // broadcast without the notification that says we are.
            CommentaryBroadcaster.stop()
            stopSelf()
        }
        return START_NOT_STICKY
    }

    override fun onTaskRemoved(rootIntent: Intent?) {
        // The scorer swiped the app away: that ends the broadcast too.
        CommentaryBroadcaster.stop()
        stopSelf()
    }

    companion object {
        private const val NOTIFICATION_ID = 7301
        private const val ACTION_END = "com.haraan.app.commentary.END"
        private const val ACTION_MUTE = "com.haraan.app.commentary.MUTE"
        private const val EXTRA_TITLE = "title"
        private const val EXTRA_MUTED = "muted"

        fun start(context: Context, title: String) {
            val i = Intent(context, CommentaryBroadcastService::class.java).putExtra(EXTRA_TITLE, title)
            runCatching { ContextCompat.startForegroundService(context, i) }
        }

        fun refresh(context: Context, title: String, muted: Boolean) {
            ensureChannel(context)
            context.getSystemService(NotificationManager::class.java)
                ?.notify(NOTIFICATION_ID, notification(context, title, muted))
        }

        fun stop(context: Context) {
            context.stopService(Intent(context, CommentaryBroadcastService::class.java))
        }

        private fun notification(context: Context, title: String, muted: Boolean): Notification {
            ensureChannel(context)
            fun action(action: String, code: Int) = PendingIntent.getService(
                context, code, Intent(context, CommentaryBroadcastService::class.java).setAction(action),
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
            )
            return NotificationCompat.Builder(context, CHANNEL_ID)
                .setSmallIcon(R.drawable.ic_stat_notification)
                .setContentTitle(if (muted) "Commentary muted" else "You’re on air")
                .setContentText(title.ifBlank { "Live commentary" })
                .setOngoing(true)
                .setOnlyAlertOnce(true)
                .setCategory(NotificationCompat.CATEGORY_SERVICE)
                .setContentIntent(openApp(context))
                .addAction(0, if (muted) "Unmute" else "Mute", action(ACTION_MUTE, 1))
                .addAction(0, "End", action(ACTION_END, 2))
                .build()
        }
    }
}

/**
 * Keeps a listener's commentary playing when they lock the phone or switch apps. Stop in the
 * notification ends it.
 */
class CommentaryListenService : Service() {

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (intent?.action == ACTION_STOP) {
            CommentaryListener.stop()
            return START_NOT_STICKY
        }
        val title = intent?.getStringExtra(EXTRA_TITLE).orEmpty()
        val by = intent?.getStringExtra(EXTRA_BY).orEmpty()
        val type = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) ServiceInfo.FOREGROUND_SERVICE_TYPE_MEDIA_PLAYBACK else 0
        runCatching {
            ServiceCompat.startForeground(this, NOTIFICATION_ID, notification(this, title, by), type)
        }.onFailure {
            // Can't hold playback in the background; it still plays while the app is open.
            stopSelf()
        }
        return START_NOT_STICKY
    }

    override fun onTaskRemoved(rootIntent: Intent?) {
        CommentaryListener.stop()
        stopSelf()
    }

    companion object {
        private const val NOTIFICATION_ID = 7302
        private const val ACTION_STOP = "com.haraan.app.commentary.STOP_LISTENING"
        private const val EXTRA_TITLE = "title"
        private const val EXTRA_BY = "by"

        fun start(context: Context, title: String, by: String) {
            val i = Intent(context, CommentaryListenService::class.java)
                .putExtra(EXTRA_TITLE, title)
                .putExtra(EXTRA_BY, by)
            runCatching { ContextCompat.startForegroundService(context, i) }
        }

        fun stop(context: Context) {
            context.stopService(Intent(context, CommentaryListenService::class.java))
        }

        private fun notification(context: Context, title: String, by: String): Notification {
            ensureChannel(context)
            val stop = PendingIntent.getService(
                context, 3, Intent(context, CommentaryListenService::class.java).setAction(ACTION_STOP),
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
            )
            return NotificationCompat.Builder(context, CHANNEL_ID)
                .setSmallIcon(R.drawable.ic_stat_notification)
                .setContentTitle(title.ifBlank { "Live commentary" })
                .setContentText(if (by.isBlank()) "Live commentary" else "$by is calling it live")
                .setOngoing(true)
                .setOnlyAlertOnce(true)
                .setCategory(NotificationCompat.CATEGORY_TRANSPORT)
                .setContentIntent(openApp(context))
                .addAction(0, "Stop", stop)
                .build()
        }
    }
}

private const val CHANNEL_ID = "haraan_commentary"

private fun ensureChannel(context: Context) {
    if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
    val manager = context.getSystemService(NotificationManager::class.java) ?: return
    if (manager.getNotificationChannel(CHANNEL_ID) != null) return
    manager.createNotificationChannel(
        NotificationChannel(CHANNEL_ID, "Live commentary", NotificationManager.IMPORTANCE_LOW).apply {
            description = "Shown while you're on air or listening to a match's commentary."
            setShowBadge(false)
        }
    )
}

/** Brings Haraan back to the front as it was left (the scorer or the match), not to a fresh start. */
private fun openApp(context: Context): PendingIntent {
    val launch = context.packageManager.getLaunchIntentForPackage(context.packageName)
        ?.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_RESET_TASK_IF_NEEDED)
        ?: Intent()
    return PendingIntent.getActivity(context, 0, launch, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
}
