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
        viewModelScope.launch { repository.revalidate() }
        // /control saved a campaign: skip the freshness window and fetch now.
        viewModelScope.launch {
            RealtimeBus.updates.filter { it == REALTIME_DOMAIN }.collect { repository.revalidate(force = true) }
        }
    }

    /** Cheap to call often (screen resume, periodic tick) — no-ops while the cache is fresh. */
    suspend fun revalidate() {
        repository.revalidate()
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
