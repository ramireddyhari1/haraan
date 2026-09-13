package com.haraan.partner.recurring.data

import com.haraan.partner.ApiConfig
import com.haraan.partner.recurring.model.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URL

class RecurringRemoteDataSource(private val baseUrl: String = ApiConfig.BASE_URL) {

    suspend fun fetchDashboard(token: String, venueId: Long): Triple<StandingDashboardMetrics, List<TodaySessionItem>, List<AtRiskAlertItem>> = withContext(Dispatchers.IO) {
        val res = get("/api/partner/venues/$venueId/standing-contracts/dashboard", token)
        val json = JSONObject(res)

        val mObj = json.getJSONObject("metrics")
        val metrics = StandingDashboardMetrics(
            totalContracts = mObj.optInt("total_contracts", 0),
            activeContracts = mObj.optInt("active_contracts", 0),
            atRiskContracts = mObj.optInt("at_risk_contracts", 0),
            pausedContracts = mObj.optInt("paused_contracts", 0),
            monthlyRecurringRevenue = mObj.optDouble("monthly_recurring_revenue", 0.0),
            totalSecurityDeposits = mObj.optDouble("total_security_deposits", 0.0),
            averageAttendanceRate = mObj.optDouble("average_attendance_rate", 100.0)
        )

        val todayArr = json.optJSONArray("today_sessions") ?: JSONArray()
        val todaySessions = mutableListOf<TodaySessionItem>()
        for (i in 0 until todayArr.length()) {
            val o = todayArr.getJSONObject(i)
            todaySessions.add(
                TodaySessionItem(
                    sessionId = o.optLong("session_id"),
                    contractId = o.optLong("contract_id"),
                    customerName = o.optString("customer_name"),
                    customerPhone = o.optString("customer_phone"),
                    courtName = o.optString("court_name"),
                    startTime = o.optString("start_time"),
                    endTime = o.optString("end_time"),
                    attendanceStatus = o.optString("attendance_status"),
                    paymentStatus = o.optString("payment_status"),
                    price = o.optDouble("price", 0.0)
                )
            )
        }

        val alertArr = json.optJSONArray("at_risk_alerts") ?: JSONArray()
        val atRiskAlerts = mutableListOf<AtRiskAlertItem>()
        for (i in 0 until alertArr.length()) {
            val o = alertArr.getJSONObject(i)
            atRiskAlerts.add(
                AtRiskAlertItem(
                    id = o.optLong("id"),
                    customerName = o.optString("customer_name"),
                    customerPhone = o.optString("customer_phone"),
                    weekday = o.optString("weekday"),
                    time = o.optString("time"),
                    missedStreak = o.optInt("missed_streak", 0),
                    attendanceRate = o.optDouble("attendance_rate", 0.0),
                    riskReason = o.optString("risk_reason").takeIf { it.isNotBlank() }
                )
            )
        }

        Triple(metrics, todaySessions, atRiskAlerts)
    }

    suspend fun fetchContracts(
        token: String,
        venueId: Long,
        status: String = "all",
        search: String? = null
    ): List<StandingContractSummary> = withContext(Dispatchers.IO) {
        val query = StringBuilder("/api/partner/venues/$venueId/standing-contracts?status=$status")
        if (!search.isNullOrBlank()) {
            query.append("&search=").append(java.net.URLEncoder.encode(search, "UTF-8"))
        }

        val res = get(query.toString(), token)
        val json = JSONObject(res)
        val arr = json.optJSONArray("data") ?: JSONArray()

        val list = mutableListOf<StandingContractSummary>()
        for (i in 0 until arr.length()) {
            list.add(parseContractSummary(arr.getJSONObject(i)))
        }
        list
    }

