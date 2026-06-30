package com.example.thanna

import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.safeDrawingPadding
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.navigation3.runtime.entryProvider
import androidx.navigation3.runtime.rememberNavBackStack
import androidx.navigation3.ui.NavDisplay
import com.example.thanna.ui.main.EventDetailScreen
import com.example.thanna.ui.main.MainScreen
import com.example.thanna.ui.matches.MatchDetailsScreen
import com.example.thanna.ui.matches.ScoringScreen

@Composable
fun MainNavigation() {
  val backStack = rememberNavBackStack(Main)

  NavDisplay(
    backStack = backStack,
    onBack = { backStack.removeLastOrNull() },
    entryProvider =
      entryProvider {
        entry<Main> {
          MainScreen(onItemClick = { navKey -> backStack.add(navKey) }, modifier = Modifier.safeDrawingPadding().padding(16.dp))
        }
        entry<EventDetail> { event ->
          EventDetailScreen(
            event = event,
            onBack = { backStack.removeLastOrNull() }
          )
        }
        entry<MatchDetails> { match ->
          MatchDetailsScreen(
            matchId = match.id,
            joinCode = match.code,
            onOpenScorer = { backStack.add(Scoring(match.id, match.code)) },
            onBack = { backStack.removeLastOrNull() }
          )
        }
        entry<Scoring> { s ->
          ScoringScreen(matchId = s.id, code = s.code, onBack = { backStack.removeLastOrNull() })
        }
        entry<VenueDetail> { _ ->
          // Venue detail screen placeholder — TODO: implement VenueDetailScreen
        }
      },
  )
}
