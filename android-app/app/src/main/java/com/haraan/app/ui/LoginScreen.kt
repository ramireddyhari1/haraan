package com.haraan.app.ui

import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.tween
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.LinearEasing
import androidx.compose.ui.platform.LocalView
import androidx.activity.compose.BackHandler
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.spring
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.filled.Check
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.Image
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalDensity
import androidx.core.view.WindowInsetsControllerCompat
import com.haraan.app.ui.animations.pressScale
import coil.compose.AsyncImage
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.KeyboardArrowDown
import androidx.compose.material.icons.outlined.Email
import androidx.compose.material.icons.outlined.Visibility
import androidx.compose.material.icons.outlined.VisibilityOff
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
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
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
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
import kotlinx.coroutines.launch
import kotlinx.coroutines.yield

@Composable
fun LoginRoute(
    onSkipClick: () -> Unit = {},
    onLoginSuccess: (String) -> Unit = {},
    modifier: Modifier = Modifier,
    viewModel: LoginViewModel = viewModel()
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()
    val context = androidx.compose.ui.platform.LocalContext.current
    val scope = rememberCoroutineScope()

    LoginScreen(
        uiState = uiState,
        onEmailChange = viewModel::onEmailChange,
        onPasswordChange = viewModel::onPasswordChange,
        onNameChange = viewModel::onNameChange,
        onSignUpToggle = viewModel::setSignUp,
        onSubmitClick = { viewModel.signInWithPassword(onLoginSuccess) },
        onExitForm = viewModel::clearMessages,
        // Reset lives on the website (same accounts, same mail) — no app-side twin
        // to keep in sync. Opens externally so the login screen keeps its state.
        onForgotPasswordClick = {
            runCatching {
                context.startActivity(
                    android.content.Intent(
                        android.content.Intent.ACTION_VIEW,
                        android.net.Uri.parse("${com.haraan.app.data.ApiConfig.BASE_URL}/forgot-password")
                    )
                )
            }
        },
        onSkipClick = onSkipClick,
        // "Continue with Google": drive Credential Manager here (needs an Activity context),
        // then hand the ID token to the VM. Only offered when a Web client ID is configured.
        googleEnabled = com.haraan.app.data.GoogleSignInHelper.isConfigured,
        onGoogleClick = {
            viewModel.setLoading(true)
            scope.launch {
                when (val r = com.haraan.app.data.GoogleSignInHelper.signIn(context)) {
                    is com.haraan.app.data.GoogleSignInResult.Success ->
                        viewModel.signInWithGoogle(r.idToken, onLoginSuccess)
                    is com.haraan.app.data.GoogleSignInResult.Cancelled ->
                        viewModel.setLoading(false)
                    is com.haraan.app.data.GoogleSignInResult.Error ->
                        viewModel.onGoogleError(r.message)
                }
            }
        },
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
private val FrostFill = Color.White.copy(alpha = 0.16f)
private val FrostBorder = Color.White.copy(alpha = 0.30f)
private val Danger = Color(0xFFDC2626)
// Green is reserved for the committed/done state — blue stays the "act now" colour.
private val Success = Color(0xFF16A34A)
private val SuccessTint = Color(0xFFE7F7EC)

// ── Type scale ────────────────────────────────────────────────────────────────
// Three sizes, three weights. The premium read on this screen comes from using
// the same few values everywhere rather than from more decoration — the previous
// pass drifted to 11/13/13.5/14/15/16/26sp, which is what made it feel generic.
private val TitleSize = 24.sp   // the one headline
private val BodySize = 16.sp    // CTA labels, field text
private val CaptionSize = 13.sp // helper text, legal, secondary links

// ── Spacing grid ──────────────────────────────────────────────────────────────
// Everything on this screen snaps to 8 / 16 / 24. No in-between values.
private val GapS = 8.dp
private val GapM = 16.dp
private val GapL = 24.dp

@OptIn(ExperimentalFoundationApi::class)
@Composable
fun LoginScreen(
    uiState: LoginUiState,
    onEmailChange: (String) -> Unit,
    onPasswordChange: (String) -> Unit,
    onNameChange: (String) -> Unit,
    onSignUpToggle: (Boolean) -> Unit,
    onSubmitClick: () -> Unit,
    onExitForm: () -> Unit = {},
    onForgotPasswordClick: () -> Unit = {},
    onSkipClick: () -> Unit = {},
    googleEnabled: Boolean = false,
    onGoogleClick: () -> Unit = {},
    modifier: Modifier = Modifier
) {
    var isDetailsInputVisible by remember { mutableStateOf(false) }
    val view = LocalView.current

    // Expanding the card into the credentials form is a navigation step, so Back has to
    // undo it. Without this the whole login screen is the Activity's only destination and
    // Back fell straight through to the system — the app closed instead of going back to
    // the Google/email choice. Unwinds one step at a time (sign-up → sign-in → landing).
    // Disabled during Success so Back can't interrupt the hand-off to the app.
    BackHandler(enabled = isDetailsInputVisible && uiState.stage != LoginStage.Success) {
        if (uiState.isSignUp) {
            onSignUpToggle(false)
        } else {
            isDetailsInputVisible = false
            onExitForm()
        }
    }

    // The top of this screen is ALWAYS a darkened poster, so the status-bar icons must
    // be light. Without this the system keeps whatever appearance the rest of the app
    // uses (dark icons for light surfaces) and they sink into the scrim — which is what
    // made "no scrim" and "weak scrim" look equally bad. Restored on dispose so the
    // screens after login keep their own appearance.
    if (!view.isInEditMode) {
        DisposableEffect(Unit) {
            val window = (view.context as? android.app.Activity)?.window
            val controller = window?.let { WindowInsetsControllerCompat(it, view) }
            val previous = controller?.isAppearanceLightStatusBars
            controller?.isAppearanceLightStatusBars = false
            onDispose { if (previous != null) controller.isAppearanceLightStatusBars = previous }
        }
    }

    // Card entrance — the poster settles first, then the card rises 24dp and fades
    // in on an ease-out curve. Deliberately a short, single motion: the previous
    // 450ms linear rise from 64dp read as a slide-up panel rather than an arrival.
    var cardVisible by remember { mutableStateOf(false) }
    LaunchedEffect(Unit) { cardVisible = true }
    val cardAlpha by animateFloatAsState(
        targetValue = if (cardVisible) 1f else 0f,
        animationSpec = tween(durationMillis = 350, easing = FastOutSlowInEasing), label = "cardAlpha"
    )
    val cardShift = with(LocalDensity.current) { GapL.toPx() }
    val cardShiftPx by animateFloatAsState(
        targetValue = if (cardVisible) 0f else cardShift,
        animationSpec = tween(durationMillis = 350, easing = FastOutSlowInEasing), label = "cardShift"
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
            // Anchor the crop to the TOP: the card covers the lower ~45% of the screen,
            // and posters put their subject's face in the upper half. Centre-cropping
            // pushed faces behind the card and sliced them at the card edge.
            if (hasRemote) {
                AsyncImage(
                    model = remotePosters[actualPage],
                    contentDescription = "Poster ${actualPage + 1}",
                    modifier = imgModifier,
                    contentScale = ContentScale.Crop,
                    alignment = Alignment.TopCenter,
                    placeholder = painterResource(id = localPosters[actualPage % localPosters.size]),
                    error = painterResource(id = localPosters[actualPage % localPosters.size]),
                )
            } else {
                Image(
                    painter = painterResource(id = localPosters[actualPage]),
                    contentDescription = "Poster ${actualPage + 1}",
                    modifier = imgModifier,
                    contentScale = ContentScale.Crop,
                    alignment = Alignment.TopCenter
                )
            }
        }

        // 2. Scrim — the top stop has to guarantee legibility for the status-bar icons
        //    and Skip against an ARBITRARY admin-uploaded poster, so it is sized for the
        //    worst case (a bright, near-white image), not for the posters shipped today.
        //    0.30f was too weak: on the light cricket poster the status icons vanished.
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    Brush.verticalGradient(
                        0.0f to Ink.copy(alpha = 0.55f),
                        0.18f to Color.Transparent,
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
            if (uiState.stage == LoginStage.EnterCredentials && !isDetailsInputVisible) {
                Row(
                    modifier = Modifier.padding(bottom = GapM),
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
                        .padding(start = GapL, end = GapL, top = GapM, bottom = GapL),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    // Authenticated: hold a short confirmation instead of cutting straight
                    // to the app. The VM delays its success callback by the same beat.
                    if (uiState.stage == LoginStage.Success) {
                        LoginSuccessPanel(name = uiState.name)
                        return@Column
                    }

                    // Full branding only in the collapsed hero — hidden once the keyboard
                    // is up so the card stays compact and nothing clips at the top.
                    val showBranding = uiState.stage == LoginStage.EnterCredentials && !isDetailsInputVisible

                    // Grab handle.
                    Box(
                        modifier = Modifier
                            .padding(bottom = GapM)
                            .size(width = 36.dp, height = 4.dp)
                            .clip(RoundedCornerShape(2.dp))
                            .background(Stroke)
                    )

                    // Brand once, then get out of the way. The card previously stacked a
                    // tinted "H" tile, the wordmark, an all-caps tagline AND a subtitle
                    // before the first button — four elements all saying "this is Haraan".
                    // The wordmark alone carries identity; the poster carries personality.
                    // The decorative festival/sports line-art is gone for the same reason:
                    // it duplicated what the hero image already communicates, and sitting
                    // behind the wordmark it cost contrast for nothing.
                    Image(
                        painter = painterResource(id = R.drawable.haraan_wordmark),
                        contentDescription = com.haraan.app.ui.theme.Brand.name,
                        modifier = Modifier.height(if (showBranding) 40.dp else 32.dp),
                        contentScale = ContentScale.Fit
                    )

                    Spacer(Modifier.height(GapS))

                    // One line of copy, sized as the screen's single headline. It states
                    // what this screen is for — the tagline said what the brand is for,
                    // which the user did not ask about at the moment of signing in.
                    Text(
                        text = when {
                            // Kept short on purpose: at 24sp "…to continue" ran the full
                            // width of a 411dp screen and would wrap to two lines on a
                            // 360dp phone, which turns the headline into a paragraph.
                            !isDetailsInputVisible -> "Login or sign up"
                            uiState.isSignUp -> "Create your account"
                            else -> "Sign in to continue"
                        },
                        fontSize = if (showBranding) TitleSize else BodySize,
                        fontWeight = FontWeight.Bold,
                        color = Text1,
                        textAlign = TextAlign.Center,
                        lineHeight = if (showBranding) 30.sp else 22.sp
                    )

                    Spacer(Modifier.height(GapL))

                    val fieldColors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = Accent,
                        unfocusedBorderColor = Stroke,
                        focusedContainerColor = FieldBg,
                        unfocusedContainerColor = FieldBg,
                        focusedLabelColor = Accent,
                        unfocusedLabelColor = Text3
                    )

                    // Landing offers the two ways in; tapping the email option expands
                    // this same card into the credentials form. There is no second step —
                    // the backend signs up an unknown email, exactly as the website does.
                    run {
                            if (!isDetailsInputVisible) {
                                // Primary CTA — "Continue with Google" (when a Web client ID is
                                // configured). Email drops to a secondary text link below.
                                if (googleEnabled) {
                                    val gi = remember { MutableInteractionSource() }
                                    Button(
                                        onClick = {
                                            view.performHapticFeedback(android.view.HapticFeedbackConstants.KEYBOARD_TAP)
                                            onGoogleClick()
                                        },
                                        interactionSource = gi,
                                        modifier = Modifier.fillMaxWidth().height(56.dp).pressScale(gi),
                                        shape = RoundedCornerShape(16.dp),
                                        colors = ButtonDefaults.buttonColors(containerColor = Accent),
                                        enabled = !uiState.isLoading
                                    ) {
                                        // Real multi-colour Google mark on a white chip so it
                                        // stays legible on the blue button.
                                        Box(
                                            modifier = Modifier
                                                .size(24.dp)
                                                .clip(RoundedCornerShape(6.dp))
                                                .background(Color.White),
                                            contentAlignment = Alignment.Center
                                        ) {
                                            Image(
                                                painter = painterResource(id = R.drawable.ic_google_logo),
                                                contentDescription = null,
                                                modifier = Modifier.size(16.dp)
                                            )
                                        }
                                        Spacer(Modifier.width(GapS))
                                        Text(
                                            "Continue with Google",
                                            fontSize = BodySize, fontWeight = FontWeight.Bold, color = Color.White,
                                            maxLines = 1, softWrap = false, overflow = TextOverflow.Ellipsis
                                        )
                                    }

                                    if (!uiState.errorMessage.isNullOrEmpty()) {
                                        Spacer(Modifier.height(GapM))
                                        Text(uiState.errorMessage, color = Danger, fontSize = CaptionSize, fontWeight = FontWeight.Medium, modifier = Modifier.fillMaxWidth(), textAlign = TextAlign.Center)
                                    }

                                    // Secondary — email + password as a plain text link.
                                    Spacer(Modifier.height(GapM))
                                    Text(
                                        text = "Continue with email",
                                        fontSize = CaptionSize,
                                        fontWeight = FontWeight.SemiBold,
                                        color = Accent,
                                        textAlign = TextAlign.Center,
                                        modifier = Modifier
                                            .clip(RoundedCornerShape(8.dp))
                                            .clickable {
                                                view.performHapticFeedback(android.view.HapticFeedbackConstants.KEYBOARD_TAP)
                                                isDetailsInputVisible = true
                                            }
                                            .padding(horizontal = GapM, vertical = GapS)
                                    )
                                } else {
                                    // No Google client configured — email is the primary CTA.
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
                                        Spacer(Modifier.width(GapS))
                                        Text(
                                            "Continue with email",
                                            fontSize = BodySize, fontWeight = FontWeight.Bold, color = Color.White,
                                            maxLines = 1, softWrap = false, overflow = TextOverflow.Ellipsis
                                        )
                                    }

                                    if (!uiState.errorMessage.isNullOrEmpty()) {
                                        Spacer(Modifier.height(GapM))
                                        Text(uiState.errorMessage, color = Danger, fontSize = CaptionSize, fontWeight = FontWeight.Medium, modifier = Modifier.fillMaxWidth(), textAlign = TextAlign.Center)
                                    }
                                }
                            } else {
                                // Name is asked only when the user chose "Create account".
                                // On plain sign-in an unknown email still creates the account
                                // (as on the website) and the name defaults to the local part.
                                // Labels float above the value instead of being placeholders:
                                // a placeholder disappears the moment you type, leaving two
                                // identical-looking boxes with no way to re-check which is which.
                                if (uiState.isSignUp) {
                                    OutlinedTextField(
                                        value = uiState.name,
                                        onValueChange = onNameChange,
                                        label = { Text("Your name") },
                                        modifier = Modifier.fillMaxWidth(),
                                        shape = RoundedCornerShape(16.dp),
                                        keyboardOptions = KeyboardOptions(
                                            keyboardType = KeyboardType.Text,
                                            imeAction = ImeAction.Next
                                        ),
                                        singleLine = true,
                                        colors = fieldColors
                                    )
                                    Spacer(Modifier.height(GapM))
                                }

                                // Inline validation only after the user has actually typed —
                                // a pristine empty field must never be shouted at.
                                val emailTouched = uiState.email.isNotEmpty()
                                val emailBad = emailTouched && !uiState.isEmailValid
                                OutlinedTextField(
                                    value = uiState.email,
                                    onValueChange = onEmailChange,
                                    label = { Text("Email address") },
                                    isError = emailBad,
                                    supportingText = if (emailBad) {
                                        { Text("Enter a valid email address", fontSize = CaptionSize) }
                                    } else null,
                                    modifier = Modifier.fillMaxWidth(),
                                    shape = RoundedCornerShape(16.dp),
                                    keyboardOptions = KeyboardOptions(
                                        keyboardType = KeyboardType.Email,
                                        imeAction = ImeAction.Next
                                    ),
                                    singleLine = true,
                                    colors = fieldColors
                                )

                                Spacer(Modifier.height(GapM))

                                var passwordVisible by remember { mutableStateOf(false) }
                                val passwordTouched = uiState.password.isNotEmpty()
                                val passwordBad = passwordTouched && !uiState.isPasswordValid
                                OutlinedTextField(
                                    value = uiState.password,
                                    onValueChange = onPasswordChange,
                                    label = { Text("Password") },
                                    isError = passwordBad,
                                    // Says why the button is inert, instead of leaving a dead
                                    // grey button and no explanation.
                                    supportingText = if (passwordBad) {
                                        { Text("At least 6 characters", fontSize = CaptionSize) }
                                    } else null,
                                    modifier = Modifier.fillMaxWidth(),
                                    shape = RoundedCornerShape(16.dp),
                                    keyboardOptions = KeyboardOptions(
                                        keyboardType = KeyboardType.Password,
                                        imeAction = ImeAction.Done
                                    ),
                                    keyboardActions = KeyboardActions(onDone = { if (uiState.canSubmit) onSubmitClick() }),
                                    visualTransformation =
                                        if (passwordVisible) VisualTransformation.None
                                        else PasswordVisualTransformation(),
                                    trailingIcon = {
                                        IconButton(onClick = { passwordVisible = !passwordVisible }) {
                                            Icon(
                                                imageVector =
                                                    if (passwordVisible) Icons.Outlined.VisibilityOff
                                                    else Icons.Outlined.Visibility,
                                                contentDescription =
                                                    if (passwordVisible) "Hide password" else "Show password",
                                                tint = Text3
                                            )
                                        }
                                    },
                                    singleLine = true,
                                    colors = fieldColors
                                )

                                if (!uiState.errorMessage.isNullOrEmpty()) {
                                    Spacer(Modifier.height(GapM))
                                    Text(uiState.errorMessage, color = Danger, fontSize = CaptionSize, fontWeight = FontWeight.Medium, modifier = Modifier.fillMaxWidth())
                                }

                                Spacer(Modifier.height(GapL))

                                val pi = remember { MutableInteractionSource() }
                                Button(
                                    onClick = {
                                        view.performHapticFeedback(android.view.HapticFeedbackConstants.KEYBOARD_TAP)
                                        onSubmitClick()
                                    },
                                    interactionSource = pi,
                                    modifier = Modifier.fillMaxWidth().height(56.dp).pressScale(pi),
                                    shape = RoundedCornerShape(16.dp),
                                    colors = ButtonDefaults.buttonColors(containerColor = Accent, disabledContainerColor = Stroke),
                                    enabled = uiState.canSubmit
                                ) {
                                    Text(
                                        when {
                                            uiState.isLoading -> "Please wait…"
                                            uiState.isSignUp -> "Create account"
                                            else -> "Sign in"
                                        },
                                        fontSize = BodySize, fontWeight = FontWeight.Bold,
                                        color = if (uiState.canSubmit) Color.White else Text3
                                    )
                                }

                                // Two different intents, so two different weights: the mode
                                // switch is the accent-coloured one, recovery is quiet grey.
                                // Previously both were equal-weight and read as a pair.
                                Spacer(Modifier.height(GapS))
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    TextButton(onClick = { onSignUpToggle(!uiState.isSignUp) }) {
                                        Text(
                                            if (uiState.isSignUp) "I have an account" else "Create account",
                                            color = Accent, fontSize = CaptionSize, fontWeight = FontWeight.SemiBold
                                        )
                                    }
                                    // Reset is web-only — the app opens the site's flow.
                                    if (!uiState.isSignUp) {
                                        TextButton(onClick = onForgotPasswordClick) {
                                            Text(
                                                "Forgot password?",
                                                color = Text3, fontSize = CaptionSize, fontWeight = FontWeight.Normal
                                            )
                                        }
                                    }
                                }
                            }
                    }

                    Spacer(Modifier.height(GapM))

                    Text(
                        text = buildAnnotatedString {
                            append("By continuing, you agree to our ")
                            withStyle(SpanStyle(color = Accent, fontWeight = FontWeight.SemiBold)) {
                                append("Terms & Conditions")
                            }
                        },
                        fontSize = CaptionSize,
                        color = Text3,
                        textAlign = TextAlign.Center,
                        lineHeight = 18.sp
                    )
                }
            }
        }
    }
}

