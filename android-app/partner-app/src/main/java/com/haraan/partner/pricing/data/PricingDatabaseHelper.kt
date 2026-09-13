package com.haraan.partner.pricing.data

import android.content.Context
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper

class PricingDatabaseHelper(context: Context) : SQLiteOpenHelper(context, DATABASE_NAME, null, DATABASE_VERSION) {

    companion object {
        const val DATABASE_NAME = "haraan_pricing.db"
        const val DATABASE_VERSION = 1

        const val TABLE_MATRIX_CACHE = "pricing_matrix_cache"
        const val TABLE_RULES_CACHE = "pricing_rules_cache"
        const val TABLE_HIERARCHY_CACHE = "court_hierarchy_cache"
        const val TABLE_OFFLINE_ACTIONS = "offline_pricing_actions"
    }

    override fun onCreate(db: SQLiteDatabase) {
        db.execSQL(
            """
            CREATE TABLE $TABLE_MATRIX_CACHE (
                venue_id INTEGER NOT NULL,
                court_id INTEGER NOT NULL,
                matrix_json TEXT NOT NULL,
                updated_at INTEGER NOT NULL,
                PRIMARY KEY (venue_id, court_id)
            )
            """.trimIndent()
        )

        db.execSQL(
            """
            CREATE TABLE $TABLE_RULES_CACHE (
                venue_id INTEGER PRIMARY KEY,
                rules_json TEXT NOT NULL,
                updated_at INTEGER NOT NULL
            )
            """.trimIndent()
        )

        db.execSQL(
            """
            CREATE TABLE $TABLE_HIERARCHY_CACHE (
                venue_id INTEGER PRIMARY KEY,
                hierarchy_json TEXT NOT NULL,
                updated_at INTEGER NOT NULL
            )
            """.trimIndent()
        )

        db.execSQL(
            """
            CREATE TABLE $TABLE_OFFLINE_ACTIONS (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                action_type TEXT NOT NULL,
                venue_id INTEGER NOT NULL,
                payload_json TEXT NOT NULL,
                created_at INTEGER NOT NULL
            )
            """.trimIndent()
        )
    }

    override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) {
        db.execSQL("DROP TABLE IF EXISTS $TABLE_MATRIX_CACHE")
        db.execSQL("DROP TABLE IF EXISTS $TABLE_RULES_CACHE")
        db.execSQL("DROP TABLE IF EXISTS $TABLE_HIERARCHY_CACHE")
        db.execSQL("DROP TABLE IF EXISTS $TABLE_OFFLINE_ACTIONS")
        onCreate(db)
    }
}
