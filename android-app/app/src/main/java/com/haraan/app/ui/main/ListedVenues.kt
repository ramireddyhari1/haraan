package com.haraan.app.ui.main

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import coil.compose.AsyncImagePainter
import com.haraan.app.data.ApiConfig
import com.haraan.app.data.AskToBookResult
import com.haraan.app.data.ListedVenueDetail
import com.haraan.app.data.ListedVenueItem
import com.haraan.app.data.ListedVenueRepository
import com.haraan.app.data.TokenStore
import com.haraan.app.ui.openExternalUrl
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.theme.premiumCardShadow
import kotlinx.coroutines.launch

/*
 * Listed venues on Pulse: places Haraan knows about whose owners haven't joined, so nothing
 * here can be booked. They sit in the normal Pulse venue list on the normal cards, marked "Not
 * bookable online" with View instead of Book, and open a sheet with what a player needs to go there — photos, hours, address,
 * directions, a call button — plus "Ask to book", which tells the team the venue is wanted.
 */

private val NoBookingInk = Color(0xFF92400E)
private val NoBookingTint = Color(0xFFFEF6E7)
private val OpenInk = Color(0xFF15803D)
private val ClosedInk = Color(0xFFB45309)

/** A court drawn in thin lines — what a listed venue shows when Google has no photo. */
@Composable
private fun DrawnCourt(modifier: Modifier = Modifier) {
  Box(modifier.background(Color(0xFFEEF3EF))) {
    Canvas(Modifier.fillMaxSize().padding(14.dp)) {
      val line = Color(0xFF94A3B8)
      val stroke = Stroke(width = 1.4.dp.toPx())
      drawRoundRect(line, size = size, cornerRadius = CornerRadius(4.dp.toPx()), style = stroke)
      drawLine(line, Offset(size.width / 2, 0f), Offset(size.width / 2, size.height), strokeWidth = 1.4.dp.toPx())
      drawCircle(line, radius = size.minDimension * 0.16f, center = Offset(size.width / 2, size.height / 2), style = stroke)
    }
  }
}

/** The venue's Google photo, falling back to [DrawnCourt] while loading or when there is none. */
@Composable
private fun ListedPhoto(url: String?, description: String, modifier: Modifier = Modifier) {
  var failed by remember(url) { mutableStateOf(url.isNullOrBlank()) }
  var loaded by remember(url) { mutableStateOf(false) }
  Box(modifier) {
    if (!loaded || failed) DrawnCourt(Modifier.fillMaxSize())
    if (!failed) {
      AsyncImage(
        model = url,
        contentDescription = description,
        contentScale = ContentScale.Crop,
        modifier = Modifier.fillMaxSize(),
        onState = { s ->
          when (s) {
            is AsyncImagePainter.State.Success -> loaded = true
            is AsyncImagePainter.State.Error -> failed = true
            else -> Unit
          }
        },
      )
    }
  }
}

/**
 * A listed venue opened from Pulse. Loads its detail; everything shown is a fact we hold
 * (Google's photos with their photographers, hours, address, phone). Booking is off — the one
 * action is "Ask to book", which goes to the team and, through them, the owner.
 */
