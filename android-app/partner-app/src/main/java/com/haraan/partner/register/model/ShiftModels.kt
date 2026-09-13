package com.haraan.partner.register.model

/**
 * Full state of a venue desk shift session.
 */
data class ShiftSessionUiModel(
    val shiftId: Long,
    val venueId: Long,
    val venueName: String,
    val isOpen: Boolean,
    val staffId: Long,
    val staffName: String,
    val openedAt: String,
    val closedAt: String? = null,
    val openingFloat: Double = 0.0,
    val cashCollected: Double = 0.0,
    val upiCollected: Double = 0.0,
    val cardCollected: Double = 0.0,
    val totalDrops: Double = 0.0,
    val expectedCash: Double = 0.0,
    val countedCash: Double? = null,
    val variance: Double? = null,
    val varianceLabel: String = "Square",
    val note: String? = null,
    val payments: List<ShiftPaymentItem> = emptyList(),
    val drops: List<ShiftDropItem> = emptyList(),
) {
    val totalInflows: Double get() = cashCollected + upiCollected + cardCollected
    val isShort: Boolean get() = (variance ?: 0.0) < -0.01
    val isOver: Boolean get() = (variance ?: 0.0) > 0.01
    val isSquare: Boolean get() = variance != null && Math.abs(variance) <= 0.01
}

data class ShiftPaymentItem(
    val id: Long,
    val amount: Double,
    val method: String,
    val customerName: String,
    val time: String,
)

data class ShiftDropItem(
    val id: Long,
    val amount: Double,
    val category: String,
    val reason: String?,
    val staffName: String,
    val time: String,
)

/**
 * Currency denomination breakdown for physical cash counting.
 */
data class DenominationCount(
    val d500: Int = 0,
    val d200: Int = 0,
    val d100: Int = 0,
    val d50: Int = 0,
    val d20: Int = 0,
    val d10: Int = 0,
    val coins: Int = 0,
) {
    val total: Double
        get() = (d500 * 500 + d200 * 200 + d100 * 100 + d50 * 50 + d20 * 20 + d10 * 10 + coins).toDouble()

    fun toSummaryString(): String {
        val parts = mutableListOf<String>()
        if (d500 > 0) parts.add("₹500×$d500")
        if (d200 > 0) parts.add("₹200×$d200")
        if (d100 > 0) parts.add("₹100×$d100")
        if (d50 > 0) parts.add("₹50×$d50")
        if (d20 > 0) parts.add("₹20×$d20")
        if (d10 > 0) parts.add("₹10×$d10")
        if (coins > 0) parts.add("Coins:₹$coins")
        return parts.joinToString(", ")
    }
}

enum class ShiftDropCategory(val code: String, val label: String) {
    DIESEL("diesel", "Diesel / Fuel"),
    CLEANING("cleaning", "Cleaning & Housekeeping"),
    MAINTENANCE("maintenance", "Turf Maintenance / Repairs"),
    SUPPLIES("supplies", "Balls / Bibs / Gear"),
    OWNER_DRAW("owner_draw", "Owner Cash Withdrawal"),
    BANK_DEPOSIT("bank_deposit", "Bank Cash Deposit"),
    OTHER("other", "Other Expense"),
}

data class HistoricalShiftSummary(
    val id: Long,
    val staffName: String,
    val closedBy: String?,
    val openedAt: String,
    val closedAt: String?,
    val openingFloat: Double,
    val cashCollected: Double,
    val totalDrops: Double,
    val expectedCash: Double,
    val countedCash: Double?,
    val variance: Double?,
    val varianceLabel: String,
    val note: String?,
)

data class OfflineShiftAction(
    val actionId: String,
    val actionType: ActionType,
    val venueId: Long,
    val payloadJson: String,
    val createdAt: Long = System.currentTimeMillis(),
    val status: SyncStatus = SyncStatus.PENDING,
) {
    enum class ActionType {
        OPEN_SHIFT,
        CASH_DROP,
        CLOSE_SHIFT,
    }

    enum class SyncStatus {
        PENDING,
        SYNCING,
        FAILED,
        SYNCED,
    }
}
