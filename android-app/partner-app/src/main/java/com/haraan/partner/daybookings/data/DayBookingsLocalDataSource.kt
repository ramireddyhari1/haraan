package com.haraan.partner.daybookings.data

import android.content.ContentValues
import android.content.Context
import android.database.Cursor
import com.haraan.partner.BookingSummary
import com.haraan.partner.CourtCell
import com.haraan.partner.CourtCol
import com.haraan.partner.DayBooking
import com.haraan.partner.DayGrid
import com.haraan.partner.DaySlot
import com.haraan.partner.daybookings.model.OfflineSyncAction
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject

class DayBookingsLocalDataSource(context: Context) {
    private val dbHelper = DayBookingsDatabaseHelper(context)

    suspend fun saveDayGrid(venueId: Long, date: String, grid: DayGrid): Unit = withContext(Dispatchers.IO) {
        val db = dbHelper.writableDatabase
        val values = ContentValues().apply {
            put(DayBookingsDatabaseHelper.COL_VENUE_ID, venueId)
            put(DayBookingsDatabaseHelper.COL_DATE, date)
            put(DayBookingsDatabaseHelper.COL_DATA_JSON, serializeDayGrid(grid).toString())
            put(DayBookingsDatabaseHelper.COL_UPDATED_AT, System.currentTimeMillis())
        }
        db.insertWithOnConflict(
            DayBookingsDatabaseHelper.TABLE_GRID_CACHE,
            null,
            values,
            android.database.sqlite.SQLiteDatabase.CONFLICT_REPLACE
        )
    }

    suspend fun getDayGrid(venueId: Long, date: String): DayGrid? = withContext(Dispatchers.IO) {
        val db = dbHelper.readableDatabase
        var cursor: Cursor? = null
        try {
            cursor = db.query(
                DayBookingsDatabaseHelper.TABLE_GRID_CACHE,
                arrayOf(DayBookingsDatabaseHelper.COL_DATA_JSON),
                "${DayBookingsDatabaseHelper.COL_VENUE_ID} = ? AND ${DayBookingsDatabaseHelper.COL_DATE} = ?",
                arrayOf(venueId.toString(), date),
                null, null, null
            )
            if (cursor.moveToFirst()) {
                val jsonStr = cursor.getString(0)
                return@withContext deserializeDayGrid(JSONObject(jsonStr))
            }
        } catch (e: Exception) {
            e.printStackTrace()
        } finally {
            cursor?.close()
        }
        null
    }

    suspend fun saveBookings(venueId: Long, date: String, bookings: List<BookingSummary>): Unit = withContext(Dispatchers.IO) {
        val db = dbHelper.writableDatabase
        val array = JSONArray()
        for (b in bookings) {
            array.put(serializeBooking(b))
        }
        val values = ContentValues().apply {
            put(DayBookingsDatabaseHelper.COL_VENUE_ID, venueId)
            put(DayBookingsDatabaseHelper.COL_DATE, date)
            put(DayBookingsDatabaseHelper.COL_DATA_JSON, array.toString())
            put(DayBookingsDatabaseHelper.COL_UPDATED_AT, System.currentTimeMillis())
        }
        db.insertWithOnConflict(
            DayBookingsDatabaseHelper.TABLE_BOOKINGS_CACHE,
            null,
            values,
            android.database.sqlite.SQLiteDatabase.CONFLICT_REPLACE
        )
    }

    suspend fun getBookings(venueId: Long, date: String): List<BookingSummary>? = withContext(Dispatchers.IO) {
        val db = dbHelper.readableDatabase
        var cursor: Cursor? = null
        try {
            cursor = db.query(
                DayBookingsDatabaseHelper.TABLE_BOOKINGS_CACHE,
                arrayOf(DayBookingsDatabaseHelper.COL_DATA_JSON),
                "${DayBookingsDatabaseHelper.COL_VENUE_ID} = ? AND ${DayBookingsDatabaseHelper.COL_DATE} = ?",
                arrayOf(venueId.toString(), date),
                null, null, null
            )
            if (cursor.moveToFirst()) {
                val jsonStr = cursor.getString(0)
                val array = JSONArray(jsonStr)
                val list = mutableListOf<BookingSummary>()
                for (i in 0 until array.length()) {
                    list.add(deserializeBooking(array.getJSONObject(i)))
                }
                return@withContext list
            }
        } catch (e: Exception) {
            e.printStackTrace()
        } finally {
            cursor?.close()
        }
        null
    }

