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
import androidx.compose.material.icons.outlined.Phone
import androidx.compose.material.icons.outlined.Visibility
import androidx.compose.material.icons.outlined.VisibilityOff
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.input.OffsetMapping
import androidx.compose.ui.text.input.TransformedText
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
import androidx.compose.ui.text.withLink
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.haraan.app.R
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.yield
import com.haraan.app.ui.theme.HaraanColors

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
    // Firebase phone-auth needs an Activity for its app-check (reCAPTCHA / Play Integrity).
    val activity = context as? android.app.Activity

    LoginScreen(
        uiState = uiState,
        onEmailChange = viewModel::onEmailChange,
        onPasswordChange = viewModel::onPasswordChange,
        onNameChange = viewModel::onNameChange,
        onSignUpToggle = viewModel::setSignUp,
        onSubmitClick = { viewModel.signInWithPassword(onLoginSuccess) },
        onExitForm = viewModel::clearMessages,
        onPhoneChange = viewModel::onPhoneChange,
        onOtpChange = viewModel::onOtpChange,
        // "Continue with phone": drive Firebase phone-auth (needs an Activity), then the VM
        // hands the resulting Firebase token to the backend for an app JWT.
        onSendPhoneCode = {
            if (activity != null) viewModel.sendPhoneCode(activity, onLoginSuccess)
            else viewModel.onGoogleError("Phone sign-in isn't available here.")
        },
        onVerifyPhoneCode = { viewModel.verifyPhoneCode(onLoginSuccess) },
        onResetPhone = viewModel::resetPhone,
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
private val Accent = HaraanColors.EventsBlue
private val Text1 = HaraanColors.TextPrimary
private val Text2 = HaraanColors.TextSecondary
private val Text3 = HaraanColors.TextMuted
private val Stroke = HaraanColors.BorderLight
private val FieldBg = HaraanColors.Background
private val FrostFill = Color.White.copy(alpha = 0.16f)
private val FrostBorder = Color.White.copy(alpha = 0.30f)
private val Danger = HaraanColors.Danger
// Green is reserved for the committed/done state — blue stays the "act now" colour.
private val Success = HaraanColors.Success
private val SuccessTint = HaraanColors.SuccessTint

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
    onPhoneChange: (String) -> Unit = {},
    onOtpChange: (String) -> Unit = {},
    onSendPhoneCode: () -> Unit = {},
    onVerifyPhoneCode: () -> Unit = {},
    onResetPhone: () -> Unit = {},
    modifier: Modifier = Modifier
) {
    var isDetailsInputVisible by remember { mutableStateOf(false) }
    // Parallel to email: the landing card expands into the phone-number / code flow.
    var isPhoneInputVisible by remember { mutableStateOf(false) }
    val view = LocalView.current

    // Expanding the card into the credentials form is a navigation step, so Back has to
    // undo it. Without this the whole login screen is the Activity's only destination and
    // Back fell straight through to the system — the app closed instead of going back to
    // the Google/email choice. Unwinds one step at a time (sign-up → sign-in → landing).
    // Disabled during Success so Back can't interrupt the hand-off to the app.
    BackHandler(enabled = (isDetailsInputVisible || isPhoneInputVisible) && uiState.stage != LoginStage.Success) {
        when {
            // Phone: code step → number step → landing.
            isPhoneInputVisible && uiState.phoneCodeSent -> onResetPhone()
            isPhoneInputVisible -> {
                isPhoneInputVisible = false
                onResetPhone()
                onExitForm()
            }
            uiState.isSignUp -> onSignUpToggle(false)
            else -> {
                isDetailsInputVisible = false
                onExitForm()
            }
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

    // Poster images — fetched from the admin API; falls back to local drawables if offline.
    // AUTO-REFRESH: fetch on resume + re-poll every 6s while the login screen is visible, so
    // an admin change in /control appears here within seconds — no need to background/reopen.
    // AutoRefresh binds to RESUMED, so it pauses in the background and costs nothing off-screen.
    val localPosters = listOf(R.drawable.poster1, R.drawable.poster2, R.drawable.poster3)
    var remotePosters by remember { mutableStateOf<List<String>>(emptyList()) }
    com.haraan.app.ui.components.AutoRefresh(intervalMs = 6_000L) {
        withContext(Dispatchers.IO) {
            runCatching {
                // Cache-bust so no proxy/HTTP cache can serve a stale list — freshness is the
                // whole point. Payload is a few hundred bytes, so a 6s poll is cheap.
                val url = "${ApiConfig.BASE_URL}/api/login-posters?t=${System.currentTimeMillis()}"
                val conn = (java.net.URL(url).openConnection() as java.net.HttpURLConnection).apply {
                    connectTimeout = 8000; readTimeout = 8000
                    setRequestProperty("Cache-Control", "no-cache")
                }
                val json = conn.inputStream.bufferedReader().use { it.readText() }
                val arr = JSONArray(json)
                (0 until arr.length()).mapNotNull { i ->
                    arr.getJSONObject(i).optString("image").takeIf { it.isNotBlank() }
                }
            }
            // Only overwrite on a successful fetch: a network failure keeps the posters we
            // already have (no flicker to the local fallback), while an empty-but-successful
            // response correctly reflects the admin having removed every poster. Skip the
            // reassignment when unchanged so we don't recompose the pager needlessly.
            .onSuccess { fresh -> if (fresh != remotePosters) remotePosters = fresh }
        }
    }
    val hasRemote = remotePosters.isNotEmpty()
    val posterCount = if (hasRemote) remotePosters.size else localPosters.size

    Box(
        modifier = modifier
            .fillMaxSize()
            .background(Ink)
    ) {
        // 1. Full-bleed poster pager (static image, auto-advancing carousel).
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
            // Static poster — no Ken-Burns zoom (fixed in place, by request). The
            // carousel still slides between posters; only the per-poster zoom is gone.
            val imgModifier = Modifier.fillMaxSize()
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
        // Bound the phone/OTP step ONLY while the keyboard is up. With it down there is
        // no surplus to distribute, and filling then swallowed the hero entirely — the
        // landing must keep its short sheet over the image.
        val stepIsBounded = isPhoneInputVisible &&
            WindowInsets.ime.getBottom(LocalDensity.current) > 0

        Column(
            modifier = Modifier
                .align(Alignment.BottomCenter)
                .fillMaxWidth()
                // The phone/OTP step needs a BOUNDED height. While the card wraps its
                // content there is no distributable space, so every attempt to move the
                // dead area just slid content toward the keyboard (see the note on the
                // card's insets). Filling here, plus weight() on the card, gives the step
                // a real height for its layout to work inside.
                .then(if (stepIsBounded) Modifier.fillMaxHeight() else Modifier),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            // Dots only in the collapsed hero — hidden once the keyboard appears so the
            // card has room and nothing clips.
            if (uiState.stage == LoginStage.EnterCredentials && !isDetailsInputVisible && !isPhoneInputVisible) {
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
                    // Bounded in the phone/OTP step (see the anchor above); wraps its
                    // content everywhere else so the landing keeps its short sheet over
                    // the hero.
                    .then(if (stepIsBounded) Modifier.weight(1f) else Modifier.wrapContentHeight())
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
                        .then(if (stepIsBounded) Modifier.fillMaxHeight() else Modifier)
                        // Insets live INSIDE the white card, so the region reserved for the
                        // keyboard / nav bar is white — never a strip of background image.
                        // Union (not sum) avoids double-counting the nav bar.
                        //
                        // THE EMPTY SPACE UNDER THE CODE STEP IS NOT AN INSET BUG. Three
                        // attempts on 2026-08-06, all measured on a Pixel_9, all reverted:
                        //   1. ime inset moved out to the anchor column so the card wraps
                        //      content -> card floats above the keyboard and the hero image
                        //      shows through the same surplus. Worse.
                        //   2. navigationBars.exclude(ime) -> gap unchanged, so it is not
                        //      nav-bar double-counting.
                        //   3. Splitting the surplus above/below the content -> the dead
                        //      white "shrank" 327px to 198px only because the content was
                        //      pushed DOWN under the keyboard; lifting all of it (226px)
                        //      hid the Verify button outright.
                        // The card is bottom-anchored AND wraps its content, so padding
                        // moves never remove space, they only slide content toward the
                        // keyboard. The real fix is to give this step a BOUNDED height and
                        // lay it out — header / content / action pinned to the bottom —
                        // rather than to keep tuning insets.
                        .windowInsetsPadding(WindowInsets.ime.union(WindowInsets.navigationBars))
                        .padding(start = GapL, end = GapL, top = GapM, bottom = GapL),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    // Centred only where the height is bounded. Anywhere else this is a
                    // no-op, and Top keeps the landing sheet exactly as it was.
                    verticalArrangement = if (stepIsBounded) Arrangement.Center else Arrangement.Top
                ) {
                    // Authenticated: hold a short confirmation instead of cutting straight
                    // to the app. The VM delays its success callback by the same beat.
                    if (uiState.stage == LoginStage.Success) {
                        LoginSuccessPanel(name = uiState.name)
                        return@Column
                    }

                    // Full branding only in the collapsed hero — hidden once the keyboard
                    // is up so the card stays compact and nothing clips at the top.
                    val showBranding = uiState.stage == LoginStage.EnterCredentials &&
                        !isDetailsInputVisible && !isPhoneInputVisible

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
                            isPhoneInputVisible && uiState.phoneCodeSent -> "Enter the code"
                            isPhoneInputVisible -> "Sign in with phone"
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
                            if (isPhoneInputVisible) {
                                // ── Phone sign-in (Firebase SMS OTP) ──────────────────────
                                // Two steps in one card: number → 6-digit code. The step is
                                // driven by whether Firebase has handed back a verificationId.
                                if (!uiState.phoneCodeSent) {
                                    // A real number field, not a labelled box: the country code
                                    // is a fixed affordance and the digits group as you type.
                                    // The old version was a stock Material field whose helper
                                    // text ("Add +country code for non-India numbers") was doing
                                    // the job of a control that hadn't been built.
                                    PhoneNumberField(
                                        value = uiState.phone,
                                        onValueChange = onPhoneChange,
                                        isError = !uiState.errorMessage.isNullOrEmpty(),
                                        onDone = { if (uiState.isPhoneValid) onSendPhoneCode() }
                                    )

                                    if (!uiState.errorMessage.isNullOrEmpty()) {
                                        Spacer(Modifier.height(GapM))
                                        Text(uiState.errorMessage, color = Danger, fontSize = CaptionSize, fontWeight = FontWeight.Medium, modifier = Modifier.fillMaxWidth(), textAlign = TextAlign.Center)
                                    }

                                    Spacer(Modifier.height(GapL))
                                    val si = remember { MutableInteractionSource() }
                                    Button(
                                        onClick = {
                                            view.performHapticFeedback(android.view.HapticFeedbackConstants.KEYBOARD_TAP)
                                            onSendPhoneCode()
                                        },
                                        interactionSource = si,
                                        modifier = Modifier.fillMaxWidth().height(56.dp).pressScale(si),
                                        shape = RoundedCornerShape(16.dp),
                                        colors = ButtonDefaults.buttonColors(containerColor = Accent, disabledContainerColor = Stroke),
                                        enabled = uiState.isPhoneValid && !uiState.isLoading
                                    ) {
                                        Text(
                                            if (uiState.isLoading) "Sending…" else "Send code",
                                            fontSize = BodySize, fontWeight = FontWeight.Bold,
                                            color = if (uiState.isPhoneValid) Color.White else Text3
                                        )
                                    }
                                } else {
                                    // Name the channel. We KNOW whether the code went over
                                    // WhatsApp or SMS (the backend says so), and saying which
                                    // is the one thing here no generic login screen can say —
                                    // it also tells the user which app to go look in.
                                    Text(
                                        text = buildAnnotatedString {
                                            append(if (uiState.phoneWaToken != null) "Sent on WhatsApp to " else "Sent by SMS to ")
                                            withStyle(SpanStyle(color = Text1, fontWeight = FontWeight.SemiBold)) {
                                                append(prettyPhone(uiState.phoneE164 ?: uiState.phone))
                                            }
                                        },
                                        fontSize = CaptionSize, color = Text2, textAlign = TextAlign.Center,
                                        modifier = Modifier.fillMaxWidth()
                                    )
                                    Spacer(Modifier.height(GapL))
                                    // Six cells, not one labelled box. A single "Verification
                                    // code" field is the stock Material default and reads as
                                    // unfinished; every app people already use for OTP shows
                                    // the digits landing one at a time.
                                    OtpCells(
                                        value = uiState.otp,
                                        onValueChange = onOtpChange,
                                        isError = !uiState.errorMessage.isNullOrEmpty(),
                                        onDone = { if (uiState.isOtpValid) onVerifyPhoneCode() }
                                    )

                                    // Auto-submit the moment the 6-digit code is complete — the user
                                    // (or the keyboard's SMS autofill) never has to tap Verify.
                                    // verifyPhoneCode() no-ops while loading; tracking the last
                                    // submitted value stops a wrong code from resubmitting in a loop.
                                    var lastAutoOtp by remember { mutableStateOf("") }
                                    LaunchedEffect(uiState.otp, uiState.isLoading) {
                                        if (uiState.isOtpValid && !uiState.isLoading && uiState.otp != lastAutoOtp) {
                                            lastAutoOtp = uiState.otp
                                            onVerifyPhoneCode()
                                        }
                                    }

                                    if (!uiState.errorMessage.isNullOrEmpty()) {
                                        Spacer(Modifier.height(GapM))
                                        Text(uiState.errorMessage, color = Danger, fontSize = CaptionSize, fontWeight = FontWeight.Medium, modifier = Modifier.fillMaxWidth(), textAlign = TextAlign.Center)
                                    }

                                    Spacer(Modifier.height(GapL))
                                    val vi = remember { MutableInteractionSource() }
                                    Button(
                                        onClick = {
                                            view.performHapticFeedback(android.view.HapticFeedbackConstants.KEYBOARD_TAP)
                                            onVerifyPhoneCode()
                                        },
                                        interactionSource = vi,
                                        modifier = Modifier.fillMaxWidth().height(56.dp).pressScale(vi),
                                        shape = RoundedCornerShape(16.dp),
                                        colors = ButtonDefaults.buttonColors(containerColor = Accent, disabledContainerColor = Stroke),
                                        enabled = uiState.isOtpValid && !uiState.isLoading
                                    ) {
                                        Text(
                                            // "Verify" — the "& continue" was filler. The button
                                            // is already the only thing to press.
                                            if (uiState.isLoading) "Verifying…" else "Verify",
                                            fontSize = BodySize, fontWeight = FontWeight.Bold,
                                            color = if (uiState.isOtpValid) Color.White else Text3
                                        )
                                    }

                                    Spacer(Modifier.height(GapS))
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        TextButton(onClick = onResetPhone) {
                                            Text("Change number", color = Accent, fontSize = CaptionSize, fontWeight = FontWeight.SemiBold)
                                        }
                                        // Resend gated by a short cooldown so we don't spam SMS.
                                        if (uiState.phoneResendSeconds > 0) {
                                            Text(
                                                "Resend in ${uiState.phoneResendSeconds}s",
                                                color = Text3, fontSize = CaptionSize, fontWeight = FontWeight.Normal
                                            )
                                        } else {
                                            TextButton(onClick = onSendPhoneCode, enabled = !uiState.isLoading) {
                                                Text("Resend code", color = Accent, fontSize = CaptionSize, fontWeight = FontWeight.SemiBold)
                                            }
                                        }
                                    }
                                }
                            } else if (!isDetailsInputVisible) {
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

                                    // Secondary ways in — phone then email, matching the
                                    // website's order (Google → Phone → email).
                                    Spacer(Modifier.height(GapM))
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.Center,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text(
                                            text = "Continue with phone",
                                            fontSize = CaptionSize,
                                            fontWeight = FontWeight.SemiBold,
                                            color = Accent,
                                            modifier = Modifier
                                                .clip(RoundedCornerShape(8.dp))
                                                .clickable {
                                                    view.performHapticFeedback(android.view.HapticFeedbackConstants.KEYBOARD_TAP)
                                                    onExitForm()
                                                    isPhoneInputVisible = true
                                                }
                                                .padding(horizontal = GapM, vertical = GapS)
                                        )
                                        Box(Modifier.size(width = 1.dp, height = 14.dp).background(Stroke))
                                        Text(
                                            text = "Continue with email",
                                            fontSize = CaptionSize,
                                            fontWeight = FontWeight.SemiBold,
                                            color = Accent,
                                            modifier = Modifier
                                                .clip(RoundedCornerShape(8.dp))
                                                .clickable {
                                                    view.performHapticFeedback(android.view.HapticFeedbackConstants.KEYBOARD_TAP)
                                                    isDetailsInputVisible = true
                                                }
                                                .padding(horizontal = GapM, vertical = GapS)
                                        )
                                    }
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

                                    // Secondary — phone sign-in as a plain text link.
                                    Spacer(Modifier.height(GapM))
                                    Text(
                                        text = "Continue with phone",
                                        fontSize = CaptionSize,
                                        fontWeight = FontWeight.SemiBold,
                                        color = Accent,
                                        textAlign = TextAlign.Center,
                                        modifier = Modifier
                                            .clip(RoundedCornerShape(8.dp))
                                            .clickable {
                                                view.performHapticFeedback(android.view.HapticFeedbackConstants.KEYBOARD_TAP)
                                                onExitForm()
                                                isPhoneInputVisible = true
                                            }
                                            .padding(horizontal = GapM, vertical = GapS)
                                    )
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
                        // Real tappable links (not just styled text) — each opens the live
                        // document so the legal copy is reachable before a user signs up.
                        text = buildAnnotatedString {
                            append("By continuing, you agree to our ")
                            withLink(androidx.compose.ui.text.LinkAnnotation.Url("${ApiConfig.BASE_URL}/legal/terms")) {
                                withStyle(SpanStyle(color = Accent, fontWeight = FontWeight.SemiBold)) {
                                    append("Terms & Conditions")
                                }
                            }
                            append(" and ")
                            withLink(androidx.compose.ui.text.LinkAnnotation.Url("${ApiConfig.BASE_URL}/legal/privacy")) {
                                withStyle(SpanStyle(color = Accent, fontWeight = FontWeight.SemiBold)) {
                                    append("Privacy Policy")
                                }
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

// ── Phone + OTP input ───────────────────────────────────────────────────────
// Both of these replaced stock Material fields. The stock versions were not ugly,
// they were *generic*: a floating-label box for a phone number and a second one
// for a 6-digit code is what a framework gives you before anyone has designed the
// screen, and that is exactly what reads as unfinished. What follows is what the
// apps these users already open every day do — a fixed country code, digits that
// group as you type, and a code that lands one cell at a time.

/** "98765 43210" — the grouping Indian numbers are read and spoken in. */
private fun groupIndianDigits(digits: String): String =
    if (digits.length <= 5) digits else digits.take(5) + " " + digits.drop(5)

/** For the "Sent on WhatsApp to …" line: "+91 97013 77681". */
private fun prettyPhone(raw: String): String {
    val d = raw.filter { it.isDigit() }
    return if (raw.startsWith("+91") && d.length == 12) "+91 " + groupIndianDigits(d.drop(2)) else raw
}

/**
 * Groups the local part as it is typed without touching the stored value, so the
 * ViewModel keeps raw digits and the cursor still lands where the user expects.
 * [OffsetMapping] is what keeps the caret maths honest — dropping it is how these
 * fields end up putting the cursor in the wrong place after an edit.
 */
private val IndianGroupingTransformation = VisualTransformation { text ->
    val digits = text.text
    if (digits.startsWith("+")) {
        TransformedText(text, OffsetMapping.Identity)
    } else {
        TransformedText(
            androidx.compose.ui.text.AnnotatedString(groupIndianDigits(digits)),
            object : OffsetMapping {
                override fun originalToTransformed(offset: Int) = if (offset <= 5) offset else offset + 1
                override fun transformedToOriginal(offset: Int) = if (offset <= 5) offset else offset - 1
            }
        )
    }
}

/**
 * Number entry with the country code as a real part of the control.
 *
 * The `+91` is a fixed segment behind a hairline, not prose telling the user to
 * type one — but it steps out of the way the moment the value starts with `+`, so
 * an international number still works exactly as it did.
 */
@Composable
private fun PhoneNumberField(
    value: String,
    onValueChange: (String) -> Unit,
    isError: Boolean,
    onDone: () -> Unit,
) {
    val international = value.startsWith("+")

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .height(58.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(Color(0xFFF8FAFC))
            .border(
                width = if (isError) 1.5.dp else 1.dp,
                color = if (isError) Danger else Stroke,
                shape = RoundedCornerShape(16.dp)
            ),
        verticalAlignment = Alignment.CenterVertically
    ) {
        if (!international) {
            Text(
                text = "+91",
                color = Text1,
                fontSize = BodySize,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier.padding(start = 18.dp, end = 12.dp)
            )
            Box(
                modifier = Modifier
                    .width(1.dp)
                    .height(24.dp)
                    .background(Stroke)
            )
        }

        Box(
            modifier = Modifier
                .weight(1f)
                .padding(horizontal = 14.dp),
            contentAlignment = Alignment.CenterStart
        ) {
            if (value.isEmpty()) {
                Text("98765 43210", color = Text3, fontSize = BodySize)
            }
            BasicTextField(
                value = value,
                onValueChange = onValueChange,
                singleLine = true,
                textStyle = TextStyle(
                    color = Text1,
                    fontSize = BodySize,
                    fontWeight = FontWeight.SemiBold,
                    // Wider than body text: a phone number is read in chunks, and the
                    // extra tracking is what makes the grouping legible at a glance.
                    letterSpacing = 0.8.sp
                ),
                cursorBrush = SolidColor(Accent),
                visualTransformation = IndianGroupingTransformation,
                keyboardOptions = KeyboardOptions(
                    keyboardType = KeyboardType.Phone,
                    imeAction = ImeAction.Done
                ),
                keyboardActions = KeyboardActions(onDone = { onDone() }),
                modifier = Modifier.fillMaxWidth()
            )
        }
    }
}

/**
 * Six cells for a six-digit code.
 *
 * One transparent [BasicTextField] stretched across the row owns the text, and the
 * cells are drawn from its value. That is deliberate: it keeps the real keyboard,
 * paste, and the SMS/WhatsApp autofill the platform already gives us — hand-rolling
 * six separate fields is how you lose all three and gain a focus-management bug.
 */
@Composable
private fun OtpCells(
    value: String,
    onValueChange: (String) -> Unit,
    isError: Boolean,
    onDone: () -> Unit,
    count: Int = 6,
) {
    val focus = remember { FocusRequester() }
    val view = LocalView.current
    // The code screen exists to be typed into; opening it focused saves a tap.
    LaunchedEffect(Unit) { runCatching { focus.requestFocus() } }

    // A tick per digit. The screen was silent in the hand — digits appeared and
    // nothing else happened, which is most of what "no feel" actually means.
    LaunchedEffect(value.length) {
        if (value.isNotEmpty()) {
            view.performHapticFeedback(android.view.HapticFeedbackConstants.KEYBOARD_TAP)
        }
    }

    // Wrong code: shake the row and buzz once, so the failure is felt where it
    // happened rather than only announced in red text below.
    val shake = remember { androidx.compose.animation.core.Animatable(0f) }
    LaunchedEffect(isError) {
        if (isError) {
            view.performHapticFeedback(android.view.HapticFeedbackConstants.LONG_PRESS)
            // Decaying left-right, not a single lurch — a lurch reads as a layout bug.
            listOf(14f, -11f, 8f, -5f, 0f).forEach {
                shake.animateTo(it, tween(durationMillis = 48, easing = LinearEasing))
            }
        } else {
            shake.snapTo(0f)
        }
    }

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .graphicsLayer { translationX = shake.value }
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            repeat(count) { i ->
                val char = value.getOrNull(i)
                val filled = char != null
                // The caret cell is the next empty one, and stays put once the code is
                // complete so the row never looks unfocused mid-verify.
                val isCaret = i == value.length.coerceAtMost(count - 1) && value.length < count

                // The digit lands with a small spring instead of appearing. This is the
                // per-keystroke feedback the row was missing — keep the overshoot low,
                // a bouncy cell reads as a toy.
                val pop by animateFloatAsState(
                    targetValue = if (filled) 1f else 0.94f,
                    animationSpec = spring(dampingRatio = 0.55f, stiffness = 900f),
                    label = "otpCellPop"
                )

                Box(
                    modifier = Modifier
                        .weight(1f)
                        .height(56.dp)
                        .graphicsLayer { scaleX = pop; scaleY = pop }
                        .clip(RoundedCornerShape(14.dp))
                        .background(if (filled) Color.White else Color(0xFFF8FAFC))
                        .border(
                            width = if (isError || isCaret) 1.5.dp else 1.dp,
                            color = when {
                                isError -> Danger
                                isCaret -> Accent
                                filled -> Text3.copy(alpha = 0.55f)
                                else -> Stroke
                            },
                            shape = RoundedCornerShape(14.dp)
                        ),
                    contentAlignment = Alignment.Center
                ) {
                    if (filled) {
                        Text(
                            text = char.toString(),
                            color = Text1,
                            fontSize = 22.sp,
                            fontWeight = FontWeight.Bold
                        )
                    } else if (isCaret) {
                        // A resting caret in the active cell — the cue that typing goes
                        // here, without a blinking cursor to chase.
                        Box(
                            modifier = Modifier
                                .width(2.dp)
                                .height(22.dp)
                                .background(Accent.copy(alpha = 0.55f))
                        )
                    }
                }
            }
        }

        // The real input: invisible, full-bleed over the cells, so a tap anywhere on
        // the row opens the keyboard and the platform autofill still targets it.
        BasicTextField(
            value = value,
            onValueChange = { onValueChange(it.filter(Char::isDigit).take(count)) },
            singleLine = true,
            textStyle = TextStyle(color = Color.Transparent, fontSize = 1.sp),
            cursorBrush = SolidColor(Color.Transparent),
            keyboardOptions = KeyboardOptions(
                keyboardType = KeyboardType.NumberPassword,
                imeAction = ImeAction.Done
            ),
            keyboardActions = KeyboardActions(onDone = { onDone() }),
            modifier = Modifier
                .matchParentSize()
                .focusRequester(focus)
        )
    }
}
