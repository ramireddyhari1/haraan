package com.haraan.partner.pricing.data

import com.haraan.partner.pricing.model.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

class PricingRemoteDataSource(
    private val baseUrl: String = "http://10.0.2.2:8000"
) {

    suspend fun fetchDashboard(token: String, venueId: Long): PricingDashboardMetrics = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/pricing/dashboard"
        val responseStr = makeHttpRequest("GET", endpoint, token)
        val json = JSONObject(responseStr)

        val recsArr = json.optJSONArray("top_recommendations") ?: JSONArray()
        val recs = mutableListOf<YieldRecommendationItem>()
        for (i in 0 until recsArr.length()) {
            val r = recsArr.getJSONObject(i)
            val p = r.optJSONObject("rule_payload")
            recs.add(
                YieldRecommendationItem(
                    id = r.getString("id"),
                    title = r.getString("title"),
                    dayOfWeek = r.getString("day_of_week"),
                    timeWindow = r.getString("time_window"),
                    currentOccupancyRate = r.getDouble("current_occupancy_rate"),
                    suggestedMode = r.getString("suggested_mode"),
                    suggestedRate = r.getDouble("suggested_rate"),
                    projectedMonthlyUplift = r.getDouble("projected_monthly_uplift"),
                    rationale = r.getString("rationale"),
                    rulePayload = if (p != null) {
                        val wdArr = p.optJSONArray("weekdays") ?: JSONArray()
                        val wds = mutableListOf<String>()
                        for (j in 0 until wdArr.length()) wds.add(wdArr.getString(j))
                        CreatePricingRuleRequest(
                            name = p.getString("name"),
                            ruleType = p.optString("rule_type", "time_of_day"),
                            weekdays = wds,
                            startTime = p.getString("start_time"),
                            endTime = p.getString("end_time"),
                            pricingMode = p.getString("pricing_mode"),
                            amount = p.getDouble("amount"),
                            priority = p.optInt("priority", 10)
                        )
                    } else null
                )
            )
        }

        PricingDashboardMetrics(
            averageHourlyRate = json.optInt("average_hourly_rate", 1000),
            minRate = json.optInt("min_rate", 800),
            maxRate = json.optInt("max_rate", 1500),
            activeRulesCount = json.optInt("active_rules_count", 0),
            compositeCourtsCount = json.optInt("composite_courts_count", 0),
            recommendationsCount = json.optInt("recommendations_count", recs.size),
            topRecommendations = recs
        )
    }

    suspend fun fetchMatrix(token: String, venueId: Long, courtId: Long? = null): WeeklyPricingMatrix = withContext(Dispatchers.IO) {
        val endpoint = if (courtId != null) {
            "$baseUrl/api/partner/venues/$venueId/pricing/matrix?court_id=$courtId"
        } else {
            "$baseUrl/api/partner/venues/$venueId/pricing/matrix"
        }
        val responseStr = makeHttpRequest("GET", endpoint, token)
        val json = JSONObject(responseStr)

        val matrixMap = mutableMapOf<String, DayPricingSchedule>()
        val matrixObj = json.optJSONObject("matrix") ?: JSONObject()
        val keys = matrixObj.keys()
        while (keys.hasNext()) {
            val key = keys.next()
            val dayObj = matrixObj.getJSONObject(key)
            val slotsArr = dayObj.optJSONArray("slots") ?: JSONArray()
            val slots = mutableListOf<HourSlotRate>()
            for (i in 0 until slotsArr.length()) {
                val s = slotsArr.getJSONObject(i)
                slots.add(
                    HourSlotRate(
                        hour = s.getInt("hour"),
                        timeLabel = s.getString("time_label"),
                        rate = s.getInt("rate"),
                        baseRate = s.optInt("base_rate", 1000),
                        ruleId = if (s.has("rule_id") && !s.isNull("rule_id")) s.getLong("rule_id") else null,
                        ruleName = s.optString("rule_name").takeIf { it.isNotBlank() && it != "null" },
                        mode = s.optString("mode", "absolute"),
                        isPeak = s.optBoolean("is_peak", false),
                        tag = s.optString("tag", "standard")
                    )
                )
            }
            matrixMap[key] = DayPricingSchedule(
                dayName = dayObj.getString("day_name"),
                date = dayObj.getString("date"),
                slots = slots
            )
        }

        WeeklyPricingMatrix(
            courtId = if (json.has("court_id") && !json.isNull("court_id")) json.getLong("court_id") else null,
            courtName = json.optString("court_name", "Court"),
            baseRate = json.optInt("base_rate", 1000),
            minRate = json.optInt("min_rate", 1000),
            maxRate = json.optInt("max_rate", 1000),
            averageRate = json.optInt("average_rate", 1000),
            matrix = matrixMap
        )
    }

    suspend fun fetchRules(token: String, venueId: Long, courtId: Long? = null, activeOnly: Boolean? = null): List<PricingRuleItem> = withContext(Dispatchers.IO) {
        var url = "$baseUrl/api/partner/venues/$venueId/pricing/rules"
        val params = mutableListOf<String>()
        if (courtId != null) params.add("court_id=$courtId")
        if (activeOnly != null) params.add("active_only=$activeOnly")
        if (params.isNotEmpty()) url += "?" + params.joinToString("&")

        val responseStr = makeHttpRequest("GET", url, token)
        val json = JSONObject(responseStr)
        val arr = json.optJSONArray("rules") ?: JSONArray()
        val list = mutableListOf<PricingRuleItem>()
        for (i in 0 until arr.length()) {
            val o = arr.getJSONObject(i)
            val wdArr = o.optJSONArray("weekdays") ?: JSONArray()
            val wds = mutableListOf<String>()
            for (j in 0 until wdArr.length()) wds.add(wdArr.getString(j))

            list.add(
                PricingRuleItem(
                    id = o.getLong("id"),
                    venueId = o.getLong("venue_id"),
                    venueCourtId = if (o.has("venue_court_id") && !o.isNull("venue_court_id")) o.getLong("venue_court_id") else null,
                    courtName = o.optString("court_name", "All Courts"),
                    name = o.getString("name"),
                    ruleType = o.optString("rule_type", "time_of_day"),
                    weekdays = wds,
                    startTime = o.getString("start_time"),
                    endTime = o.getString("end_time"),
                    dateFrom = o.optString("date_from").takeIf { it.isNotBlank() && it != "null" },
                    dateTo = o.optString("date_to").takeIf { it.isNotBlank() && it != "null" },
                    pricingMode = o.optString("pricing_mode", "absolute"),
                    amount = o.getDouble("amount"),
                    minPrice = if (o.has("min_price") && !o.isNull("min_price")) o.getDouble("min_price") else null,
                    maxPrice = if (o.has("max_price") && !o.isNull("max_price")) o.getDouble("max_price") else null,
                    priority = o.optInt("priority", 10),
                    isActive = o.optBoolean("is_active", true),
                    createdAt = o.optString("created_at", "")
                )
            )
        }
        list
    }

    suspend fun createRule(token: String, venueId: Long, req: CreatePricingRuleRequest): PricingRuleItem = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/pricing/rules"
        val payload = JSONObject().apply {
            put("name", req.name)
            if (req.venueCourtId != null) put("venue_court_id", req.venueCourtId)
            put("rule_type", req.ruleType)
            put("weekdays", JSONArray(req.weekdays))
            put("start_time", req.startTime)
            put("end_time", req.endTime)
            if (req.dateFrom != null) put("date_from", req.dateFrom)
            if (req.dateTo != null) put("date_to", req.dateTo)
            put("pricing_mode", req.pricingMode)
            put("amount", req.amount)
            if (req.minPrice != null) put("min_price", req.minPrice)
            if (req.maxPrice != null) put("max_price", req.maxPrice)
            put("priority", req.priority)
        }

        val resStr = makeHttpRequest("POST", endpoint, token, payload.toString())
        val json = JSONObject(resStr)
        val o = json.getJSONObject("rule")

        val wdArr = o.optJSONArray("weekdays") ?: JSONArray()
        val wds = mutableListOf<String>()
        for (j in 0 until wdArr.length()) wds.add(wdArr.getString(j))

        PricingRuleItem(
            id = o.getLong("id"),
            venueId = o.getLong("venue_id"),
            venueCourtId = if (o.has("venue_court_id") && !o.isNull("venue_court_id")) o.getLong("venue_court_id") else null,
            name = o.getString("name"),
            ruleType = o.optString("rule_type", "time_of_day"),
            weekdays = wds,
            startTime = o.getString("start_time"),
            endTime = o.getString("end_time"),
            pricingMode = o.optString("pricing_mode", "absolute"),
            amount = o.getDouble("amount"),
            priority = o.optInt("priority", 10),
            isActive = o.optBoolean("is_active", true),
            createdAt = o.optString("created_at", "")
        )
    }

    suspend fun toggleRule(token: String, venueId: Long, ruleId: Long): Boolean = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/pricing/rules/$ruleId/toggle"
        val resStr = makeHttpRequest("POST", endpoint, token, "{}")
        val json = JSONObject(resStr)
        json.optBoolean("is_active", true)
    }

    suspend fun deleteRule(token: String, venueId: Long, ruleId: Long): Boolean = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/pricing/rules/$ruleId"
        makeHttpRequest("DELETE", endpoint, token)
        true
    }

    suspend fun fetchHierarchy(token: String, venueId: Long): CourtHierarchyData = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/pricing/hierarchy"
        val resStr = makeHttpRequest("GET", endpoint, token)
        val root = JSONObject(resStr)

        val compList = mutableListOf<CompositeCourtItem>()
        val compArr = root.optJSONArray("composite_courts") ?: JSONArray()
        for (i in 0 until compArr.length()) {
            val c = compArr.getJSONObject(i)
            val spArr = c.optJSONArray("sports") ?: JSONArray()
            val sports = mutableListOf<String>()
            for (j in 0 until spArr.length()) sports.add(spArr.getString(j))

            val chList = mutableListOf<ChildPartitionItem>()
            val chArr = c.optJSONArray("children") ?: JSONArray()
            for (k in 0 until chArr.length()) {
                val ch = chArr.getJSONObject(k)
                val chSpArr = ch.optJSONArray("sports") ?: JSONArray()
                val chSports = mutableListOf<String>()
                for (l in 0 until chSpArr.length()) chSports.add(chSpArr.getString(l))

                chList.add(
                    ChildPartitionItem(
                        id = ch.getLong("id"),
                        name = ch.getString("name"),
                        partitionLabel = ch.optString("partition_label").takeIf { it.isNotBlank() && it != "null" },
                        price = ch.optInt("price", 1000),
                        seats = if (ch.has("seats") && !ch.isNull("seats")) ch.getInt("seats") else null,
                        sports = chSports,
                        isActive = ch.optBoolean("is_active", true)
                    )
                )
            }

            compList.add(
                CompositeCourtItem(
                    id = c.getLong("id"),
                    name = c.getString("name"),
                    kind = c.optString("kind", "court"),
                    isComposite = c.optBoolean("is_composite", true),
                    splitType = c.optString("split_type", "half"),
                    price = c.optInt("price", 1000),
                    sports = sports,
                    allowSimultaneousBooking = c.optBoolean("allow_simultaneous_booking", false),
                    children = chList
                )
            )
        }

        val standList = mutableListOf<StandaloneCourtItem>()
        val standArr = root.optJSONArray("standalone_courts") ?: JSONArray()
        for (i in 0 until standArr.length()) {
            val s = standArr.getJSONObject(i)
            val spArr = s.optJSONArray("sports") ?: JSONArray()
            val sports = mutableListOf<String>()
            for (j in 0 until spArr.length()) sports.add(spArr.getString(j))

            standList.add(
                StandaloneCourtItem(
                    id = s.getLong("id"),
                    name = s.getString("name"),
                    kind = s.optString("kind", "court"),
                    isComposite = s.optBoolean("is_composite", false),
                    price = s.optInt("price", 1000),
                    sports = sports
                )
            )
        }

        CourtHierarchyData(
            compositeCourts = compList,
            standaloneCourts = standList,
            totalComposite = root.optInt("total_composite", compList.size),
            totalStandalone = root.optInt("total_standalone", standList.size)
        )
    }

    suspend fun splitCourt(token: String, venueId: Long, req: SplitCourtRequest): Boolean = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/pricing/hierarchy/split"
        val payload = JSONObject().apply {
            put("court_id", req.courtId)
            put("split_type", req.splitType)
            val partArr = JSONArray()
            req.partitions.forEach { p ->
                partArr.put(JSONObject().apply {
                    put("name", p.name)
                    if (p.label != null) put("label", p.label)
                    if (p.price != null) put("price", p.price)
                })
            }
            put("partitions", partArr)
        }
        makeHttpRequest("POST", endpoint, token, payload.toString())
        true
    }

    suspend fun mergeCourts(token: String, venueId: Long, courtId: Long): Boolean = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/pricing/hierarchy/merge"
        val payload = JSONObject().apply {
            put("court_id", courtId)
        }
        makeHttpRequest("POST", endpoint, token, payload.toString())
        true
    }

    suspend fun applyRecommendation(token: String, venueId: Long, req: CreatePricingRuleRequest): Boolean = withContext(Dispatchers.IO) {
        val endpoint = "$baseUrl/api/partner/venues/$venueId/pricing/recommendations/apply"
        val payload = JSONObject().apply {
            put("name", req.name)
            put("rule_type", req.ruleType)
            put("weekdays", JSONArray(req.weekdays))
            put("start_time", req.startTime)
            put("end_time", req.endTime)
            put("pricing_mode", req.pricingMode)
            put("amount", req.amount)
            put("priority", req.priority)
        }
        makeHttpRequest("POST", endpoint, token, payload.toString())
        true
    }

    private fun makeHttpRequest(method: String, urlString: String, token: String, body: String? = null): String {
        val url = URL(urlString)
        val conn = url.openConnection() as HttpURLConnection
        conn.requestMethod = method
        conn.setRequestProperty("Authorization", "Bearer $token")
        conn.setRequestProperty("Accept", "application/json")
        conn.setRequestProperty("Content-Type", "application/json")
        conn.connectTimeout = 10000
        conn.readTimeout = 10000

        if (body != null && (method == "POST" || method == "PUT")) {
            conn.doOutput = true
            conn.outputStream.use { os ->
                os.write(body.toByteArray(Charsets.UTF_8))
            }
        }

        val code = conn.responseCode
        val stream = if (code in 200..299) conn.inputStream else conn.errorStream
        val reader = BufferedReader(InputStreamReader(stream ?: conn.inputStream))
        val response = reader.readText()
        reader.close()

        if (code !in 200..299) {
            val errorMsg = try {
                val errObj = JSONObject(response)
                errObj.optString("message", "HTTP $code: $response")
            } catch (_: Exception) {
                "HTTP $code: $response"
            }
            throw RuntimeException(errorMsg)
        }
        return response
    }
}
