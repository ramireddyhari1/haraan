package com.haraan.app.data

import android.content.Context
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.Response
import okhttp3.WebSocket
import okhttp3.WebSocketListener
import org.json.JSONObject

/**
 * Realtime content updates over Reverb (Pusher protocol) via a raw WebSocket.
 * On a `content.updated` signal it refreshes global stores (config/i18n) and
 * republishes the domain on [RealtimeBus] so screens can refetch their data.
 *
 * Config-driven: connection details come from /api/config → realtime, so the
 * socket host can change without an app release. Fully guarded — does nothing
 * unless realtime is enabled and a key/host are present.
 */
object RealtimeClient {
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val http = OkHttpClient()

    private var started = false
    private var socket: WebSocket? = null
    private var reconnectAttempts = 0

    // Extra channels subscribed at runtime (e.g. "match.{id}" while a match detail is
    // open). Tracked so they're re-subscribed after a reconnect, not just on first open.
    private val extraChannels = java.util.Collections.synchronizedSet(mutableSetOf<String>())

    private fun subscribeFrame(channel: String) =
        """{"event":"pusher:subscribe","data":{"channel":"$channel"}}"""

    /** Subscribe to an additional channel now (and on every future reconnect). */
    @Synchronized
    fun subscribe(channel: String) {
        if (channel.isBlank() || !extraChannels.add(channel)) return
        socket?.send(subscribeFrame(channel))
    }

    /** Stop listening to an additional channel. */
    @Synchronized
    fun unsubscribe(channel: String) {
        if (!extraChannels.remove(channel)) return
        socket?.send("""{"event":"pusher:unsubscribe","data":{"channel":"$channel"}}""")
    }

    @Synchronized
    fun start(context: Context, config: RealtimeConfig) {
        if (started || !config.enabled || config.key.isNullOrBlank() || config.host.isNullOrBlank()) {
            return
        }
        started = true
        connect(context.applicationContext, config)
    }

    @Synchronized
    fun stop() {
        started = false
        socket?.close(1000, "stopped")
        socket = null
    }

    private fun connect(appContext: Context, config: RealtimeConfig) {
        val wsScheme = if (config.scheme == "https") "wss" else "ws"
        val url = "$wsScheme://${config.host}:${config.port}/app/${config.key}" +
            "?protocol=7&client=android&version=1.0&flash=false"

        socket = http.newWebSocket(
            Request.Builder().url(url).build(),
            object : WebSocketListener() {
                override fun onOpen(webSocket: WebSocket, response: Response) {
                    reconnectAttempts = 0
                    webSocket.send(subscribeFrame(config.channel))
                    // Re-subscribe to any runtime channels (match.{id}) after a reconnect.
                    synchronized(extraChannels) {
                        extraChannels.forEach { webSocket.send(subscribeFrame(it)) }
                    }
                }

                override fun onMessage(webSocket: WebSocket, text: String) {
                    handleFrame(appContext, webSocket, text)
                }

                override fun onClosed(webSocket: WebSocket, code: Int, reason: String) {
                    scheduleReconnect(appContext, config)
                }

                override fun onFailure(webSocket: WebSocket, t: Throwable, response: Response?) {
                    scheduleReconnect(appContext, config)
                }
            },
        )
    }

    private fun handleFrame(appContext: Context, webSocket: WebSocket, text: String) {
        try {
            val frame = JSONObject(text)
            when (frame.optString("event")) {
                "pusher:ping" -> webSocket.send("""{"event":"pusher:pong","data":{}}""")
                "content.updated" -> {
                    // Pusher delivers the event payload as a JSON-encoded string.
                    val data = frame.opt("data")
                    val domain = when (data) {
                        is JSONObject -> data.optString("domain")
                        is String -> JSONObject(data).optString("domain")
                        else -> ""
                    }
                    if (domain.isNotBlank()) dispatch(appContext, domain)
                }
                "conversation.updated" -> {
                    // Chat's nudge. The frame deliberately carries no message body — the
                    // channel is public — so the listener refetches over the authenticated
                    // endpoint and the server remains the source of what was said.
                    val data = frame.opt("data")
                    val id = when (data) {
                        is JSONObject -> data.optString("id")
                        is String -> JSONObject(data).optString("id")
                        else -> ""
                    }
                    if (id.isNotBlank()) ChatRealtimeBus.emit(id)
                }
                "match.updated" -> {
                    val data = frame.opt("data")
                    val id = when (data) {
                        is JSONObject -> data.optString("id")
                        is String -> JSONObject(data).optString("id")
                        else -> ""
                    }
                    if (id.isNotBlank()) MatchRealtimeBus.emit(id)
                }
            }
        } catch (_: Exception) {
            // ignore malformed frames
        }
    }

    private fun dispatch(appContext: Context, domain: String) {
        scope.launch {
            when (domain) {
                "config" -> RemoteBootstrap.reloadConfig(appContext)
                "i18n" -> RemoteBootstrap.reloadTranslations(appContext)
            }
            RealtimeBus.emit(domain)
        }
    }

    private fun scheduleReconnect(appContext: Context, config: RealtimeConfig) {
        if (!started) return
        socket = null
        val backoffMs = minOf(30_000L, 1_000L * (1 shl minOf(reconnectAttempts, 5)))
        reconnectAttempts++
        scope.launch {
            delay(backoffMs)
            if (started) connect(appContext, config)
        }
    }
}
