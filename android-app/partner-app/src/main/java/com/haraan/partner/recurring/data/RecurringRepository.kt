package com.haraan.partner.recurring.data

import com.haraan.partner.recurring.model.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flow
import kotlinx.coroutines.flow.flowOn
import kotlinx.coroutines.withContext
import org.json.JSONObject

class RecurringRepository(
    private val localDs: RecurringLocalDataSource,
    private val remoteDs: RecurringRemoteDataSource = RecurringRemoteDataSource()
) {

    fun getDashboardFlow(token: String, venueId: Long, forceRefresh: Boolean = false): Flow<Pair<StandingDashboardMetrics, List<TodaySessionItem>>> = flow {
        // 1. Emit local cache first for instantaneous UI
        val cachedMetrics = localDs.getCachedDashboard(venueId)
        if (cachedMetrics != null) {
            emit(Pair(cachedMetrics, emptyList()))
        }

        // 2. Fetch fresh metrics from backend
        try {
            val (metrics, todaySessions, _) = remoteDs.fetchDashboard(token, venueId)
            val cachedContracts = localDs.getCachedContracts(venueId) ?: emptyList()
            localDs.saveCache(venueId, cachedContracts, metrics)
            emit(Pair(metrics, todaySessions))
        } catch (e: Exception) {
            if (cachedMetrics == null) {
                // If nothing in cache and network fails, emit default empty metrics
                emit(Pair(StandingDashboardMetrics(), emptyList()))
            }
        }
    }.flowOn(Dispatchers.IO)

    fun getContractsFlow(
        token: String,
        venueId: Long,
        status: String = "all",
        search: String? = null,
        forceRefresh: Boolean = false
    ): Flow<List<StandingContractSummary>> = flow {
        // 1. Emit local cache
        val cached = localDs.getCachedContracts(venueId)
        if (cached != null) {
            emit(filterContracts(cached, status, search))
        }

        // 2. Fetch from remote
        try {
            val fresh = remoteDs.fetchContracts(token, venueId, status, search)
            val cachedDashboard = localDs.getCachedDashboard(venueId)
            localDs.saveCache(venueId, fresh, cachedDashboard)
            emit(fresh)
        } catch (e: Exception) {
            if (cached == null) {
                emit(emptyList())
            }
        }
    }.flowOn(Dispatchers.IO)

    suspend fun checkConflicts(
        token: String,
        venueId: Long,
        courtId: Long,
        dayOfWeek: String,
        startTime: String,
        endTime: String,
        activeFrom: String,
        activeUntil: String? = null,
        weeks: Int = 12
    ): ConflictReport = withContext(Dispatchers.IO) {
        remoteDs.checkConflicts(token, venueId, courtId, dayOfWeek, startTime, endTime, activeFrom, activeUntil, weeks)
    }

    suspend fun createContract(
        token: String,
        venueId: Long,
        req: CreateContractRequest
    ): Result<StandingContractDetail> = withContext(Dispatchers.IO) {
        try {
            val created = remoteDs.createContract(token, venueId, req)
            // Refresh local cache
            val freshList = remoteDs.fetchContracts(token, venueId)
            localDs.saveCache(venueId, freshList, localDs.getCachedDashboard(venueId))
            Result.success(created)
        } catch (e: Exception) {
            // Queue offline action
            val payload = JSONObject().apply {
                put("customer_name", req.customerName)
                put("customer_phone", req.customerPhone)
                put("court_id", req.courtId)
                if (req.sport != null) put("sport", req.sport)
                put("day_of_week", req.dayOfWeek)
                put("start_time", req.startTime)
                put("end_time", req.endTime)
                put("duration_minutes", req.durationMinutes)
                put("price_per_session", req.pricePerSession)
                put("security_deposit", req.securityDeposit)
                put("advance_paid", req.advancePaid)
                put("active_from", req.activeFrom)
                put("auto_renew", req.autoRenew)
                put("auto_skip_conflicts", req.autoSkipConflicts)
            }
            localDs.enqueueOfflineAction(
                OfflineRecurringAction(
                    actionType = "CREATE_CONTRACT",
                    venueId = venueId,
                    payloadJson = payload.toString()
                )
            )
            Result.failure(e)
        }
    }

    suspend fun fetchContractDetail(
        token: String,
        venueId: Long,
        contractId: Long
    ): Result<StandingContractDetail> = withContext(Dispatchers.IO) {
        try {
            val detail = remoteDs.fetchContractDetail(token, venueId, contractId)
            Result.success(detail)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun skipSession(
        token: String,
        venueId: Long,
        contractId: Long,
        date: String,
        reason: String?
    ): Result<StandingContractDetail> = withContext(Dispatchers.IO) {
        try {
            val updated = remoteDs.skipSession(token, venueId, contractId, date, reason)
            Result.success(updated)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun pauseContract(
        token: String,
        venueId: Long,
        contractId: Long,
        reason: String?
    ): Result<StandingContractDetail> = withContext(Dispatchers.IO) {
        try {
            val updated = remoteDs.pauseContract(token, venueId, contractId, reason)
            Result.success(updated)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun resumeContract(
        token: String,
        venueId: Long,
        contractId: Long
    ): Result<StandingContractDetail> = withContext(Dispatchers.IO) {
        try {
            val updated = remoteDs.resumeContract(token, venueId, contractId)
            Result.success(updated)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun terminateContract(
        token: String,
        venueId: Long,
        contractId: Long,
        reason: String?
    ): Result<StandingContractDetail> = withContext(Dispatchers.IO) {
        try {
            val updated = remoteDs.terminateContract(token, venueId, contractId, reason)
            Result.success(updated)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun transferCourt(
        token: String,
        venueId: Long,
        contractId: Long,
        courtId: Long
    ): Result<StandingContractDetail> = withContext(Dispatchers.IO) {
        try {
            val updated = remoteDs.transferCourt(token, venueId, contractId, courtId)
            Result.success(updated)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun recordAttendance(
        token: String,
        venueId: Long,
        contractId: Long,
        sessionId: Long,
        status: String
    ): Result<StandingContractDetail> = withContext(Dispatchers.IO) {
        try {
            val updated = remoteDs.recordAttendance(token, venueId, contractId, sessionId, status)
            Result.success(updated)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun recordPayment(
        token: String,
        venueId: Long,
        contractId: Long,
        amount: Double,
        paymentType: String,
        method: String,
        notes: String?
    ): Result<StandingContractDetail> = withContext(Dispatchers.IO) {
        try {
            val updated = remoteDs.recordPayment(token, venueId, contractId, amount, paymentType, method, notes)
            Result.success(updated)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun syncOfflineActions(token: String): Int = withContext(Dispatchers.IO) {
        val actions = localDs.getPendingActions()
        var syncedCount = 0
        for (a in actions) {
            try {
                if (a.actionType == "CREATE_CONTRACT") {
                    val p = JSONObject(a.payloadJson)
                    val req = CreateContractRequest(
                        customerName = p.getString("customer_name"),
                        customerPhone = p.getString("customer_phone"),
                        courtId = p.getLong("court_id"),
                        sport = p.optString("sport").takeIf { it.isNotBlank() },
                        dayOfWeek = p.getString("day_of_week"),
                        startTime = p.getString("start_time"),
                        endTime = p.getString("end_time"),
                        durationMinutes = p.optInt("duration_minutes", 60),
                        pricePerSession = p.getDouble("price_per_session"),
                        securityDeposit = p.optDouble("security_deposit", 0.0),
                        advancePaid = p.optDouble("advance_paid", 0.0),
                        activeFrom = p.getString("active_from"),
                        autoRenew = p.optBoolean("auto_renew", true),
                        autoSkipConflicts = p.optBoolean("auto_skip_conflicts", true)
                    )
                    remoteDs.createContract(token, a.venueId, req)
                    localDs.deletePendingAction(a.id)
                    syncedCount++
                }
            } catch (_: Exception) {
                // Keep in queue to retry later
            }
        }
        syncedCount
    }

    fun getPendingActionCount(): Int {
        return localDs.getPendingActions().size
    }

    private fun filterContracts(list: List<StandingContractSummary>, status: String, search: String?): List<StandingContractSummary> {
        return list.filter { c ->
            val matchStatus = when (status) {
                "all" -> true
                "at_risk" -> c.isAtRisk
                else -> c.status.equals(status, ignoreCase = true)
            }
            val matchSearch = if (search.isNullOrBlank()) true else {
                c.customerName.contains(search, ignoreCase = true) || c.customerPhone.contains(search)
            }
            matchStatus && matchSearch
        }
    }
}
