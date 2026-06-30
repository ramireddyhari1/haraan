package com.example.thanna.ui

import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.tween
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.LinearEasing
import androidx.compose.ui.platform.LocalView
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.Image
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.graphics.graphicsLayer
import com.example.thanna.ui.animations.pressScale
import coil.compose.AsyncImage
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.KeyboardArrowDown
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import com.example.thanna.data.ApiConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.example.thanna.R
import kotlinx.coroutines.delay
import kotlinx.coroutines.yield

@Composable
fun LoginRoute(
    onSkipClick: () -> Unit = {},
    onLoginSuccess: (String) -> Unit = {},
    modifier: Modifier = Modifier,
    viewModel: LoginViewModel = viewModel()
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()

    LoginScreen(
        uiState = uiState,
        onPhoneNumberChange = viewModel::onPhoneNumberChange,
        onOtpChange = viewModel::onOtpChange,
        onContinueClick = viewModel::requestOtp,
        onVerifyOtpClick = { viewModel.verifyOtp(onLoginSuccess) },
        onBackToPhoneClick = viewModel::resetToPhoneEntry,
        onSkipClick = onSkipClick,
        modifier = modifier
    )
}

// Palette — content lives on a white card; the card floats over a full-bleed,
// slowly-zooming hero image with a soft scrim.
private val Ink = Color(0xFF0A0E14)
private val Accent = Color(0xFF2563EB)
private val Text1 = Color(0xFF0F172A)
private val Text2 = Color(0xFF475569)
private val Text3 = Color(0xFF94A3B8)
private val Stroke = Color(0xFFE2E8F0)
private val FieldBg = Color(0xFFF8FAFC)
private val BlueTint = Color(0xFFEFF4FF)
private val FrostFill = Color.White.copy(alpha = 0.16f)
private val FrostBorder = Color.White.copy(alpha = 0.30f)

