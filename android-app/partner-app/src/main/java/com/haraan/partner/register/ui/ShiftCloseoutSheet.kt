package com.haraan.partner.register.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.register.model.DenominationCount
import com.haraan.partner.register.model.ShiftSessionUiModel
import kotlin.math.abs

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ShiftCloseoutSheet(
    shift: ShiftSessionUiModel,
    onDismiss: () -> Unit,
    onConfirmClose: (countedCash: Double, note: String?, denominations: Map<String, Int>?) -> Unit,
    isSubmitting: Boolean = false,
) {
    var d500 by remember { mutableStateOf(0) }
    var d200 by remember { mutableStateOf(0) }
    var d100 by remember { mutableStateOf(0) }
    var d50 by remember { mutableStateOf(0) }
    var d20 by remember { mutableStateOf(0) }
    var d10 by remember { mutableStateOf(0) }
    var coins by remember { mutableStateOf(0) }

    var useDirectTotal by remember { mutableStateOf(false) }
    var directTotalText by remember { mutableStateOf("") }
    var noteText by remember { mutableStateOf("") }
    var validationError by remember { mutableStateOf<String?>(null) }

    val denomTotal = (d500 * 500 + d200 * 200 + d100 * 100 + d50 * 50 + d20 * 20 + d10 * 10 + coins).toDouble()
    val countedCash = if (useDirectTotal) (directTotalText.toDoubleOrNull() ?: 0.0) else denomTotal
    val expectedCash = shift.expectedCash
    val variance = countedCash - expectedCash
    val isSquare = abs(variance) < 0.01
    val isShort = variance < -0.01
    val isOver = variance > 0.01

    ModalBottomSheet(
        onDismissRequest = { if (!isSubmitting) onDismiss() },
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = Color.White,
        dragHandle = { BottomSheetDefaults.DragHandle() },
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 20.dp, vertical = 8.dp)
                .verticalScroll(rememberScrollState())
        ) {
            // Header
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween,
                modifier = Modifier.fillMaxWidth()
            ) {
                Column {
                    Text(
                        text = "End of Shift Close-Out",
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Bold,
                        color = ShiftColors.AuthInk
                    )
                    Text(
                        text = "Staff: ${shift.staffName} · Started: ${shift.openedAt.takeLast(8).take(5)}",
                        fontSize = 12.sp,
                        color = ShiftColors.AuthMuted
                    )
                }
                Surface(
                    shape = RoundedCornerShape(12.dp),
                    color = ShiftColors.AuthPageBg,
                    modifier = Modifier.padding(start = 8.dp)
                ) {
                    Text(
                        text = if (useDirectTotal) "Manual Entry" else "Denomination Mode",
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        color = ShiftColors.AuthAccent,
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                    )
                }
            }

            HorizontalDivider(color = ShiftColors.Hairline, modifier = Modifier.padding(vertical = 12.dp))

            // Expected Cash Banner
            Surface(
                shape = RoundedCornerShape(12.dp),
                color = ShiftColors.AuthPageBg,
                modifier = Modifier.fillMaxWidth()
            ) {
                Row(
                    modifier = Modifier.padding(14.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text("SYSTEM EXPECTED CASH", fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = ShiftColors.AuthMuted)
                        Text("Float (₹${shift.openingFloat.toInt()}) + Cash In (₹${shift.cashCollected.toInt()}) - Drops (₹${shift.totalDrops.toInt()})", fontSize = 11.sp, color = ShiftColors.AuthMuted)
                    }
                    Text("₹${String.format("%.2f", expectedCash)}", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = ShiftColors.AuthInk)
                }
            }

            Spacer(Modifier.height(14.dp))

            // Switch to manual total toggle
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween,
                modifier = Modifier.fillMaxWidth()
            ) {
                Text(
                    text = "COUNT PHYSICAL CASH",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    color = ShiftColors.AuthInk
                )
                TextButton(onClick = { useDirectTotal = !useDirectTotal }) {
                    Text(
                        text = if (useDirectTotal) "Switch to Denominations" else "Direct Total Entry",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        color = ShiftColors.AuthAccent
                    )
                }
            }

            if (useDirectTotal) {
                OutlinedTextField(
                    value = directTotalText,
                    onValueChange = { directTotalText = it },
                    prefix = { Text("₹ ", fontWeight = FontWeight.Bold) },
                    placeholder = { Text("Enter total cash counted") },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth()
                )
            } else {
                // Denomination Grid
                Column(
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    DenominationRow(value = 500, count = d500, onCountChange = { d500 = it })
                    DenominationRow(value = 200, count = d200, onCountChange = { d200 = it })
                    DenominationRow(value = 100, count = d100, onCountChange = { d100 = it })
                    DenominationRow(value = 50, count = d50, onCountChange = { d50 = it })
                    DenominationRow(value = 20, count = d20, onCountChange = { d20 = it })
                    DenominationRow(value = 10, count = d10, onCountChange = { d10 = it })
                    CoinsRow(amount = coins, onAmountChange = { coins = it })
                }
            }

            Spacer(Modifier.height(16.dp))

            // Variance Card
            val varianceBg = when {
                isSquare -> ShiftColors.GREEN.copy(alpha = 0.08f)
                isShort -> ShiftColors.RED.copy(alpha = 0.08f)
                else -> ShiftColors.AMBER.copy(alpha = 0.08f)
            }
            val varianceBorder = when {
                isSquare -> ShiftColors.GREEN.copy(alpha = 0.3f)
                isShort -> ShiftColors.RED.copy(alpha = 0.3f)
                else -> ShiftColors.AMBER.copy(alpha = 0.3f)
            }
            val varianceTextColor = when {
                isSquare -> ShiftColors.GREEN
                isShort -> ShiftColors.RED
                else -> ShiftColors.AMBER
            }

            Surface(
                shape = RoundedCornerShape(12.dp),
                color = varianceBg,
                border = androidx.compose.foundation.BorderStroke(1.dp, varianceBorder),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(modifier = Modifier.padding(14.dp)) {
                    Row(
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text("Total Counted Cash", fontSize = 13.sp, color = ShiftColors.AuthInk)
                        Text("₹${String.format("%.2f", countedCash)}", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = ShiftColors.AuthInk)
                    }
                    Spacer(Modifier.height(4.dp))
                    Row(
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text(
                            text = when {
                                isSquare -> "Variance: Square (Exact Match)"
                                isShort -> "Variance: Drawer Short"
                                else -> "Variance: Drawer Over"
                            },
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Bold,
                            color = varianceTextColor
                        )
                        Text(
                            text = if (isSquare) "₹0.00" else (if (isShort) "-₹${String.format("%.2f", abs(variance))}" else "+₹${String.format("%.2f", variance)}"),
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Bold,
                            color = varianceTextColor
                        )
                    }
                }
            }

            Spacer(Modifier.height(14.dp))

            // Reason field (mandatory if short/over)
            Text(
                text = if (!isSquare) "Variance Reason (Required) *" else "Close-Out Notes (Optional)",
                fontSize = 12.sp,
                fontWeight = FontWeight.Medium,
                color = if (!isSquare) ShiftColors.RED else ShiftColors.AuthInk,
                modifier = Modifier.padding(bottom = 4.dp)
            )

            OutlinedTextField(
                value = noteText,
                onValueChange = {
                    noteText = it
                    validationError = null
                },
                placeholder = {
                    Text(if (!isSquare) "Explain why drawer is short/over (e.g. ₹50 change rounding)" else "Any remarks for shift handover")
                },
                isError = validationError != null,
                supportingText = validationError?.let { { Text(it, color = ShiftColors.RED) } },
                singleLine = false,
                maxLines = 2,
                modifier = Modifier.fillMaxWidth()
            )

            Spacer(Modifier.height(20.dp))

            // Submit Button
            Button(
                onClick = {
                    if (!isSquare && noteText.isBlank()) {
                        validationError = "Mandatory explanation required for non-zero variance."
                        return@Button
                    }
                    val denomMap = if (!useDirectTotal) {
                        mapOf(
                            "500" to d500,
                            "200" to d200,
                            "100" to d100,
                            "50" to d50,
                            "20" to d20,
                            "10" to d10,
                            "coins" to coins
                        )
                    } else null
                    onConfirmClose(countedCash, noteText.takeIf { it.isNotBlank() }, denomMap)
                },
                enabled = !isSubmitting,
                colors = ButtonDefaults.buttonColors(
                    containerColor = if (isShort) ShiftColors.RED else ShiftColors.AuthAccent
                ),
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier.fillMaxWidth().height(50.dp)
            ) {
                if (isSubmitting) {
                    CircularProgressIndicator(strokeWidth = 2.dp, color = Color.White, modifier = Modifier.size(20.dp))
                } else {
                    Text(
                        text = "Confirm & Close Shift",
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
            }

            Spacer(Modifier.height(16.dp))
        }
    }
}

