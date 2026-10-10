package com.haraan.app.data.devices

import android.content.Context
import android.os.Build
import com.haraan.app.BuildConfig
import java.io.File
import java.util.UUID

/**
 * Who this phone is, for the plan's device limit (Free 1, Pro 1, Hero 3).
 *
 * The id lives in noBackupFilesDir on purpose. Auto Backup restores SharedPreferences onto a
 * new phone, so an id kept there would make the new phone claim the old one's slot (and both
 * would count as one device). A file the OS never backs up dies with this install — a fresh
 * install is honestly a new device. Not [com.haraan.app.data.InstallId], which is backed up.
 */
object DeviceIdentity {
    const val CLIENT_HEADER = "X-Haraan-Client"
    const val CLIENT = "member-android"

    private const val FILE = "haraan_device_id"

    @Volatile private var cached: String? = null

    /** Set once by HomeActivity, so context-free callers (the sign-in repository) can send headers. */
    @Volatile private var appContext: Context? = null

    fun init(context: Context) {
        appContext = context.applicationContext
    }

    /** [headers] when [init] has run; empty otherwise (the sign-in then simply takes no device). */
    fun headersOrEmpty(): Map<String, String> = appContext?.let(::headers).orEmpty()

    fun id(context: Context): String {
        cached?.let { return it }
        synchronized(this) {
            cached?.let { return it }
            val file = File(context.applicationContext.noBackupFilesDir, FILE)
            val existing = runCatching { file.readText().trim() }.getOrNull()
            val id = if (!existing.isNullOrBlank()) existing else UUID.randomUUID().toString().also {
                runCatching { file.writeText(it) }
            }
            cached = id
            return id
        }
    }

    /** "Realme RMX3393" — what the member sees in their device list. */
    fun name(): String {
        val maker = Build.MANUFACTURER.orEmpty().replaceFirstChar { it.uppercase() }
        val model = Build.MODEL.orEmpty()
        return when {
            model.isBlank() -> maker.ifBlank { "Android phone" }
            model.startsWith(maker, ignoreCase = true) -> model
            else -> "$maker $model"
        }.take(120)
    }

    /** The headers a sign-in or enroll call carries so the server can record this phone. */
    fun headers(context: Context): Map<String, String> = mapOf(
        CLIENT_HEADER to CLIENT,
        "X-Device-Id" to id(context),
        "X-Device-Name" to name(),
        "X-App-Version" to BuildConfig.VERSION_NAME,
    )

    /** True when [token] was minted for a device (carries `sid`). Older sessions don't. */
    fun tokenHasDevice(token: String?): Boolean {
        if (token.isNullOrBlank()) return false
        return runCatching {
            val payload = token.split(".")[1]
            val json = String(
                android.util.Base64.decode(
                    payload,
                    android.util.Base64.URL_SAFE or android.util.Base64.NO_PADDING or android.util.Base64.NO_WRAP,
                ),
            )
            org.json.JSONObject(json).optString("sid").isNotBlank()
        }.getOrDefault(false)
    }
}
