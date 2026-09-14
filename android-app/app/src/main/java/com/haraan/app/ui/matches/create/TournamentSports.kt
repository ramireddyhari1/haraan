package com.haraan.app.ui.matches.create

/**
 * What a tournament is made of in each non-cricket sport. Mirrors App\Models\Tournament::SPORTS
 * on the server — which is the authority: it re-derives a preset's numbers and validates a
 * custom format against these same ranges. Cricket keeps its own richer model
 * ([TournamentFormat], [BallType], [GroundType]).
 */

/** One number (or named choice) a format is made of. */
sealed interface TournamentRule {
    val key: String
    val label: String

    /** A stepper between [min] and [max]. */
    data class Range(
        override val key: String,
        override val label: String,
        val min: Int,
        val max: Int,
        val suffix: String = "",
        val step: Int = 1,
    ) : TournamentRule

    /** One of a fixed set of numbers — "best of" 1, 3 or 5. */
    data class Choice(
        override val key: String,
        override val label: String,
        val options: List<Int>,
        val optionLabel: (Int) -> String = { it.toString() },
    ) : TournamentRule

    /** One of a few named choices — tennis's deciding set. */
    data class Named(
        override val key: String,
        override val label: String,
        val options: List<Pair<String, String>>,
    ) : TournamentRule
}

data class TournamentPreset(
    val key: String,
    val label: String,
    val sub: String,
    /** Players a side the preset implies; null for racquet sports. */
    val players: Int?,
    /** Rule key → value (Int, or String for named rules). */
    val rules: Map<String, Any>,
)

/** A sport-specific extra: a named choice with a default, or an optional number. */
sealed interface TournamentOption {
    val key: String
    val label: String

    data class Named(
        override val key: String,
        override val label: String,
        val options: List<Pair<String, String>>,
        val default: String,
    ) : TournamentOption

    data class OptionalNumber(
        override val key: String,
        override val label: String,
        val min: Int,
        val max: Int,
        val suffix: String,
        val placeholder: String,
    ) : TournamentOption
}

