package com.haraan.app.commentary

import android.content.Context
import android.util.Log
import com.haraan.app.data.InstallId
import com.haraan.app.data.TokenStore
import io.livekit.android.AudioOptions
import io.livekit.android.AudioType
import io.livekit.android.LiveKit
import io.livekit.android.LiveKitOverrides
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
 * The viewer's side: the speaker icon on match detail. Free for everyone, never autoplays.
 * One match at a time; tapping another match's speaker switches to it. Keeps playing in the
 * background under a media foreground service ([CommentaryListenService]) with a Stop button.
 */
object CommentaryListener {

    sealed interface State {
        data object Idle : State
        data class Connecting(val matchId: String) : State
        data class Playing(
            val matchId: String,
            val session: Long,
            val by: String,
            /** 0..1, the commentator's loudness, so the icon's waves move with the voice. */
            val level: Float = 0f,
        ) : State
        data class Failed(val matchId: String, val message: String) : State
    }

    private const val TAG = "CommentaryListen"

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)
    private val _state = MutableStateFlow<State>(State.Idle)
    val state: StateFlow<State> = _state.asStateFlow()

    private var room: Room? = null
    private var jobs = mutableListOf<Job>()
    private var appContext: Context? = null

    fun matchOf(s: State): String? = when (s) {
        is State.Connecting -> s.matchId
        is State.Playing -> s.matchId
        is State.Failed -> s.matchId
        State.Idle -> null
    }

    /** Tap on the speaker: start listening to [matchId], or stop if it's already playing. */
    fun toggle(context: Context, matchId: String, code: String, title: String) {
        val now = _state.value
        if (matchOf(now) == matchId && (now is State.Playing || now is State.Connecting)) {
            stop()
            return
        }
        stop()
        val app = context.applicationContext
        appContext = app
        _state.value = State.Connecting(matchId)

        scope.launch {
            val token = TokenStore.getSignedInToken(app)
            when (val res = CommentaryApi.listen(matchId, code, InstallId.get(app), token)) {
                is CommentaryApi.Result.No -> _state.value = State.Failed(matchId, res.refused.message)
                is CommentaryApi.Result.Ok -> connect(app, matchId, title, res.value)
            }
        }
    }

    private suspend fun connect(app: Context, matchId: String, title: String, pass: CommentaryApi.ListenPass) {
        // Media audio: the loudspeaker and the music volume, like any match stream — not the
        // earpiece a call would use.
        val lk = LiveKit.create(
            app,
            overrides = LiveKitOverrides(audioOptions = AudioOptions(audioOutputType = AudioType.MediaAudioType())),
        )
        room = lk
        try {
            lk.connect(pass.url, pass.token)
        } catch (e: Exception) {
            Log.w(TAG, "connect failed: ${e.message}")
            release()
            _state.value = State.Failed(matchId, "Couldn’t play the commentary. Try again.")
            return
        }
        if (_state.value !is State.Connecting || matchOf(_state.value) != matchId) {
            release()
            return
        }

        _state.value = State.Playing(matchId, pass.session, pass.by)
        CommentaryListenService.start(app, title, pass.by)

        jobs += scope.launch {
            lk.events.events.collect { event ->
                if (event is RoomEvent.Disconnected) end(matchId, "Commentary ended.")
            }
        }
        jobs += scope.launch {
            while (isActive) {
                delay(120)
                val playing = _state.value as? State.Playing ?: break
                val level = room?.remoteParticipants?.values?.maxOfOrNull { it.audioLevel }?.coerceIn(0f, 1f) ?: 0f
                if (level != playing.level) _state.value = playing.copy(level = level)
            }
        }
    }

    /** The current broadcast's session, for reporting it. */
    fun sessionFor(matchId: String): Long? = (state.value as? State.Playing)?.takeIf { it.matchId == matchId }?.session

    /**
     * Report what's playing on [matchId]. Runs on this object's scope, so closing the sheet
     * doesn't cancel the request. [done] gets true when Haraan has it.
     */
    fun report(context: Context, matchId: String, reason: String, done: (Boolean) -> Unit) {
        val session = sessionFor(matchId) ?: return done(false)
        val app = context.applicationContext
        scope.launch {
            val res = CommentaryApi.report(session, reason, InstallId.get(app), TokenStore.getSignedInToken(app))
            done(res is CommentaryApi.Result.Ok)
        }
    }

    fun stop() {
        val running = jobs
        jobs = mutableListOf()
        appContext?.let { CommentaryListenService.stop(it) }
        release()
        _state.value = State.Idle
        running.forEach { it.cancel() }
    }

    /** The failure line was shown; back to idle. */
    fun acknowledge() {
        if (_state.value is State.Failed) _state.value = State.Idle
    }

    private fun end(matchId: String, message: String) {
        if (matchOf(_state.value) != matchId) return
        stop()
        _state.value = State.Failed(matchId, message)
    }

    private fun release() {
        val r = room ?: return
        room = null
        runCatching { r.disconnect() }
        runCatching { r.release() }
    }
}
