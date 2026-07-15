package com.haraan.app.ui

import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.tween
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.LinearEasing
import androidx.compose.ui.platform.LocalView
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.PathFillType
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke as DrawStroke
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.graphics.graphicsLayer
import com.haraan.app.ui.animations.pressScale
import coil.compose.AsyncImage
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.KeyboardArrowDown
import androidx.compose.material.icons.outlined.Email
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
import androidx.compose.ui.layout.layout
import androidx.compose.ui.res.painterResource
import com.haraan.app.data.ApiConfig
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
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.repeatOnLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.haraan.app.R
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
        onEmailChange = viewModel::onEmailChange,
        onNameChange = viewModel::onNameChange,
        onDobChange = viewModel::onDobChange,
        onOtpChange = viewModel::onOtpChange,
        onContinueClick = viewModel::requestOtp,
        onVerifyOtpClick = { viewModel.verifyOtp(onLoginSuccess) },
        onCompleteProfileClick = { viewModel.completeProfile(onLoginSuccess) },
        onBackToEmailClick = viewModel::resetToEmail,
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
    onEmailChange: (String) -> Unit,
    onNameChange: (String) -> Unit,
    onDobChange: (String) -> Unit,
    onOtpChange: (String) -> Unit,
    onContinueClick: () -> Unit,
    onVerifyOtpClick: () -> Unit,
    onCompleteProfileClick: () -> Unit,
    onBackToEmailClick: () -> Unit,
    onSkipClick: () -> Unit = {},
    modifier: Modifier = Modifier
) {
    var isDetailsInputVisible by remember { mutableStateOf(false) }
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

    // Poster images — fetched from the admin API; falls back to local drawables if offline.
    // Re-fetched every time the screen resumes (repeatOnLifecycle at RESUMED), so an admin
    // change in /control shows up on the next foreground, not only on a cold app restart.
    val localPosters = listOf(R.drawable.poster1, R.drawable.poster2, R.drawable.poster3)
    val lifecycleOwner = LocalLifecycleOwner.current
    var remotePosters by remember { mutableStateOf<List<String>>(emptyList()) }
    LaunchedEffect(lifecycleOwner) {
        lifecycleOwner.lifecycle.repeatOnLifecycle(Lifecycle.State.RESUMED) {
            withContext(Dispatchers.IO) {
                runCatching {
                    val json = java.net.URL("${ApiConfig.BASE_URL}/api/login-posters").readText()
                    val arr = JSONArray(json)
                    (0 until arr.length()).mapNotNull { i ->
                        arr.getJSONObject(i).optString("image").takeIf { it.isNotBlank() }
                    }
                }
            // Only overwrite on a successful fetch: a network failure keeps the posters we
            // already have (no flicker to the local fallback), while an empty-but-successful
            // response correctly reflects the admin having removed every poster.
            }.onSuccess { remotePosters = it }
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
            if (uiState.stage == LoginStage.EnterEmail && !isDetailsInputVisible) {
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
                    val showBranding = uiState.stage == LoginStage.EnterEmail && !isDetailsInputVisible

                    // Grab handle.
                    Box(
                        modifier = Modifier
                            .padding(bottom = 12.dp)
                            .size(width = 36.dp, height = 4.dp)
                            .clip(RoundedCornerShape(2.dp))
                            .background(Stroke)
                    )

                    if (showBranding) {
                        // Hero — the decorative art (festival left / sports right) is a
                        // full-bleed backdrop with a clear centre channel; the brand mark,
                        // wordmark and tagline stack over that channel so the artwork frames
                        // the branding as one unit, matching the reference design. Bleeds past
                        // the card's 24dp side padding so the art reaches the card edges.
                        Box(
                            modifier = Modifier
                                .layout { measurable, constraints ->
                                    val bleed = 24.dp.roundToPx()
                                    val fullWidth = constraints.maxWidth + bleed * 2
                                    val placeable = measurable.measure(
                                        constraints.copy(minWidth = fullWidth, maxWidth = fullWidth)
                                    )
                                    // Report the ORIGINAL width so the parent keeps this node in
                                    // its normal centred slot; the content overflows symmetrically
                                    // by `bleed` on each side to reach both card edges.
                                    layout(constraints.maxWidth, placeable.height) {
                                        placeable.place(-bleed, 0)
                                    }
                                },
                            contentAlignment = Alignment.Center
                        ) {
                            // Full art (not cropped) so the festival/sports clusters and their
                            // waves run down the left/right sides of the card instead of leaving
                            // empty margins.
                            Image(
                                painter = painterResource(id = R.drawable.login_card_art),
                                contentDescription = null,
                                modifier = Modifier.fillMaxWidth(),
                                contentScale = ContentScale.FillWidth
                            )
                            Column(horizontalAlignment = Alignment.CenterHorizontally) {
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
                                        contentDescription = null,
                                        modifier = Modifier.size(30.dp),
                                        contentScale = ContentScale.Fit,
                                        colorFilter = ColorFilter.tint(Accent)
                                    )
                                }
                                Spacer(Modifier.height(8.dp))
                                // Brand wordmark image — replaces the text wordmark.
                                Image(
                                    painter = painterResource(id = R.drawable.haraan_wordmark),
                                    contentDescription = com.haraan.app.ui.theme.Brand.name,
                                    modifier = Modifier.height(46.dp),
                                    contentScale = ContentScale.Fit
                                )
                            }
                        }
                        // Tagline + subtitle on clean white, flanked by matching accent
                        // motifs (blue left / green right) drawn into the side margins so the
                        // empty lower sides of the card carry the artwork downward.
                        Spacer(Modifier.height(8.dp))
                        Box(modifier = Modifier.fillMaxWidth()) {
                            LoginSideAccents(modifier = Modifier.matchParentSize())
                            Column(
                                modifier = Modifier
                                    .align(Alignment.Center)
                                    .padding(vertical = 8.dp),
                                horizontalAlignment = Alignment.CenterHorizontally
                            ) {
                                Text(
                                    text = com.haraan.app.ui.theme.Brand.tagline.uppercase(),
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = Text2,
                                    letterSpacing = 1.4.sp,
                                    maxLines = 1,
                                    textAlign = TextAlign.Center
                                )
                                Spacer(Modifier.height(12.dp))
                                Text(
                                    text = "Login or sign up to continue",
                                    fontSize = 14.sp,
                                    fontWeight = FontWeight.Medium,
                                    color = Text2,
                                    textAlign = TextAlign.Center
                                )
                            }
                        }
                    } else {
                        // Compact header for the email / OTP / profile steps — keeps the brand
                        // mark for identity but drops the full artwork so the card stays tight.
                        Box(
                            modifier = Modifier
                                .size(48.dp)
                                .clip(RoundedCornerShape(14.dp))
                                .background(BlueTint),
                            contentAlignment = Alignment.Center
                        ) {
                            Image(
                                painter = painterResource(id = R.drawable.haraan_copy),
                                contentDescription = null,
                                modifier = Modifier.size(26.dp),
                                contentScale = ContentScale.Fit,
                                colorFilter = ColorFilter.tint(Accent)
                            )
                        }
                        Spacer(Modifier.height(10.dp))
                        Text(
                            text = com.haraan.app.ui.theme.Brand.name,
                            fontSize = 26.sp,
                            fontWeight = FontWeight.ExtraBold,
                            color = Text1,
                            letterSpacing = (-0.5).sp
                        )
                        Spacer(Modifier.height(16.dp))
                    }

                    // On the branding landing the subtitle is rendered inside the accent box
                    // above; here it covers every other stage (email entry / OTP / profile),
                    // flanked by the same accent motifs so these cards aren't blank either.
                    if (!showBranding) {
                        Box(modifier = Modifier.fillMaxWidth()) {
                            LoginSideAccents(modifier = Modifier.matchParentSize())
                            Text(
                                modifier = Modifier
                                    .align(Alignment.Center)
                                    .padding(vertical = 6.dp),
                                text = when (uiState.stage) {
                                    LoginStage.EnterEmail ->
                                        if (!isDetailsInputVisible) "Login or sign up to continue"
                                        else "Enter your email to get a login code"
                                    LoginStage.VerifyOtp -> "Enter the 6-digit code sent to your email"
                                    LoginStage.CompleteProfile -> "Almost there — tell us a bit about you"
                                    else -> ""
                                },
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Medium,
                                color = Text2,
                                textAlign = TextAlign.Center
                            )
                        }
                    }

                    Spacer(Modifier.height(18.dp))

                    val fieldColors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = Accent,
                        unfocusedBorderColor = Stroke,
                        focusedContainerColor = FieldBg,
                        unfocusedContainerColor = FieldBg
                    )

                    when (uiState.stage) {
                        // ── Step 1: email only ────────────────────────────────────────────
                        LoginStage.EnterEmail -> {
                            if (!isDetailsInputVisible) {
                                val ci = remember { MutableInteractionSource() }
                                Button(
                                    onClick = {
                                        view.performHapticFeedback(android.view.HapticFeedbackConstants.KEYBOARD_TAP)
                                        isDetailsInputVisible = true
                                    },
                                    interactionSource = ci,
                                    modifier = Modifier.fillMaxWidth().height(56.dp).pressScale(ci),
                                    shape = RoundedCornerShape(16.dp),
                                    colors = ButtonDefaults.buttonColors(containerColor = Accent)
                                ) {
                                    Icon(
                                        imageVector = Icons.Outlined.Email,
                                        contentDescription = null,
                                        tint = Color.White,
                                        modifier = Modifier.size(20.dp)
                                    )
                                    Spacer(Modifier.width(10.dp))
                                    Text(
                                        "Continue with email",
                                        fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Color.White,
                                        maxLines = 1, softWrap = false, overflow = TextOverflow.Ellipsis
                                    )
                                }
                            } else {
                                OutlinedTextField(
                                    value = uiState.email,
                                    onValueChange = onEmailChange,
                                    placeholder = { Text("Email address", color = Text3) },
                                    modifier = Modifier.fillMaxWidth(),
                                    shape = RoundedCornerShape(16.dp),
                                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
                                    singleLine = true,
                                    colors = fieldColors
                                )

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
                                        if (uiState.isLoading) "Please wait…" else "Get login code",
                                        fontSize = 16.sp, fontWeight = FontWeight.Bold,
                                        color = if (uiState.canContinue) Color.White else Text3
                                    )
                                }
                            }
                        }

                        // ── Step 2: verify the 6-digit code ───────────────────────────────
                        LoginStage.VerifyOtp -> {
                            OtpEntryRow(otp = uiState.otp, onOtpChange = onOtpChange, modifier = Modifier.fillMaxWidth())

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
                            TextButton(onClick = onBackToEmailClick) {
                                Text("Change email", color = Accent, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
                            }
                        }

                        // ── Step 3: new users only — name + date of birth ─────────────────
                        LoginStage.CompleteProfile -> {
                            OutlinedTextField(
                                value = uiState.name,
                                onValueChange = onNameChange,
                                placeholder = { Text("Your name", color = Text3) },
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(16.dp),
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Text),
                                singleLine = true,
                                colors = fieldColors
                            )

                            Spacer(Modifier.height(10.dp))

                            DateOfBirthField(dob = uiState.dob, onDobChange = onDobChange)

                            if (!uiState.errorMessage.isNullOrEmpty()) {
                                Spacer(Modifier.height(12.dp))
                                Text(uiState.errorMessage, color = Color(0xFFDC2626), fontSize = 13.sp, fontWeight = FontWeight.Medium, modifier = Modifier.fillMaxWidth())
                            }

                            Spacer(Modifier.height(14.dp))

                            val fi = remember { MutableInteractionSource() }
                            Button(
                                onClick = {
                                    view.performHapticFeedback(android.view.HapticFeedbackConstants.KEYBOARD_TAP)
                                    onCompleteProfileClick()
                                },
                                interactionSource = fi,
                                modifier = Modifier.fillMaxWidth().height(56.dp).pressScale(fi),
                                shape = RoundedCornerShape(16.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = Accent, disabledContainerColor = Stroke),
                                enabled = uiState.canComplete
                            ) {
                                Text(
                                    if (uiState.isLoading) "Creating account…" else "Create account",
                                    fontSize = 16.sp, fontWeight = FontWeight.Bold,
                                    color = if (uiState.canComplete) Color.White else Text3
                                )
                            }
                        }

                        else -> {}
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

