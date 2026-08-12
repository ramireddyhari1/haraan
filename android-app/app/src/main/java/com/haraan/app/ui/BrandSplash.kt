package com.haraan.app.ui

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.CubicBezierEasing
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.BlendMode
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.graphics.CompositingStrategy
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.unit.dp
import androidx.compose.foundation.Image
import com.haraan.app.R
import kotlinx.coroutines.delay
import com.haraan.app.ui.theme.HaraanColors

/**
 * Stage 2 of the launch experience — the brand moment the system splash hands off to.
 *
 * This is a port of the website's splash loader (`.hloader` in site/layout.blade.php):
 * a grey "ghost" wordmark with a blue copy that fills in left→right, and a slim meter
 * that fills in lockstep. **The logo IS the progress bar** — that's the idea worth
 * keeping; it says "loading" without a spinner, which is what separates a funded app's
 * launch from a stock one. Keep the two surfaces in step when either changes.
 *
 * Seamlessness rules (both easy to break):
 *  - [SplashBase] must equal `@color/splash_background`, which the launch theme paints
 *    as the system splash window colour. A mismatch shows as a colour flash mid-launch.
 *  - The whole thing is time-boxed. It renders OVER the app, which is already composing
 *    behind it, so a slow network can never hold the user on this screen.
 */

/** The seam colour. Must stay equal to `@color/splash_background`. */
private val SplashBase = HaraanColors.Background
private val GhostGrey = Color(0xFFDBE3EF)

// Web parity: cubic-bezier(0.16, 1, 0.3, 1) for the entrance pop and
// cubic-bezier(0.62, 0.02, 0.34, 1) for the fill sweep.
private val PopEasing = CubicBezierEasing(0.16f, 1f, 0.3f, 1f)
private val SweepEasing = CubicBezierEasing(0.62f, 0.02f, 0.34f, 1f)

// haraan_logo_white.png is 1680x445; the mask is its alpha, so the asset's own
// colour is irrelevant — only its letterform coverage matters.
private const val WORDMARK_ASPECT = 1680f / 445f

@Composable
fun BrandSplash(onFinished: () -> Unit) {
  val pop = remember { Animatable(0f) }
  val sweep = remember { Animatable(0f) }
  val exit = remember { Animatable(1f) }

  LaunchedEffect(Unit) {
    pop.animateTo(1f, tween(durationMillis = 420, easing = PopEasing))
    sweep.animateTo(1f, tween(durationMillis = 880, easing = SweepEasing))
    delay(170) // a beat on the completed mark, so it doesn't snap away
    exit.animateTo(0f, tween(durationMillis = 380))
    onFinished()
  }

  Box(
    modifier = Modifier
      .fillMaxSize()
      .graphicsLayer { alpha = exit.value }
      .background(SplashBase),
    contentAlignment = Alignment.Center
  ) {
    // A soft light source behind the lockup so the base isn't one dead plane —
    // the same device as the "Haraan special" band, kept faint.
    Canvas(modifier = Modifier.fillMaxSize()) {
      drawRect(
        brush = Brush.radialGradient(
          colors = listOf(Color.White, Color.White.copy(alpha = 0.55f), Color.Transparent),
          center = Offset(size.width / 2f, size.height * 0.44f),
          radius = size.width * 0.78f
        )
      )
    }

    Column(
      horizontalAlignment = Alignment.CenterHorizontally,
      verticalArrangement = Arrangement.Center,
      modifier = Modifier.graphicsLayer {
        // Entrance: 0.92 → 1 with a fade, matching the web's `hl-pop`.
        alpha = pop.value
        val s = 0.92f + (0.08f * pop.value)
        scaleX = s
        scaleY = s
        translationY = -size.height * 0.02f
      }
    ) {
      val wordmark = painterResource(id = R.drawable.haraan_logo_white)

      Box(
        modifier = Modifier
          .width(228.dp)
          .aspectRatio(WORDMARK_ASPECT)
      ) {
        // Ghost: the full wordmark in a pale grey, so the shape is legible from
        // frame one and the blue reads as filling something that's already there.
        Image(
          painter = wordmark,
          contentDescription = null,
          contentScale = ContentScale.Fit,
          colorFilter = ColorFilter.tint(GhostGrey),
          modifier = Modifier.fillMaxSize()
        )
        // Fill: the same wordmark painted with the brand gradient and revealed
        // left→right. CompositingStrategy.Offscreen is REQUIRED — it gives the
        // layer its own buffer so SrcIn paints the gradient through the image's
        // alpha (the letterforms) instead of over the whole rectangle.
        Image(
          painter = wordmark,
          contentDescription = "Haraan",
          contentScale = ContentScale.Fit,
          modifier = Modifier
            .fillMaxSize()
            .graphicsLayer { compositingStrategy = CompositingStrategy.Offscreen }
            .drawWithContent {
              drawContent()
              drawRect(
                brush = Brush.horizontalGradient(
                  colorStops = arrayOf(
                    0f to Color(0xFF60A5FA),
                    0.46f to Color(0xFF3B82F6),
                    1f to Color(0xFF2563EB)
                  ),
                  startX = 0f,
                  endX = size.width
                ),
                blendMode = BlendMode.SrcIn
              )
              // Hide what hasn't filled yet by punching it back out, rather than
              // clipping the layout — clipping would rescale the ContentScale.Fit
              // image and make the letters crawl as the sweep advances.
              val revealed = size.width * sweep.value
              drawRect(
                color = Color.Transparent,
                topLeft = Offset(revealed, 0f),
                size = androidx.compose.ui.geometry.Size(size.width - revealed, size.height),
                blendMode = BlendMode.Clear
              )
            }
        )
      }

      Spacer(modifier = Modifier.height(22.dp))

      // Meter — a thin track whose blue segment fills in lockstep with the mark,
      // reinforcing the "loading" read without adding a spinner.
      Box(
        modifier = Modifier
          .width(150.dp)
          .height(3.dp)
          .clip(RoundedCornerShape(99.dp))
          .background(Color(0xFF2563EB).copy(alpha = 0.14f))
      ) {
        Box(
          modifier = Modifier
            // fillMaxHeight BEFORE the fractional width — `fillMaxSize()` here would
            // re-set the width to full and the meter would read as complete from
            // frame one, silently defeating the whole progress effect.
            .fillMaxHeight()
            .fillMaxWidth(sweep.value)
            .clip(RoundedCornerShape(99.dp))
            .background(
              Brush.horizontalGradient(listOf(Color(0xFF3B82F6), Color(0xFF2563EB)))
            )
        )
      }
    }
  }
}
