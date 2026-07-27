package com.haraan.app

import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.safeDrawingPadding
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.navigation3.runtime.entryProvider
import androidx.navigation3.runtime.rememberNavBackStack
import androidx.navigation3.ui.NavDisplay
import com.haraan.app.data.TokenStore
import com.haraan.app.ui.main.EventDetailScreen
import com.haraan.app.ui.main.MainScreen
import com.haraan.app.ui.main.OrderSummaryScreen
import com.haraan.app.ui.main.PriceChartScreen
import com.haraan.app.ui.main.SupportChatScreen
import com.haraan.app.ui.main.VenueDetailScreen
import com.haraan.app.ui.matches.MatchDetailsScreen
import com.haraan.app.ui.matches.ScoringScreen

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
          val ctx = LocalContext.current
          EventDetailScreen(
            event = event,
            onBack = { backStack.removeLastOrNull() },
            // Checkout requires a real account. Guests (and "skipped" browsers) are
            // sent through the login wall first, then continue to the same order.
            onCheckout = { order ->
              if (TokenStore.isSignedIn(TokenStore.getToken(ctx))) {
                backStack.add(order)
              } else {
                backStack.add(LoginGate(order))
              }
            }
          )
        }
        entry<LoginGate> { gate ->
          val ctx = LocalContext.current
          com.haraan.app.ui.LoginRoute(
            // Cancel / "Skip" → abandon checkout, back to the event (no order).
            onSkipClick = { backStack.removeLastOrNull() },
            // Signed in → persist the session, then drop the login screen and
            // continue to the pending order (OrderSummary reads the saved token).
            onLoginSuccess = { token ->
              TokenStore.saveToken(ctx, token)
              com.haraan.app.push.PushRegistrar.syncToken(ctx)
              backStack.removeLastOrNull()
              backStack.add(gate.pendingOrder)
            }
          )
        }
        entry<OrderSummary> { order ->
          OrderSummaryScreen(
            order = order,
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
        entry<SupportChat> {
          val ctx = LocalContext.current
          SupportChatScreen(
            token = TokenStore.getToken(ctx) ?: "",
            onClose = { backStack.removeLastOrNull() }
          )
        }
        entry<VenueDetail> { venue ->
          VenueDetailScreen(
            venue = venue,
            onBack = { backStack.removeLastOrNull() },
            onOpenPriceChart = { backStack.add(PriceChart(venue.id)) }
          )
        }
        entry<PriceChart> { pc ->
          PriceChartScreen(venueId = pc.venueId, onBack = { backStack.removeLastOrNull() })
        }
      },
  )
}