    suspend fun checkConflicts(
        token: String,
        venueId: Long,
        courtId: Long,
        dayOfWeek: String,
        startTime: String,
        endTime: String,
        activeFrom: String,
        activeUntil: String? = null,
        weeks: Int = 12
    ): ConflictReport = withContext(Dispatchers.IO) {
        val payload = JSONObject().apply {
            put("court_id", courtId)
            put("day_of_week", dayOfWeek)
            put("start_time", startTime)
            put("end_time", endTime)
            put("active_from", activeFrom)
            if (activeUntil != null) put("active_until", activeUntil)
            put("weeks", weeks)
        }

        val res = post("/api/partner/venues/$venueId/standing-contracts/check-conflicts", payload.toString(), token)
        val json = JSONObject(res)

        val conflictsArr = json.optJSONArray("conflicts") ?: JSONArray()
        val conflicts = mutableListOf<ConflictItem>()
        for (i in 0 until conflictsArr.length()) {
            val c = conflictsArr.getJSONObject(i)
            conflicts.add(
                ConflictItem(
                    date = c.optString("date"),
                    weekday = c.optString("weekday"),
                    startTime = c.optString("start_time"),
                    endTime = c.optString("end_time"),
                    conflictType = c.optString("conflict_type"),
                    conflictingId = if (c.isNull("conflicting_id")) null else c.optLong("conflicting_id"),
                    reason = c.optString("reason"),
                    suggestedAction = c.optString("suggested_action")
                )
            )
        }

        val previewArr = json.optJSONArray("schedule_preview") ?: JSONArray()
        val preview = mutableListOf<SchedulePreviewItem>()
        for (i in 0 until previewArr.length()) {
            val p = previewArr.getJSONObject(i)
            val conflictObj = if (p.has("conflict") && !p.isNull("conflict")) {
                val co = p.getJSONObject("conflict")
                ConflictItem(
                    date = co.optString("date"),
                    weekday = co.optString("weekday"),
                    startTime = co.optString("start_time"),
                    endTime = co.optString("end_time"),
                    conflictType = co.optString("conflict_type"),
                    conflictingId = if (co.isNull("conflicting_id")) null else co.optLong("conflicting_id"),
                    reason = co.optString("reason"),
                    suggestedAction = co.optString("suggested_action")
                )
            } else null

            preview.add(
                SchedulePreviewItem(
                    date = p.optString("date"),
                    weekday = p.optString("weekday"),
                    startTime = p.optString("start_time"),
                    endTime = p.optString("end_time"),
                    status = p.optString("status"),
                    conflict = conflictObj
                )
            )
        }

        ConflictReport(
            isClear = json.optBoolean("is_clear", false),
            totalSessionsChecked = json.optInt("total_sessions_checked", 0),
            conflictingSessionsCount = json.optInt("conflicting_sessions_count", 0),
            clearSessionsCount = json.optInt("clear_sessions_count", 0),
            conflicts = conflicts,
            schedulePreview = preview
        )
    }

    suspend fun createContract(
        token: String,
        venueId: Long,
        req: CreateContractRequest
    ): StandingContractDetail = withContext(Dispatchers.IO) {
        val payload = JSONObject().apply {
            put("customer_name", req.customerName)
            put("customer_phone", req.customerPhone)
            put("court_id", req.courtId)
            if (req.sport != null) put("sport", req.sport)
            put("day_of_week", req.dayOfWeek)
            put("start_time", req.startTime)
            put("end_time", req.endTime)
            put("duration_minutes", req.durationMinutes)
            put("price_per_session", req.pricePerSession)
            if (req.monthlyPackagePrice != null) put("monthly_package_price", req.monthlyPackagePrice)
            put("security_deposit", req.securityDeposit)
            put("advance_paid", req.advancePaid)
            put("active_from", req.activeFrom)
            if (req.activeUntil != null) put("active_until", req.activeUntil)
            put("auto_renew", req.autoRenew)
            put("auto_skip_conflicts", req.autoSkipConflicts)
            put("max_members", req.maxMembers)
            if (req.notes != null) put("notes", req.notes)
            put("payment_method", req.paymentMethod)
        }

        val res = post("/api/partner/venues/$venueId/standing-contracts", payload.toString(), token)
        val json = JSONObject(res)
        parseContractDetail(json.getJSONObject("contract"))
    }

    suspend fun fetchContractDetail(
        token: String,
        venueId: Long,
        contractId: Long
    ): StandingContractDetail = withContext(Dispatchers.IO) {
        val res = get("/api/partner/venues/$venueId/standing-contracts/$contractId", token)
        val json = JSONObject(res)
        parseContractDetail(json.getJSONObject("contract"))
    }

    suspend fun skipSession(
        token: String,
        venueId: Long,
        contractId: Long,
        date: String,
        reason: String?
    ): StandingContractDetail = withContext(Dispatchers.IO) {
        val payload = JSONObject().apply {
            put("date", date)
            if (reason != null) put("reason", reason)
        }
        val res = post("/api/partner/venues/$venueId/standing-contracts/$contractId/skip-date", payload.toString(), token)
        val json = JSONObject(res)
        parseContractDetail(json.getJSONObject("contract"))
    }

