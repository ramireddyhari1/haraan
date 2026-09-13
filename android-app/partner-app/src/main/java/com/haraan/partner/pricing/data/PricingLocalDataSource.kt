package com.haraan.partner.pricing.data

import android.content.ContentValues
import android.content.Context
import com.haraan.partner.pricing.model.*
import org.json.JSONArray
import org.json.JSONObject

class PricingLocalDataSource(context: Context) {
    private val dbHelper = PricingDatabaseHelper(context)

    fun getCachedMatrix(venueId: Long, courtId: Long): WeeklyPricingMatrix? {
        val db = dbHelper.readableDatabase
        val cursor = db.query(
            PricingDatabaseHelper.TABLE_MATRIX_CACHE,
            arrayOf("matrix_json"),
            "venue_id = ? AND court_id = ?",
            arrayOf(venueId.toString(), courtId.toString()),
            null, null, null
        )
        return cursor.use {
            if (it.moveToFirst()) {
                val jsonStr = it.getString(0)
                parseMatrixJson(jsonStr)
            } else null
        }
    }

    fun saveCachedMatrix(venueId: Long, courtId: Long, matrix: WeeklyPricingMatrix) {
        val db = dbHelper.writableDatabase
        val values = ContentValues().apply {
            put("venue_id", venueId)
            put("court_id", courtId)
            put("matrix_json", serializeMatrix(matrix).toString())
            put("updated_at", System.currentTimeMillis())
        }
        db.insertWithOnConflict(
            PricingDatabaseHelper.TABLE_MATRIX_CACHE,
            null,
            values,
            android.database.sqlite.SQLiteDatabase.CONFLICT_REPLACE
        )
    }

    fun getCachedRules(venueId: Long): List<PricingRuleItem>? {
        val db = dbHelper.readableDatabase
        val cursor = db.query(
            PricingDatabaseHelper.TABLE_RULES_CACHE,
            arrayOf("rules_json"),
            "venue_id = ?",
            arrayOf(venueId.toString()),
            null, null, null
        )
        return cursor.use {
            if (it.moveToFirst()) {
                val jsonStr = it.getString(0)
                parseRulesJson(jsonStr)
            } else null
        }
    }

    fun saveCachedRules(venueId: Long, rules: List<PricingRuleItem>) {
        val db = dbHelper.writableDatabase
        val values = ContentValues().apply {
            put("venue_id", venueId)
            put("rules_json", serializeRules(rules).toString())
            put("updated_at", System.currentTimeMillis())
        }
        db.insertWithOnConflict(
            PricingDatabaseHelper.TABLE_RULES_CACHE,
            null,
            values,
            android.database.sqlite.SQLiteDatabase.CONFLICT_REPLACE
        )
    }

    fun getCachedHierarchy(venueId: Long): CourtHierarchyData? {
        val db = dbHelper.readableDatabase
        val cursor = db.query(
            PricingDatabaseHelper.TABLE_HIERARCHY_CACHE,
            arrayOf("hierarchy_json"),
            "venue_id = ?",
            arrayOf(venueId.toString()),
            null, null, null
        )
        return cursor.use {
            if (it.moveToFirst()) {
                val jsonStr = it.getString(0)
                parseHierarchyJson(jsonStr)
            } else null
        }
    }

    fun saveCachedHierarchy(venueId: Long, hierarchy: CourtHierarchyData) {
        val db = dbHelper.writableDatabase
        val values = ContentValues().apply {
            put("venue_id", venueId)
            put("hierarchy_json", serializeHierarchy(hierarchy).toString())
            put("updated_at", System.currentTimeMillis())
        }
        db.insertWithOnConflict(
            PricingDatabaseHelper.TABLE_HIERARCHY_CACHE,
            null,
            values,
            android.database.sqlite.SQLiteDatabase.CONFLICT_REPLACE
        )
    }

    fun enqueueOfflineAction(action: OfflinePricingAction) {
        val db = dbHelper.writableDatabase
        val values = ContentValues().apply {
            put("action_type", action.actionType)
            put("venue_id", action.venueId)
            put("payload_json", action.payloadJson)
            put("created_at", action.createdAt)
        }
        db.insert(PricingDatabaseHelper.TABLE_OFFLINE_ACTIONS, null, values)
    }

    fun getPendingActions(): List<OfflinePricingAction> {
        val db = dbHelper.readableDatabase
        val cursor = db.query(
            PricingDatabaseHelper.TABLE_OFFLINE_ACTIONS,
            null, null, null, null, null, "id ASC"
        )
        val list = mutableListOf<OfflinePricingAction>()
        cursor.use {
            while (it.moveToNext()) {
                list.add(
                    OfflinePricingAction(
                        id = it.getLong(it.getColumnIndexOrThrow("id")),
                        actionType = it.getString(it.getColumnIndexOrThrow("action_type")),
                        venueId = it.getLong(it.getColumnIndexOrThrow("venue_id")),
                        payloadJson = it.getString(it.getColumnIndexOrThrow("payload_json")),
                        createdAt = it.getLong(it.getColumnIndexOrThrow("created_at"))
                    )
                )
            }
        }
        return list
    }

