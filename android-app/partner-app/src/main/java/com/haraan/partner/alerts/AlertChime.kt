package com.haraan.partner.alerts

import android.content.Context
import android.media.AudioAttributes
import android.media.AudioFormat
import android.media.AudioManager
import android.media.AudioTrack
import android.os.Build
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import kotlin.math.PI
import kotlin.math.exp
import kotlin.math.sin

/**
 * Haraan's booking chime and buzz.
 *
 * The chime is synthesised, not a stock tone: two soft bell notes a fifth apart (E6 then
 * B6), each with a quick attack and a long ring-out, the second a touch louder — it reads
 * as "something good landed", not as an alarm. It plays on the notification stream and
 * stays silent when the phone is on silent or vibrate.
 *
 * The buzz carries its meaning in the count (two knocks), so it reads the same on the
 * cheap rotary motors most desk phones have — see android-haptics notes.
 */
object AlertChime {

    fun play(context: Context) {
        val audio = context.getSystemService(Context.AUDIO_SERVICE) as? AudioManager
        if (audio?.ringerMode == AudioManager.RINGER_MODE_NORMAL) {
            runCatching { tone() }
        }
        buzz(context)
    }

    private fun tone() {
        val rate = 44_100
        val notes = listOf(1318.5 to 0.0, 1975.5 to 0.16) // E6, then B6 160 ms later
        val length = (rate * 1.1).toInt()
        val pcm = ShortArray(length)
        for ((freq, at) in notes) {
            val start = (at * rate).toInt()
            val loud = if (freq > 1500) 0.32 else 0.26
            for (i in start until length) {
                val t = (i - start).toDouble() / rate
                val env = (1 - exp(-t * 400)) * exp(-t * 5.5)
                // A little second harmonic gives it a bell's edge instead of a beep's.
                val s = sin(2 * PI * freq * t) + 0.25 * sin(2 * PI * freq * 2 * t)
                val v = pcm[i] + (s * env * loud * Short.MAX_VALUE).toInt()
                pcm[i] = v.coerceIn(Short.MIN_VALUE.toInt(), Short.MAX_VALUE.toInt()).toShort()
            }
        }
        val track = AudioTrack.Builder()
            .setAudioAttributes(
                AudioAttributes.Builder()
                    .setUsage(AudioAttributes.USAGE_NOTIFICATION_EVENT)
                    .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                    .build(),
            )
            .setAudioFormat(
                AudioFormat.Builder().setSampleRate(rate).setEncoding(AudioFormat.ENCODING_PCM_16BIT)
                    .setChannelMask(AudioFormat.CHANNEL_OUT_MONO).build(),
            )
            .setTransferMode(AudioTrack.MODE_STATIC)
            .setBufferSizeInBytes(pcm.size * 2)
            .build()
        track.write(pcm, 0, pcm.size)
        track.setNotificationMarkerPosition(pcm.size - 1)
        track.setPlaybackPositionUpdateListener(object : AudioTrack.OnPlaybackPositionUpdateListener {
            override fun onMarkerReached(t: AudioTrack) { t.release() }
            override fun onPeriodicNotification(t: AudioTrack) {}
        })
        track.play()
    }

    private fun buzz(context: Context) {
        val vibrator: Vibrator? = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            (context.getSystemService(Context.VIBRATOR_MANAGER_SERVICE) as? VibratorManager)?.defaultVibrator
        } else {
            @Suppress("DEPRECATION") context.getSystemService(Context.VIBRATOR_SERVICE) as? Vibrator
        }
        if (vibrator == null || !vibrator.hasVibrator()) return
        runCatching {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                // Two knocks: long-short, matched to the chime's two notes.
                vibrator.vibrate(VibrationEffect.createWaveform(longArrayOf(0, 60, 110, 35), -1))
            } else {
                @Suppress("DEPRECATION") vibrator.vibrate(longArrayOf(0, 60, 110, 35), -1)
            }
        }
    }
}
