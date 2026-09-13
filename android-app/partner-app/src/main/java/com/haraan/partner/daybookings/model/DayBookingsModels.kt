package com.haraan.partner.daybookings.model

import com.haraan.partner.DayBooking
import com.haraan.partner.DaySlot
import com.haraan.partner.PayMethod

/**
 * Filter criteria for filtering the Day Bookings list.
 */
data class BookingFilter(
    val searchQuery: String = "",
    val statusFilter: StatusFilter = StatusFilter.ALL,
    val courtIdFilter: Long? = null,
    val channelFilter: ChannelFilter = ChannelFilter.ALL,
)

enum class StatusFilter(val label: String) {
    ALL("All"),
    PAID("Paid"),
    UNPAID("Unpaid / Due"),
    CHECKED_IN("Checked In"),
    WALK_IN("Walk-in"),
    CANCELLED("Cancelled"),
}

enum class ChannelFilter(val label: String) {
    ALL("All Channels"),
    ONLINE("Online"),
    OFFLINE("Walk-in / Desk"),
}

/**
 * Display mode for the Day Bookings screen.
 */
enum class ViewMode {
    GRID, // Matrix view (Court x Time Slot)
    LIST, // Chronological timeline list
}

/**
 * Aggregated key performance indicators for the selected date.
 */
data class DaySummaryStats(
    val date: String,
    val totalSlots: Int = 0,
    val bookedSlots: Int = 0,
    val availableSlots: Int = 0,
    val occupancyRate: Float = 0f,
    val expectedRevenue: Double = 0.0,
    val collectedRevenue: Double = 0.0,
    val pendingDue: Double = 0.0,
    val chaseCount: Int = 0,
    val cashCollected: Double = 0.0,
    val upiCollected: Double = 0.0,
    val onlineCollected: Double = 0.0,
    val isBlocked: Boolean = false,
)

/**
 * Enriched booking item with calculated fields for immediate UI consumption.
 */
data class DayBookingItem(
    val id: Long,
    val ticketCode: String,
    val customerName: String,
    val phone: String?,
    val channel: String, // "online" | "offline"
    val status: String,  // "CONFIRMED", "CANCELLED", etc.
    val checkedInCount: Int,
    val totalAmount: Double,
    val amountPaid: Double,
    val balanceDue: Double,
    val paymentStatus: String, // "paid", "partial", "unpaid"
    val paymentMethod: String?,
    val slotDate: String,
    val slotTime: String,
    val courtId: Long?,
    val courtName: String?,
    val branchName: String?,
    val isCancelled: Boolean,
    val isCheckedIn: Boolean,
) {
    val isWalkIn: Boolean get() = channel.equals("offline", ignoreCase = true)
    val isDue: Boolean get() = balanceDue > 0.0 && !isCancelled && totalAmount > 0.0
}

/**
 * Representation of an action performed offline that must be synchronized with the server.
 */
data class OfflineSyncAction(
    val actionId: String,
    val actionType: ActionType,
    val venueId: Long,
    val date: String,
    val payloadJson: String,
    val createdAt: Long = System.currentTimeMillis(),
    val status: SyncStatus = SyncStatus.PENDING,
    val retryCount: Int = 0,
    val lastError: String? = null,
) {
    enum class ActionType {
        CREATE_WALK_IN,
        CHECK_IN,
        CANCEL_BOOKING,
        SET_DAY_CLOSED,
    }

    enum class SyncStatus {
        PENDING,
        SYNCING,
        FAILED,
        SYNCED,
    }
}

/**
 * Data needed to render a cell in the Court x Slot grid.
 */
data class GridCellUiModel(
    val slotId: Long,
    val slotTime: String,
    val courtId: Long,
    val courtName: String,
    val price: Double,
    val isBooked: Boolean,
    val isHeld: Boolean,
    val isPeak: Boolean,
    val isAllowed: Boolean,
    val bookings: List<DayBooking>,
)

/**
 * Target payload for launching the walk-in modal with prefilled data.
 */
data class WalkInTarget(
    val slotId: Long,
    val slotTime: String,
    val courtId: Long? = null,
    val courtName: String? = null,
    val basePrice: Double = 0.0,
)
