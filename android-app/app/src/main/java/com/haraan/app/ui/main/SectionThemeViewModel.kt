package com.haraan.app.ui.main

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.haraan.app.data.RealtimeBus
import com.haraan.app.data.theme.SectionThemeRepository
import com.haraan.app.data.theme.ThemeSection
import com.haraan.app.ui.theme.SectionTheme
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.catch
import kotlinx.coroutines.flow.filter
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch

/**
 * Exposes the resolved campaign theme for each home lane. Values are null only until the
 * DataStore cache has been read (a few ms, behind the brand splash); after that they are
 * always a paintable theme — the campaign, or the lane's default palette.
 */
class SectionThemeViewModel(application: Application) : AndroidViewModel(application) {
    private val repository = SectionThemeRepository.get(application)

    val events: StateFlow<SectionTheme?> = themeFor(ThemeSection.Events)
    val pulse: StateFlow<SectionTheme?> = themeFor(ThemeSection.Pulse)

    init {
        // No fetch here: the screen's on-resume refresh runs on first composition too.
        // /control saved a campaign, or the socket just (re)connected and may have missed that
        // signal while the app was frozen in the background: skip the freshness window.
        viewModelScope.launch {
            RealtimeBus.updates
                .filter { it == REALTIME_DOMAIN || it == RealtimeBus.RECONNECTED }
                .collect { repository.revalidate(force = true) }
        }
    }

    /**
     * [force] = false no-ops while the cache is fresh (the periodic tick). Pass true when the
     * user comes back to the app: a campaign published while they were away must show on
     * return, not up to [SectionThemeRepository.FRESH_FOR_MS] later.
     */
    suspend fun revalidate(force: Boolean = false) {
        repository.revalidate(force)
    }

    private fun themeFor(section: ThemeSection): StateFlow<SectionTheme?> {
        val fallback = SectionTheme.defaultFor(section)
        return repository.activeTheme(section)
            .map { SectionTheme.resolve(it, fallback) }
            .catch { emit(fallback) }
            .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), null)
    }

    private companion object {
        /** Matches `SectionTheme::$contentDomain` on the backend. */
        const val REALTIME_DOMAIN = "themes"
    }
}
