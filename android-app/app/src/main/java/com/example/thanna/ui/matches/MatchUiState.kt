package com.example.thanna.ui.matches

data class MatchUiState(
    val team1: String,
    val team1FullName: String,
    val team1Logo: String,
    val team2: String,
    val team2FullName: String,
    val team2Logo: String,
    val score: String,
    val overs: String,
    val target: String,
    val crr: String,
    val rrr: String,
    val status: String,
    val isLive: Boolean = true
)

sealed class MatchScreenState {
    object Loading : MatchScreenState()
    data class Success(val data: MatchUiState) : MatchScreenState()
    data class Error(val message: String) : MatchScreenState()
    object Empty : MatchScreenState()
}
