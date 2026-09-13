package com.haraan.partner.pricing.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.pricing.viewmodel.PricingMatrixViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PricingMatrixDashboard(
    viewModel: PricingMatrixViewModel,
    venueId: Long,
    availableCourts: List<Pair<Long, String>>,
    onNavigateBack: () -> Unit
) {
    val state by viewModel.uiState.collectAsState()
    val snackbarHostState = remember { SnackbarHostState() }

    LaunchedEffect(venueId) {
        val initialCourt = availableCourts.firstOrNull()?.first ?: 1L
        viewModel.loadAll(venueId, initialCourt)
    }

    LaunchedEffect(state.errorMessage) {
        state.errorMessage?.let {
            snackbarHostState.showSnackbar(it)
            viewModel.clearMessages()
        }
    }

    LaunchedEffect(state.successMessage) {
        state.successMessage?.let {
            snackbarHostState.showSnackbar(it)
            viewModel.clearMessages()
        }
    }

    // Overlay: Create Rule Sheet
    if (state.showCreateRuleSheet) {
        PricingRuleEditorSheet(
            availableCourts = availableCourts,
            isSubmitting = state.isSubmitting,
            initialCourtId = state.selectedCourtId,
            initialHour = state.selectedSlot?.hour,
            onSubmit = { req -> viewModel.createRule(venueId, req) },
            onDismiss = { viewModel.setShowCreateRuleSheet(false) }
        )
        return
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text("Dynamic Pricing & Courts", fontSize = 18.sp, fontWeight = FontWeight.Bold)
                        Text("Rate matrix, rules & court partition manager", fontSize = 11.sp, color = PricingColors.AuthMuted)
                    }
                },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.Default.ArrowBack, contentDescription = "Back")
                    }
                },
                actions = {
                    if (state.offlineCount > 0) {
                        Surface(
                            shape = RoundedCornerShape(12.dp),
                            color = PricingColors.AMBER.copy(alpha = 0.15f),
                            modifier = Modifier.padding(end = 4.dp)
                        ) {
                            Row(
                                modifier = Modifier
                                    .clickable { viewModel.syncOffline(venueId) }
                                    .padding(horizontal = 8.dp, vertical = 4.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(Icons.Default.Sync, contentDescription = null, tint = PricingColors.AMBER, modifier = Modifier.size(14.dp))
                                Spacer(Modifier.width(4.dp))
                                Text("${state.offlineCount} Queued", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = PricingColors.AMBER)
                            }
                        }
                    }
                    IconButton(onClick = { viewModel.loadAll(venueId, state.selectedCourtId) }) {
                        Icon(Icons.Default.Refresh, contentDescription = "Refresh")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = PricingColors.SurfaceWhite)
            )
        },
        snackbarHost = { SnackbarHost(snackbarHostState) },
        containerColor = PricingColors.AuthPageBg
    ) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
        ) {
            // KPI Summary Row
            Card(
                shape = RoundedCornerShape(0.dp),
                colors = CardDefaults.cardColors(containerColor = PricingColors.SurfaceWhite),
                modifier = Modifier.fillMaxWidth()
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 12.dp),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    KpiStatItem("AVG RATE", "₹${state.metrics.averageHourlyRate}/hr")
                    KpiStatItem("RANGE", "₹${state.metrics.minRate} - ₹${state.metrics.maxRate}")
                    KpiStatItem("ACTIVE RULES", "${state.metrics.activeRulesCount}")
                    KpiStatItem("COMPOSITE", "${state.metrics.compositeCourtsCount}")
                }
            }

            HorizontalDivider(color = PricingColors.Hairline)

            // Tab Bar
            ScrollableTabRow(
                selectedTabIndex = state.activeTab,
                containerColor = PricingColors.SurfaceWhite,
                edgePadding = 16.dp,
                modifier = Modifier.fillMaxWidth()
            ) {
                Tab(
                    selected = state.activeTab == 0,
                    onClick = { viewModel.setActiveTab(0) },
                    text = { Text("Rate Matrix", fontWeight = FontWeight.SemiBold, fontSize = 13.sp) }
                )
                Tab(
                    selected = state.activeTab == 1,
                    onClick = { viewModel.setActiveTab(1) },
                    text = { Text("Rules (${state.rules.size})", fontWeight = FontWeight.SemiBold, fontSize = 13.sp) }
                )
                Tab(
                    selected = state.activeTab == 2,
                    onClick = { viewModel.setActiveTab(2) },
                    text = { Text("Split / Merge", fontWeight = FontWeight.SemiBold, fontSize = 13.sp) }
                )
                Tab(
                    selected = state.activeTab == 3,
                    onClick = { viewModel.setActiveTab(3) },
                    text = {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text("Yield", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
                            if (state.metrics.topRecommendations.isNotEmpty()) {
                                Spacer(Modifier.width(4.dp))
                                Surface(
                                    shape = RoundedCornerShape(8.dp),
                                    color = PricingColors.GREEN
                                ) {
                                    Text(
                                        text = "${state.metrics.topRecommendations.size}",
                                        fontSize = 9.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = Color.White,
                                        modifier = Modifier.padding(horizontal = 5.dp, vertical = 1.dp)
                                    )
                                }
                            }
                        }
                    }
                )
            }

            // Tab Contents
            when (state.activeTab) {
                0 -> WeeklyPricingGridScreen(
                    matrix = state.matrix,
                    availableCourts = availableCourts.ifEmpty { listOf(1L to "Court 1", 2L to "Court 2") },
                    selectedCourtId = state.selectedCourtId,
                    onSelectCourt = { courtId -> viewModel.selectCourt(venueId, courtId) },
                    onSelectSlot = { slot, day -> viewModel.selectSlot(slot, day) },
                    onNewRule = { viewModel.setShowCreateRuleSheet(true) }
                )
                1 -> PricingRulesListScreen(
                    rules = state.rules,
                    onToggleRule = { ruleId -> viewModel.toggleRule(venueId, ruleId) },
                    onDeleteRule = { ruleId -> viewModel.deleteRule(venueId, ruleId) },
                    onNewRule = { viewModel.setShowCreateRuleSheet(true) }
                )
                2 -> CourtSplitMergeScreen(
                    hierarchy = state.hierarchy,
                    isSubmitting = state.isSubmitting,
                    onSplitCourt = { req -> viewModel.splitCourt(venueId, req) },
                    onMergeCourts = { courtId -> viewModel.mergeCourts(venueId, courtId) }
                )
                3 -> YieldRecommendationsScreen(
                    recommendations = state.metrics.topRecommendations,
                    isSubmitting = state.isSubmitting,
                    onApplyRecommendation = { rec -> viewModel.applyRecommendation(venueId, rec) }
                )
            }
        }
    }

    // Slot Rate Inspector Dialog
    if (state.showSlotRateDialog && state.selectedSlot != null) {
        val s = state.selectedSlot!!
        AlertDialog(
            onDismissRequest = { viewModel.dismissSlotDialog() },
            title = { Text("Slot Rate: ${s.timeLabel} (${state.selectedSlotDay?.replaceFirstChar { it.uppercase() }})") },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text("Effective Rate: ₹${s.rate}", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = PricingColors.AuthInk)
                    Text("Base Rack Rate: ₹${s.baseRate}", fontSize = 13.sp, color = PricingColors.AuthMuted)
                    s.ruleName?.let {
                        Text("Active Rule: $it", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = PricingColors.AuthAccent)
                    }
                    if (s.isPeak) {
                        Text("Peak hour modifier is applied.", fontSize = 11.sp, color = PricingColors.RED)
                    }
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        viewModel.dismissSlotDialog()
                        viewModel.setShowCreateRuleSheet(true)
                    }
                ) {
                    Text("Set Custom Rule")
                }
            },
            dismissButton = {
                TextButton(onClick = { viewModel.dismissSlotDialog() }) { Text("Close") }
            }
        )
    }
}

@Composable
private fun KpiStatItem(label: String, value: String) {
    Column {
        Text(label, fontSize = 9.sp, fontWeight = FontWeight.SemiBold, color = PricingColors.AuthMuted)
        Text(value, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = PricingColors.AuthInk)
    }
}
