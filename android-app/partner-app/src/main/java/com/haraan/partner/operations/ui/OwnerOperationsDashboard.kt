package com.haraan.partner.operations.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.operations.viewmodel.OwnerOperationsViewModel
import com.haraan.partner.ui.components.HaraanKpiCard
import com.haraan.partner.ui.components.HaraanSectionHeader
import com.haraan.partner.ui.components.HaraanSegmentItem
import com.haraan.partner.ui.components.HaraanSegmentedControl
import com.haraan.partner.ui.theme.HaraanTheme
import com.haraan.partner.ui.theme.haraanCard

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun OwnerOperationsDashboard(
    viewModel: OwnerOperationsViewModel,
    venueId: Long,
    onNavigateBack: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()

    LaunchedEffect(venueId) {
        viewModel.loadOverview(venueId)
    }

    val snackbarHostState = remember { SnackbarHostState() }
    LaunchedEffect(uiState.actionMessage) {
        uiState.actionMessage?.let {
            snackbarHostState.showSnackbar(it)
            viewModel.clearActionMessage()
        }
    }

    Scaffold(
        snackbarHost = { SnackbarHost(snackbarHostState) },
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text(
                            "Operations Center",
                            fontSize = 17.sp,
                            fontWeight = FontWeight.Bold,
                            color = HaraanTheme.colors.textPrimary
                        )
                        Text(
                            "Real-Time Revenue, Occupancy & AI Suggestions",
                            fontSize = 11.sp,
                            color = HaraanTheme.colors.textSecondary
                        )
                    }
                },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(
                            Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = "Back",
                            tint = HaraanTheme.colors.textPrimary
                        )
                    }
                },
                actions = {
                    IconButton(onClick = { viewModel.loadOverview(venueId) }) {
                        Icon(
                            Icons.Filled.Refresh,
                            contentDescription = "Refresh",
                            tint = HaraanTheme.colors.textPrimary
                        )
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = HaraanTheme.colors.surfaceDefault
                )
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .background(HaraanTheme.colors.canvas)
        ) {
            // Segmented Control Tabs
            val segments = listOf(
                HaraanSegmentItem("0", "Revenue"),
                HaraanSegmentItem("1", "Heatmap"),
                HaraanSegmentItem("2", "Staff"),
                HaraanSegmentItem("3", "Funnel"),
                HaraanSegmentItem("4", "AI")
            )

            HaraanSegmentedControl(
                items = segments,
                selectedKey = uiState.selectedTab.toString(),
                onItemSelected = { key -> viewModel.selectTab(key.toIntOrNull() ?: 0) },
                modifier = Modifier.padding(horizontal = 14.dp, vertical = 8.dp)
            )

            if (uiState.isLoading) {
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = HaraanTheme.colors.slateDark)
                }
            } else {
                when (uiState.selectedTab) {
                    0 -> RevenueOverviewTab(uiState.data.revenue)
                    1 -> OccupancyHeatmapGrid(uiState.data.heatmap)
                    2 -> StaffPerformanceView(uiState.data.staff)
                    3 -> RevenueLeakageView(
                        funnel = uiState.data.funnel,
                        alerts = uiState.data.alerts,
                        onResolveAlert = { viewModel.resolveAlert(venueId, it) }
                    )
                    4 -> AiSuggestionsView(
                        suggestions = uiState.data.suggestions,
                        onApply = { viewModel.applySuggestion(venueId, it) },
                        onDismiss = { viewModel.dismissSuggestion(venueId, it) }
                    )
                }
            }
        }
    }
}

@Composable
fun RevenueOverviewTab(revenue: com.haraan.partner.operations.model.RevenueOverview) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 14.dp, vertical = 6.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        // Today vs Yesterday
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            HaraanKpiCard(
                title = "Today's Revenue",
                value = "₹${revenue.todayRevenue.toInt()}",
                trendPercent = revenue.dayOverDayGrowthPct,
                trendLabel = "DoD",
                subtitle = "Active Cash & Digital",
                icon = Icons.Filled.Payments,
                iconTint = HaraanTheme.colors.emeraldPrimary,
                modifier = Modifier.weight(1f)
            )
            HaraanKpiCard(
                title = "Yesterday",
                value = "₹${revenue.yesterdayRevenue.toInt()}",
                subtitle = "Closed Bookings",
                icon = Icons.Filled.Today,
                iconTint = HaraanTheme.colors.textSecondary,
                modifier = Modifier.weight(1f)
            )
        }

        // Run-rate and month forecast
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            HaraanKpiCard(
                title = "Month-to-Date",
                value = "₹${revenue.monthToDateRevenue.toInt()}",
                subtitle = "Active Month Total",
                icon = Icons.Filled.CalendarMonth,
                iconTint = HaraanTheme.colors.textSecondary,
                modifier = Modifier.weight(1f)
            )
            HaraanKpiCard(
                title = "Projected Month-End",
                value = "₹${revenue.projectedMonthRevenue.toInt()}",
                subtitle = "AI Run-rate Forecast",
                icon = Icons.Filled.TrendingUp,
                iconTint = HaraanTheme.colors.slateDark,
                modifier = Modifier.weight(1f)
            )
        }

        // Standing Contract MRR Card
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .haraanCard(radius = 16.dp, elevation = 2.dp)
                .padding(16.dp)
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text(
                    text = "CONTRACTED RECURRING MRR",
                    style = HaraanTheme.typography.overline,
                    color = HaraanTheme.colors.textSecondary
                )
                Text(
                    text = "₹${revenue.standingContractsMrr.toInt()}",
                    style = HaraanTheme.typography.tnumMetricLarge,
                    color = Color(0xFF6366F1) // Indigo recurring accent
                )
                Text(
                    text = "Guaranteed recurring revenue from active standing slot contracts",
                    style = HaraanTheme.typography.caption,
                    color = HaraanTheme.colors.textMuted
                )
            }
        }

        // Channel Breakdown
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .haraanCard(radius = 16.dp, elevation = 2.dp)
                .padding(16.dp)
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                HaraanSectionHeader(title = "Revenue by Channel")

                revenue.channelBreakdown.forEach { (channel, data) ->
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(vertical = 5.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = channel.replaceFirstChar { it.uppercase() },
                            style = HaraanTheme.typography.bodyMedium,
                            fontWeight = FontWeight.Medium,
                            color = HaraanTheme.colors.textPrimary
                        )
                        Text(
                            text = "₹${data.amount.toInt()} · ${data.count} bookings",
                            style = HaraanTheme.typography.tnumCurrency,
                            color = HaraanTheme.colors.textPrimary
                        )
                    }
                    HorizontalDivider(
                        color = HaraanTheme.colors.borderHairline,
                        thickness = 0.5.dp
                    )
                }
            }
        }

        Spacer(Modifier.height(16.dp))
    }
}