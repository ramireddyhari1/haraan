package com.haraan.partner

import android.content.Context
import android.content.Intent
import androidx.core.content.FileProvider
import java.io.File
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box as LayoutBox
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.TrendingDown
import androidx.compose.material.icons.automirrored.filled.TrendingUp
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.BarChart
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.ChevronLeft
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.ConfirmationNumber
import androidx.compose.material.icons.filled.CurrencyRupee
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.Tune
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Notifications
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.Phone
import androidx.compose.material.icons.filled.People
import androidx.compose.material.icons.filled.Place
import androidx.compose.material.icons.filled.Sms
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.filled.Today
import androidx.compose.material3.Button
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import com.journeyapps.barcodescanner.ScanContract
import com.journeyapps.barcodescanner.ScanOptions
import kotlinx.coroutines.launch
import kotlin.math.roundToInt
import android.view.HapticFeedbackConstants
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.activity.compose.BackHandler
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.sp

private sealed interface UiState<out T> {
    data object Loading : UiState<Nothing>
    data class Error(val message: String) : UiState<Nothing>
    data class Data<T>(val value: T) : UiState<T>
}

@Composable
fun PartnerApp() {
    val context = LocalContext.current
    val session = remember { Session(context) }
    val api = remember { PartnerApi() }
    var signedIn by remember { mutableStateOf(session.isSignedIn) }

    if (!signedIn) {
        LoginScreen(api = api, session = session, onSignedIn = { signedIn = true })
    } else {
        HomeScaffold(api = api, session = session, onSignedOut = { signedIn = false })
    }
}

// ---- Login --------------------------------------------------------------

// Partner auth palette — a native port of the web partner "blue-aurora" sign-in
// (resources/views/filament/partner/auth-brand.blade.php). Keep in step with it.
private val AuthInkTop = Color(0xFF0A1738)
private val AuthInkMid = Color(0xFF0B1C46)
private val AuthInkBot = Color(0xFF0A1230)
private val AuthAccent = Color(0xFF2F6BFF)
private val AuthAccentDeep = Color(0xFF1E50E6)
private val AuthPageBg = Color(0xFFF7F8FB)
private val AuthInk = Color(0xFF0F172A)
private val AuthMuted = Color(0xFF64748B)
private val CardBorder = Color(0x0F0F172A)
private val Hairline = Color(0x140F172A)

/** The one premium card surface used across every screen: soft lifted shadow,
 *  white fill, hairline border, consistent radius. Keeps the whole app coherent. */
private fun Modifier.premiumSurface(radius: Dp = 18.dp): Modifier = this
    .shadow(10.dp, RoundedCornerShape(radius), clip = false, spotColor = Color(0x1A0F172A))
    .clip(RoundedCornerShape(radius))
    .background(Color.White)
    .border(1.dp, CardBorder, RoundedCornerShape(radius))

/** A muted, uniform top-bar action icon (no stray tonal circle). */
@Composable
private fun HeaderIcon(icon: ImageVector, desc: String, onClick: () -> Unit) {
    IconButton(onClick = onClick) {
        Icon(icon, contentDescription = desc, tint = AuthMuted, modifier = Modifier.size(22.dp))
    }
}

/** Header bell with an unread-count badge for live booking alerts. */
@Composable
private fun BellIcon(count: Int, onClick: () -> Unit) {
    LayoutBox {
        IconButton(onClick = onClick) {
            Icon(Icons.Filled.Notifications, contentDescription = "Bookings", tint = AuthMuted, modifier = Modifier.size(22.dp))
        }
        if (count > 0) {
            LayoutBox(
                Modifier.align(Alignment.TopEnd).padding(top = 7.dp, end = 5.dp)
                    .size(16.dp).clip(RoundedCornerShape(99.dp)).background(RED)
                    .border(1.5.dp, Color.White, RoundedCornerShape(99.dp)),
                contentAlignment = Alignment.Center,
            ) { Text(if (count > 9) "9+" else "$count", fontSize = 8.5.sp, fontWeight = FontWeight.Bold, color = Color.White) }
        }
    }
}

/** The navy live-booking banner that drops in when a new booking arrives. */
@Composable
private fun BookingBanner(message: String, onClick: () -> Unit) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 8.dp)
            .shadow(12.dp, RoundedCornerShape(14.dp), clip = false, spotColor = AuthInkTop)
            .clip(RoundedCornerShape(14.dp))
            .background(Brush.linearGradient(listOf(AuthInkTop, AuthInkMid)))
            .clickable { onClick() }
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        LayoutBox(
            Modifier.size(30.dp).clip(RoundedCornerShape(99.dp)).background(Color(0x333B82F6)),
            contentAlignment = Alignment.Center,
        ) { Icon(Icons.Filled.Notifications, contentDescription = null, tint = Color.White, modifier = Modifier.size(16.dp)) }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text("NEW BOOKING", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = Color(0xB3CFE0FF), letterSpacing = 1.sp)
            Spacer(Modifier.height(1.dp))
            Text(message, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Color.White, maxLines = 1)
        }
        Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = Color(0x99FFFFFF), modifier = Modifier.size(20.dp))
    }
}

private enum class AuthMode { Home, Phone }

@Composable
private fun LoginScreen(api: PartnerApi, session: Session, onSignedIn: () -> Unit) {
    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var showPw by remember { mutableStateOf(false) }
    var phone by remember { mutableStateOf("") }
    var code by remember { mutableStateOf("") }
    var otpToken by remember { mutableStateOf<String?>(null) }
    var mode by remember { mutableStateOf(AuthMode.Home) }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()
    val view = LocalView.current
    val context = LocalContext.current

    // Every sign-in path funnels here: confirm it's really a partner (overview 403s
    // otherwise), persist the session, then enter.
    suspend fun applyLogin(result: LoginResult) {
        api.overview(result.token)
        session.token = result.token
        session.name = result.name
        session.partnerType = result.partnerType
        session.isDesk = result.isDesk
        session.permissionsCsv = result.permissions.joinToString(",")
        view.performHapticFeedback(HapticFeedbackConstants.CONFIRM)
        onSignedIn()
    }
    fun fail(message: String?) {
        error = message ?: "Something went wrong"
        view.performHapticFeedback(HapticFeedbackConstants.REJECT)
    }

    // The phone lane leans on the system back button/gesture, so it needs no in-card
    // arrow: OTP step → back to the number, number step → back to the home lane.
    BackHandler(enabled = mode == AuthMode.Phone) {
        error = null
        if (otpToken != null) { otpToken = null; code = "" } else { mode = AuthMode.Home; phone = "" }
    }

    val fieldColors = OutlinedTextFieldDefaults.colors(
        focusedBorderColor = AuthAccent,
        focusedLabelColor = AuthAccent,
        cursorColor = AuthAccent,
        unfocusedBorderColor = Color(0x1F0F172A),
    )

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(AuthPageBg)
            .verticalScroll(rememberScrollState())
            .navigationBarsPadding()
            .imePadding(),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        AuthBrandBand()

        // Sign-in card, tucked up under the band's rounded corner (BMS-style).
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .widthIn(max = 460.dp)
                .padding(horizontal = 20.dp)
                .offset(y = (-26).dp)
                .shadow(22.dp, RoundedCornerShape(22.dp), clip = false)
                .clip(RoundedCornerShape(22.dp))
                .background(Color.White)
                .border(1.dp, Color(0x140F172A), RoundedCornerShape(22.dp))
                .padding(horizontal = 22.dp, vertical = 24.dp),
        ) {
            if (mode == AuthMode.Home) {
                Text("Welcome back", fontSize = 21.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                Spacer(Modifier.height(5.dp))
                Text(
                    "Sign in to manage your events and venues",
                    fontSize = 13.5.sp, color = AuthMuted, lineHeight = 18.sp,
                )
                Spacer(Modifier.height(18.dp))

                OutlinedTextField(
                    value = email,
                    onValueChange = { email = it; error = null },
                    label = { Text("Email") },
                    singleLine = true,
                    shape = RoundedCornerShape(12.dp),
                    colors = fieldColors,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
                    modifier = Modifier.fillMaxWidth(),
                )
                Spacer(Modifier.height(12.dp))
                OutlinedTextField(
                    value = password,
                    onValueChange = { password = it; error = null },
                    label = { Text("Password") },
                    singleLine = true,
                    shape = RoundedCornerShape(12.dp),
                    colors = fieldColors,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
                    visualTransformation = if (showPw) VisualTransformation.None else PasswordVisualTransformation(),
                    trailingIcon = {
                        IconButton(onClick = {
                            showPw = !showPw
                            view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
                        }) {
                            Icon(
                                if (showPw) Icons.Filled.VisibilityOff else Icons.Filled.Visibility,
                                contentDescription = if (showPw) "Hide password" else "Show password",
                                tint = AuthMuted,
                            )
                        }
                    },
                    modifier = Modifier.fillMaxWidth(),
                )

                if (error != null) {
                    Spacer(Modifier.height(12.dp))
                    Text(error!!, color = MaterialTheme.colorScheme.error, fontSize = 13.sp)
                }

                Spacer(Modifier.height(20.dp))
                GradientCta(
                    text = if (loading) "Signing in…" else "Sign in",
                    enabled = !loading && email.isNotBlank() && password.isNotBlank(),
                    loading = loading,
                ) {
                    view.performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
                    loading = true; error = null
                    scope.launch {
                        try { applyLogin(api.login(email.trim(), password)) }
                        catch (e: ApiException) { fail(e.message) }
                        catch (e: Exception) { fail(e.message ?: "Unable to sign in") }
                        finally { loading = false }
                    }
                }

                Spacer(Modifier.height(18.dp))
                AuthDivider()
                Spacer(Modifier.height(16.dp))

                SocialButton(
                    text = "Continue with phone",
                    enabled = !loading,
                    leading = { Icon(Icons.Filled.Phone, contentDescription = null, tint = AuthAccent, modifier = Modifier.size(20.dp)) },
                ) { error = null; mode = AuthMode.Phone }

                if (GoogleSignInHelper.isConfigured) {
                    Spacer(Modifier.height(12.dp))
                    SocialButton(
                        text = "Continue with Google",
                        enabled = !loading,
                        leading = {
                            Image(
                                painter = painterResource(R.drawable.ic_google_logo),
                                contentDescription = null,
                                modifier = Modifier.size(19.dp),
                            )
                        },
                    ) {
                        loading = true; error = null
                        scope.launch {
                            when (val r = GoogleSignInHelper.signIn(context)) {
                                is GoogleSignInResult.Success ->
                                    try { applyLogin(api.google(r.idToken)) }
                                    catch (e: ApiException) { fail(e.message) }
                                    catch (e: Exception) { fail(e.message ?: "Unable to sign in") }
                                is GoogleSignInResult.Cancelled -> {}
                                is GoogleSignInResult.Error -> fail(r.message)
                            }
                            loading = false
                        }
                    }
                }
            } else {
                // ---- Phone lane: enter number, then the 6-digit code -------------
                val startOtp: () -> Unit = {
                    view.performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
                    loading = true; error = null
                    scope.launch {
                        try {
                            val s = api.startPhoneOtp(phone.trim())
                            if (s.channel == "whatsapp" && !s.token.isNullOrBlank()) {
                                otpToken = s.token; code = ""
                                view.performHapticFeedback(HapticFeedbackConstants.CONFIRM)
                            } else {
                                fail("Couldn't send a WhatsApp code to that number. Try email or Google.")
                            }
                        } catch (e: ApiException) { fail(e.message) }
                        catch (e: Exception) { fail(e.message ?: "Couldn't send the code") }
                        finally { loading = false }
                    }
                }

                // No in-card back arrow — the system back button/gesture drives this
                // lane (see BackHandler above). A hero badge anchors it instead.
                AuthIconBadge(if (otpToken == null) Icons.Filled.Phone else Icons.Filled.Sms)
                Spacer(Modifier.height(14.dp))

                if (otpToken == null) {
                    Text("Sign in with phone", fontSize = 20.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                    Spacer(Modifier.height(6.dp))
                    Text(
                        "Enter your number and we'll send a one-time code to your WhatsApp.",
                        fontSize = 13.5.sp, color = AuthMuted, lineHeight = 19.sp,
                    )
                    Spacer(Modifier.height(18.dp))
                    PhoneNumberField(
                        value = phone,
                        onValueChange = { phone = it.filter { c -> c.isDigit() }.take(10); error = null },
                    )
                    Spacer(Modifier.height(12.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        LayoutBox(Modifier.size(7.dp).clip(RoundedCornerShape(99.dp)).background(Color(0xFF25D366)))
                        Spacer(Modifier.width(8.dp))
                        Text("Delivered free on WhatsApp", fontSize = 12.sp, color = AuthMuted)
                    }
                    if (error != null) {
                        Spacer(Modifier.height(12.dp))
                        Text(error!!, color = MaterialTheme.colorScheme.error, fontSize = 13.sp)
                    }
                    Spacer(Modifier.height(20.dp))
                    GradientCta(
                        text = if (loading) "Sending…" else "Send code",
                        enabled = !loading && phone.trim().length == 10,
                        loading = loading,
                        onClick = startOtp,
                    )
                } else {
                    Text("Enter the code", fontSize = 20.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                    Spacer(Modifier.height(6.dp))
                    Text(
                        "We sent a 6-digit code on WhatsApp to +91 ${phone.trim()}.",
                        fontSize = 13.5.sp, color = AuthMuted, lineHeight = 19.sp,
                    )
                    Spacer(Modifier.height(18.dp))
                    OutlinedTextField(
                        value = code,
                        onValueChange = { code = it.filter { c -> c.isDigit() }.take(6); error = null },
                        label = { Text("6-digit code") },
                        singleLine = true,
                        shape = RoundedCornerShape(12.dp),
                        colors = fieldColors,
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        textStyle = TextStyle(fontSize = 22.sp, letterSpacing = 10.sp, fontWeight = FontWeight.Bold, color = AuthInk),
                        modifier = Modifier.fillMaxWidth(),
                    )
                    if (error != null) {
                        Spacer(Modifier.height(12.dp))
                        Text(error!!, color = MaterialTheme.colorScheme.error, fontSize = 13.sp)
                    }
                    Spacer(Modifier.height(20.dp))
                    GradientCta(
                        text = if (loading) "Verifying…" else "Verify & sign in",
                        enabled = !loading && code.length == 6,
                        loading = loading,
                    ) {
                        view.performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
                        loading = true; error = null
                        scope.launch {
                            try { applyLogin(api.verifyPhoneOtp(otpToken!!, code.trim())) }
                            catch (e: ApiException) { fail(e.message) }
                            catch (e: Exception) { fail(e.message ?: "Couldn't verify the code") }
                            finally { loading = false }
                        }
                    }
                    Spacer(Modifier.height(4.dp))
                    TextButton(
                        onClick = startOtp,
                        enabled = !loading,
                        modifier = Modifier.align(Alignment.CenterHorizontally),
                    ) { Text("Resend code", color = AuthAccent, fontWeight = FontWeight.SemiBold) }
                }
            }
        }

        Spacer(Modifier.height(14.dp))
        Text(
            "Trusted by turf owners, clubs & event organisers",
            fontSize = 12.sp,
            color = AuthMuted,
            textAlign = TextAlign.Center,
            modifier = Modifier.padding(horizontal = 32.dp),
        )
        Spacer(Modifier.height(8.dp))
        // A faint "watermark" Haraan wordmark under the footer credit — a quiet
        // shadow-type brand mark, kept tight to the footer.
        Image(
            painter = painterResource(R.drawable.haraan_logo_white),
            contentDescription = null,
            contentScale = ContentScale.Fit,
            colorFilter = ColorFilter.tint(AuthInk),
            modifier = Modifier.width(120.dp).alpha(0.08f),
        )
        Spacer(Modifier.height(12.dp))
    }
}

/** Outlined white "continue with…" button — leading mark + label, with a press dip. */
@Composable
private fun SocialButton(
    text: String,
    leading: @Composable () -> Unit,
    enabled: Boolean = true,
    onClick: () -> Unit,
) {
    val view = LocalView.current
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val scale by animateFloatAsState(if (pressed && enabled) 0.98f else 1f, label = "social-scale")
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.Center,
        modifier = Modifier
            .fillMaxWidth()
            .height(50.dp)
            .graphicsLayer { scaleX = scale; scaleY = scale }
            .clip(RoundedCornerShape(14.dp))
            .background(Color.White)
            .border(1.dp, Color(0x220F172A), RoundedCornerShape(14.dp))
            .clickable(interactionSource = interaction, indication = null, enabled = enabled) {
                view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
                onClick()
            },
    ) {
        leading()
        Spacer(Modifier.width(10.dp))
        Text(text, fontSize = 14.5.sp, fontWeight = FontWeight.SemiBold, color = AuthInk)
    }
}

/** A thin rule with a centred "or continue with" label. */
@Composable
private fun AuthDivider() {
    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
        LayoutBox(Modifier.weight(1f).height(1.dp).background(Color(0x1A0F172A)))
        Text(
            "or continue with",
            fontSize = 12.sp,
            color = AuthMuted,
            modifier = Modifier.padding(horizontal = 12.dp),
        )
        LayoutBox(Modifier.weight(1f).height(1.dp).background(Color(0x1A0F172A)))
    }
}

