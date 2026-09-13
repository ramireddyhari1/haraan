package com.haraan.partner.register.data

import com.haraan.partner.ApiConfig
import com.haraan.partner.register.model.HistoricalShiftSummary
import com.haraan.partner.register.model.ShiftDropItem
import com.haraan.partner.register.model.ShiftPaymentItem
import com.haraan.partner.register.model.ShiftSessionUiModel
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URL

class ShiftRemoteDataSource(private val baseUrl: String = ApiConfig.BASE_URL) {

    suspend fun fetchCurrentShift(token: String, venueId: Long): ShiftSessionUiModel? = withContext(Dispatchers.IO) {
        val res = get("/api/partner/venues/$venueId/shift/current", token)
        val json = JSONObject(res)
        if (!json.optBoolean("has_open_shift", false)) {
            return@withContext null
        }
        val shiftObj = json.getJSONObject("shift")
        parseShift(shiftObj)
    }

    suspend fun openShift(
        token: String,
        venueId: Long,
        openingFloat: Double,
        note: String? = null,
    ): ShiftSessionUiModel = withContext(Dispatchers.IO) {
        val payload = JSONObject().apply {
            put("opening_float", openingFloat)
            if (note != null) put("note", note)
        }
        val res = post("/api/partner/venues/$venueId/shift/open", payload.toString(), token)
        val json = JSONObject(res)
        parseShift(json.getJSONObject("shift"))
    }

    suspend fun recordDrop(
        token: String,
        venueId: Long,
        amount: Double,
        category: String,
        reason: String? = null,
    ): ShiftSessionUiModel = withContext(Dispatchers.IO) {
        val payload = JSONObject().apply {
            put("amount", amount)
            put("category", category)
            if (reason != null) put("reason", reason)
        }
        val res = post("/api/partner/venues/$venueId/shift/drop", payload.toString(), token)
        val json = JSONObject(res)
        parseShift(json.getJSONObject("shift"))
    }

    suspend fun closeShift(
        token: String,
        venueId: Long,
        countedCash: Double,
        note: String? = null,
        denominations: Map<String, Int>? = null,
    ): ShiftSessionUiModel = withContext(Dispatchers.IO) {
        val payload = JSONObject().apply {
            put("counted_cash", countedCash)
            if (note != null) put("note", note)
            if (denominations != null) {
                val dObj = JSONObject()
                denominations.forEach { (k, v) -> dObj.put(k, v) }
                put("denominations", dObj)
            }
        }
        val res = post("/api/partner/venues/$venueId/shift/close", payload.toString(), token)
        val json = JSONObject(res)
        parseShift(json.getJSONObject("shift"))
    }

    suspend fun fetchHistory(token: String, venueId: Long): List<HistoricalShiftSummary> = withContext(Dispatchers.IO) {
        val res = get("/api/partner/venues/$venueId/shifts", token)
        val json = JSONObject(res)
        val arr = json.optJSONArray("data") ?: JSONArray()
        val list = mutableListOf<HistoricalShiftSummary>()
        for (i in 0 until arr.length()) {
            val o = arr.getJSONObject(i)
            list.add(
                HistoricalShiftSummary(
                    id = o.optLong("id"),
                    staffName = o.optString("staff_name", "Staff"),
                    closedBy = o.optString("closed_by").takeIf { it.isNotBlank() },
                    openedAt = o.optString("opened_at"),
                    closedAt = o.optString("closed_at").takeIf { it.isNotBlank() },
                    openingFloat = o.optDouble("opening_float", 0.0),
                    cashCollected = o.optDouble("cash_collected", 0.0),
                    totalDrops = o.optDouble("total_drops", 0.0),
                    expectedCash = o.optDouble("expected_cash", 0.0),
                    countedCash = if (o.isNull("counted_cash")) null else o.optDouble("counted_cash"),
                    variance = if (o.isNull("variance")) null else o.optDouble("variance"),
                    varianceLabel = o.optString("variance_label", "Square"),
                    note = o.optString("note").takeIf { it.isNotBlank() },
                )
            )
        }
        list
    }

