package com.example.thanna.data

import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue

/**
 * App-wide snapshot of the latest remote config. Backed by Compose state so any
 * composable reading [config] (or [isEnabled]) recomposes when it's refreshed at
 * launch. Defaults are empty, so before the first fetch the app behaves exactly
 * as it did pre-remote-config.
 */
object RemoteConfigStore {
    var config by mutableStateOf(RemoteConfig())
        private set

    fun update(value: RemoteConfig) {
        config = value
    }

    /** True only when the flag exists and is enabled for this viewer. */
    fun isEnabled(key: String): Boolean = config.features[key] == true

    val theme: AppTheme get() = config.theme
}
