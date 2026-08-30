package com.haraan.app.ui.matches.tabs

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.animation.animateColorAsState
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.layout.layout
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.data.PlayerForm
import com.haraan.app.data.PlayerRepository
import com.haraan.app.data.SquadMember
import com.haraan.app.ui.matches.CrexColors
import com.haraan.app.ui.pressable
import kotlin.math.roundToInt

// ─────────────────────────────────────────────────────────────────────────────
//  FORM
//
//  Pick a side, pick a player, and see what they have actually been doing: the
//  last five innings as a row of scores, the innings behind them, and the
//  totals those add up to.
//
//  ONE section with a discipline switch, not two.
//
//  It was two — "Analyse batters" and "Analyse bowlers" — identical component,
//  identical toggle, identical avatar row, stacked one above the other. That is
//  the same page twice, and a reader scrolling past it learns that this screen is
//  a loop rather than a composition. Batting and bowling are two views of the
//  same player, so they are two states of one control.
//
//  Every figure is replayed from the ball log of the matches named beside it, so
//  a reader can check any line against the scorecard it came from. Two panels
//  the reference cards carry are deliberately absent, and the section says why
//  rather than showing an empty table:
//
//   · spin versus pace — no historical delivery records the bowler's type;
//   · "top six" dismissals — batting position is not recorded anywhere.
// ─────────────────────────────────────────────────────────────────────────────

private val Hairline = Color(0xFFEDF1F6)
private val Well = Color(0xFFF4F7FB)

enum class AnalyseMode { BATTING, BOWLING }

@Composable
fun AnalyseSection(
    team1Name: String,
    team2Name: String,
    team1Squad: List<SquadMember>,
    team2Squad: List<SquadMember>,
) {
    var mode by remember { mutableStateOf(AnalyseMode.BATTING) }
    // Only players with a real account can have a form line: a guest has no id, so
    // there is nothing to look up and nothing to show.
    val squads = listOf(
        team1Name to team1Squad.filter { it.id.isNotBlank() && !it.id.equals("null", true) },
        team2Name to team2Squad.filter { it.id.isNotBlank() && !it.id.equals("null", true) },
    )
    if (squads.all { it.second.isEmpty() }) return

    var teamIndex by remember { mutableStateOf(if (squads[0].second.isEmpty()) 1 else 0) }
    val squad = squads[teamIndex].second
    var selected by remember(teamIndex) { mutableStateOf(squad.firstOrNull()) }
    var form by remember { mutableStateOf<PlayerForm?>(null) }
    var loading by remember { mutableStateOf(false) }

    LaunchedEffect(selected?.id) {
        val id = selected?.id ?: return@LaunchedEffect
        loading = true
        form = PlayerRepository().fetchForm(id)
        loading = false
    }

    Column(Modifier.fillMaxWidth()) {
        Box(Modifier.fillMaxWidth().height(1.dp).background(CrexColors.Border.copy(alpha = 0.55f)))
        Spacer(Modifier.height(18.dp))

        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                "FORM",
                color = CrexColors.TextMuted,
                fontSize = 9.5.sp,
                fontWeight = FontWeight.ExtraBold,
                letterSpacing = 1.4.sp,
                modifier = Modifier.weight(1f),
            )
            DisciplineSwitch(mode) { mode = it }
        }
        Spacer(Modifier.height(14.dp))

        TeamTabs(squads.map { it.first }, teamIndex) { teamIndex = it }
        Spacer(Modifier.height(16.dp))

        AvatarRow(squad, selected) { selected = it }

        selected?.let { player ->
            Spacer(Modifier.height(16.dp))
            PlayerFormCard(mode, player, form, loading)
        }
    }
}

