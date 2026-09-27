package com.haraan.partner.whatsapp.ui

import androidx.lifecycle.repeatOnLifecycle
import android.content.Intent
import android.net.Uri
import androidx.activity.compose.BackHandler
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ui.Haptics
import com.haraan.partner.whatsapp.model.ChatMessage
import com.haraan.partner.whatsapp.model.ConversationSummary
import com.haraan.partner.whatsapp.viewmodel.WhatsAppDeskViewModel
import kotlinx.coroutines.delay

/** How often an open chat checks for new messages (and for a paid link). */
private const val THREAD_POLL_MS = 6_000L

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ConversationDetailScreen(
    viewModel: WhatsAppDeskViewModel,
    venueId: Long,
    conversation: ConversationSummary,
    snackbarHostState: SnackbarHostState,
    onBack: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()
    val context = LocalContext.current
    val view = LocalView.current
    var inputText by remember { mutableStateOf("") }
    var showQuickReplies by remember { mutableStateOf(false) }
    var showPicker by remember { mutableStateOf(false) }

    // Back from a chat returns to the inbox, not out of the desk.
    BackHandler(onBack = onBack)

    // Live while it's open: new messages, and a payment landing, without a manual refresh.
    val lifecycle = androidx.lifecycle.compose.LocalLifecycleOwner.current.lifecycle
    LaunchedEffect(conversation.id) {
        // Only while the app is on screen, not in the background.
        lifecycle.repeatOnLifecycle(androidx.lifecycle.Lifecycle.State.STARTED) {
            while (true) {
                delay(THREAD_POLL_MS)
                viewModel.refreshOpenConversation(venueId)
            }
        }
    }

    val listState = rememberLazyListState()
    LaunchedEffect(uiState.messages.size) {
        if (uiState.messages.isNotEmpty()) listState.animateScrollToItem(uiState.messages.size - 1)
    }

    Scaffold(
        snackbarHost = { SnackbarHost(snackbarHostState) },
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text(
                            text = conversation.customerName ?: conversation.phoneNumber,
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.White
                        )
                        Text(
                            text = if (uiState.isWindowActive) conversation.phoneNumber
                            else "${conversation.phoneNumber} · replies closed (24h)",
                            fontSize = 11.sp,
                            color = Color.White.copy(alpha = 0.85f)
                        )
                    }
                },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = Color.White)
                    }
                },
                actions = {
                    IconButton(onClick = {
                        context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:${conversation.phoneNumber}")))
                    }) {
                        Icon(Icons.Filled.Call, contentDescription = "Call", tint = Color.White)
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = WhatsAppDeskColors.DarkTeal)
            )
        },
        bottomBar = {
            Composer(
                text = inputText,
                onTextChange = { inputText = it },
                windowOpen = uiState.isWindowActive,
                sending = uiState.isSending,
                hasReplies = uiState.quickReplies.isNotEmpty(),
                onQuickReplies = { showQuickReplies = true },
                onSend = {
                    Haptics.tick(view)
                    viewModel.sendMessage(venueId, inputText) { inputText = "" }
                },
                onCall = { context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:${conversation.phoneNumber}"))) }
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .background(Color(0xFFEFEAE2))
        ) {
            val hold = uiState.hold
            val suggestion = uiState.suggestion
            when {
                hold != null -> DeskHoldCard(
                    hold = hold,
                    secondsLeft = uiState.holdSecondsLeft,
                    totalSeconds = uiState.holdTotalSeconds,
                    busy = uiState.isActionLoading,
                    windowOpen = uiState.isWindowActive,
                    onSendLink = { viewModel.sendPaymentLink(venueId) },
                    onMarkPaid = { method -> viewModel.markPaid(venueId, method) },
                    onRelease = { viewModel.releaseHold(venueId) },
                    onHoldAgain = {
                        val courtId = hold.courtId
                        if (courtId != null) {
                            viewModel.holdSlot(venueId, courtId, hold.date, hold.startTime, hold.endTime)
                        } else {
                            showPicker = true
                        }
                    }
                )
                suggestion != null && suggestion.isComplete -> DeskSuggestionCard(
                    suggestion = suggestion,
                    busy = uiState.isActionLoading,
                    onHold = {
                        viewModel.holdSlot(venueId, suggestion.courtId!!, suggestion.date!!, suggestion.startTime!!, suggestion.endTime!!)
                    },
                    onChange = { showPicker = true },
                    onDismiss = { viewModel.dismissSuggestion() }
                )
                !uiState.isThreadLoading -> HoldSlotPrompt(onPick = { showPicker = true })
            }

            if (uiState.isThreadLoading && uiState.messages.isEmpty()) {
                Box(Modifier.fillMaxWidth().weight(1f), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = WhatsAppDeskColors.DarkTeal, strokeWidth = 2.dp)
                }
            } else {
                LazyColumn(
                    state = listState,
                    modifier = Modifier.fillMaxWidth().weight(1f).padding(horizontal = 12.dp),
                    contentPadding = PaddingValues(vertical = 8.dp),
                    verticalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    items(uiState.messages, key = { it.id }) { msg -> ChatBubbleItem(msg) }
                }
            }
        }
    }

    if (showPicker) {
        SlotPickerSheet(
            availability = uiState.availability,
            loading = uiState.availabilityLoading,
            error = uiState.availabilityError,
            busy = uiState.isActionLoading,
            initial = uiState.suggestion ?: uiState.hold?.let {
                com.haraan.partner.whatsapp.model.BookingSuggestion(null, it.courtId, it.courtName, it.date, it.startTime, it.endTime, it.amount)
            },
            onDateChange = { date -> viewModel.loadAvailability(venueId, date) },
            onHold = { courtId, date, start, end ->
                viewModel.holdSlot(venueId, courtId, date, start, end) { showPicker = false }
            },
            onDismiss = { showPicker = false }
        )
    }

    if (showQuickReplies) {
        ModalBottomSheet(onDismissRequest = { showQuickReplies = false }, containerColor = Color.White) {
            Column(Modifier.fillMaxWidth().padding(horizontal = 16.dp).padding(bottom = 24.dp)) {
                Text("Quick replies", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = WhatsAppDeskColors.TextPrimary)
                Text("Filled in with your venue’s details. Edit before sending if you need to.", fontSize = 12.sp, color = WhatsAppDeskColors.TextSecondary)
                Spacer(Modifier.height(10.dp))
                uiState.quickReplies.forEach { reply ->
                    Column(
                        Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(10.dp))
                            .clickable {
                                Haptics.tick(view)
                                inputText = viewModel.render(reply)
                                showQuickReplies = false
                            }
                            .padding(vertical = 10.dp, horizontal = 4.dp)
                    ) {
                        Text(reply.title, fontWeight = FontWeight.SemiBold, fontSize = 14.sp, color = WhatsAppDeskColors.TextPrimary)
                        Text(viewModel.render(reply), fontSize = 12.sp, color = WhatsAppDeskColors.TextSecondary, maxLines = 2)
                    }
                    HorizontalDivider(color = WhatsAppDeskColors.SlateBorder, thickness = 0.5.dp)
                }
            }
        }
    }
}

