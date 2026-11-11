package com.haraan.app.data

import com.haraan.app.BuildConfig

/**
 * DEBUG BUILDS ONLY: stand-in rows for ActionBoard's District and State tabs, so the
 * boards can be checked for layout while the real ranked ledger is still empty.
 *
 * Used only when the real API returns nothing (or the viewer has no district set).
 * [enabled] is false in release builds, so a shipped app never shows these players:
 * the real boards' rule is "never invent a player".
 */
object DemoLeaderboard {
  val enabled: Boolean = BuildConfig.DEBUG

  const val DISTRICT = "NTR"
  const val STATE = "Andhra Pradesh"

  // name, district, monthly XP, matches
  private val PLAYERS = listOf(
    Triple("Venkatesh Naidu", "NTR", 2840 to 9),
    Triple("Harsha Vardhan", "NTR", 2610 to 11),
    Triple("Pavan Kalyan R", "NTR", 2395 to 8),
    Triple("Srinivas Reddy", "NTR", 2120 to 10),
    Triple("Mahesh Chowdary", "NTR", 1985 to 7),
    Triple("Teja Sai", "NTR", 1730 to 9),
    Triple("Sai Kiran", "NTR", 1520 to 6),
    Triple("Lokesh Varma", "NTR", 1390 to 8),
    Triple("Nikhil Prasad", "NTR", 1240 to 5),
    Triple("Ganesh Babu", "NTR", 1105 to 6),
    Triple("Ravi Kumar", "NTR", 1060 to 7),
    Triple("Rohit Siddharth", "NTR", 960 to 4),
    Triple("Ravi Teja", "NTR", 890 to 5),
    Triple("Aditya Kumar", "NTR", 820 to 4),
    Triple("Kiran Rao", "NTR", 700 to 4),
    Triple("Sandeep Rao", "NTR", 640 to 3),
    Triple("Vamsi Krishna", "NTR", 470 to 3),
    Triple("Charan Tej", "NTR", 310 to 2),
    Triple("Karthik Raju", "Visakhapatnam", 3120 to 12),
    Triple("Bharath Simha", "Visakhapatnam", 2270 to 9),
    Triple("Prudhvi Raj", "Guntur", 2980 to 11),
    Triple("Yaswanth Reddy", "Guntur", 1820 to 7),
    Triple("Dinesh Kumar", "Guntur", 905 to 4),
    Triple("Sai Charan", "Krishna", 2450 to 10),
    Triple("Manoj Varma", "Krishna", 1310 to 6),
    Triple("Raghu Ram", "East Godavari", 2060 to 8),
    Triple("Naveen Chandra", "East Godavari", 1150 to 5),
    Triple("Abhishek Naidu", "Nellore", 1890 to 8),
    Triple("Jagadeesh P", "Nellore", 720 to 3),
    Triple("Hemanth Kumar", "Kurnool", 1640 to 7),
    Triple("Sravan Teja", "Kurnool", 560 to 3),
    Triple("Chaitanya Reddy", "Chittoor", 2190 to 9),
    Triple("Vinay Sagar", "Chittoor", 1020 to 5),
    Triple("Rakesh Goud", "Chittoor", 415 to 2),
  )

  /**
   * Demo board for a scope. The district board shows NTR's players under whatever
   * district name the viewer has; the state board ranks every demo player.
   */
  fun board(isStateBoard: Boolean, location: String?): List<LeaderboardRow> {
    val picked = if (isStateBoard) PLAYERS else PLAYERS.filter { it.second == DISTRICT }
    val district = if (isStateBoard) null else location?.takeIf { it.isNotBlank() }
    val state = if (isStateBoard) location?.takeIf { it.isNotBlank() } ?: STATE else STATE
    return picked
      .sortedByDescending { it.third.first }
      .mapIndexed { i, (name, d, xpMatches) ->
        LeaderboardRow(
          rank = i + 1,
          playerId = "",
          name = name,
          avatar = null,
          district = district ?: d,
          state = state,
          xp = xpMatches.first,
          matches = xpMatches.second,
        )
      }
  }

  /** Demo "Your district" card, for a viewer the server has no district summary for. */
  fun summary(): DistrictSummary = DistrictSummary(
    district = DISTRICT,
    state = STATE,
    liveMatches = 4,
    totalMatches = 37,
    players = 18,
    topBatter = DistrictTopPlayer("", "Pavan Kalyan R", null, 1410),
    topBowler = DistrictTopPlayer("", "Srinivas Reddy", null, 52),
    districtRank = 2,
    districtRankTotal = 8,
  )
}
