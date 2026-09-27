package com.haraan.partner.whatsapp.ui

import androidx.lifecycle.repeatOnLifecycle
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Chat
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.HaraanEmptyState
import com.haraan.partner.ui.components.HaraanSegmentItem
import com.haraan.partner.ui.components.HaraanSegmentedControl
import com.haraan.partner.ui.theme.HaraanTheme
import com.haraan.partner.whatsapp.model.ConversationSummary
import com.haraan.partner.whatsapp.model.WhatsAppMetrics
import com.haraan.partner.whatsapp.viewmodel.WhatsAppDeskViewModel
import kotlinx.coroutines.delay

/** How often the inbox checks for new chats while it's on screen. */
private const val INBOX_POLL_MS = 15_000L

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun WhatsAppDeskDashboard(
    viewModel: WhatsAppDeskViewModel,
    venueId: Long?,
    onNavigateBack: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()
    val view = LocalView.current
    val snackbarHostState = remember { SnackbarHostState() }

    // One host for both screens, so a message raised in a chat is actually shown.
    LaunchedEffect(uiState.actionFeedbackMessage) {
        uiState.actionFeedbackMessage?.let {
            viewModel.clearFeedback()
            snackbarHostState.showSnackbar(it)
        }
    }

    if (venueId == null) {
        NoVenue(onNavigateBack)
        return
    }

    val lifecycle = androidx.lifecycle.compose.LocalLifecycleOwner.current.lifecycle
    LaunchedEffect(venueId) {
        viewModel.loadDashboard(venueId)
        // Polls only while the app is on screen, not in the background.
        lifecycle.repeatOnLifecycle(androidx.lifecycle.Lifecycle.State.STARTED) {
            while (true) {
                delay(INBOX_POLL_MS)
                if (viewModel.uiState.value.selectedConversation == null) viewModel.refreshInbox(venueId)
            }
        }
    }

    uiState.selectedConversation?.let { conv ->
        ConversationDetailScreen(
            viewModel = viewModel,
            venueId = venueId,
            conversation = conv,
            snackbarHostState = snackbarHostState,
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
                        Text("WhatsApp Desk", fontSize = 17.sp, fontWeight = FontWeight.Bold, color = HaraanTheme.colors.textPrimary)
                        Text(
                            "Customers who message your venue on WhatsApp",
                            fontSize = 11.sp,
                            color = HaraanTheme.colors.textSecondary
                        )
                    }
                },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = HaraanTheme.colors.textPrimary)
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = HaraanTheme.colors.surfaceDefault)
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .background(HaraanTheme.colors.canvas)
        ) {
            TodayLine(uiState.metrics)

            OutlinedTextField(
                value = uiState.searchQuery,
                onValueChange = { viewModel.onSearchQueryChanged(venueId, it) },
                placeholder = { Text("Search name, number or message", fontSize = 13.sp) },
                leadingIcon = { Icon(Icons.Filled.Search, contentDescription = null, tint = HaraanTheme.colors.textSecondary) },
                modifier = Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 4.dp),
                shape = HaraanTheme.shapes.medium,
                singleLine = true,
                colors = OutlinedTextFieldDefaults.colors(
                    focusedContainerColor = Color.White,
                    unfocusedContainerColor = Color.White,
                    focusedBorderColor = WhatsAppDeskColors.Action,
                    unfocusedBorderColor = HaraanTheme.colors.borderSubtle
                )
            )

            HaraanSegmentedControl(
                items = listOf(
                    HaraanSegmentItem("all", "All"),
                    HaraanSegmentItem("needs_action", "To reply", uiState.metrics.needsActionCount),
                    HaraanSegmentItem("holds", "On hold", uiState.metrics.activeHoldsCount),
                    HaraanSegmentItem("converted", "Booked")
                ),
                selectedKey = uiState.selectedTab,
                onItemSelected = { key ->
                    Haptics.tick(view)
                    viewModel.selectTab(venueId, key)
                },
                modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
            )

            when {
                uiState.isLoading -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = WhatsAppDeskColors.Action, strokeWidth = 2.dp)
                }
                uiState.loadError != null && uiState.conversations.isEmpty() -> HaraanEmptyState(
                    icon = Icons.Filled.Chat,
                    title = "Couldn’t load chats",
                    description = uiState.loadError ?: ""
                )
                uiState.conversations.isEmpty() -> HaraanEmptyState(
                    icon = Icons.Filled.Chat,
                    title = if (uiState.selectedTab == "all" && uiState.searchQuery.isBlank()) "No WhatsApp chats yet" else "Nothing here",
                    description = if (uiState.selectedTab == "all" && uiState.searchQuery.isBlank()) {
                        "When a customer who has booked with you messages Haraan on WhatsApp, the chat lands here."
                    } else {
                        "No chats match this filter."
                    }
                )
                else -> LazyColumn(Modifier.fillMaxWidth().weight(1f)) {
                    items(uiState.conversations, key = { it.id }) { conv ->
                        ConversationRowItem(conv) {
                            Haptics.tick(view)
                            viewModel.selectConversation(venueId, conv)
                        }
                        HorizontalDivider(color = HaraanTheme.colors.borderHairline, thickness = 0.5.dp)
                    }
                }
            }
        }
    }
}

