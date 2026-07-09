package com.example.thanna

import androidx.navigation3.runtime.NavKey
import kotlinx.serialization.Serializable

@Serializable data object Main : NavKey

@Serializable data object SupportChat : NavKey

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

/** One line of a ticket order: a chosen tier (or flat price) × quantity. */
@Serializable
data class OrderLine(
	val ticketTypeId: Int = -1,  // -1 = flat-price event (no named tier)
	val name: String,
	val unitPrice: Double,
	val quantity: Int,
	val admits: Int = 1,
	val phaseLabel: String = "", // e.g. "Phase 1" when priced by a dynamic phase
)

@Serializable
data class OrderSummary(
	val eventId: Int,
	val title: String,
	val date: String,
	val venue: String,
	val imageUrl: String,
	val lines: List<OrderLine>,
	val feeType: String = "none", // convenience fee: none | flat | percent
	val feeValue: Double = 0.0,   // ₹ amount (flat) or % of subtotal (percent)
	val infoNotes: List<String> = emptyList(), // "Before you book" policy bullets
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

@Serializable data class PriceChart(val venueId: String) : NavKey
