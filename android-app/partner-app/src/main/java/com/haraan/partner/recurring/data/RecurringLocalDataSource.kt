package com.haraan.partner.recurring.data

import android.content.ContentValues
import android.content.Context
import com.haraan.partner.recurring.model.*
import org.json.JSONArray
import org.json.JSONObject

class RecurringLocalDataSource(context: Context) {
    private val dbHelper = RecurringDatabaseHelper(context)

    fun saveCache(venueId: Long, contracts: List<StandingContractSummary>, dashboard: StandingDashboardMetrics?) {
        val db = dbHelper.writableDatabase
        val cv = ContentValues().apply {
            put("venue_id", venueId)
            put("contracts_json", serializeContracts(contracts))
            put("dashboard_json", serializeDashboard(dashboard))
            put("updated_at", System.currentTimeMillis())
        }
        db.insertWithOnConflict("recurring_cache", null, cv, android.database.sqlite.SQLiteDatabase.CONFLICT_REPLACE)
    }

    fun getCachedContracts(venueId: Long): List<StandingContractSummary>? {
        val db = dbHelper.readableDatabase
        val cursor = db.rawQuery(
            "SELECT contracts_json FROM recurring_cache WHERE venue_id = ?",
            arrayOf(venueId.toString())
        )
        return cursor.use {
            if (it.moveToFirst()) {
                val json = it.getString(0)
                deserializeContracts(json)
            } else {
                null
            }
        }
    }

    fun getCachedDashboard(venueId: Long): StandingDashboardMetrics? {
        val db = dbHelper.readableDatabase
        val cursor = db.rawQuery(
            "SELECT dashboard_json FROM recurring_cache WHERE venue_id = ?",
            arrayOf(venueId.toString())
        )
        return cursor.use {
            if (it.moveToFirst()) {
                val json = it.getString(0)
                deserializeDashboard(json)
            } else {
                null
            }
        }
    }

    fun enqueueOfflineAction(action: OfflineRecurringAction): Long {
        val db = dbHelper.writableDatabase
        val cv = ContentValues().apply {
            put("action_type", action.actionType)
            put("venue_id", action.venueId)
            put("contract_id", action.contractId)
            put("payload_json", action.payloadJson)
            put("created_at", action.createdAt)
        }
        return db.insert("offline_recurring_actions", null, cv)
    }

    fun getPendingActions(): List<OfflineRecurringAction> {
        val db = dbHelper.readableDatabase
        val cursor = db.rawQuery(
            "SELECT id, action_type, venue_id, contract_id, payload_json, created_at FROM offline_recurring_actions ORDER BY id ASC",
            null
        )
        val list = mutableListOf<OfflineRecurringAction>()
        cursor.use {
            while (it.moveToNext()) {
                list.add(
                    OfflineRecurringAction(
                        id = it.getLong(0),
                        actionType = it.getString(1),
                        venueId = it.getLong(2),
                        contractId = if (it.isNull(3)) null else it.getLong(3),
                        payloadJson = it.getString(4),
                        createdAt = it.getLong(5)
                    )
                )
            }
        }
        return list
    }

    fun deletePendingAction(id: Long) {
        val db = dbHelper.writableDatabase
        db.delete("offline_recurring_actions", "id = ?", arrayOf(id.toString()))
    }

    // JSON Serializers
    private fun serializeContracts(list: List<StandingContractSummary>): String {
        val arr = JSONArray()
        list.forEach { c ->
            val obj = JSONObject().apply {
                put("id", c.id)
                put("customer_name", c.customerName)
                put("customer_phone", c.customerPhone)
                put("court_id", c.courtId)
                put("court_name", c.courtName)
                put("sport", c.sport ?: "")
                put("day_of_week", c.dayOfWeek)
                put("start_time", c.startTime)
                put("end_time", c.endTime)
                put("price_per_session", c.pricePerSession)
                put("monthly_value", c.monthlyValue)
                put("security_deposit", c.securityDeposit)
                put("balance_due", c.balanceDue)
                put("attendance_rate", c.attendanceRate)
                put("status", c.status)
                put("is_at_risk", c.isAtRisk)
                put("risk_reason", c.riskReason ?: "")
                put("active_from", c.activeFrom)
                put("active_until", c.activeUntil ?: "")
                c.nextSession?.let { ns ->
                    val nsObj = JSONObject().apply {
                        put("id", ns.id)
                        put("date", ns.date)
                        put("time", ns.time)
                    }
                    put("next_session", nsObj)
                }
            }
            arr.put(obj)
        }
        return arr.toString()
    }

    private fun deserializeContracts(json: String): List<StandingContractSummary> {
        val list = mutableListOf<StandingContractSummary>()
        val arr = JSONArray(json)
        for (i in 0 until arr.length()) {
            val obj = arr.getJSONObject(i)
            val ns = if (obj.has("next_session") && !obj.isNull("next_session")) {
                val nsObj = obj.getJSONObject("next_session")
                NextSessionInfo(
                    id = nsObj.optLong("id"),
                    date = nsObj.optString("date"),
                    time = nsObj.optString("time")
                )
            } else null

            list.add(
                StandingContractSummary(
                    id = obj.optLong("id"),
                    customerName = obj.optString("customer_name"),
                    customerPhone = obj.optString("customer_phone"),
                    courtId = obj.optLong("court_id"),
                    courtName = obj.optString("court_name", "Court"),
                    sport = obj.optString("sport").takeIf { it.isNotBlank() },
                    dayOfWeek = obj.optString("day_of_week"),
                    startTime = obj.optString("start_time"),
                    endTime = obj.optString("end_time"),
                    pricePerSession = obj.optDouble("price_per_session", 0.0),
                    monthlyValue = obj.optDouble("monthly_value", 0.0),
                    securityDeposit = obj.optDouble("security_deposit", 0.0),
                    balanceDue = obj.optDouble("balance_due", 0.0),
                    attendanceRate = obj.optDouble("attendance_rate", 100.0),
                    status = obj.optString("status", "active"),
                    isAtRisk = obj.optBoolean("is_at_risk", false),
                    riskReason = obj.optString("risk_reason").takeIf { it.isNotBlank() },
                    activeFrom = obj.optString("active_from"),
                    activeUntil = obj.optString("active_until").takeIf { it.isNotBlank() },
                    nextSession = ns
                )
            )
        }
        return list
    }

    private fun serializeDashboard(d: StandingDashboardMetrics?): String {
        if (d == null) return "{}"
        return JSONObject().apply {
            put("total_contracts", d.totalContracts)
            put("active_contracts", d.activeContracts)
            put("at_risk_contracts", d.atRiskContracts)
            put("paused_contracts", d.pausedContracts)
            put("monthly_recurring_revenue", d.monthlyRecurringRevenue)
            put("total_security_deposits", d.totalSecurityDeposits)
            put("average_attendance_rate", d.averageAttendanceRate)
        }.toString()
    }

    private fun deserializeDashboard(json: String): StandingDashboardMetrics {
        val obj = JSONObject(json)
        return StandingDashboardMetrics(
            totalContracts = obj.optInt("total_contracts", 0),
            activeContracts = obj.optInt("active_contracts", 0),
            atRiskContracts = obj.optInt("at_risk_contracts", 0),
            pausedContracts = obj.optInt("paused_contracts", 0),
            monthlyRecurringRevenue = obj.optDouble("monthly_recurring_revenue", 0.0),
            totalSecurityDeposits = obj.optDouble("total_security_deposits", 0.0),
            averageAttendanceRate = obj.optDouble("average_attendance_rate", 100.0)
        )
    }
}