    suspend fun enqueueAction(action: OfflineSyncAction): Unit = withContext(Dispatchers.IO) {
        val db = dbHelper.writableDatabase
        val values = ContentValues().apply {
            put(DayBookingsDatabaseHelper.COL_ACTION_ID, action.actionId)
            put(DayBookingsDatabaseHelper.COL_ACTION_TYPE, action.actionType.name)
            put(DayBookingsDatabaseHelper.COL_VENUE_ID, action.venueId)
            put(DayBookingsDatabaseHelper.COL_DATE, action.date)
            put(DayBookingsDatabaseHelper.COL_PAYLOAD_JSON, action.payloadJson)
            put(DayBookingsDatabaseHelper.COL_STATUS, action.status.name)
            put(DayBookingsDatabaseHelper.COL_CREATED_AT, action.createdAt)
            put(DayBookingsDatabaseHelper.COL_RETRY_COUNT, action.retryCount)
            put(DayBookingsDatabaseHelper.COL_LAST_ERROR, action.lastError)
        }
        db.insertWithOnConflict(
            DayBookingsDatabaseHelper.TABLE_OFFLINE_QUEUE,
            null,
            values,
            android.database.sqlite.SQLiteDatabase.CONFLICT_REPLACE
        )
    }

    suspend fun getPendingActions(): List<OfflineSyncAction> = withContext(Dispatchers.IO) {
        val db = dbHelper.readableDatabase
        val list = mutableListOf<OfflineSyncAction>()
        var cursor: Cursor? = null
        try {
            cursor = db.query(
                DayBookingsDatabaseHelper.TABLE_OFFLINE_QUEUE,
                null,
                "${DayBookingsDatabaseHelper.COL_STATUS} != ?",
                arrayOf(OfflineSyncAction.SyncStatus.SYNCED.name),
                null, null,
                "${DayBookingsDatabaseHelper.COL_CREATED_AT} ASC"
            )
            while (cursor.moveToNext()) {
                list.add(
                    OfflineSyncAction(
                        actionId = cursor.getString(cursor.getColumnIndexOrThrow(DayBookingsDatabaseHelper.COL_ACTION_ID)),
                        actionType = OfflineSyncAction.ActionType.valueOf(
                            cursor.getString(cursor.getColumnIndexOrThrow(DayBookingsDatabaseHelper.COL_ACTION_TYPE))
                        ),
                        venueId = cursor.getLong(cursor.getColumnIndexOrThrow(DayBookingsDatabaseHelper.COL_VENUE_ID)),
                        date = cursor.getString(cursor.getColumnIndexOrThrow(DayBookingsDatabaseHelper.COL_DATE)),
                        payloadJson = cursor.getString(cursor.getColumnIndexOrThrow(DayBookingsDatabaseHelper.COL_PAYLOAD_JSON)),
                        createdAt = cursor.getLong(cursor.getColumnIndexOrThrow(DayBookingsDatabaseHelper.COL_CREATED_AT)),
                        status = OfflineSyncAction.SyncStatus.valueOf(
                            cursor.getString(cursor.getColumnIndexOrThrow(DayBookingsDatabaseHelper.COL_STATUS))
                        ),
                        retryCount = cursor.getInt(cursor.getColumnIndexOrThrow(DayBookingsDatabaseHelper.COL_RETRY_COUNT)),
                        lastError = cursor.getString(cursor.getColumnIndexOrThrow(DayBookingsDatabaseHelper.COL_LAST_ERROR)),
                    )
                )
            }
        } catch (e: Exception) {
            e.printStackTrace()
        } finally {
            cursor?.close()
        }
        list
    }

    suspend fun updateActionStatus(actionId: String, status: OfflineSyncAction.SyncStatus, error: String? = null): Unit = withContext(Dispatchers.IO) {
        val db = dbHelper.writableDatabase
        val values = ContentValues().apply {
            put(DayBookingsDatabaseHelper.COL_STATUS, status.name)
            if (error != null) {
                put(DayBookingsDatabaseHelper.COL_LAST_ERROR, error)
            }
        }
        db.update(
            DayBookingsDatabaseHelper.TABLE_OFFLINE_QUEUE,
            values,
            "${DayBookingsDatabaseHelper.COL_ACTION_ID} = ?",
            arrayOf(actionId)
        )
    }

    suspend fun removeAction(actionId: String): Unit = withContext(Dispatchers.IO) {
        val db = dbHelper.writableDatabase
        db.delete(
            DayBookingsDatabaseHelper.TABLE_OFFLINE_QUEUE,
            "${DayBookingsDatabaseHelper.COL_ACTION_ID} = ?",
            arrayOf(actionId)
        )
    }

