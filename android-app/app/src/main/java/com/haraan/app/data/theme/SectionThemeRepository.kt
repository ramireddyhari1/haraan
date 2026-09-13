package com.haraan.app.data.theme

import android.content.Context
import android.util.Log
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.emptyPreferences
import androidx.datastore.preferences.core.longPreferencesKey
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.haraan.app.data.net.HaraanHttp
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.catch
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.transformLatest
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.serialization.builtins.ListSerializer
import kotlinx.serialization.json.Json
import java.io.IOException

private val Context.sectionThemeStore: DataStore<Preferences> by preferencesDataStore(name = "section_themes")

/**
 * Campaign themes for the Events and Pulse lanes, stale-while-revalidate over DataStore.
 *
 *  - **Stale:** [activeTheme] reads the last payload from disk, so a campaign is on screen
 *    from the first frame of a cold start — offline included — with no network wait.
 *  - **Revalidate:** [revalidate] fetches in the background and rewrites the cache; the
 *    DataStore flow then pushes the fresh payload to every collector. A failed fetch leaves
 *    the cache alone, so a network blip never strips a live campaign.
 *
 * The validity window is enforced HERE, on the device clock corrected by the server's
 * clock: a campaign switches on and off on the minute even if no fetch ever happens again,
 * and an ended campaign can't linger because the cache is old.
 */
class SectionThemeRepository internal constructor(
    private val store: DataStore<Preferences>,
    private val api: SectionThemeApi,
    private val json: Json,
    private val clock: () -> Long = System::currentTimeMillis,
    private val freshForMs: Long = FRESH_FOR_MS,
    /** android.util.Log by default; replaceable so the logic runs in plain JVM tests. */
    private val logWarning: (String, Throwable) -> Unit = { message, e -> Log.w(TAG, message, e) },
) {
    internal data class Snapshot(
        val themes: List<RemoteThemeConfig>,
        val fetchedAtMs: Long,
        /** serverClock − deviceClock at fetch time. */
        val clockOffsetMs: Long,
    )

    private val refreshLock = Mutex()
    private val listSerializer = ListSerializer(RemoteThemeConfig.serializer())

    internal val snapshot: Flow<Snapshot?> = store.data
        .catch { e -> if (e is IOException) emit(emptyPreferences()) else throw e }
        .map(::decode)
        .distinctUntilChanged()

    /**
     * The campaign that should be showing on [section] right now, or null for the built-in
     * palette. Re-emits by itself when a window opens or closes, without a refetch.
     */
    @OptIn(ExperimentalCoroutinesApi::class)
    fun activeTheme(section: ThemeSection): Flow<RemoteThemeConfig?> = snapshot
        .transformLatest { snap ->
            val candidates = snap?.themes.orEmpty().filter { it.section == section.key }
            if (snap == null || candidates.isEmpty()) {
                emit(null)
                return@transformLatest
            }
            while (true) {
                val nowSec = (clock() + snap.clockOffsetMs) / 1000
                emit(pickActive(candidates, nowSec))
                val next = nextBoundary(candidates, nowSec) ?: break
                // Capped: coroutine delay runs on uptime, which stops while the device sleeps,
                // so a long single sleep could overshoot a boundary. Re-reading the wall clock
                // every minute bounds that error; distinctUntilChanged absorbs the no-op ticks.
                delay(((next - nowSec) * 1000).coerceIn(MIN_TICK_MS, MAX_TICK_MS))
            }
        }
        .distinctUntilChanged()

    /**
     * Refetch unless the cache is younger than [freshForMs]. [force] skips that check — for a
     * realtime "themes changed" push. Concurrent callers share one request. Returns true when
     * the cache is fresh afterwards; never throws for network or decode failures.
     */
    suspend fun revalidate(force: Boolean = false): Boolean = refreshLock.withLock {
        if (!force) {
            val cached = snapshot.first()
            if (cached != null && clock() - cached.fetchedAtMs in 0 until freshForMs) return@withLock true
        }
        try {
            val response = api.sectionThemes()
            val now = clock()
            store.edit { prefs ->
                prefs[KEY_PAYLOAD] = json.encodeToString(listSerializer, response.themes)
                prefs[KEY_FETCHED_AT] = now
                prefs[KEY_CLOCK_OFFSET] = response.serverTime * 1000 - now
            }
            true
        } catch (e: CancellationException) {
            throw e
        } catch (e: Exception) {
            logWarning("section themes revalidation failed; keeping cache", e)
            false
        }
    }

    private fun decode(prefs: Preferences): Snapshot? {
        val payload = prefs[KEY_PAYLOAD] ?: return null
        val themes = try {
            json.decodeFromString(listSerializer, payload)
        } catch (e: Exception) {
            // A cache written by a build with a different model. Show defaults; the next
            // revalidation overwrites it.
            logWarning("discarding unreadable section theme cache", e)
            return null
        }
        return Snapshot(themes, prefs[KEY_FETCHED_AT] ?: 0L, prefs[KEY_CLOCK_OFFSET] ?: 0L)
    }

    companion object {
        private const val TAG = "SectionThemes"

        /** Revalidation is cheap but not free; realtime pushes cover urgent changes. */
        const val FRESH_FOR_MS = 10 * 60 * 1000L
        private const val MIN_TICK_MS = 1_000L
        private const val MAX_TICK_MS = 60_000L

        private val KEY_PAYLOAD = stringPreferencesKey("payload")
        private val KEY_FETCHED_AT = longPreferencesKey("fetched_at_ms")
        private val KEY_CLOCK_OFFSET = longPreferencesKey("clock_offset_ms")

        @Volatile
        private var instance: SectionThemeRepository? = null

        fun get(context: Context): SectionThemeRepository =
            instance ?: synchronized(this) {
                instance ?: SectionThemeRepository(
                    store = context.applicationContext.sectionThemeStore,
                    api = HaraanHttp.create(SectionThemeApi::class.java),
                    json = HaraanHttp.json,
                ).also { instance = it }
            }

        /** Highest priority wins; on a tie, the campaign that started most recently. */
        internal fun pickActive(themes: List<RemoteThemeConfig>, nowSec: Long): RemoteThemeConfig? =
            themes.filter { it.isValidAt(nowSec) }
                .maxWithOrNull(compareBy<RemoteThemeConfig> { it.priority }.thenBy { it.validFrom })

        /** The next instant any candidate starts or ends, or null when nothing else will change. */
        internal fun nextBoundary(themes: List<RemoteThemeConfig>, nowSec: Long): Long? =
            themes.flatMap { listOf(it.validFrom, it.validUntil) }
                .filter { it > nowSec }
                .minOrNull()
    }
}
