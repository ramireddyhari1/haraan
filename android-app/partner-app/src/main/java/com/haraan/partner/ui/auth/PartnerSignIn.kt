package com.haraan.partner.ui.auth

import androidx.activity.compose.BackHandler
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.AnimationVector1D
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.keyframes
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.isImeVisible
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.LinkAnnotation
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.TextLinkStyles
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.OffsetMapping
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.TransformedText
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.withLink
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.partner.ApiConfig
import com.haraan.partner.ApiException
import com.haraan.partner.GoogleSignInHelper
import com.haraan.partner.GoogleSignInResult
import com.haraan.partner.LoginResult
import com.haraan.partner.PartnerApi
import com.haraan.partner.R
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.pressScale
import com.haraan.partner.ui.components.pressShade
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlin.math.roundToInt

private val Blue = Color(0xFF2563EB)
private val BlueInk = Color(0xFF1D4ED8)
private val NavyTop = Color(0xFF0B1A3F)
private val NavyBot = Color(0xFF081230)
private val Ink = Color(0xFF0B1220)
private val Muted = Color(0xFF6B7688)
private val Faint = Color(0xFFA3ABB9)
private val Fill = Color(0xFFF3F5F9)
private val Hairline = Color(0xFFE4E8EF)
private val Red = Color(0xFFDC2626)
private val Valid = Color(0xFF16A34A)
private val WhatsApp = Color(0xFF25D366)

/** Seconds before "Resend code" comes back. A client rule, said as one. */
private const val RESEND_SECONDS = 30

private enum class Step { Phone, Code, Email }