/** Soft gradient badge that anchors a sub-screen (phone / OTP) with a single mark. */
@Composable
private fun AuthIconBadge(icon: ImageVector) {
    LayoutBox(
        modifier = Modifier
            .size(52.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(Brush.linearGradient(listOf(Color(0xFFEAF1FF), Color(0xFFDCE8FF))))
            .border(1.dp, Color(0x142F6BFF), RoundedCornerShape(16.dp)),
        contentAlignment = Alignment.Center,
    ) { Icon(icon, contentDescription = null, tint = AuthAccent, modifier = Modifier.size(26.dp)) }
}

/**
 * A single-line phone field with a fixed "+91" country segment, a divider, and an
 * animated focus ring — reads far more like a real product than a plain prefix.
 */
@Composable
private fun PhoneNumberField(value: String, onValueChange: (String) -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    val focused by interaction.collectIsFocusedAsState()
    val borderColor by animateColorAsState(
        if (focused) AuthAccent else Color(0x1F0F172A), label = "phone-border",
    )
    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .fillMaxWidth()
            .height(56.dp)
            .clip(RoundedCornerShape(14.dp))
            .background(Color(0xFFF8FAFC))
            .border(if (focused) 1.6.dp else 1.dp, borderColor, RoundedCornerShape(14.dp)),
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier.padding(start = 14.dp, end = 11.dp),
        ) {
            Text("🇮🇳", fontSize = 17.sp)
            Spacer(Modifier.width(7.dp))
            Text("+91", fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = AuthInk)
        }
        LayoutBox(Modifier.width(1.dp).height(26.dp).background(Color(0x1F0F172A)))
        BasicTextField(
            value = value,
            onValueChange = onValueChange,
            singleLine = true,
            textStyle = TextStyle(fontSize = 16.sp, color = AuthInk, letterSpacing = 1.sp, fontWeight = FontWeight.Medium),
            cursorBrush = SolidColor(AuthAccent),
            interactionSource = interaction,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
            modifier = Modifier.weight(1f).padding(horizontal = 13.dp),
            decorationBox = { inner ->
                LayoutBox(contentAlignment = Alignment.CenterStart) {
                    if (value.isEmpty()) {
                        Text("00000 00000", color = Color(0xFF94A3B8), fontSize = 16.sp, letterSpacing = 1.sp)
                    }
                    inner()
                }
            },
        )
    }
}

/** The dark aurora brand band — the mobile "hero" of the web partner sign-in. */
@Composable
private fun AuthBrandBand() {
    val bandShape = RoundedCornerShape(bottomStart = 28.dp, bottomEnd = 28.dp)
    // Gentle, endless drift on the two glows so the band feels alive, not static.
    val t = rememberInfiniteTransition(label = "aurora")
    val drift by t.animateFloat(
        initialValue = 0f, targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(9000), RepeatMode.Reverse),
        label = "drift",
    )

    LayoutBox(
        modifier = Modifier
            .fillMaxWidth()
            .clip(bandShape)
            .background(Brush.linearGradient(listOf(AuthInkTop, AuthInkMid, AuthInkBot))),
    ) {
        // Aurora glows (soft radial gradients; no blur dependency so it looks the
        // same on every API level).
        LayoutBox(
            Modifier
                .matchParentSize()
                .graphicsLayer { translationX = -40f + 30f * drift; translationY = -30f + 22f * drift }
                .background(
                    Brush.radialGradient(
                        colors = listOf(Color(0x8C3B82F6), Color(0x00000000)),
                        center = Offset(180f, 60f), radius = 520f,
                    )
                )
        )
        LayoutBox(
            Modifier
                .matchParentSize()
                .graphicsLayer { translationX = 30f - 26f * drift; translationY = 24f - 18f * drift }
                .background(
                    Brush.radialGradient(
                        colors = listOf(Color(0x80818CF8), Color(0x00000000)),
                        center = Offset(760f, 360f), radius = 520f,
                    )
                )
        )

        Column(
            modifier = Modifier
                .fillMaxWidth()
                .statusBarsPadding()
                .padding(start = 22.dp, end = 22.dp, top = 22.dp, bottom = 30.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Image(
                    painter = painterResource(R.drawable.haraan_logo_white),
                    contentDescription = "Haraan",
                    contentScale = ContentScale.Fit,
                    modifier = Modifier.height(22.dp),
                )
                Spacer(Modifier.width(10.dp))
                Text(
                    "PARTNER",
                    fontSize = 10.sp,
                    fontWeight = FontWeight.ExtraBold,
                    letterSpacing = 2.4.sp,
                    color = Color(0xFFCFE0FF),
                    modifier = Modifier
                        .clip(RoundedCornerShape(999.dp))
                        .background(Color(0x1AFFFFFF))
                        .border(1.dp, Color(0x2EFFFFFF), RoundedCornerShape(999.dp))
                        .padding(horizontal = 9.dp, vertical = 4.dp),
                )
            }
            Spacer(Modifier.height(18.dp))
            Text(
                "Run your venue.\nFill every show.",
                fontSize = 27.sp,
                lineHeight = 30.sp,
                fontWeight = FontWeight.ExtraBold,
                letterSpacing = (-0.5).sp,
                color = Color.White,
            )
            Spacer(Modifier.height(10.dp))
            Text(
                "One console for hosts & venue owners — publish events, take bookings, scan tickets at the gate and watch earnings in real time.",
                fontSize = 13.5.sp,
                lineHeight = 19.sp,
                color = Color(0xCCE0E8FF),
            )
            Spacer(Modifier.height(16.dp))
            // Equal thirds so all three always sit on one line — never wrapping,
            // never clipped, on any screen width.
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                AuthChip("₹", "Earnings", Modifier.weight(1f))
                AuthChip("⚡", "Check-in", Modifier.weight(1f))
                AuthChip("◎", "Dashboard", Modifier.weight(1f))
            }
        }
    }
}

