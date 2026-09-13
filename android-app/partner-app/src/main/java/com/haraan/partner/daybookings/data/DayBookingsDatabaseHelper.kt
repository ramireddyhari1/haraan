package com.haraan.partner.daybookings.data

import android.content.Context
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper

class DayBookingsDatabaseHelper(context: Context) : SQLiteOpenHelper(
    context,
    DATABASE_NAME,
    null,
    DATABASE_VERSION
) {
    companion object {
        const val DATABASE_NAME = "haraan_partner_day_bookings.db"
        const val DATABASE_VERSION = 1

        const val TABLE_GRID_CACHE = "day_grid_cache"
        const val TABLE_BOOKINGS_CACHE = "day_bookings_cache"
        const val TABLE_OFFLINE_QUEUE = "offline_action_queue"

        const val COL_VENUE_ID = "venue_id"
        const val COL_DATE = "date"
        const val COL_DATA_JSON = "data_json"
        const val COL_UPDATED_AT = "updated_at"

        const val COL_ACTION_ID = "action_id"
        const val COL_ACTION_TYPE = "action_type"
        const val COL_PAYLOAD_JSON = "payload_json"
        const val COL_STATUS = "status"
        const val COL_CREATED_AT = "created_at"
        const val COL_RETRY_COUNT = "retry_count"
        const val COL_LAST_ERROR = "last_error"
    }

    override fun onCreate(db: SQLiteDatabase) {
        db.execSQL(
            """
            CREATE TABLE IF NOT EXISTS $TABLE_GRID_CACHE (
                $COL_VENUE_ID INTEGER NOT NULL,
                $COL_DATE TEXT NOT NULL,
                $COL_DATA_JSON TEXT NOT NULL,
                $COL_UPDATED_AT INTEGER NOT NULL,
                PRIMARY KEY ($COL_VENUE_ID, $COL_DATE)
            )
            """.trimIndent()
        )

        db.execSQL(
            """
            CREATE TABLE IF NOT EXISTS $TABLE_BOOKINGS_CACHE (
                $COL_VENUE_ID INTEGER NOT NULL,
                $COL_DATE TEXT NOT NULL,
                $COL_DATA_JSON TEXT NOT NULL,
                $COL_UPDATED_AT INTEGER NOT NULL,
                PRIMARY KEY ($COL_VENUE_ID, $COL_DATE)
            )
            """.trimIndent()
        )

        db.execSQL(
            """
            CREATE TABLE IF NOT EXISTS $TABLE_OFFLINE_QUEUE (
                $COL_ACTION_ID TEXT PRIMARY KEY,
                $COL_ACTION_TYPE TEXT NOT NULL,
                $COL_VENUE_ID INTEGER NOT NULL,
                $COL_DATE TEXT NOT NULL,
                $COL_PAYLOAD_JSON TEXT NOT NULL,
                $COL_STATUS TEXT NOT NULL,
                $COL_CREATED_AT INTEGER NOT NULL,
                $COL_RETRY_COUNT INTEGER NOT NULL DEFAULT 0,
                $COL_LAST_ERROR TEXT
            )
            """.trimIndent()
        )
    }

    override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) {
        db.execSQL("DROP TABLE IF EXISTS $TABLE_GRID_CACHE")
        db.execSQL("DROP TABLE IF EXISTS $TABLE_BOOKINGS_CACHE")
        db.execSQL("DROP TABLE IF EXISTS $TABLE_OFFLINE_QUEUE")
        onCreate(db)
    }
}