/**
 * The partner app's front door, phone-first like every ticketing app these owners
 * already use: number → WhatsApp code → in. Google and email sit one tap away.
 *
 * - The navy band carries the brand and one true line about the product, over a court
 *   drawn in hairlines. When the keyboard opens, the headline folds away so the form
 *   never hides under the keys.
 * - The white sheet rises in on first open. Steps slide sideways, forward to the right.
 * - The code lands digit by digit in six cells driven by ONE text field (so paste and
 *   keyboard suggestions still work), verifies itself on the sixth digit, and shakes
 *   with a reject buzz when it's wrong.
 *
 * [onLogin] confirms the account is a partner and stores the session; it throws when
 * it isn't, and that message is shown like any other.
 */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun PartnerSignIn(api: PartnerApi, onLogin: suspend (LoginResult) -> Unit) {
    val view = LocalView.current
    val context = LocalContext.current
    val focus = LocalFocusManager.current
    val scope = rememberCoroutineScope()

    var step by rememberSaveable { mutableStateOf(Step.Phone) }
    var phone by rememberSaveable { mutableStateOf("") }
    var otpToken by rememberSaveable { mutableStateOf<String?>(null) }
    var email by rememberSaveable { mutableStateOf("") }
    var code by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    // Which door is busy, so only that button spins and the others stay still.
    var busy by remember { mutableStateOf<String?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var resendAt by remember { mutableLongStateOf(0L) }
    val shake = remember { Animatable(0f) }

    fun fail(message: String?) {
        error = message?.takeIf { it.isNotBlank() } ?: "Something went wrong. Try again."
        Haptics.reject(view)
        scope.launch { shake(shake) }
    }

    fun go(to: Step) {
        error = null
        step = to
    }

    BackHandler(enabled = step != Step.Phone) {
        code = ""
        go(Step.Phone)
    }

    fun sendCode() {
        if (busy != null || phone.length != 10) return
        focus.clearFocus()
        busy = "phone"; error = null
        scope.launch {
            try {
                val s = api.startPhoneOtp(phone)
                if (s.channel == "whatsapp" && !s.token.isNullOrBlank()) {
                    otpToken = s.token
                    code = ""
                    resendAt = System.currentTimeMillis() + RESEND_SECONDS * 1000L
                    Haptics.confirm(view)
                    go(Step.Code)
                } else {
                    fail("We couldn't reach WhatsApp on this number. Sign in with Google or email instead.")
                }
            } catch (e: ApiException) { fail(e.message) }
            catch (e: Exception) { fail("Couldn't send the code. Check your connection.") }
            finally { busy = null }
        }
    }

    fun verify(entered: String) {
        val token = otpToken ?: return
        if (busy != null) return
        busy = "code"; error = null
        scope.launch {
            try { onLogin(api.verifyPhoneOtp(token, entered)) }
            catch (e: ApiException) { code = ""; fail(e.message) }
            catch (e: Exception) { code = ""; fail(e.message ?: "Couldn't check the code.") }
            finally { busy = null }
        }
    }

    fun emailSignIn() {
        if (busy != null || email.isBlank() || password.isBlank()) return
        focus.clearFocus()
        busy = "email"; error = null
        scope.launch {
            try { onLogin(api.login(email.trim(), password)) }
            catch (e: ApiException) { fail(e.message) }
            catch (e: Exception) { fail(e.message ?: "Unable to sign in.") }
            finally { busy = null }
        }
    }

    fun google() {
        if (busy != null) return
        busy = "google"; error = null
        scope.launch {
            when (val r = GoogleSignInHelper.signIn(context)) {
                is GoogleSignInResult.Success ->
                    try { onLogin(api.google(r.idToken)) }
                    catch (e: ApiException) { fail(e.message) }
                    catch (e: Exception) { fail(e.message ?: "Unable to sign in.") }
                is GoogleSignInResult.Cancelled -> {}
                is GoogleSignInResult.Error -> fail(r.message)
            }
            busy = null
        }
    }

    // The sheet rises once, on first open.
    val rise = remember { Animatable(0f) }
    LaunchedEffect(Unit) { rise.animateTo(1f, spring(dampingRatio = 0.82f, stiffness = Spring.StiffnessLow)) }

    val keyboard = WindowInsets.isImeVisible

    // The sheet sits on the bottom edge at its own height and the band takes whatever
    // is left above it, so a tall phone gets more navy, never an empty white gap.
    var sheetHeight by remember { mutableIntStateOf(0) }
    val density = LocalDensity.current

    Box(
        Modifier
            .fillMaxSize()
            .background(NavyBot)
            .imePadding(),
    ) {
        Band(
            compact = keyboard,
            sheetHeight = with(density) { sheetHeight.toDp() },
            modifier = Modifier.fillMaxSize(),
        )

        Column(
            Modifier
                .align(Alignment.BottomCenter)
                .fillMaxWidth()
                .onSizeChanged { sheetHeight = it.height }
                .graphicsLayer {
                    translationY = (1f - rise.value) * 60.dp.toPx()
                    alpha = rise.value.coerceIn(0f, 1f)
                }
                .clip(RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp))
                .background(Color.White)
                .verticalScroll(rememberScrollState()),
        ) {
            Column(
                Modifier
                    .fillMaxWidth()
                    .padding(start = 24.dp, end = 24.dp, top = 28.dp, bottom = 8.dp),
            ) {
                AnimatedContent(
                    targetState = step,
                    transitionSpec = {
                        val forward = targetState.ordinal > initialState.ordinal || initialState == Step.Phone
                        val dir = if (forward) 1 else -1
                        (slideInHorizontally(spring(dampingRatio = 0.9f, stiffness = Spring.StiffnessMediumLow)) { it * dir / 3 } + fadeIn(tween(200))) togetherWith
                            (slideOutHorizontally(tween(160)) { -it * dir / 3 } + fadeOut(tween(120)))
                    },
                    label = "sign-in-step",
                ) { s ->
                    Column(Modifier.fillMaxWidth()) {
                        when (s) {
                            Step.Phone -> PhoneStep(
                                phone = phone,
                                onPhone = { phone = it; error = null },
                                busy = busy,
                                error = error,
                                shake = shake,
                                onContinue = ::sendCode,
                                onGoogle = if (GoogleSignInHelper.isConfigured) ::google else null,
                                onEmail = { go(Step.Email) },
                            )
                            Step.Code -> CodeStep(
                                phone = phone,
                                code = code,
                                onCode = { c ->
                                    code = c
                                    error = null
                                    if (c.length == 6) verify(c)
                                },
                                busy = busy,
                                error = error,
                                shake = shake,
                                resendAt = resendAt,
                                onResend = ::sendCode,
                                onChangeNumber = { code = ""; go(Step.Phone) },
                            )
                            Step.Email -> EmailStep(
                                email = email,
                                onEmail = { email = it; error = null },
                                password = password,
                                onPassword = { password = it; error = null },
                                busy = busy,
                                error = error,
                                shake = shake,
                                onSignIn = ::emailSignIn,
                                onUsePhone = { go(Step.Phone) },
                            )
                        }
                    }
                }
            }

            AnimatedVisibility(!keyboard, enter = fadeIn() + expandVertically(), exit = fadeOut() + shrinkVertically()) {
                Terms(Modifier.navigationBarsPadding().padding(start = 32.dp, end = 32.dp, top = 14.dp, bottom = 18.dp))
            }
        }
    }
}

// ---- Steps -----------------------------------------------------------------------

@Composable
private fun PhoneStep(
    phone: String,
    onPhone: (String) -> Unit,
    busy: String?,
    error: String?,
    shake: Animatable<Float, AnimationVector1D>,
    onContinue: () -> Unit,
    onGoogle: (() -> Unit)?,
    onEmail: () -> Unit,
) {
    Title("Sign in", "Use the mobile number your venue or events are registered with.")
    Spacer(Modifier.height(22.dp))
    PhoneInput(
        value = phone,
        onValueChange = onPhone,
        isError = error != null,
        onDone = onContinue,
        modifier = Modifier.offset { IntOffset(shake.value.roundToInt(), 0) },
    )
    Spacer(Modifier.height(10.dp))
    if (error != null) ErrorLine(error) else {
        Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(start = 4.dp)) {
            WhatsAppGlyph(Modifier.size(14.dp))
            Spacer(Modifier.width(7.dp))
            Text("We'll send a 6-digit code on WhatsApp", fontSize = 12.5.sp, color = Muted)
        }
    }
    Spacer(Modifier.height(22.dp))
    PrimaryButton(
        text = if (busy == "phone") "Sending code" else "Continue",
        enabled = phone.length == 10 && busy == null,
        loading = busy == "phone",
        onClick = onContinue,
    )

    Spacer(Modifier.height(26.dp))
    OrRule()
    Spacer(Modifier.height(18.dp))
    Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        if (onGoogle != null) {
            AltButton(
                text = "Google",
                loading = busy == "google",
                enabled = busy == null,
                modifier = Modifier.weight(1f),
                leading = { Image(painterResource(R.drawable.ic_google_logo), null, Modifier.size(18.dp)) },
                onClick = onGoogle,
            )
        }
        AltButton(
            text = "Email",
            loading = false,
            enabled = busy == null,
            modifier = Modifier.weight(1f),
            leading = { MailGlyph(Modifier.size(width = 18.dp, height = 14.dp)) },
            onClick = onEmail,
        )
    }
}