@Composable
private fun AuthChip(glyph: String, label: String, modifier: Modifier = Modifier) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.Center,
        modifier = modifier
            .clip(RoundedCornerShape(999.dp))
            .background(Color(0x14FFFFFF))
            .border(1.dp, Color(0x24FFFFFF), RoundedCornerShape(999.dp))
            .padding(horizontal = 8.dp, vertical = 8.dp),
    ) {
        LayoutBox(
            modifier = Modifier
                .size(17.dp)
                .clip(RoundedCornerShape(999.dp))
                .background(Color(0x593884FF)),
            contentAlignment = Alignment.Center,
        ) { Text(glyph, fontSize = 9.sp, fontWeight = FontWeight.Bold, color = Color.White) }
        Spacer(Modifier.width(6.dp))
        Text(label, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = Color(0xFFE6EDFF), maxLines = 1)
    }
}

/** Full-width gradient sign-in button with a confident press state (scale + lift). */
@Composable
private fun GradientCta(
    text: String,
    enabled: Boolean,
    loading: Boolean,
    onClick: () -> Unit,
) {
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val scale by animateFloatAsState(if (pressed && enabled) 0.975f else 1f, label = "cta-scale")

    LayoutBox(
        modifier = Modifier
            .fillMaxWidth()
            .height(52.dp)
            .graphicsLayer { scaleX = scale; scaleY = scale }
            .shadow(
                elevation = if (enabled) 16.dp else 0.dp,
                shape = RoundedCornerShape(14.dp),
                clip = false,
                spotColor = AuthAccent,
                ambientColor = AuthAccent,
            )
            .clip(RoundedCornerShape(14.dp))
            .background(
                if (enabled) Brush.verticalGradient(listOf(AuthAccent, AuthAccentDeep))
                else Brush.verticalGradient(listOf(Color(0xFFE8EEF9), Color(0xFFDFE7F4)))
            )
            .clickable(
                interactionSource = interaction,
                indication = null,
                enabled = enabled,
                onClick = onClick,
            ),
        contentAlignment = Alignment.Center,
    ) {
        val contentColor = if (enabled) Color.White else Color(0xFF9AA7BD)
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            if (loading) {
                CircularProgressIndicator(
                    modifier = Modifier.size(18.dp),
                    color = contentColor,
                    strokeWidth = 2.dp,
                )
            }
            Text(text, color = contentColor, fontSize = 15.sp, fontWeight = FontWeight.Bold)
        }
    }
}

// ---- Home scaffold + tabs ----------------------------------------------

private enum class Tab(val label: String) { Home("Home"), Events("Events"), Venues("Venues"), Sales("Sales"), Scan("Scan") }

/** Which lane the signed-in partner belongs to. Drives the whole shell. */
private enum class Lane { EVENT, VENUE, BOTH }

private fun laneOf(partnerType: String?): Lane = when (partnerType?.lowercase()) {
    "event", "host", "organiser", "organizer" -> Lane.EVENT
    "venue" -> Lane.VENUE
    else -> Lane.BOTH // legacy / no type / admin → combined
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun HomeScaffold(api: PartnerApi, session: Session, onSignedOut: () -> Unit) {
    var detail by remember { mutableStateOf<AnalyticsTarget?>(null) }
    var manageVenue by remember { mutableStateOf<Pair<Long, String>?>(null) }
    var showReports by remember { mutableStateOf(false) }
    var showStaff by remember { mutableStateOf(false) }
    val token = session.token ?: return

    if (showReports) {
        ReportsScreen(api, token, onBack = { showReports = false })
        return
    }
    if (showStaff) {
        StaffScreen(api, token, onBack = { showStaff = false })
        return
    }

    // Build the visible tabs from the partner's lane so an event host never sees
    // venue tabs and vice-versa. Sales is shared but relabelled per lane. The lane
    // seeds from the cached type and is corrected once the server confirms it.
    var lane by remember { mutableStateOf(laneOf(session.partnerType)) }
    val tabs = remember(lane) {
        buildList {
            add(Tab.Home)
            if (lane != Lane.VENUE) add(Tab.Events)
            if (lane != Lane.EVENT) add(Tab.Venues)
            add(Tab.Sales)
            add(Tab.Scan)
        }
    }
    var tab by remember { mutableStateOf(Tab.Home) }
    // If the lane resolves and the current tab is no longer valid, fall back Home.
    LaunchedEffect(tabs) { if (tab !in tabs) tab = Tab.Home }

    // Live booking alerts (Part A — works with no push infra): while the app is
    // open, poll for bookings and surface anything newer than we've already shown
    // as a top banner + a bell badge. FCM (Part B) covers the closed-app case.
    val view = LocalView.current
    var unseenBookings by remember { mutableStateOf(0) }
    var bookingBanner by remember { mutableStateOf<String?>(null) }
    LaunchedEffect(token) {
        while (true) {
            runCatching {
                val list = api.bookings(token)
                val maxId = list.maxOfOrNull { it.id } ?: 0L
                val last = session.lastNotifiedBookingId
                when {
                    last == 0L -> session.lastNotifiedBookingId = maxId // baseline; don't alert on first load
                    maxId > last -> {
                        val fresh = list.filter { it.id > last }
                        unseenBookings += fresh.size
                        bookingBanner = fresh.firstOrNull()?.let {
                            "${it.label ?: "Booking"} · ₹" + formatInr(it.amount)
                        }
                        session.lastNotifiedBookingId = maxId
                        view.performHapticFeedback(HapticFeedbackConstants.CONFIRM)
                    }
                }
            }
            kotlinx.coroutines.delay(20_000)
        }
    }
    LaunchedEffect(bookingBanner) {
        if (bookingBanner != null) { kotlinx.coroutines.delay(6_000); bookingBanner = null }
    }

    detail?.let { target ->
        AnalyticsScreen(api, token, target, onBack = { detail = null })
        return
    }

    manageVenue?.let { (id, name) ->
        VenueDayScreen(
            api, token, id, name,
            onBack = { manageVenue = null },
            onAnalytics = { detail = AnalyticsTarget(AnalyticsKind.Venue, id, name) },
            canPricing = session.can("pricing"),
            canBookings = session.can("bookings"),
        )
        return
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = Color.White,
                    scrolledContainerColor = Color.White,
                ),
                title = {
                    Image(
                        painter = painterResource(R.drawable.haraan_logo),
                        contentDescription = "Haraan",
                        contentScale = ContentScale.Fit,
                        modifier = Modifier.height(22.dp),
                    )
                },
                actions = {
                    BellIcon(unseenBookings) { tab = Tab.Sales; unseenBookings = 0 }
                    if (!session.isDesk) HeaderIcon(Icons.Filled.People, "Staff") { showStaff = true }
                    if (session.can("reports")) HeaderIcon(Icons.Filled.Description, "Reports") { showReports = true }
                    Spacer(Modifier.width(2.dp))
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier
                            .padding(end = 10.dp)
                            .clip(RoundedCornerShape(999.dp))
                            .background(Color(0x142F6BFF))
                            .clickable { session.clear(); onSignedOut() }
                            .padding(horizontal = 12.dp, vertical = 7.dp),
                    ) {
                        Text("Sign out", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = AuthAccentDeep)
                    }
                },
            )
        },
        bottomBar = {
            NavigationBar {
                tabs.forEach { t ->
                    val label = labelFor(t, lane)
                    NavigationBarItem(
                        selected = tab == t,
                        onClick = { tab = t },
                        icon = { Icon(iconFor(t), contentDescription = label) },
                        label = { Text(label) },
                    )
                }
            }
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            bookingBanner?.let { msg ->
                BookingBanner(msg) { bookingBanner = null; tab = Tab.Sales; unseenBookings = 0 }
            }
            when (tab) {
                Tab.Home -> HomeTab(api, token, session.name ?: "Partner", lane) { serverType ->
                    if (serverType != null) {
                        session.partnerType = serverType
                        lane = laneOf(serverType)
                    }
                }
                Tab.Events -> EventsTab(api, token) { id, name ->
                    detail = AnalyticsTarget(AnalyticsKind.Event, id, name)
                }
                Tab.Venues -> VenuesTab(api, token) { id, name ->
                    manageVenue = id to name
                }
                Tab.Sales -> SalesTab(api, token)
                Tab.Scan -> ScanTab(api, token)
            }
        }
    }
}

private fun iconFor(tab: Tab) = when (tab) {
    Tab.Home -> Icons.Filled.Home
    Tab.Events -> Icons.Filled.CalendarMonth
    Tab.Venues -> Icons.Filled.Place
    Tab.Sales -> Icons.Filled.Payments
    Tab.Scan -> Icons.Filled.QrCodeScanner
}

/** Venue owners see "Bookings" where event hosts see "Sales". */
private fun labelFor(tab: Tab, lane: Lane): String =
    if (tab == Tab.Sales && lane == Lane.VENUE) "Bookings" else tab.label

