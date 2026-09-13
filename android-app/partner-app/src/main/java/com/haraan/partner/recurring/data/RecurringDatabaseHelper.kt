package com.haraan.partner.recurring.data

import android.content.Context
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper

class RecurringDatabaseHelper(context: Context) :
    SQLiteOpenHelper(context, DATABASE_NAME, null, DATABASE_VERSION) {

    override fun onCreate(db: SQLiteDatabase) {
        db.execSQL(
            """
            CREATE TABLE IF NOT EXISTS recurring_cache (
                venue_id INTEGER PRIMARY KEY,
                contracts_json TEXT NOT NULL,
                dashboard_json TEXT NOT NULL,
                updated_at INTEGER NOT NULL
            )
            """.trimIndent()
        )

        db.execSQL(
            """
            CREATE TABLE IF NOT EXISTS offline_recurring_actions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                action_type TEXT NOT NULL,
                venue_id INTEGER NOT NULL,
                contract_id INTEGER,
                payload_json TEXT NOT NULL,
                created_at INTEGER NOT NULL
            )
            """.trimIndent()
        )
    }

    override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) {
        db.execSQL("DROP TABLE IF EXISTS recurring_cache")
        db.execSQL("DROP TABLE IF EXISTS offline_recurring_actions")
        onCreate(db)
    }

    companion object {
        private const val DATABASE_NAME = "haraan_recurring.db"
        private const val DATABASE_VERSION = 1
    }
}
