package com.haraan.app.push

import android.content.Context
import com.google.firebase.messaging.FirebaseMessaging
import com.haraan.app.data.NotificationRepository
import com.haraan.app.data.TokenStore
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch

/**
 * Registers this device's FCM token with the backend (POST /api/devices/register)
 * so a segment's devices can be reached by background push.
 *
 * Registration needs a real signed-in session, so it is gated on [TokenStore]:
 *  - [syncToken] runs on app launch and after a fresh login — it pulls the current
 *    FCM token and registers it if (and only if) the user is signed in.
 *  - [onNewToken] runs when FCM rotates the token; it registers the new one, again
 *    only when signed in. A rotation that happens while logged out is picked up by
 *    the next [syncToken] on launch, so nothing is lost.
 */
object PushRegistrar {
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val repository = NotificationRepository()

    /** Fetch the current FCM token and register it, if the user is signed in. Best-effort. */
    fun syncToken(context: Context) {
        val appContext = context.applicationContext
        val authToken = TokenStore.getToken(appContext)
        if (!TokenStore.isSignedIn(authToken)) return

        FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
            val fcmToken = task.result
            if (!task.isSuccessful || fcmToken.isNullOrBlank()) return@addOnCompleteListener
            register(authToken!!, fcmToken)
        }
    }

    /** Called from the messaging service when FCM issues a new token. */
    fun onNewToken(context: Context, fcmToken: String) {
        val authToken = TokenStore.getToken(context.applicationContext)
        if (!TokenStore.isSignedIn(authToken) || fcmToken.isBlank()) return
        register(authToken!!, fcmToken)
    }

    private fun register(authToken: String, fcmToken: String) {
        scope.launch { runCatching { repository.registerDevice(authToken, fcmToken) } }
    }
}