/**
 * Loads data once, shows it, and lets the user swipe down to reload. Keeps the
 * current content on screen while a refresh spins, so it never flashes empty.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun <T> RefreshableContent(key: Any?, load: suspend () -> T, content: @Composable (T) -> Unit) {
    var data by remember(key) { mutableStateOf<UiState<T>>(UiState.Loading) }
    var refreshing by remember(key) { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    LaunchedEffect(key) { data = runCatchingUi { load() } }
    PullToRefreshBox(
        isRefreshing = refreshing,
        onRefresh = {
            scope.launch {
                refreshing = true
                data = runCatchingUi { load() }
                refreshing = false
            }
        },
        modifier = Modifier.fillMaxSize(),
    ) {
        Loaded(data) { content(it) }
    }
}

private data class Tile(val icon: ImageVector, val label: String, val value: String, val hint: String)

@Composable
private fun HomeTab(api: PartnerApi, token: String, name: String, lane: Lane, onLane: (String?) -> Unit) {
    RefreshableContent(token, load = { api.overview(token) }) { o ->
        // Report the server's authoritative type up so the tab bar matches.
        LaunchedEffect(o.type) { onLane(o.type) }
        // If the server knows a more specific lane than the cached one, honour it.
        val effective = if (o.type != null) laneOf(o.type) else lane
        val subtitle = when (effective) {
            Lane.EVENT -> "Event organiser"
            Lane.VENUE -> "Venue owner"
            Lane.BOTH -> "Partner"
        }
        val tiles = when (effective) {
            Lane.EVENT -> listOf(
                Tile(Icons.Filled.ConfirmationNumber, "Events", o.eventsTotal.toString(), "${o.eventsUpcoming} upcoming"),
                Tile(Icons.Filled.ConfirmationNumber, "Tickets", o.ticketsSold.toString(), "sold all-time"),
                Tile(Icons.Filled.Today, "Today", o.bookingsToday.toString(), "bookings today"),
                Tile(Icons.Filled.Close, "Cancelled", o.cancelled.toString(), "all-time"),
            )
            Lane.VENUE -> listOf(
                Tile(Icons.Filled.Place, "Venues", o.venuesTotal.toString(), "turfs & spaces"),
                Tile(Icons.Filled.ConfirmationNumber, "Bookings", o.bookingsTotal.toString(), "all-time"),
                Tile(Icons.Filled.Today, "Today", o.bookingsToday.toString(), "bookings today"),
            )
            Lane.BOTH -> listOf(
                Tile(Icons.Filled.ConfirmationNumber, "Events", o.eventsTotal.toString(), "${o.eventsUpcoming} upcoming"),
                Tile(Icons.Filled.Place, "Venues", o.venuesTotal.toString(), "turfs & spaces"),
                Tile(Icons.Filled.Today, "Today", o.bookingsToday.toString(), "bookings today"),
                Tile(Icons.Filled.ConfirmationNumber, "Bookings", o.bookingsTotal.toString(), "all-time"),
            )
        }

        LazyColumn(
            Modifier.fillMaxSize().background(AuthPageBg).padding(horizontal = 16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(top = 12.dp, bottom = 24.dp),
        ) {
            item { GreetingHeader(name, subtitle) }
            item { RevenueHero(o) }
            item { StatStrip(tiles) }
            if (effective != Lane.EVENT) {
                item { BookingsMixCard(o) }
            }
        }
    }
}

@Composable
private fun BookingsMixCard(o: Overview) {
    val online = o.online.coerceAtLeast(0)
    val offline = o.offline.coerceAtLeast(0)
    val total = (online + offline).coerceAtLeast(1)
    val onlineColor = AuthAccent
    val offlineColor = Color(0xFF0EA5E9)

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(10.dp, RoundedCornerShape(18.dp), clip = false, spotColor = Color(0x1A0F172A))
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(1.dp, Color(0x0F0F172A), RoundedCornerShape(18.dp))
            .padding(18.dp),
    ) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Text("Bookings mix", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk)
            Text("$total total", fontSize = 12.sp, color = AuthMuted)
        }
        Spacer(Modifier.height(14.dp))
        Row(Modifier.fillMaxWidth().height(12.dp), horizontalArrangement = Arrangement.spacedBy(3.dp)) {
            if (online > 0) LayoutBox(Modifier.weight(online.toFloat()).fillMaxHeight().clip(RoundedCornerShape(6.dp)).background(onlineColor))
            if (offline > 0) LayoutBox(Modifier.weight(offline.toFloat()).fillMaxHeight().clip(RoundedCornerShape(6.dp)).background(offlineColor))
            if (online == 0 && offline == 0) LayoutBox(Modifier.weight(1f).fillMaxHeight().clip(RoundedCornerShape(6.dp)).background(Color(0xFFE5E7EB)))
        }
        Spacer(Modifier.height(14.dp))
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(20.dp)) {
            MixLegend(onlineColor, "Online", online, total)
            MixLegend(offlineColor, "Walk-in", offline, total)
        }
        if (o.cancelled > 0) {
            Spacer(Modifier.height(14.dp))
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x14DC2626)).padding(horizontal = 10.dp, vertical = 5.dp),
            ) {
                LayoutBox(Modifier.size(6.dp).clip(RoundedCornerShape(99.dp)).background(RED))
                Spacer(Modifier.width(6.dp))
                Text("${o.cancelled} cancelled / refunded", fontSize = 12.sp, fontWeight = FontWeight.Medium, color = RED)
            }
        }
    }
}

@Composable
private fun MixLegend(color: Color, label: String, count: Int, total: Int) {
    val pct = if (total > 0) (count * 100 / total) else 0
    Row(verticalAlignment = Alignment.CenterVertically) {
        LayoutBox(Modifier.size(9.dp).clip(RoundedCornerShape(99.dp)).background(color))
        Spacer(Modifier.width(7.dp))
        Text("$label ", fontSize = 13.sp, color = AuthInk, fontWeight = FontWeight.SemiBold)
        Text("· $count ($pct%)", fontSize = 13.sp, color = AuthMuted)
    }
}

private fun greeting(): String {
    val h = java.util.Calendar.getInstance().get(java.util.Calendar.HOUR_OF_DAY)
    return when {
        h < 12 -> "Good morning"
        h < 17 -> "Good afternoon"
        else -> "Good evening"
    }
}

@Composable
private fun GreetingHeader(name: String, subtitle: String) {
    Column(Modifier.padding(top = 4.dp)) {
        Text(greeting(), fontSize = 14.sp, color = AuthMuted)
        Spacer(Modifier.height(2.dp))
        Text(name, fontSize = 24.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk, letterSpacing = (-0.5).sp)
        Spacer(Modifier.height(8.dp))
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier
                .clip(RoundedCornerShape(999.dp))
                .background(Color(0x142F6BFF))
                .padding(horizontal = 10.dp, vertical = 5.dp),
        ) {
            LayoutBox(Modifier.size(6.dp).clip(RoundedCornerShape(99.dp)).background(AuthAccent))
            Spacer(Modifier.width(6.dp))
            Text(subtitle, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = AuthAccentDeep)
        }
    }
}

@Composable
private fun RevenueHero(o: Overview) {
    // Real week-over-week trend from the 14-day series: last 7 vs the prior 7.
    val trendPct: Int? = run {
        if (o.trend.size < 14) null else {
            val last7 = o.trend.takeLast(7).sum()
            val prev7 = o.trend.dropLast(7).takeLast(7).sum()
            if (prev7 <= 0.0) null else (((last7 - prev7) / prev7) * 100).roundToInt()
        }
    }
    LayoutBox(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(18.dp, RoundedCornerShape(22.dp), clip = false, spotColor = AuthInkTop)
            .clip(RoundedCornerShape(22.dp))
            .background(Brush.linearGradient(listOf(AuthInkTop, AuthInkMid, AuthInkBot))),
    ) {
        LayoutBox(
            Modifier.matchParentSize().background(
                Brush.radialGradient(
                    listOf(Color(0x553B82F6), Color(0x00000000)),
                    center = Offset(120f, 40f), radius = 520f,
                )
            )
        )
        Column(Modifier.fillMaxWidth().padding(20.dp)) {
            Text("TOTAL REVENUE", color = Color(0xB3CFE0FF), fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.5.sp)
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.Bottom) {
                Text("₹" + formatInr(o.revenue), color = Color.White, fontSize = 34.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-1).sp)
                if (trendPct != null) {
                    Spacer(Modifier.width(10.dp))
                    val up = trendPct >= 0
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier
                            .padding(bottom = 6.dp)
                            .clip(RoundedCornerShape(999.dp))
                            .background(if (up) Color(0x3325D366) else Color(0x33F87171))
                            .padding(horizontal = 8.dp, vertical = 3.dp),
                    ) {
                        Icon(
                            if (up) Icons.AutoMirrored.Filled.TrendingUp else Icons.AutoMirrored.Filled.TrendingDown,
                            contentDescription = null,
                            tint = if (up) Color(0xFF6EE7A8) else Color(0xFFFCA5A5),
                            modifier = Modifier.size(13.dp),
                        )
                        Spacer(Modifier.width(3.dp))
                        Text("${if (up) "+" else ""}$trendPct%", color = if (up) Color(0xFF6EE7A8) else Color(0xFFFCA5A5), fontSize = 11.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }
            Spacer(Modifier.height(6.dp))
            Text("${o.ticketsSold} bookings · ${o.bookingsToday} today", color = Color(0xCCE0E8FF), fontSize = 13.sp)
            if (o.trend.any { it > 0 }) {
                Spacer(Modifier.height(18.dp))
                Sparkline(o.trend, Modifier.fillMaxWidth().height(52.dp), Color(0xFF7DA9FF))
                Spacer(Modifier.height(6.dp))
                Text("last 14 days", color = Color(0x99CFE0FF), fontSize = 11.sp)
            }
        }
    }
}

/** A single clean stat strip — replaces the boxed count-cards. Equal columns
 *  divided by hairlines, so 3 (or 4) metrics read as one considered unit. */
@Composable
private fun StatStrip(tiles: List<Tile>) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(10.dp, RoundedCornerShape(18.dp), clip = false, spotColor = Color(0x1A0F172A))
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(1.dp, Color(0x0F0F172A), RoundedCornerShape(18.dp))
            .padding(vertical = 16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        tiles.forEachIndexed { i, t ->
            StatColumn(Modifier.weight(1f), t)
            if (i < tiles.size - 1) {
                LayoutBox(Modifier.width(1.dp).height(46.dp).background(Color(0x140F172A)))
            }
        }
    }
}

