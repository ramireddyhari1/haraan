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
	val imageUrl: String
) : NavKey

@Serializable
data class MatchDetails(val id: String) : NavKey
