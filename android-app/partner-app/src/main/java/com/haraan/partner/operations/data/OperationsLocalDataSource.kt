package com.haraan.partner.operations.data

import android.content.ContentValues
import android.content.Context
import android.database.sqlite.SQLiteDatabase

class OperationsLocalDataSource(context: Context) {

    private val dbHelper = OperationsDatabaseHelper(context)

    fun cacheOverview(venueId: Long, jsonStr: String) {
        val db = dbHelper.writableDatabase
        val values = ContentValues().apply {
            put("venue_id", venueId)
            put("overview_json", jsonStr)
            put("updated_at", System.currentTimeMillis())
        }
        db.insertWithOnConflict(
            OperationsDatabaseHelper.TABLE_OVERVIEW_CACHE,
            null,
            values,
            SQLiteDatabase.CONFLICT_REPLACE
        )
    }

    fun getCachedOverview(venueId: Long): String? {
        val db = dbHelper.readableDatabase
        val cursor = db.query(
            OperationsDatabaseHelper.TABLE_OVERVIEW_CACHE,
            arrayOf("overview_json"),
            "venue_id = ?",
            arrayOf(venueId.toString()),
            null, null, null
        )
        cursor.use {
            if (it.moveToFirst()) return it.getString(0)
        }
        return null
    }
}