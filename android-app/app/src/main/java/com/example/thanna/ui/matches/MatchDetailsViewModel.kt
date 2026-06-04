package com.example.thanna.ui.matches

import androidx.lifecycle.ViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

class MatchDetailsViewModel : ViewModel() {

    private val _uiState = MutableStateFlow<MatchScreenState>(MatchScreenState.Loading)
    val uiState: StateFlow<MatchScreenState> = _uiState.asStateFlow()

    init {
        loadMockData()
    }

    private fun loadMockData() {
        // Simulating network delay and returning mock data for Phase 1
        val mockData = MatchUiState(
            team1 = "MI",
            team1FullName = "MUMBAI INDIANS",
            team1Logo = "https://upload.wikimedia.org/wikipedia/en/thumb/c/cd/Mumbai_Indians_Logo.svg/1200px-Mumbai_Indians_Logo.svg.png",
            team2 = "CSK",
            team2FullName = "CHENNAI SUPER KINGS",
            team2Logo = "https://upload.wikimedia.org/wikipedia/en/thumb/2/2b/Chennai_Super_Kings_Logo.svg/1200px-Chennai_Super_Kings_Logo.svg.png",
            score = "168/4",
            overs = "19.2",
            target = "175",
            crr = "8.75",
            rrr = "9.00",
            status = "MI need 6 runs from 4 balls",
            isLive = true
        )
        _uiState.value = MatchScreenState.Success(mockData)
    }
}
