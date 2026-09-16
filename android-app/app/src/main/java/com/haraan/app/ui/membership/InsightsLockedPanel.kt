package com.haraan.app.ui.membership

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.membership.MembershipNav

// The locked-insights card sits inside the match screens' Insights tabs, where a dark card reads
// as "a different kind of content" against the light match surface.
internal object InsightNavy {
    val Ground = Color(0xFF0A1322)
    val Ink = Color(0xFFEAF0F8)
    val Muted = Color(0xFF8A99B2)
    val Blue = Color(0xFF5B8DFF)
}

/**
 * What a match's Insights tab shows when the server refuses: the server's own sentence, and
 * the one action that fixes it — choose sports (Pro) or see plans (Free / guest).
 */
@Composable
fun InsightsLockedPanel(message: String, code: String?, modifier: Modifier = Modifier) {
    val choose = code == "selection_required"
    Column(
        modifier
            .fillMaxWidth()
            .padding(16.dp)
            .clip(RoundedCornerShape(18.dp))
            .background(InsightNavy.Ground)
            .padding(20.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Filled.Lock, null, tint = InsightNavy.Blue, modifier = Modifier.size(14.dp))
            Spacer(Modifier.width(6.dp))
            Text("ADVANCED INSIGHTS", color = InsightNavy.Muted, fontSize = 10.5.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
        }
        Spacer(Modifier.height(10.dp))
        Text(message, color = InsightNavy.Ink, fontSize = 15.5.sp, fontWeight = FontWeight.SemiBold, lineHeight = 21.sp)
        Spacer(Modifier.height(16.dp))
        Box(
            Modifier
                .clip(RoundedCornerShape(12.dp))
                .background(InsightNavy.Blue)
                .clickable { if (choose) MembershipNav.openInsightSports() else MembershipNav.open() }
                .padding(horizontal = 18.dp, vertical = 11.dp),
        ) {
            Text(if (choose) "Choose sports" else "See plans", color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
        }
    }
}
