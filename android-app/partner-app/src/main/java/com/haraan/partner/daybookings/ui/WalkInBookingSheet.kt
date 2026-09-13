package com.haraan.partner.daybookings.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Phone
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.DayGrid
import com.haraan.partner.PayMethod
import com.haraan.partner.daybookings.model.WalkInTarget

private val PrimaryBlue = Color(0xFF1D4ED8)
private val InkDark = Color(0xFF0B1220)
private val MutedGray = Color(0xFF6B7688)
private val GreenColor = Color(0xFF16A34A)
private val RedColor = Color(0xFFDC2626)
private val CardBorder = Color(0xFFE5E7EB)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun WalkInBookingSheet(
    target: WalkInTarget,
    grid: DayGrid?,
    date: String,
    isSubmitting: Boolean,
    onDismiss: () -> Unit,
    onSubmit: (slotId: Long, courtId: Long?, date: String, name: String, phone: String, method: PayMethod) -> Unit,
) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)

    var guestName by remember { mutableStateOf("") }
    var guestPhone by remember { mutableStateOf("") }
    var selectedCourtId by remember { mutableStateOf<Long?>(target.courtId) }
    var selectedMethod by remember { mutableStateOf(PayMethod.CASH) }
    var nameError by remember { mutableStateOf(false) }
    var phoneError by remember { mutableStateOf(false) }

    val currentSlot = remember(grid, target.slotId) {
        grid?.slots?.firstOrNull { it.slotId == target.slotId }
    }

    val availableCourts = remember(currentSlot, grid) {
        grid?.courts?.filter { court ->
            val cell = currentSlot?.courts?.firstOrNull { it.courtId == court.id }
            cell == null || (!cell.isBooked && !cell.isHeld && cell.allowed)
        } ?: emptyList()
    }

    val effectivePrice = remember(selectedCourtId, currentSlot, target.basePrice) {
        val cell = currentSlot?.courts?.firstOrNull { it.courtId == selectedCourtId }
        cell?.price ?: currentSlot?.price ?: target.basePrice
    }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = Color.White,
        shape = RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .imePadding()
                .padding(horizontal = 20.dp, vertical = 8.dp)
                .verticalScroll(rememberScrollState()),
        ) {
            // Header
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                Column {
                    Text(
                        "New Walk-in Booking",
                        fontSize = 18.sp,
                        fontWeight = FontWeight.ExtraBold,
                        color = InkDark,
                    )
                    Text(
                        "${target.slotTime} · $date",
                        fontSize = 12.sp,
                        color = MutedGray,
                    )
                }
                IconButton(onClick = onDismiss) {
                    Icon(Icons.Filled.Close, contentDescription = "Close", tint = MutedGray)
                }
            }

            Spacer(Modifier.height(16.dp))

            // Guest Name
            OutlinedTextField(
                value = guestName,
                onValueChange = {
                    guestName = it
                    nameError = it.isBlank()
                },
                label = { Text("Guest Name *") },
                placeholder = { Text("e.g. Rahul Sharma") },
                leadingIcon = { Icon(Icons.Filled.Person, contentDescription = null, tint = MutedGray) },
                singleLine = true,
                isError = nameError,
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = PrimaryBlue,
                    unfocusedBorderColor = CardBorder,
                ),
                modifier = Modifier.fillMaxWidth(),
            )
            if (nameError) {
                Text("Guest name is required", fontSize = 11.sp, color = RedColor, modifier = Modifier.padding(start = 4.dp, top = 2.dp))
            }

            Spacer(Modifier.height(12.dp))

            // Guest Phone
            OutlinedTextField(
                value = guestPhone,
                onValueChange = {
                    if (it.length <= 10 && it.all { char -> char.isDigit() }) {
                        guestPhone = it
                        phoneError = it.length != 10
                    }
                },
                label = { Text("Phone Number *") },
                placeholder = { Text("10-digit mobile number") },
                leadingIcon = { Icon(Icons.Filled.Phone, contentDescription = null, tint = MutedGray) },
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                singleLine = true,
                isError = phoneError,
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = PrimaryBlue,
                    unfocusedBorderColor = CardBorder,
                ),
                modifier = Modifier.fillMaxWidth(),
            )
            if (phoneError) {
                Text("Please enter a valid 10-digit mobile number", fontSize = 11.sp, color = RedColor, modifier = Modifier.padding(start = 4.dp, top = 2.dp))
            }

            // Court Selection
            if (availableCourts.isNotEmpty()) {
                Spacer(Modifier.height(16.dp))
                Text("Select Court / Table", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = InkDark)
                Spacer(Modifier.height(6.dp))
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    for (court in availableCourts) {
                        val isSelected = selectedCourtId == court.id
                        Box(
                            modifier = Modifier
                                .weight(1f)
                                .clip(RoundedCornerShape(10.dp))
                                .background(if (isSelected) Color(0xFFEFF6FF) else Color(0xFFF8FAFC))
                                .border(1.5.dp, if (isSelected) PrimaryBlue else CardBorder, RoundedCornerShape(10.dp))
                                .clickable { selectedCourtId = court.id }
                                .padding(vertical = 10.dp),
                            contentAlignment = Alignment.Center,
                        ) {
                            Text(
                                court.name,
                                fontSize = 12.sp,
                                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                color = if (isSelected) PrimaryBlue else InkDark,
                            )
                        }
                    }
                }
            }

            Spacer(Modifier.height(16.dp))

            // Payment Method Selection
            Text("Payment Mode", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = InkDark)
            Spacer(Modifier.height(6.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                val methods = listOf(PayMethod.CASH, PayMethod.UPI, PayMethod.LINK)
                for (m in methods) {
                    val isSelected = selectedMethod == m
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .clip(RoundedCornerShape(10.dp))
                            .background(if (isSelected) Color(0xFFEFF6FF) else Color(0xFFF8FAFC))
                            .border(1.5.dp, if (isSelected) PrimaryBlue else CardBorder, RoundedCornerShape(10.dp))
                            .clickable { selectedMethod = m }
                            .padding(vertical = 10.dp),
                        contentAlignment = Alignment.Center,
                    ) {
                        Text(
                            m.label,
                            fontSize = 12.sp,
                            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                            color = if (isSelected) PrimaryBlue else InkDark,
                        )
                    }
                }
            }

            Spacer(Modifier.height(16.dp))

            // Summary Card
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .background(Color(0xFFF8FAFC))
                    .border(1.dp, CardBorder, RoundedCornerShape(12.dp))
                    .padding(14.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                Text("Total to collect:", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = InkDark)
                Text(
                    "₹" + effectivePrice.toInt(),
                    fontSize = 18.sp,
                    fontWeight = FontWeight.ExtraBold,
                    color = GreenColor,
                )
            }

            Spacer(Modifier.height(20.dp))

            // Submit Button
            Button(
                onClick = {
                    val isNameValid = guestName.isNotBlank()
                    val isPhoneValid = guestPhone.length == 10
                    nameError = !isNameValid
                    phoneError = !isPhoneValid

                    if (isNameValid && isPhoneValid) {
                        onSubmit(
                            target.slotId,
                            selectedCourtId,
                            date,
                            guestName.trim(),
                            guestPhone.trim(),
                            selectedMethod,
                        )
                    }
                },
                enabled = !isSubmitting,
                colors = ButtonDefaults.buttonColors(containerColor = PrimaryBlue),
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .height(48.dp),
            ) {
                if (isSubmitting) {
                    CircularProgressIndicator(color = Color.White, modifier = Modifier.size(20.dp), strokeWidth = 2.dp)
                } else {
                    Text("Confirm Walk-in Booking", fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = Color.White)
                }
            }

            Spacer(Modifier.height(12.dp))
        }
    }
}