/**
 * The confirmation beat shown between a successful sign-in and the app taking over.
 * Green, not blue: on this screen blue means "act", green means "committed" — the
 * user has nothing left to do here.
 *
 * The check scales in once (spring, no bounce loop) so the moment registers without
 * turning into an animation the user has to wait through; [LoginViewModel] holds the
 * navigation for the same beat, so the panel is never cut off mid-transition.
 */
@Composable
private fun LoginSuccessPanel(name: String) {
    var shown by remember { mutableStateOf(false) }
    LaunchedEffect(Unit) { shown = true }
    val scale by animateFloatAsState(
        targetValue = if (shown) 1f else 0.6f,
        animationSpec = spring(dampingRatio = Spring.DampingRatioMediumBouncy, stiffness = Spring.StiffnessLow),
        label = "checkScale"
    )

    Column(
        modifier = Modifier.fillMaxWidth().padding(vertical = GapL),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Box(
            modifier = Modifier
                .size(64.dp)
                .graphicsLayer { scaleX = scale; scaleY = scale }
                .clip(CircleShape)
                .background(SuccessTint),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = Icons.Default.Check,
                contentDescription = null,
                tint = Success,
                modifier = Modifier.size(34.dp)
            )
        }
        Spacer(Modifier.height(GapM))
        Text(
            // The name is only known when they just signed up; returning users get the
            // neutral line rather than a greeting built from a guessed value.
            text = if (name.isNotBlank()) "Welcome, ${name.trim()}" else "You're signed in",
            fontSize = TitleSize,
            fontWeight = FontWeight.Bold,
            color = Text1,
            textAlign = TextAlign.Center
        )
        Spacer(Modifier.height(GapS))
        Text(
            text = "Taking you in…",
            fontSize = CaptionSize,
            fontWeight = FontWeight.Normal,
            color = Text2,
            textAlign = TextAlign.Center
        )
    }
}