@Composable
private fun CodeStep(
    phone: String,
    code: String,
    onCode: (String) -> Unit,
    busy: String?,
    error: String?,
    shake: Animatable<Float, AnimationVector1D>,
    resendAt: Long,
    onResend: () -> Unit,
    onChangeNumber: () -> Unit,
) {
    val focus = remember { FocusRequester() }
    LaunchedEffect(Unit) {
        delay(220)
        runCatching { focus.requestFocus() }
    }
    Text("Enter the code", fontSize = 22.sp, fontWeight = FontWeight.Bold, color = Ink, letterSpacing = (-0.3).sp)
    Spacer(Modifier.height(6.dp))
    Row(verticalAlignment = Alignment.CenterVertically) {
        WhatsAppGlyph(Modifier.size(14.dp))
        Spacer(Modifier.width(7.dp))
        Text(
            "Sent on WhatsApp to +91 ${groupPhone(phone)}",
            fontSize = 13.5.sp, color = Muted, modifier = Modifier.weight(1f, fill = false),
        )
        Spacer(Modifier.width(8.dp))
        TextLink("Change", onChangeNumber)
    }
    Spacer(Modifier.height(24.dp))
    CodeCells(
        code = code,
        onCode = onCode,
        isError = error != null,
        enabled = busy == null,
        focusRequester = focus,
        modifier = Modifier.offset { IntOffset(shake.value.roundToInt(), 0) },
    )
    Spacer(Modifier.height(14.dp))
    Box(Modifier.fillMaxWidth().height(22.dp), contentAlignment = Alignment.CenterStart) {
        when {
            busy == "code" -> Row(verticalAlignment = Alignment.CenterVertically) {
                CircularProgressIndicator(color = Blue, strokeWidth = 2.dp, modifier = Modifier.size(14.dp))
                Spacer(Modifier.width(9.dp))
                Text("Checking the code", fontSize = 13.sp, color = Muted)
            }
            error != null -> ErrorLine(error)
        }
    }
    Spacer(Modifier.height(18.dp))
    ResendLine(resendAt = resendAt, enabled = busy == null, onResend = onResend)
}

@Composable
private fun EmailStep(
    email: String,
    onEmail: (String) -> Unit,
    password: String,
    onPassword: (String) -> Unit,
    busy: String?,
    error: String?,
    shake: Animatable<Float, AnimationVector1D>,
    onSignIn: () -> Unit,
    onUsePhone: () -> Unit,
) {
    val focus = LocalFocusManager.current
    var showPw by remember { mutableStateOf(false) }
    val view = LocalView.current
    Title("Sign in with email", "The email and password your Haraan partner account was set up with.")
    Spacer(Modifier.height(22.dp))
    Column(Modifier.offset { IntOffset(shake.value.roundToInt(), 0) }) {
        TextInput(
            value = email,
            onValueChange = onEmail,
            placeholder = "Email address",
            isError = error != null,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email, imeAction = ImeAction.Next),
            keyboardActions = KeyboardActions(onNext = { focus.moveFocus(androidx.compose.ui.focus.FocusDirection.Down) }),
        )
        Spacer(Modifier.height(10.dp))
        TextInput(
            value = password,
            onValueChange = onPassword,
            placeholder = "Password",
            isError = error != null,
            visualTransformation = if (showPw) VisualTransformation.None else PasswordVisualTransformation(),
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password, imeAction = ImeAction.Done),
            keyboardActions = KeyboardActions(onDone = { onSignIn() }),
            trailing = {
                if (password.isNotEmpty()) {
                    TextLink(if (showPw) "Hide" else "Show") {
                        Haptics.tick(view)
                        showPw = !showPw
                    }
                }
            },
        )
    }
    Spacer(Modifier.height(10.dp))
    if (error != null) ErrorLine(error)
    Spacer(Modifier.height(18.dp))
    PrimaryButton(
        text = if (busy == "email") "Signing in" else "Sign in",
        enabled = email.isNotBlank() && password.isNotBlank() && busy == null,
        loading = busy == "email",
        onClick = onSignIn,
    )
    Spacer(Modifier.height(20.dp))
    Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
        TextLink("Use mobile number instead", onUsePhone)
    }
}

// ---- Band ------------------------------------------------------------------------