@Composable
private fun Composer(
    text: String,
    onTextChange: (String) -> Unit,
    windowOpen: Boolean,
    sending: Boolean,
    hasReplies: Boolean,
    onQuickReplies: () -> Unit,
    onSend: () -> Unit,
    onCall: () -> Unit
) {
    Surface(color = Color.White, shadowElevation = 8.dp) {
        if (!windowOpen) {
            // WhatsApp only delivers free text within 24h of the customer's last message.
            Row(
                Modifier.fillMaxWidth().navigationBarsPadding().padding(horizontal = 16.dp, vertical = 12.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    "The customer hasn’t messaged in 24 hours, so WhatsApp won’t deliver a reply. They can message you again any time.",
                    fontSize = 12.sp,
                    color = WhatsAppDeskColors.TextSecondary,
                    modifier = Modifier.weight(1f)
                )
                TextButton(onClick = onCall) { Text("Call", color = WhatsAppDeskColors.Action, fontWeight = FontWeight.SemiBold) }
            }
            return@Surface
        }

        Row(
            modifier = Modifier.fillMaxWidth().navigationBarsPadding().padding(horizontal = 8.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            if (hasReplies) {
                IconButton(onClick = onQuickReplies) {
                    Icon(Icons.Filled.FlashOn, contentDescription = "Quick replies", tint = WhatsAppDeskColors.Action)
                }
            }
            OutlinedTextField(
                value = text,
                onValueChange = onTextChange,
                placeholder = { Text("Message", fontSize = 14.sp) },
                modifier = Modifier.weight(1f).padding(horizontal = 4.dp),
                shape = RoundedCornerShape(22.dp),
                maxLines = 4,
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = WhatsAppDeskColors.Action,
                    unfocusedBorderColor = WhatsAppDeskColors.SlateBorder
                )
            )
            IconButton(
                onClick = onSend,
                enabled = text.isNotBlank() && !sending,
                modifier = Modifier
                    .size(44.dp)
                    .clip(CircleShape)
                    .background(if (text.isNotBlank()) WhatsAppDeskColors.Action else WhatsAppDeskColors.SlateBorder)
            ) {
                if (sending) {
                    CircularProgressIndicator(Modifier.size(18.dp), color = Color.White, strokeWidth = 2.dp)
                } else {
                    Icon(Icons.AutoMirrored.Filled.Send, contentDescription = "Send", tint = Color.White, modifier = Modifier.size(18.dp))
                }
            }
        }
    }
}

