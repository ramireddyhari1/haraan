package com.haraan.partner.daybookings.data

import com.haraan.partner.BookingSummary
import com.haraan.partner.CheckInResult
import com.haraan.partner.DayGrid
import com.haraan.partner.PayMethod
import com.haraan.partner.WalkInResult
import com.haraan.partner.daybookings.model.DayBookingItem
import com.haraan.partner.daybookings.model.DaySummaryStats
import com.haraan.partner.daybookings.model.OfflineSyncAction
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flow
import kotlinx.coroutines.flow.flowOn
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.util.UUID

sealed class Resource<out T> {
    data class Loading<out T>(val partialData: T? = null) : Resource<T>()
    data class Success<out T>(val data: T, val isOffline: Boolean = false) : Resource<T>()
    data class Error<out T>(val message: String, val cachedData: T? = null) : Resource<T>()
}

data class SyncResult(
    val totalProcessed: Int,
    val successful: Int,
    val failed: Int,
    val errorMessages: List<String> = emptyList(),
)

interface DayBookingsRepository {
    fun getDayGrid(token: String, venueId: Long, date: String, forceRefresh: Boolean = false): Flow<Resource<DayGrid>>
    fun getDayBookings(token: String, venueId: Long?, date: String, forceRefresh: Boolean = false): Flow<Resource<List<DayBookingItem>>>
    fun calculateStats(grid: DayGrid?, bookings: List<DayBookingItem>, date: String): DaySummaryStats
    suspend fun createWalkIn(
        token: String,
        venueId: Long,
        slotId: Long,
        courtId: Long?,
        date: String,
        guestName: String,
        guestPhone: String,
        method: PayMethod,
        customerPackageId: Long? = null,
    ): Result<WalkInResult>
    suspend fun cancelBooking(token: String, bookingId: Long, venueId: Long, date: String): Result<Unit>
    suspend fun checkInTicket(token: String, ticketCode: String, venueId: Long, date: String): Result<CheckInResult>
    suspend fun setDateClosed(token: String, venueId: Long, date: String, closed: Boolean): Result<Unit>
    suspend fun syncOfflineQueue(token: String): SyncResult
    suspend fun getPendingActionCount(): Int
}