/** Batting or bowling — the same player, two ways of reading them. */
@Composable
private fun DisciplineSwitch(mode: AnalyseMode, onChange: (AnalyseMode) -> Unit) {
    Row(
        Modifier
            .clip(RoundedCornerShape(999.dp))
            .background(Well)
            .padding(3.dp),
    ) {
        AnalyseMode.entries.forEach { option ->
            val on = option == mode
            val bg by animateColorAsState(
                if (on) CrexColors.Surface else Color.Transparent,
                tween(200),
                label = "discBg",
            )
            val fg by animateColorAsState(
                if (on) CrexColors.TextPrimary else CrexColors.TextMuted,
                tween(200),
                label = "discFg",
            )
            Box(
                Modifier
                    .pressable { onChange(option) }
                    .clip(RoundedCornerShape(999.dp))
                    .background(bg)
                    .padding(horizontal = 13.dp, vertical = 6.dp),
            ) {
                Text(
                    if (option == AnalyseMode.BATTING) "Batting" else "Bowling",
                    color = fg,
                    fontSize = 11.5.sp,
                    fontWeight = FontWeight.Bold,
                )
            }
        }
    }
}

/**
 * Which side. An underlined tab rather than a second pill: the discipline switch beside
 * it is already a pill, and two pill controls stacked is the look of a settings screen.
 * The underline is the same idiom the profile's own tabs use.
 */
@Composable
private fun TeamTabs(names: List<String>, selected: Int, onSelect: (Int) -> Unit) {
    Row(Modifier.fillMaxWidth()) {
        names.forEachIndexed { i, name ->
            val on = i == selected
            val fg by animateColorAsState(
                if (on) CrexColors.TextPrimary else CrexColors.TextMuted,
                tween(200),
                label = "teamFg",
            )
            val rule by animateColorAsState(
                if (on) CrexColors.AccentBlue else Color.Transparent,
                tween(200),
                label = "teamRule",
            )
            Column(
                Modifier.weight(1f).pressable { onSelect(i) },
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Text(
                    name.ifBlank { "Team ${i + 1}" },
                    color = fg,
                    fontSize = 13.5.sp,
                    fontWeight = if (on) FontWeight.Bold else FontWeight.Medium,
                    maxLines = 1,
                )
                Spacer(Modifier.height(9.dp))
                Box(Modifier.fillMaxWidth().height(2.dp).background(rule))
            }
        }
    }
}

/** The squad, as faces. The selected one is ringed and points at the card below it. */
@Composable
private fun AvatarRow(squad: List<SquadMember>, selected: SquadMember?, onSelect: (SquadMember) -> Unit) {
    val scroll = rememberScrollState()
    Row(
        Modifier.fillMaxWidth().horizontalScroll(scroll),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        squad.forEach { member ->
            val on = member.id == selected?.id
            val ring by animateFloatAsState(if (on) 1f else 0f, tween(220), label = "ring")
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                modifier = Modifier.pressable { onSelect(member) },
            ) {
                Box(
                    Modifier
                        .size(60.dp)
                        .clip(CircleShape)
                        .background(
                            Brush.linearGradient(
                                listOf(
                                    CrexColors.AccentBlue.copy(alpha = ring),
                                    CrexColors.AccentBlue.copy(alpha = ring * 0.4f),
                                ),
                            ),
                        )
                        .padding(2.5.dp * ring),
                    contentAlignment = Alignment.Center,
                ) {
                    Box(
                        Modifier
                            .fillMaxSize()
                            .clip(CircleShape)
                            .background(Well),
                        contentAlignment = Alignment.Center,
                    ) {
                        if (member.avatar.isNotBlank()) {
                            AsyncImage(
                                model = member.avatar,
                                contentDescription = member.name,
                                contentScale = ContentScale.Crop,
                                modifier = Modifier.fillMaxSize().clip(CircleShape),
                            )
                        } else {
                            // A monogram, because most grassroots squad entries have no
                            // photo and a grey disc is the cheapest thing on a screen.
                            Text(
                                member.name.trim().take(1).uppercase(),
                                color = CrexColors.TextSecondary,
                                fontSize = 20.sp,
                                fontWeight = FontWeight.Bold,
                            )
                        }
                    }
                }
                Spacer(Modifier.height(5.dp))
                Text(
                    member.name.trim().split(" ").first(),
                    color = if (on) CrexColors.TextPrimary else CrexColors.TextMuted,
                    fontSize = 10.5.sp,
                    fontWeight = if (on) FontWeight.Bold else FontWeight.Medium,
                    maxLines = 1,
                    modifier = Modifier.width(62.dp),
                    textAlign = TextAlign.Center,
                )
            }
        }
    }
}

