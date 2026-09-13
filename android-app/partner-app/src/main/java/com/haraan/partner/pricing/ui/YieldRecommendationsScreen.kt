package com.haraan.partner.pricing.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.pricing.model.YieldRecommendationItem

@Composable
fun YieldRecommendationsScreen(
    recommendations: List<YieldRecommendationItem>,
    isSubmitting: Boolean,
    onApplyRecommendation: (YieldRecommendationItem) -> Unit,
    modifier: Modifier = Modifier
) {
    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp)
    ) {
        item {
            Card(
                shape = RoundedCornerShape(14.dp),
                colors = CardDefaults.cardColors(containerColor = PricingColors.SurfaceWhite),
                elevation = CardDefaults.cardElevation(defaultElevation = 1.5.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            modifier = Modifier
                                .size(32.dp)
                                .clip(CircleShape)
                                .background(PricingColors.GREEN.copy(alpha = 0.1f)),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(Icons.Default.AutoAwesome, contentDescription = null, tint = PricingColors.GREEN, modifier = Modifier.size(18.dp))
                        }
                        Spacer(Modifier.width(10.dp))
                        Column {
                            Text("SMART REVENUE RECOVERY", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = PricingColors.AuthInk)
                            Text("Real rupee yield opportunities based on your last 30-day occupancy", fontSize = 11.sp, color = PricingColors.AuthMuted)
                        }
                    }
                }
            }
        }

        if (recommendations.isEmpty()) {
            item {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = 40.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Icon(Icons.Default.CheckCircle, contentDescription = null, tint = PricingColors.GREEN, modifier = Modifier.size(48.dp))
                        Spacer(Modifier.height(10.dp))
                        Text("Pricing is fully optimized!", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = PricingColors.AuthInk)
                        Text("No dead slots or high-surge anomalies detected right now.", fontSize = 12.sp, color = PricingColors.AuthMuted)
                    }
                }
            }
        } else {
            items(recommendations, key = { it.id }) { rec ->
                YieldRecommendationCard(
                    rec = rec,
                    isSubmitting = isSubmitting,
                    onApply = { onApplyRecommendation(rec) }
                )
            }
        }
    }
}

@Composable
private fun YieldRecommendationCard(
    rec: YieldRecommendationItem,
    isSubmitting: Boolean,
    onApply: () -> Unit
) {
    Card(
        shape = RoundedCornerShape(14.dp),
        colors = CardDefaults.cardColors(containerColor = PricingColors.SurfaceWhite),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
        border = androidx.compose.foundation.BorderStroke(1.dp, PricingColors.GREEN.copy(alpha = 0.3f)),
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text(rec.title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = PricingColors.AuthInk)
                    Text("${rec.dayOfWeek} · ${rec.timeWindow}", fontSize = 11.sp, color = PricingColors.AuthMuted)
                }

                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = PricingColors.GREEN.copy(alpha = 0.1f)
                ) {
                    Text(
                        text = "+₹${String.format("%,.0f", rec.projectedMonthlyUplift)}/mo",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Black,
                        color = PricingColors.GREEN,
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                    )
                }
            }

            Spacer(Modifier.height(8.dp))

            // Rationale text
            Text(
                text = rec.rationale,
                fontSize = 12.sp,
                color = PricingColors.AuthInk,
                lineHeight = 17.sp
            )

            HorizontalDivider(color = PricingColors.Hairline, modifier = Modifier.padding(vertical = 12.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("Occupancy: ", fontSize = 11.sp, color = PricingColors.AuthMuted)
                    Text("${rec.currentOccupancyRate.toInt()}%", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = PricingColors.RED)
                }

                Button(
                    onClick = onApply,
                    enabled = !isSubmitting,
                    shape = RoundedCornerShape(8.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = PricingColors.GREEN),
                    contentPadding = PaddingValues(horizontal = 14.dp, vertical = 6.dp),
                    modifier = Modifier.height(34.dp)
                ) {
                    Icon(Icons.Default.Check, contentDescription = null, modifier = Modifier.size(14.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("Apply Rule", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}