/** Today at a glance, as one line of text rather than a row of boxes. */
@Composable
private fun TodayLine(m: WhatsAppMetrics) {
    val parts = buildList {
        add(if (m.needsActionCount == 1) "1 to reply" else "${m.needsActionCount} to reply")
        add("${m.activeHoldsCount} on hold")
        add("${m.convertedTodayCount} booked today")
        if (m.totalDeskRevenueToday > 0) add(DeskTime.rupees(m.totalDeskRevenueToday))
    }
    Text(
        parts.joinToString("  ·  "),
        fontSize = 12.sp,
        color = HaraanTheme.colors.textSecondary,
        modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp)
    )
}

@Composable
private fun NoVenue(onBack: () -> Unit) {
    Column(Modifier.fillMaxSize().background(HaraanTheme.colors.canvas)) {
        IconButton(onClick = onBack, modifier = Modifier.padding(8.dp)) {
            Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = HaraanTheme.colors.textPrimary)
        }
        HaraanEmptyState(
            icon = Icons.Filled.Chat,
            title = "No venue selected",
            description = "The WhatsApp Desk works per venue. Pick one of your venues first."
        )
    }
}

@Composable
fun ConversationRowItem(conv: ConversationSummary, onClick: () -> Unit) {
    val unread = conv.unreadCount > 0
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
            .background(Color.White)
            .padding(horizontal = 16.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        val initial = (conv.customerName ?: "").trim().firstOrNull()?.uppercaseChar()?.toString() ?: "#"
        Box(
            modifier = Modifier.size(42.dp).clip(CircleShape).background(HaraanTheme.colors.surfaceSubtle),
            contentAlignment = Alignment.Center
        ) {
            Text(initial, fontWeight = FontWeight.SemiBold, fontSize = 16.sp, color = HaraanTheme.colors.slateDark)
        }

        Spacer(Modifier.width(12.dp))

        Column(Modifier.weight(1f)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = conv.customerName ?: conv.phoneNumber,
                    fontSize = 14.sp,
                    fontWeight = if (unread) FontWeight.Bold else FontWeight.SemiBold,
                    color = HaraanTheme.colors.textPrimary,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f)
                )
                Text(
                    text = DeskTime.listStamp(conv.lastMessageAt),
                    fontSize = 11.sp,
                    color = if (unread) WhatsAppDeskColors.Action else HaraanTheme.colors.textSecondary
                )
            }
            Spacer(Modifier.height(2.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                StatusTag(conv.status)
                Text(
                    text = conv.lastMessagePreview ?: "",
                    fontSize = 13.sp,
                    color = if (unread) HaraanTheme.colors.textPrimary else HaraanTheme.colors.textSecondary,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f)
                )
                if (unread) {
                    Box(
                        Modifier
                            .padding(start = 8.dp)
                            .defaultMinSize(minWidth = 20.dp, minHeight = 20.dp)
                            .clip(CircleShape)
                            .background(WhatsAppDeskColors.Action)
                            .padding(horizontal = 5.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Text("${conv.unreadCount}", color = Color.White, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }
        }
    }
}

@Composable
private fun StatusTag(status: String) {
    val (label, color) = when (status) {
        "hold_active" -> "On hold · " to WhatsAppDeskColors.AmberHold
        "converted" -> "Booked · " to WhatsAppDeskColors.ConvertedGreen
        else -> return
    }
    Text(label, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = color)
}