@Composable
fun ChatBubbleItem(msg: ChatMessage) {
    // Staff-only lines (holds, releases) sit in the middle, like WhatsApp's own notices.
    if (msg.senderType == "system" && msg.deliveryStatus == "internal") {
        Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
            Text(
                msg.body,
                fontSize = 11.sp,
                color = WhatsAppDeskColors.TextSecondary,
                textAlign = TextAlign.Center,
                modifier = Modifier
                    .clip(RoundedCornerShape(8.dp))
                    .background(Color(0xFFFFF8E1))
                    .padding(horizontal = 10.dp, vertical = 5.dp)
                    .widthIn(max = 300.dp)
            )
        }
        return
    }

    val isOutbound = msg.direction == "outbound"
    val failed = msg.deliveryStatus == "failed"

    Column(Modifier.fillMaxWidth(), horizontalAlignment = if (isOutbound) Alignment.End else Alignment.Start) {
        Surface(
            shape = RoundedCornerShape(
                topStart = 12.dp, topEnd = 12.dp,
                bottomStart = if (isOutbound) 12.dp else 2.dp,
                bottomEnd = if (isOutbound) 2.dp else 12.dp
            ),
            color = if (isOutbound) WhatsAppDeskColors.ChatBubblePartner else WhatsAppDeskColors.ChatBubbleCustomer,
            shadowElevation = 0.5.dp,
            modifier = Modifier.widthIn(max = 290.dp)
        ) {
            Column(Modifier.padding(horizontal = 10.dp, vertical = 7.dp)) {
                if (msg.messageType == "ticket_card") {
                    Text("BOOKING CONFIRMED", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = WhatsAppDeskColors.ConvertedGreen)
                    Spacer(Modifier.height(3.dp))
                }
                Text(msg.body, fontSize = 14.sp, color = WhatsAppDeskColors.TextPrimary, lineHeight = 19.sp)
                Row(Modifier.align(Alignment.End), verticalAlignment = Alignment.CenterVertically) {
                    Text(DeskTime.clock(msg.createdAt), fontSize = 10.sp, color = WhatsAppDeskColors.TextSecondary)
                    if (isOutbound) {
                        Spacer(Modifier.width(3.dp))
                        Icon(
                            imageVector = if (failed) Icons.Filled.ErrorOutline else Icons.Filled.Done,
                            contentDescription = if (failed) "Not delivered" else "Sent",
                            tint = if (failed) WhatsAppDeskColors.Danger else WhatsAppDeskColors.TextSecondary,
                            modifier = Modifier.size(12.dp)
                        )
                    }
                }
            }
        }
        if (failed) {
            Text("Not delivered", fontSize = 10.sp, color = WhatsAppDeskColors.Danger, modifier = Modifier.padding(top = 2.dp, end = 4.dp))
        }
    }
}