data class TournamentSportSpec(
    val key: String,
    val label: String,
    /** Played by sides (players a side + a gender) rather than by entries in events. */
    val team: Boolean,
    val playersRange: IntRange,
    /** What one side is called on the players stepper: "Per side", "On the mat". */
    val playersLabel: String,
    val rules: List<TournamentRule>,
    val presets: List<TournamentPreset>,
    /** The starting numbers of a custom format. */
    val customDefaults: Map<String, Any>,
    val customPlayers: Int,
    val events: List<Pair<String, String>> = emptyList(),
    val surfaceTitle: String = "Surface",
    val surfaces: List<Pair<String, String>> = emptyList(),
    val options: List<TournamentOption> = emptyList(),
) {
    val hasEvents: Boolean get() = events.isNotEmpty()

    companion object {
        private val racquetEvents = listOf(
            "mens_singles" to "Men's singles",
            "womens_singles" to "Women's singles",
            "mens_doubles" to "Men's doubles",
            "womens_doubles" to "Women's doubles",
            "mixed_doubles" to "Mixed doubles",
        )

        private fun bestOf(noun: String): (Int) -> String = { n -> if (n == 1) "One $noun" else "Best of $n" }

        val Football = TournamentSportSpec(
            key = "football",
            label = "Football",
            team = true,
            playersRange = 3..11,
            playersLabel = "Players per side",
            rules = listOf(
                TournamentRule.Range("halves", "Halves", 1, 2),
                TournamentRule.Range("half_minutes", "Minutes per half", 5, 60, suffix = "min", step = 5),
            ),
            presets = listOf(
                TournamentPreset("eleven", "11-a-side", "2 × 45 min", 11, mapOf("halves" to 2, "half_minutes" to 45)),
                TournamentPreset("nine", "9-a-side", "2 × 30 min", 9, mapOf("halves" to 2, "half_minutes" to 30)),
                TournamentPreset("seven", "7-a-side", "2 × 25 min", 7, mapOf("halves" to 2, "half_minutes" to 25)),
                TournamentPreset("five", "5-a-side", "2 × 20 min", 5, mapOf("halves" to 2, "half_minutes" to 20)),
                TournamentPreset("futsal", "Futsal", "Indoor, 2 × 20 min", 5, mapOf("halves" to 2, "half_minutes" to 20)),
            ),
            customDefaults = mapOf("halves" to 2, "half_minutes" to 30),
            customPlayers = 8,
            surfaces = listOf(
                "grass" to "Natural grass",
                "artificial" to "Artificial turf",
                "indoor" to "Indoor court",
                "sand" to "Sand",
                "mud" to "Mud ground",
            ),
            options = listOf(
                TournamentOption.Named(
                    "knockout_tiebreak", "Knockout draws decided by",
                    listOf("penalties" to "Penalty shoot-out", "extra_time_penalties" to "Extra time, then penalties"),
                    default = "penalties",
                ),
            ),
        )

        val Badminton = TournamentSportSpec(
            key = "badminton",
            label = "Badminton",
            team = false,
            playersRange = 1..2,
            playersLabel = "",
            rules = listOf(
                TournamentRule.Choice("best_of", "Games", listOf(1, 3, 5), bestOf("game")),
                TournamentRule.Range("points_to", "Points per game", 11, 30),
            ),
            presets = listOf(
                TournamentPreset("standard", "Standard", "Best of 3 to 21", null, mapOf("best_of" to 3, "points_to" to 21)),
                TournamentPreset("short", "Short games", "Best of 3 to 15", null, mapOf("best_of" to 3, "points_to" to 15)),
                TournamentPreset("one_game", "One game", "Single game to 21", null, mapOf("best_of" to 1, "points_to" to 21)),
                TournamentPreset("fast11", "Fast 11s", "Best of 5 to 11", null, mapOf("best_of" to 5, "points_to" to 11)),
            ),
            customDefaults = mapOf("best_of" to 3, "points_to" to 21),
            customPlayers = 1,
            events = racquetEvents,
            surfaceTitle = "Court",
            surfaces = listOf(
                "wooden" to "Wooden court",
                "synthetic" to "Synthetic mat",
                "cement" to "Outdoor cement",
            ),
            options = listOf(
                TournamentOption.Named("shuttle", "Shuttle", listOf("feather" to "Feather", "nylon" to "Nylon"), default = "feather"),
            ),
        )

        val Volleyball = TournamentSportSpec(
            key = "volleyball",
            label = "Volleyball",
            team = true,
            playersRange = 2..9,
            playersLabel = "Players per side",
            rules = listOf(
                TournamentRule.Choice("best_of", "Sets", listOf(1, 3, 5), bestOf("set")),
                TournamentRule.Range("points_to", "Points per set", 15, 30),
                TournamentRule.Range("decider_to", "Deciding set to", 15, 30),
            ),
            presets = listOf(
                TournamentPreset("indoor", "Indoor 6s", "Best of 5 to 25", 6, mapOf("best_of" to 5, "points_to" to 25, "decider_to" to 15)),
                TournamentPreset("indoor_bo3", "Indoor, best of 3", "Best of 3 to 25", 6, mapOf("best_of" to 3, "points_to" to 25, "decider_to" to 15)),
                TournamentPreset("beach", "Beach 2s", "Best of 3 to 21", 2, mapOf("best_of" to 3, "points_to" to 21, "decider_to" to 15)),
                TournamentPreset("one_set", "One set", "Single set to 25", 6, mapOf("best_of" to 1, "points_to" to 25, "decider_to" to 25)),
            ),
            customDefaults = mapOf("best_of" to 3, "points_to" to 25, "decider_to" to 15),
            customPlayers = 6,
            surfaceTitle = "Court",
            surfaces = listOf(
                "indoor" to "Indoor court",
                "sand" to "Sand",
                "grass" to "Grass",
                "outdoor" to "Outdoor hard court",
            ),
        )

        val Basketball = TournamentSportSpec(
            key = "basketball",
            label = "Basketball",
            team = true,
            playersRange = 3..5,
            playersLabel = "Players per side",
            rules = listOf(
                TournamentRule.Choice("periods", "Periods", listOf(1, 2, 4)) { n ->
                    when (n) { 1 -> "One period"; 2 -> "Two halves"; else -> "Four quarters" }
                },
                TournamentRule.Range("period_minutes", "Minutes per period", 5, 20, suffix = "min"),
            ),
            presets = listOf(
                TournamentPreset("fiba", "5x5 FIBA", "4 × 10 min", 5, mapOf("periods" to 4, "period_minutes" to 10)),
                TournamentPreset("long", "12-minute quarters", "4 × 12 min", 5, mapOf("periods" to 4, "period_minutes" to 12)),
                TournamentPreset("halves", "Two halves", "2 × 20 min", 5, mapOf("periods" to 2, "period_minutes" to 20)),
                TournamentPreset("three", "3x3", "One 10-min period", 3, mapOf("periods" to 1, "period_minutes" to 10)),
            ),
            customDefaults = mapOf("periods" to 4, "period_minutes" to 8),
            customPlayers = 5,
            surfaceTitle = "Court",
            surfaces = listOf(
                "indoor" to "Indoor wooden",
                "outdoor" to "Outdoor concrete",
                "synthetic" to "Synthetic tiles",
            ),
        )

        val Kabaddi = TournamentSportSpec(
            key = "kabaddi",
            label = "Kabaddi",
            team = true,
            playersRange = 4..8,
            playersLabel = "Players on the mat",
            rules = listOf(
                TournamentRule.Range("halves", "Halves", 1, 2),
                TournamentRule.Range("half_minutes", "Minutes per half", 5, 25, suffix = "min"),
            ),
            presets = listOf(
                TournamentPreset("standard", "Standard", "7 on the mat, 2 × 20 min", 7, mapOf("halves" to 2, "half_minutes" to 20)),
                TournamentPreset("short", "Short halves", "7 on the mat, 2 × 15 min", 7, mapOf("halves" to 2, "half_minutes" to 15)),
                TournamentPreset("beach", "Beach kabaddi", "4 a side, 2 × 15 min", 4, mapOf("halves" to 2, "half_minutes" to 15)),
                TournamentPreset("circle", "Circle style", "8 a side, 2 × 20 min", 8, mapOf("halves" to 2, "half_minutes" to 20)),
            ),
            customDefaults = mapOf("halves" to 2, "half_minutes" to 15),
            customPlayers = 7,
            surfaces = listOf(
                "mat" to "Synthetic mat",
                "mud" to "Mud ground",
                "sand" to "Sand",
            ),
            options = listOf(
                TournamentOption.OptionalNumber("weight_limit_kg", "Weight limit (optional)", 30, 120, "kg", "No limit"),
            ),
        )

        val Tennis = TournamentSportSpec(
            key = "tennis",
            label = "Tennis",
            team = false,
            playersRange = 1..2,
            playersLabel = "",
            rules = listOf(
                TournamentRule.Choice("best_of", "Sets", listOf(1, 3, 5), bestOf("set")),
                TournamentRule.Range("games_to", "Games per set", 4, 9),
                TournamentRule.Named(
                    "final_set", "Deciding set",
                    listOf("full" to "Full set", "super_tiebreak" to "Super tiebreak to 10"),
                ),
            ),
            presets = listOf(
                TournamentPreset("best3", "Best of 3", "Full sets to 6", null, mapOf("best_of" to 3, "games_to" to 6, "final_set" to "full")),
                TournamentPreset("super_tiebreak", "Super tiebreak", "Best of 3, decider to 10", null, mapOf("best_of" to 3, "games_to" to 6, "final_set" to "super_tiebreak")),
                TournamentPreset("pro_set", "Pro set", "One set to 8 games", null, mapOf("best_of" to 1, "games_to" to 8, "final_set" to "full")),
                TournamentPreset("fast4", "Fast4", "Best of 3 sets to 4", null, mapOf("best_of" to 3, "games_to" to 4, "final_set" to "full")),
                TournamentPreset("best5", "Best of 5", "Full sets to 6", null, mapOf("best_of" to 5, "games_to" to 6, "final_set" to "full")),
            ),
            customDefaults = mapOf("best_of" to 3, "games_to" to 6, "final_set" to "full"),
            customPlayers = 1,
            events = racquetEvents,
            surfaceTitle = "Court",
            surfaces = listOf(
                "hard" to "Hard court",
                "clay" to "Clay",
                "grass" to "Grass",
                "synthetic" to "Artificial grass",
            ),
        )

        val TableTennis = TournamentSportSpec(
            key = "table_tennis",
            label = "Table Tennis",
            team = false,
            playersRange = 1..2,
            playersLabel = "",
            rules = listOf(
                TournamentRule.Choice("best_of", "Games", listOf(1, 3, 5, 7), bestOf("game")),
                TournamentRule.Choice("points_to", "Points per game", listOf(11, 21)) { "To $it" },
            ),
            presets = listOf(
                TournamentPreset("best5", "Best of 5", "Games to 11", null, mapOf("best_of" to 5, "points_to" to 11)),
                TournamentPreset("best7", "Best of 7", "Games to 11", null, mapOf("best_of" to 7, "points_to" to 11)),
                TournamentPreset("best3", "Best of 3", "Games to 11", null, mapOf("best_of" to 3, "points_to" to 11)),
            ),
            customDefaults = mapOf("best_of" to 5, "points_to" to 11),
            customPlayers = 1,
            events = racquetEvents,
        )

        private val all = listOf(Football, Badminton, Volleyball, Basketball, Kabaddi, Tennis, TableTennis)

        /** Null for cricket, which has its own format model. */
        fun forKey(sport: String): TournamentSportSpec? = all.firstOrNull { it.key == sport.lowercase() }
    }
}

