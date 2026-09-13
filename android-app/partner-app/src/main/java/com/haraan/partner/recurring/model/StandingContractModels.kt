package com.haraan.partner.recurring.model

/**
 * Summary representation of a recurring standing contract in lists and cards.
 */
data class StandingContractSummary(
    val id: Long,
    val customerName: String,
    val customerPhone: String,
    val courtId: Long,
    val courtName: String,
    val sport: String?,
    val dayOfWeek: String,
    val startTime: String,
    val endTime: String,
    val pricePerSession: Double,
    val monthlyValue: Double,
    val securityDeposit: Double,
    val balanceDue: Double,
    val attendanceRate: Double,
    val status: String,
    val isAtRisk: Boolean,
    val riskReason: String?,
    val activeFrom: String,
    val activeUntil: String?,
    val nextSession: NextSessionInfo?
)

data class NextSessionInfo(
    val id: Long,
    val date: String,
    val time: String
)

data class StandingDashboardMetrics(
    val totalContracts: Int = 0,
    val activeContracts: Int = 0,
    val atRiskContracts: Int = 0,
    val pausedContracts: Int = 0,
    val monthlyRecurringRevenue: Double = 0.0,
    val totalSecurityDeposits: Double = 0.0,
    val averageAttendanceRate: Double = 100.0
)

data class TodaySessionItem(
    val sessionId: Long,
    val contractId: Long,
    val customerName: String,
    val customerPhone: String,
    val courtName: String,
    val startTime: String,
    val endTime: String,
    val attendanceStatus: String,
    val paymentStatus: String,
    val price: Double
)

data class AtRiskAlertItem(
    val id: Long,
    val customerName: String,
    val customerPhone: String,
    val weekday: String,
    val time: String,
    val missedStreak: Int,
    val attendanceRate: Double,
    val riskReason: String?
)

data class StandingContractDetail(
    val id: Long,
    val customerName: String,
    val customerPhone: String,
    val courtId: Long,
    val courtName: String,
    val sport: String?,
    val dayOfWeek: String,
    val startTime: String,
    val endTime: String,
    val durationMinutes: Int,
    val pricePerSession: Double,
    val monthlyValue: Double,
    val securityDeposit: Double,
    val advancePaid: Double,
    val balanceDue: Double,
    val attendanceRate: Double,
    val status: String,
    val isAtRisk: Boolean,
    val riskReason: String?,
    val activeFrom: String,
    val activeUntil: String?,
    val autoRenew: Boolean,
    val maxMembers: Int,
    val notes: String?,
    val consecutiveMissedSessions: Int,
    val totalSessionsCount: Int,
    val attendedSessionsCount: Int,
    val sessions: List<ContractSessionItem> = emptyList(),
    val payments: List<ContractPaymentItem> = emptyList(),
    val logs: List<ContractLogItem> = emptyList()
)

data class ContractSessionItem(
    val id: Long,
    val bookingId: Long?,
    val sessionDate: String,
    val startTime: String,
    val endTime: String,
    val courtName: String,
    val price: Double,
    val attendanceStatus: String,
    val paymentStatus: String,
    val checkInTime: String?,
    val notes: String?
)

data class ContractPaymentItem(
    val id: Long,
    val amount: Double,
    val paymentType: String,
    val method: String,
    val collectorName: String,
    val notes: String?,
    val createdAt: String
)

data class ContractLogItem(
    val id: Long,
    val action: String,
    val details: String?,
    val actorName: String,
    val createdAt: String
)

data class ConflictReport(
    val isClear: Boolean,
    val totalSessionsChecked: Int,
    val conflictingSessionsCount: Int,
    val clearSessionsCount: Int,
    val conflicts: List<ConflictItem>,
    val schedulePreview: List<SchedulePreviewItem>
)

data class ConflictItem(
    val date: String,
    val weekday: String,
    val startTime: String,
    val endTime: String,
    val conflictType: String,
    val conflictingId: Long?,
    val reason: String,
    val suggestedAction: String
)

data class SchedulePreviewItem(
    val date: String,
    val weekday: String,
    val startTime: String,
    val endTime: String,
    val status: String, // available, conflict
    val conflict: ConflictItem?
)

data class CreateContractRequest(
    val customerName: String,
    val customerPhone: String,
    val courtId: Long,
    val sport: String?,
    val dayOfWeek: String,
    val startTime: String,
    val endTime: String,
    val durationMinutes: Int = 60,
    val pricePerSession: Double,
    val monthlyPackagePrice: Double? = null,
    val securityDeposit: Double = 0.0,
    val advancePaid: Double = 0.0,
    val activeFrom: String,
    val activeUntil: String? = null,
    val autoRenew: Boolean = true,
    val autoSkipConflicts: Boolean = true,
    val maxMembers: Int = 10,
    val notes: String? = null,
    val paymentMethod: String = "cash"
)

data class OfflineRecurringAction(
    val id: Long = 0,
    val actionType: String, // CREATE_CONTRACT, SKIP_SESSION, MARK_ATTENDANCE, RECORD_PAYMENT, PAUSE_CONTRACT, RESUME_CONTRACT
    val venueId: Long,
    val contractId: Long? = null,
    val payloadJson: String,
    val createdAt: Long = System.currentTimeMillis()
)