@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
internal fun ListedVenueSheet(venue: ListedVenueItem, onDismiss: () -> Unit) {
  val context = LocalContext.current
  val repo = remember { ListedVenueRepository() }
  val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
  var detail by remember(venue.id) { mutableStateOf<ListedVenueDetail?>(null) }
  var loading by remember(venue.id) { mutableStateOf(true) }

  LaunchedEffect(venue.id) {
    detail = repo.getDetail(venue.id)
    loading = false
  }

  ModalBottomSheet(onDismissRequest = onDismiss, sheetState = sheetState, containerColor = Color.White) {
    Column(
      Modifier
        .fillMaxWidth()
        .verticalScroll(rememberScrollState())
        .navigationBarsPadding()
        .padding(bottom = 20.dp)
    ) {
      val d = detail
      val photos = d?.photos.orEmpty()
      if (photos.isNotEmpty()) {
        LazyRow(
          contentPadding = PaddingValues(horizontal = 16.dp),
          horizontalArrangement = Arrangement.spacedBy(10.dp),
        ) {
          items(photos) { p ->
            Box(Modifier.size(width = 260.dp, height = 170.dp).clip(RoundedCornerShape(14.dp))) {
              ListedPhoto(p.url, venue.name, Modifier.fillMaxSize())
              if (p.credit.isNotBlank()) {
                Text(
                  "Photo: ${p.credit}",
                  color = Color.White,
                  fontSize = 10.5.sp,
                  maxLines = 1,
                  overflow = TextOverflow.Ellipsis,
                  modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(8.dp)
                    .clip(RoundedCornerShape(6.dp))
                    .background(Color(0xB80F172A))
                    .padding(horizontal = 6.dp, vertical = 2.dp),
                )
              }
            }
          }
        }
        Text(
          "Photos from this venue’s Google Maps listing.",
          color = HaraanColors.TextMuted,
          fontSize = 11.5.sp,
          modifier = Modifier.padding(start = 16.dp, top = 6.dp),
        )
      } else if (!loading) {
        ListedPhoto(null, venue.name, Modifier.padding(horizontal = 16.dp).fillMaxWidth().height(150.dp).clip(RoundedCornerShape(14.dp)))
      }

      Column(Modifier.padding(horizontal = 16.dp, vertical = 14.dp)) {
        Text(venue.name, color = HaraanColors.TextPrimary, fontSize = 22.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.3).sp)
        val where = listOf(venue.area, venue.city).filter { it.isNotBlank() }.joinToString(", ")
        val sub = listOf(venue.sports.joinToString(", "), where).filter { it.isNotBlank() }.joinToString(" · ")
        if (sub.isNotBlank()) Text(sub, color = HaraanColors.TextSecondary, fontSize = 14.sp, modifier = Modifier.padding(top = 3.dp))
        Spacer(Modifier.height(10.dp))
        Text(
          "Not on Haraan yet — no online booking",
          color = NoBookingInk,
          fontSize = 13.sp,
          fontWeight = FontWeight.Bold,
          modifier = Modifier.clip(RoundedCornerShape(8.dp)).background(NoBookingTint).padding(horizontal = 10.dp, vertical = 6.dp),
        )
        venue.openState?.let {
          Text(it, color = if (venue.openNow) OpenInk else ClosedInk, fontWeight = FontWeight.Bold, fontSize = 14.sp, modifier = Modifier.padding(top = 10.dp))
        }

        if (loading) {
          Box(Modifier.fillMaxWidth().padding(24.dp), contentAlignment = Alignment.Center) {
            CircularProgressIndicator(color = HaraanColors.EventsBlue, strokeWidth = 2.dp, modifier = Modifier.size(24.dp))
          }
        }

        if (d != null) {
          Spacer(Modifier.height(14.dp))
          if (d.hours.isNotEmpty()) FactRow("Hours", d.hours.joinToString("\n"))
          if (d.address.isNotBlank()) FactRow("Address", d.address)
          FactRow("Booking", "Not available on Haraan. Call the venue to book, or ask us below and we’ll tell the owner you want to play here.")

          Row(Modifier.fillMaxWidth().padding(top = 14.dp), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            if (d.mapLink.isNotBlank()) {
              OutlinedButton(
                onClick = { openExternalUrl(context, d.mapLink) },
                modifier = Modifier.weight(1f).height(46.dp),
                shape = RoundedCornerShape(12.dp),
                border = BorderStroke(1.dp, HaraanColors.BorderLight),
              ) { Text("Directions", color = HaraanColors.EventsBlue, fontWeight = FontWeight.Bold) }
            }
            if (d.phone.isNotBlank()) {
              OutlinedButton(
                onClick = {
                  runCatching {
                    context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:" + d.phone.filter { it.isDigit() || it == '+' })))
                  }
                },
                modifier = Modifier.weight(1f).height(46.dp),
                shape = RoundedCornerShape(12.dp),
                border = BorderStroke(1.dp, HaraanColors.BorderLight),
              ) { Text("Call venue", color = HaraanColors.EventsBlue, fontWeight = FontWeight.Bold) }
            }
          }

          if (d.requestsOpen) {
            AskToBookForm(venue = venue, detail = d, onSend = { name, phone, sport, time ->
              repo.askToBook(venue.id, name, phone, sport, time, TokenStore.getSignedInToken(context))
            })
          }

          Text(
            "Haraan has not visited or verified this venue. Details come from public listings and may be out of date — call before you go.",
            color = HaraanColors.TextMuted,
            fontSize = 12.sp,
            lineHeight = 17.sp,
            modifier = Modifier.padding(top = 16.dp),
          )
          Text(
            "Is this your venue? Claim it to take bookings online",
            color = HaraanColors.EventsBlue,
            fontSize = 13.5.sp,
            fontWeight = FontWeight.Bold,
            modifier = Modifier
              .padding(top = 12.dp)
              .clickable { openExternalUrl(context, ApiConfig.PUBLIC_WEB_URL.trimEnd('/') + "/become-a-partner") },
          )
        } else if (!loading) {
          Text(
            "Couldn’t load this venue. Check your connection and try again.",
            color = HaraanColors.TextSecondary,
            fontSize = 14.sp,
            modifier = Modifier.padding(top = 16.dp),
          )
        }
      }
    }
  }
}