/**
 * Navy, with a court drawn in hairlines bleeding off the right edge: the one shape
 * every partner's business is built on, kept faint enough to stay texture.
 */
@Composable
private fun Band(compact: Boolean, sheetHeight: androidx.compose.ui.unit.Dp, modifier: Modifier = Modifier) {
    Box(
        modifier.background(Brush.verticalGradient(listOf(NavyTop, NavyBot))),
    ) {
        Canvas(Modifier.matchParentSize()) { courtTexture() }
        // Logo at the top; the headline rests just above the sheet's edge.
        Column(
            Modifier
                .fillMaxSize()
                .statusBarsPadding()
                .padding(start = 24.dp, end = 24.dp, top = 18.dp, bottom = if (compact) 0.dp else sheetHeight + 26.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Image(
                    painter = painterResource(R.drawable.haraan_logo_white),
                    contentDescription = "Haraan",
                    contentScale = ContentScale.Fit,
                    modifier = Modifier.height(21.dp),
                )
                Spacer(Modifier.width(11.dp))
                Box(Modifier.width(1.dp).height(14.dp).background(Color.White.copy(alpha = 0.28f)))
                Spacer(Modifier.width(11.dp))
                Text(
                    "PARTNER", fontSize = 10.sp, fontWeight = FontWeight.SemiBold,
                    letterSpacing = 2.6.sp, color = Color.White.copy(alpha = 0.72f),
                )
            }
            Spacer(Modifier.weight(1f))
            AnimatedVisibility(
                visible = !compact,
                enter = expandVertically(spring(dampingRatio = 0.9f)) + fadeIn(tween(220, delayMillis = 60)),
                exit = shrinkVertically(tween(200)) + fadeOut(tween(120)),
            ) {
                Column {
                    Text(
                        "Run your venue.\nFill every show.",
                        fontSize = 29.sp, lineHeight = 34.sp, fontWeight = FontWeight.Bold,
                        letterSpacing = (-0.6).sp, color = Color.White,
                    )
                    Spacer(Modifier.height(22.dp))
                    SpecRow()
                }
            }
        }
    }
}

/** What the app does, as a ruled row of facts. Hairlines, not buttons: nothing here is tappable. */
@Composable
private fun SpecRow() {
    val line = Color.White.copy(alpha = 0.16f)
    Column {
        Box(Modifier.fillMaxWidth().height(1.dp).background(line))
        Row(Modifier.fillMaxWidth().padding(top = 14.dp), verticalAlignment = Alignment.CenterVertically) {
            SpecItem("Bookings", Modifier.weight(1f)) { courtIcon(it) }
            Box(Modifier.width(1.dp).height(18.dp).background(line))
            SpecItem("Gate check-in", Modifier.weight(1.25f)) { ticketIcon(it) }
            Box(Modifier.width(1.dp).height(18.dp).background(line))
            SpecItem("Payouts", Modifier.weight(1f)) { barsIcon(it) }
        }
    }
}

@Composable
private fun SpecItem(label: String, modifier: Modifier, icon: DrawScope.(Color) -> Unit) {
    Row(modifier, horizontalArrangement = Arrangement.Center, verticalAlignment = Alignment.CenterVertically) {
        val c = Color(0xFFBFD3FF)
        Canvas(Modifier.size(16.dp)) { icon(c) }
        Spacer(Modifier.width(7.dp))
        Text(label, fontSize = 12.sp, fontWeight = FontWeight.Medium, color = Color.White.copy(alpha = 0.82f), maxLines = 1)
    }
}

// ---- Inputs ----------------------------------------------------------------------

@Composable
private fun PhoneInput(
    value: String,
    onValueChange: (String) -> Unit,
    isError: Boolean,
    onDone: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val interaction = remember { MutableInteractionSource() }
    val focused by interaction.collectIsFocusedAsState()
    val view = LocalView.current
    FieldShell(focused = focused, isError = isError, modifier = modifier) {
        IndiaFlag(Modifier.size(width = 22.dp, height = 15.dp))
        Spacer(Modifier.width(9.dp))
        Text("+91", fontSize = 16.sp, fontWeight = FontWeight.SemiBold, color = Ink, style = TextStyle(fontFeatureSettings = "tnum"))
        Spacer(Modifier.width(12.dp))
        Box(Modifier.width(1.dp).height(22.dp).background(Hairline))
        Spacer(Modifier.width(12.dp))
        Box(Modifier.weight(1f), contentAlignment = Alignment.CenterStart) {
            if (value.isEmpty()) Text("98765 43210", fontSize = 16.sp, color = Faint, letterSpacing = 0.4.sp)
            BasicTextField(
                value = value,
                onValueChange = { raw ->
                    val digits = raw.filter(Char::isDigit)
                    // A pasted "+91 98765 43210" keeps its last ten; typing stops at ten.
                    val next = if (digits.length > 10 && digits.length - value.length > 1) digits.takeLast(10) else digits.take(10)
                    if (next.length == 10 && value.length != 10) Haptics.confirm(view)
                    onValueChange(next)
                },
                singleLine = true,
                interactionSource = interaction,
                visualTransformation = PhoneGrouping,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone, imeAction = ImeAction.Go),
                keyboardActions = KeyboardActions(onGo = { onDone() }),
                textStyle = TextStyle(fontSize = 16.sp, color = Ink, fontWeight = FontWeight.SemiBold, letterSpacing = 0.4.sp, fontFeatureSettings = "tnum"),
                cursorBrush = SolidColor(Blue),
                modifier = Modifier.fillMaxWidth(),
            )
        }
        DrawnCheck(visible = value.length == 10, modifier = Modifier.padding(start = 8.dp).size(20.dp))
    }
}