@Composable
private fun PlayerFormCard(
    mode: AnalyseMode,
    player: SquadMember,
    form: PlayerForm?,
    loading: Boolean,
) {
    // No outer card. The section it sits in already has a rule and a heading; wrapping
    // this in a bordered box as well was a card inside a card inside a page.
    Column(Modifier.fillMaxWidth()) {
        Text(
            player.name,
            color = CrexColors.TextPrimary,
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = (-0.5).sp,
        )
        val style = if (mode == AnalyseMode.BATTING) form?.battingStyle else form?.bowlingStyle
        if (!style.isNullOrBlank()) {
            Spacer(Modifier.height(3.dp))
            Text(style, color = CrexColors.TextSecondary, fontSize = 12.5.sp)
        }

        Spacer(Modifier.height(16.dp))

        val block = if (mode == AnalyseMode.BATTING) form?.batting else form?.bowling
        when {
            loading && block == null -> Row(verticalAlignment = Alignment.CenterVertically) {
                CircularProgressIndicator(
                    color = CrexColors.AccentBlue,
                    strokeWidth = 2.dp,
                    modifier = Modifier.size(14.dp),
                )
                Spacer(Modifier.width(9.dp))
                Text("Reading the ball log…", color = CrexColors.TextMuted, fontSize = 12.5.sp)
            }

            block == null || block.innings.isEmpty() -> Text(
                if (mode == AnalyseMode.BATTING)
                    "No scored innings for ${player.name.trim().split(" ").first()} yet."
                else
                    "No scored spells for ${player.name.trim().split(" ").first()} yet.",
                color = CrexColors.TextMuted,
                fontSize = 12.5.sp,
                lineHeight = 18.sp,
            )

            else -> {
                // The lead. A player opening this wants to know how they are going, not
                // to read a table and work it out — so the headline figure and the read
                // on it come first, and the evidence follows underneath.
                FormLead(block, mode)
                Spacer(Modifier.height(18.dp))
                Text(
                    "LAST ${block.innings.size}",
                    color = CrexColors.TextMuted,
                    fontSize = 9.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = 1.2.sp,
                )
                Spacer(Modifier.height(10.dp))
                FormChips(block.innings, mode)
                Spacer(Modifier.height(16.dp))
                InningsTable(block.innings, mode)
                if (block.efficiency.isNotEmpty()) {
                    Spacer(Modifier.height(16.dp))
                    Text(
                        if (mode == AnalyseMode.BATTING) "Scoring shape" else "Bowling efficiency",
                        color = CrexColors.TextPrimary,
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold,
                    )
                    Spacer(Modifier.height(10.dp))
                    block.efficiency.forEachIndexed { i, metric ->
                        if (i > 0) Spacer(Modifier.height(9.dp))
                        EfficiencyRow(metric.label, metric.value)
                    }
                }
            }
        }
    }
}

/**
 * What these five add up to, said once and large, with the sentence that interprets it.
 *
 * The arithmetic is done on figures already on this screen — a player's own average
 * across the five they can see — so nothing here is a claim the reader cannot check by
 * looking down. There is no comparison to a previous window, because we hold only one:
 * inventing "up 8% on last month" would be the kind of number that makes every real one
 * beside it suspect.
 */