/**
 * Date-of-birth picker field (new-user sign-up). Renders like the other fields but opens a
 * Material date picker; emits the chosen date as an ISO yyyy-MM-dd string. Future dates are
 * not selectable.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DateOfBirthField(dob: String, onDobChange: (String) -> Unit) {
    var showPicker by remember { mutableStateOf(false) }

    val display = remember(dob) {
        runCatching {
            java.time.LocalDate.parse(dob)
                .format(java.time.format.DateTimeFormatter.ofPattern("d MMM yyyy"))
        }.getOrNull()
    }

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(56.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(FieldBg)
            .border(1.dp, Stroke, RoundedCornerShape(16.dp))
            .clickable { showPicker = true }
            .padding(horizontal = 16.dp),
        contentAlignment = Alignment.CenterStart
    ) {
        Text(
            text = display ?: "Date of birth",
            color = if (display == null) Text3 else Text1,
            fontSize = 16.sp
        )
    }

    if (showPicker) {
        val today = remember { java.time.LocalDate.now() }
        val pickerState = rememberDatePickerState(
            initialSelectedDateMillis = dob.takeIf { it.isNotBlank() }?.let {
                runCatching {
                    java.time.LocalDate.parse(it).atStartOfDay(java.time.ZoneOffset.UTC).toInstant().toEpochMilli()
                }.getOrNull()
            },
            yearRange = 1920..today.year,
            selectableDates = object : SelectableDates {
                override fun isSelectableDate(utcTimeMillis: Long): Boolean =
                    utcTimeMillis <= System.currentTimeMillis()
            }
        )
        DatePickerDialog(
            onDismissRequest = { showPicker = false },
            confirmButton = {
                TextButton(onClick = {
                    pickerState.selectedDateMillis?.let { millis ->
                        val iso = java.time.Instant.ofEpochMilli(millis)
                            .atZone(java.time.ZoneOffset.UTC).toLocalDate().toString()
                        onDobChange(iso)
                    }
                    showPicker = false
                }) { Text("OK", color = Accent, fontWeight = FontWeight.Bold) }
            },
            dismissButton = {
                TextButton(onClick = { showPicker = false }) { Text("Cancel", color = Text2) }
            }
        ) {
            DatePicker(state = pickerState, title = null, showModeToggle = false)
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

// Art green sampled from the login artwork's sports (right) cluster.
private val ArtGreen = Color(0xFF16A34A)

/**
 * Decorative accent motifs — sparkles, a dotted path and a location pin — drawn into the
 * left/right side margins (blue on the left, green on the right) so the empty lower sides
 * of the login card echo the artwork instead of reading as blank white.
 */