@Composable
private fun TextInput(
    value: String,
    onValueChange: (String) -> Unit,
    placeholder: String,
    isError: Boolean,
    keyboardOptions: KeyboardOptions,
    keyboardActions: KeyboardActions,
    visualTransformation: VisualTransformation = VisualTransformation.None,
    trailing: (@Composable () -> Unit)? = null,
) {
    val interaction = remember { MutableInteractionSource() }
    val focused by interaction.collectIsFocusedAsState()
    FieldShell(focused = focused, isError = isError) {
        Box(Modifier.weight(1f), contentAlignment = Alignment.CenterStart) {
            if (value.isEmpty()) Text(placeholder, fontSize = 15.5.sp, color = Faint)
            BasicTextField(
                value = value,
                onValueChange = onValueChange,
                singleLine = true,
                interactionSource = interaction,
                visualTransformation = visualTransformation,
                keyboardOptions = keyboardOptions,
                keyboardActions = keyboardActions,
                textStyle = TextStyle(fontSize = 15.5.sp, color = Ink, fontWeight = FontWeight.SemiBold),
                cursorBrush = SolidColor(Blue),
                modifier = Modifier.fillMaxWidth(),
            )
        }
        if (trailing != null) {
            Spacer(Modifier.width(10.dp))
            trailing()
        }
    }
}

/** The box every input sits in: grey at rest, white with a blue edge while typing, red after a failed try. */
@Composable
private fun FieldShell(
    focused: Boolean,
    isError: Boolean,
    modifier: Modifier = Modifier,
    content: @Composable androidx.compose.foundation.layout.RowScope.() -> Unit,
) {
    val border by animateColorAsState(
        when {
            isError -> Red
            focused -> Blue
            else -> Color.Transparent
        },
        tween(160), label = "field-border",
    )
    val bg by animateColorAsState(if (focused || isError) Color.White else Fill, tween(160), label = "field-bg")
    val width by animateDpAsState(if (focused || isError) 1.5.dp else 1.dp, tween(160), label = "field-w")
    Row(
        modifier
            .fillMaxWidth()
            .height(58.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(bg)
            .border(width, border, RoundedCornerShape(16.dp))
            .padding(horizontal = 16.dp),
        verticalAlignment = Alignment.CenterVertically,
        content = content,
    )
}

/**
 * Six cells, one hidden field. The digit typed pops into its cell; the next empty
 * cell holds a blinking caret so the eye knows where the next digit goes.
 */
@Composable
private fun CodeCells(
    code: String,
    onCode: (String) -> Unit,
    isError: Boolean,
    enabled: Boolean,
    focusRequester: FocusRequester,
    modifier: Modifier = Modifier,
) {
    val interaction = remember { MutableInteractionSource() }
    val focused by interaction.collectIsFocusedAsState()
    BasicTextField(
        value = code,
        onValueChange = { raw -> onCode(raw.filter(Char::isDigit).take(6)) },
        enabled = enabled,
        singleLine = true,
        interactionSource = interaction,
        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword, imeAction = ImeAction.Done),
        modifier = modifier
            .fillMaxWidth()
            .focusRequester(focusRequester)
            .semantics { contentDescription = "6-digit code, ${code.length} entered" },
        decorationBox = {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(9.dp)) {
                repeat(6) { i ->
                    CodeCell(
                        digit = code.getOrNull(i),
                        active = focused && enabled && i == code.length.coerceAtMost(5) && code.length < 6,
                        isError = isError,
                        modifier = Modifier.weight(1f),
                    )
                }
            }
        },
    )
}

@Composable
private fun CodeCell(digit: Char?, active: Boolean, isError: Boolean, modifier: Modifier) {
    val border by animateColorAsState(
        when {
            isError -> Red
            active -> Blue
            digit != null -> Color(0xFFC9D1DD)
            else -> Color.Transparent
        },
        tween(140), label = "cell-border",
    )
    val bg by animateColorAsState(if (digit != null || active) Color.White else Fill, tween(140), label = "cell-bg")
    Box(
        modifier
            .height(60.dp)
            .clip(RoundedCornerShape(14.dp))
            .background(bg)
            .border(if (active || isError) 1.5.dp else 1.dp, border, RoundedCornerShape(14.dp)),
        contentAlignment = Alignment.Center,
    ) {
        if (digit != null) {
            val pop = remember(digit) { Animatable(0.55f) }
            LaunchedEffect(digit) { pop.animateTo(1f, spring(dampingRatio = 0.5f, stiffness = Spring.StiffnessMedium)) }
            Text(
                digit.toString(), fontSize = 24.sp, fontWeight = FontWeight.Bold, color = Ink,
                modifier = Modifier.graphicsLayer { scaleX = pop.value; scaleY = pop.value },
            )
        } else if (active) {
            val blink by rememberInfiniteTransition(label = "caret").animateFloat(
                1f, 0f, infiniteRepeatable(tween(520), RepeatMode.Reverse), label = "caret-a",
            )
            Box(Modifier.width(2.dp).height(24.dp).alpha(blink).background(Blue))
        }
    }
}