@Composable
private fun FactRow(label: String, value: String) {
  Row(Modifier.fillMaxWidth().padding(vertical = 8.dp)) {
    Text(label, color = HaraanColors.TextSecondary, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.width(84.dp))
    Text(value, color = HaraanColors.TextPrimary, fontSize = 14.sp, lineHeight = 20.sp, modifier = Modifier.weight(1f))
  }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun AskToBookForm(
  venue: ListedVenueItem,
  detail: ListedVenueDetail,
  onSend: suspend (name: String, phone: String, sport: String?, time: String?) -> AskToBookResult,
) {
  val scope = rememberCoroutineScope()
  var name by remember { mutableStateOf("") }
  var phone by remember { mutableStateOf("") }
  var sport by remember { mutableStateOf<String?>(null) }
  var time by remember { mutableStateOf<String?>(null) }
  var sending by remember { mutableStateOf(false) }
  var error by remember { mutableStateOf<String?>(null) }
  var sent by remember { mutableStateOf(false) }

  Column(
    Modifier
      .padding(top = 18.dp)
      .fillMaxWidth()
      .clip(RoundedCornerShape(16.dp))
      .border(1.dp, HaraanColors.BorderLight, RoundedCornerShape(16.dp))
      .padding(16.dp)
  ) {
    if (sent) {
      Text("Got it", color = HaraanColors.TextPrimary, fontSize = 18.sp, fontWeight = FontWeight.ExtraBold)
      Text(
        "We’ll message you when ${venue.name} opens for booking on Haraan.",
        color = HaraanColors.TextSecondary,
        fontSize = 14.sp,
        modifier = Modifier.padding(top = 4.dp),
      )
      return@Column
    }

    Text("Ask to book here", color = HaraanColors.TextPrimary, fontSize = 17.sp, fontWeight = FontWeight.ExtraBold)
    Text(
      "Leave your number. We’ll tell the owner, and message you when this venue opens for booking.",
      color = HaraanColors.TextSecondary,
      fontSize = 13.5.sp,
      modifier = Modifier.padding(top = 3.dp, bottom = 10.dp),
    )
    val fieldColors = OutlinedTextFieldDefaults.colors(
      focusedBorderColor = HaraanColors.EventsBlue,
      unfocusedBorderColor = Color(0xFFCBD5E1),
    )
    OutlinedTextField(
      value = name, onValueChange = { name = it.take(60) },
      label = { Text("Your name") }, singleLine = true, colors = fieldColors,
      modifier = Modifier.fillMaxWidth(),
    )
    Spacer(Modifier.height(8.dp))
    OutlinedTextField(
      value = phone, onValueChange = { phone = it.filter { c -> c.isDigit() || c == '+' || c == ' ' }.take(14) },
      label = { Text("Mobile number") }, singleLine = true, colors = fieldColors,
      keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
      modifier = Modifier.fillMaxWidth(),
    )
    if (venue.sports.size > 1) {
      ChoiceRow("Sport", venue.sports, sport) { sport = it }
    }
    if (detail.preferredTimes.isNotEmpty()) {
      ChoiceRow("When you play", detail.preferredTimes, time) { time = it }
    }
    error?.let { Text(it, color = HaraanColors.Danger, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.padding(top = 10.dp)) }
    Button(
      onClick = {
        val digits = phone.filter { it.isDigit() }.let { if (it.length == 12 && it.startsWith("91")) it.drop(2) else it }
        when {
          name.trim().length < 2 -> error = "Enter your name."
          !Regex("^[6-9]\\d{9}$").matches(digits) -> error = "Enter a 10-digit mobile number."
          else -> {
            error = null
            sending = true
            scope.launch {
              when (val r = onSend(name, digits, sport, time)) {
                AskToBookResult.Sent -> sent = true
                is AskToBookResult.Error -> error = r.message
              }
              sending = false
            }
          }
        }
      },
      enabled = !sending,
      shape = RoundedCornerShape(12.dp),
      colors = ButtonDefaults.buttonColors(containerColor = HaraanColors.EventsBlue),
      modifier = Modifier.fillMaxWidth().padding(top = 14.dp).height(48.dp),
    ) {
      Text(if (sending) "Sending…" else "Ask to book", fontWeight = FontWeight.Bold, fontSize = 15.sp)
    }
    Text(
      "Your number is used only to tell you about this venue.",
      color = HaraanColors.TextMuted,
      fontSize = 12.sp,
      modifier = Modifier.padding(top = 8.dp),
    )
  }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun ChoiceRow(label: String, options: List<String>, selected: String?, onPick: (String?) -> Unit) {
  Text(label, color = HaraanColors.TextSecondary, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 12.dp, bottom = 6.dp))
  FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
    options.forEach { o ->
      val on = o == selected
      Text(
        o,
        color = if (on) Color.White else HaraanColors.TextPrimary,
        fontSize = 13.sp,
        fontWeight = FontWeight.SemiBold,
        modifier = Modifier
          .clip(RoundedCornerShape(50))
          .background(if (on) HaraanColors.EventsBlue else HaraanColors.Field)
          .clickable { onPick(if (on) null else o) }
          .padding(horizontal = 12.dp, vertical = 7.dp),
      )
    }
  }
}