/** The numbers of a non-cricket format in the sport's own words. Mirrors Tournament::rulesSummary. */
fun tournamentRulesSummary(sport: String, r: Map<String, Any>): String {
    fun int(key: String, fallback: Int): Int = (r[key] as? Int) ?: fallback
    return when (sport) {
        "football", "kabaddi" -> if (int("halves", 2) == 1) "${int("half_minutes", 0)} min, one half"
            else "${int("halves", 2)} × ${int("half_minutes", 0)} min"
        "basketball" -> if (int("periods", 4) == 1) "${int("period_minutes", 0)} min, one period"
            else "${int("periods", 4)} × ${int("period_minutes", 0)} min"
        "badminton", "table_tennis" -> if (int("best_of", 3) == 1) "one game to ${int("points_to", 21)}"
            else "best of ${int("best_of", 3)} to ${int("points_to", 21)}"
        "volleyball" -> if (int("best_of", 3) == 1) "one set to ${int("points_to", 25)}"
            else "best of ${int("best_of", 3)} to ${int("points_to", 25)}"
        "tennis" -> {
            val bestOf = int("best_of", 3)
            val games = int("games_to", 6)
            buildString {
                append(if (bestOf == 1) "one set to $games" else "best of $bestOf sets")
                if (bestOf > 1 && games != 6) append(" to $games games")
                if (bestOf > 1 && r["final_set"] == "super_tiebreak") append(", super tiebreak")
            }
        }
        else -> ""
    }
}
