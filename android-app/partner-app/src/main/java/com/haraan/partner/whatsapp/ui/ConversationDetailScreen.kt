package com.haraan.partner.whatsapp.ui

import android.content.Intent
import android.net.Uri
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.whatsapp.model.ChatMessage
import com.haraan.partner.whatsapp.model.ConversationSummary
import com.haraan.partner.whatsapp.viewmodel.WhatsAppDeskViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ConversationDetailScreen(
    viewModel: WhatsAppDeskViewModel,
    venueId: Long,
    conversation: ConversationSummary,
    onBack: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()
    val context = LocalContext.current
    var inputText by remember { mutableStateOf("") }
    var showQuickRepliesSheet by remember { mutableStateOf(false) }

    val listState = rememberLazyListState()
    LaunchedEffect(uiState.messages.size) {
        if (uiState.messages.isNotEmpty()) {
            listState.animateScrollToItem(uiState.messages.size - 1)
        }
    }

    Scaffold(
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
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(
                                text = conversation.phoneNumber,
                                fontSize = 11.sp,
                                color = Color.White.copy(alpha = 0.85f)
                            )
                            Spacer(Modifier.width(8.dp))
                            if (conversation.isWindowActive) {
                                Text(
                                    text = "• 24h window active",
                                    fontSize = 11.sp,
                                    color = Color(0xFFBBF7D0)
                                )
                            }
                        }
                    }
                },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = Color.White)
                    }
                },
                actions = {
                    IconButton(onClick = {
                        val dialIntent = Intent(Intent.ACTION_DIAL, Uri.parse("tel:${conversation.phoneNumber}"))
                        context.startActivity(dialIntent)
                    }) {
                        Icon(Icons.Filled.Call, contentDescription = "Call", tint = Color.White)
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = WhatsAppDeskColors.Teal
                )
            )
        },
        bottomBar = {
            Surface(
                color = Color.White,
                tonalElevation = 8.dp,
                shadowElevation = 8.dp
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 8.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    IconButton(onClick = { showQuickRepliesSheet = true }) {
                        Icon(
                            imageVector = Icons.Filled.FlashOn,
                            contentDescription = "Quick Replies",
                            tint = WhatsAppDeskColors.Teal
                        )
                    }

                    OutlinedTextField(
                        value = inputText,
                        onValueChange = { inputText = it },
                        placeholder = { Text("Type a message or tap ⚡ for replies...", fontSize = 13.sp) },
                        modifier = Modifier
                            .weight(1f)
                            .padding(horizontal = 4.dp),
                        shape = RoundedCornerShape(20.dp),
                        maxLines = 3,
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = WhatsAppDeskColors.Teal,
                            unfocusedBorderColor = WhatsAppDeskColors.SlateBorder
                        )
                    )

                    IconButton(
                        onClick = {
                            if (inputText.isNotBlank()) {
                                viewModel.sendMessage(venueId, inputText)
                                inputText = ""
                            }
                        },
                        modifier = Modifier
                            .clip(CircleShape)
                            .background(WhatsAppDeskColors.Teal)
                            .size(40.dp)
                    ) {
                        Icon(
                            imageVector = Icons.AutoMirrored.Filled.Send,
                            contentDescription = "Send",
                            tint = Color.White,
                            modifier = Modifier.size(18.dp)
                        )
                    }
                }
            }
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .background(Color(0xFFEFEAE2)) // WhatsApp chat background tint
        ) {
            // Sticky Booking Suggestion / Active Hold Card at Top
            uiState.bookingSuggestion?.let { suggestion ->
                BookingSuggestionCard(
                    suggestion = suggestion,
                    holdSecondsLeft = uiState.activeHoldTimerSeconds,
                    isLoading = uiState.isActionLoading,
                    onHoldAndSendLink = {
                        viewModel.holdSlot(
                            venueId = venueId,
                            courtId = suggestion.courtId,
                            courtName = suggestion.courtName,
                            date = suggestion.date,
                            startTime = suggestion.startTime,
                            endTime = suggestion.endTime,
                            price = suggestion.price
                        )
                    },
                    onSendPaymentLink = {
                        viewModel.sendPaymentLink(venueId, suggestion.price)
                    },
                    onMarkPaidCash = {
                        viewModel.markPaidManual(venueId, "cash")
                    },
                    onReleaseHold = {
                        viewModel.releaseHold(venueId)
                    },
                    onDismiss = {
                        viewModel.dismissSuggestion()
                    }
                )
            }

            // Message Timeline
            LazyColumn(
                state = listState,
                modifier = Modifier
                    .fillMaxWidth()
                    .weight(1f)
                    .padding(horizontal = 12.dp, vertical = 6.dp),
                verticalArrangement = Arrangement.spacedBy(6.dp)
            ) {
                items(uiState.messages) { msg ->
                    ChatBubbleItem(msg)
                }
            }
        }
    }

    // Quick Replies Bottom Sheet
    if (showQuickRepliesSheet) {
        ModalBottomSheet(
            onDismissRequest = { showQuickRepliesSheet = false }
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp)
            ) {
                Text(
                    text = "Quick Replies & Canned Responses",
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold,
                    color = WhatsAppDeskColors.TextPrimary
                )
                Spacer(Modifier.height(10.dp))

                uiState.quickReplies.forEach { reply ->
                    Surface(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(vertical = 4.dp)
                            .clickable {
                                inputText = reply.body
                                showQuickRepliesSheet = false
                            },
                        shape = RoundedCornerShape(10.dp),
                        color = WhatsAppDeskColors.SlateLight
                    ) {
                        Row(
                            modifier = Modifier.padding(12.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                text = reply.shortcut,
                                fontWeight = FontWeight.Bold,
                                color = WhatsAppDeskColors.Teal,
                                fontSize = 13.sp,
                                modifier = Modifier.width(75.dp)
                            )
                            Column(Modifier.weight(1f)) {
                                Text(reply.title, fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
                                Text(reply.body, fontSize = 11.sp, color = WhatsAppDeskColors.TextSecondary, maxLines = 1)
                            }
                        }
                    }
                }
                Spacer(Modifier.height(20.dp))
            }
        }
    }
}

