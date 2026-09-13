package com.haraan.partner.register.data

import android.content.ContentValues
import android.content.Context
import android.database.Cursor
import com.haraan.partner.register.model.OfflineShiftAction
import com.haraan.partner.register.model.ShiftDropItem
import com.haraan.partner.register.model.ShiftPaymentItem
import com.haraan.partner.register.model.ShiftSessionUiModel
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject

class ShiftLocalDataSource(context: Context) {
    private val dbHelper = ShiftDatabaseHelper(context)

    suspend fun saveShiftCache(venueId: Long, shift: ShiftSessionUiModel): Unit = withContext(Dispatchers.IO) {
        val db = dbHelper.writableDatabase
        val values = ContentValues().apply {
            put(ShiftDatabaseHelper.COL_VENUE_ID, venueId)
            put(ShiftDatabaseHelper.COL_DATA_JSON, serializeShift(shift).toString())
            put(ShiftDatabaseHelper.COL_UPDATED_AT, System.currentTimeMillis())
        }
        db.insertWithOnConflict(
            ShiftDatabaseHelper.TABLE_SHIFT_CACHE,
            null,
            values,
            android.database.sqlite.SQLiteDatabase.CONFLICT_REPLACE
        )
    }

    suspend fun getCachedShift(venueId: Long): ShiftSessionUiModel? = withContext(Dispatchers.IO) {
        val db = dbHelper.readableDatabase
        var cursor: Cursor? = null
        try {
            cursor = db.query(
                ShiftDatabaseHelper.TABLE_SHIFT_CACHE,
                arrayOf(ShiftDatabaseHelper.COL_DATA_JSON),
                "${ShiftDatabaseHelper.COL_VENUE_ID} = ?",
                arrayOf(venueId.toString()),
                null, null, null
            )
            if (cursor.moveToFirst()) {
                val jsonStr = cursor.getString(0)
                return@withContext deserializeShift(JSONObject(jsonStr))
            }
        } catch (e: Exception) {
            e.printStackTrace()
        } finally {
            cursor?.close()
        }
        null
    }

    suspend fun clearShiftCache(venueId: Long): Unit = withContext(Dispatchers.IO) {
        val db = dbHelper.writableDatabase
        db.delete(
            ShiftDatabaseHelper.TABLE_SHIFT_CACHE,
            "${ShiftDatabaseHelper.COL_VENUE_ID} = ?",
            arrayOf(venueId.toString())
        )
    }

    suspend fun enqueueAction(action: OfflineShiftAction): Unit = withContext(Dispatchers.IO) {
        val db = dbHelper.writableDatabase
        val values = ContentValues().apply {
            put(ShiftDatabaseHelper.COL_ACTION_ID, action.actionId)
            put(ShiftDatabaseHelper.COL_ACTION_TYPE, action.actionType.name)
            put(ShiftDatabaseHelper.COL_VENUE_ID, action.venueId)
            put(ShiftDatabaseHelper.COL_PAYLOAD_JSON, action.payloadJson)
            put(ShiftDatabaseHelper.COL_STATUS, action.status.name)
            put(ShiftDatabaseHelper.COL_CREATED_AT, action.createdAt)
        }
        db.insertWithOnConflict(
            ShiftDatabaseHelper.TABLE_OFFLINE_SHIFT_ACTIONS,
            null,
            values,
            android.database.sqlite.SQLiteDatabase.CONFLICT_REPLACE
        )
    }