    suspend fun pauseContract(
        token: String,
        venueId: Long,
        contractId: Long,
        reason: String?
    ): StandingContractDetail = withContext(Dispatchers.IO) {
        val payload = JSONObject().apply {
            if (reason != null) put("reason", reason)
        }
        val res = post("/api/partner/venues/$venueId/standing-contracts/$contractId/pause", payload.toString(), token)
        val json = JSONObject(res)
        parseContractDetail(json.getJSONObject("contract"))
    }

    suspend fun resumeContract(
        token: String,
        venueId: Long,
        contractId: Long
    ): StandingContractDetail = withContext(Dispatchers.IO) {
        val res = post("/api/partner/venues/$venueId/standing-contracts/$contractId/resume", "{}", token)
        val json = JSONObject(res)
        parseContractDetail(json.getJSONObject("contract"))
    }

    suspend fun terminateContract(
        token: String,
        venueId: Long,
        contractId: Long,
        reason: String?
    ): StandingContractDetail = withContext(Dispatchers.IO) {
        val payload = JSONObject().apply {
            if (reason != null) put("reason", reason)
        }
        val res = post("/api/partner/venues/$venueId/standing-contracts/$contractId/terminate", payload.toString(), token)
        val json = JSONObject(res)
        parseContractDetail(json.getJSONObject("contract"))
    }

    suspend fun transferCourt(
        token: String,
        venueId: Long,
        contractId: Long,
        courtId: Long
    ): StandingContractDetail = withContext(Dispatchers.IO) {
        val payload = JSONObject().apply {
            put("court_id", courtId)
        }
        val res = post("/api/partner/venues/$venueId/standing-contracts/$contractId/transfer-court", payload.toString(), token)
        val json = JSONObject(res)
        parseContractDetail(json.getJSONObject("contract"))
    }

    suspend fun recordAttendance(
        token: String,
        venueId: Long,
        contractId: Long,
        sessionId: Long,
        status: String
    ): StandingContractDetail = withContext(Dispatchers.IO) {
        val payload = JSONObject().apply {
            put("session_id", sessionId)
            put("status", status)
        }
        val res = post("/api/partner/venues/$venueId/standing-contracts/$contractId/attendance", payload.toString(), token)
        val json = JSONObject(res)
        parseContractDetail(json.getJSONObject("contract"))
    }

    suspend fun recordPayment(
        token: String,
        venueId: Long,
        contractId: Long,
        amount: Double,
        paymentType: String,
        method: String,
        notes: String?
    ): StandingContractDetail = withContext(Dispatchers.IO) {
        val payload = JSONObject().apply {
            put("amount", amount)
            put("payment_type", paymentType)
            put("method", method)
            if (notes != null) put("notes", notes)
        }
        val res = post("/api/partner/venues/$venueId/standing-contracts/$contractId/payment", payload.toString(), token)
        val json = JSONObject(res)
        parseContractDetail(json.getJSONObject("contract"))
    }

