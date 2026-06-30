package com.example.thanna

import androidx.navigation3.runtime.NavKey
import kotlinx.serialization.Serializable

@Serializable data object Main : NavKey

@Serializable
data class EventDetail(
	val id: String,
	val title: String,
	val date: String,
	val venue: String,
	val price: String,
	val category: String,
	val imageUrl: String,
	val description: String = "",
	val bookedThisWeek: Int = 0,
	val infoNotes: List<String> = emptyList(),
	val organizer: String = "",
	val organizerSubtitle: String = "",
) : NavKey

@Serializable
data class MatchDetails(val id: String = "", val code: String = "") : NavKey

@Serializable
data class Scoring(val id: String = "", val code: String = "") : NavKey

@Serializable
data class VenueDetail(
	val id: String,
	val title: String,
	val location: String,
	val rating: String = "",
	val category: String = "",
	val price: Int = 0,
	val imageUrl: String = "",
	val tagline: String = "",
	val distance: String = "",
) : NavKey