@Composable
private fun StatColumn(modifier: Modifier, t: Tile) {
    Column(modifier.padding(horizontal = 6.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Icon(t.icon, contentDescription = null, tint = AuthAccent, modifier = Modifier.size(18.dp))
        Spacer(Modifier.height(9.dp))
        Text(t.value, fontSize = 22.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
        Spacer(Modifier.height(1.dp))
        Text(t.label, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = AuthMuted, maxLines = 1)
    }
}

@Composable
private fun Sparkline(values: List<Double>, modifier: Modifier, color: Color) {
    val max = (values.maxOrNull() ?: 0.0).coerceAtLeast(1.0)
    Canvas(modifier) {
        if (values.size < 2) return@Canvas
        val stepX = size.width / (values.size - 1)
        // Inset the top a little so the peak isn't clipped against the card edge.
        val top = size.height * 0.12f
        val h = size.height - top
        val pts = values.mapIndexed { i, v ->
            Offset(i * stepX, top + (h - (v / max * h).toFloat()))
        }
        // Soft gradient fill beneath the line.
        val fill = Path().apply {
            moveTo(0f, size.height)
            pts.forEach { lineTo(it.x, it.y) }
            lineTo(size.width, size.height)
            close()
        }
        drawPath(fill, Brush.verticalGradient(listOf(color.copy(alpha = 0.35f), color.copy(alpha = 0f))))
        for (i in 0 until pts.size - 1) {
            drawLine(color, pts[i], pts[i + 1], strokeWidth = 5f, cap = StrokeCap.Round)
        }
    }
}

@Composable
private fun EventsTab(api: PartnerApi, token: String, onOpen: (Long, String) -> Unit) {
    RefreshableContent(token, load = { api.events(token) }) { list ->
        if (list.isEmpty()) EmptyState("No events yet") else
            LazyColumn(Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                items(list) { e -> EventCard(e) { onOpen(e.id, e.title) } }
            }
    }
}

/** Maps a raw event status to a semantic colour for the status dot. */
@Composable
private fun statusColor(status: String?): Color = when (status?.lowercase()) {
    "published", "live", "active", "ongoing" -> Color(0xFF16A34A) // green
    "draft", "pending", "scheduled" -> Color(0xFFF59E0B)          // amber
    "cancelled", "canceled" -> Color(0xFFDC2626)                  // red
    else -> MaterialTheme.colorScheme.onSurfaceVariant
}

@Composable
private fun EventCard(e: EventSummary, onClick: () -> Unit) {
    val total = e.totalSlots.coerceAtLeast(1)
    val sold = (e.totalSlots - e.seatsLeft).coerceIn(0, total)
    val fill = sold.toFloat() / total.toFloat()

    Card(
        Modifier.fillMaxWidth().clickable { onClick() },
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
    ) {
        Column(Modifier.padding(16.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text(
                    e.title,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.SemiBold,
                    maxLines = 2,
                    modifier = Modifier.weight(1f, false),
                )
                Text(
                    "₹" + formatInr(e.revenue),
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary,
                )
            }
            Spacer(Modifier.height(6.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                LayoutBox(Modifier.size(8.dp).clip(RoundedCornerShape(4.dp)).background(statusColor(e.status)))
                Spacer(Modifier.width(6.dp))
                Text(
                    listOfNotNull(e.status?.replaceFirstChar { it.uppercase() }, e.category, e.date).joinToString(" · "),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Spacer(Modifier.height(12.dp))
            LinearProgressIndicator(
                progress = { fill },
                modifier = Modifier.fillMaxWidth().height(6.dp).clip(RoundedCornerShape(3.dp)),
                color = MaterialTheme.colorScheme.primary,
                trackColor = MaterialTheme.colorScheme.primaryContainer,
            )
            Spacer(Modifier.height(6.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text("$sold sold", style = MaterialTheme.typography.bodySmall, fontWeight = FontWeight.Medium)
                Text("${e.seatsLeft} of ${e.totalSlots} left", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}

@Composable
private fun VenuesTab(api: PartnerApi, token: String, onOpen: (Long, String) -> Unit) {
    RefreshableContent(token, load = { api.venues(token) }) { list ->
        if (list.isEmpty()) EmptyState("No venues yet") else
            LazyColumn(Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                items(list) { v -> VenueCard(v) { onOpen(v.id, v.name) } }
            }
    }
}

@Composable
private fun VenueCard(v: VenueSummary, onClick: () -> Unit) {
    PressableSurface(onClick = onClick) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            LayoutBox(
                Modifier.size(46.dp).clip(RoundedCornerShape(13.dp))
                    .background(Brush.linearGradient(listOf(Color(0xFFEAF1FF), Color(0xFFDCE8FF)))),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Filled.Place, contentDescription = null, tint = AuthAccent, modifier = Modifier.size(23.dp))
            }
            Spacer(Modifier.width(14.dp))
            Column(Modifier.weight(1f)) {
                Text(v.name, fontSize = 15.5.sp, fontWeight = FontWeight.Bold, color = AuthInk, maxLines = 1)
                Spacer(Modifier.height(1.dp))
                Text(v.location ?: "—", fontSize = 12.5.sp, color = AuthMuted, maxLines = 1)
                Spacer(Modifier.height(7.dp))
                Text("${v.bookings} bookings", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = AuthAccentDeep)
            }
            Spacer(Modifier.width(10.dp))
            Column(horizontalAlignment = Alignment.End) {
                Text("₹" + formatInr(v.revenue), fontSize = 16.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                Spacer(Modifier.height(4.dp))
                Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = Color(0xFFB6C0D0), modifier = Modifier.size(20.dp))
            }
        }
    }
}

/** A premium card that presses in slightly and gives a haptic tick when tapped. */
@Composable
private fun PressableSurface(
    onClick: () -> Unit,
    radius: Dp = 18.dp,
    content: @Composable () -> Unit,
) {
    val view = LocalView.current
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val scale by animateFloatAsState(if (pressed) 0.985f else 1f, label = "press")
    LayoutBox(
        modifier = Modifier
            .fillMaxWidth()
            .graphicsLayer { scaleX = scale; scaleY = scale }
            .premiumSurface(radius)
            .clickable(interactionSource = interaction, indication = null) {
                view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
                onClick()
            },
    ) { content() }
}

// ---- Venue day / booking management ------------------------------------

private const val DAY_MS = 86_400_000L
private val RED = Color(0xFFDC2626)
private val GREEN = Color(0xFF16A34A)

private fun todayMillis(): Long {
    val c = java.util.Calendar.getInstance()
    c.set(java.util.Calendar.HOUR_OF_DAY, 12)
    c.set(java.util.Calendar.MINUTE, 0); c.set(java.util.Calendar.SECOND, 0); c.set(java.util.Calendar.MILLISECOND, 0)
    return c.timeInMillis
}

private fun apiDate(ms: Long): String =
    java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US).format(java.util.Date(ms))

private fun prettyDate(ms: Long): String =
    java.text.SimpleDateFormat("EEE, dd MMM", java.util.Locale.getDefault()).format(java.util.Date(ms))

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun VenueDayScreen(
    api: PartnerApi,
    token: String,
    venueId: Long,
    venueName: String,
    onBack: () -> Unit,
    onAnalytics: () -> Unit,
    canPricing: Boolean = true,
    canBookings: Boolean = true,
) {
    var dayMillis by remember { mutableStateOf(todayMillis()) }
    var reload by remember { mutableStateOf(0) }
    var addForSlot by remember { mutableStateOf<DaySlot?>(null) }
    var addForCell by remember { mutableStateOf<CellTarget?>(null) }
    var cancelTarget by remember { mutableStateOf<DayBooking?>(null) }
    var showPricing by remember { mutableStateOf(false) }
    var booking by remember { mutableStateOf(false) }
    var payLink by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()
    val date = apiDate(dayMillis)
    val state by produceState<UiState<DayGrid>>(UiState.Loading, dayMillis, reload) {
        value = runCatchingUi { api.venueDay(token, venueId, date) }
    }

    if (showPricing) {
        VenuePricingScreen(api, token, venueId, venueName, onBack = { showPricing = false; reload++ })
        return
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White, scrolledContainerColor = Color.White),
                title = { Text(venueName, maxLines = 1, fontWeight = FontWeight.Bold, color = AuthInk, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = AuthInk) }
                },
                actions = {
                    if (canPricing) HeaderIcon(Icons.Filled.Tune, "Pricing & slots") { showPricing = true }
                    HeaderIcon(Icons.Filled.BarChart, "Analytics") { onAnalytics() }
                    Spacer(Modifier.width(4.dp))
                },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().background(AuthPageBg).padding(padding)) {
            Row(
                Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp)
                    .premiumSurface(14.dp).padding(4.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                IconButton(onClick = { dayMillis -= DAY_MS }) { Icon(Icons.Filled.ChevronLeft, contentDescription = "Previous day", tint = AuthAccent) }
                Text(prettyDate(dayMillis), fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk)
                IconButton(onClick = { dayMillis += DAY_MS }) { Icon(Icons.Filled.ChevronRight, contentDescription = "Next day", tint = AuthAccent) }
            }
            Loaded(state) { grid ->
                LazyColumn(
                    Modifier.fillMaxSize().padding(horizontal = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                    contentPadding = androidx.compose.foundation.layout.PaddingValues(bottom = 24.dp),
                ) {
                    item {
                        if (grid.isBlocked) {
                            Row(
                                Modifier.fillMaxWidth().premiumSurface().padding(16.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                Text("Closed on this day", color = RED, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                                if (canBookings) {
                                    TextButton(onClick = { scope.launch { runCatching { api.setDateClosed(token, venueId, date, false) }; reload++ } }) { Text("Reopen", color = AuthAccent, fontWeight = FontWeight.SemiBold) }
                                }
                            }
                        } else if (canBookings) {
                            TextButton(onClick = { scope.launch { runCatching { api.setDateClosed(token, venueId, date, true) }; reload++ } }) {
                                Text("Close this day (maintenance/holiday)", color = AuthMuted, fontSize = 13.sp)
                            }
                        }
                    }
                    if (grid.courts.isNotEmpty()) {
                        item {
                            CourtGrid(
                                grid = grid,
                                canBookings = canBookings && !grid.isBlocked,
                                onAddCell = { slot, courtId, courtName ->
                                    // Court's own rate wins over the slot base price.
                                    val cellPrice = slot.courts.firstOrNull { it.courtId == courtId }?.price ?: slot.price
                                    addForCell = CellTarget(slot, courtId, courtName, cellPrice)
                                },
                                onCancel = { b -> cancelTarget = b },
                            )
                        }
                    } else {
                        if (grid.slots.isEmpty()) {
                            item { Text("No slots configured for this venue.", Modifier.padding(16.dp), color = AuthMuted) }
                        }
                        items(grid.slots) { slot ->
                            SlotCard(
                                slot = slot,
                                blocked = grid.isBlocked,
                                canBookings = canBookings,
                                onAdd = { addForSlot = slot },
                                onCancel = { bid -> scope.launch { runCatching { api.cancelBooking(token, bid) }; reload++ } },
                            )
                        }
                    }
                }
            }
        }
    }

    addForSlot?.let { slot ->
        WalkInDialog(
            slotLabel = slot.time ?: slot.label,
            amount = slot.price,
            busy = booking,
            onDismiss = { addForSlot = null },
            onConfirm = { name, phone, method ->
                booking = true
                scope.launch {
                    runCatching { api.createWalkIn(token, venueId, slot.slotId, date, name, phone, null, method) }
                        .onSuccess { payLink = it.paymentLink }
                    booking = false; addForSlot = null; reload++
                }
            },
        )
    }

    addForCell?.let { t ->
        WalkInDialog(
            slotLabel = "${t.slot.time ?: t.slot.label} · ${t.courtName}",
            amount = t.price,
            busy = booking,
            onDismiss = { addForCell = null },
            onConfirm = { name, phone, method ->
                booking = true
                scope.launch {
                    runCatching { api.createWalkIn(token, venueId, t.slot.slotId, date, name, phone, t.courtId, method) }
                        .onSuccess { payLink = it.paymentLink }
                    booking = false; addForCell = null; reload++
                }
            },
        )
    }

    payLink?.let { link -> PaymentLinkDialog(link) { payLink = null } }

    cancelTarget?.let { b ->
        AlertDialog(
            onDismissRequest = { cancelTarget = null },
            title = { Text("Cancel booking?") },
            text = { Text("${b.customer} — this frees the court for that time.") },
            confirmButton = {
                TextButton(onClick = {
                    cancelTarget = null
                    scope.launch { runCatching { api.cancelBooking(token, b.id) }; reload++ }
                }) { Text("Cancel booking", color = RED, fontWeight = FontWeight.SemiBold) }
            },
            dismissButton = { TextButton(onClick = { cancelTarget = null }) { Text("Keep") } },
        )
    }
}

/** A tapped free cell in the court grid: which slot + which court. */
private data class CellTarget(val slot: DaySlot, val courtId: Long, val courtName: String, val price: Double = 0.0)

/** Playo-style day grid: courts as columns, time slots as rows. Free cells add a
 *  walk-in; booked cells open a cancel confirm. Scrolls horizontally for many courts. */
@Composable
private fun CourtGrid(
    grid: DayGrid,
    canBookings: Boolean,
    onAddCell: (DaySlot, Long, String) -> Unit,
    onCancel: (DayBooking) -> Unit,
) {
    val timeW = 58.dp
    val cellW = 96.dp
    val courtName = { id: Long -> grid.courts.firstOrNull { it.id == id }?.name ?: "Court" }
    Column(Modifier.fillMaxWidth().premiumSurface().padding(vertical = 12.dp)) {
        Row(Modifier.padding(start = 14.dp, end = 14.dp, bottom = 10.dp), verticalAlignment = Alignment.CenterVertically) {
            LegendDot(Color(0xFF16A34A), "Open"); Spacer(Modifier.width(14.dp)); LegendDot(AuthAccent, "Booked")
        }
        Column(Modifier.horizontalScroll(rememberScrollState())) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                LayoutBox(Modifier.width(timeW).padding(start = 14.dp)) { Text("Time", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = AuthMuted) }
                grid.courts.forEach { c ->
                    Column(Modifier.width(cellW).padding(horizontal = 4.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(c.name, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = AuthInk, maxLines = 1)
                        if (c.sports.isNotEmpty()) Text(c.sports.first(), fontSize = 10.sp, color = AuthMuted, maxLines = 1)
                    }
                }
            }
            Spacer(Modifier.height(8.dp))
            grid.slots.forEach { slot ->
                Row(Modifier.padding(vertical = 3.dp), verticalAlignment = Alignment.CenterVertically) {
                    LayoutBox(Modifier.width(timeW).height(56.dp).padding(start = 14.dp), contentAlignment = Alignment.CenterStart) {
                        Text(slot.time ?: slot.label, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = AuthInk, maxLines = 2)
                    }
                    slot.courts.forEach { cell ->
                        GridCell(
                            cell = cell,
                            width = cellW,
                            canBook = canBookings && slot.isOpen,
                            onFree = { onAddCell(slot, cell.courtId, courtName(cell.courtId)) },
                            onBooked = { onCancel(it) },
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun LegendDot(color: Color, label: String) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        LayoutBox(Modifier.size(9.dp).clip(RoundedCornerShape(99.dp)).background(color))
        Spacer(Modifier.width(6.dp))
        Text(label, fontSize = 11.5.sp, color = AuthMuted)
    }
}

@Composable
private fun GridCell(cell: CourtCell, width: Dp, canBook: Boolean, onFree: () -> Unit, onBooked: (DayBooking) -> Unit) {
    val booked = cell.isBooked
    LayoutBox(
        modifier = Modifier
            .width(width).height(56.dp).padding(horizontal = 4.dp)
            .clip(RoundedCornerShape(11.dp))
            .background(if (booked) Color(0x142F6BFF) else Color(0x1416A34A))
            .border(1.dp, if (booked) Color(0x332F6BFF) else Color(0x3316A34A), RoundedCornerShape(11.dp))
            .clickable(enabled = booked || canBook) {
                if (booked) cell.bookings.firstOrNull()?.let(onBooked) else onFree()
            },
        contentAlignment = Alignment.Center,
    ) {
        if (booked) {
            val b = cell.bookings.firstOrNull()
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Text(b?.customer?.take(9) ?: "Booked", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = AuthAccentDeep, maxLines = 1)
                if ((b?.checkedIn ?: 0) > 0) Text("✓ in", fontSize = 9.5.sp, fontWeight = FontWeight.SemiBold, color = GREEN)
            }
        } else {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Text("Open", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = GREEN)
                if (cell.price > 0) Text("₹${cell.price.toInt()}", fontSize = 10.sp, color = AuthMuted)
            }
        }
    }
}

@Composable
private fun SlotCard(slot: DaySlot, blocked: Boolean, canBookings: Boolean, onAdd: () -> Unit, onCancel: (Long) -> Unit) {
    val full = slot.available <= 0
    val cap = slot.capacity.coerceAtLeast(1)
    val fill = (slot.booked.toFloat() / cap).coerceIn(0f, 1f)
    val capColor = if (full) RED else GREEN
    Column(Modifier.fillMaxWidth().premiumSurface().padding(16.dp)) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Column {
                Text(slot.time ?: slot.label, fontSize = 15.5.sp, fontWeight = FontWeight.Bold, color = AuthInk)
                if (slot.price > 0) {
                    Spacer(Modifier.height(1.dp))
                    Text("₹" + formatInr(slot.price), fontSize = 12.5.sp, color = AuthMuted)
                }
            }
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(capColor.copy(alpha = 0.12f)).padding(horizontal = 10.dp, vertical = 5.dp),
            ) {
                Text("${slot.booked}/${slot.capacity}", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = capColor)
            }
        }
        Spacer(Modifier.height(11.dp))
        LayoutBox(Modifier.fillMaxWidth().height(6.dp).clip(RoundedCornerShape(99.dp)).background(Color(0xFFEDF0F5))) {
            LayoutBox(Modifier.fillMaxWidth(fill).fillMaxHeight().clip(RoundedCornerShape(99.dp)).background(if (full) RED else AuthAccent))
        }
        slot.bookings.forEach { b ->
            Spacer(Modifier.height(12.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Row(Modifier.weight(1f), verticalAlignment = Alignment.CenterVertically) {
                    LayoutBox(
                        Modifier.size(30.dp).clip(RoundedCornerShape(99.dp)).background(Color(0x142F6BFF)),
                        contentAlignment = Alignment.Center,
                    ) { Text(b.customer.take(1).uppercase(), fontSize = 13.sp, fontWeight = FontWeight.Bold, color = AuthAccentDeep) }
                    Spacer(Modifier.width(10.dp))
                    Column {
                        Text(b.customer, fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = AuthInk, maxLines = 1)
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(if (b.channel == "offline") "Walk-in" else "Online", fontSize = 11.5.sp, color = AuthMuted)
                            if (b.checkedIn > 0) {
                                Spacer(Modifier.width(6.dp))
                                Text("✓ checked in", fontSize = 11.5.sp, fontWeight = FontWeight.SemiBold, color = GREEN)
                            }
                        }
                    }
                }
                if (canBookings) {
                    IconButton(onClick = { onCancel(b.id) }) { Icon(Icons.Filled.Close, contentDescription = "Cancel", tint = Color(0xFFC0491F).copy(alpha = 0.8f), modifier = Modifier.size(18.dp)) }
                }
            }
        }
        if (canBookings && !blocked && !full && slot.isOpen) {
            Spacer(Modifier.height(14.dp))
            Row(
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp))
                    .background(Color(0x0F2F6BFF))
                    .border(1.dp, Color(0x242F6BFF), RoundedCornerShape(12.dp))
                    .clickable { onAdd() }
                    .padding(vertical = 11.dp),
            ) {
                Icon(Icons.Filled.Add, contentDescription = null, tint = AuthAccent, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(6.dp))
                Text("Add walk-in booking", fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = AuthAccentDeep)
            }
        }
    }
}

@Composable
private fun WalkInDialog(
    slotLabel: String,
    amount: Double,
    busy: Boolean = false,
    onDismiss: () -> Unit,
    onConfirm: (String, String, PayMethod) -> Unit,
) {
    var name by remember { mutableStateOf("") }
    var phone by remember { mutableStateOf("") }
    var method by remember { mutableStateOf(PayMethod.CASH) }
    val view = LocalView.current

    Dialog(onDismissRequest = onDismiss) {
        Column(
            Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(24.dp))
                .background(Color.White)
                .padding(22.dp),
        ) {
            // Header: what's being booked, and for how much.
            Row(verticalAlignment = Alignment.CenterVertically) {
                LayoutBox(
                    Modifier.size(44.dp).clip(RoundedCornerShape(13.dp))
                        .background(Brush.linearGradient(listOf(Color(0xFFEAF1FF), Color(0xFFDCE8FF)))),
                    contentAlignment = Alignment.Center,
                ) { Icon(Icons.Filled.Add, contentDescription = null, tint = AuthAccent, modifier = Modifier.size(22.dp)) }
                Spacer(Modifier.width(12.dp))
                Column(Modifier.weight(1f)) {
                    Text("Walk-in booking", fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                    Spacer(Modifier.height(1.dp))
                    Text(slotLabel, fontSize = 12.sp, color = AuthMuted, maxLines = 2)
                }
            }

            if (amount > 0) {
                Spacer(Modifier.height(16.dp))
                Row(
                    Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp))
                        .background(Color(0xFFF6F8FC))
                        .border(1.dp, CardBorder, RoundedCornerShape(14.dp))
                        .padding(horizontal = 14.dp, vertical = 12.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text("Amount to collect", fontSize = 13.sp, color = AuthMuted)
                    Text("₹" + formatInr(amount), fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                }
            }

            Spacer(Modifier.height(16.dp))
            val fieldColors = OutlinedTextFieldDefaults.colors(
                focusedBorderColor = AuthAccent, focusedLabelColor = AuthAccent,
                cursorColor = AuthAccent, unfocusedBorderColor = Color(0x1F0F172A),
            )
            OutlinedTextField(
                value = name,
                onValueChange = { name = it },
                label = { Text("Customer name") },
                singleLine = true,
                shape = RoundedCornerShape(12.dp),
                colors = fieldColors,
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(10.dp))
            OutlinedTextField(
                value = phone,
                onValueChange = { phone = it.filter { c -> c.isDigit() }.take(10) },
                label = { Text("Phone (optional)") },
                singleLine = true,
                shape = RoundedCornerShape(12.dp),
                colors = fieldColors,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                modifier = Modifier.fillMaxWidth(),
            )

            Spacer(Modifier.height(18.dp))
            Text("PAYMENT", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = AuthMuted, letterSpacing = 1.4.sp)
            Spacer(Modifier.height(10.dp))
            // Two rows of two so every label stays readable at any width.
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                PayChip(PayMethod.CASH, method, Modifier.weight(1f)) { method = it; view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP) }
                PayChip(PayMethod.UPI, method, Modifier.weight(1f)) { method = it; view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP) }
            }
            Spacer(Modifier.height(8.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                PayChip(PayMethod.CARD, method, Modifier.weight(1f)) { method = it; view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP) }
                PayChip(PayMethod.LATER, method, Modifier.weight(1f)) { method = it; view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP) }
            }
            Spacer(Modifier.height(8.dp))
            PayChip(PayMethod.LINK, method, Modifier.fillMaxWidth()) { method = it; view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP) }

            if (method == PayMethod.LINK) {
                Spacer(Modifier.height(8.dp))
                Text(
                    "A Razorpay payment link is created and texted to the customer. The booking stays unpaid until they pay.",
                    fontSize = 11.5.sp, color = AuthMuted, lineHeight = 16.sp,
                )
            }

            Spacer(Modifier.height(20.dp))
            GradientCta(
                text = if (busy) "Booking…" else "Confirm booking",
                enabled = !busy && name.isNotBlank(),
                loading = busy,
            ) {
                view.performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
                onConfirm(name.trim(), phone.trim(), method)
            }
            Spacer(Modifier.height(4.dp))
            TextButton(onClick = onDismiss, modifier = Modifier.fillMaxWidth()) {
                Text("Cancel", color = AuthMuted, fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

/** A selectable payment-method chip. */
@Composable
private fun PayChip(value: PayMethod, selected: PayMethod, modifier: Modifier = Modifier, onPick: (PayMethod) -> Unit) {
    val on = value == selected
    Row(
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
        modifier = modifier
            .clip(RoundedCornerShape(12.dp))
            .background(if (on) Color(0x142F6BFF) else Color(0xFFF8FAFC))
            .border(if (on) 1.5.dp else 1.dp, if (on) AuthAccent else Color(0x1F0F172A), RoundedCornerShape(12.dp))
            .clickable { onPick(value) }
            .padding(vertical = 11.dp, horizontal = 8.dp),
    ) {
        if (on) {
            LayoutBox(Modifier.size(7.dp).clip(RoundedCornerShape(99.dp)).background(AuthAccent))
            Spacer(Modifier.width(7.dp))
        }
        Text(
            value.label,
            fontSize = 13.sp,
            fontWeight = if (on) FontWeight.Bold else FontWeight.Medium,
            color = if (on) AuthAccentDeep else AuthInk,
            maxLines = 1,
        )
    }
}

/** Shown after a "payment link" walk-in so the desk can share it immediately. */
@Composable
private fun PaymentLinkDialog(link: String, onDismiss: () -> Unit) {
    val context = LocalContext.current
    Dialog(onDismissRequest = onDismiss) {
        Column(
            Modifier.fillMaxWidth().clip(RoundedCornerShape(24.dp)).background(Color.White).padding(22.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            AuthIconBadge(Icons.Filled.Payments)
            Spacer(Modifier.height(14.dp))
            Text("Payment link ready", fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
            Spacer(Modifier.height(6.dp))
            Text(
                "Razorpay has texted this to the customer. You can share it again below.",
                fontSize = 13.sp, color = AuthMuted, textAlign = TextAlign.Center, lineHeight = 18.sp,
            )
            Spacer(Modifier.height(14.dp))
            Text(
                link,
                fontSize = 12.5.sp,
                color = AuthAccentDeep,
                fontWeight = FontWeight.SemiBold,
                textAlign = TextAlign.Center,
                modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp))
                    .background(Color(0xFFF6F8FC)).border(1.dp, CardBorder, RoundedCornerShape(12.dp))
                    .padding(12.dp),
            )
            Spacer(Modifier.height(18.dp))
            GradientCta(text = "Share link", enabled = true, loading = false) {
                val send = Intent(Intent.ACTION_SEND).apply {
                    type = "text/plain"
                    putExtra(Intent.EXTRA_TEXT, "Complete your booking payment: $link")
                }
                context.startActivity(Intent.createChooser(send, "Share payment link"))
            }
            Spacer(Modifier.height(4.dp))
            TextButton(onClick = onDismiss, modifier = Modifier.fillMaxWidth()) {
                Text("Done", color = AuthMuted, fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

// ---- Venue pricing / slot editor ---------------------------------------

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun VenuePricingScreen(api: PartnerApi, token: String, venueId: Long, venueName: String, onBack: () -> Unit) {
    var reload by remember { mutableStateOf(0) }
    var editing by remember { mutableStateOf<SlotEdit?>(null) }
    var adding by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    val state by produceState<UiState<List<SlotEdit>>>(UiState.Loading, reload) {
        value = runCatchingUi { api.venueSlots(token, venueId) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Pricing & slots", maxLines = 1) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back") }
                },
                actions = {
                    IconButton(onClick = { adding = true }) { Icon(Icons.Filled.Add, contentDescription = "Add slot") }
                },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            Text(
                venueName,
                Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold,
            )
            Loaded(state) { slots ->
                if (slots.isEmpty()) {
                    EmptyState("No slots yet. Tap + to add your first bookable slot.")
                } else {
                    LazyColumn(
                        Modifier.fillMaxSize().padding(horizontal = 16.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp),
                        contentPadding = androidx.compose.foundation.layout.PaddingValues(bottom = 24.dp),
                    ) {
                        items(slots) { slot ->
                            Card(
                                Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = Color.White),
                                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                            ) {
                                Row(Modifier.padding(16.dp).fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                    Column(Modifier.weight(1f)) {
                                        Text(slot.time, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                                        Text(
                                            listOfNotNull(
                                                slot.day,
                                                "cap ${slot.capacity}",
                                                if (slot.isOpen) "open" else "closed",
                                            ).joinToString(" · "),
                                            style = MaterialTheme.typography.bodySmall,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                                        )
                                    }
                                    Text(
                                        "₹" + formatInr(slot.price),
                                        style = MaterialTheme.typography.titleMedium,
                                        fontWeight = FontWeight.Bold,
                                        color = MaterialTheme.colorScheme.primary,
                                    )
                                    IconButton(onClick = { editing = slot }) { Icon(Icons.Filled.Edit, contentDescription = "Edit") }
                                    IconButton(onClick = { scope.launch { runCatching { api.deleteSlot(token, venueId, slot.id) }; reload++ } }) {
                                        Icon(Icons.Filled.Delete, contentDescription = "Delete", tint = RED)
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if (adding) {
        SlotEditDialog(
            existing = null,
            onDismiss = { adding = false },
            onSave = { day, time, price, capacity, isOpen ->
                adding = false
                scope.launch { runCatching { api.saveSlot(token, venueId, null, day, time, price, capacity, isOpen) }; reload++ }
            },
        )
    }
    editing?.let { slot ->
        SlotEditDialog(
            existing = slot,
            onDismiss = { editing = null },
            onSave = { day, time, price, capacity, isOpen ->
                editing = null
                scope.launch { runCatching { api.saveSlot(token, venueId, slot.id, day, time, price, capacity, isOpen) }; reload++ }
            },
        )
    }
}

@Composable
private fun SlotEditDialog(
    existing: SlotEdit?,
    onDismiss: () -> Unit,
    onSave: (day: String?, time: String, price: Double, capacity: Int, isOpen: Boolean) -> Unit,
) {
    var day by remember { mutableStateOf(existing?.day ?: "") }
    var time by remember { mutableStateOf(existing?.time ?: "") }
    var price by remember { mutableStateOf(existing?.price?.let { if (it == it.toLong().toDouble()) it.toLong().toString() else it.toString() } ?: "") }
    var capacity by remember { mutableStateOf((existing?.capacity ?: 1).toString()) }
    var isOpen by remember { mutableStateOf(existing?.isOpen ?: true) }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(if (existing == null) "Add slot" else "Edit slot") },
        text = {
            Column {
                OutlinedTextField(day, { day = it }, label = { Text("Day (e.g. Mon-Fri, Sat-Sun)") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(8.dp))
                OutlinedTextField(time, { time = it }, label = { Text("Time (e.g. 06:00 AM - 07:00 AM)") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(8.dp))
                OutlinedTextField(price, { price = it.filter { c -> c.isDigit() } }, label = { Text("Price (₹)") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(8.dp))
                OutlinedTextField(capacity, { capacity = it.filter { c -> c.isDigit() } }, label = { Text("Capacity (courts)") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(8.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("Open for booking", Modifier.weight(1f))
                    Switch(checked = isOpen, onCheckedChange = { isOpen = it })
                }
            }
        },
        confirmButton = {
            TextButton(
                onClick = { onSave(day.trim().ifBlank { null }, time.trim(), price.toDoubleOrNull() ?: 0.0, capacity.toIntOrNull() ?: 1, isOpen) },
                enabled = time.isNotBlank(),
            ) { Text("Save") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel") } },
    )
}

// ---- Reports ------------------------------------------------------------

private fun pickDate(context: Context, current: Long, onPicked: (Long) -> Unit) {
    val c = java.util.Calendar.getInstance().apply { timeInMillis = current }
    android.app.DatePickerDialog(
        context,
        { _, y, m, d ->
            val nc = java.util.Calendar.getInstance()
            nc.set(y, m, d, 12, 0, 0)
            onPicked(nc.timeInMillis)
        },
        c.get(java.util.Calendar.YEAR),
        c.get(java.util.Calendar.MONTH),
        c.get(java.util.Calendar.DAY_OF_MONTH),
    ).show()
}

private fun shareCsv(context: Context, from: String, to: String, csv: String) {
    val file = File(context.cacheDir, "bookings_${from}_to_$to.csv")
    file.writeText(csv)
    val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
    val send = Intent(Intent.ACTION_SEND).apply {
        type = "text/csv"
        putExtra(Intent.EXTRA_STREAM, uri)
        putExtra(Intent.EXTRA_SUBJECT, "Booking report $from to $to")
        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
    }
    context.startActivity(Intent.createChooser(send, "Share report").addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ReportsScreen(api: PartnerApi, token: String, onBack: () -> Unit) {
    val context = LocalContext.current
    var fromMs by remember { mutableStateOf(todayMillis() - 30 * DAY_MS) }
    var toMs by remember { mutableStateOf(todayMillis()) }
    var busy by remember { mutableStateOf(false) }
    var msg by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Reports") },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back") } },
            )
        },
    ) { padding ->
        Column(
            Modifier.fillMaxSize().padding(padding).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            Text("Booking report", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
            Text(
                "Download a CSV of all bookings across your events and venues for a date range.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            DateRow("From", prettyDate(fromMs)) { pickDate(context, fromMs) { fromMs = it } }
            DateRow("To", prettyDate(toMs)) { pickDate(context, toMs) { toMs = it } }
            Button(
                enabled = !busy,
                onClick = {
                    busy = true
                    msg = null
                    scope.launch {
                        try {
                            val csv = api.reportCsv(token, apiDate(fromMs), apiDate(toMs))
                            shareCsv(context, apiDate(fromMs), apiDate(toMs), csv)
                            msg = "Report ready — pick where to save or share it."
                        } catch (e: Exception) {
                            msg = e.message ?: "Could not build report"
                        } finally {
                            busy = false
                        }
                    }
                },
                modifier = Modifier.fillMaxWidth(),
            ) { Text(if (busy) "Preparing…" else "Download / share CSV") }
            if (msg != null) {
                Text(msg!!, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.primary)
            }
        }
    }
}

@Composable
private fun DateRow(label: String, value: String, onClick: () -> Unit) {
    Card(
        Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
    ) {
        Row(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 6.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Text(label, style = MaterialTheme.typography.bodyLarge)
            TextButton(onClick = onClick) { Text(value) }
        }
    }
}

// ---- Staff (desk persons) ----------------------------------------------

private fun permLabel(p: String): String = when (p) {
    "bookings" -> "Bookings & walk-ins"
    "checkin" -> "Ticket / slot check-in"
    "pricing" -> "Pricing & slots"
    "reports" -> "Reports"
    else -> p
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun StaffScreen(api: PartnerApi, token: String, onBack: () -> Unit) {
    var reload by remember { mutableStateOf(0) }
    var adding by remember { mutableStateOf(false) }
    var editing by remember { mutableStateOf<StaffMember?>(null) }
    val scope = rememberCoroutineScope()
    val state by produceState<UiState<List<StaffMember>>>(UiState.Loading, reload) {
        value = runCatchingUi { api.staff(token) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Desk staff") },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back") } },
                actions = { IconButton(onClick = { adding = true }) { Icon(Icons.Filled.Add, contentDescription = "Add") } },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            Loaded(state) { list ->
                if (list.isEmpty()) {
                    EmptyState("No desk staff yet. Tap + to add a front-desk login.")
                } else {
                    LazyColumn(
                        Modifier.fillMaxSize().padding(16.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp),
                    ) {
                        items(list) { m ->
                            Card(
                                Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(16.dp),
                                colors = CardDefaults.cardColors(containerColor = Color.White),
                                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                            ) {
                                Column(Modifier.padding(16.dp)) {
                                    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                        Column(Modifier.weight(1f)) {
                                            Text(m.name, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                                            Text(m.email, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                        }
                                        IconButton(onClick = { editing = m }) { Icon(Icons.Filled.Edit, contentDescription = "Edit") }
                                        IconButton(onClick = { scope.launch { runCatching { api.deleteStaff(token, m.id) }; reload++ } }) {
                                            Icon(Icons.Filled.Delete, contentDescription = "Delete", tint = RED)
                                        }
                                    }
                                    Spacer(Modifier.height(6.dp))
                                    Text(
                                        if (m.permissions.isEmpty()) "No permissions" else m.permissions.joinToString(" · ") { permLabel(it) },
                                        style = MaterialTheme.typography.bodySmall,
                                        color = MaterialTheme.colorScheme.primary,
                                    )
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if (adding) {
        StaffDialog(
            existing = null,
            onDismiss = { adding = false },
            onSaveNew = { name, email, pass, perms ->
                adding = false
                scope.launch { runCatching { api.createStaff(token, name, email, pass, perms) }; reload++ }
            },
            onSavePerms = {},
        )
    }
    editing?.let { m ->
        StaffDialog(
            existing = m,
            onDismiss = { editing = null },
            onSaveNew = { _, _, _, _ -> },
            onSavePerms = { perms ->
                editing = null
                scope.launch { runCatching { api.updateStaff(token, m.id, perms) }; reload++ }
            },
        )
    }
}

@Composable
private fun StaffDialog(
    existing: StaffMember?,
    onDismiss: () -> Unit,
    onSaveNew: (name: String, email: String, password: String, perms: List<String>) -> Unit,
    onSavePerms: (perms: List<String>) -> Unit,
) {
    var name by remember { mutableStateOf(existing?.name ?: "") }
    var email by remember { mutableStateOf(existing?.email ?: "") }
    var password by remember { mutableStateOf("") }
    val perms = remember { mutableStateListOf<String>().apply { addAll(existing?.permissions ?: listOf("bookings", "checkin")) } }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(if (existing == null) "Add desk person" else "Edit permissions") },
        text = {
            Column {
                if (existing == null) {
                    OutlinedTextField(name, { name = it }, label = { Text("Name") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                    Spacer(Modifier.height(8.dp))
                    OutlinedTextField(email, { email = it }, label = { Text("Email") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                    Spacer(Modifier.height(8.dp))
                    OutlinedTextField(password, { password = it }, label = { Text("Password (min 6)") }, singleLine = true, visualTransformation = PasswordVisualTransformation(), modifier = Modifier.fillMaxWidth())
                    Spacer(Modifier.height(12.dp))
                }
                Text("Permissions", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                STAFF_PERMISSIONS.forEach { p ->
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Checkbox(checked = p in perms, onCheckedChange = { if (it) perms.add(p) else perms.remove(p) })
                        Text(permLabel(p))
                    }
                }
            }
        },
        confirmButton = {
            TextButton(
                onClick = {
                    if (existing == null) onSaveNew(name.trim(), email.trim(), password, perms.toList())
                    else onSavePerms(perms.toList())
                },
                enabled = existing != null || (name.isNotBlank() && email.isNotBlank() && password.length >= 6),
            ) { Text("Save") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel") } },
    )
}

// ---- Analytics detail ---------------------------------------------------

private enum class AnalyticsKind { Event, Venue }
private data class AnalyticsTarget(val kind: AnalyticsKind, val id: Long, val name: String)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun AnalyticsScreen(api: PartnerApi, token: String, target: AnalyticsTarget, onBack: () -> Unit) {
    val state by produceState<UiState<Analytics>>(UiState.Loading, target.id) {
        value = runCatchingUi {
            when (target.kind) {
                AnalyticsKind.Event -> api.eventAnalytics(token, target.id)
                AnalyticsKind.Venue -> api.venueAnalytics(token, target.id)
            }
        }
    }
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(target.name, maxLines = 1) },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            Loaded(state) { a ->
                LazyColumn(
                    Modifier.fillMaxSize().padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(16.dp),
                ) {
                    item { StatGrid(a.stats) }
                    item { TrendCard(a) }
                    if (a.tiers.isNotEmpty()) item { TiersCard(a.tiers) }
                }
            }
        }
    }
}

@Composable
private fun StatGrid(stats: List<StatItem>) {
    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        stats.chunked(2).forEach { row ->
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                row.forEach { s ->
                    Card(
                        Modifier.weight(1f),
                        shape = RoundedCornerShape(16.dp),
                        colors = CardDefaults.cardColors(containerColor = Color.White),
                        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                    ) {
                        Column(Modifier.padding(14.dp)) {
                            Text(
                                s.label,
                                style = MaterialTheme.typography.labelMedium,
                                color = MaterialTheme.colorScheme.primary,
                            )
                            Text(s.value, style = MaterialTheme.typography.titleLarge)
                        }
                    }
                }
                if (row.size == 1) Spacer(Modifier.weight(1f))
            }
        }
    }
}

@Composable
private fun TrendCard(a: Analytics) {
    Card(
        Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
    ) {
        Column(Modifier.padding(16.dp)) {
            Text("Revenue — last 14 days", style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.height(12.dp))
            BarChart(
                values = a.sales.map { it.revenue },
                modifier = Modifier.fillMaxWidth().height(140.dp),
            )
            Spacer(Modifier.height(8.dp))
            val totalSecondary = a.sales.sumOf { it.secondary }
            val totalRevenue = a.sales.sumOf { it.revenue }
            Text(
                "₹${formatInr(totalRevenue)} · $totalSecondary ${a.secondaryLabel.lowercase()} in this window",
                style = MaterialTheme.typography.bodyMedium,
            )
        }
    }
}

@Composable
private fun BarChart(values: List<Double>, modifier: Modifier = Modifier) {
    val max = (values.maxOrNull() ?: 0.0).coerceAtLeast(1.0)
    val barColor = MaterialTheme.colorScheme.primary
    Canvas(modifier) {
        if (values.isEmpty()) return@Canvas
        val gap = 6f
        val barWidth = ((size.width - gap * (values.size - 1)) / values.size).coerceAtLeast(1f)
        values.forEachIndexed { i, v ->
            val h = (v / max * size.height).toFloat().coerceAtLeast(if (v > 0) 3f else 0f)
            val x = i * (barWidth + gap)
            drawRect(
                color = barColor,
                topLeft = Offset(x, size.height - h),
                size = androidx.compose.ui.geometry.Size(barWidth, h),
            )
        }
    }
}

@Composable
private fun TiersCard(tiers: List<TierRow>) {
    Card(
        Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
    ) {
        Column(Modifier.padding(16.dp)) {
            Text("Revenue by ticket tier", style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.height(8.dp))
            tiers.forEach { t ->
                Row(Modifier.fillMaxWidth().padding(vertical = 6.dp), horizontalArrangement = Arrangement.SpaceBetween) {
                    Column(Modifier.weight(1f)) {
                        Text(t.name, style = MaterialTheme.typography.bodyLarge)
                        Text("${t.tickets} tickets · ${t.orders} orders", style = MaterialTheme.typography.bodySmall)
                    }
                    Column(horizontalAlignment = Alignment.End) {
                        Text("₹" + formatInr(t.revenue), style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.primary)
                        Text("${t.pct}%", style = MaterialTheme.typography.bodySmall)
                    }
                }
            }
        }
    }
}

@Composable
private fun SalesTab(api: PartnerApi, token: String) {
    RefreshableContent(token, load = { api.bookings(token) }) { list ->
        if (list.isEmpty()) EmptyState("No bookings yet") else
            LazyColumn(Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                items(list) { b ->
                    ListCard(
                        title = b.label ?: (b.ticketCode ?: "Booking #${b.id}"),
                        subtitle = "${b.quantity} × · ${b.status ?: ""}",
                        trailing = "₹" + formatInr(b.amount),
                        footer = if (b.checkedIn > 0) "Checked in: ${b.checkedIn}" else b.ticketCode ?: "",
                    )
                }
            }
    }
}

@Composable
private fun ScanTab(api: PartnerApi, token: String) {
    var code by remember { mutableStateOf("") }
    var busy by remember { mutableStateOf(false) }
    var result by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    fun submit(value: String) {
        val trimmed = value.trim()
        if (trimmed.isEmpty() || busy) return
        busy = true
        result = null
        scope.launch {
            result = try {
                api.checkIn(token, trimmed).message
            } catch (e: ApiException) {
                e.message
            } catch (e: Exception) {
                e.message ?: "Check-in failed"
            } finally {
                busy = false
                code = ""
            }
        }
    }

    val scanLauncher = rememberLauncherForActivityResult(ScanContract()) { scan ->
        scan.contents?.let { submit(it) }
    }

    Column(
        Modifier.fillMaxSize().background(AuthPageBg).padding(24.dp),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        AuthIconBadge(Icons.Filled.QrCodeScanner)
        Spacer(Modifier.height(16.dp))
        Text("Ticket check-in", fontSize = 22.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
        Spacer(Modifier.height(6.dp))
        Text(
            "Scan the attendee's ticket QR, or enter the code by hand.",
            fontSize = 13.5.sp, color = AuthMuted, textAlign = TextAlign.Center, lineHeight = 19.sp,
        )
        Spacer(Modifier.height(24.dp))
        GradientCta(
            text = if (busy) "Checking…" else "Scan ticket QR",
            enabled = !busy,
            loading = busy,
        ) {
            result = null
            scanLauncher.launch(
                ScanOptions()
                    .setDesiredBarcodeFormats(ScanOptions.QR_CODE)
                    .setPrompt("Scan ticket QR")
                    .setBeepEnabled(true)
                    .setOrientationLocked(false),
            )
        }
        Spacer(Modifier.height(18.dp))
        Text("or enter the code manually", fontSize = 12.sp, color = AuthMuted)
        Spacer(Modifier.height(14.dp))
        OutlinedTextField(
            value = code,
            onValueChange = { code = it; result = null },
            label = { Text("Ticket code") },
            singleLine = true,
            shape = RoundedCornerShape(12.dp),
            colors = OutlinedTextFieldDefaults.colors(
                focusedBorderColor = AuthAccent, focusedLabelColor = AuthAccent,
                cursorColor = AuthAccent, unfocusedBorderColor = Color(0x1F0F172A),
            ),
            modifier = Modifier.fillMaxWidth(),
        )
        Spacer(Modifier.height(12.dp))
        TextButton(
            enabled = !busy && code.isNotBlank(),
            onClick = { submit(code) },
            modifier = Modifier.fillMaxWidth(),
        ) { Text(if (busy) "Checking…" else "Check in by code", color = AuthAccent, fontWeight = FontWeight.SemiBold) }
        if (result != null) {
            val r = result!!
            val ok = r.startsWith("Checked in", ignoreCase = true)
            val warn = r.startsWith("Already", ignoreCase = true)
            val tone = if (ok) GREEN else if (warn) Color(0xFFF59E0B) else RED
            Spacer(Modifier.height(18.dp))
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp))
                    .background(tone.copy(alpha = 0.10f))
                    .border(1.dp, tone.copy(alpha = 0.35f), RoundedCornerShape(14.dp))
                    .padding(16.dp),
            ) {
                LayoutBox(Modifier.size(9.dp).clip(RoundedCornerShape(99.dp)).background(tone))
                Spacer(Modifier.width(10.dp))
                Text(r, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = tone)
            }
        }
    }
}

// ---- Small building blocks ---------------------------------------------

@Composable
private fun <T> Loaded(state: UiState<T>, content: @Composable (T) -> Unit) {
    when (state) {
        is UiState.Loading -> Box { CircularProgressIndicator(Modifier.padding(32.dp)) }
        is UiState.Error -> EmptyState(state.message)
        is UiState.Data -> content(state.value)
    }
}

@Composable
private fun Box(content: @Composable () -> Unit) {
    Column(
        Modifier.fillMaxSize(),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) { content() }
}

@Composable
private fun EmptyState(message: String) {
    Column(
        Modifier.fillMaxSize().background(AuthPageBg).padding(32.dp),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        LayoutBox(
            Modifier.size(64.dp).clip(RoundedCornerShape(20.dp))
                .background(Brush.linearGradient(listOf(Color(0xFFEAF1FF), Color(0xFFDCE8FF)))),
            contentAlignment = Alignment.Center,
        ) { Icon(Icons.Filled.Description, contentDescription = null, tint = AuthAccent, modifier = Modifier.size(28.dp)) }
        Spacer(Modifier.height(16.dp))
        Text(message, textAlign = TextAlign.Center, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = AuthInk)
        Spacer(Modifier.height(4.dp))
        Text("Pull down to refresh.", textAlign = TextAlign.Center, fontSize = 12.5.sp, color = AuthMuted)
    }
}

@Composable
private fun ListCard(title: String, subtitle: String, trailing: String, footer: String, onClick: (() -> Unit)? = null) {
    val inner: @Composable () -> Unit = {
        Column(Modifier.padding(16.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.Top) {
                Text(title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk, maxLines = 1, modifier = Modifier.weight(1f, false))
                Spacer(Modifier.width(10.dp))
                Text(trailing, fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
            }
            Spacer(Modifier.height(3.dp))
            Text(subtitle, fontSize = 12.5.sp, color = AuthMuted)
            if (footer.isNotBlank()) {
                Spacer(Modifier.height(8.dp))
                Text(footer, fontSize = 11.5.sp, fontWeight = FontWeight.Medium, color = AuthAccentDeep)
            }
        }
    }
    if (onClick != null) PressableSurface(onClick = onClick) { inner() }
    else LayoutBox(Modifier.fillMaxWidth().premiumSurface()) { inner() }
}

private suspend fun <T> runCatchingUi(block: suspend () -> T): UiState<T> = try {
    UiState.Data(block())
} catch (e: ApiException) {
    UiState.Error(e.message ?: "Error")
} catch (e: Exception) {
    UiState.Error(e.message ?: "Something went wrong")
}

