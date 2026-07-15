package com.haraan.app.data

import com.haraan.app.BuildConfig

/**
 * Single source of truth for the backend base URL.
 *
 * The value comes from the build flavor (dev / staging / production) via
 * [BuildConfig.API_BASE_URL] — see app/build.gradle.kts. Repositories must read
 * [ApiConfig.BASE_URL] instead of hardcoding hosts like 10.0.2.2 or localhost.
 *
 * dev runs against the local Laravel server reached through
 * `adb reverse tcp:8000 tcp:8000`.
 */
object ApiConfig {
    val BASE_URL: String = BuildConfig.API_BASE_URL.trimEnd('/')

    /**
     * Resolve a server-supplied media path against [BASE_URL].
     *
     * Uploads are stored root-relative ("/storage/avatars/x.jpg"), which Coil cannot
     * fetch on its own — it needs a scheme and a host. Anything already absolute is
     * passed through untouched. Returns null for blank input and for the literal
     * string "null", which JSON encoders occasionally hand us.
     */
    fun mediaUrl(raw: String?): String? {
        val s = raw?.trim().orEmpty()
        if (s.isBlank() || s == "null") return null
        return if (s.startsWith("http")) s else BASE_URL + "/" + s.trimStart('/')
    }
}