@Composable
private fun ResendLine(resendAt: Long, enabled: Boolean, onResend: () -> Unit) {
    var now by remember { mutableLongStateOf(System.currentTimeMillis()) }
    LaunchedEffect(resendAt) {
        now = System.currentTimeMillis()
        while (now < resendAt) {
            delay(250)
            now = System.currentTimeMillis()
        }
    }
    val left = ((resendAt - now + 999) / 1000).coerceAtLeast(0)
    Row(verticalAlignment = Alignment.CenterVertically) {
        Text("Didn't get it?", fontSize = 13.5.sp, color = Muted)
        Spacer(Modifier.width(6.dp))
        if (left > 0) {
            Text(
                "Resend in 0:%02d".format(left), fontSize = 13.5.sp, color = Faint,
                fontWeight = FontWeight.SemiBold, style = TextStyle(fontFeatureSettings = "tnum"),
            )
        } else {
            TextLink("Resend code", if (enabled) onResend else ({}))
        }
    }
}

// ---- Buttons & bits --------------------------------------------------------------

@Composable
private fun Title(title: String, sub: String) {
    Text(title, fontSize = 22.sp, fontWeight = FontWeight.Bold, color = Ink, letterSpacing = (-0.3).sp)
    Spacer(Modifier.height(6.dp))
    Text(sub, fontSize = 13.5.sp, color = Muted, lineHeight = 19.sp)
}

@Composable
private fun PrimaryButton(text: String, enabled: Boolean, loading: Boolean, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    val view = LocalView.current
    val bg by animateColorAsState(
        when {
            loading -> Blue.copy(alpha = 0.86f)
            enabled -> Blue
            else -> Color(0xFFE9EDF4)
        },
        tween(180), label = "cta-bg",
    )
    val fg by animateColorAsState(if (enabled || loading) Color.White else Faint, tween(180), label = "cta-fg")
    Row(
        Modifier
            .then(if (enabled) Modifier.pressScale(interaction, 0.97f) else Modifier)
            .fillMaxWidth()
            .height(56.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(bg)
            .pressShade(interaction, 0.12f)
            .clickable(interactionSource = interaction, indication = null, enabled = enabled) {
                Haptics.tick(view)
                onClick()
            },
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (loading) {
            CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(11.dp))
        }
        AnimatedContent(
            targetState = text,
            transitionSpec = { fadeIn(tween(160)) togetherWith fadeOut(tween(100)) },
            label = "cta-text",
        ) { t -> Text(t, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = fg) }
    }
}

@Composable
private fun AltButton(
    text: String,
    loading: Boolean,
    enabled: Boolean,
    modifier: Modifier,
    leading: @Composable () -> Unit,
    onClick: () -> Unit,
) {
    val interaction = remember { MutableInteractionSource() }
    val view = LocalView.current
    Row(
        modifier
            .pressScale(interaction, 0.96f)
            .height(52.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(16.dp))
            .pressShade(interaction)
            .clickable(interactionSource = interaction, indication = null, enabled = enabled) {
                Haptics.tick(view)
                onClick()
            },
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (loading) CircularProgressIndicator(color = Blue, strokeWidth = 2.dp, modifier = Modifier.size(16.dp))
        else leading()
        Spacer(Modifier.width(10.dp))
        Text(text, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = Ink)
    }
}

@Composable
private fun TextLink(text: String, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    Text(
        text,
        fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = BlueInk,
        modifier = Modifier
            .pressScale(interaction, 0.94f)
            .clip(RoundedCornerShape(6.dp))
            .clickable(interactionSource = interaction, indication = null, onClick = onClick)
            .padding(horizontal = 2.dp, vertical = 4.dp),
    )
}

@Composable
private fun OrRule() {
    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
        Box(Modifier.weight(1f).height(1.dp).background(Hairline))
        Text("or sign in with", fontSize = 12.5.sp, color = Faint, modifier = Modifier.padding(horizontal = 14.dp))
        Box(Modifier.weight(1f).height(1.dp).background(Hairline))
    }
}

@Composable
private fun ErrorLine(text: String) {
    Row(verticalAlignment = Alignment.Top, modifier = Modifier.padding(start = 4.dp)) {
        Canvas(Modifier.padding(top = 3.dp).size(13.dp)) {
            val s = size.minDimension
            drawCircle(Red, radius = s / 2, style = Stroke(1.4.dp.toPx()))
            drawLine(Red, Offset(s / 2, s * 0.26f), Offset(s / 2, s * 0.58f), strokeWidth = 1.5.dp.toPx(), cap = StrokeCap.Round)
            drawCircle(Red, radius = 0.95.dp.toPx(), center = Offset(s / 2, s * 0.76f))
        }
        Spacer(Modifier.width(7.dp))
        Text(text, fontSize = 13.sp, color = Red, fontWeight = FontWeight.Medium, lineHeight = 18.sp)
    }
}

