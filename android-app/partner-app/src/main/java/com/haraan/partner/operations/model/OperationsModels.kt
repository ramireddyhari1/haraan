package com.haraan.partner.operations.model

import java.io.Serializable

data class RevenueChannelData(
    val amount: Double,
    val count: Int
) : Serializable

data class RevenueOverview(
    val todayRevenue: Double = 0.0,
    val yesterdayRevenue: Double = 0.0,
    val weekToDateRevenue: Double = 0.0,
    val monthToDateRevenue: Double = 0.0,
    val projectedMonthRevenue: Double = 0.0,
    val dayOverDayGrowthPct: Double = 0.0,
    val standingContractsMrr: Double = 0.0,
    val channelBreakdown: Map<String, RevenueChannelData> = emptyMap()
) : Serializable

data class OccupancyHeatmapCell(
    val hour: Int,
    val hourLabel: String,
    val bookedCount: Int,
    val capacity: Int,
    val occupancyPct: Double,
    val intensity: String // zero, low, medium, high, peak
) : Serializable

data class OccupancyHeatmapRow(
    val dayOfWeek: Int,
    val dayName: String,
    val hours: List<OccupancyHeatmapCell> = emptyList()
) : Serializable

data class StaffPerformanceItem(
    val staffId: Long,
    val name: String,
    val email: String,
    val shiftsCompleted: Int,
    val totalCashHandled: Double,
    val cashVariance: Double,
    val conversationsHeld: Int,
    val bookingsConverted: Int
) : Serializable

data class FunnelStageItem(
    val stage: String,
    val count: Int,
    val dropOffPct: Double
) : Serializable

data class WhatsAppFunnelOverview(
    val totalInquiries: Int = 0,
    val convertedBookings: Int = 0,
    val conversionRatePct: Double = 0.0,
    val totalWhatsappRevenue: Double = 0.0,
    val stages: List<FunnelStageItem> = emptyList()
) : Serializable

data class RevenueLeakageAlertItem(
    val id: Long,
    val venueId: Long,
    val alertType: String,
    val severity: String, // low, medium, high, critical
    val title: String,
    val description: String,
    val isResolved: Boolean = false,
    val createdAt: String = ""
) : Serializable

data class BusinessSuggestionItem(
    val id: Long,
    val venueId: Long,
    val category: String, // pricing, occupancy, retention, leakage
    val title: String,
    val rationale: String,
    val projectedRevenueImpact: Double,
    val status: String // pending, applied, dismissed
) : Serializable

data class OperationsMasterData(
    val revenue: RevenueOverview = RevenueOverview(),
    val heatmap: List<OccupancyHeatmapRow> = emptyList(),
    val staff: List<StaffPerformanceItem> = emptyList(),
    val funnel: WhatsAppFunnelOverview = WhatsAppFunnelOverview(),
    val alerts: List<RevenueLeakageAlertItem> = emptyList(),
    val suggestions: List<BusinessSuggestionItem> = emptyList()
) : Serializable