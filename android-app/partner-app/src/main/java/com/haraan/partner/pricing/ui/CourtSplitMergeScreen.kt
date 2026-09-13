package com.haraan.partner.pricing.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.pricing.model.*

@Composable
fun CourtSplitMergeScreen(
    hierarchy: CourtHierarchyData,
    isSubmitting: Boolean,
    onSplitCourt: (SplitCourtRequest) -> Unit,
    onMergeCourts: (Long) -> Unit,
    modifier: Modifier = Modifier
) {
    var showSplitDialogForCourt by remember { mutableStateOf<StandaloneCourtItem?>(null) }
    var showMergeConfirmForCourt by remember { mutableStateOf<CompositeCourtItem?>(null) }

    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(16.dp)
    ) {
        // Explanatory Banner
        Surface(
            shape = RoundedCornerShape(12.dp),
            color = PricingColors.CompositeBadge.copy(alpha = 0.5f),
            modifier = Modifier.fillMaxWidth()
        ) {
            Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Default.CallSplit, contentDescription = null, tint = PricingColors.CompositeText, modifier = Modifier.size(20.dp))
                Spacer(Modifier.width(10.dp))
                Column {
                    Text("TURF PARTITION & SPLIT MANAGER", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = PricingColors.CompositeText)
                    Text(
                        "Split full grounds into sub-courts (e.g. 5v5 pitches) to earn more during peak hours. The engine strictly prevents double-booking across parent and sub-courts.",
                        fontSize = 11.sp,
                        color = PricingColors.AuthInk
                    )
                }
            }
        }

        Spacer(Modifier.height(14.dp))

        LazyColumn(
            verticalArrangement = Arrangement.spacedBy(14.dp),
            modifier = Modifier.weight(1f)
        ) {
            // Composite Courts Section
            if (hierarchy.compositeCourts.isNotEmpty()) {
                item {
                    Text(
                        "PARTITIONED COMPOSITE GROUNDS (${hierarchy.compositeCourts.size})",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = PricingColors.AuthMuted
                    )
                }

                items(hierarchy.compositeCourts, key = { it.id }) { parent ->
                    CompositeCourtCard(
                        court = parent,
                        onMerge = { showMergeConfirmForCourt = parent }
                    )
                }
            }

            // Standalone Courts Section
            if (hierarchy.standaloneCourts.isNotEmpty()) {
                item {
                    Text(
                        "STANDALONE COURTS (${hierarchy.standaloneCourts.size})",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = PricingColors.AuthMuted,
                        modifier = Modifier.padding(top = 8.dp)
                    )
                }

                items(hierarchy.standaloneCourts, key = { it.id }) { court ->
                    StandaloneCourtCard(
                        court = court,
                        onSplit = { showSplitDialogForCourt = court }
                    )
                }
            }
        }
    }

    // Split Dialog
    showSplitDialogForCourt?.let { court ->
        SplitCourtDialog(
            court = court,
            isSubmitting = isSubmitting,
            onConfirm = { req ->
                onSplitCourt(req)
                showSplitDialogForCourt = null
            },
            onDismiss = { showSplitDialogForCourt = null }
        )
    }

    // Merge Confirmation Dialog
    showMergeConfirmForCourt?.let { court ->
        AlertDialog(
            onDismissRequest = { showMergeConfirmForCourt = null },
            title = { Text("Merge '${court.name}'?") },
            text = {
                Text(
                    "This will remove the ${court.children.size} sub-courts and restore the ground as a single composite arena. Ensure no upcoming bookings exist on sub-courts.",
                    fontSize = 13.sp
                )
            },
            confirmButton = {
                Button(
                    onClick = {
                        onMergeCourts(court.id)
                        showMergeConfirmForCourt = null
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = PricingColors.AuthAccent)
                ) {
                    Text("Confirm Merge")
                }
            },
            dismissButton = {
                TextButton(onClick = { showMergeConfirmForCourt = null }) { Text("Cancel") }
            }
        )
    }
}