/** The legal line every sign-in owes, with the two documents as real links. */
@Composable
private fun Terms(modifier: Modifier = Modifier) {
    val link = TextLinkStyles(SpanStyle(color = Muted, fontWeight = FontWeight.SemiBold))
    val text: AnnotatedString = buildAnnotatedString {
        append("By continuing you agree to our ")
        withLink(LinkAnnotation.Url("${ApiConfig.BASE_URL}/legal/terms", link)) { append("Terms") }
        append(" and ")
        withLink(LinkAnnotation.Url("${ApiConfig.BASE_URL}/legal/privacy", link)) { append("Privacy Policy") }
    }
    Text(
        text, fontSize = 12.sp, color = Faint, lineHeight = 17.sp, textAlign = TextAlign.Center,
        modifier = modifier.fillMaxWidth(),
    )
}

/** A tick that draws itself in one stroke, and wipes back out. */
@Composable
private fun DrawnCheck(visible: Boolean, modifier: Modifier = Modifier) {
    val progress by animateFloatAsState(if (visible) 1f else 0f, if (visible) tween(320) else tween(140), label = "check")
    if (progress <= 0f) {
        Spacer(modifier)
        return
    }
    Canvas(modifier) {
        val s = size.minDimension
        drawCircle(Valid.copy(alpha = 0.12f * progress), radius = s / 2f)
        val path = Path().apply {
            moveTo(s * 0.28f, s * 0.52f)
            lineTo(s * 0.44f, s * 0.67f)
            lineTo(s * 0.73f, s * 0.36f)
        }
        val measure = androidx.compose.ui.graphics.PathMeasure().apply { setPath(path, false) }
        val partial = Path()
        measure.getSegment(0f, measure.length * progress, partial, true)
        drawPath(partial, Valid, style = Stroke(width = s * 0.11f, cap = StrokeCap.Round, join = StrokeJoin.Round))
    }
}

// ---- Drawn marks -----------------------------------------------------------------

/** The flag, drawn — an emoji flag renders as the letters "IN" on some phones. */
@Composable
private fun IndiaFlag(modifier: Modifier) {
    Canvas(modifier.clip(RoundedCornerShape(2.5.dp))) {
        val band = size.height / 3
        drawRect(Color(0xFFFF9933), Offset.Zero, Size(size.width, band))
        drawRect(Color.White, Offset(0f, band), Size(size.width, band))
        drawRect(Color(0xFF138808), Offset(0f, band * 2), Size(size.width, band))
        drawCircle(Color(0xFF000080), radius = band * 0.42f, center = center, style = Stroke(0.9.dp.toPx()))
        drawRoundRect(Color(0x1A000000), style = Stroke(1.dp.toPx()), cornerRadius = CornerRadius(2.5.dp.toPx()))
    }
}

@Composable
private fun WhatsAppGlyph(modifier: Modifier) {
    Canvas(modifier) {
        val s = size.minDimension
        val c = Offset(s / 2, s / 2)
        // A speech bubble: circle with a tail at the lower left.
        val tail = Path().apply {
            moveTo(s * 0.2f, s * 0.68f)
            lineTo(s * 0.06f, s * 0.96f)
            lineTo(s * 0.38f, s * 0.86f)
            close()
        }
        drawPath(tail, WhatsApp)
        drawCircle(WhatsApp, radius = s * 0.46f, center = c)
        // The handset, as a short white arc.
        drawArc(
            Color.White, startAngle = 110f, sweepAngle = 160f, useCenter = false,
            topLeft = Offset(s * 0.3f, s * 0.3f), size = Size(s * 0.4f, s * 0.4f),
            style = Stroke(s * 0.11f, cap = StrokeCap.Round),
        )
    }
}

@Composable
private fun MailGlyph(modifier: Modifier) {
    Canvas(modifier) {
        val w = 1.6.dp.toPx()
        val r = Rect(w / 2, w / 2, size.width - w / 2, size.height - w / 2)
        drawRoundRect(Ink, r.topLeft, r.size, CornerRadius(2.5.dp.toPx()), style = Stroke(w))
        val flap = Path().apply {
            moveTo(r.left + w, r.top + w)
            lineTo(r.center.x, r.top + r.height * 0.55f)
            lineTo(r.right - w, r.top + w)
        }
        drawPath(flap, Ink, style = Stroke(w, cap = StrokeCap.Round, join = StrokeJoin.Round))
    }
}