@Composable
fun ChatBubbleItem(msg: ChatMessage) {
    val isOutbound = msg.direction == "outbound"
    val alignment = if (isOutbound) Alignment.End else Alignment.Start
    val bubbleColor = when {
        msg.senderType == "system" -> WhatsAppDeskColors.ChatBubbleSystem
        isOutbound -> WhatsAppDeskColors.ChatBubblePartner
        else -> WhatsAppDeskColors.ChatBubbleCustomer
    }

    Column(
        modifier = Modifier.fillMaxWidth(),
        horizontalAlignment = alignment
    ) {
        Surface(
            shape = RoundedCornerShape(
                topStart = 14.dp,
                topEnd = 14.dp,
                bottomStart = if (isOutbound) 14.dp else 2.dp,
                bottomEnd = if (isOutbound) 2.dp else 14.dp
            ),
            color = bubbleColor,
            tonalElevation = 1.dp,
            shadowElevation = 1.dp,
            modifier = Modifier.widthIn(max = 280.dp)
        ) {
            Column(modifier = Modifier.padding(10.dp)) {
                if (msg.messageType == "ticket_card") {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Filled.ConfirmationNumber, contentDescription = null, tint = WhatsAppDeskColors.ConvertedGreen, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(6.dp))
                        Text("VERIFIED PASS ISSUED", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = WhatsAppDeskColors.ConvertedGreen)
                    }
                    Spacer(Modifier.height(4.dp))
                }

                Text(
                    text = msg.body,
                    fontSize = 13.sp,
                    color = WhatsAppDeskColors.TextPrimary,
                    lineHeight = 18.sp
                )

                Spacer(Modifier.height(4.dp))

                Row(
                    modifier = Modifier.align(Alignment.End),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = msg.createdAt.takeLast(8).ifEmpty { "12:00" },
                        fontSize = 10.sp,
                        color = WhatsAppDeskColors.TextSecondary
                    )
                    if (isOutbound) {
                        Spacer(Modifier.width(3.dp))
                        Icon(
                            imageVector = Icons.Filled.DoneAll,
                            contentDescription = "Delivered",
                            tint = if (msg.deliveryStatus == "read") Color(0xFF34B7F1) else WhatsAppDeskColors.TextSecondary,
                            modifier = Modifier.size(12.dp)
                        )
                    }
                }
            }
        }
    }
}