    private fun parseShift(o: JSONObject): ShiftSessionUiModel {
        val payments = mutableListOf<ShiftPaymentItem>()
        val pArr = o.optJSONArray("payments")
        if (pArr != null) {
            for (i in 0 until pArr.length()) {
                val po = pArr.getJSONObject(i)
                payments.add(
                    ShiftPaymentItem(
                        id = po.optLong("id"),
                        amount = po.optDouble("amount", 0.0),
                        method = po.optString("method", "cash"),
                        customerName = po.optString("customer_name", "Walk-in Guest"),
                        time = po.optString("time", ""),
                    )
                )
            }
        }

        val drops = mutableListOf<ShiftDropItem>()
        val dArr = o.optJSONArray("drops")
        if (dArr != null) {
            for (i in 0 until dArr.length()) {
                val do_ = dArr.getJSONObject(i)
                drops.add(
                    ShiftDropItem(
                        id = do_.optLong("id"),
                        amount = do_.optDouble("amount", 0.0),
                        category = do_.optString("category", "expense"),
                        reason = do_.optString("reason").takeIf { it.isNotBlank() },
                        staffName = do_.optString("staff_name", "Staff"),
                        time = do_.optString("time", ""),
                    )
                )
            }
        }

        return ShiftSessionUiModel(
            shiftId = o.optLong("id"),
            venueId = o.optLong("venue_id"),
            venueName = o.optString("venue_name", "Venue"),
            isOpen = o.optBoolean("is_open", true),
            staffId = o.optLong("staff_id"),
            staffName = o.optString("staff_name", "Staff"),
            openedAt = o.optString("opened_at"),
            closedAt = o.optString("closed_at").takeIf { it.isNotBlank() },
            openingFloat = o.optDouble("opening_float", 0.0),
            cashCollected = o.optDouble("cash_collected", 0.0),
            upiCollected = o.optDouble("upi_collected", 0.0),
            cardCollected = o.optDouble("card_collected", 0.0),
            totalDrops = o.optDouble("total_drops", 0.0),
            expectedCash = o.optDouble("expected_cash", 0.0),
            countedCash = if (o.isNull("counted_cash")) null else o.optDouble("counted_cash"),
            variance = if (o.isNull("variance")) null else o.optDouble("variance"),
            varianceLabel = o.optString("variance_label", "Square"),
            note = o.optString("note").takeIf { it.isNotBlank() },
            payments = payments,
            drops = drops,
        )
    }

    private fun get(path: String, token: String?): String = request("GET", path, null, token)
    private fun post(path: String, body: String, token: String?): String = request("POST", path, body, token)

    private fun request(method: String, path: String, body: String?, token: String?): String {
        val conn = (URL(baseUrl + path).openConnection() as HttpURLConnection).apply {
            requestMethod = method
            connectTimeout = 15_000
            readTimeout = 20_000
            setRequestProperty("Accept", "application/json")
            if (token != null) setRequestProperty("Authorization", "Bearer $token")
            if (body != null) {
                doOutput = true
                setRequestProperty("Content-Type", "application/json; charset=utf-8")
                OutputStreamWriter(outputStream, Charsets.UTF_8).use { it.write(body) }
            }
        }

        val code = conn.responseCode
        val stream = if (code in 200..299) conn.inputStream else (conn.errorStream ?: conn.inputStream)
        val text = BufferedReader(InputStreamReader(stream, Charsets.UTF_8)).use { it.readText() }

        if (code !in 200..299) {
            val errJson = runCatching { JSONObject(text) }.getOrNull()
            val msg = errJson?.optString("error")?.takeIf { it.isNotBlank() }
                ?: errJson?.optString("message")?.takeIf { it.isNotBlank() }
                ?: "HTTP $code: $text"
            throw Exception(msg)
        }
        return text
    }
}