@Composable
private fun FormLead(block: com.haraan.app.data.FormBlock, mode: AnalyseMode) {
    val headline = block.totals.firstOrNull { it.label == (if (mode == AnalyseMode.BATTING) "R" else "W") }
    val supporting = block.totals.filter { it != headline }

    Row(verticalAlignment = Alignment.Bottom) {
        Text(
            headline?.value ?: "-",
            color = CrexColors.TextPrimary,
            fontSize = 44.sp,
            fontFamily = com.haraan.app.theme.ArchivoDisplay,
            letterSpacing = (-1.6).sp,
        )
        Spacer(Modifier.width(9.dp))
        Text(
            if (mode == AnalyseMode.BATTING) "runs" else "wickets",
            color = CrexColors.TextSecondary,
            fontSize = 14.sp,
            fontWeight = FontWeight.Medium,
            modifier = Modifier.padding(bottom = 7.dp),
        )
        Spacer(Modifier.weight(1f))
        Column(horizontalAlignment = Alignment.End) {
            supporting.take(3).forEach { total ->
                Row {
                    Text("${total.label} ", color = CrexColors.TextMuted, fontSize = 11.5.sp)
                    Text(
                        total.value,
                        color = CrexColors.TextPrimary,
                        fontSize = 11.5.sp,
                        fontWeight = FontWeight.Bold,
                        style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                }
            }
        }
    }

    readOn(block, mode)?.let { line ->
        Spacer(Modifier.height(10.dp))
        Text(line, color = CrexColors.TextSecondary, fontSize = 13.sp, lineHeight = 19.sp)
    }
}

/**
 * One sentence about the most recent innings against the other four.
 *
 * Deliberately comparative rather than evaluative: it says where the last innings sits
 * among these five, which is a fact, and stops short of calling a player in or out of
 * form on a sample of five.
 */
private fun readOn(block: com.haraan.app.data.FormBlock, mode: AnalyseMode): String? {
    val values = block.innings.mapNotNull { it.leadValue }
    if (values.size < 3) return null
    val latest = values.first()
    val rest = values.drop(1)
    val mean = rest.average()
    val best = values.max()

    return when {
        mode == AnalyseMode.BATTING && latest == best && latest > 0 ->
            "Their best of these five came last time out."
        mode == AnalyseMode.BATTING && latest > mean ->
            "Last time out was above their average across these five."
        mode == AnalyseMode.BATTING ->
            "Last time out was below their average across these five."
        latest == best && latest > 0 -> "Their best return of these five came last time out."
        latest > mean -> "More wickets last time out than their average across these five."
        else -> "Fewer wickets last time out than their average across these five."
    }
}

/** The five most recent, as the row of boxes a cricketer reads first. */
@Composable
private fun FormChips(innings: List<com.haraan.app.data.FormInnings>, mode: AnalyseMode) {
    val scroll = rememberScrollState()
    val best = innings.mapNotNull { it.leadValue }.maxOrNull()
    Row(
        Modifier.fillMaxWidth().horizontalScroll(scroll),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        innings.forEachIndexed { i, line ->
            // The standout innings is marked. Without it these are five equal boxes and
            // the reader has to compare them by eye — which is the work the screen is
            // supposed to be doing for them.
            val isBest = best != null && best > 0 && line.leadValue == best
            Rise(index = i) {
                Column(
                    Modifier
                        .clip(RoundedCornerShape(12.dp))
                        .background(if (isBest) CrexColors.AccentBlue.copy(alpha = 0.08f) else Well)
                        .padding(horizontal = 14.dp, vertical = 10.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Text(
                        line.headline,
                        color = if (isBest) CrexColors.AccentBlue else CrexColors.TextPrimary,
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold,
                        style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                    Spacer(Modifier.height(2.dp))
                    Text(
                        if (i == 0) "latest" else line.support,
                        color = if (isBest) CrexColors.AccentBlue.copy(alpha = 0.7f) else CrexColors.TextMuted,
                        fontSize = 11.sp,
                        style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                }
            }
        }
    }
}

@Composable
private fun InningsTable(innings: List<com.haraan.app.data.FormInnings>, mode: AnalyseMode) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(CrexColors.Surface)
            .border(1.dp, Hairline, RoundedCornerShape(14.dp)),
    ) {
        Row(Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 10.dp)) {
            HeaderCell(if (mode == AnalyseMode.BATTING) "Score" else "O-M-R-W", 0.28f)
            HeaderCell("Innings", 0.52f)
            HeaderCell("Date", 0.20f, TextAlign.End)
        }
        Box(Modifier.fillMaxWidth().height(1.dp).background(Hairline))
        innings.forEachIndexed { i, line ->
            if (i > 0) Box(Modifier.fillMaxWidth().padding(horizontal = 14.dp).height(1.dp).background(Hairline))
            Row(
                Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 11.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(Modifier.weight(0.28f)) {
                    Text(
                        line.headline,
                        color = CrexColors.TextPrimary,
                        fontSize = 13.5.sp,
                        fontWeight = FontWeight.Bold,
                        style = TextStyle(fontFeatureSettings = "tnum"),
                    )
                    if (mode == AnalyseMode.BATTING && line.support.isNotBlank()) {
                        Text(
                            line.support,
                            color = CrexColors.TextMuted,
                            fontSize = 11.sp,
                            style = TextStyle(fontFeatureSettings = "tnum"),
                        )
                    }
                }
                Text(
                    line.match,
                    color = CrexColors.TextSecondary,
                    fontSize = 12.sp,
                    lineHeight = 16.sp,
                    modifier = Modifier.weight(0.52f).padding(end = 8.dp),
                    maxLines = 2,
                )
                Text(
                    line.date,
                    color = CrexColors.TextMuted,
                    fontSize = 11.5.sp,
                    textAlign = TextAlign.End,
                    modifier = Modifier.weight(0.20f),
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
            }
        }
    }
}

@Composable
private fun HeaderCell(label: String, weight: Float, align: TextAlign = TextAlign.Start) {
    Text(
        label,
        color = CrexColors.TextMuted,
        fontSize = 10.5.sp,
        fontWeight = FontWeight.SemiBold,
        letterSpacing = 0.5.sp,
        textAlign = align,
        modifier = Modifier.fillMaxWidth(weight),
    )
}

/** What the five add up to. */
@Composable
private fun TotalsStrip(totals: List<com.haraan.app.data.FormTotal>) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(CrexColors.AccentBlue.copy(alpha = 0.06f))
            .padding(horizontal = 14.dp, vertical = 11.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(
            "Total",
            color = CrexColors.TextMuted,
            fontSize = 11.sp,
            fontWeight = FontWeight.SemiBold,
        )
        totals.forEach { total ->
            Spacer(Modifier.weight(1f))
            Row(verticalAlignment = Alignment.Bottom) {
                Text("${total.label}: ", color = CrexColors.TextMuted, fontSize = 11.5.sp)
                Text(
                    total.value,
                    color = CrexColors.TextPrimary,
                    fontSize = 12.5.sp,
                    fontWeight = FontWeight.Bold,
                    style = TextStyle(fontFeatureSettings = "tnum"),
                )
            }
        }
    }
}

/** A percentage as a bar, because a column of them is a column of numbers. */
@Composable
private fun EfficiencyRow(label: String, value: String) {
    val pct = value.removeSuffix("%").toFloatOrNull()?.div(100f)?.coerceIn(0f, 1f) ?: 0f
    val grow by animateFloatAsState(pct, tween(700, easing = FastOutSlowInEasing), label = "eff")
    Column(Modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(label, color = CrexColors.TextSecondary, fontSize = 12.5.sp, modifier = Modifier.weight(1f))
            Text(
                value,
                color = CrexColors.TextPrimary,
                fontSize = 12.5.sp,
                fontWeight = FontWeight.Bold,
                style = TextStyle(fontFeatureSettings = "tnum"),
            )
        }
        Spacer(Modifier.height(6.dp))
        Box(
            Modifier
                .fillMaxWidth()
                .height(6.dp)
                .clip(RoundedCornerShape(3.dp))
                .background(Hairline),
        ) {
            Box(
                Modifier
                    .fillMaxWidth(grow)
                    .fillMaxHeight()
                    .clip(RoundedCornerShape(3.dp))
                    .background(
                        Brush.horizontalGradient(
                            listOf(CrexColors.AccentBlue.copy(alpha = 0.75f), CrexColors.AccentBlue),
                        ),
                    ),
            )
        }
    }
}

/** Chips arrive left to right, so the row reads as a sequence of innings. */
@Composable
private fun Rise(index: Int, content: @Composable () -> Unit) {
    val enter = remember(index) { Animatable(0f) }
    LaunchedEffect(index) {
        kotlinx.coroutines.delay(index * 55L)
        enter.animateTo(1f, tween(320, easing = FastOutSlowInEasing))
    }
    Box(
        Modifier
            .alpha(enter.value)
            .layout { measurable, constraints ->
                val placeable = measurable.measure(constraints)
                val lift = ((1f - enter.value) * 8.dp.toPx()).roundToInt()
                layout(placeable.width, placeable.height) { placeable.place(0, lift) }
            },
    ) {
        content()
    }
}
