package com.haraan.partner.register.data

import com.haraan.partner.register.model.HistoricalShiftSummary
import com.haraan.partner.register.model.OfflineShiftAction
import com.haraan.partner.register.model.ShiftDropItem
import com.haraan.partner.register.model.ShiftSessionUiModel
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flow
import kotlinx.coroutines.flow.flowOn
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.util.UUID

sealed class ShiftResource<out T> {
    data class Success<out T>(val data: T) : ShiftResource<T>()
    data class Error(val message: String, val cachedData: Any? = null) : ShiftResource<Nothing>()
    data object Loading : ShiftResource<Nothing>()
}

class ShiftRepository(
    private val localDataSource: ShiftLocalDataSource,
    private val remoteDataSource: ShiftRemoteDataSource = ShiftRemoteDataSource(),
) {
    fun getCurrentShift(
        token: String,
        venueId: Long,
        forceRefresh: Boolean = false,
    ): Flow<ShiftResource<ShiftSessionUiModel?>> = flow {
        emit(ShiftResource.Loading)

        // 1. Read local cache first
        val cached = localDataSource.getCachedShift(venueId)
        if (cached != null) {
            emit(ShiftResource.Success(cached))
        }

        // 2. Fetch fresh from remote if forced or if no cache
        try {
            val remote = remoteDataSource.fetchCurrentShift(token, venueId)
            if (remote != null) {
                localDataSource.saveShiftCache(venueId, remote)
                emit(ShiftResource.Success(remote))
            } else {
                localDataSource.clearShiftCache(venueId)
                emit(ShiftResource.Success(null))
            }
        } catch (e: Exception) {
            if (cached != null) {
                // Return cached data gracefully even if offline
                emit(ShiftResource.Success(cached))
            } else {
                emit(ShiftResource.Error(e.message ?: "Failed to fetch shift status", cached))
            }
        }
    }.flowOn(Dispatchers.IO)

    suspend fun openShift(
        token: String,
        venueId: Long,
        openingFloat: Double,
        note: String? = null,
    ): Result<ShiftSessionUiModel> = withContext(Dispatchers.IO) {
        try {
            val remote = remoteDataSource.openShift(token, venueId, openingFloat, note)
            localDataSource.saveShiftCache(venueId, remote)
            Result.success(remote)
        } catch (e: Exception) {
            // Optimistic offline queueing
            val payload = JSONObject().apply {
                put("opening_float", openingFloat)
                if (note != null) put("note", note)
            }
            val action = OfflineShiftAction(
                actionId = UUID.randomUUID().toString(),
                actionType = OfflineShiftAction.ActionType.OPEN_SHIFT,
                venueId = venueId,
                payloadJson = payload.toString(),
            )
            localDataSource.enqueueAction(action)

            val optimistic = ShiftSessionUiModel(
                shiftId = System.currentTimeMillis(),
                venueId = venueId,
                venueName = "Current Venue",
                isOpen = true,
                staffId = 0L,
                staffName = "Current Staff (Offline)",
                openedAt = java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", java.util.Locale.US).format(java.util.Date()),
                openingFloat = openingFloat,
                cashCollected = 0.0,
                expectedCash = openingFloat,
                note = note,
            )
            localDataSource.saveShiftCache(venueId, optimistic)
            Result.success(optimistic)
        }
    }

    suspend fun recordDrop(
        token: String,
        venueId: Long,
        amount: Double,
        category: String,
        reason: String? = null,
    ): Result<ShiftSessionUiModel> = withContext(Dispatchers.IO) {
        try {
            val remote = remoteDataSource.recordDrop(token, venueId, amount, category, reason)
            localDataSource.saveShiftCache(venueId, remote)
            Result.success(remote)
        } catch (e: Exception) {
            // Enqueue drop offline
            val payload = JSONObject().apply {
                put("amount", amount)
                put("category", category)
                if (reason != null) put("reason", reason)
            }
            val action = OfflineShiftAction(
                actionId = UUID.randomUUID().toString(),
                actionType = OfflineShiftAction.ActionType.CASH_DROP,
                venueId = venueId,
                payloadJson = payload.toString(),
            )
            localDataSource.enqueueAction(action)

            // Decrement local cache expected cash
            val cached = localDataSource.getCachedShift(venueId)
            if (cached != null) {
                val updatedDrops = cached.drops + ShiftDropItem(
                    id = System.currentTimeMillis(),
                    amount = amount,
                    category = category,
                    reason = reason,
                    staffName = "Staff",
                    time = java.text.SimpleDateFormat("HH:mm", java.util.Locale.US).format(java.util.Date()),
                )
                val updated = cached.copy(
                    totalDrops = cached.totalDrops + amount,
                    expectedCash = cached.expectedCash - amount,
                    drops = updatedDrops,
                )
                localDataSource.saveShiftCache(venueId, updated)
                Result.success(updated)
            } else {
                Result.failure(e)
            }
        }
    }

    suspend fun closeShift(
        token: String,
        venueId: Long,
        countedCash: Double,
        note: String? = null,
        denominations: Map<String, Int>? = null,
    ): Result<ShiftSessionUiModel> = withContext(Dispatchers.IO) {
        try {
            val remote = remoteDataSource.closeShift(token, venueId, countedCash, note, denominations)
            localDataSource.clearShiftCache(venueId)
            Result.success(remote)
        } catch (e: Exception) {
            // Enqueue close offline
            val payload = JSONObject().apply {
                put("counted_cash", countedCash)
                if (note != null) put("note", note)
                if (denominations != null) {
                    val dObj = JSONObject()
                    denominations.forEach { (k, v) -> dObj.put(k, v) }
                    put("denominations", dObj)
                }
            }
            val action = OfflineShiftAction(
                actionId = UUID.randomUUID().toString(),
                actionType = OfflineShiftAction.ActionType.CLOSE_SHIFT,
                venueId = venueId,
                payloadJson = payload.toString(),
            )
            localDataSource.enqueueAction(action)

            val cached = localDataSource.getCachedShift(venueId)
            val variance = (cached?.expectedCash ?: 0.0).let { expected -> countedCash - expected }
            val closed = cached?.copy(
                isOpen = false,
                closedAt = java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", java.util.Locale.US).format(java.util.Date()),
                countedCash = countedCash,
                variance = variance,
                varianceLabel = if (variance < -0.01) "Short" else if (variance > 0.01) "Over" else "Square",
                note = note,
            ) ?: ShiftSessionUiModel(
                shiftId = 0L,
                venueId = venueId,
                venueName = "Venue",
                isOpen = false,
                staffId = 0L,
                staffName = "Staff",
                openedAt = "",
                closedAt = "",
                countedCash = countedCash,
            )
            localDataSource.clearShiftCache(venueId)
            Result.success(closed)
        }
    }

    suspend fun syncOfflineActions(token: String): Int = withContext(Dispatchers.IO) {
        val pending = localDataSource.getPendingActions()
        var syncedCount = 0
        for (action in pending) {
            try {
                val json = JSONObject(action.payloadJson)
                when (action.actionType) {
                    OfflineShiftAction.ActionType.OPEN_SHIFT -> {
                        remoteDataSource.openShift(
                            token = token,
                            venueId = action.venueId,
                            openingFloat = json.optDouble("opening_float", 0.0),
                            note = json.optString("note").takeIf { it.isNotBlank() },
                        )
                    }
                    OfflineShiftAction.ActionType.CASH_DROP -> {
                        remoteDataSource.recordDrop(
                            token = token,
                            venueId = action.venueId,
                            amount = json.optDouble("amount", 0.0),
                            category = json.optString("category", "expense"),
                            reason = json.optString("reason").takeIf { it.isNotBlank() },
                        )
                    }
                    OfflineShiftAction.ActionType.CLOSE_SHIFT -> {
                        val dObj = json.optJSONObject("denominations")
                        val denomMap = mutableMapOf<String, Int>()
                        dObj?.keys()?.forEach { k -> denomMap[k] = dObj.optInt(k) }
                        remoteDataSource.closeShift(
                            token = token,
                            venueId = action.venueId,
                            countedCash = json.optDouble("counted_cash", 0.0),
                            note = json.optString("note").takeIf { it.isNotBlank() },
                            denominations = if (denomMap.isNotEmpty()) denomMap else null,
                        )
                    }
                }
                localDataSource.removeAction(action.actionId)
                syncedCount++
            } catch (e: Exception) {
                // Action remains in queue to retry later
                break
            }
        }
        syncedCount
    }

    suspend fun getShiftHistory(token: String, venueId: Long): Result<List<HistoricalShiftSummary>> = withContext(Dispatchers.IO) {
        try {
            val list = remoteDataSource.fetchHistory(token, venueId)
            Result.success(list)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