@Composable
private fun LoginSideAccents(modifier: Modifier = Modifier) {
    val blue = Accent.copy(alpha = 0.55f)
    val green = ArtGreen.copy(alpha = 0.55f)
    Canvas(modifier = modifier) {
        drawAccentCluster(size.width, size.height, mirror = false, color = blue)
        drawAccentCluster(size.width, size.height, mirror = true, color = green)
    }
}

/** Draws one side's cluster; [mirror] flips it horizontally for the opposite margin. */
private fun DrawScope.drawAccentCluster(w: Float, h: Float, mirror: Boolean, color: Color) {
    fun px(fx: Float) = if (mirror) w - w * fx else w * fx
    val path = Path().apply {
        moveTo(px(0.05f), h * 0.12f)
        quadraticBezierTo(px(0.22f), h * 0.32f, px(0.10f), h * 0.62f)
    }
    drawPath(
        path,
        color,
        style = DrawStroke(
            width = 1.5.dp.toPx(),
            pathEffect = PathEffect.dashPathEffect(floatArrayOf(5f, 8f))
        )
    )
    drawSparkle(Offset(px(0.14f), h * 0.30f), 6.dp.toPx(), color)
    drawSparkle(Offset(px(0.045f), h * 0.54f), 3.5.dp.toPx(), color)
    drawPin(Offset(px(0.11f), h * 0.82f), 5.dp.toPx(), color)
}

