package com.haraan.partner.operations.data

import com.haraan.partner.operations.model.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

class OperationsRemoteDataSource(
    private val baseUrl: String = "http://10.0.2.2:8000"
) {

    suspend fun fetchOverview(token: String, venueId: Long): Pair<OperationsMasterData, String> = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/operations/overview"
        val responseStr = makeHttpRequest("GET", endpoint, token)
        val root = JSONObject(responseStr)
        val data = root.optJSONObject("data") ?: JSONObject()

        val parsed = parseOverviewJson(data)
        Pair(parsed, responseStr)
    }

    suspend fun resolveAlert(token: String, venueId: Long, alertId: Long): Boolean = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/operations/alerts/$alertId/resolve"
        val responseStr = makeHttpRequest("POST", endpoint, token, "{}")
        JSONObject(responseStr).optString("status") == "success"
    }

    suspend fun applySuggestion(token: String, venueId: Long, suggestionId: Long): Boolean = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/operations/suggestions/$suggestionId/apply"
        val responseStr = makeHttpRequest("POST", endpoint, token, "{}")
        JSONObject(responseStr).optString("status") == "success"
    }

    suspend fun dismissSuggestion(token: String, venueId: Long, suggestionId: Long): Boolean = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/operations/suggestions/$suggestionId/dismiss"
        val responseStr = makeHttpRequest("POST", endpoint, token, "{}")
        JSONObject(responseStr).optString("status") == "success"
    }

    fun parseOverviewJson(data: JSONObject): OperationsMasterData {
        // Revenue
        val revObj = data.optJSONObject("revenue") ?: JSONObject()
        val channelsMap = mutableMapOf<String, RevenueChannelData>()
        val chObj = revObj.optJSONObject("channel_breakdown")
        if (chObj != null) {
            val keys = chObj.keys()
            while (keys.hasNext()) {
                val k = keys.next()
                val c = chObj.getJSONObject(k)
                channelsMap[k] = RevenueChannelData(c.optDouble("amount", 0.0), c.optInt("count", 0))
            }
        }
        val revenue = RevenueOverview(
            todayRevenue = revObj.optDouble("today_revenue", 0.0),
            yesterdayRevenue = revObj.optDouble("yesterday_revenue", 0.0),
            weekToDateRevenue = revObj.optDouble("week_to_date_revenue", 0.0),
            monthToDateRevenue = revObj.optDouble("month_to_date_revenue", 0.0),
            projectedMonthRevenue = revObj.optDouble("projected_month_revenue", 0.0),
            dayOverDayGrowthPct = revObj.optDouble("day_over_day_growth_pct", 0.0),
            standingContractsMrr = revObj.optDouble("standing_contracts_mrr", 0.0),
            channelBreakdown = channelsMap
        )

        // Occupancy Heatmap
        val occObj = data.optJSONObject("occupancy") ?: JSONObject()
        val hmArr = occObj.optJSONArray("heatmap") ?: JSONArray()
        val heatmapRows = mutableListOf<OccupancyHeatmapRow>()
        for (i in 0 until hmArr.length()) {
            val r = hmArr.getJSONObject(i)
            val hArr = r.optJSONArray("hours") ?: JSONArray()
            val hours = mutableListOf<OccupancyHeatmapCell>()
            for (j in 0 until hArr.length()) {
                val cell = hArr.getJSONObject(j)
                hours.add(
                    OccupancyHeatmapCell(
                        hour = cell.getInt("hour"),
                        hourLabel = cell.getString("hour_label"),
                        bookedCount = cell.getInt("booked_count"),
                        capacity = cell.getInt("capacity"),
                        occupancyPct = cell.getDouble("occupancy_pct"),
                        intensity = cell.getString("intensity")
                    )
                )
            }
            heatmapRows.add(
                OccupancyHeatmapRow(
                    dayOfWeek = r.getInt("day_of_week"),
                    dayName = r.getString("day_name"),
                    hours = hours
                )
            )
        }

        // Staff Performance
        val staffArr = data.optJSONArray("staff") ?: JSONArray()
        val staffList = mutableListOf<StaffPerformanceItem>()
        for (i in 0 until staffArr.length()) {
            val s = staffArr.getJSONObject(i)
            staffList.add(
                StaffPerformanceItem(
                    staffId = s.getLong("staff_id"),
                    name = s.getString("name"),
                    email = s.optString("email", ""),
                    shiftsCompleted = s.optInt("shifts_completed", 0),
                    totalCashHandled = s.optDouble("total_cash_handled", 0.0),
                    cashVariance = s.optDouble("cash_variance", 0.0),
                    conversationsHeld = s.optInt("conversations_held", 0),
                    bookingsConverted = s.optInt("bookings_converted", 0)
                )
            )
        }

        // WhatsApp Funnel
        val fObj = data.optJSONObject("funnel") ?: JSONObject()
        val stagesArr = fObj.optJSONArray("stages") ?: JSONArray()
        val stages = mutableListOf<FunnelStageItem>()
        for (i in 0 until stagesArr.length()) {
            val stg = stagesArr.getJSONObject(i)
            stages.add(
                FunnelStageItem(
                    stage = stg.getString("stage"),
                    count = stg.getInt("count"),
                    dropOffPct = stg.optDouble("drop_off_pct", 0.0)
                )
            )
        }
        val funnel = WhatsAppFunnelOverview(
            totalInquiries = fObj.optInt("total_inquiries", 0),
            convertedBookings = fObj.optInt("converted_bookings", 0),
            conversionRatePct = fObj.optDouble("conversion_rate_pct", 0.0),
            totalWhatsappRevenue = fObj.optDouble("total_whatsapp_revenue", 0.0),
            stages = stages
        )

        // Revenue Leakage Alerts
        val aArr = data.optJSONArray("leakage_alerts") ?: JSONArray()
        val alerts = mutableListOf<RevenueLeakageAlertItem>()
        for (i in 0 until aArr.length()) {
            val al = aArr.getJSONObject(i)
            alerts.add(
                RevenueLeakageAlertItem(
                    id = al.getLong("id"),
                    venueId = al.getLong("venue_id"),
                    alertType = al.getString("alert_type"),
                    severity = al.optString("severity", "medium"),
                    title = al.getString("title"),
                    description = al.getString("description"),
                    isResolved = al.optBoolean("is_resolved", false),
                    createdAt = al.optString("created_at", "")
                )
            )
        }

        // AI Business Suggestions
        val sgArr = data.optJSONArray("ai_suggestions") ?: JSONArray()
        val suggestions = mutableListOf<BusinessSuggestionItem>()
        for (i in 0 until sgArr.length()) {
            val sg = sgArr.getJSONObject(i)
            suggestions.add(
                BusinessSuggestionItem(
                    id = sg.getLong("id"),
                    venueId = sg.getLong("venue_id"),
                    category = sg.getString("category"),
                    title = sg.getString("title"),
                    rationale = sg.getString("rationale"),
                    projectedRevenueImpact = sg.optDouble("projected_revenue_impact", 0.0),
                    status = sg.optString("status", "pending")
                )
            )
        }

        return OperationsMasterData(
            revenue = revenue,
            heatmap = heatmapRows,
            staff = staffList,
            funnel = funnel,
            alerts = alerts,
            suggestions = suggestions
        )
    }

    private fun makeHttpRequest(method: String, urlStr: String, token: String, bodyJson: String? = null): String {
        val url = URL(urlStr)
        val conn = url.openConnection() as HttpURLConnection
        conn.requestMethod = method
        conn.setRequestProperty("Accept", "application/json")
        conn.setRequestProperty("Authorization", "Bearer $token")
        conn.connectTimeout = 6000
        conn.readTimeout = 10000

        if (bodyJson != null && (method == "POST" || method == "PUT" || method == "PATCH")) {
            conn.doOutput = true
            conn.setRequestProperty("Content-Type", "application/json")
            conn.outputStream.use { os ->
                os.write(bodyJson.toByteArray(Charsets.UTF_8))
            }
        }

        val code = conn.responseCode
        val stream = if (code in 200..299) conn.inputStream else conn.errorStream
            ?: throw Exception("HTTP $code without stream")

        val responseStr = BufferedReader(InputStreamReader(stream, Charsets.UTF_8)).use { it.readText() }
        if (code !in 200..299) {
            throw Exception("HTTP $code: $responseStr")
        }
        return responseStr
    }
}