class DayBookingsRepositoryImpl(
    private val localDataSource: DayBookingsLocalDataSource,
    private val remoteDataSource: DayBookingsRemoteDataSource,
) : DayBookingsRepository {

    override fun getDayGrid(
        token: String,
        venueId: Long,
        date: String,
        forceRefresh: Boolean
    ): Flow<Resource<DayGrid>> = flow {
        val cached = localDataSource.getDayGrid(venueId, date)
        if (cached != null) {
            emit(Resource.Loading(cached))
        } else {
            emit(Resource.Loading())
        }

        try {
            val remoteGrid = remoteDataSource.getVenueDay(token, venueId, date)
            localDataSource.saveDayGrid(venueId, date, remoteGrid)
            emit(Resource.Success(remoteGrid, isOffline = false))
        } catch (e: Exception) {
            if (cached != null) {
                emit(Resource.Success(cached, isOffline = true))
            } else {
                emit(Resource.Error(e.message ?: "Failed to load day grid", null))
            }
        }
    }.flowOn(Dispatchers.IO)

    override fun getDayBookings(
        token: String,
        venueId: Long?,
        date: String,
        forceRefresh: Boolean
    ): Flow<Resource<List<DayBookingItem>>> = flow {
        val cacheKeyVenueId = venueId ?: 0L
        val cachedSummaries = localDataSource.getBookings(cacheKeyVenueId, date)
        val cachedItems = cachedSummaries?.map { it.toDayBookingItem() }?.filter {
            it.slotDate.isBlank() || it.slotDate == date
        }

        if (cachedItems != null) {
            emit(Resource.Loading(cachedItems))
        } else {
            emit(Resource.Loading())
        }

        try {
            val remoteSummaries = remoteDataSource.getBookings(token, venueId)
            localDataSource.saveBookings(cacheKeyVenueId, date, remoteSummaries)
            val filteredItems = remoteSummaries
                .map { it.toDayBookingItem() }
                .filter { it.slotDate.isBlank() || it.slotDate == date }
            emit(Resource.Success(filteredItems, isOffline = false))
        } catch (e: Exception) {
            if (cachedItems != null) {
                emit(Resource.Success(cachedItems, isOffline = true))
            } else {
                emit(Resource.Error(e.message ?: "Failed to load bookings", null))
            }
        }
    }.flowOn(Dispatchers.IO)

    override fun calculateStats(grid: DayGrid?, bookings: List<DayBookingItem>, date: String): DaySummaryStats {
        var totalSlots = 0
        var bookedSlots = 0
        var availableSlots = 0
        var expectedRevenue = 0.0

        if (grid != null) {
            for (slot in grid.slots) {
                if (slot.courts.isNotEmpty()) {
                    for (cell in slot.courts) {
                        totalSlots++
                        if (cell.isBooked || cell.isHeld) {
                            bookedSlots++
                            expectedRevenue += cell.price
                        } else if (cell.allowed && !grid.isBlocked) {
                            availableSlots++
                        }
                    }
                } else {
                    totalSlots += slot.capacity
                    bookedSlots += slot.booked
                    availableSlots += slot.available
                    expectedRevenue += (slot.booked * slot.price)
                }
            }
        }

        var collectedRevenue = 0.0
        var pendingDue = 0.0
        var chaseCount = 0
        var cashCollected = 0.0
        var upiCollected = 0.0
        var onlineCollected = 0.0

        for (b in bookings) {
            if (!b.isCancelled) {
                collectedRevenue += b.amountPaid
                if (b.isDue) {
                    pendingDue += b.balanceDue
                    chaseCount++
                }

                val method = b.paymentMethod?.lowercase() ?: ""
                when {
                    method.contains("cash") -> cashCollected += b.amountPaid
                    method.contains("upi") -> upiCollected += b.amountPaid
                    else -> onlineCollected += b.amountPaid
                }
            }
        }

        // If bookings list gave higher expected revenue than grid, ensure consistency
        if (collectedRevenue + pendingDue > expectedRevenue && bookings.isNotEmpty()) {
            expectedRevenue = collectedRevenue + pendingDue
        }

        val occupancy = if (totalSlots > 0) (bookedSlots.toFloat() / totalSlots.toFloat()).coerceIn(0f, 1f) else 0f

        return DaySummaryStats(
            date = date,
            totalSlots = totalSlots,
            bookedSlots = bookedSlots,
            availableSlots = availableSlots,
            occupancyRate = occupancy,
            expectedRevenue = expectedRevenue,
            collectedRevenue = collectedRevenue,
            pendingDue = pendingDue,
            chaseCount = chaseCount,
            cashCollected = cashCollected,
            upiCollected = upiCollected,
            onlineCollected = onlineCollected,
            isBlocked = grid?.isBlocked == true,
        )
    }

    override suspend fun createWalkIn(
        token: String,
        venueId: Long,
        slotId: Long,
        courtId: Long?,
        date: String,
        guestName: String,
        guestPhone: String,
        method: PayMethod,
        customerPackageId: Long?,
    ): Result<WalkInResult> = withContext(Dispatchers.IO) {
        try {
            val result = remoteDataSource.createWalkIn(
                token = token,
                venueId = venueId,
                slotId = slotId,
                date = date,
                guestName = guestName,
                guestPhone = guestPhone,
                method = method,
                courtId = courtId,
                customerPackageId = customerPackageId,
            )
            // Refresh local cache after successful booking
            runCatching {
                val freshGrid = remoteDataSource.getVenueDay(token, venueId, date)
                localDataSource.saveDayGrid(venueId, date, freshGrid)
            }
            Result.success(result)
        } catch (e: Exception) {
            // Queue for offline sync
            val payload = JSONObject().apply {
                put("slotId", slotId)
                put("courtId", courtId)
                put("date", date)
                put("guestName", guestName)
                put("guestPhone", guestPhone)
                put("method", method.name)
                put("customerPackageId", customerPackageId)
            }
            val offlineAction = OfflineSyncAction(
                actionId = UUID.randomUUID().toString(),
                actionType = OfflineSyncAction.ActionType.CREATE_WALK_IN,
                venueId = venueId,
                date = date,
                payloadJson = payload.toString(),
            )
            localDataSource.enqueueAction(offlineAction)

            // Return optimistic offline result
            Result.success(
                WalkInResult(
                    bookingId = -System.currentTimeMillis(), // Temporary negative ID
                    amount = 0.0,
                    paymentMethod = method.api,
                    paymentLink = null,
                    paymentLinkId = null,
                )
            )
        }
    }

    override suspend fun cancelBooking(
        token: String,
        bookingId: Long,
        venueId: Long,
        date: String
    ): Result<Unit> = withContext(Dispatchers.IO) {
        try {
            remoteDataSource.cancelBooking(token, bookingId)
            Result.success(Unit)
        } catch (e: Exception) {
            val payload = JSONObject().apply {
                put("bookingId", bookingId)
            }
            val offlineAction = OfflineSyncAction(
                actionId = UUID.randomUUID().toString(),
                actionType = OfflineSyncAction.ActionType.CANCEL_BOOKING,
                venueId = venueId,
                date = date,
                payloadJson = payload.toString(),
            )
            localDataSource.enqueueAction(offlineAction)
            Result.success(Unit)
        }
    }

    override suspend fun checkInTicket(
        token: String,
        ticketCode: String,
        venueId: Long,
        date: String
    ): Result<CheckInResult> = withContext(Dispatchers.IO) {
        try {
            val res = remoteDataSource.checkInTicket(token, ticketCode)
            Result.success(res)
        } catch (e: Exception) {
            val payload = JSONObject().apply {
                put("ticketCode", ticketCode)
            }
            val offlineAction = OfflineSyncAction(
                actionId = UUID.randomUUID().toString(),
                actionType = OfflineSyncAction.ActionType.CHECK_IN,
                venueId = venueId,
                date = date,
                payloadJson = payload.toString(),
            )
            localDataSource.enqueueAction(offlineAction)
            Result.success(CheckInResult("queued_offline", "Queued for sync when online"))
        }
    }

    override suspend fun setDateClosed(
        token: String,
        venueId: Long,
        date: String,
        closed: Boolean
    ): Result<Unit> = withContext(Dispatchers.IO) {
        try {
            remoteDataSource.setDateClosed(token, venueId, date, closed)
            Result.success(Unit)
        } catch (e: Exception) {
            val payload = JSONObject().apply {
                put("closed", closed)
            }
            val offlineAction = OfflineSyncAction(
                actionId = UUID.randomUUID().toString(),
                actionType = OfflineSyncAction.ActionType.SET_DAY_CLOSED,
                venueId = venueId,
                date = date,
                payloadJson = payload.toString(),
            )
            localDataSource.enqueueAction(offlineAction)
            Result.success(Unit)
        }
    }

    override suspend fun syncOfflineQueue(token: String): SyncResult = withContext(Dispatchers.IO) {
        val pending = localDataSource.getPendingActions()
        var successCount = 0
        var failedCount = 0
        val errors = mutableListOf<String>()

        for (action in pending) {
            try {
                localDataSource.updateActionStatus(action.actionId, OfflineSyncAction.SyncStatus.SYNCING)
                val payload = JSONObject(action.payloadJson)
                when (action.actionType) {
                    OfflineSyncAction.ActionType.CREATE_WALK_IN -> {
                        val slotId = payload.getLong("slotId")
                        val courtId = payload.optLong("courtId").takeIf { it > 0 }
                        val date = payload.getString("date")
                        val guestName = payload.getString("guestName")
                        val guestPhone = payload.getString("guestPhone")
                        val method = PayMethod.valueOf(payload.getString("method"))
                        val customerPackageId = payload.optLong("customerPackageId").takeIf { it > 0 }

                        remoteDataSource.createWalkIn(
                            token = token,
                            venueId = action.venueId,
                            slotId = slotId,
                            courtId = courtId,
                            date = date,
                            guestName = guestName,
                            guestPhone = guestPhone,
                            method = method,
                            customerPackageId = customerPackageId,
                        )
                    }
                    OfflineSyncAction.ActionType.CHECK_IN -> {
                        val code = payload.getString("ticketCode")
                        remoteDataSource.checkInTicket(token, code)
                    }
                    OfflineSyncAction.ActionType.CANCEL_BOOKING -> {
                        val bookingId = payload.getLong("bookingId")
                        remoteDataSource.cancelBooking(token, bookingId)
                    }
                    OfflineSyncAction.ActionType.SET_DAY_CLOSED -> {
                        val closed = payload.getBoolean("closed")
                        remoteDataSource.setDateClosed(token, action.venueId, action.date, closed)
                    }
                }
                localDataSource.removeAction(action.actionId)
                successCount++
            } catch (e: Exception) {
                failedCount++
                val msg = e.message ?: "Failed to sync"
                errors.add(msg)
                localDataSource.updateActionStatus(action.actionId, OfflineSyncAction.SyncStatus.FAILED, msg)
            }
        }

        SyncResult(
            totalProcessed = pending.size,
            successful = successCount,
            failed = failedCount,
            errorMessages = errors,
        )
    }

    override suspend fun getPendingActionCount(): Int = withContext(Dispatchers.IO) {
        localDataSource.getPendingActionCount()
    }

    // --- Mapper Extensions ---

    private fun BookingSummary.toDayBookingItem(): DayBookingItem {
        val total = this.amount
        val paid = this.amountPaid
        val due = (total - paid).coerceAtLeast(0.0)
        val statusUpper = (this.status ?: "CONFIRMED").uppercase()
        val cancelled = statusUpper in listOf("CANCELLED", "REFUNDED", "FAILED")

        return DayBookingItem(
            id = this.id,
            ticketCode = this.ticketCode ?: "HRN-${this.id}",
            customerName = this.customer.ifBlank { "Guest" },
            phone = null,
            channel = this.channel,
            status = statusUpper,
            checkedInCount = this.checkedIn,
            totalAmount = total,
            amountPaid = paid,
            balanceDue = if (cancelled) 0.0 else due,
            paymentStatus = this.paymentStatus.lowercase(),
            paymentMethod = this.paymentMethod,
            slotDate = this.slotDate ?: "",
            slotTime = this.slotLabel ?: this.label ?: "",
            courtId = null,
            courtName = null,
            branchName = this.branch,
            isCancelled = cancelled,
            isCheckedIn = this.checkedIn > 0,
        )
    }
}