@Composable
private fun DenominationRow(
    value: Int,
    count: Int,
    onCountChange: (Int) -> Unit,
) {
    val subtotal = value * count
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween,
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(8.dp))
            .background(ShiftColors.AuthPageBg)
            .padding(horizontal = 12.dp, vertical = 6.dp)
    ) {
        Text("₹$value", fontWeight = FontWeight.Bold, fontSize = 14.sp, color = ShiftColors.AuthInk)

        Row(verticalAlignment = Alignment.CenterVertically) {
            // Minus button
            Surface(
                shape = CircleShape,
                color = Color.White,
                border = androidx.compose.foundation.BorderStroke(1.dp, ShiftColors.Hairline),
                modifier = Modifier.size(28.dp),
                onClick = { if (count > 0) onCountChange(count - 1) }
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Text("-", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = ShiftColors.AuthInk)
                }
            }

            Text(
                text = "$count",
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                color = ShiftColors.AuthInk,
                modifier = Modifier.padding(horizontal = 14.dp)
            )

            // Plus button
            Surface(
                shape = CircleShape,
                color = Color.White,
                border = androidx.compose.foundation.BorderStroke(1.dp, ShiftColors.Hairline),
                modifier = Modifier.size(28.dp),
                onClick = { onCountChange(count + 1) }
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(Icons.Default.Add, contentDescription = null, modifier = Modifier.size(14.dp), tint = ShiftColors.AuthInk)
                }
            }
        }

        Text(
            text = "₹$subtotal",
            fontSize = 13.sp,
            fontWeight = FontWeight.SemiBold,
            color = if (subtotal > 0) ShiftColors.AuthAccent else ShiftColors.AuthMuted
        )
    }
}

