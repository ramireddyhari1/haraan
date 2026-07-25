package com.haraan.app.push

import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import com.haraan.app.R

/**
 * Receives FCM callbacks. Two jobs:
 *  - [onNewToken]: FCM rotated this device's token — re-register it with the backend.
 *  - [onMessageReceived]: a push arrived while the app was in the foreground, or a
 *    data-only message arrived in the background — render it in the system shade.
 *
 * Notification-only messages that arrive while the app is backgrounded are drawn by
 * FCM itself (using the default channel/icon meta-data in the manifest) and never
 * reach [onMessageReceived]; we only build our own notification for the cases FCM
 * hands to the app.
 */
class HaraanMessagingService : FirebaseMessagingService() {

    override fun onNewToken(token: String) {
        PushRegistrar.onNewToken(this, token)
    }

    override fun onMessageReceived(message: RemoteMessage) {
        val data = message.data
        val notification = message.notification

        val title = notification?.title ?: data["title"] ?: getString(R.string.app_name)
        val body = notification?.body ?: data["body"] ?: return
        // deep_link lets a tap route into the app (e.g. a booking or event screen).
        val deepLink = data["deep_link"]

        PushNotifications.show(this, title, body, deepLink)
    }
}
