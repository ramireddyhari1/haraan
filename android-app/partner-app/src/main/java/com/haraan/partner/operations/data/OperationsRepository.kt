package com.haraan.partner.operations.data

import com.haraan.partner.operations.model.OperationsMasterData
import org.json.JSONObject

class OperationsRepository(
    private val remote: OperationsRemoteDataSource,
    private val local: OperationsLocalDataSource
) {

    suspend fun getOperationsOverview(token: String, venueId: Long): OperationsMasterData {
        return try {
            val result = remote.fetchOverview(token, venueId)
            local.cacheOverview(venueId, result.second)
            result.first
        } catch (e: Exception) {
            val cachedJson = local.getCachedOverview(venueId)
            if (cachedJson != null) {
                try {
                    val root = JSONObject(cachedJson)
                    val data = root.optJSONObject("data") ?: JSONObject()
                    remote.parseOverviewJson(data)
                } catch (parseEx: Exception) {
                    OperationsMasterData()
                }
            } else {
                OperationsMasterData()
            }
        }
    }

    suspend fun resolveAlert(token: String, venueId: Long, alertId: Long): Boolean {
        return remote.resolveAlert(token, venueId, alertId)
    }

    suspend fun applySuggestion(token: String, venueId: Long, suggestionId: Long): Boolean {
        return remote.applySuggestion(token, venueId, suggestionId)
    }

    suspend fun dismissSuggestion(token: String, venueId: Long, suggestionId: Long): Boolean {
        return remote.dismissSuggestion(token, venueId, suggestionId)
    }
}