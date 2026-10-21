package com.haraan.app.ui.main

import android.content.Intent
import android.net.Uri
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.data.BookingRepository
import com.haraan.app.ui.Feel
import com.haraan.app.ui.components.QrImage
import com.haraan.app.ui.pressable
import kotlinx.coroutines.delay

/**
 * Pay a held booking by UPI from any app — the way UPI always shows, even on a phone where
 * Razorpay's checkout lists no UPI app.
 *
 * A Razorpay UPI QR for exactly the order's amount: scan it from another phone, or hand it
 * to a UPI app on this one. The page polls the server, which confirms the booking the
 * moment Razorpay reports the money captured. The countdown is the slot hold — after it
 * the slot may be gone, so the page says so instead of taking money for nothing.
 */
@Composable
internal fun UpiQrPayPage(
  qr: BookingRepository.UpiQr,
  venueName: String,
  /** Polls the server; returns a confirmed booking, or null while still waiting. */
  checkPaid: suspend () -> com.haraan.app.data.BookingResult.Success?,
  onPaid: (com.haraan.app.data.BookingResult.Success) -> Unit,
  onUseRazorpay: () -> Unit,
  onBack: () -> Unit,
) {
  val ctx = LocalContext.current
  var secondsLeft by remember(qr.qrId) { mutableIntStateOf(qr.expiresIn) }

  // UPI apps on this phone that can take the link — none means the QR is for another phone.
  val upiApps = remember(qr.upi) {
    qr.upi?.let { link ->
      runCatching {
        ctx.packageManager.queryIntentActivities(Intent(Intent.ACTION_VIEW, Uri.parse(link)), 0).size
      }.getOrDefault(0)
    } ?: 0
  }

  LaunchedEffect(qr.qrId) {
    while (secondsLeft > 0) {
      delay(1_000)
      secondsLeft -= 1
    }
  }
  // Ask every 3s while the hold is live; one last look after it lapses.
  LaunchedEffect(qr.qrId) {
    while (true) {
      checkPaid()?.let { onPaid(it); return@LaunchedEffect }
      if (secondsLeft <= 0) return@LaunchedEffect
      delay(3_000)
    }
  }

  val expired = secondsLeft <= 0
  Column(Modifier.fillMaxSize().background(BookInk.Page)) {
    Row(
      modifier = Modifier
        .fillMaxWidth()
        .background(Color.White)
        .statusBarsPadding()
        .padding(horizontal = 12.dp, vertical = 10.dp),
      verticalAlignment = Alignment.CenterVertically,
    ) {
      Box(
        modifier = Modifier
          .pressable(haptic = Feel.SELECT, onClick = onBack)
          .size(38.dp)
          .clip(CircleShape)
          .background(BookInk.Chip),
        contentAlignment = Alignment.Center,
      ) {
        Icon(Icons.Default.ArrowBack, "Back", tint = BookInk.Ink, modifier = Modifier.size(20.dp))
      }
      Spacer(Modifier.width(10.dp))
      Text("Pay by UPI", color = BookInk.Ink, fontWeight = FontWeight.Bold, fontSize = 18.sp, letterSpacing = (-0.36).sp)
    }

    Column(
      modifier = Modifier
        .weight(1f)
        .verticalScroll(rememberScrollState())
        .padding(12.dp),
      verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
      ReviewCard {
        Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally) {
          Text(venueName, color = BookInk.Muted, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, textAlign = TextAlign.Center)
          Spacer(Modifier.height(4.dp))
          Text(
            "₹" + if (qr.amount % 1.0 == 0.0) inr(qr.amount.toInt()) else "%,.2f".format(qr.amount),
            color = BookInk.Ink, fontWeight = FontWeight.ExtraBold, fontSize = 30.sp, letterSpacing = (-0.6).sp,
          )
          Spacer(Modifier.height(16.dp))
          Box(
            Modifier
              .shadow(6.dp, RoundedCornerShape(18.dp), ambientColor = Color(0x220F172A), spotColor = Color(0x220F172A))
              .clip(RoundedCornerShape(18.dp))
              .background(Color.White)
              .border(1.dp, BookInk.Line, RoundedCornerShape(18.dp))
              .padding(14.dp)
              .alpha(if (expired) 0.25f else 1f),
            contentAlignment = Alignment.Center,
          ) {
            if (qr.upi != null) {
              QrImage(content = qr.upi, modifier = Modifier.size(220.dp))
            } else {
              AsyncImage(model = qr.imageUrl, contentDescription = "UPI QR code", contentScale = ContentScale.Fit, modifier = Modifier.size(220.dp))
            }
          }
          Spacer(Modifier.height(14.dp))
          Text(
            if (expired) "This QR has closed" else "Scan with any UPI app",
            color = BookInk.Ink, fontWeight = FontWeight.ExtraBold, fontSize = 15.sp,
          )
          Text(
            "Google Pay, PhonePe, Paytm, BHIM or your bank’s app",
            color = BookInk.Muted, fontSize = 12.5.sp, textAlign = TextAlign.Center,
            modifier = Modifier.padding(top = 2.dp),
          )
          Spacer(Modifier.height(14.dp))
          if (!expired) {
            WaitingLine("Waiting for payment · slot held for ${secondsLeft / 60}:${"%02d".format(secondsLeft % 60)}")
          } else {
            Text(
              "Your slot hold ran out. If you already paid, we’ll confirm it automatically — otherwise go back and pick the slot again.",
              color = BookInk.Amber, fontSize = 12.5.sp, lineHeight = 17.sp, textAlign = TextAlign.Center,
            )
          }
        }
      }

      if (!expired && upiApps == 0) {
        Text(
          "No UPI app is set up on this phone. Scan this code from another phone, or pay with a card or netbanking below.",
          color = BookInk.Muted, fontSize = 12.5.sp, lineHeight = 17.sp, textAlign = TextAlign.Center,
          modifier = Modifier.fillMaxWidth().padding(horizontal = 8.dp),
        )
      }
    }

    Column(
      modifier = Modifier
        .fillMaxWidth()
        .shadow(elevation = 14.dp, clip = false)
        .background(Color.White)
        .navigationBarsPadding()
        .padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
      if (!expired && upiApps > 0 && qr.upi != null) {
        Box(
          modifier = Modifier
            .pressable(haptic = Feel.COMMIT) {
              val link = Intent(Intent.ACTION_VIEW, Uri.parse(qr.upi))
              runCatching { ctx.startActivity(Intent.createChooser(link, "Pay with")) }
            }
            .fillMaxWidth()
            .height(52.dp)
            .shadow(10.dp, RoundedCornerShape(14.dp), ambientColor = BookInk.Blue, spotColor = BookInk.Blue.copy(alpha = 0.5f))
            .clip(RoundedCornerShape(14.dp))
            .background(BookInk.Blue),
          contentAlignment = Alignment.Center,
        ) {
          Text("Pay with a UPI app", color = Color.White, fontWeight = FontWeight.ExtraBold, fontSize = 16.sp)
        }
        Spacer(Modifier.height(10.dp))
      }
      Box(
        modifier = Modifier
          .pressable(haptic = Feel.SELECT, onClick = onUseRazorpay)
          .fillMaxWidth()
          .height(46.dp)
          .clip(RoundedCornerShape(14.dp))
          .background(Color.White)
          .border(1.dp, BookInk.Line, RoundedCornerShape(14.dp)),
        contentAlignment = Alignment.Center,
      ) {
        Text("Pay with card, netbanking or wallet", color = BookInk.BlueDeep, fontWeight = FontWeight.Bold, fontSize = 14.sp)
      }
    }
  }
}

@Composable
private fun WaitingLine(text: String) {
  val pulse = rememberInfiniteTransition(label = "waitPulse")
  val a by pulse.animateFloat(0.3f, 1f, infiniteRepeatable(tween(800), RepeatMode.Reverse), label = "waitDot")
  Row(verticalAlignment = Alignment.CenterVertically) {
    Box(Modifier.size(8.dp).alpha(a).clip(CircleShape).background(BookInk.Green))
    Spacer(Modifier.width(8.dp))
    Text(text, color = BookInk.Slate, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
  }
}