    suspend fun getPendingActionCount(): Int = withContext(Dispatchers.IO) {
        val db = dbHelper.readableDatabase
        var cursor: Cursor? = null
        try {
            cursor = db.rawQuery(
                "SELECT COUNT(*) FROM ${DayBookingsDatabaseHelper.TABLE_OFFLINE_QUEUE} WHERE ${DayBookingsDatabaseHelper.COL_STATUS} != ?",
                arrayOf(OfflineSyncAction.SyncStatus.SYNCED.name)
            )
            if (cursor.moveToFirst()) {
                return@withContext cursor.getInt(0)
            }
        } catch (e: Exception) {
            e.printStackTrace()
        } finally {
            cursor?.close()
        }
        0
    }

    // --- JSON Serialization Helpers ---

    private fun serializeDayGrid(grid: DayGrid): JSONObject {
        val json = JSONObject()
        json.put("date", grid.date)
        json.put("venueName", grid.venueName)
        json.put("isBlocked", grid.isBlocked)

        val courtsArr = JSONArray()
        for (c in grid.courts) {
            val co = JSONObject()
            co.put("id", c.id)
            co.put("name", c.name)
            co.put("sports", JSONArray(c.sports))
            courtsArr.put(co)
        }
        json.put("courts", courtsArr)

        val slotsArr = JSONArray()
        for (s in grid.slots) {
            val so = JSONObject()
            so.put("slotId", s.slotId)
            so.put("label", s.label)
            so.put("time", s.time)
            so.put("price", s.price)
            so.put("capacity", s.capacity)
            so.put("booked", s.booked)
            so.put("available", s.available)
            so.put("isOpen", s.isOpen)
            so.put("sports", JSONArray(s.sports))

            val bookingsArr = JSONArray()
            for (b in s.bookings) {
                val bo = JSONObject()
                bo.put("id", b.id)
                bo.put("customer", b.customer)
                bo.put("phone", b.phone)
                bo.put("channel", b.channel)
                bo.put("status", b.status)
                bo.put("checkedIn", b.checkedIn)
                bo.put("amount", b.amount)
                bo.put("amountPaid", b.amountPaid)
                bo.put("paymentStatus", b.paymentStatus)
                bookingsArr.put(bo)
            }
            so.put("bookings", bookingsArr)

            val cellsArr = JSONArray()
            for (c in s.courts) {
                val co = JSONObject()
                co.put("courtId", c.courtId)
                co.put("booked", c.booked)
                co.put("isBooked", c.isBooked)
                co.put("isHeld", c.isHeld)
                co.put("price", c.price)
                co.put("isPeak", c.isPeak)
                co.put("allowed", c.allowed)
                val cBookingsArr = JSONArray()
                for (b in c.bookings) {
                    val bo = JSONObject()
                    bo.put("id", b.id)
                    bo.put("customer", b.customer)
                    bo.put("phone", b.phone)
                    bo.put("channel", b.channel)
                    bo.put("status", b.status)
                    bo.put("checkedIn", b.checkedIn)
                    bo.put("amount", b.amount)
                    bo.put("amountPaid", b.amountPaid)
                    bo.put("paymentStatus", b.paymentStatus)
                    cBookingsArr.put(bo)
                }
                co.put("bookings", cBookingsArr)
                cellsArr.put(co)
            }
            so.put("courts", cellsArr)
            slotsArr.put(so)
        }
        json.put("slots", slotsArr)
        return json
    }

