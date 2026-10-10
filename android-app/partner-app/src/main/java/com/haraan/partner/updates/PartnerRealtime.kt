package com.haraan.partner.updates

import android.util.Log
import com.haraan.partner.PartnerApi
import com.haraan.partner.PartnerRealtimeInfo
import com.haraan.partner.PartnerUpdateItem
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.launch
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.Response
import okhttp3.WebSocket
import okhttp3.WebSocketListener
import org.json.JSONObject
import java.util.concurrent.TimeUnit

/** Every partner update the app learns about, from the socket or the fallback poll. */
object PartnerUpdatesBus {
    private val _events = MutableSharedFlow<PartnerUpdateItem>(extraBufferCapacity = 16)
    val events: SharedFlow<PartnerUpdateItem> = _events

    /** True while the console is on screen: the on-duty service then leaves alerts to it. */
    @Volatile var appVisible: Boolean = false

    fun emit(u: PartnerUpdateItem) { _events.tryEmit(u) }
}

/**
 * The partner's live line to Haraan: one WebSocket to Reverb (Pusher protocol),
 * subscribed to `private-partner.{id}` and signed in through /api/partner/realtime/auth.
 * When admin, finance or the partner's manager changes something in /control, the
 * update arrives here within a second and goes out on [PartnerUpdatesBus].
 *
 * Shared by the open app and the on-duty service: each [acquire]s it with a tag and
 * [release]s it; the socket lives while anyone holds it, and reconnects with backoff.
 * The 20s/15s polls keep running underneath, so a dropped socket only delays an update.
 */
object PartnerRealtime {
    private const val TAG = "PartnerRealtime"
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val client = OkHttpClient.Builder()
        .pingInterval(25, TimeUnit.SECONDS)
        .readTimeout(0, TimeUnit.MILLISECONDS)
        .build()

    private val holders = mutableSetOf<String>()
    private var socket: WebSocket? = null
    private var info: PartnerRealtimeInfo? = null
    private var token: String? = null
    private var retry: Job? = null
    private var attempt = 0

    @Synchronized
    fun acquire(tag: String, token: String, info: PartnerRealtimeInfo) {
        holders += tag
        val changed = this.info != info || this.token != token
        this.info = info
        this.token = token
        if (socket == null || changed) connect()
    }

    @Synchronized
    fun release(tag: String) {
        holders -= tag
        if (holders.isEmpty()) {
            retry?.cancel()
            socket?.close(1000, "bye")
            socket = null
        }
    }

    @Synchronized
    private fun connect() {
        val i = info ?: return
        socket?.cancel()
        val wsScheme = if (i.scheme == "https") "wss" else "ws"
        val port = if ((i.scheme == "https" && i.port == 443) || (i.scheme == "http" && i.port == 80)) "" else ":${i.port}"
        val url = "$wsScheme://${i.host}$port/app/${i.key}?protocol=7&client=haraan-partner&version=1.0"
        socket = client.newWebSocket(Request.Builder().url(url).build(), Listener())
    }

    @Synchronized
    private fun scheduleReconnect() {
        if (holders.isEmpty()) return
        retry?.cancel()
        val wait = (1000L shl attempt.coerceAtMost(5)).coerceAtMost(30_000L)
        attempt++
        retry = scope.launch { delay(wait); connect() }
    }

    private class Listener : WebSocketListener() {
        override fun onMessage(webSocket: WebSocket, text: String) {
            runCatching {
                val frame = JSONObject(text)
                val data = frame.opt("data").let { d -> if (d is String) runCatching { JSONObject(d) }.getOrNull() else d as? JSONObject }
                when (frame.optString("event")) {
                    "pusher:connection_established" -> {
                        attempt = 0
                        val socketId = data?.optString("socket_id") ?: return
                        val i = info ?: return
                        val t = token ?: return
                        scope.launch {
                            runCatching {
                                val auth = PartnerApi().realtimeAuth(t, socketId, i.channel)
                                webSocket.send(
                                    JSONObject().put("event", "pusher:subscribe")
                                        .put("data", JSONObject().put("auth", auth).put("channel", i.channel)).toString(),
                                )
                            }.onFailure { Log.w(TAG, "auth failed: ${it.message}") }
                        }
                    }
                    "pusher:ping" -> webSocket.send("{\"event\":\"pusher:pong\",\"data\":{}}")
                    "partner.updated" -> data?.let { PartnerUpdatesBus.emit(PartnerUpdateItem.from(it)) }
                }
            }
        }

        override fun onFailure(webSocket: WebSocket, t: Throwable, response: Response?) {
            Log.w(TAG, "socket failed: ${t.message}")
            if (webSocket === socket) scheduleReconnect()
        }

        override fun onClosed(webSocket: WebSocket, code: Int, reason: String) {
            if (webSocket === socket && code != 1000) scheduleReconnect()
        }
    }
}
