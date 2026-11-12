package com.haraan.app.commentary

import android.content.Context
import android.util.Log
import com.haraan.app.data.TokenStore
import io.livekit.android.LiveKit
import io.livekit.android.events.RoomEvent
import io.livekit.android.room.Room
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch

/**
 * The scorer's side of live commentary: one broadcast at a time, owned by the process rather
 * than a screen, so it keeps going when the scorer locks the phone or flips to WhatsApp. A
 * foreground service ([CommentaryBroadcastService]) holds the mic while it runs.
 *
 * The server decides whether we stay on air. Every heartbeat asks; an answer of "off air"
 * (taken off by /control, by listener reports, the time limit, the match finishing) ends the
 * broadcast here with the server's own words.
 */
object CommentaryBroadcaster {

    sealed interface State {
        data object Idle : State
        data class Starting(val matchId: String) : State
        data class OnAir(
            val matchId: String,
            val session: Long,
            val startedAt: Long,
            val listeners: Int = 0,
            val muted: Boolean = false,
            /** 0..1, the mic's loudness, for the meter. */
            val level: Float = 0f,
        ) : State
        /** The plan doesn't include it: show the Hero sheet. */
        data class Locked(val matchId: String, val message: String) : State
        /** Ended or refused, with a line to show once. */
        data class Ended(val matchId: String, val message: String) : State
    }