    private fun deserializeDayGrid(json: JSONObject): DayGrid {
        val date = json.optString("date")
        val venueName = json.optString("venueName", "Venue")
        val isBlocked = json.optBoolean("isBlocked", false)

        val courts = mutableListOf<CourtCol>()
        val courtsArr = json.optJSONArray("courts")
        if (courtsArr != null) {
            for (i in 0 until courtsArr.length()) {
                val co = courtsArr.getJSONObject(i)
                val sports = mutableListOf<String>()
                val sportsArr = co.optJSONArray("sports")
                if (sportsArr != null) {
                    for (j in 0 until sportsArr.length()) sports.add(sportsArr.optString(j))
                }
                courts.add(CourtCol(co.optLong("id"), co.optString("name"), sports))
            }
        }

        val slots = mutableListOf<DaySlot>()
        val slotsArr = json.optJSONArray("slots")
        if (slotsArr != null) {
            for (i in 0 until slotsArr.length()) {
                val so = slotsArr.getJSONObject(i)
                val sBookings = mutableListOf<DayBooking>()
                val bArr = so.optJSONArray("bookings")
                if (bArr != null) {
                    for (j in 0 until bArr.length()) {
                        val bo = bArr.getJSONObject(j)
                        sBookings.add(
                            DayBooking(
                                id = bo.optLong("id"),
                                customer = bo.optString("customer"),
                                phone = bo.optString("phone").takeIf { it.isNotBlank() },
                                channel = bo.optString("channel", "online"),
                                status = bo.optString("status", "CONFIRMED"),
                                checkedIn = bo.optInt("checkedIn"),
                                amount = bo.optDouble("amount", 0.0),
                                amountPaid = bo.optDouble("amountPaid", 0.0),
                                paymentStatus = bo.optString("paymentStatus", "unpaid"),
                            )
                        )
                    }
                }

                val cells = mutableListOf<CourtCell>()
                val cellsArr = so.optJSONArray("courts")
                if (cellsArr != null) {
                    for (j in 0 until cellsArr.length()) {
                        val co = cellsArr.getJSONObject(j)
                        val cBookings = mutableListOf<DayBooking>()
                        val cbArr = co.optJSONArray("bookings")
                        if (cbArr != null) {
                            for (k in 0 until cbArr.length()) {
                                val bo = cbArr.getJSONObject(k)
                                cBookings.add(
                                    DayBooking(
                                        id = bo.optLong("id"),
                                        customer = bo.optString("customer"),
                                        phone = bo.optString("phone").takeIf { it.isNotBlank() },
                                        channel = bo.optString("channel", "online"),
                                        status = bo.optString("status", "CONFIRMED"),
                                        checkedIn = bo.optInt("checkedIn"),
                                        amount = bo.optDouble("amount", 0.0),
                                        amountPaid = bo.optDouble("amountPaid", 0.0),
                                        paymentStatus = bo.optString("paymentStatus", "unpaid"),
                                    )
                                )
                            }
                        }
                        cells.add(
                            CourtCell(
                                courtId = co.optLong("courtId"),
                                booked = co.optInt("booked"),
                                isBooked = co.optBoolean("isBooked"),
                                isHeld = co.optBoolean("isHeld"),
                                price = co.optDouble("price", 0.0),
                                isPeak = co.optBoolean("isPeak"),
                                allowed = co.optBoolean("allowed", true),
                                bookings = cBookings
                            )
                        )
                    }
                }

                val sports = mutableListOf<String>()
                val sportsArr = so.optJSONArray("sports")
                if (sportsArr != null) {
                    for (j in 0 until sportsArr.length()) sports.add(sportsArr.optString(j))
                }

                slots.add(
                    DaySlot(
                        slotId = so.optLong("slotId"),
                        label = so.optString("label"),
                        time = so.optString("time").takeIf { it.isNotBlank() },
                        price = so.optDouble("price", 0.0),
                        capacity = so.optInt("capacity", 1),
                        booked = so.optInt("booked", 0),
                        available = so.optInt("available", 1),
                        isOpen = so.optBoolean("isOpen", true),
                        sports = sports,
                        bookings = sBookings,
                        courts = cells,
                    )
                )
            }
        }

        return DayGrid(date, venueName, isBlocked, slots, courts)
    }

    private fun serializeBooking(b: BookingSummary): JSONObject {
        val o = JSONObject()
        o.put("id", b.id)
        o.put("ticketCode", b.ticketCode)
        o.put("quantity", b.quantity)
        o.put("amount", b.amount)
        o.put("status", b.status)
        o.put("checkedIn", b.checkedIn)
        o.put("label", b.label)
        o.put("branch", b.branch)
        o.put("customer", b.customer)
        o.put("channel", b.channel)
        o.put("paymentStatus", b.paymentStatus)
        o.put("amountPaid", b.amountPaid)
        o.put("paymentMethod", b.paymentMethod)
        o.put("slotDate", b.slotDate)
        o.put("slotLabel", b.slotLabel)
        return o
    }

    private fun deserializeBooking(o: JSONObject): BookingSummary {
        return BookingSummary(
            id = o.optLong("id"),
            ticketCode = o.optString("ticketCode").takeIf { it.isNotBlank() },
            quantity = o.optInt("quantity", 1),
            amount = o.optDouble("amount", 0.0),
            status = o.optString("status").takeIf { it.isNotBlank() },
            checkedIn = o.optInt("checkedIn", 0),
            label = o.optString("label").takeIf { it.isNotBlank() },
            branch = o.optString("branch").takeIf { it.isNotBlank() },
            customer = o.optString("customer", "Guest"),
            channel = o.optString("channel", "online"),
            paymentStatus = o.optString("paymentStatus", "paid"),
            amountPaid = o.optDouble("amountPaid", 0.0),
            paymentMethod = o.optString("paymentMethod").takeIf { it.isNotBlank() },
            slotDate = o.optString("slotDate").takeIf { it.isNotBlank() },
            slotLabel = o.optString("slotLabel").takeIf { it.isNotBlank() },
        )
    }
}
