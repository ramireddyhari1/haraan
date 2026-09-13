package com.haraan.partner.whatsapp.data

import android.content.Context
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper

class WhatsAppDatabaseHelper(context: Context) : SQLiteOpenHelper(context, DATABASE_NAME, null, DATABASE_VERSION) {

    companion object {
        const val DATABASE_NAME = "haraan_whatsapp_desk.db"
        const val DATABASE_VERSION = 1

        const val TABLE_CONVERSATIONS_CACHE = "conversations_cache"
        const val TABLE_MESSAGES_CACHE = "messages_cache"
        const val TABLE_SUGGESTIONS_CACHE = "suggestions_cache"
        const val TABLE_QUICK_REPLIES_CACHE = "quick_replies_cache"
        const val TABLE_PENDING_ACTIONS = "pending_whatsapp_actions"
    }

    override fun onCreate(db: SQLiteDatabase) {
        db.execSQL(
            """
            CREATE TABLE $TABLE_CONVERSATIONS_CACHE (
                venue_id INTEGER NOT NULL,
                status_filter TEXT NOT NULL,
                conversations_json TEXT NOT NULL,
                updated_at INTEGER NOT NULL,
                PRIMARY KEY (venue_id, status_filter)
            )
            """.trimIndent()
        )

        db.execSQL(
            """
            CREATE TABLE $TABLE_MESSAGES_CACHE (
                conversation_id INTEGER PRIMARY KEY,
                messages_json TEXT NOT NULL,
                updated_at INTEGER NOT NULL
            )
            """.trimIndent()
        )

        db.execSQL(
            """
            CREATE TABLE $TABLE_SUGGESTIONS_CACHE (
                conversation_id INTEGER PRIMARY KEY,
                suggestion_json TEXT NOT NULL,
                updated_at INTEGER NOT NULL
            )
            """.trimIndent()
        )

        db.execSQL(
            """
            CREATE TABLE $TABLE_QUICK_REPLIES_CACHE (
                venue_id INTEGER PRIMARY KEY,
                replies_json TEXT NOT NULL,
                updated_at INTEGER NOT NULL
            )
            """.trimIndent()
        )

        db.execSQL(
            """
            CREATE TABLE $TABLE_PENDING_ACTIONS (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                action_type TEXT NOT NULL,
                conversation_id INTEGER NOT NULL,
                payload_json TEXT NOT NULL,
                created_at INTEGER NOT NULL
            )
            """.trimIndent()
        )
    }

    override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) {
        db.execSQL("DROP TABLE IF EXISTS $TABLE_CONVERSATIONS_CACHE")
        db.execSQL("DROP TABLE IF EXISTS $TABLE_MESSAGES_CACHE")
        db.execSQL("DROP TABLE IF EXISTS $TABLE_SUGGESTIONS_CACHE")
        db.execSQL("DROP TABLE IF EXISTS $TABLE_QUICK_REPLIES_CACHE")
        db.execSQL("DROP TABLE IF EXISTS $TABLE_PENDING_ACTIONS")
        onCreate(db)
    }
}