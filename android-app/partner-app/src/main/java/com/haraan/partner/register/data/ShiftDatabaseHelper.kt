package com.haraan.partner.register.data

import android.content.Context
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper

class ShiftDatabaseHelper(context: Context) : SQLiteOpenHelper(
    context,
    DATABASE_NAME,
    null,
    DATABASE_VERSION
) {
    companion object {
        const val DATABASE_NAME = "haraan_shift_register.db"
        const val DATABASE_VERSION = 1

        const val TABLE_SHIFT_CACHE = "shift_cache"
        const val TABLE_OFFLINE_SHIFT_ACTIONS = "offline_shift_actions"

        const val COL_VENUE_ID = "venue_id"
        const val COL_DATA_JSON = "data_json"
        const val COL_UPDATED_AT = "updated_at"

        const val COL_ACTION_ID = "action_id"
        const val COL_ACTION_TYPE = "action_type"
        const val COL_PAYLOAD_JSON = "payload_json"
        const val COL_STATUS = "status"
        const val COL_CREATED_AT = "created_at"
    }

    override fun onCreate(db: SQLiteDatabase) {
        db.execSQL(
            """
            CREATE TABLE IF NOT EXISTS $TABLE_SHIFT_CACHE (
                $COL_VENUE_ID INTEGER PRIMARY KEY,
                $COL_DATA_JSON TEXT NOT NULL,
                $COL_UPDATED_AT INTEGER NOT NULL
            )
            """.trimIndent()
        )

        db.execSQL(
            """
            CREATE TABLE IF NOT EXISTS $TABLE_OFFLINE_SHIFT_ACTIONS (
                $COL_ACTION_ID TEXT PRIMARY KEY,
                $COL_ACTION_TYPE TEXT NOT NULL,
                $COL_VENUE_ID INTEGER NOT NULL,
                $COL_PAYLOAD_JSON TEXT NOT NULL,
                $COL_STATUS TEXT NOT NULL,
                $COL_CREATED_AT INTEGER NOT NULL
            )
            """.trimIndent()
        )
    }

    override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) {
        db.execSQL("DROP TABLE IF EXISTS $TABLE_SHIFT_CACHE")
        db.execSQL("DROP TABLE IF EXISTS $TABLE_OFFLINE_SHIFT_ACTIONS")
        onCreate(db)
    }
}