@Composable
private fun CoinsRow(
    amount: Int,
    onAmountChange: (Int) -> Unit,
) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween,
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(8.dp))
            .background(ShiftColors.AuthPageBg)
            .padding(horizontal = 12.dp, vertical = 6.dp)
    ) {
        Text("Loose Coins (₹)", fontWeight = FontWeight.Bold, fontSize = 14.sp, color = ShiftColors.AuthInk)

        Row(verticalAlignment = Alignment.CenterVertically) {
            Surface(
                shape = CircleShape,
                color = Color.White,
                border = androidx.compose.foundation.BorderStroke(1.dp, ShiftColors.Hairline),
                modifier = Modifier.size(28.dp),
                onClick = { if (amount >= 5) onAmountChange(amount - 5) else onAmountChange(0) }
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Text("-", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = ShiftColors.AuthInk)
                }
            }

            Text(
                text = "₹$amount",
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                color = ShiftColors.AuthInk,
                modifier = Modifier.padding(horizontal = 14.dp)
            )

            Surface(
                shape = CircleShape,
                color = Color.White,
                border = androidx.compose.foundation.BorderStroke(1.dp, ShiftColors.Hairline),
                modifier = Modifier.size(28.dp),
                onClick = { onAmountChange(amount + 5) }
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(Icons.Default.Add, contentDescription = null, modifier = Modifier.size(14.dp), tint = ShiftColors.AuthInk)
                }
            }
        }

        Text(
            text = "₹$amount",
            fontSize = 13.sp,
            fontWeight = FontWeight.SemiBold,
            color = if (amount > 0) ShiftColors.AuthAccent else ShiftColors.AuthMuted
        )
    }
}