    private const val TAG = "Commentary"

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)
    private val _state = MutableStateFlow<State>(State.Idle)
    val state: StateFlow<State> = _state.asStateFlow()

    private var room: Room? = null
    private var jobs = mutableListOf<Job>()
    private var appContext: Context? = null
    private var matchTitle: String = ""

    fun isOnAir(matchId: String): Boolean = (state.value as? State.OnAir)?.matchId == matchId

    /** Go on air for [matchId]. The caller has the RECORD_AUDIO permission already. */
    fun start(context: Context, matchId: String, title: String) {
        val current = _state.value
        if (current is State.Starting || current is State.OnAir) return
        val app = context.applicationContext
        appContext = app
        matchTitle = title
        _state.value = State.Starting(matchId)

        scope.launch {
            val token = TokenStore.getSignedInToken(app)
            if (token == null) {
                _state.value = State.Ended(matchId, "Sign in to commentate.")
                return@launch
            }
            when (val res = CommentaryApi.start(token, matchId)) {
                is CommentaryApi.Result.No -> {
                    _state.value = if (res.refused.code == "upgrade_required") {
                        State.Locked(matchId, res.refused.message)
                    } else {
                        State.Ended(matchId, res.refused.message)
                    }
                }
                is CommentaryApi.Result.Ok -> connect(app, token, matchId, res.value)
            }
        }
    }

    private suspend fun connect(app: Context, token: String, matchId: String, pass: CommentaryApi.OnAir) {
        val lk = LiveKit.create(app)
        room = lk
        try {
            lk.connect(pass.url, pass.token)
            lk.localParticipant.setMicrophoneEnabled(true)
        } catch (e: Exception) {
            Log.w(TAG, "connect failed: ${e.message}")
            release()
            CommentaryApi.stop(token, matchId, pass.session)
            _state.value = State.Ended(matchId, "Couldn’t go on air. Check your connection and try again.")
            return
        }

        sessionToken = token
        sessionId = pass.session
        val startedAt = System.currentTimeMillis()
        _state.value = State.OnAir(matchId, pass.session, startedAt)
        CommentaryBroadcastService.start(app, matchTitle)

        // The server's say on whether we stay on air, and how many are listening.
        jobs += scope.launch {
            var failures = 0
            while (isActive) {
                delay(pass.heartbeatSeconds * 1000L)
                val now = _state.value as? State.OnAir ?: break
                when (val beat = CommentaryApi.heartbeat(token, matchId, pass.session, now.muted)) {
                    is CommentaryApi.Result.Ok -> {
                        failures = 0
                        if (!beat.value.onAir) {
                            finish(matchId, beat.value.message.ifBlank { "You’re off air." }, tellServer = false)
                            break
                        }
                        (_state.value as? State.OnAir)?.let { _state.value = it.copy(listeners = beat.value.listeners) }
                    }
                    // A missed beat or two on ground Wi-Fi is normal; the server only drops
                    // us after 45 s of silence, so give up just before that.
                    is CommentaryApi.Result.No -> if (++failures >= 3) {
                        finish(matchId, "Lost the connection to Haraan, so you went off air.", tellServer = false)
                        break
                    }
                }
            }
        }

        // The meter, and the silence cut-off. Silence only counts once the audio server has
        // ever told us we're speaking, so a phone that never reports levels is never cut off.
        jobs += scope.launch {
            var lastVoiceAt = System.currentTimeMillis()
            var heardOnce = false
            val silenceMs = pass.silenceMinutes * 60_000L
            while (isActive) {
                delay(120)
                val on = _state.value as? State.OnAir ?: break
                val participant = room?.localParticipant ?: break
                val level = if (on.muted) 0f else participant.audioLevel.coerceIn(0f, 1f)
                if (participant.isSpeaking || level > 0.02f) {
                    heardOnce = true
                    lastVoiceAt = System.currentTimeMillis()
                }
                if (on.muted) lastVoiceAt = System.currentTimeMillis()
                if (level != on.level) _state.value = on.copy(level = level)
                if (silenceMs > 0 && heardOnce && System.currentTimeMillis() - lastVoiceAt > silenceMs) {
                    finish(matchId, "Your mic was silent for ${pass.silenceMinutes} min, so you went off air.", tellServer = true, silence = true)
                    break
                }
            }
        }

        jobs += scope.launch {
            lk.events.events.collect { event ->
                if (event is RoomEvent.Disconnected && _state.value is State.OnAir) {
                    finish(matchId, "Commentary ended.", tellServer = true)
                }
            }
        }
    }

    private var sessionToken: String? = null
    private var sessionId: Long = 0

    fun toggleMute() {
        val on = _state.value as? State.OnAir ?: return
        val muted = !on.muted
        _state.value = on.copy(muted = muted, level = 0f)
        scope.launch {
            runCatching { room?.localParticipant?.setMicrophoneEnabled(!muted) }
            sessionToken?.let { CommentaryApi.heartbeat(it, on.matchId, on.session, muted) }
        }
        appContext?.let { CommentaryBroadcastService.refresh(it, matchTitle, muted) }
    }

    /** The scorer tapped End (or the notification's End). */
    fun stop() {
        val on = _state.value as? State.OnAir ?: return
        finish(on.matchId, "", tellServer = true)
    }

    /** The message was shown; go back to idle. */
    fun acknowledge() {
        if (_state.value is State.Ended || _state.value is State.Locked) _state.value = State.Idle
    }

    /**
     * Not suspending on purpose: it is called from inside the jobs it cancels, so telling the
     * server runs in its own coroutine that the cancellation can't reach.
     */
    private fun finish(matchId: String, message: String, tellServer: Boolean, silence: Boolean = false) {
        if (_state.value !is State.OnAir) return
        val token = sessionToken
        val session = sessionId
        sessionToken = null
        sessionId = 0
        _state.value = if (message.isBlank()) State.Idle else State.Ended(matchId, message)
        appContext?.let { CommentaryBroadcastService.stop(it) }
        release()
        if (tellServer && token != null && session > 0) {
            scope.launch { CommentaryApi.stop(token, matchId, session, silence) }
        }
        val running = jobs
        jobs = mutableListOf()
        running.forEach { it.cancel() }
    }

    private fun release() {
        val r = room ?: return
        room = null
        runCatching { r.disconnect() }
        runCatching { r.release() }
    }
}
