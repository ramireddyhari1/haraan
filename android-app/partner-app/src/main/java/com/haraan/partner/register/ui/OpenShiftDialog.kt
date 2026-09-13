package com.haraan.partner.register.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Lock
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
import androidx.compose.ui.window.Dialog

@Composable
fun OpenShiftDialog(
    onDismiss: () -> Unit,
    onConfirm: (openingFloat: Double, note: String?) -> Unit,
    isSubmitting: Boolean = false,
) {
    var floatText by remember { mutableStateOf("1000") }
    var noteText by remember { mutableStateOf("") }
    var validationError by remember { mutableStateOf<String?>(null) }

    val presetAmounts = listOf(0, 500, 1000, 2000, 5000)

    Dialog(onDismissRequest = { if (!isSubmitting) onDismiss() }) {
        Surface(
            shape = RoundedCornerShape(16.dp),
            color = Color.White,
            shadowElevation = 8.dp,
            modifier = Modifier.fillMaxWidth().padding(horizontal = 8.dp)
        ) {
            Column(
                modifier = Modifier.padding(20.dp)
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.padding(bottom = 12.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .size(36.dp)
                            .clip(RoundedCornerShape(8.dp))
                            .background(ShiftColors.AuthAccent.copy(alpha = 0.1f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            Icons.Default.Lock,
                            contentDescription = null,
                            tint = ShiftColors.AuthAccent,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                    Spacer(Modifier.width(12.dp))
                    Column {
                        Text(
                            text = "Open Desk Shift",
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            color = ShiftColors.AuthInk
                        )
                        Text(
                            text = "Set starting cash drawer float",
                            fontSize = 12.sp,
                            color = ShiftColors.AuthMuted
                        )
                    }
                }

                HorizontalDivider(color = ShiftColors.Hairline, modifier = Modifier.padding(vertical = 8.dp))

                Text(
                    text = "Opening Cash Float (₹)",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Medium,
                    color = ShiftColors.AuthInk,
                    modifier = Modifier.padding(bottom = 6.dp)
                )

                OutlinedTextField(
                    value = floatText,
                    onValueChange = {
                        floatText = it
                        validationError = null
                    },
                    prefix = { Text("₹ ", fontWeight = FontWeight.Bold) },
                    placeholder = { Text("0") },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    singleLine = true,
                    isError = validationError != null,
                    supportingText = validationError?.let { { Text(it, color = ShiftColors.RED) } },
                    modifier = Modifier.fillMaxWidth()
                )

                Spacer(Modifier.height(10.dp))

                // Quick preset chips
                Text(
                    text = "Quick Presets",
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Medium,
                    color = ShiftColors.AuthMuted,
                    modifier = Modifier.padding(bottom = 6.dp)
                )
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    presetAmounts.forEach { preset ->
                        val isSelected = floatText == preset.toString()
                        Surface(
                            shape = RoundedCornerShape(8.dp),
                            color = if (isSelected) ShiftColors.AuthAccent else ShiftColors.AuthPageBg,
                            modifier = Modifier
                                .weight(1f)
                                .clickable { floatText = preset.toString() }
                        ) {
                            Box(
                                contentAlignment = Alignment.Center,
                                modifier = Modifier.padding(vertical = 8.dp)
                            ) {
                                Text(
                                    text = if (preset == 0) "₹0" else "₹${preset}",
                                    fontSize = 11.sp,
                                    fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal,
                                    color = if (isSelected) Color.White else ShiftColors.AuthInk
                                )
                            }
                        }
                    }
                }

                Spacer(Modifier.height(14.dp))

                Text(
                    text = "Shift Note (Optional)",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Medium,
                    color = ShiftColors.AuthInk,
                    modifier = Modifier.padding(bottom = 6.dp)
                )

                OutlinedTextField(
                    value = noteText,
                    onValueChange = { noteText = it },
                    placeholder = { Text("e.g. Morning Shift - Terminal 1") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth()
                )

                Spacer(Modifier.height(20.dp))

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    OutlinedButton(
                        onClick = onDismiss,
                        enabled = !isSubmitting,
                        modifier = Modifier.weight(1f)
                    ) {
                        Text("Cancel", color = ShiftColors.AuthInk)
                    }

                    Button(
                        onClick = {
                            val amount = floatText.toDoubleOrNull()
                            if (amount == null || amount < 0) {
                                validationError = "Enter valid positive float"
                                return@Button
                            }
                            onConfirm(amount, noteText.takeIf { it.isNotBlank() })
                        },
                        enabled = !isSubmitting,
                        colors = ButtonDefaults.buttonColors(containerColor = ShiftColors.AuthAccent),
                        modifier = Modifier.weight(1.5f)
                    ) {
                        if (isSubmitting) {
                            CircularProgressIndicator(
                                strokeWidth = 2.dp,
                                color = Color.White,
                                modifier = Modifier.size(16.dp)
                            )
                        } else {
                            Text("Open Shift", fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }
        }
    }
}
