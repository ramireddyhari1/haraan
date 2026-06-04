package com.example.thanna.ui.matches

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

@Composable
fun MomentumGraph(modifier: Modifier = Modifier) {
    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = CrexColors.Surface),
        border = androidx.compose.foundation.BorderStroke(1.dp, CrexColors.Border)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "MATCH MOMENTUM",
                    color = CrexColors.TextSecondary,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = 1.sp
                )
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(modifier = Modifier.size(8.dp).background(CrexColors.AccentBlue, androidx.compose.foundation.shape.CircleShape))
                    Spacer(modifier = Modifier.width(4.dp))
                    Text("IND", color = CrexColors.TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.Medium)
                    Spacer(modifier = Modifier.width(12.dp))
                    Box(modifier = Modifier.size(8.dp).background(CrexColors.AccentYellow, androidx.compose.foundation.shape.CircleShape))
                    Spacer(modifier = Modifier.width(4.dp))
                    Text("AUS", color = CrexColors.TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.Medium)
                }
            }

            Spacer(modifier = Modifier.height(24.dp))

            // The actual canvas drawing the momentum wave
            val points = remember {
                listOf(
                    0f, 15f, 40f, 25f, -10f, -30f, -15f, 10f, 45f, 60f, 35f, 10f, -5f, -20f, 15f, 50f
                )
            }

            Canvas(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(120.dp)
            ) {
                val width = size.width
                val height = size.height
                val centerLine = height / 2f
                val stepX = width / (points.size - 1)
                
                // Draw zero line
                drawLine(
                    color = CrexColors.Border,
                    start = Offset(0f, centerLine),
                    end = Offset(width, centerLine),
                    strokeWidth = 1.dp.toPx()
                )
                
                val path = Path()
                points.forEachIndexed { index, value ->
                    val x = index * stepX
                    // scale value to height (assume max magnitude is 100)
                    val y = centerLine - (value / 100f * centerLine)
                    if (index == 0) {
                        path.moveTo(x, y)
                    } else {
                        // Create smooth cubic bezier curve
                        val prevX = (index - 1) * stepX
                        val prevY = centerLine - (points[index - 1] / 100f * centerLine)
                        val cp1x = prevX + (x - prevX) / 2f
                        val cp1y = prevY
                        val cp2x = prevX + (x - prevX) / 2f
                        val cp2y = y
                        path.cubicTo(cp1x, cp1y, cp2x, cp2y, x, y)
                    }
                }
                
                // Draw path stroke
                drawPath(
                    path = path,
                    color = CrexColors.AccentBlue,
                    style = Stroke(
                        width = 3.dp.toPx(),
                        cap = StrokeCap.Round
                    )
                )
                
                // Add fill gradient below path (needs a closed path)
                val fillPath = Path().apply {
                    addPath(path)
                    lineTo(width, centerLine)
                    lineTo(0f, centerLine)
                    close()
                }
                
                drawPath(
                    path = fillPath,
                    brush = Brush.verticalGradient(
                        colors = listOf(
                            CrexColors.AccentBlue.copy(alpha = 0.3f),
                            Color.Transparent
                        ),
                        startY = 0f,
                        endY = centerLine
                    )
                )

                // Key event markers (e.g. Wickets, boundaries)
                val eventIndices = listOf(5, 10, 14)
                eventIndices.forEach { index ->
                    val x = index * stepX
                    val y = centerLine - (points[index] / 100f * centerLine)
                    
                    drawLine(
                        color = CrexColors.TextSecondary.copy(alpha = 0.4f),
                        start = Offset(x, y),
                        end = Offset(x, centerLine),
                        strokeWidth = 1.dp.toPx()
                    )
                    
                    drawCircle(
                        color = CrexColors.TextPrimary,
                        radius = 4.dp.toPx(),
                        center = Offset(x, y)
                    )
                    drawCircle(
                        color = CrexColors.AccentRed,
                        radius = 2.dp.toPx(),
                        center = Offset(x, y)
                    )
                }
            }
        }
    }
}
