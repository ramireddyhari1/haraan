package com.example.thanna.ui.components

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.getValue
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.repeatOnLifecycle
import kotlinx.coroutines.delay

/**
 * Keeps a screen's data fresh without the user pulling to refresh, switching tabs,
 * or restarting the app.
 *
 * Backed by [repeatOnLifecycle] at [Lifecycle.State.RESUMED], which does exactly the
 * two things that fix "everything is stale until I do something":
 *
 *  - **On becoming visible** — the first time the screen shows, every time the user
 *    navigates back to it, and every time the app returns from the background — the
 *    block is (re)started, so [onRefresh] runs once immediately when [refreshOnResume]
 *    is true.
 *  - **While visible** — if [intervalMs] > 0, [onRefresh] runs again every [intervalMs].
 *
 * When the screen is hidden or the app is backgrounded the coroutine is cancelled, so
 * no polling happens off-screen — it costs nothing and drains no battery in the
 * background, then resumes the moment the screen is shown again.
 *
 * This is the client-side counterpart to [com.example.thanna.data.RealtimeBus]: the
 * realtime socket pushes admin/content edits instantly, while this covers everything
 * the backend doesn't broadcast (live scores ticking, new user matches, bookings) and
 * the "I came back to the app" case that a socket alone can't catch.
 *
 * Usage — drop it alongside the screen's existing initial-load effect:
 * ```
 * AutoRefresh(intervalMs = 15_000) { viewModel.refresh() }
 * ```
 * Pass [keys] that, when changed, should tear down and restart the loop (e.g. the
 * auth token or a match id the refresh depends on).
 *
 * @param intervalMs poll cadence while the screen is visible; 0 disables polling and
 *   the block only runs on resume. Keep it honest to the data: ~10–20s for lists,
 *   3–5s for a live scoreboard, 0 for content that only changes via a realtime push.
 * @param refreshOnResume run [onRefresh] immediately each time the screen becomes
 *   visible. Usually true; set false if the caller already loads on first composition
 *   and only wants the periodic tick.
 */
@Composable
fun AutoRefresh(
    intervalMs: Long = 15_000L,
    refreshOnResume: Boolean = true,
    vararg keys: Any?,
    onRefresh: suspend () -> Unit,
) {
    val lifecycleOwner = LocalLifecycleOwner.current
    // Always invoke the latest lambda (it captures current state/token) without
    // restarting the lifecycle loop when only the lambda instance changes.
    val currentOnRefresh by rememberUpdatedState(onRefresh)

    LaunchedEffect(lifecycleOwner, intervalMs, refreshOnResume, *keys) {
        lifecycleOwner.lifecycle.repeatOnLifecycle(Lifecycle.State.RESUMED) {
            if (refreshOnResume) {
                runCatching { currentOnRefresh() }
            }
            if (intervalMs > 0L) {
                while (true) {
                    delay(intervalMs)
                    runCatching { currentOnRefresh() }
                }
            }
        }
    }
}
