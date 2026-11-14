package com.haraan.app.camera

import android.content.Context
import org.json.JSONArray
import org.json.JSONObject
import java.util.UUID

/**
 * Bowling sessions, kept on this phone only.
 *
 * A session is a name the member gives a practice spell ("Nets — yorkers", "Tuesday spell")
 * and the LBW camera filming it with no match behind it. Each tracked ball's video goes to the
 * Gallery in a folder under that name (see [albumOf]); this store holds the list of sessions
 * and their counts so the Sessions screen can show live ones and history without a server.
 *
 * A session is LIVE while its camera is open: the camera touches it every few seconds, and one
 * not touched for [LIVE_WINDOW_MS] (the phone died, the app was killed) reads as finished.
 */
object BowlingSessionStore {

    enum class Camera(val label: String, val blurb: String) {
        LBW("LBW review", "Behind the bowler's stumps — line, length, speed and the LBW call."),
        SIDE_ON("Bowler analysis", "Side-on to the pitch — release, speed and the ball's flight."),
    }

    data class Session(
        val id: String,
        val name: String,
        val camera: Camera,
        val createdAtMs: Long,
        val lastActiveAtMs: Long,
        val endedAtMs: Long?,
        val balls: Int,
    ) {
        fun isLive(nowMs: Long = System.currentTimeMillis()): Boolean =
            endedAtMs == null && nowMs - lastActiveAtMs <= LIVE_WINDOW_MS

        /** The Gallery folder this session's videos are written to. */
        val album: String get() = albumOf(name, createdAtMs)
    }

    const val LIVE_WINDOW_MS = 90_000L
    const val MAX_NAME = 40

    /** Root Gallery folder, under Movies. */
    const val ROOT_ALBUM = "Haraan Sessions"

    private const val PREFS = "bowling_sessions"
    private const val KEY = "sessions"
    private val lock = Any()

    /** A safe folder name for [name]; the date keeps two sessions with one name apart. */
    fun albumOf(name: String, createdAtMs: Long): String {
        val clean = name.replace(Regex("[^A-Za-z0-9 _.-]"), "").trim().replace(Regex("\\s+"), " ").take(MAX_NAME)
        val day = java.text.SimpleDateFormat("yyyy-MM-dd HHmm", java.util.Locale.US).format(java.util.Date(createdAtMs))
        return "${clean.ifBlank { "Session" }} $day"
    }

    fun all(context: Context): List<Session> = synchronized(lock) { read(context) }
        .sortedByDescending { it.createdAtMs }

    fun get(context: Context, id: String): Session? = all(context).firstOrNull { it.id == id }

    fun create(context: Context, name: String, camera: Camera): Session = synchronized(lock) {
        val now = System.currentTimeMillis()
        val session = Session(
            id = UUID.randomUUID().toString(),
            name = name.trim().take(MAX_NAME).ifBlank { "Bowling session" },
            camera = camera,
            createdAtMs = now,
            lastActiveAtMs = now,
            endedAtMs = null,
            balls = 0,
        )
        write(context, read(context) + session)
        session
    }

    /** The camera is still open on this session. Reopening a finished one makes it live again. */
    fun touch(context: Context, id: String) = update(context, id) {
        it.copy(lastActiveAtMs = System.currentTimeMillis(), endedAtMs = null)
    }

    fun addBall(context: Context, id: String) = update(context, id) {
        it.copy(balls = it.balls + 1, lastActiveAtMs = System.currentTimeMillis())
    }

    fun end(context: Context, id: String) = update(context, id) {
        it.copy(endedAtMs = System.currentTimeMillis())
    }

    fun rename(context: Context, id: String, name: String) = update(context, id) {
        it.copy(name = name.trim().take(MAX_NAME).ifBlank { it.name })
    }

    /** Forgets the session. Its videos stay in the Gallery: they are the member's files. */
    fun delete(context: Context, id: String) = synchronized(lock) {
        write(context, read(context).filterNot { it.id == id })
    }

    private fun update(context: Context, id: String, change: (Session) -> Session) = synchronized(lock) {
        val list = read(context)
        if (list.none { it.id == id }) return@synchronized
        write(context, list.map { if (it.id == id) change(it) else it })
    }

    private fun read(context: Context): List<Session> {
        val raw = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getString(KEY, null) ?: return emptyList()
        return runCatching {
            val arr = JSONArray(raw)
            (0 until arr.length()).mapNotNull { i -> fromJson(arr.getJSONObject(i)) }
        }.getOrDefault(emptyList())
    }

    private fun write(context: Context, list: List<Session>) {
        val arr = JSONArray()
        list.forEach { arr.put(toJson(it)) }
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putString(KEY, arr.toString()).apply()
    }

    private fun toJson(s: Session) = JSONObject().apply {
        put("id", s.id)
        put("name", s.name)
        put("camera", s.camera.name)
        put("createdAt", s.createdAtMs)
        put("lastActiveAt", s.lastActiveAtMs)
        if (s.endedAtMs != null) put("endedAt", s.endedAtMs)
        put("balls", s.balls)
    }

    private fun fromJson(o: JSONObject): Session? = runCatching {
        Session(
            id = o.getString("id"),
            name = o.getString("name"),
            camera = runCatching { Camera.valueOf(o.optString("camera")) }.getOrDefault(Camera.LBW),
            createdAtMs = o.getLong("createdAt"),
            lastActiveAtMs = o.optLong("lastActiveAt", o.getLong("createdAt")),
            endedAtMs = if (o.has("endedAt")) o.getLong("endedAt") else null,
            balls = o.optInt("balls", 0),
        )
    }.getOrNull()
}