@Composable
private fun CompositeCourtCard(
    court: CompositeCourtItem,
    onMerge: () -> Unit
) {
    val totalChildPrice = court.children.sumOf { it.price }
    val uplift = totalChildPrice - court.price

    Card(
        shape = RoundedCornerShape(14.dp),
        colors = CardDefaults.cardColors(containerColor = PricingColors.SurfaceWhite),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(court.name, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = PricingColors.AuthInk)
                        Spacer(Modifier.width(8.dp))
                        Surface(
                            shape = RoundedCornerShape(6.dp),
                            color = PricingColors.CompositeBadge
                        ) {
                            Text(
                                text = "${court.children.size}-WAY ${court.splitType.uppercase()}",
                                fontSize = 9.sp,
                                fontWeight = FontWeight.Bold,
                                color = PricingColors.CompositeText,
                                modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                            )
                        }
                    }
                    Text("Full Arena: ₹${court.price}/hr · Combined Partitions: ₹${totalChildPrice}/hr", fontSize = 11.sp, color = PricingColors.AuthMuted)
                }

                OutlinedButton(
                    onClick = onMerge,
                    shape = RoundedCornerShape(8.dp),
                    contentPadding = PaddingValues(horizontal = 10.dp, vertical = 4.dp),
                    modifier = Modifier.height(32.dp)
                ) {
                    Icon(Icons.Default.Merge, contentDescription = null, modifier = Modifier.size(14.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("Merge", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                }
            }

            if (uplift > 0) {
                Spacer(Modifier.height(8.dp))
                Surface(
                    shape = RoundedCornerShape(6.dp),
                    color = PricingColors.GREEN.copy(alpha = 0.08f),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text(
                        text = "Partitioning earns +₹${uplift}/hr (+${((uplift.toDouble() / court.price) * 100).toInt()}%) more when booked separately.",
                        fontSize = 10.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = PricingColors.GREEN,
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                    )
                }
            }

            HorizontalDivider(color = PricingColors.Hairline, modifier = Modifier.padding(vertical = 10.dp))

            // Sub-courts Grid
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                court.children.forEach { child ->
                    Surface(
                        shape = RoundedCornerShape(8.dp),
                        color = PricingColors.AuthPageBg,
                        border = androidx.compose.foundation.BorderStroke(1.dp, PricingColors.Hairline),
                        modifier = Modifier.weight(1f)
                    ) {
                        Column(modifier = Modifier.padding(10.dp)) {
                            Text(child.name, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = PricingColors.AuthInk)
                            Text(child.partitionLabel ?: "Partition", fontSize = 10.sp, color = PricingColors.AuthMuted)
                            Spacer(Modifier.height(4.dp))
                            Text("₹${child.price}/hr", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = PricingColors.AuthAccent)
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun StandaloneCourtCard(
    court: StandaloneCourtItem,
    onSplit: () -> Unit
) {
    Card(
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = PricingColors.SurfaceWhite),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
        modifier = Modifier.fillMaxWidth()
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Text(court.name, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = PricingColors.AuthInk)
                Text("₹${court.price}/hr · Standalone ${court.kind}", fontSize = 11.sp, color = PricingColors.AuthMuted)
            }

            Button(
                onClick = onSplit,
                shape = RoundedCornerShape(8.dp),
                colors = ButtonDefaults.buttonColors(containerColor = PricingColors.AuthAccent),
                contentPadding = PaddingValues(horizontal = 12.dp, vertical = 4.dp),
                modifier = Modifier.height(34.dp)
            ) {
                Icon(Icons.Default.CallSplit, contentDescription = null, modifier = Modifier.size(14.dp))
                Spacer(Modifier.width(4.dp))
                Text("Split Court", fontSize = 11.sp, fontWeight = FontWeight.Bold)
            }
        }
    }
}

@Composable
private fun SplitCourtDialog(
    court: StandaloneCourtItem,
    isSubmitting: Boolean,
    onConfirm: (SplitCourtRequest) -> Unit,
    onDismiss: () -> Unit
) {
    var splitType by remember { mutableStateOf("half") } // half, third, quarter
    var p1Name by remember { mutableStateOf("${court.name} - Half A") }
    var p1Price by remember { mutableStateOf("${(court.price * 0.6).toInt()}") }
    var p2Name by remember { mutableStateOf("${court.name} - Half B") }
    var p2Price by remember { mutableStateOf("${(court.price * 0.6).toInt()}") }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Split '${court.name}'") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text("Partition full court into independent sub-courts:", fontSize = 12.sp, color = PricingColors.AuthMuted)

                // Split type buttons
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    listOf("half" to "2 Halves", "third" to "3 Nets", "quarter" to "4 Zones").forEach { (type, label) ->
                        val isSelected = splitType == type
                        FilterChip(
                            selected = isSelected,
                            onClick = {
                                splitType = type
                                if (type == "half") {
                                    p1Name = "${court.name} - Pitch A"
                                    p2Name = "${court.name} - Pitch B"
                                }
                            },
                            label = { Text(label, fontSize = 11.sp) }
                        )
                    }
                }

                OutlinedTextField(
                    value = p1Name,
                    onValueChange = { p1Name = it },
                    label = { Text("Sub-court 1 Name") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth()
                )
                OutlinedTextField(
                    value = p1Price,
                    onValueChange = { p1Price = it },
                    label = { Text("Sub-court 1 Price (₹/hr)") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth()
                )

                OutlinedTextField(
                    value = p2Name,
                    onValueChange = { p2Name = it },
                    label = { Text("Sub-court 2 Name") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth()
                )
                OutlinedTextField(
                    value = p2Price,
                    onValueChange = { p2Price = it },
                    label = { Text("Sub-court 2 Price (₹/hr)") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth()
                )
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    val partitions = listOf(
                        PartitionConfig(name = p1Name, label = "Partition 1", price = p1Price.toDoubleOrNull() ?: 1000.0),
                        PartitionConfig(name = p2Name, label = "Partition 2", price = p2Price.toDoubleOrNull() ?: 1000.0)
                    )
                    onConfirm(
                        SplitCourtRequest(
                            courtId = court.id,
                            splitType = splitType,
                            partitions = partitions
                        )
                    )
                },
                enabled = !isSubmitting,
                colors = ButtonDefaults.buttonColors(containerColor = PricingColors.AuthAccent)
            ) {
                Text("Confirm Split")
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("Cancel") }
        }
    )
}
