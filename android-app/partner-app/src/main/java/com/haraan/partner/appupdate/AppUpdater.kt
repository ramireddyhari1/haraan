package com.haraan.partner.appupdate

import android.Manifest
import android.app.Activity
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.pm.PackageInstaller
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.SystemClock
import android.provider.Settings
import androidx.core.content.ContextCompat
import androidx.core.content.IntentCompat
import androidx.core.content.pm.PackageInfoCompat
import com.haraan.partner.BuildConfig
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.ensureActive
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.File
import java.io.FileOutputStream
import java.io.IOException
import java.net.HttpURLConnection
import java.net.SocketTimeoutException
import java.net.URL
import java.net.URLEncoder
import java.net.UnknownHostException

// The in-app update flow. The same file lives in both Android apps; only these three differ.
private const val APP = "partner"
private const val LABEL = "Haraan Partner"
private val BASE_URL get() = com.haraan.partner.ApiConfig.BASE_URL

enum class UpdatePhase { OFFER, DOWNLOADING, CHECKING, PERMISSION, READY, INSTALLING, RESTART, FAILED }

/** What /control → App updates published, as GET /api/app/update returns it. */
data class UpdateInfo(
    val required: Boolean,
    val latestVersion: String?,
    val message: String,
    val whatsNew: List<String>,
    val apkUrl: String,
    val storeUrl: String?,
    val remindAfterHours: Int,
)

data class UpdateState(
    val info: UpdateInfo,
    val phase: UpdatePhase = UpdatePhase.OFFER,
    val bytes: Long = 0,
    /** -1 while the size is unknown. */
    val total: Long = -1,
    val error: String? = null,
    /** False when trying again can't help (a differently signed APK): offer the download page. */
    val canRetry: Boolean = true,
    /** An optional update the person tucked away mid-download; it comes back to install. */
    val minimized: Boolean = false,
) {
    val fraction: Float? get() = if (total > 0) (bytes.toFloat() / total).coerceIn(0f, 1f) else null
}

/**
 * How an app store delivers the update instead of our own download — Google Play's in-app
 * updates for a phone that installed from Play. Null for a sideloaded app.
 */
interface StoreTransport {
    fun handles(context: Context): Boolean
    fun start(activity: Activity, onState: (UpdatePhase, Long, Long, String?) -> Unit, fallback: () -> Unit)
    fun resume(activity: Activity, onState: (UpdatePhase, Long, Long, String?) -> Unit)
    fun complete()
}

/**
 * Checks GET /api/app/update when the app comes to the front, downloads the new APK itself
 * (resuming a cut-off download), proves the file is a newer build of THIS app signed with the
 * same key, and hands it to Android's PackageInstaller. Android still shows its own "Update
 * this app?" confirmation — an app can't install over itself silently, and shouldn't.
 */
object AppUpdater {
    private const val CHECK_EVERY_MS = 10 * 60_000L
    private const val RETRY_AFTER_MS = 30_000L
    private const val PREFS = "app_update"

    private val _state = MutableStateFlow<UpdateState?>(null)
    val state: StateFlow<UpdateState?> = _state

