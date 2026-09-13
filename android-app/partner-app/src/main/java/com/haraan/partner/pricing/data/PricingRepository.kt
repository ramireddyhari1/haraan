package com.haraan.partner.pricing.data

import com.haraan.partner.pricing.model.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flow
import kotlinx.coroutines.flow.flowOn
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject

class PricingRepository(
    private val localDs: PricingLocalDataSource,
    private val remoteDs: PricingRemoteDataSource = PricingRemoteDataSource()
) {

    fun getDashboardFlow(token: String, venueId: Long): Flow<PricingDashboardMetrics> = flow {
        try {
            val fresh = remoteDs.fetchDashboard(token, venueId)
            emit(fresh)
        } catch (_: Exception) {
            // Emit fallback metrics
            emit(PricingDashboardMetrics())
        }
    }.flowOn(Dispatchers.IO)

    fun getMatrixFlow(token: String, venueId: Long, courtId: Long): Flow<WeeklyPricingMatrix> = flow {
        // 1. Emit local cache first
        val cached = localDs.getCachedMatrix(venueId, courtId)
        if (cached != null) {
            emit(cached)
        }

        // 2. Fetch fresh matrix
        try {
            val fresh = remoteDs.fetchMatrix(token, venueId, courtId)
            localDs.saveCachedMatrix(venueId, courtId, fresh)
            emit(fresh)
        } catch (_: Exception) {
            if (cached == null) {
                emit(WeeklyPricingMatrix(courtId = courtId))
            }
        }
    }.flowOn(Dispatchers.IO)

    fun getRulesFlow(token: String, venueId: Long, courtId: Long? = null, activeOnly: Boolean? = null): Flow<List<PricingRuleItem>> = flow {
        val cached = localDs.getCachedRules(venueId)
        if (cached != null) {
            emit(cached)
        }

        try {
            val fresh = remoteDs.fetchRules(token, venueId, courtId, activeOnly)
            localDs.saveCachedRules(venueId, fresh)
            emit(fresh)
        } catch (_: Exception) {
            if (cached == null) {
                emit(emptyList())
            }
        }
    }.flowOn(Dispatchers.IO)

    fun getHierarchyFlow(token: String, venueId: Long): Flow<CourtHierarchyData> = flow {
        val cached = localDs.getCachedHierarchy(venueId)
        if (cached != null) {
            emit(cached)
        }

        try {
            val fresh = remoteDs.fetchHierarchy(token, venueId)
            localDs.saveCachedHierarchy(venueId, fresh)
            emit(fresh)
        } catch (_: Exception) {
            if (cached == null) {
                emit(CourtHierarchyData())
            }
        }
    }.flowOn(Dispatchers.IO)

    suspend fun createRule(token: String, venueId: Long, req: CreatePricingRuleRequest): Result<PricingRuleItem> = withContext(Dispatchers.IO) {
        try {
            val created = remoteDs.createRule(token, venueId, req)
            val freshRules = remoteDs.fetchRules(token, venueId)
            localDs.saveCachedRules(venueId, freshRules)
            Result.success(created)
        } catch (e: Exception) {
            val payload = JSONObject().apply {
                put("name", req.name)
                if (req.venueCourtId != null) put("venue_court_id", req.venueCourtId)
                put("rule_type", req.ruleType)
                put("weekdays", JSONArray(req.weekdays))
                put("start_time", req.startTime)
                put("end_time", req.endTime)
                put("pricing_mode", req.pricingMode)
                put("amount", req.amount)
                put("priority", req.priority)
            }
            localDs.enqueueOfflineAction(
                OfflinePricingAction(
                    actionType = "CREATE_RULE",
                    venueId = venueId,
                    payloadJson = payload.toString()
                )
            )
            Result.failure(e)
        }
    }

    suspend fun toggleRule(token: String, venueId: Long, ruleId: Long): Result<Boolean> = withContext(Dispatchers.IO) {
        try {
            val res = remoteDs.toggleRule(token, venueId, ruleId)
            val freshRules = remoteDs.fetchRules(token, venueId)
            localDs.saveCachedRules(venueId, freshRules)
            Result.success(res)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun deleteRule(token: String, venueId: Long, ruleId: Long): Result<Boolean> = withContext(Dispatchers.IO) {
        try {
            val res = remoteDs.deleteRule(token, venueId, ruleId)
            val freshRules = remoteDs.fetchRules(token, venueId)
            localDs.saveCachedRules(venueId, freshRules)
            Result.success(res)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun splitCourt(token: String, venueId: Long, req: SplitCourtRequest): Result<Boolean> = withContext(Dispatchers.IO) {
        try {
            val res = remoteDs.splitCourt(token, venueId, req)
            val freshH = remoteDs.fetchHierarchy(token, venueId)
            localDs.saveCachedHierarchy(venueId, freshH)
            Result.success(res)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun mergeCourts(token: String, venueId: Long, courtId: Long): Result<Boolean> = withContext(Dispatchers.IO) {
        try {
            val res = remoteDs.mergeCourts(token, venueId, courtId)
            val freshH = remoteDs.fetchHierarchy(token, venueId)
            localDs.saveCachedHierarchy(venueId, freshH)
            Result.success(res)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun applyRecommendation(token: String, venueId: Long, req: CreatePricingRuleRequest): Result<Boolean> = withContext(Dispatchers.IO) {
        try {
            val res = remoteDs.applyRecommendation(token, venueId, req)
            val freshRules = remoteDs.fetchRules(token, venueId)
            localDs.saveCachedRules(venueId, freshRules)
            Result.success(res)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun syncOfflineActions(token: String): Int = withContext(Dispatchers.IO) {
        val actions = localDs.getPendingActions()
        var count = 0
        for (a in actions) {
            try {
                if (a.actionType == "CREATE_RULE") {
                    val p = JSONObject(a.payloadJson)
                    val wdArr = p.optJSONArray("weekdays") ?: JSONArray()
                    val wds = mutableListOf<String>()
                    for (j in 0 until wdArr.length()) wds.add(wdArr.getString(j))

                    val req = CreatePricingRuleRequest(
                        name = p.getString("name"),
                        venueCourtId = if (p.has("venue_court_id") && !p.isNull("venue_court_id")) p.getLong("venue_court_id") else null,
                        ruleType = p.optString("rule_type", "time_of_day"),
                        weekdays = wds,
                        startTime = p.getString("start_time"),
                        endTime = p.getString("end_time"),
                        pricingMode = p.getString("pricing_mode"),
                        amount = p.getDouble("amount"),
                        priority = p.optInt("priority", 10)
                    )
                    remoteDs.createRule(token, a.venueId, req)
                    localDs.deletePendingAction(a.id)
                    count++
                }
            } catch (_: Exception) {
                // Keep for next retry
            }
        }
        count
    }

    fun getPendingActionCount(): Int {
        return localDs.getPendingActions().size
    }
}
