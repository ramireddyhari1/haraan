package com.haraan.app.ui.social

import android.content.Context
import android.media.AudioAttributes
import android.media.MediaPlayer
import android.media.MediaRecorder
import android.os.Build
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Pause
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.Feel
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import java.io.File

/** "0:07", "1:42". */
internal fun voiceClock(ms: Int): String {
    val s = (ms.coerceAtLeast(0) + 500) / 1000
    return "${s / 60}:${(s % 60).toString().padStart(2, '0')}"
}

/**
 * One voice note playing at a time, app-wide — starting another stops the first, the way
 * every messenger behaves. Streams straight from the URL; nothing is saved to the phone.
 */
object ChatAudioPlayer {
    data class State(
        val url: String? = null,
        val playing: Boolean = false,
        val preparing: Boolean = false,
        val positionMs: Int = 0,
        val durationMs: Int = 0,
    )

    private val _state = MutableStateFlow(State())
    val state: StateFlow<State> = _state
    private var player: MediaPlayer? = null

    fun toggle(url: String) {
        val s = _state.value
        val p = player
        if (s.url == url && p != null && !s.preparing) {
            if (p.isPlaying) {
                p.pause()
                _state.value = s.copy(playing = false)
            } else {
                p.start()
                _state.value = s.copy(playing = true)
            }
            return
        }
        stop()
        _state.value = State(url = url, preparing = true)
        val mp = MediaPlayer()
        player = mp
        try {
            mp.setAudioAttributes(
                AudioAttributes.Builder()
                    .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH)
                    .setUsage(AudioAttributes.USAGE_MEDIA)
                    .build()
            )
            mp.setDataSource(url)
            mp.setOnPreparedListener {
                if (player !== it) return@setOnPreparedListener
                it.start()
                _state.value = _state.value.copy(preparing = false, playing = true, durationMs = it.duration)
            }
            mp.setOnCompletionListener {
                if (player !== it) return@setOnCompletionListener
                _state.value = _state.value.copy(playing = false, positionMs = 0)
                it.seekTo(0)
            }
            mp.setOnErrorListener { it, _, _ ->
                if (player === it) stop()
                true
            }
            mp.prepareAsync()
        } catch (_: Exception) {
            stop()
        }
    }

    /** Called by the bubble while it's on screen, to move the bar. */
    fun tick() {
        val p = player ?: return
        val s = _state.value
        if (s.playing) runCatching { _state.value = s.copy(positionMs = p.currentPosition) }
    }

    fun stop() {
        player?.let { runCatching { it.reset(); it.release() } }
        player = null
        _state.value = State()
    }
}

/** The bubble's player: play/pause, a progress bar, and the length. */
@Composable
internal fun VoiceNote(url: String?, durationMs: Int, mine: Boolean, pending: Boolean = false) {
    val st by ChatAudioPlayer.state.collectAsState()
    val active = url != null && st.url == url
    if (active && st.playing) {
        LaunchedEffect(url) {
            while (true) {
                ChatAudioPlayer.tick()
                delay(120)
            }
        }
    }
    val total = if (active && st.durationMs > 0) st.durationMs else durationMs
    val progress = if (active && total > 0) (st.positionMs.toFloat() / total).coerceIn(0f, 1f) else 0f
    val fg = if (mine) Color.White else HaraanColors.EventsBlue
    val track = if (mine) Color.White.copy(alpha = 0.35f) else HaraanColors.EventsBlue.copy(alpha = 0.18f)

    Row(Modifier.widthIn(min = 190.dp, max = 240.dp), verticalAlignment = Alignment.CenterVertically) {
        Box(
            Modifier
                .size(36.dp)
                .clip(CircleShape)
                .background(if (mine) Color.White.copy(alpha = 0.22f) else HaraanColors.EventsBlue.copy(alpha = 0.12f))
                .then(if (url != null && !pending) Modifier.pressable(haptic = Feel.SELECT) { ChatAudioPlayer.toggle(url) } else Modifier),
            contentAlignment = Alignment.Center,
        ) {
            when {
                active && st.preparing -> CircularProgressIndicator(color = fg, strokeWidth = 2.dp, modifier = Modifier.size(16.dp))
                active && st.playing -> Icon(Icons.Filled.Pause, "Pause", tint = fg, modifier = Modifier.size(20.dp))
                else -> Icon(Icons.Filled.PlayArrow, "Play voice message", tint = fg, modifier = Modifier.size(22.dp))
            }
        }
        Spacer(Modifier.width(10.dp))
        Column(Modifier.weight(1f)) {
            Box(Modifier.fillMaxWidth().height(4.dp).clip(RoundedCornerShape(2.dp)).background(track)) {
                Box(Modifier.fillMaxWidth(progress).height(4.dp).clip(RoundedCornerShape(2.dp)).background(fg))
            }
            Spacer(Modifier.height(5.dp))
            Text(
                if (active && (st.playing || st.positionMs > 0)) voiceClock(st.positionMs) else voiceClock(total),
                color = if (mine) Color.White.copy(alpha = 0.85f) else HaraanColors.TextSecondary,
                fontSize = 11.sp,
                fontWeight = FontWeight.SemiBold,
            )
        }
    }
}

/** Stop playback when the thread closes — a voice note shouldn't keep talking from nowhere. */
@Composable
internal fun StopVoiceOnLeave() {
    DisposableEffect(Unit) { onDispose { ChatAudioPlayer.stop() } }
}

/**
 * Records one voice note to the app's cache as AAC in an .m4a. Spoken-voice settings (mono,
 * 64 kbps) keep a two-minute note around a megabyte.
 */
class VoiceRecorder(private val context: Context) {
    private var recorder: MediaRecorder? = null
    private var file: File? = null
    private var startedAt = 0L

    val elapsedMs: Int get() = if (recorder == null) 0 else (System.currentTimeMillis() - startedAt).toInt()

    fun start(): Boolean {
        cancel()
        val out = File(context.cacheDir, "voice-${System.currentTimeMillis()}.m4a")
        val r = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) MediaRecorder(context) else @Suppress("DEPRECATION") MediaRecorder()
        return try {
            r.setAudioSource(MediaRecorder.AudioSource.MIC)
            r.setOutputFormat(MediaRecorder.OutputFormat.MPEG_4)
            r.setAudioEncoder(MediaRecorder.AudioEncoder.AAC)
            r.setAudioChannels(1)
            r.setAudioSamplingRate(44_100)
            r.setAudioEncodingBitRate(64_000)
            r.setOutputFile(out.absolutePath)
            r.prepare()
            r.start()
            recorder = r
            file = out
            startedAt = System.currentTimeMillis()
            true
        } catch (_: Exception) {
            runCatching { r.release() }
            out.delete()
            false
        }
    }

    /** Finish: the bytes and the length, or null if it was too short or failed. */
    fun finish(): Pair<ByteArray, Int>? {
        val r = recorder ?: return null
        val f = file
        val ms = elapsedMs
        recorder = null
        file = null
        val ok = runCatching { r.stop() }.isSuccess
        runCatching { r.release() }
        if (!ok || f == null || ms < 700) {
            f?.delete()
            return null
        }
        val bytes = runCatching { f.readBytes() }.getOrNull()
        f.delete()
        return bytes?.let { it to ms }
    }

    fun cancel() {
        recorder?.let { runCatching { it.stop() }; runCatching { it.release() } }
        recorder = null
        file?.delete()
        file = null
    }
}
