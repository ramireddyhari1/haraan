package com.haraan.partner.whatsapp.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.components.HaraanEmptyState
import com.haraan.partner.ui.components.HaraanSegmentItem
import com.haraan.partner.ui.components.HaraanSegmentedControl
import com.haraan.partner.ui.components.HaraanStatusBadge
import com.haraan.partner.ui.components.HaraanStatusType
import com.haraan.partner.ui.theme.HaraanTheme
import com.haraan.partner.ui.theme.haraanCard
import com.haraan.partner.whatsapp.model.ConversationSummary
import com.haraan.partner.whatsapp.viewmodel.WhatsAppDeskViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun WhatsAppDeskDashboard(
    viewModel: WhatsAppDeskViewModel,
    venueId: Long,
    onNavigateBack: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()

    LaunchedEffect(venueId) {
        viewModel.loadDashboard(venueId)
    }

    // Feedback Snackbar
    val snackbarHostState = remember { SnackbarHostState() }
    LaunchedEffect(uiState.actionFeedbackMessage) {
        uiState.actionFeedbackMessage?.let {
            snackbarHostState.showSnackbar(it)
            viewModel.clearFeedback()
        }
    }

    // If a conversation is selected, show detail screen
    uiState.selectedConversation?.let { conv ->
        ConversationDetailScreen(
            viewModel = viewModel,
            venueId = venueId,
            conversation = conv,
            onBack = { viewModel.selectConversation(venueId, null) }
        )
        return
    }

    Scaffold(
        snackbarHost = { SnackbarHost(snackbarHostState) },
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text(
                            "WhatsApp Partner Desk",
                            fontSize = 17.sp,
                            fontWeight = FontWeight.Bold,
                            color = HaraanTheme.colors.textPrimary
                        )
                        Text(
                            "1-Tap ${HaraanTheme.DEFAULT_HOLD_DURATION_MINUTES}-Min Hold & Fast Settlement",
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
                    IconButton(onClick = { viewModel.loadDashboard(venueId) }) {
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
            // Metrics Strip
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 12.dp, vertical = 8.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                DeskMetricCard(
                    title = "Inquiries",
                    value = "${uiState.metrics.activeWindowOpenCount}",
                    accentColor = HaraanTheme.colors.slateDark,
                    modifier = Modifier.weight(1f)
                )
                DeskMetricCard(
                    title = "${HaraanTheme.DEFAULT_HOLD_DURATION_MINUTES}m Holds",
                    value = "${uiState.metrics.activeHoldsCount}",
                    accentColor = HaraanTheme.colors.amberPrimary,
                    modifier = Modifier.weight(1f)
                )
                DeskMetricCard(
                    title = "Converted",
                    value = "${uiState.metrics.convertedTodayCount}",
                    accentColor = HaraanTheme.colors.emeraldPrimary,
                    modifier = Modifier.weight(1f)
                )
                DeskMetricCard(
                    title = "Desk Rev.",
                    value = "₹${uiState.metrics.totalDeskRevenueToday.toInt()}",
                    accentColor = HaraanTheme.colors.slateDark,
                    modifier = Modifier.weight(1.1f)
                )
            }

            // Search Bar
            OutlinedTextField(
                value = uiState.searchQuery,
                onValueChange = { viewModel.onSearchQueryChanged(venueId, it) },
                placeholder = { Text("Search by name, phone or message...", fontSize = 13.sp) },
                leadingIcon = {
                    Icon(
                        Icons.Filled.Search,
                        contentDescription = null,
                        tint = HaraanTheme.colors.textSecondary
                    )
                },
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 12.dp, vertical = 4.dp),
                shape = HaraanTheme.shapes.medium,
                singleLine = true,
                colors = OutlinedTextFieldDefaults.colors(
                    focusedContainerColor = Color.White,
                    unfocusedContainerColor = Color.White,
                    focusedBorderColor = HaraanTheme.colors.borderFocus,
                    unfocusedBorderColor = HaraanTheme.colors.borderSubtle
                )
            )

            Spacer(Modifier.height(4.dp))

            // Filter Tabs via HaraanSegmentedControl
            val segments = listOf(
                HaraanSegmentItem("all", "All"),
                HaraanSegmentItem("needs_action", "Action", uiState.metrics.activeWindowOpenCount),
                HaraanSegmentItem("holds", "${HaraanTheme.DEFAULT_HOLD_DURATION_MINUTES}m Holds", uiState.metrics.activeHoldsCount),
                HaraanSegmentItem("converted", "Converted", uiState.metrics.convertedTodayCount)
            )

            HaraanSegmentedControl(
                items = segments,
                selectedKey = uiState.selectedTab,
                onItemSelected = { key -> viewModel.selectTab(venueId, key) },
                modifier = Modifier.padding(horizontal = 12.dp, vertical = 4.dp)
            )

            // Conversations List
            if (uiState.isLoading) {
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = HaraanTheme.colors.slateDark)
                }
            } else if (uiState.conversations.isEmpty()) {
                HaraanEmptyState(
                    icon = Icons.Filled.Chat,
                    title = "No conversations in this filter",
                    description = "When customers message your venue desk on WhatsApp, their threads and 1-tap booking cards will appear here."
                )
            } else {
                LazyColumn(
                    modifier = Modifier
                        .fillMaxWidth()
                        .weight(1f)
                ) {
                    items(uiState.conversations) { conv ->
                        ConversationRowItem(
                            conv = conv,
                            onClick = { viewModel.selectConversation(venueId, conv) }
                        )
                        HorizontalDivider(
                            color = HaraanTheme.colors.borderHairline,
                            thickness = 0.5.dp
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun DeskMetricCard(
    title: String,
    value: String,
    accentColor: Color,
    modifier: Modifier = Modifier
) {
    Box(
        modifier = modifier
            .haraanCard(radius = 12.dp, elevation = 2.dp)
            .padding(vertical = 10.dp, horizontal = 8.dp),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(2.dp)
        ) {
            Text(
                text = title.uppercase(),
                style = HaraanTheme.typography.overline,
                color = HaraanTheme.colors.textSecondary,
                maxLines = 1
            )
            Text(
                text = value,
                style = HaraanTheme.typography.tnumMetricMedium,
                color = accentColor
            )
        }
    }
}

@Composable
fun ConversationRowItem(
    conv: ConversationSummary,
    onClick: () -> Unit
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
            .background(Color.White)
            .padding(horizontal = 14.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        // Avatar
        Box(
            modifier = Modifier
                .size(44.dp)
                .clip(CircleShape)
                .background(HaraanTheme.colors.surfaceSubtle),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                Icons.Filled.Person,
                contentDescription = null,
                tint = HaraanTheme.colors.slateDark,
                modifier = Modifier.size(24.dp)
            )
        }

        Spacer(Modifier.width(12.dp))

        // Name & Preview
        Column(modifier = Modifier.weight(1f)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = conv.customerName ?: conv.phoneNumber,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    color = HaraanTheme.colors.textPrimary,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Text(
                    text = conv.lastMessageAt?.takeLast(8) ?: "",
                    style = HaraanTheme.typography.caption,
                    color = HaraanTheme.colors.textSecondary
                )
            }

            Spacer(Modifier.height(2.dp))

            Text(
                text = conv.lastMessagePreview ?: "No messages yet",
                fontSize = 12.sp,
                color = if (conv.unreadCount > 0) HaraanTheme.colors.textPrimary else HaraanTheme.colors.textSecondary,
                fontWeight = if (conv.unreadCount > 0) FontWeight.SemiBold else FontWeight.Normal,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )

            Spacer(Modifier.height(6.dp))

            // Semantic status badge
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp)
            ) {
                if (conv.status == "hold_active") {
                    HaraanStatusBadge(
                        text = "Hold (${HaraanTheme.DEFAULT_HOLD_DURATION_MINUTES}m)",
                        type = HaraanStatusType.Hold
                    )
                } else if (conv.status == "converted") {
                    HaraanStatusBadge(
                        text = "Converted",
                        type = HaraanStatusType.Confirmed
                    )
                }

                if (conv.unreadCount > 0) {
                    Box(
                        modifier = Modifier
                            .size(18.dp)
                            .clip(CircleShape)
                            .background(HaraanTheme.colors.emeraldPrimary),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = "${conv.unreadCount}",
                            color = Color.White,
                            fontSize = 10.sp,
                            fontWeight = FontWeight.Bold
                        )
                    }
                }
            }
        }
    }
}