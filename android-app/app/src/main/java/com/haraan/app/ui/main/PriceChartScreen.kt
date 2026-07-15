package com.haraan.app.ui.main

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
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
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.VenueDetailData
import com.haraan.app.data.VenueRepository
import com.haraan.app.ui.theme.HaraanColors

/**
 * Full-screen Price Chart page (Playo-style), opened from the venue detail "Available Sports" card.
 * Header shows "Price Chart" + a Book Now CTA; the body is the shared [PriceChartBody].
 */
@Composable
fun PriceChartScreen(venueId: String, onBack: () -> Unit) {
  var detail by remember { mutableStateOf<VenueDetailData?>(null) }
  var loading by remember { mutableStateOf(true) }
  var showBooking by remember { mutableStateOf(false) }

  LaunchedEffect(venueId) {
    detail = VenueRepository().getVenueDetail(venueId)
    loading = false
  }

  Surface(modifier = Modifier.fillMaxSize(), color = Color.White) {
    Column(
      modifier = Modifier
        .fillMaxSize()
        .statusBarsPadding()
        .padding(horizontal = 16.dp)
    ) {
      // Back button.
      Spacer(Modifier.height(8.dp))
      Box(
        modifier = Modifier
          .size(40.dp)
          .clip(CircleShape)
          .clickable { onBack() },
        contentAlignment = Alignment.Center
      ) {
        Icon(Icons.Default.ArrowBack, "Back", tint = HaraanColors.TextPrimary, modifier = Modifier.size(24.dp))
      }

      // Title row + Book Now.
      Spacer(Modifier.height(6.dp))
      Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween
      ) {
        Text("Price Chart", color = HaraanColors.TextPrimary, fontWeight = FontWeight.ExtraBold, fontSize = 26.sp)
        if (detail?.isBookable == true) {
          Button(
            onClick = { showBooking = true },
            colors = ButtonDefaults.buttonColors(containerColor = HaraanColors.GameHubGreen),
            shape = RoundedCornerShape(10.dp),
            modifier = Modifier.height(44.dp)
          ) {
            Text("BOOK NOW", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 14.sp)
          }
        }
      }
      Spacer(Modifier.height(4.dp))
      Text(detail?.name ?: "", color = HaraanColors.TextSecondary, fontSize = 15.sp)
      Spacer(Modifier.height(18.dp))

      if (loading) {
        Box(Modifier.fillMaxWidth().padding(40.dp), contentAlignment = Alignment.Center) {
          CircularProgressIndicator(color = HaraanColors.GameHubGreen)
        }
      }

      detail?.let { d ->
        Column(
          modifier = Modifier
            .fillMaxWidth()
            .verticalScroll(rememberScrollState())
            .navigationBarsPadding()
        ) {
          PriceChartBody(d)
          Spacer(Modifier.height(24.dp))
        }
      }
    }
  }

  if (showBooking && detail != null) {
    BookingSheet(venue = detail!!, onDismiss = { showBooking = false })
  }
}
