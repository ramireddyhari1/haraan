package com.haraan.partner.whatsapp.data

import android.content.ContentValues
import android.content.Context
import android.database.sqlite.SQLiteDatabase
import com.haraan.partner.whatsapp.model.*
import org.json.JSONArray
import org.json.JSONObject

class WhatsAppLocalDataSource(context: Context) {

    private val dbHelper = WhatsAppDatabaseHelper(context)

    fun cacheConversations(venueId: Long, statusFilter: String, items: List<ConversationSummary>) {
        val db = dbHelper.writableDatabase
        val arr = JSONArray()
        for (item in items) {
            val obj = JSONObject().apply {
                put("id", item.id)
                put("venue_id", item.venueId)
                put("phone_number", item.phoneNumber)
                put("customer_name", item.customerName ?: "")
                put("status", item.status)
                put("assigned_staff_id", item.assignedStaffId ?: JSONObject.NULL)
                put("assigned_staff_name", item.assignedStaffName ?: "")
                put("last_message_at", item.lastMessageAt ?: "")
                put("last_message_preview", item.lastMessagePreview ?: "")
                put("last_message_sender", item.lastMessageSender)
                put("unread_count", item.unreadCount)
                put("window_expires_at", item.windowExpiresAt ?: "")
                put("is_window_active", item.isWindowActive)
                put("active_booking_id", item.activeBookingId ?: JSONObject.NULL)
            }
            arr.put(obj)
        }

        val values = ContentValues().apply {
            put("venue_id", venueId)
            put("status_filter", statusFilter)
            put("conversations_json", arr.toString())
            put("updated_at", System.currentTimeMillis())
        }
        db.insertWithOnConflict(
            WhatsAppDatabaseHelper.TABLE_CONVERSATIONS_CACHE,
            null,
            values,
            SQLiteDatabase.CONFLICT_REPLACE
        )
    }

    fun getCachedConversations(venueId: Long, statusFilter: String): List<ConversationSummary>? {
        val db = dbHelper.readableDatabase
        val cursor = db.query(
            WhatsAppDatabaseHelper.TABLE_CONVERSATIONS_CACHE,
            arrayOf("conversations_json"),
            "venue_id = ? AND status_filter = ?",
            arrayOf(venueId.toString(), statusFilter),
            null, null, null
        )

        cursor.use {
            if (it.moveToFirst()) {
                val jsonStr = it.getString(0)
                val arr = JSONArray(jsonStr)
                val list = mutableListOf<ConversationSummary>()
                for (i in 0 until arr.length()) {
                    val obj = arr.getJSONObject(i)
                    list.add(
                        ConversationSummary(
                            id = obj.getLong("id"),
                            venueId = obj.getLong("venue_id"),
                            phoneNumber = obj.getString("phone_number"),
                            customerName = obj.optString("customer_name").takeIf { s -> s.isNotEmpty() },
                            status = obj.getString("status"),
                            assignedStaffId = if (obj.isNull("assigned_staff_id")) null else obj.getLong("assigned_staff_id"),
                            assignedStaffName = obj.optString("assigned_staff_name").takeIf { s -> s.isNotEmpty() },
                            lastMessageAt = obj.optString("last_message_at").takeIf { s -> s.isNotEmpty() },
                            lastMessagePreview = obj.optString("last_message_preview").takeIf { s -> s.isNotEmpty() },
                            lastMessageSender = obj.optString("last_message_sender", "customer"),
                            unreadCount = obj.optInt("unread_count", 0),
                            windowExpiresAt = obj.optString("window_expires_at").takeIf { s -> s.isNotEmpty() },
                            isWindowActive = obj.optBoolean("is_window_active", true),
                            activeBookingId = if (obj.isNull("active_booking_id")) null else obj.getLong("active_booking_id")
                        )
                    )
                }
                return list
            }
        }
        return null
    }

    fun cacheMessages(conversationId: Long, messages: List<ChatMessage>) {
        val db = dbHelper.writableDatabase
        val arr = JSONArray()
        for (m in messages) {
            val obj = JSONObject().apply {
                put("id", m.id)
                put("conversation_id", m.conversationId)
                put("direction", m.direction)
                put("sender_type", m.senderType)
                put("message_type", m.messageType)
                put("body", m.body)
                put("media_url", m.mediaUrl ?: "")
                put("delivery_status", m.deliveryStatus)
                put("created_at", m.createdAt)
                put("ticket_code", m.ticketCode ?: "")
                put("ticket_url", m.ticketUrl ?: "")
                put("payment_url", m.paymentUrl ?: "")
            }
            arr.put(obj)
        }

        val values = ContentValues().apply {
            put("conversation_id", conversationId)
            put("messages_json", arr.toString())
            put("updated_at", System.currentTimeMillis())
        }
        db.insertWithOnConflict(
            WhatsAppDatabaseHelper.TABLE_MESSAGES_CACHE,
            null,
            values,
            SQLiteDatabase.CONFLICT_REPLACE
        )
    }

    fun getCachedMessages(conversationId: Long): List<ChatMessage>? {
        val db = dbHelper.readableDatabase
        val cursor = db.query(
            WhatsAppDatabaseHelper.TABLE_MESSAGES_CACHE,
            arrayOf("messages_json"),
            "conversation_id = ?",
            arrayOf(conversationId.toString()),
            null, null, null
        )

        cursor.use {
            if (it.moveToFirst()) {
                val jsonStr = it.getString(0)
                val arr = JSONArray(jsonStr)
                val list = mutableListOf<ChatMessage>()
                for (i in 0 until arr.length()) {
                    val obj = arr.getJSONObject(i)
                    list.add(
                        ChatMessage(
                            id = obj.getLong("id"),
                            conversationId = obj.getLong("conversation_id"),
                            direction = obj.getString("direction"),
                            senderType = obj.getString("sender_type"),
                            messageType = obj.getString("message_type"),
                            body = obj.getString("body"),
                            mediaUrl = obj.optString("media_url").takeIf { s -> s.isNotEmpty() },
                            deliveryStatus = obj.optString("delivery_status", "sent"),
                            createdAt = obj.optString("created_at", ""),
                            ticketCode = obj.optString("ticket_code").takeIf { s -> s.isNotEmpty() },
                            ticketUrl = obj.optString("ticket_url").takeIf { s -> s.isNotEmpty() },
                            paymentUrl = obj.optString("payment_url").takeIf { s -> s.isNotEmpty() }
                        )
                    )
                }
                return list
            }
        }
        return null
    }

    fun enqueuePendingAction(actionType: String, conversationId: Long, payloadJson: String) {
        val db = dbHelper.writableDatabase
        val values = ContentValues().apply {
            put("action_type", actionType)
            put("conversation_id", conversationId)
            put("payload_json", payloadJson)
            put("created_at", System.currentTimeMillis())
        }
        db.insert(WhatsAppDatabaseHelper.TABLE_PENDING_ACTIONS, null, values)
    }
}