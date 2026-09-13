package com.haraan.partner.register.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import com.haraan.partner.register.model.ShiftDropCategory

@Composable
fun CashDropDialog(
    onDismiss: () -> Unit,
    onConfirm: (amount: Double, category: String, reason: String?) -> Unit,
    isSubmitting: Boolean = false,
) {
    var amountText by remember { mutableStateOf("") }
    var selectedCategory by remember { mutableStateOf(ShiftDropCategory.DIESEL) }
    var reasonText by remember { mutableStateOf("") }
    var validationError by remember { mutableStateOf<String?>(null) }

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
                Text(
                    text = "Record Cash Drop / Expense",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = ShiftColors.AuthInk
                )
                Text(
                    text = "Logs cash taken out of drawer during active shift",
                    fontSize = 12.sp,
                    color = ShiftColors.AuthMuted,
                    modifier = Modifier.padding(bottom = 12.dp)
                )

                HorizontalDivider(color = ShiftColors.Hairline, modifier = Modifier.padding(bottom = 12.dp))

                Text(
                    text = "Amount Withdrawn (₹)",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Medium,
                    color = ShiftColors.AuthInk,
                    modifier = Modifier.padding(bottom = 6.dp)
                )

                OutlinedTextField(
                    value = amountText,
                    onValueChange = {
                        amountText = it
                        validationError = null
                    },
                    prefix = { Text("₹ ", fontWeight = FontWeight.Bold, color = ShiftColors.RED) },
                    placeholder = { Text("500") },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    singleLine = true,
                    isError = validationError != null,
                    supportingText = validationError?.let { { Text(it, color = ShiftColors.RED) } },
                    modifier = Modifier.fillMaxWidth()
                )

                Spacer(Modifier.height(12.dp))

                Text(
                    text = "Expense Category",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Medium,
                    color = ShiftColors.AuthInk,
                    modifier = Modifier.padding(bottom = 6.dp)
                )

                LazyRow(
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    items(ShiftDropCategory.values()) { cat ->
                        val isSelected = selectedCategory == cat
                        Surface(
                            shape = RoundedCornerShape(20.dp),
                            color = if (isSelected) ShiftColors.AuthInk else ShiftColors.AuthPageBg,
                            modifier = Modifier.clickable { selectedCategory = cat }
                        ) {
                            Text(
                                text = cat.label,
                                fontSize = 11.sp,
                                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal,
                                color = if (isSelected) Color.White else ShiftColors.AuthInk,
                                modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                            )
                        }
                    }
                }

                Spacer(Modifier.height(14.dp))

                Text(
                    text = "Reason / Notes",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Medium,
                    color = ShiftColors.AuthInk,
                    modifier = Modifier.padding(bottom = 6.dp)
                )

                OutlinedTextField(
                    value = reasonText,
                    onValueChange = { reasonText = it },
                    placeholder = { Text("e.g. 50L diesel for generator") },
                    singleLine = false,
                    maxLines = 3,
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
                            val amount = amountText.toDoubleOrNull()
                            if (amount == null || amount <= 0) {
                                validationError = "Enter valid positive amount"
                                return@Button
                            }
                            onConfirm(amount, selectedCategory.code, reasonText.takeIf { it.isNotBlank() })
                        },
                        enabled = !isSubmitting,
                        colors = ButtonDefaults.buttonColors(containerColor = ShiftColors.RED),
                        modifier = Modifier.weight(1.5f)
                    ) {
                        if (isSubmitting) {
                            CircularProgressIndicator(
                                strokeWidth = 2.dp,
                                color = Color.White,
                                modifier = Modifier.size(16.dp)
                            )
                        } else {
                            Text("Record Drop", fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }
        }
    }
}
