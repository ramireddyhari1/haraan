package com.haraan.partner.operations.data

import android.content.Context
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper

class OperationsDatabaseHelper(context: Context) : SQLiteOpenHelper(context, DATABASE_NAME, null, DATABASE_VERSION) {

    companion object {
        const val DATABASE_NAME = "haraan_operations.db"
        const val DATABASE_VERSION = 1

        const val TABLE_OVERVIEW_CACHE = "operations_overview_cache"
        const val TABLE_HEATMAP_CACHE = "occupancy_heatmap_cache"
    }

    override fun onCreate(db: SQLiteDatabase) {
        db.execSQL(
            """
            CREATE TABLE $TABLE_OVERVIEW_CACHE (
                venue_id INTEGER PRIMARY KEY,
                overview_json TEXT NOT NULL,
                updated_at INTEGER NOT NULL
            )
            """.trimIndent()
        )

        db.execSQL(
            """
            CREATE TABLE $TABLE_HEATMAP_CACHE (
                venue_id INTEGER PRIMARY KEY,
                heatmap_json TEXT NOT NULL,
                updated_at INTEGER NOT NULL
            )
            """.trimIndent()
        )
    }

    override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) {
        db.execSQL("DROP TABLE IF EXISTS $TABLE_OVERVIEW_CACHE")
        db.execSQL("DROP TABLE IF EXISTS $TABLE_HEATMAP_CACHE")
        onCreate(db)
    }
}