    /** Set by an app that can update through a store (the Haraan app on Play installs). */
    var store: StoreTransport? = null

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)
    private var checkJob: Job? = null
    private var workJob: Job? = null
    private var lastCheckAt = 0L
    private var receiverRegistered = false
    private var apk: File? = null

    @Volatile private var appContext: Context? = null

    /** Look for an update. Throttled unless [force]; never interrupts one in progress. */
    fun check(context: Context, force: Boolean = false, isRetry: Boolean = false) {
        val app = context.applicationContext
        appContext = app
        val now = SystemClock.elapsedRealtime()
        if (!force && lastCheckAt != 0L && now - lastCheckAt < CHECK_EVERY_MS) return
        if (checkJob?.isActive == true || workJob?.isActive == true) return
        lastCheckAt = now
        checkJob = scope.launch {
            val result = runCatching { fetch() }
            if (result.isFailure) {
                // Offline or the server is down: keep whatever is showing.
                android.util.Log.w("AppUpdater", "update check failed", result.exceptionOrNull())
                // Try again on the next return to the app, and once shortly in case it stays open.
                // Only once: a phone that stays offline isn't polled.
                lastCheckAt = 0L
                if (!isRetry) {
                    scope.launch {
                        kotlinx.coroutines.delay(RETRY_AFTER_MS)
                        if (lastCheckAt == 0L) check(app, isRetry = true)
                    }
                }
                return@launch
            }
            val info = result.getOrNull()
            val current = _state.value
            if (info == null) {
                // The admin withdrew it: drop a sheet that hasn't started, never one mid-install.
                if (current != null && current.phase in IDLE_PHASES) _state.value = null
                return@launch
            }
            if (current != null && current.phase !in IDLE_PHASES) {
                _state.value = current.copy(info = info)
                return@launch
            }
            if (!info.required && snoozed(app, info.latestVersion)) return@launch
            val cached = withContext(Dispatchers.IO) { cachedApk(app, info)?.takeIf { verify(app, it) == null } }
            apk = cached
            _state.value = UpdateState(info, phase = if (cached != null) UpdatePhase.READY else UpdatePhase.OFFER)
        }
    }

    /** From the HTTP layer on a 426: the server already refuses this version. */
    fun requestCheck() {
        val app = appContext ?: return
        scope.launch { check(app, force = true) }
    }

    fun later(context: Context) {
        val s = _state.value ?: return
        if (s.info.required) return
        if (s.phase == UpdatePhase.DOWNLOADING || s.phase == UpdatePhase.CHECKING) {
            _state.value = s.copy(minimized = true)
            return
        }
        prefs(context).edit()
            .putString("snooze_version", s.info.latestVersion.orEmpty())
            .putLong("snooze_until", System.currentTimeMillis() + s.info.remindAfterHours * 3_600_000L)
            .apply()
        _state.value = null
    }

    fun start(activity: Activity) {
        val s = _state.value ?: return
        val app = activity.applicationContext
        val store = store
        if (store != null && store.handles(app)) {
            store.start(activity, ::applyStore) { openLink(app, s.info.storeUrl ?: s.info.apkUrl) }
            return
        }
        if (!canSelfInstall(app)) {
            openLink(app, s.info.apkUrl)
            return
        }
        if (s.phase == UpdatePhase.READY && apk?.exists() == true) {
            install(app)
            return
        }
        workJob?.cancel()
        workJob = scope.launch {
            try {
                _state.update { it?.copy(phase = UpdatePhase.DOWNLOADING, bytes = 0, total = -1, error = null, canRetry = true) }
                val file = download(app, s.info) { done, total -> _state.update { it?.copy(bytes = done, total = total) } }
                _state.update { it?.copy(phase = UpdatePhase.CHECKING) }
                val problem = withContext(Dispatchers.IO) { verify(app, file) }
                if (problem != null) {
                    file.delete()
                    fail(problem.message, problem.retry)
                    return@launch
                }
                apk = file
                val tucked = _state.value?.minimized == true
                _state.update { it?.copy(phase = UpdatePhase.READY, bytes = file.length(), total = file.length(), minimized = false) }
                // Someone who hid the sheet gets it back with an Install button, not a system
                // dialog out of nowhere.
                if (!tucked) install(app)
            } catch (e: CancellationException) {
                throw e
            } catch (e: Exception) {
                fail(
                    when (e) {
                        is UnknownHostException, is SocketTimeoutException ->
                            "The connection dropped. Try again and the download carries on from where it stopped."
                        else -> "The download didn’t finish (${e.message ?: e.javaClass.simpleName}). Try again."
                    },
                )
            }
        }
    }

    fun install(context: Context) {
        val app = context.applicationContext
        val file = apk ?: return
        if (Build.VERSION.SDK_INT >= 26 && !app.packageManager.canRequestPackageInstalls()) {
            _state.update { it?.copy(phase = UpdatePhase.PERMISSION, error = null) }
            return
        }
        _state.update { it?.copy(phase = UpdatePhase.INSTALLING, error = null) }
        ensureReceiver(app)
        scope.launch {
            try {
                withContext(Dispatchers.IO) { commit(app, file) }
            } catch (e: CancellationException) {
                throw e
            } catch (e: Exception) {
                fail("Android couldn’t start the install (${e.message ?: e.javaClass.simpleName}).")
            }
        }
    }

    /** Android 8+: the one-time "Allow from this source" switch for this app. */
    fun openInstallPermission(context: Context) {
        val intent = Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES, Uri.parse("package:${context.packageName}"))
        runCatching { context.startActivity(intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)) }
    }

    fun openDownloadPage(context: Context) {
        val s = _state.value ?: return
        openLink(context.applicationContext, s.info.apkUrl)
    }

    /** Play's update is downloaded; restarting the app installs it. */
    fun restart() {
        store?.complete()
    }

    fun onResume(activity: Activity) {
        val app = activity.applicationContext
        val s = _state.value
        if (s?.phase == UpdatePhase.PERMISSION && (Build.VERSION.SDK_INT < 26 || app.packageManager.canRequestPackageInstalls())) {
            install(app)
        }
        store?.takeIf { it.handles(app) }?.resume(activity, ::applyStore)
        check(app)
    }

    private fun applyStore(phase: UpdatePhase, bytes: Long, total: Long, error: String?) {
        _state.update { it?.copy(phase = phase, bytes = bytes, total = total, error = error, canRetry = true) }
    }

    private fun fail(message: String, retry: Boolean = true) {
        _state.update { it?.copy(phase = UpdatePhase.FAILED, error = message, canRetry = retry, minimized = false) }
    }

    // ── Server ──────────────────────────────────────────────────────────────────────

    private suspend fun fetch(): UpdateInfo? = withContext(Dispatchers.IO) {
        val version = URLEncoder.encode(BuildConfig.VERSION_NAME, "UTF-8")
        val conn = (URL("$BASE_URL/api/app/update?app=$APP&version=$version").openConnection() as HttpURLConnection).apply {
            connectTimeout = 10_000
            readTimeout = 10_000
            setRequestProperty("Accept", "application/json")
        }
        try {
            val code = conn.responseCode
            if (code !in 200..299) throw IOException("HTTP $code")
            val root = JSONObject(conn.inputStream.bufferedReader().use { it.readText() })
            if (!root.optBoolean("available")) return@withContext null
            fun text(key: String) = root.optString(key).takeIf { it.isNotBlank() && it != "null" }
            val notes = root.optJSONArray("whats_new")
            UpdateInfo(
                required = root.optBoolean("required"),
                latestVersion = text("latest_version"),
                message = text("message").orEmpty(),
                whatsNew = if (notes == null) emptyList() else (0 until notes.length()).map { notes.optString(it) }.filter { it.isNotBlank() },
                apkUrl = text("apk_url").orEmpty(),
                storeUrl = text("store_url"),
                remindAfterHours = root.optInt("remind_after_hours", 24).coerceAtLeast(1),
            )
        } finally {
            conn.disconnect()
        }
    }

    // ── Download ────────────────────────────────────────────────────────────────────

    private fun updateDir(context: Context) = File(context.cacheDir, "app-update").apply { mkdirs() }

    private fun apkName(info: UpdateInfo) = "haraan-$APP-${info.latestVersion ?: "latest"}.apk"

    private fun cachedApk(context: Context, info: UpdateInfo): File? =
        File(updateDir(context), apkName(info)).takeIf { it.isFile && it.length() > 0 }

    private suspend fun download(context: Context, info: UpdateInfo, onProgress: (Long, Long) -> Unit): File =
        withContext(Dispatchers.IO) {
            if (!info.apkUrl.startsWith("https://") && !BuildConfig.DEBUG) throw IOException("the update link is not https")
            val dir = updateDir(context)
            val name = apkName(info)
            val target = File(dir, name)
            val part = File(dir, "$name.part")
            // Old versions' files go; a half-finished file for THIS version is resumed.
            dir.listFiles()?.filter { it.name != name && it.name != part.name }?.forEach { it.delete() }
            if (target.isFile && target.length() > 0) return@withContext target

            val have = if (part.isFile) part.length() else 0L
            val conn = (URL(info.apkUrl).openConnection() as HttpURLConnection).apply {
                connectTimeout = 15_000
                readTimeout = 30_000
                instanceFollowRedirects = true
                if (have > 0) setRequestProperty("Range", "bytes=$have-")
            }
            try {
                val code = conn.responseCode
                if (code !in 200..299) throw IOException("HTTP $code")
                val resumed = code == 206
                val start = if (resumed) have else 0L
                val length = conn.contentLengthLong
                val total = if (length > 0) start + length else -1L
                var done = start
                var lastEmit = 0L
                onProgressMain(onProgress, done, total)
                conn.inputStream.use { input ->
                    FileOutputStream(part, resumed).use { out ->
                        val buffer = ByteArray(64 * 1024)
                        while (true) {
                            ensureActive()
                            val n = input.read(buffer)
                            if (n < 0) break
                            out.write(buffer, 0, n)
                            done += n
                            val now = SystemClock.elapsedRealtime()
                            if (now - lastEmit >= 80) {
                                lastEmit = now
                                onProgressMain(onProgress, done, total)
                            }
                        }
                        out.fd.sync()
                    }
                }
                if (total > 0 && done < total) throw IOException("the file ended early")
                onProgressMain(onProgress, done, if (total > 0) total else done)
                if (!part.renameTo(target)) throw IOException("couldn’t save the file")
                target
            } finally {
                conn.disconnect()
            }
        }

    private suspend fun onProgressMain(onProgress: (Long, Long) -> Unit, done: Long, total: Long) =
        withContext(Dispatchers.Main) { onProgress(done, total) }

    // ── Verify ──────────────────────────────────────────────────────────────────────

    private class Problem(val message: String, val retry: Boolean = true)

    /** Null when [file] is a newer build of this app signed like this one. */
    private fun verify(context: Context, file: File): Problem? {
        val pm = context.packageManager
        @Suppress("DEPRECATION")
        val flags = if (Build.VERSION.SDK_INT >= 28) PackageManager.GET_SIGNING_CERTIFICATES else PackageManager.GET_SIGNATURES
        val archive = pm.getPackageArchiveInfo(file.path, flags)
            ?: return Problem("The downloaded file is damaged. Try again.")
        if (archive.packageName != context.packageName) {
            return Problem("The file at the update link isn’t the $LABEL app.", retry = false)
        }
        if (PackageInfoCompat.getLongVersionCode(archive) <= BuildConfig.VERSION_CODE.toLong()) {
            return Problem("The file at the update link is version ${archive.versionName}, which isn’t newer than this app.", retry = false)
        }
        val theirs = signers(archive)
        val ours = runCatching { signers(pm.getPackageInfo(context.packageName, flags)) }.getOrDefault(emptySet())
        if (theirs.isNotEmpty() && ours.isNotEmpty() && theirs != ours) {
            return Problem(
                "This phone has a copy of $LABEL from a different build, so Android won’t update it in place. " +
                    "Uninstall it, then install the new one from the download page.",
                retry = false,
            )
        }
        return null
    }

    @Suppress("DEPRECATION")
    private fun signers(info: android.content.pm.PackageInfo): Set<String> =
        if (Build.VERSION.SDK_INT >= 28) {
            info.signingInfo?.apkContentsSigners?.map { it.toCharsString() }?.toSet().orEmpty()
        } else {
            info.signatures?.map { it.toCharsString() }?.toSet().orEmpty()
        }

    // ── Install ─────────────────────────────────────────────────────────────────────

    private fun statusAction(context: Context) = "${context.packageName}.APP_UPDATE_STATUS"

    private fun commit(context: Context, file: File) {
        val installer = context.packageManager.packageInstaller
        val params = PackageInstaller.SessionParams(PackageInstaller.SessionParams.MODE_FULL_INSTALL).apply {
            setAppPackageName(context.packageName)
            setSize(file.length())
            if (Build.VERSION.SDK_INT >= 31) setRequireUserAction(PackageInstaller.SessionParams.USER_ACTION_NOT_REQUIRED)
        }
        val id = installer.createSession(params)
        installer.openSession(id).use { session ->
            session.openWrite("base.apk", 0, file.length()).use { out ->
                file.inputStream().use { it.copyTo(out, 256 * 1024) }
                session.fsync(out)
            }
            // Explicit (package set) so it is allowed to be mutable on Android 14+; the installer
            // fills in the status extras.
            val intent = Intent(statusAction(context)).setPackage(context.packageName)
            val flags = PendingIntent.FLAG_UPDATE_CURRENT or (if (Build.VERSION.SDK_INT >= 31) PendingIntent.FLAG_MUTABLE else 0)
            session.commit(PendingIntent.getBroadcast(context, id, intent, flags).intentSender)
        }
    }

    private fun ensureReceiver(context: Context) {
        if (receiverRegistered) return
        // Not exported: only this app's own PendingIntent can reach it, so nobody else can
        // hand us an Intent to launch.
        ContextCompat.registerReceiver(context, statusReceiver, IntentFilter(statusAction(context)), ContextCompat.RECEIVER_NOT_EXPORTED)
        receiverRegistered = true
    }

    private val statusReceiver = object : BroadcastReceiver() {
        override fun onReceive(context: Context, intent: Intent) {
            val message = intent.getStringExtra(PackageInstaller.EXTRA_STATUS_MESSAGE)
            when (intent.getIntExtra(PackageInstaller.EXTRA_STATUS, PackageInstaller.STATUS_FAILURE)) {
                PackageInstaller.STATUS_PENDING_USER_ACTION -> {
                    val confirm = IntentCompat.getParcelableExtra(intent, Intent.EXTRA_INTENT, Intent::class.java) ?: return
                    runCatching { context.startActivity(confirm.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)) }
                        .onFailure { _state.update { it?.copy(phase = UpdatePhase.READY) } }
                }
                PackageInstaller.STATUS_SUCCESS -> _state.value = null
                // "Cancel" on Android's dialog: the file is kept, one tap installs it.
                PackageInstaller.STATUS_FAILURE_ABORTED -> _state.update { it?.copy(phase = UpdatePhase.READY) }
                PackageInstaller.STATUS_FAILURE_CONFLICT, PackageInstaller.STATUS_FAILURE_INCOMPATIBLE -> fail(
                    "Android refused to install over this copy of $LABEL. Uninstall it, then install the new one from the download page.",
                    retry = false,
                )
                PackageInstaller.STATUS_FAILURE_STORAGE -> fail("The phone is out of space. Free up some room and try again.")
                PackageInstaller.STATUS_FAILURE_BLOCKED -> fail("This phone blocks installs from apps. Use the download page instead.", retry = false)
                else -> fail("Android stopped the install" + (message?.let { ": $it" } ?: "."))
            }
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────────

    private val IDLE_PHASES = setOf(UpdatePhase.OFFER, UpdatePhase.FAILED)

    private fun prefs(context: Context) = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    private fun snoozed(context: Context, version: String?): Boolean {
        val p = prefs(context)
        return p.getString("snooze_version", null) == version.orEmpty() && p.getLong("snooze_until", 0) > System.currentTimeMillis()
    }

    /** True when this build may install APKs (the permission is only declared where it's allowed). */
    private fun canSelfInstall(context: Context): Boolean = runCatching {
        @Suppress("DEPRECATION")
        val info = context.packageManager.getPackageInfo(context.packageName, PackageManager.GET_PERMISSIONS)
        info.requestedPermissions?.contains(Manifest.permission.REQUEST_INSTALL_PACKAGES) == true
    }.getOrDefault(false)

    private fun openLink(context: Context, url: String) {
        if (url.isBlank()) return
        runCatching { context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)) }
    }

    val currentVersion: String get() = BuildConfig.VERSION_NAME
    val appLabel: String get() = LABEL
}