    // Parsers
    private fun parseContractSummary(obj: JSONObject): StandingContractSummary {
        val ns = if (obj.has("next_session") && !obj.isNull("next_session")) {
            val nsObj = obj.getJSONObject("next_session")
            NextSessionInfo(
                id = nsObj.optLong("id"),
                date = nsObj.optString("date"),
                time = nsObj.optString("time")
            )
        } else null

        return StandingContractSummary(
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
    }

    private fun parseContractDetail(obj: JSONObject): StandingContractDetail {
        val summary = parseContractSummary(obj)

        val sessionsArr = obj.optJSONArray("sessions") ?: JSONArray()
        val sessions = mutableListOf<ContractSessionItem>()
        for (i in 0 until sessionsArr.length()) {
            val s = sessionsArr.getJSONObject(i)
            sessions.add(
                ContractSessionItem(
                    id = s.optLong("id"),
                    bookingId = if (s.isNull("booking_id")) null else s.optLong("booking_id"),
                    sessionDate = s.optString("session_date"),
                    startTime = s.optString("start_time"),
                    endTime = s.optString("end_time"),
                    courtName = s.optString("court_name", "Court"),
                    price = s.optDouble("price", 0.0),
                    attendanceStatus = s.optString("attendance_status", "scheduled"),
                    paymentStatus = s.optString("payment_status", "unpaid"),
                    checkInTime = s.optString("check_in_time").takeIf { it.isNotBlank() },
                    notes = s.optString("notes").takeIf { it.isNotBlank() }
                )
            )
        }

        val paymentsArr = obj.optJSONArray("payments") ?: JSONArray()
        val payments = mutableListOf<ContractPaymentItem>()
        for (i in 0 until paymentsArr.length()) {
            val p = paymentsArr.getJSONObject(i)
            payments.add(
                ContractPaymentItem(
                    id = p.optLong("id"),
                    amount = p.optDouble("amount", 0.0),
                    paymentType = p.optString("payment_type"),
                    method = p.optString("method"),
                    collectorName = p.optString("collector_name", "Desk"),
                    notes = p.optString("notes").takeIf { it.isNotBlank() },
                    createdAt = p.optString("created_at")
                )
            )
        }

        val logsArr = obj.optJSONArray("logs") ?: JSONArray()
        val logs = mutableListOf<ContractLogItem>()
        for (i in 0 until logsArr.length()) {
            val l = logsArr.getJSONObject(i)
            logs.add(
                ContractLogItem(
                    id = l.optLong("id"),
                    action = l.optString("action"),
                    details = l.optString("details").takeIf { it.isNotBlank() },
                    actorName = l.optString("actor_name", "System"),
                    createdAt = l.optString("created_at")
                )
            )
        }

        return StandingContractDetail(
            id = summary.id,
            customerName = summary.customerName,
            customerPhone = summary.customerPhone,
            courtId = summary.courtId,
            courtName = summary.courtName,
            sport = summary.sport,
            dayOfWeek = summary.dayOfWeek,
            startTime = summary.startTime,
            endTime = summary.endTime,
            durationMinutes = obj.optInt("duration_minutes", 60),
            pricePerSession = summary.pricePerSession,
            monthlyValue = summary.monthlyValue,
            securityDeposit = summary.securityDeposit,
            advancePaid = obj.optDouble("advance_paid", 0.0),
            balanceDue = summary.balanceDue,
            attendanceRate = summary.attendanceRate,
            status = summary.status,
            isAtRisk = summary.isAtRisk,
            riskReason = summary.riskReason,
            activeFrom = summary.activeFrom,
            activeUntil = summary.activeUntil,
            autoRenew = obj.optBoolean("auto_renew", true),
            maxMembers = obj.optInt("max_members", 10),
            notes = obj.optString("notes").takeIf { it.isNotBlank() },
            consecutiveMissedSessions = obj.optInt("consecutive_missed_sessions", 0),
            totalSessionsCount = obj.optInt("total_sessions_count", 0),
            attendedSessionsCount = obj.optInt("attended_sessions_count", 0),
            sessions = sessions,
            payments = payments,
            logs = logs
        )
    }

    private fun get(path: String, token: String): String {
        val url = URL(if (path.startsWith("http")) path else "$baseUrl$path")
        val conn = url.openConnection() as HttpURLConnection
        conn.requestMethod = "GET"
        conn.setRequestProperty("Authorization", "Bearer $token")
        conn.setRequestProperty("Accept", "application/json")
        conn.connectTimeout = 10000
        conn.readTimeout = 10000

        val code = conn.responseCode
        val stream = if (code in 200..299) conn.inputStream else conn.errorStream
        val text = BufferedReader(InputStreamReader(stream)).use { it.readText() }
        if (code !in 200..299) {
            throw Exception("HTTP $code: $text")
        }
        return text
    }

    private fun post(path: String, body: String, token: String): String {
        val url = URL(if (path.startsWith("http")) path else "$baseUrl$path")
        val conn = url.openConnection() as HttpURLConnection
        conn.requestMethod = "POST"
        conn.setRequestProperty("Authorization", "Bearer $token")
        conn.setRequestProperty("Content-Type", "application/json")
        conn.setRequestProperty("Accept", "application/json")
        conn.connectTimeout = 12000
        conn.readTimeout = 12000
        conn.doOutput = true

        OutputStreamWriter(conn.outputStream).use { it.write(body) }

        val code = conn.responseCode
        val stream = if (code in 200..299) conn.inputStream else conn.errorStream
        val text = BufferedReader(InputStreamReader(stream)).use { it.readText() }
        if (code !in 200..299) {
            throw Exception("HTTP $code: $text")
        }
        return text
    }
}
