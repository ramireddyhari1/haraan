package com.example.thanna.ui.matches

import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlin.math.roundToInt

/**
 * Isolated demo simulator that runs a coroutine to update the MatchUiState
 * dynamically every 7 seconds, demonstrating the live scorecard updates,
 * scoring marquee, score count-ups, and the outcome ticker transitions.
 */
object DemoMatchSimulator {
    private var job: Job? = null

    fun start(
        initialState: MatchUiState,
        scope: CoroutineScope,
        updateState: (MatchUiState) -> Unit
    ) {
        job?.cancel()
        var mockState = initialState
        job = scope.launch {
            // Wait for initial load
            delay(1000)
            
            // Simulation balls sequence
            val sequence = listOf(
                Pair("•", 0), // Dot
                Pair("4", 4), // Four
                Pair("1", 1), // Single
                Pair("W", 0), // Wicket
                Pair("6", 6), // Six
                Pair("2", 2)  // Double (MI wins!)
            )
            
            var seqIdx = 0
            
            // Batters state
            var strikerName = mockState.striker
            var nonStrikerName = mockState.nonStriker
            
            var strikerStats = mockState.strikerStats ?: BatterStats(34, 19, 3, 2)
            var nonStrikerStats = mockState.nonStrikerStats ?: BatterStats(28, 16, 2, 2)
            
            // Bowler state
            var bowlerName = mockState.bowler
            var bowlerStats = mockState.bowlerStats ?: BowlerStats(1, 41, 23) // 3.5 overs = 23 balls
            
            // Partnership
            var pShipRuns = mockState.partnership?.runs ?: 59
            var pShipBalls = mockState.partnership?.balls ?: 93
            
            // Last wicket
            var lastWkt = mockState.lastWicket ?: LastWicket("R Sharma", 21, 14)
            
            // Track total balls in the match
            val totalOversVal = mockState.overs.toDoubleOrNull() ?: 19.2
            val totalOversInt = totalOversVal.toInt()
            val totalOversBalls = ((totalOversVal - totalOversInt) * 10).roundToInt()
            var matchBallsBowled = totalOversInt * 6 + totalOversBalls
            
            // List of upcoming batters for wicket simulation
            val squadBatters = if (mockState.team1 == "MI") {
                listOf("R Shepherd", "G Coetzee", "J Bumrah", "P Chawla")
            } else {
                listOf("R Jadeja", "MS Dhoni", "S Thakur", "D Chahar")
            }
            var nextBatterIdx = 0
            
            while (true) {
                delay(7000) // 7 seconds per ball
                
                val (ball, runs) = sequence[seqIdx]
                seqIdx = (seqIdx + 1) % sequence.size
                
                // Update thisOver and recentOvers
                val nextOverList = mockState.thisOver.toMutableList()
                val updatedRecentOvers = mockState.recentOvers.toMutableList()
                if (nextOverList.size >= 6) {
                    val completedOverLabel = (matchBallsBowled / 6).toString()
                    val completedOverRuns = nextOverList.sumOf { b ->
                        val r = b.toIntOrNull()
                        if (r != null) r else if (b.startsWith("wd", ignoreCase = true) || b.startsWith("nb", ignoreCase = true)) 1 else 0
                    }
                    val completedOverObj = RecentOver(
                        label = completedOverLabel,
                        runs = completedOverRuns,
                        balls = nextOverList.toList()
                    )
                    updatedRecentOvers.add(completedOverObj)
                    if (updatedRecentOvers.size > 3) {
                        updatedRecentOvers.removeAt(0)
                    }
                    nextOverList.clear() // Start new over simulation
                }
                nextOverList.add(ball)
                
                // Calculate new score, wickets, balls left, runs needed
                val currentParts = mockState.score.split("/")
                var currentRuns = currentParts.getOrNull(0)?.toIntOrNull() ?: 0
                var currentWickets = currentParts.getOrNull(1)?.toIntOrNull() ?: 0
                
                currentRuns += runs
                
                // Track match balls bowled
                matchBallsBowled += 1
                val nextOvers = "${matchBallsBowled / 6}.${matchBallsBowled % 6}"
                
                val ballsLeft = mockState.ballsLeft
                val runsNeeded = mockState.runsNeeded

                val nextBallsLeft = if (ballsLeft != null) {
                    if (ballsLeft <= 1) 6 else ballsLeft - 1
                } else null
                
                val nextRunsNeeded = if (runsNeeded != null) {
                    (runsNeeded - runs).coerceAtLeast(0)
                } else null
                
                // The chase is decided once the target is knocked off or the side is bowled
                // out — then the demo freezes at a believable score instead of ballooning.
                val chaseWon = nextRunsNeeded == 0
                val allOut = currentWickets >= 10
                val decided = chaseWon || allOut
                val nextStatus = when {
                    chaseWon -> "${mockState.team1} won by ${10 - currentWickets} wickets"
                    allOut -> "${mockState.team1} all out — ${mockState.team2} won"
                    else -> "${mockState.team1} need $nextRunsNeeded runs from $nextBallsLeft balls"
                }

                // Increment striker balls, bowler balls, partnership balls
                var sRuns = strikerStats.runs
                var sBalls = strikerStats.balls + 1
                var sFours = strikerStats.fours
                var sSixes = strikerStats.sixes
                
                var bWickets = bowlerStats.wickets
                var bRuns = bowlerStats.runs
                var bBallsConceded = bowlerStats.balls + 1
                
                pShipBalls += 1
                
                if (ball == "W") {
                    currentWickets = (currentWickets + 1).coerceAtMost(10) // innings caps at 10 down
                    bWickets += 1
                    
                    // Update last wicket
                    lastWkt = LastWicket(strikerName, sRuns, sBalls)
                    
                    // Reset partnership
                    pShipRuns = 0
                    pShipBalls = 0
                    
                    // Bring in new batter
                    strikerName = squadBatters.getOrNull(nextBatterIdx % squadBatters.size) ?: "New Batter"
                    nextBatterIdx += 1
                    
                    sRuns = 0
                    sBalls = 0
                    sFours = 0
                    sSixes = 0
                } else {
                    sRuns += runs
                    pShipRuns += runs
                    bRuns += runs
                    if (runs == 4) {
                        sFours += 1
                    } else if (runs == 6) {
                        sSixes += 1
                    }
                    
                    strikerStats = BatterStats(sRuns, sBalls, sFours, sSixes)
                    bowlerStats = BowlerStats(bWickets, bRuns, bBallsConceded)
                    
                    // Rotate strike if runs are odd
                    if (runs % 2 == 1) {
                        // Swap striker and non-striker names
                        val tempName = strikerName
                        strikerName = nonStrikerName
                        nonStrikerName = tempName
                        
                        // Swap stats
                        val tempStats = strikerStats
                        strikerStats = nonStrikerStats
                        nonStrikerStats = tempStats
                    }
                }
                
                if (ball == "W") {
                    strikerStats = BatterStats(sRuns, sBalls, sFours, sSixes)
                    bowlerStats = BowlerStats(bWickets, bRuns, bBallsConceded)
                }

                mockState = mockState.copy(
                    score = "$currentRuns/$currentWickets",
                    overs = nextOvers,
                    thisOver = nextOverList,
                    recentOvers = updatedRecentOvers,
                    // Once decided, clear the chase equation so the hero stops showing a
                    // "need 0 from N" line and just reads the result.
                    ballsLeft = if (decided) null else nextBallsLeft,
                    runsNeeded = if (decided) null else nextRunsNeeded,
                    status = nextStatus,
                    striker = strikerName,
                    strikerStats = strikerStats,
                    nonStriker = nonStrikerName,
                    nonStrikerStats = nonStrikerStats,
                    bowler = bowlerName,
                    bowlerStats = bowlerStats,
                    partnership = Partnership(pShipRuns, pShipBalls),
                    lastWicket = lastWkt
                )

                updateState(mockState)

                // Demo reached its climax — freeze at a realistic final score.
                if (decided) break
            }
        }
    }

    fun stop() {
        job?.cancel()
        job = null
    }
}
