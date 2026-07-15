package com.example.thanna.ui.matches

import kotlin.math.roundToInt

object MatchStatsMapper {
    fun parseBatterStats(statsStr: String): BatterStats? {
        if (statsStr.isBlank()) return null
        return try {
            // Support formats like "34 (19) · 3x4 · 2x6 · SR 178.9" or "34 (19)"
            val runsStr = statsStr.substringBefore("(").trim()
            val ballsStr = statsStr.substringAfter("(").substringBefore(")").trim()
            val runs = runsStr.toIntOrNull() ?: 0
            val balls = ballsStr.toIntOrNull() ?: 0

            var fours = 0
            if (statsStr.contains("4s:")) {
                fours = statsStr.substringAfter("4s:").substringBefore("·").trim().toIntOrNull() ?: 0
            } else if (statsStr.contains("x4")) {
                fours = statsStr.substringBefore("x4").substringAfterLast("·").trim().toIntOrNull() ?: 0
            }

            var sixes = 0
            if (statsStr.contains("6s:")) {
                sixes = statsStr.substringAfter("6s:").substringBefore("·").trim().toIntOrNull() ?: 0
            } else if (statsStr.contains("x6")) {
                sixes = statsStr.substringBefore("x6").substringAfterLast("·").trim().toIntOrNull() ?: 0
            }

            BatterStats(runs, balls, fours, sixes)
        } catch (e: Exception) {
            BatterStats(0, 0, 0, 0)
        }
    }

    fun parseBowlerStats(statsStr: String): BowlerStats? {
        if (statsStr.isBlank()) return null
        return try {
            // Support format like "1-41 (3.5)"
            val figures = statsStr.substringBefore("(").trim()
            val oversStr = statsStr.substringAfter("(").substringBefore(")").trim()

            val wickets = figures.substringBefore("-").trim().toIntOrNull() ?: 0
            val runs = figures.substringAfter("-").trim().toIntOrNull() ?: 0

            val oversVal = oversStr.toDoubleOrNull() ?: 0.0
            val overInt = oversVal.toInt()
            val ballPart = ((oversVal - overInt) * 10).roundToInt()
            val totalBalls = overInt * 6 + ballPart

            BowlerStats(wickets, runs, totalBalls)
        } catch (e: Exception) {
            BowlerStats(0, 0, 0)
        }
    }

    fun parsePartnership(partnershipStr: String): Partnership? {
        if (partnershipStr.isBlank()) return null
        return try {
            // Support format like "59 (93)"
            val runs = partnershipStr.substringBefore("(").trim().toIntOrNull() ?: 0
            val balls = partnershipStr.substringAfter("(").substringBefore(")").trim().toIntOrNull() ?: 0
            Partnership(runs, balls)
        } catch (e: Exception) {
            null
        }
    }

    fun parseLastWicket(lastWktStr: String): LastWicket? {
        if (lastWktStr.isBlank()) return null
        return try {
            // Support format like "R Sharma 21 (14)"
            val namePart = lastWktStr.substringBeforeLast(" ").substringBeforeLast(" ").trim()
            val afterName = lastWktStr.substringAfter(namePart).trim()
            val runs = afterName.substringBefore("(").trim().toIntOrNull() ?: 0
            val balls = afterName.substringAfter("(").substringBefore(")").trim().toIntOrNull() ?: 0
            val name = if (namePart.isBlank()) lastWktStr.substringBefore("(").substringBeforeLast(" ").trim() else namePart
            LastWicket(name, runs, balls)
        } catch (e: Exception) {
            LastWicket(lastWktStr.substringBefore("(").trim(), 0, 0)
        }
    }
}