@OptIn(ExperimentalFoundationApi::class)
@Composable
fun LoginScreen(
    uiState: LoginUiState,
    onPhoneNumberChange: (String) -> Unit,
    onOtpChange: (String) -> Unit,
    onContinueClick: () -> Unit,
    onVerifyOtpClick: () -> Unit,
    onBackToPhoneClick: () -> Unit,
    onSkipClick: () -> Unit = {},
    modifier: Modifier = Modifier
) {
    var isPhoneInputVisible by remember { mutableStateOf(false) }
    val view = LocalView.current

    // Card entrance — a subtle rise + fade the first time the screen appears.
    var cardVisible by remember { mutableStateOf(false) }
    LaunchedEffect(Unit) { cardVisible = true }
    val cardAlpha by animateFloatAsState(
        targetValue = if (cardVisible) 1f else 0f,
        animationSpec = tween(durationMillis = 450), label = "cardAlpha"
    )
    val cardShiftPx by animateFloatAsState(
        targetValue = if (cardVisible) 0f else 64f,
        animationSpec = tween(durationMillis = 450), label = "cardShift"
    )

    // Ken-Burns — a slow continuous zoom so the hero breathes instead of sitting flat.
    val kenBurns = rememberInfiniteTransition(label = "kenBurns")
    val posterZoom by kenBurns.animateFloat(
        initialValue = 1.0f,
        targetValue = 1.12f,
        animationSpec = infiniteRepeatable(tween(9000, easing = LinearEasing), RepeatMode.Reverse),
        label = "posterZoom"
    )

    // Poster images — fetched from admin API; falls back to local drawables if offline.
    val localPosters = listOf(R.drawable.poster1, R.drawable.poster2, R.drawable.poster3)
    val remotePosters by produceState<List<String>>(initialValue = emptyList()) {
        value = withContext(Dispatchers.IO) {
            runCatching {
                val json = java.net.URL("${ApiConfig.BASE_URL}/api/login-posters").readText()
                val arr = JSONArray(json)
                (0 until arr.length()).mapNotNull { i ->
                    arr.getJSONObject(i).optString("image").takeIf { it.isNotBlank() }
                }
            }.getOrDefault(emptyList())
        }
    }
    val hasRemote = remotePosters.isNotEmpty()
    val posterCount = if (hasRemote) remotePosters.size else localPosters.size

    Box(
        modifier = modifier
            .fillMaxSize()
            .background(Ink)
    ) {
        // 1. Full-bleed, slowly-zooming poster pager.
        val pageCount = Int.MAX_VALUE
        val pagerState = rememberPagerState(
            initialPage = (pageCount / 2) - ((pageCount / 2) % posterCount.coerceAtLeast(1)),
            pageCount = { pageCount }
        )
        LaunchedEffect(Unit) {
            while (true) {
                yield()
                delay(3500)
                pagerState.animateScrollToPage(pagerState.currentPage + 1)
            }
        }
        HorizontalPager(state = pagerState, modifier = Modifier.fillMaxSize()) { page ->
            val actualPage = page % posterCount.coerceAtLeast(1)
            val imgModifier = Modifier
                .fillMaxSize()
                .graphicsLayer { scaleX = posterZoom; scaleY = posterZoom }
            if (hasRemote) {
                AsyncImage(
                    model = remotePosters[actualPage],
                    contentDescription = "Poster ${actualPage + 1}",
                    modifier = imgModifier,
                    contentScale = ContentScale.Crop,
                    placeholder = painterResource(id = localPosters[actualPage % localPosters.size]),
                    error = painterResource(id = localPosters[actualPage % localPosters.size]),
                )
            } else {
                Image(
                    painter = painterResource(id = localPosters[actualPage]),
                    contentDescription = "Poster ${actualPage + 1}",
                    modifier = imgModifier,
                    contentScale = ContentScale.Crop
                )
            }
        }

        // 2. Soft scrim — darkens the very top (for Skip) and the very bottom (so the
        //    white card's edge blends into the image instead of cutting hard).
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    Brush.verticalGradient(
                        0.0f to Ink.copy(alpha = 0.30f),
                        0.22f to Color.Transparent,
                        0.70f to Color.Transparent,
                        1.0f to Ink.copy(alpha = 0.55f),
                    )
                )
        )

        // 3. Skip — frosted glass pill, top-right.
        Row(
            modifier = Modifier
                .align(Alignment.TopEnd)
                .statusBarsPadding()
                .padding(16.dp)
                .clip(RoundedCornerShape(20.dp))
                .background(FrostFill)
                .border(1.dp, FrostBorder, RoundedCornerShape(20.dp))
                .clickable(onClick = onSkipClick)
                .padding(horizontal = 16.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text("Skip", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
        }

        // 4. Page dots on the image + the white content card, anchored to the bottom.
        //    Insets live on THIS column so the card sits just above the keyboard/nav
        //    bar with no internal gap (applying imePadding inside the card reserves
        //    keyboard-height padding inside it → a white void + clipped header).
        Column(
            modifier = Modifier
                .align(Alignment.BottomCenter)
                .fillMaxWidth(),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            // Dots only in the collapsed hero — hidden once the keyboard appears so the
            // card has room and nothing clips.
            if (uiState.stage == LoginStage.EnterPhone && !isPhoneInputVisible) {
                Row(
                    modifier = Modifier.padding(bottom = 14.dp),
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    repeat(posterCount.coerceAtLeast(1)) { i ->
                        val sel = (pagerState.currentPage % posterCount.coerceAtLeast(1)) == i
                        Box(
                            modifier = Modifier
                                .height(3.dp)
                                .width(if (sel) 18.dp else 6.dp)
                                .clip(RoundedCornerShape(2.dp))
                                .background(if (sel) Color.White else Color.White.copy(alpha = 0.4f))
                        )
                    }
                }
            }

            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .wrapContentHeight()
                    .graphicsLayer {
                        alpha = cardAlpha
                        translationY = cardShiftPx
                    },
                shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp),
                colors = CardDefaults.cardColors(containerColor = Color.White),
                elevation = CardDefaults.cardElevation(defaultElevation = 18.dp)
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        // Insets live INSIDE the white card, so the region reserved for the
                        // keyboard / nav bar is white — never a strip of background image.
                        // Union (not sum) avoids double-counting the nav bar.
                        .windowInsetsPadding(WindowInsets.ime.union(WindowInsets.navigationBars))
                        .padding(start = 24.dp, end = 24.dp, top = 16.dp, bottom = 18.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    // Full branding only in the collapsed hero — hidden once the keyboard
                    // is up so the card stays compact and nothing clips at the top.
                    val showBranding = uiState.stage == LoginStage.EnterPhone && !isPhoneInputVisible

                    // Grab handle.
                    Box(
                        modifier = Modifier
                            .padding(bottom = 16.dp)
                            .size(width = 36.dp, height = 4.dp)
                            .clip(RoundedCornerShape(2.dp))
                            .background(Stroke)
                    )

                    if (showBranding) {
                        // Brand mark — blue H in a soft tinted tile.
                        Box(
                            modifier = Modifier
                                .size(56.dp)
                                .clip(RoundedCornerShape(16.dp))
                                .background(BlueTint),
                            contentAlignment = Alignment.Center
                        ) {
                            Image(
                                painter = painterResource(id = R.drawable.haraan_copy),
                                contentDescription = com.example.thanna.ui.theme.Brand.name,
                                modifier = Modifier.size(30.dp),
                                contentScale = ContentScale.Fit,
                                colorFilter = ColorFilter.tint(com.example.thanna.ui.theme.Brand.accent)
                            )
                        }
                        Spacer(Modifier.height(12.dp))
                    }

                    Text(
                        text = com.example.thanna.ui.theme.Brand.name,
                        fontSize = 26.sp,
                        fontWeight = FontWeight.ExtraBold,
                        color = Text1,
                        letterSpacing = (-0.5).sp
                    )

                    if (showBranding) {
                        Spacer(Modifier.height(5.dp))
                        Text(
                            text = com.example.thanna.ui.theme.Brand.tagline.uppercase(),
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = Text3,
                            letterSpacing = 1.5.sp,
                            maxLines = 1,
                            textAlign = TextAlign.Center
                        )
                    }

                    Spacer(Modifier.height(18.dp))

                    Text(
                        text = if (uiState.stage == LoginStage.EnterPhone) {
                            if (!isPhoneInputVisible) "Login or sign up to continue" else "Enter your mobile number to verify"
                        } else {
                            "Enter the 6-digit code sent via WhatsApp"
                        },
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Medium,
                        color = Text2,
                        textAlign = TextAlign.Center
                    )

                    Spacer(Modifier.height(22.dp))

                    if (uiState.stage == LoginStage.EnterPhone) {
                        if (!isPhoneInputVisible) {
                            val ci = remember { MutableInteractionSource() }
                            Button(
                                onClick = {
                                    view.performHapticFeedback(android.view.HapticFeedbackConstants.KEYBOARD_TAP)
                                    isPhoneInputVisible = true
                                },
                                interactionSource = ci,
                                modifier = Modifier.fillMaxWidth().height(56.dp).pressScale(ci),
                                shape = RoundedCornerShape(16.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = Accent)
                            ) {
                                Text(
                                    "Continue with phone",
                                    fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Color.White,
                                    maxLines = 1, softWrap = false, overflow = TextOverflow.Ellipsis
                                )
                            }
                        } else {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(10.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                // Country selector — flag + code + chevron, matched height.
                                Row(
                                    modifier = Modifier
                                        .height(56.dp)
                                        .clip(RoundedCornerShape(16.dp))
                                        .background(FieldBg)
                                        .border(1.dp, Stroke, RoundedCornerShape(16.dp))
                                        .padding(horizontal = 14.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                                ) {
                                    Text("🇮🇳", fontSize = 16.sp)
                                    Text("+91", color = Text1, fontWeight = FontWeight.SemiBold, fontSize = 15.sp)
                                    Icon(Icons.Default.KeyboardArrowDown, contentDescription = null, tint = Text3, modifier = Modifier.size(18.dp))
                                }
                                OutlinedTextField(
                                    value = uiState.phoneNumber,
                                    onValueChange = onPhoneNumberChange,
                                    placeholder = { Text("Phone number", color = Text3) },
                                    modifier = Modifier.weight(1f),
                                    shape = RoundedCornerShape(16.dp),
                                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                                    singleLine = true,
                                    colors = OutlinedTextFieldDefaults.colors(
                                        focusedBorderColor = Accent,
                                        unfocusedBorderColor = Stroke,
                                        focusedContainerColor = FieldBg,
                                        unfocusedContainerColor = FieldBg
                                    )
                                )
                            }

                            if (!uiState.errorMessage.isNullOrEmpty()) {
                                Spacer(Modifier.height(12.dp))
                                Text(uiState.errorMessage, color = Color(0xFFDC2626), fontSize = 13.sp, fontWeight = FontWeight.Medium, modifier = Modifier.fillMaxWidth())
                            }

                            Spacer(Modifier.height(14.dp))

                            val pi = remember { MutableInteractionSource() }
                            Button(
                                onClick = {
                                    view.performHapticFeedback(android.view.HapticFeedbackConstants.KEYBOARD_TAP)
                                    onContinueClick()
                                },
                                interactionSource = pi,
                                modifier = Modifier.fillMaxWidth().height(56.dp).pressScale(pi),
                                shape = RoundedCornerShape(16.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = Accent, disabledContainerColor = Stroke),
                                enabled = uiState.canContinue
                            ) {
                                Text(
                                    if (uiState.isLoading) "Please wait…" else "Proceed",
                                    fontSize = 16.sp, fontWeight = FontWeight.Bold,
                                    color = if (uiState.canContinue) Color.White else Text3
                                )
                            }
                        }
                    } else {
                        OtpEntryRow(otp = uiState.otp, onOtpChange = onOtpChange, modifier = Modifier.fillMaxWidth())

                        // Dev builds have no WhatsApp bridge, so no real OTP is delivered.
                        // Surface the master code and let a tap auto-fill it.
                        if (com.example.thanna.BuildConfig.DEBUG) {
                            Spacer(Modifier.height(12.dp))
                            Box(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clip(RoundedCornerShape(10.dp))
                                    .background(Color(0xFFFEF3C7))
                                    .clickable { onOtpChange("000000") }
                                    .padding(horizontal = 12.dp, vertical = 10.dp)
                            ) {
                                Text(
                                    "Dev · master code 000000 also works. Tap to fill it",
                                    color = Color(0xFF92400E),
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.Medium
                                )
                            }
                        }

                        if (!uiState.errorMessage.isNullOrEmpty()) {
                            Spacer(Modifier.height(12.dp))
                            Text(uiState.errorMessage, color = Color(0xFFDC2626), fontSize = 13.sp, fontWeight = FontWeight.Medium, modifier = Modifier.fillMaxWidth())
                        }
                        if (!uiState.successMessage.isNullOrEmpty()) {
                            Spacer(Modifier.height(12.dp))
                            Text(uiState.successMessage, color = Color(0xFF16A34A), fontSize = 13.sp, fontWeight = FontWeight.Medium, modifier = Modifier.fillMaxWidth())
                        }

                        Spacer(Modifier.height(14.dp))

                        val vi = remember { MutableInteractionSource() }
                        Button(
                            onClick = {
                                view.performHapticFeedback(android.view.HapticFeedbackConstants.KEYBOARD_TAP)
                                onVerifyOtpClick()
                            },
                            interactionSource = vi,
                            modifier = Modifier.fillMaxWidth().height(56.dp).pressScale(vi),
                            shape = RoundedCornerShape(16.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = Accent, disabledContainerColor = Stroke),
                            enabled = uiState.isOtpValid && !uiState.isLoading
                        ) {
                            Text(
                                if (uiState.isLoading) "Verifying…" else "Verify & Proceed",
                                fontSize = 16.sp, fontWeight = FontWeight.Bold,
                                color = if (uiState.isOtpValid && !uiState.isLoading) Color.White else Text3
                            )
                        }

                        Spacer(Modifier.height(4.dp))
                        TextButton(onClick = onBackToPhoneClick) {
                            Text("Change number", color = Accent, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }

                    Spacer(Modifier.height(16.dp))

                    Text(
                        text = buildAnnotatedString {
                            append("By continuing, you agree to our ")
                            withStyle(SpanStyle(color = Accent, fontWeight = FontWeight.SemiBold)) {
                                append("Terms & Conditions")
                            }
                        },
                        fontSize = 12.sp,
                        color = Text3,
                        textAlign = TextAlign.Center,
                        lineHeight = 17.sp
                    )
                }
            }
        }
    }
}

@Composable
fun OtpEntryRow(
    otp: String,
    onOtpChange: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    BasicTextField(
        value = otp,
        onValueChange = {
            if (it.length <= 6) {
                onOtpChange(it)
            }
        },
        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
        decorationBox = {
            Row(
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                modifier = modifier
            ) {
                repeat(6) { index ->
                    val char = when {
                        index >= otp.length -> ""
                        else -> otp[index].toString()
                    }
                    val isFocused = otp.length == index
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .height(56.dp)
                            .background(FieldBg, RoundedCornerShape(14.dp))
                            .border(
                                width = if (isFocused) 2.dp else 1.dp,
                                color = if (isFocused) Accent else Stroke,
                                shape = RoundedCornerShape(14.dp)
                            ),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = char,
                            fontSize = 20.sp,
                            fontWeight = FontWeight.Bold,
                            color = Text1
                        )
                    }
                }
            }
        }
    )
}
