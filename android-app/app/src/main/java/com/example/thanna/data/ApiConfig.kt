package com.example.thanna.data

import com.example.thanna.BuildConfig

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
}