    fun deletePendingAction(id: Long) {
        val db = dbHelper.writableDatabase
        db.delete(PricingDatabaseHelper.TABLE_OFFLINE_ACTIONS, "id = ?", arrayOf(id.toString()))
    }

    // JSON serialization helpers
    private fun serializeMatrix(matrix: WeeklyPricingMatrix): JSONObject {
        return JSONObject().apply {
            put("court_id", matrix.courtId)
            put("court_name", matrix.courtName)
            put("base_rate", matrix.baseRate)
            put("min_rate", matrix.minRate)
            put("max_rate", matrix.maxRate)
            put("average_rate", matrix.averageRate)
            val matrixObj = JSONObject()
            matrix.matrix.forEach { (day, schedule) ->
                val dayObj = JSONObject().apply {
                    put("day_name", schedule.dayName)
                    put("date", schedule.date)
                    val slotsArr = JSONArray()
                    schedule.slots.forEach { s ->
                        slotsArr.put(JSONObject().apply {
                            put("hour", s.hour)
                            put("time_label", s.timeLabel)
                            put("rate", s.rate)
                            put("base_rate", s.baseRate)
                            put("rule_id", s.ruleId)
                            put("rule_name", s.ruleName)
                            put("mode", s.mode)
                            put("is_peak", s.isPeak)
                            put("tag", s.tag)
                        })
                    }
                    put("slots", slotsArr)
                }
                matrixObj.put(day, dayObj)
            }
            put("matrix", matrixObj)
        }
    }

    private fun parseMatrixJson(jsonStr: String): WeeklyPricingMatrix {
        val root = JSONObject(jsonStr)
        val matrixMap = mutableMapOf<String, DayPricingSchedule>()
        val matrixObj = root.optJSONObject("matrix") ?: JSONObject()
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
        return WeeklyPricingMatrix(
            courtId = if (root.has("court_id") && !root.isNull("court_id")) root.getLong("court_id") else null,
            courtName = root.optString("court_name", "Court"),
            baseRate = root.optInt("base_rate", 1000),
            minRate = root.optInt("min_rate", 1000),
            maxRate = root.optInt("max_rate", 1000),
            averageRate = root.optInt("average_rate", 1000),
            matrix = matrixMap
        )
    }

    private fun serializeRules(rules: List<PricingRuleItem>): JSONArray {
        val arr = JSONArray()
        rules.forEach { r ->
            arr.put(JSONObject().apply {
                put("id", r.id)
                put("venue_id", r.venueId)
                put("venue_court_id", r.venueCourtId)
                put("court_name", r.courtName)
                put("name", r.name)
                put("rule_type", r.ruleType)
                put("weekdays", JSONArray(r.weekdays))
                put("start_time", r.startTime)
                put("end_time", r.endTime)
                put("date_from", r.dateFrom)
                put("date_to", r.dateTo)
                put("pricing_mode", r.pricingMode)
                put("amount", r.amount)
                put("min_price", r.minPrice)
                put("max_price", r.maxPrice)
                put("priority", r.priority)
                put("is_active", r.isActive)
                put("created_at", r.createdAt)
            })
        }
        return arr
    }

    private fun parseRulesJson(jsonStr: String): List<PricingRuleItem> {
        val arr = JSONArray(jsonStr)
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
        return list
    }

    private fun serializeHierarchy(hierarchy: CourtHierarchyData): JSONObject {
        return JSONObject().apply {
            put("total_composite", hierarchy.totalComposite)
            put("total_standalone", hierarchy.totalStandalone)

            val compArr = JSONArray()
            hierarchy.compositeCourts.forEach { c ->
                compArr.put(JSONObject().apply {
                    put("id", c.id)
                    put("name", c.name)
                    put("kind", c.kind)
                    put("is_composite", c.isComposite)
                    put("split_type", c.splitType)
                    put("price", c.price)
                    put("sports", JSONArray(c.sports))
                    put("allow_simultaneous_booking", c.allowSimultaneousBooking)
                    val chArr = JSONArray()
                    c.children.forEach { ch ->
                        chArr.put(JSONObject().apply {
                            put("id", ch.id)
                            put("name", ch.name)
                            put("partition_label", ch.partitionLabel)
                            put("price", ch.price)
                            put("seats", ch.seats)
                            put("sports", JSONArray(ch.sports))
                            put("is_active", ch.isActive)
                        })
                    }
                    put("children", chArr)
                })
            }
            put("composite_courts", compArr)

            val standArr = JSONArray()
            hierarchy.standaloneCourts.forEach { s ->
                standArr.put(JSONObject().apply {
                    put("id", s.id)
                    put("name", s.name)
                    put("kind", s.kind)
                    put("is_composite", s.isComposite)
                    put("price", s.price)
                    put("sports", JSONArray(s.sports))
                })
            }
            put("standalone_courts", standArr)
        }
    }

    private fun parseHierarchyJson(jsonStr: String): CourtHierarchyData {
        val root = JSONObject(jsonStr)
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

        return CourtHierarchyData(
            compositeCourts = compList,
            standaloneCourts = standList,
            totalComposite = root.optInt("total_composite", compList.size),
            totalStandalone = root.optInt("total_standalone", standList.size)
        )
    }
}