/** A 4-point sparkle (concave star). */
private fun DrawScope.drawSparkle(c: Offset, r: Float, color: Color) {
    val i = r * 0.36f
    val p = Path().apply {
        moveTo(c.x, c.y - r)
        lineTo(c.x + i, c.y - i)
        lineTo(c.x + r, c.y)
        lineTo(c.x + i, c.y + i)
        lineTo(c.x, c.y + r)
        lineTo(c.x - i, c.y + i)
        lineTo(c.x - r, c.y)
        lineTo(c.x - i, c.y - i)
        close()
    }
    drawPath(p, color)
}

/** A location pin (teardrop with a hollow centre via even-odd fill). */
private fun DrawScope.drawPin(c: Offset, r: Float, color: Color) {
    val p = Path().apply {
        fillType = PathFillType.EvenOdd
        moveTo(c.x, c.y + r * 1.9f)
        cubicTo(c.x - r * 1.4f, c.y + r * 0.4f, c.x - r, c.y - r * 0.6f, c.x, c.y - r)
        cubicTo(c.x + r, c.y - r * 0.6f, c.x + r * 1.4f, c.y + r * 0.4f, c.x, c.y + r * 1.9f)
        addOval(Rect(c.x - r * 0.42f, c.y - r * 0.55f, c.x + r * 0.42f, c.y + r * 0.29f))
    }
    drawPath(p, color)
}
