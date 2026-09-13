package com.haraan.partner.recurring.ui

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
import com.haraan.partner.recurring.viewmodel.StandingContractViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun StandingSlotsMasterScreen(
    viewModel: StandingContractViewModel,
    venueId: Long,
    availableCourts: List<Pair<Long, String>>,
    onNavigateBack: () -> Unit
) {
    val state by viewModel.uiState.collectAsState()
    val snackbarHostState = remember { SnackbarHostState() }

    LaunchedEffect(venueId) {
        viewModel.loadData(venueId)
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

    // Overlay: Create Contract Screen
    if (state.showCreateScreen) {
        CreateStandingContractScreen(
            availableCourts = availableCourts.ifEmpty { listOf(1L to "Court 1", 2L to "Court 2") },
            conflictReport = state.conflictReport,
            isCheckingConflicts = state.isCheckingConflicts,
            isSubmitting = state.isSubmitting,
            onCheckConflicts = { courtId, weekday, startTime, endTime, startDate ->
                viewModel.checkConflicts(venueId, courtId, weekday, startTime, endTime, startDate)
            },
            onSubmit = { req ->
                viewModel.createContract(venueId, req) {
                    viewModel.setShowCreateScreen(false)
                }
            },
            onNavigateBack = { viewModel.setShowCreateScreen(false) }
        )
        return
    }

    // Overlay: Contract Detail Screen
    if (state.showDetailScreen && state.selectedContract != null) {
        ContractDetailScreen(
            contract = state.selectedContract!!,
            availableCourts = availableCourts.ifEmpty { listOf(1L to "Court 1", 2L to "Court 2") },
            isSubmitting = state.isSubmitting,
            onSkipSession = { date, reason -> viewModel.skipSession(venueId, state.selectedContract!!.id, date, reason) },
            onPauseContract = { reason -> viewModel.pauseContract(venueId, state.selectedContract!!.id, reason) },
            onResumeContract = { viewModel.resumeContract(venueId, state.selectedContract!!.id) },
            onTerminateContract = { reason -> viewModel.terminateContract(venueId, state.selectedContract!!.id, reason) },
            onTransferCourt = { courtId -> viewModel.transferCourt(venueId, state.selectedContract!!.id, courtId) },
            onMarkAttendance = { sessionId, status -> viewModel.recordAttendance(venueId, state.selectedContract!!.id, sessionId, status) },
            onRecordPayment = { amt, type, method, notes -> viewModel.recordPayment(venueId, state.selectedContract!!.id, amt, type, method, notes) },
            onNavigateBack = { viewModel.setShowDetailScreen(false) }
        )
        return
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text("Standing Slots & Contracts", fontSize = 18.sp, fontWeight = FontWeight.Bold)
                        Text("Recurring groups & academy batches", fontSize = 11.sp, color = RecurringColors.AuthMuted)
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
                            color = RecurringColors.AMBER.copy(alpha = 0.15f),
                            modifier = Modifier.padding(end = 4.dp)
                        ) {
                            Row(
                                modifier = Modifier
                                    .clickable { viewModel.syncOffline(venueId) }
                                    .padding(horizontal = 8.dp, vertical = 4.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(Icons.Default.Sync, contentDescription = null, tint = RecurringColors.AMBER, modifier = Modifier.size(14.dp))
                                Spacer(Modifier.width(4.dp))
                                Text("${state.offlineCount} Queued", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = RecurringColors.AMBER)
                            }
                        }
                    }
                    IconButton(onClick = { viewModel.loadData(venueId, forceRefresh = true) }) {
                        Icon(Icons.Default.Refresh, contentDescription = "Refresh")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = RecurringColors.SurfaceWhite)
            )
        },
        snackbarHost = { SnackbarHost(snackbarHostState) },
        containerColor = RecurringColors.AuthPageBg
    ) { innerPadding ->
        Column(modifier = Modifier.fillMaxSize().padding(innerPadding)) {
            // Coordinator Tab Bar: Dashboard vs Contracts List
            TabRow(
                selectedTabIndex = state.activeTab,
                containerColor = RecurringColors.SurfaceWhite,
                modifier = Modifier.fillMaxWidth()
            ) {
                Tab(
                    selected = state.activeTab == 0,
                    onClick = { viewModel.setActiveTab(0) },
                    text = {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Dashboard, contentDescription = null, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("Dashboard", fontWeight = FontWeight.SemiBold)
                        }
                    }
                )
                Tab(
                    selected = state.activeTab == 1,
                    onClick = { viewModel.setActiveTab(1) },
                    text = {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.CalendarMonth, contentDescription = null, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("All Contracts (${state.contracts.size})", fontWeight = FontWeight.SemiBold)
                        }
                    }
                )
            }

            // Main Content Area
            if (state.activeTab == 0) {
                StandingSlotsDashboard(
                    metrics = state.metrics,
                    todaySessions = state.todaySessions,
                    atRiskAlerts = state.atRiskAlerts,
                    onNewContract = { viewModel.setShowCreateScreen(true) },
                    onViewAllContracts = { viewModel.setActiveTab(1) },
                    onSelectContract = { contractId -> viewModel.selectContract(venueId, contractId) },
                    onMarkAttendance = { contractId, sessionId, status ->
                        viewModel.recordAttendance(venueId, contractId, sessionId, status)
                    }
                )
            } else {
                StandingSlotsListScreen(
                    contracts = state.contracts,
                    selectedStatus = state.selectedStatus,
                    searchQuery = state.searchQuery,
                    onSearchChange = { viewModel.setSearchQuery(venueId, it) },
                    onStatusChange = { viewModel.setStatusFilter(venueId, it) },
                    onSelectContract = { contractId -> viewModel.selectContract(venueId, contractId) },
                    onNewContract = { viewModel.setShowCreateScreen(true) }
                )
            }
        }
    }
}