    suspend fun getPendingActions(): List<OfflineShiftAction> = withContext(Dispatchers.IO) {
        val db = dbHelper.readableDatabase
        val list = mutableListOf<OfflineShiftAction>()
        var cursor: Cursor? = null
        try {
            cursor = db.query(
                ShiftDatabaseHelper.TABLE_OFFLINE_SHIFT_ACTIONS,
                null,
                "${ShiftDatabaseHelper.COL_STATUS} != ?",
                arrayOf(OfflineShiftAction.SyncStatus.SYNCED.name),
                null, null,
                "${ShiftDatabaseHelper.COL_CREATED_AT} ASC"
            )
            while (cursor.moveToNext()) {
                list.add(
                    OfflineShiftAction(
                        actionId = cursor.getString(cursor.getColumnIndexOrThrow(ShiftDatabaseHelper.COL_ACTION_ID)),
                        actionType = OfflineShiftAction.ActionType.valueOf(
                            cursor.getString(cursor.getColumnIndexOrThrow(ShiftDatabaseHelper.COL_ACTION_TYPE))
                        ),
                        venueId = cursor.getLong(cursor.getColumnIndexOrThrow(ShiftDatabaseHelper.COL_VENUE_ID)),
                        payloadJson = cursor.getString(cursor.getColumnIndexOrThrow(ShiftDatabaseHelper.COL_PAYLOAD_JSON)),
                        createdAt = cursor.getLong(cursor.getColumnIndexOrThrow(ShiftDatabaseHelper.COL_CREATED_AT)),
                        status = OfflineShiftAction.SyncStatus.valueOf(
                            cursor.getString(cursor.getColumnIndexOrThrow(ShiftDatabaseHelper.COL_STATUS))
                        ),
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

    suspend fun removeAction(actionId: String): Unit = withContext(Dispatchers.IO) {
        val db = dbHelper.writableDatabase
        db.delete(
            ShiftDatabaseHelper.TABLE_OFFLINE_SHIFT_ACTIONS,
            "${ShiftDatabaseHelper.COL_ACTION_ID} = ?",
            arrayOf(actionId)
        )
    }

    private fun serializeShift(shift: ShiftSessionUiModel): JSONObject {
        val o = JSONObject()
        o.put("shiftId", shift.shiftId)
        o.put("venueId", shift.venueId)
        o.put("venueName", shift.venueName)
        o.put("isOpen", shift.isOpen)
        o.put("staffId", shift.staffId)
        o.put("staffName", shift.staffName)
        o.put("openedAt", shift.openedAt)
        o.put("closedAt", shift.closedAt)
        o.put("openingFloat", shift.openingFloat)
        o.put("cashCollected", shift.cashCollected)
        o.put("upiCollected", shift.upiCollected)
        o.put("cardCollected", shift.cardCollected)
        o.put("totalDrops", shift.totalDrops)
        o.put("expectedCash", shift.expectedCash)
        o.put("countedCash", shift.countedCash)
        o.put("variance", shift.variance)
        o.put("varianceLabel", shift.varianceLabel)
        o.put("note", shift.note)

        val payArr = JSONArray()
        for (p in shift.payments) {
            val po = JSONObject()
            po.put("id", p.id)
            po.put("amount", p.amount)
            po.put("method", p.method)
            po.put("customerName", p.customerName)
            po.put("time", p.time)
            payArr.put(po)
        }
        o.put("payments", payArr)

        val dropArr = JSONArray()
        for (d in shift.drops) {
            val do_ = JSONObject()
            do_.put("id", d.id)
            do_.put("amount", d.amount)
            do_.put("category", d.category)
            do_.put("reason", d.reason)
            do_.put("staffName", d.staffName)
            do_.put("time", d.time)
            dropArr.put(do_)
        }
        o.put("drops", dropArr)

        return o
    }

    private fun deserializeShift(o: JSONObject): ShiftSessionUiModel {
        val payments = mutableListOf<ShiftPaymentItem>()
        val pArr = o.optJSONArray("payments")
        if (pArr != null) {
            for (i in 0 until pArr.length()) {
                val po = pArr.getJSONObject(i)
                payments.add(
                    ShiftPaymentItem(
                        id = po.optLong("id"),
                        amount = po.optDouble("amount", 0.0),
                        method = po.optString("method", "cash"),
                        customerName = po.optString("customerName", "Guest"),
                        time = po.optString("time", ""),
                    )
                )
            }
        }

        val drops = mutableListOf<ShiftDropItem>()
        val dArr = o.optJSONArray("drops")
        if (dArr != null) {
            for (i in 0 until dArr.length()) {
                val do_ = dArr.getJSONObject(i)
                drops.add(
                    ShiftDropItem(
                        id = do_.optLong("id"),
                        amount = do_.optDouble("amount", 0.0),
                        category = do_.optString("category", "expense"),
                        reason = do_.optString("reason").takeIf { it.isNotBlank() },
                        staffName = do_.optString("staffName", "Staff"),
                        time = do_.optString("time", ""),
                    )
                )
            }
        }

        return ShiftSessionUiModel(
            shiftId = o.optLong("shiftId"),
            venueId = o.optLong("venueId"),
            venueName = o.optString("venueName", "Venue"),
            isOpen = o.optBoolean("isOpen", true),
            staffId = o.optLong("staffId"),
            staffName = o.optString("staffName", "Staff"),
            openedAt = o.optString("openedAt"),
            closedAt = o.optString("closedAt").takeIf { it.isNotBlank() },
            openingFloat = o.optDouble("openingFloat", 0.0),
            cashCollected = o.optDouble("cashCollected", 0.0),
            upiCollected = o.optDouble("upiCollected", 0.0),
            cardCollected = o.optDouble("cardCollected", 0.0),
            totalDrops = o.optDouble("totalDrops", 0.0),
            expectedCash = o.optDouble("expectedCash", 0.0),
            countedCash = if (o.isNull("countedCash")) null else o.optDouble("countedCash"),
            variance = if (o.isNull("variance")) null else o.optDouble("variance"),
            varianceLabel = o.optString("varianceLabel", "Square"),
            note = o.optString("note").takeIf { it.isNotBlank() },
            payments = payments,
            drops = drops,
        )
    }
}
