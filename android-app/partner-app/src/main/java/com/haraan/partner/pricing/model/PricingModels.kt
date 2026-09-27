package com.haraan.partner.pricing.model

import java.io.Serializable

data class HourSlotRate(
    val hour: Int,
    val timeLabel: String,
    val rate: Int,
    val baseRate: Int,
    val ruleId: Long? = null,
    val ruleName: String? = null,
    val mode: String = "absolute",
    val isPeak: Boolean = false,
    val tag: String = "standard" // standard, peak, discount
) : Serializable

data class DayPricingSchedule(
    val dayName: String,
    val date: String,
    val slots: List<HourSlotRate> = emptyList()
) : Serializable

data class WeeklyPricingMatrix(
    val courtId: Long? = null,
    val courtName: String = "Default Court",
    val baseRate: Int = 1000,
    val minRate: Int = 1000,
    val maxRate: Int = 1000,
    val averageRate: Int = 1000,
    val matrix: Map<String, DayPricingSchedule> = emptyMap()
) : Serializable

data class PricingRuleItem(
    val id: Long,
    val venueId: Long,
    val venueCourtId: Long? = null,
    val courtName: String = "All Courts",
    val name: String,
    val ruleType: String = "time_of_day",
    val weekdays: List<String> = emptyList(),
    val startTime: String,
    val endTime: String,
    val dateFrom: String? = null,
    val dateTo: String? = null,
    val pricingMode: String = "absolute", // absolute, delta, percentage
    val amount: Double,
    val minPrice: Double? = null,
    val maxPrice: Double? = null,
    val priority: Int = 10,
    val isActive: Boolean = true,
    val createdAt: String = ""
) : Serializable

data class CreatePricingRuleRequest(
    val name: String,
    val venueCourtId: Long? = null,
    val ruleType: String = "time_of_day",
    val weekdays: List<String> = listOf("monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"),
    val startTime: String,
    val endTime: String,
    val dateFrom: String? = null,
    val dateTo: String? = null,
    val pricingMode: String = "absolute",
    val amount: Double,
    val minPrice: Double? = null,
    val maxPrice: Double? = null,
    val priority: Int = 10
) : Serializable

data class ChildPartitionItem(
    val id: Long,
    val name: String,
    val partitionLabel: String? = null,
    val price: Int,
    val seats: Int? = null,
    val sports: List<String> = emptyList(),
    val isActive: Boolean = true
) : Serializable

data class CompositeCourtItem(
    val id: Long,
    val name: String,
    val kind: String = "court",
    val isComposite: Boolean = true,
    val splitType: String = "half",
    val price: Int = 1000,
    val sports: List<String> = emptyList(),
    val allowSimultaneousBooking: Boolean = false,
    val children: List<ChildPartitionItem> = emptyList()
) : Serializable

data class StandaloneCourtItem(
    val id: Long,
    val name: String,
    val kind: String = "court",
    val isComposite: Boolean = false,
    val price: Int = 1000,
    val sports: List<String> = emptyList()
) : Serializable

data class CourtHierarchyData(
    val compositeCourts: List<CompositeCourtItem> = emptyList(),
    val standaloneCourts: List<StandaloneCourtItem> = emptyList(),
    val totalComposite: Int = 0,
    val totalStandalone: Int = 0
) : Serializable

data class PartitionConfig(
    val name: String,
    val label: String? = null,
    val price: Double? = null
) : Serializable

data class SplitCourtRequest(
    val courtId: Long,
    val splitType: String, // half, third, quarter, custom
    val partitions: List<PartitionConfig>
) : Serializable

data class YieldRecommendationItem(
    val id: String,
    val title: String,
    val dayOfWeek: String,
    val timeWindow: String,
    val currentOccupancyRate: Double,
    val suggestedMode: String,
    val suggestedRate: Double,
    val projectedMonthlyUplift: Double,
    val rationale: String,
    val rulePayload: CreatePricingRuleRequest? = null
) : Serializable

/**
 * The pricing summary. Every figure defaults to 0 and [loaded] to false: these used to
 * default to ₹1000 / ₹800–₹1500, so a failed load put plausible rates that weren't the
 * partner's on screen with no sign anything was wrong.
 */
data class PricingDashboardMetrics(
    val loaded: Boolean = false,
    val averageHourlyRate: Int = 0,
    val minRate: Int = 0,
    val maxRate: Int = 0,
    val activeRulesCount: Int = 0,
    val compositeCourtsCount: Int = 0,
    val recommendationsCount: Int = 0,
    val topRecommendations: List<YieldRecommendationItem> = emptyList()
) : Serializable

data class OfflinePricingAction(
    val id: Long = 0L,
    val actionType: String, // CREATE_RULE, TOGGLE_RULE, DELETE_RULE, SPLIT_COURT, MERGE_COURT
    val venueId: Long,
    val payloadJson: String,
    val createdAt: Long = System.currentTimeMillis()
) : Serializable