private fun DrawScope.courtTexture() {
    val w = size.width
    // A badminton court in true proportion (13.4 m × 6.1 m), small enough that most of
    // it shows, so it reads as a court and not as stray lines.
    val courtW = w * 0.34f
    val courtH = courtW * 2.2f
    val left = w * 0.66f
    val top = size.height * 0.1f
    val c = Color.White.copy(alpha = 0.09f)
    val stroke = 1.3.dp.toPx()
    rotate(18f, pivot = Offset(left + courtW / 2, top + courtH / 2)) {
        val r = Rect(left, top, left + courtW, top + courtH)
        drawRect(c, r.topLeft, r.size, style = Stroke(stroke))
        // Doubles side lines, net, short service lines, centre line.
        val side = courtW * 0.075f
        drawLine(c, Offset(r.left + side, r.top), Offset(r.left + side, r.bottom), stroke)
        drawLine(c, Offset(r.right - side, r.top), Offset(r.right - side, r.bottom), stroke)
        drawLine(c, Offset(r.left, r.center.y), Offset(r.right, r.center.y), stroke * 1.8f)
        val svc = courtH * 0.15f
        drawLine(c, Offset(r.left, r.center.y - svc), Offset(r.right, r.center.y - svc), stroke)
        drawLine(c, Offset(r.left, r.center.y + svc), Offset(r.right, r.center.y + svc), stroke)
        drawLine(c, Offset(r.center.x, r.top), Offset(r.center.x, r.center.y - svc), stroke)
        drawLine(c, Offset(r.center.x, r.center.y + svc), Offset(r.center.x, r.bottom), stroke)
        val back = courtH * 0.055f
        drawLine(c, Offset(r.left, r.top + back), Offset(r.right, r.top + back), stroke)
        drawLine(c, Offset(r.left, r.bottom - back), Offset(r.right, r.bottom - back), stroke)
    }
    // A low light from the lower left, so the band has depth without a glow blob.
    drawRect(
        Brush.radialGradient(
            listOf(Color(0x332563EB), Color.Transparent),
            center = Offset(0f, size.height), radius = size.width * 0.9f,
        ),
    )
}

private fun DrawScope.courtIcon(c: Color) {
    val w = 1.4.dp.toPx()
    val r = Rect(size.width * 0.12f, size.height * 0.2f, size.width * 0.88f, size.height * 0.8f)
    drawRoundRect(c, r.topLeft, r.size, CornerRadius(1.5.dp.toPx()), style = Stroke(w))
    drawLine(c, Offset(r.center.x, r.top), Offset(r.center.x, r.bottom), w)
    drawCircle(c, radius = r.height * 0.18f, center = r.center, style = Stroke(w))
}

private fun DrawScope.ticketIcon(c: Color) {
    val w = 1.4.dp.toPx()
    val r = Rect(size.width * 0.06f, size.height * 0.22f, size.width * 0.94f, size.height * 0.78f)
    val notch = r.height * 0.2f
    val p = Path().apply {
        moveTo(r.left, r.top)
        lineTo(r.right, r.top)
        lineTo(r.right, r.center.y - notch)
        arcTo(Rect(r.right - notch, r.center.y - notch, r.right + notch, r.center.y + notch), 270f, -180f, false)
        lineTo(r.right, r.bottom)
        lineTo(r.left, r.bottom)
        lineTo(r.left, r.center.y + notch)
        arcTo(Rect(r.left - notch, r.center.y - notch, r.left + notch, r.center.y + notch), 90f, -180f, false)
        close()
    }
    drawPath(p, c, style = Stroke(w, join = StrokeJoin.Round))
    val tick = Path().apply {
        moveTo(r.center.x - r.width * 0.16f, r.center.y + r.height * 0.02f)
        lineTo(r.center.x - r.width * 0.03f, r.center.y + r.height * 0.18f)
        lineTo(r.center.x + r.width * 0.18f, r.center.y - r.height * 0.16f)
    }
    drawPath(tick, c, style = Stroke(w, cap = StrokeCap.Round, join = StrokeJoin.Round))
}

private fun DrawScope.barsIcon(c: Color) {
    val w = 2.dp.toPx()
    val base = size.height * 0.82f
    listOf(0.22f to 0.36f, 0.5f to 0.56f, 0.78f to 0.78f).forEach { (x, h) ->
        drawLine(c, Offset(size.width * x, base), Offset(size.width * x, base - size.height * h * 0.8f), w, cap = StrokeCap.Round)
    }
}

// ---- Helpers ---------------------------------------------------------------------

private suspend fun shake(anim: Animatable<Float, AnimationVector1D>) {
    anim.snapTo(0f)
    anim.animateTo(
        0f,
        keyframes {
            durationMillis = 380
            -14f at 50
            12f at 110
            -9f at 170
            6f at 230
            -3f at 290
        },
    )
}

private fun groupPhone(d: String) = if (d.length > 5) d.substring(0, 5) + " " + d.substring(5) else d

/** Shows ten digits as "98765 43210" while the field keeps the bare digits. */
private val PhoneGrouping = VisualTransformation { text ->
    val raw = text.text
    TransformedText(
        AnnotatedString(groupPhone(raw)),
        object : OffsetMapping {
            override fun originalToTransformed(offset: Int) = if (offset > 5) offset + 1 else offset
            override fun transformedToOriginal(offset: Int) = if (offset > 5) (offset - 1).coerceAtMost(raw.length) else offset
        },
    )
}
