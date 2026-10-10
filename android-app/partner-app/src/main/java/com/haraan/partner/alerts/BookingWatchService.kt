package com.haraan.partner.alerts

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
import androidx.core.content.ContextCompat
import com.haraan.partner.MainActivity
import com.haraan.partner.PartnerApi
import com.haraan.partner.R
import com.haraan.partner.Session
import com.haraan.partner.formatInr
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

/**
 * "On duty": while it runs, new bookings reach the partner with the app closed — a card
 * over whatever they're doing (or a high-priority notification without the overlay
 * permission), Haraan's chime and a two-knock buzz.
 *
 * It asks the server every 15 seconds. That's the honest version until the partner app
 * has push (no Firebase config yet): it costs a small request, and the partner sees a
 * quiet "On duty" notification the whole time so it's never running unannounced.
 */
class BookingWatchService : Service() {

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main)
    private var loop: Job? = null
    private var updatesJob: Job? = null
    private var listening = false
    private lateinit var overlay: AlertOverlay

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onCreate() {
        super.onCreate()
        overlay = AlertOverlay(this)
        channels(this)
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (intent?.action == ACTION_STOP) {
            Session(this).onDuty = false
            stopSelf()
            return START_NOT_STICKY
        }
        val n = dutyNotification()
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
            startForeground(ID_DUTY, n, ServiceInfo.FOREGROUND_SERVICE_TYPE_SPECIAL_USE)
        } else {
            startForeground(ID_DUTY, n)
        }
        if (loop?.isActive != true) loop = scope.launch { watch() }
        // Updates from Haraan reach the partner here while the app is closed; when it's
        // open, the console shows them itself (PartnerUpdatesBus.appVisible).
        if (updatesJob?.isActive != true) updatesJob = scope.launch {
            com.haraan.partner.updates.PartnerUpdatesBus.events.collect { u ->
                val session = Session(this@BookingWatchService)
                if (!com.haraan.partner.updates.PartnerUpdatesBus.appVisible && u.id > session.lastUpdateId) {
                    session.lastUpdateId = u.id
                    updateNotification(u)
                }
            }
        }
        return START_STICKY
    }

    override fun onDestroy() {
        loop?.cancel()
        updatesJob?.cancel()
        com.haraan.partner.updates.PartnerRealtime.release("duty")
        overlay.hide()
        super.onDestroy()
    }

    private suspend fun watch() {
        val session = Session(this)
        val api = PartnerApi()
        while (scope.isActive) {
            val token = session.token
            if (token.isNullOrBlank() || !session.onDuty) { stopSelf(); return }
            runCatching {
                val list = withContext(Dispatchers.IO) { api.bookings(token) }
                val maxId = list.maxOfOrNull { it.id } ?: 0L
                val last = session.lastNotifiedBookingId
                if (last == 0L) {
                    session.lastNotifiedBookingId = maxId // first look: nothing is "new"
                } else if (maxId > last) {
                    val (fresh, mark) = BookingAlert.pickFresh(list, last)
                    session.lastNotifiedBookingId = mark
                    fresh.firstOrNull()?.let { announce(BookingAlert.from(it, more = fresh.size - 1)) }
                }
            }
            runCatching {
                val page = withContext(Dispatchers.IO) { api.updates(token, after = session.lastUpdateId) }
                if (session.lastUpdateId == 0L) {
                    session.lastUpdateId = page.latestId
                } else {
                    page.items.sortedBy { it.id }.forEach { com.haraan.partner.updates.PartnerUpdatesBus.emit(it) }
                }
                if (!listening) page.realtime?.let { com.haraan.partner.updates.PartnerRealtime.acquire("duty", token, it); listening = true }
            }
            delay(POLL_MS)
        }
    }

    private fun updateNotification(u: com.haraan.partner.PartnerUpdateItem) {
        val nm = getSystemService(NotificationManager::class.java) ?: return
        val n = NotificationCompat.Builder(this, CH_UPDATES)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle(u.title)
            .setContentText(listOfNotNull(u.body, u.by?.let { "By $it" }).joinToString(" · "))
            .setStyle(NotificationCompat.BigTextStyle().bigText(listOfNotNull(u.body, u.by?.let { "By $it" }).joinToString("\n")))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_STATUS)
            .setAutoCancel(true)
            .setContentIntent(
                PendingIntent.getActivity(
                    this, 3,
                    Intent(this, MainActivity::class.java).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_SINGLE_TOP),
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
                ),
            )
            .build()
        runCatching { nm.notify(ID_UPDATE_BASE + (u.id % 1000).toInt(), n) }
    }

    private fun announce(alert: BookingAlert) {
        AlertChime.play(this)
        if (AlertOverlay.allowed(this)) {
            overlay.show(alert)
        } else {
            bookingNotification(alert)
        }
    }

    private fun openAppIntent(): PendingIntent = PendingIntent.getActivity(
        this, 1,
        Intent(this, MainActivity::class.java)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_SINGLE_TOP)
            .putExtra(AlertRouter.EXTRA_OPEN, AlertRouter.OPEN_BOOKINGS),
        PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
    )

    private fun dutyNotification(): Notification {
        val stop = PendingIntent.getService(
            this, 2, Intent(this, BookingWatchService::class.java).setAction(ACTION_STOP),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )
        return NotificationCompat.Builder(this, CH_DUTY)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle("On duty")
            .setContentText("You'll hear the moment a court is booked.")
            .setOngoing(true)
            .setSilent(true)
            .setContentIntent(openAppIntent())
            .addAction(0, "Go off duty", stop)
            .setForegroundServiceBehavior(NotificationCompat.FOREGROUND_SERVICE_IMMEDIATE)
            .build()
    }

    private fun bookingNotification(a: BookingAlert) {
        val nm = getSystemService(NotificationManager::class.java) ?: return
        val owed = (a.amount - a.paid).coerceAtLeast(0.0)
        val n = NotificationCompat.Builder(this, CH_BOOKINGS)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle("₹" + formatInr(a.amount) + " · " + a.customer)
            .setContentText(
                listOfNotNull(a.where, a.time, a.day).joinToString(" · ") +
                    if (owed > 0) " · ₹" + formatInr(owed) + " to collect" else " · Paid",
            )
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_EVENT)
            .setAutoCancel(true)
            .setContentIntent(openAppIntent())
            .build()
        runCatching { nm.notify(ID_BOOKING_BASE + (a.id % 1000).toInt(), n) }
    }

    companion object {
        private const val POLL_MS = 15_000L
        private const val ID_DUTY = 4100
        private const val ID_BOOKING_BASE = 4200
        private const val ID_UPDATE_BASE = 4300
        private const val CH_UPDATES = "haraan_updates"
        private const val CH_DUTY = "duty"
        private const val CH_BOOKINGS = "bookings"
        private const val ACTION_STOP = "haraan.duty.stop"

        fun start(context: Context) {
            ContextCompat.startForegroundService(context, Intent(context, BookingWatchService::class.java))
        }

        fun stop(context: Context) {
            context.stopService(Intent(context, BookingWatchService::class.java))
        }

        private fun channels(context: Context) {
            if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
            val nm = context.getSystemService(NotificationManager::class.java) ?: return
            nm.createNotificationChannel(
                NotificationChannel(CH_DUTY, "On duty", NotificationManager.IMPORTANCE_LOW).apply {
                    description = "Shown while Haraan is listening for new bookings."
                },
            )
            nm.createNotificationChannel(
                NotificationChannel(CH_UPDATES, "Updates from Haraan", NotificationManager.IMPORTANCE_HIGH).apply {
                    description = "Settlements, your settlement account, venue and plan changes made by Haraan."
                },
            )
            nm.createNotificationChannel(
                NotificationChannel(CH_BOOKINGS, "New bookings", NotificationManager.IMPORTANCE_HIGH).apply {
                    description = "A court was just booked."
                    // The app plays its own chime; the channel stays quiet so it never rings twice.
                    setSound(null, null)
                    enableVibration(false)
                },
            )
        }
    }
}
