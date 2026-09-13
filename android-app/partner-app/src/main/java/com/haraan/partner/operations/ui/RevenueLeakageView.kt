package com.haraan.partner.operations.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.operations.model.FunnelStageItem
import com.haraan.partner.operations.model.RevenueLeakageAlertItem
import com.haraan.partner.operations.model.WhatsAppFunnelOverview

@Composable
fun RevenueLeakageView(
    funnel: WhatsAppFunnelOverview,
    alerts: List<RevenueLeakageAlertItem>,
    onResolveAlert: (Long) -> Unit,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .padding(14.dp)
    ) {
        Text(
            text = "WhatsApp Conversion Funnel",
            fontSize = 15.sp,
            fontWeight = FontWeight.Bold,
            color = Color(0xFF0F172A)
        )
        Spacer(Modifier.height(8.dp))

        // Funnel Stages Card
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(12.dp),
            colors = CardDefaults.cardColors(containerColor = Color.White),
            elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
        ) {
            Column(Modifier.padding(14.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text("Overall Conversion", fontSize = 13.sp, color = Color(0xFF64748B))
                    Text("${funnel.conversionRatePct}%", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Color(0xFF059669))
                }

                Spacer(Modifier.height(10.dp))

                funnel.stages.forEach { stage ->
                    FunnelStageRow(stage)
                    Spacer(Modifier.height(6.dp))
                }
            }
        }

        Spacer(Modifier.height(18.dp))

        Text(
            text = "Revenue Leakage Alerts (${alerts.size})",
            fontSize = 15.sp,
            fontWeight = FontWeight.Bold,
            color = Color(0xFF0F172A)
        )
        Text(
            text = "Detects abandoned holds, cash mismatches & unutilized peak hours",
            fontSize = 12.sp,
            color = Color(0xFF64748B)
        )

        Spacer(Modifier.height(10.dp))

        if (alerts.isEmpty()) {
            Surface(
                color = Color(0xFFECFDF5),
                shape = RoundedCornerShape(10.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                Row(
                    modifier = Modifier.padding(14.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(Icons.Filled.CheckCircle, contentDescription = null, tint = Color(0xFF059669))
                    Spacer(Modifier.width(10.dp))
                    Text("No revenue leakage detected. Venue running at peak efficiency!", color = Color(0xFF065F46), fontSize = 13.sp)
                }
            }
        } else {
            LazyColumn(
                modifier = Modifier.fillMaxWidth(),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(alerts) { alert ->
                    AlertRowCard(alert, onResolve = { onResolveAlert(alert.id) })
                }
            }
        }
    }
}

@Composable
fun FunnelStageRow(stage: FunnelStageItem) {
    Column(Modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Text(stage.stage, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = Color(0xFF1E293B))
            Text("${stage.count}", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Color(0xFF0F172A))
        }
        Spacer(Modifier.height(4.dp))
        LinearProgressIndicator(
            progress = { (stage.count.toFloat() / 100f).coerceIn(0.05f, 1f) },
            modifier = Modifier.fillMaxWidth().height(6.dp),
            color = Color(0xFF128C7E),
            trackColor = Color(0xFFE2E8F0),
        )
    }
}

@Composable
fun AlertRowCard(alert: RevenueLeakageAlertItem, onResolve: () -> Unit) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(10.dp),
        colors = CardDefaults.cardColors(containerColor = Color(0xFFFFFBEB)),
        border = CardDefaults.outlinedCardBorder().copy(brush = androidx.compose.ui.graphics.SolidColor(Color(0xFFFDE68A)))
    ) {
        Column(Modifier.padding(12.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(alert.title, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Color(0xFF92400E))
                TextButton(
                    onClick = onResolve,
                    contentPadding = PaddingValues(horizontal = 8.dp, vertical = 2.dp)
                ) {
                    Text("Resolve", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Color(0xFFD97706))
                }
            }
            Text(alert.description, fontSize = 12.sp, color = Color(0xFF78350F))
        }
    }
}