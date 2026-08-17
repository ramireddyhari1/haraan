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
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.automirrored.filled.TrendingDown
import androidx.compose.material.icons.automirrored.filled.TrendingUp
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Badge
import androidx.compose.material.icons.filled.BarChart
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.ChevronLeft
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.ConfirmationNumber
import androidx.compose.material.icons.filled.CurrencyRupee
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material.icons.filled.Tune
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Menu
import androidx.compose.material.icons.automirrored.filled.HelpOutline
import androidx.compose.material.icons.filled.Notifications
import androidx.compose.material.icons.filled.School
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
import androidx.compose.material3.DrawerValue
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalDrawerSheet
import androidx.compose.material3.ModalNavigationDrawer
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.rememberDrawerState
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
import android.net.Uri
import android.view.HapticFeedbackConstants
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.activity.compose.BackHandler
import coil.compose.AsyncImage
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
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.ui.text.style.TextOverflow
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

/**
 * Which console the signed-in partner belongs to. Drives the whole shell.
 *
 * CAFE is its own lane, not a flavour of VENUE. A café owner reading "Courts"
 * and turf language is looking at somebody else's business — the two share their
 * booking arithmetic, but not their vocabulary and not their tabs.
 */
private enum class Lane { EVENT, VENUE, CAFE, BOTH }

private fun laneOf(partnerType: String?): Lane = when (partnerType?.lowercase()) {
    "event", "host", "organiser", "organizer" -> Lane.EVENT
    "venue" -> Lane.VENUE
    "cafe", "café" -> Lane.CAFE
    else -> Lane.BOTH // legacy / no type / admin → combined
}

/** What a bookable unit is called here: a turf has courts, a café has tables. */
private fun resourceNoun(lane: Lane, plural: Boolean = false): String = when (lane) {
    Lane.CAFE -> if (plural) "Tables" else "Table"
    else -> if (plural) "Courts" else "Court"
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun HomeScaffold(api: PartnerApi, session: Session, onSignedOut: () -> Unit) {
    var detail by remember { mutableStateOf<AnalyticsTarget?>(null) }
    var manageVenue by remember { mutableStateOf<Pair<Long, String>?>(null) }
    var showReports by remember { mutableStateOf(false) }
    var showPayouts by remember { mutableStateOf(false) }
    var showCustomers by remember { mutableStateOf(false) }
    var showPackages by remember { mutableStateOf(false) }
    var showAcademy by remember { mutableStateOf(false) }
    var showNotifications by remember { mutableStateOf(false) }
    var showSupport by remember { mutableStateOf(false) }
    var showStaff by remember { mutableStateOf(false) }
    val token = session.token ?: return

    if (showNotifications) {
        NotificationsScreen(api, token, onBack = { showNotifications = false })
        return
    }
    if (showSupport) {
        SupportScreen(api, token, onBack = { showSupport = false })
        return
    }
    if (showAcademy) {
        AcademyScreen(api, token, onBack = { showAcademy = false })
        return
    }
    if (showPackages) {
        PackagesScreen(api, token, session.branchId, onBack = { showPackages = false })
        return
    }
    if (showCustomers) {
        CustomersScreen(api, token, session.branchId, onBack = { showCustomers = false })
        return
    }
    if (showPayouts) {
        PayoutsScreen(api, token, onBack = { showPayouts = false })
        return
    }
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

    // The shell: which branches this account may act on, and at what altitude.
    // Loaded once; a failure leaves ctx null, which renders exactly the
    // single-branch console that shipped before branches existed — the switcher
    // never becomes a way to lock someone out of their own app.
    var ctx by remember { mutableStateOf<PartnerContext?>(null) }
    var branchId by remember { mutableStateOf(session.branchId) }
    LaunchedEffect(token) { ctx = runCatching { api.context(token) }.getOrNull() }

    // A remembered branch the server no longer offers (reassigned, deactivated)
    // must fall back to "all branches" rather than silently filtering everything
    // to an outlet this person can't see.
    LaunchedEffect(ctx) {
        val known = ctx ?: return@LaunchedEffect
        if (branchId != null && known.branches.none { it.id == branchId }) {
            branchId = null
            session.branchId = null
        }
    }
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
                        // Deliberately NOT branch-filtered: an owner wants to know
                        // a booking landed anywhere, not only at the outlet they
                        // happen to be looking at — so the alert names the branch.
                        bookingBanner = fresh.firstOrNull()?.let {
                            val where = it.branch ?: it.label
                            listOfNotNull(where, "₹" + formatInr(it.amount)).joinToString(" · ")
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
            lane = lane,
        )
        return
    }

    // The drawer is the app's one index: every destination and every tool lives
    // in it, so nothing is reachable only by remembering which header glyph it
    // hid behind. The topbar keeps just the bell, which is a live alert rather
    // than a destination.
    val drawerState = rememberDrawerState(DrawerValue.Closed)
    val drawerScope = rememberCoroutineScope()
    fun closeDrawer() { drawerScope.launch { drawerState.close() } }

    ModalNavigationDrawer(
        drawerState = drawerState,
        drawerContent = {
            PartnerDrawer(
                session = session,
                ctx = ctx,
                lane = lane,
                tabs = tabs,
                current = tab,
                branchId = branchId,
                onTab = { picked -> tab = picked; closeDrawer() },
                onBranch = { picked ->
                    branchId = picked
                    session.branchId = picked
                    closeDrawer()
                },
                onCustomers = { showCustomers = true; closeDrawer() },
                onPackages = if (session.can("pricing")) ({ showPackages = true; closeDrawer() }) else null,
                onStaff = if (!session.isDesk) ({ showStaff = true; closeDrawer() }) else null,
                onPayouts = if (session.can("reports")) ({ showPayouts = true; closeDrawer() }) else null,
                onReports = if (session.can("reports")) ({ showReports = true; closeDrawer() }) else null,
                onAcademy = if (session.can("pricing")) ({ showAcademy = true; closeDrawer() }) else null,
                onNotifications = { showNotifications = true; closeDrawer() },
                onSupport = { showSupport = true; closeDrawer() },
                onSignOut = { session.clear(); onSignedOut() },
            )
        },
    ) {
    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = Color.White,
                    scrolledContainerColor = Color.White,
                ),
                navigationIcon = {
                    IconButton(onClick = { drawerScope.launch { drawerState.open() } }) {
                        Icon(
                            Icons.Filled.Menu,
                            contentDescription = "Menu",
                            tint = AuthInk,
                            modifier = Modifier.size(22.dp),
                        )
                    }
                },
                title = {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        // The wordmark opens the drawer too: the brand is the one
                        // thing always in the same place, so it is the most
                        // findable target on the screen.
                        Image(
                            painter = painterResource(R.drawable.haraan_logo),
                            contentDescription = "Haraan",
                            contentScale = ContentScale.Fit,
                            modifier = Modifier
                                .height(22.dp)
                                .clickable { drawerScope.launch { drawerState.open() } },
                        )
                        ctx?.takeIf { it.isMultiBranch }?.let { known ->
                            Spacer(Modifier.width(8.dp))
                            BranchSwitcher(known, branchId) { picked ->
                                branchId = picked
                                session.branchId = picked
                            }
                        }
                    }
                },
                actions = {
                    BellIcon(unseenBookings) { tab = Tab.Sales; unseenBookings = 0 }
                    Spacer(Modifier.width(4.dp))
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
                Tab.Home -> HomeTab(
                    api, token, session.name ?: "Partner", lane, branchId,
                    onPayouts = if (session.can("reports")) ({ showPayouts = true }) else null,
                    onCustomers = { showCustomers = true },
                    onPackages = if (session.can("pricing")) ({ showPackages = true }) else null,
                    onAcademy = if (session.can("pricing")) ({ showAcademy = true }) else null,
                ) { serverType ->
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
                Tab.Sales -> SalesTab(api, token, branchId)
                Tab.Scan -> ScanTab(api, token)
            }
        }
    }
    }
}

/**
 * The slide-out index of the whole console.
 *
 * Three bands, in the order a partner actually thinks: where am I going
 * (the tabs), what do I want to run (the tools that used to hide as topbar
 * glyphs), and which outlet am I looking at. Sign out sits alone at the
 * bottom so nobody hits it reaching for anything else.
 *
 * Every tool is passed as a nullable lambda: a null means this account can't
 * do that, and the row simply isn't drawn — the drawer never shows a partner
 * a door that would only tell them no.
 */
@Composable
private fun PartnerDrawer(
    session: Session,
    ctx: PartnerContext?,
    lane: Lane,
    tabs: List<Tab>,
    current: Tab,
    branchId: Long?,
    onTab: (Tab) -> Unit,
    onBranch: (Long?) -> Unit,
    onCustomers: () -> Unit,
    onPackages: (() -> Unit)?,
    onStaff: (() -> Unit)?,
    onPayouts: (() -> Unit)?,
    onReports: (() -> Unit)?,
    onAcademy: (() -> Unit)?,
    onNotifications: () -> Unit,
    onSupport: () -> Unit,
    onSignOut: () -> Unit,
) {
    ModalDrawerSheet(
        drawerContainerColor = Color.White,
        drawerShape = RoundedCornerShape(topEnd = 22.dp, bottomEnd = 22.dp),
        // The sheet's own status-bar inset is dropped so the navy identity block
        // can run under the clock; the header re-applies it to its text.
        windowInsets = WindowInsets(0, 0, 0, 0),
        modifier = Modifier.widthIn(max = 320.dp),
    ) {
        Column(Modifier.fillMaxHeight()) {
            DrawerHeader(session, ctx)

            Column(
                Modifier
                    .weight(1f)
                    .verticalScroll(rememberScrollState())
                    // Bottom room so the last row can scroll clear of the pinned
                    // Sign out strip instead of sitting under it.
                    .padding(top = 6.dp, bottom = 18.dp),
            ) {
                DrawerSection("Go to")
                tabs.forEach { t ->
                    DrawerRow(
                        icon = iconFor(t),
                        label = labelFor(t, lane),
                        selected = t == current,
                        onClick = { onTab(t) },
                    )
                }

                val tools = listOfNotNull(
                    Triple(Icons.Filled.People, "Customers", onCustomers),
                    onPackages?.let { Triple(Icons.Filled.ConfirmationNumber, "Packages", it) },
                    onAcademy?.let { Triple(Icons.Filled.School, "Academy", it) },
                    onStaff?.let { Triple(Icons.Filled.Badge, "Staff", it) },
                    onPayouts?.let { Triple(Icons.Filled.Payments, "Payouts", it) },
                    onReports?.let { Triple(Icons.Filled.Description, "Reports", it) },
                )
                if (tools.isNotEmpty()) {
                    Spacer(Modifier.height(2.dp))
                    DrawerSection("Manage")
                    tools.forEach { (icon, label, action) ->
                        DrawerRow(icon = icon, label = label, selected = false, onClick = action)
                    }
                }

                Spacer(Modifier.height(2.dp))
                DrawerSection("Account")
                DrawerRow(icon = Icons.Filled.Notifications, label = "Notifications", selected = false, onClick = onNotifications)
                DrawerRow(icon = Icons.AutoMirrored.Filled.HelpOutline, label = "Support", selected = false, onClick = onSupport)

                // Only a partner who actually runs several outlets gets a branch
                // band; a single-venue account sees the drawer it always had.
                ctx?.takeIf { it.isMultiBranch }?.let { known ->
                    Spacer(Modifier.height(6.dp))
                    DrawerSection("Branch")
                    DrawerRow(
                        icon = Icons.Filled.Place,
                        label = "All branches",
                        selected = branchId == null,
                        trailing = { if (branchId == null) DrawerTick() },
                        onClick = { onBranch(null) },
                    )
                    known.branches.forEach { b ->
                        DrawerRow(
                            icon = Icons.Filled.Place,
                            label = b.branch,
                            selected = branchId == b.id,
                            trailing = { if (branchId == b.id) DrawerTick() },
                            onClick = { onBranch(b.id) },
                        )
                    }
                }
            }

            HorizontalDivider(color = Hairline)
            DrawerRow(
                icon = Icons.AutoMirrored.Filled.Logout,
                label = "Sign out",
                selected = false,
                tint = RED,
                onClick = onSignOut,
            )
            Spacer(Modifier.height(6.dp).navigationBarsPadding())
        }
    }
}

/** Navy identity block: who is signed in, and at what altitude. */
@Composable
private fun DrawerHeader(session: Session, ctx: PartnerContext?) {
    val name = ctx?.businessName ?: session.name ?: "Partner"
    // Same aurora as the login band and the revenue hero, so the drawer reads as
    // part of the app rather than a stock Material panel.
    LayoutBox(
        Modifier
            .fillMaxWidth()
            .background(Brush.linearGradient(listOf(AuthInkTop, AuthInkMid, AuthInkBot))),
    ) {
        LayoutBox(
            Modifier.matchParentSize().background(
                Brush.radialGradient(
                    listOf(Color(0x553B82F6), Color(0x00000000)),
                    center = Offset(90f, 40f), radius = 460f,
                )
            )
        )
        Column(
            Modifier.statusBarsPadding().padding(start = 20.dp, end = 20.dp, top = 14.dp, bottom = 15.dp),
        ) {
            Image(
                painter = painterResource(R.drawable.haraan_logo_white),
                contentDescription = "Haraan",
                contentScale = ContentScale.Fit,
                colorFilter = ColorFilter.tint(Color.White),
                modifier = Modifier.height(18.dp),
            )
            Spacer(Modifier.height(16.dp))
            // Monogram beside the name (not centred on the whole block, which left
            // it hanging below the text), chips on their own line underneath.
            Row(verticalAlignment = Alignment.CenterVertically) {
                LayoutBox(
                    Modifier.size(38.dp).clip(RoundedCornerShape(12.dp))
                        .background(Color(0x2E7DA9FF))
                        .border(1.dp, Color(0x3DFFFFFF), RoundedCornerShape(12.dp)),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        name.trim().take(1).uppercase(),
                        fontSize = 16.sp, fontWeight = FontWeight.ExtraBold, color = Color.White,
                    )
                }
                Spacer(Modifier.width(11.dp))
                Text(
                    name,
                    fontSize = 16.sp,
                    fontWeight = FontWeight.ExtraBold,
                    color = Color.White,
                    letterSpacing = (-0.2).sp,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f),
                )
            }
            Spacer(Modifier.height(11.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                DrawerChip(altitudeLabel(session, ctx))
                ctx?.typeLabel?.let {
                    Spacer(Modifier.width(6.dp))
                    DrawerChip(it)
                }
            }
        }
    }
}

/** Reads the partner's altitude for the header chip. Owners see "Owner". */
private fun altitudeLabel(session: Session, ctx: PartnerContext?): String = when {
    ctx?.altitude == "desk" || session.isDesk -> "Desk"
    ctx?.altitude == "manager" -> "Manager"
    else -> "Owner"
}

@Composable
private fun DrawerChip(text: String) {
    Text(
        text,
        fontSize = 11.5.sp,
        fontWeight = FontWeight.SemiBold,
        color = Color(0xFFBFD0FF),
        modifier = Modifier
            .clip(RoundedCornerShape(999.dp))
            .background(Color(0x2E4C7DFF))
            .padding(horizontal = 10.dp, vertical = 4.dp),
    )
}

@Composable
private fun DrawerSection(label: String) {
    // Label + hairline: the rule is what makes these read as groups rather than
    // stray small text floating above a flat list.
    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier.fillMaxWidth().padding(start = 21.dp, end = 20.dp, top = 8.dp, bottom = 4.dp),
    ) {
        Text(
            label.uppercase(),
            fontSize = 10.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 1.2.sp,
            color = Color(0xFF94A3B8),
        )
        Spacer(Modifier.width(10.dp))
        LayoutBox(Modifier.weight(1f).height(1.dp).background(Hairline))
    }
}

@Composable
private fun DrawerTick() {
    Icon(Icons.Filled.Check, contentDescription = null, tint = AuthAccentDeep, modifier = Modifier.size(17.dp))
}

/**
 * One drawer line. Selection is carried by a tinted pill rather than a side
 * rail so it reads the same as the branch chip in the topbar.
 */
@Composable
private fun DrawerRow(
    icon: ImageVector,
    label: String,
    selected: Boolean,
    onClick: () -> Unit,
    tint: Color? = null,
    trailing: @Composable (() -> Unit)? = null,
) {
    val fg = tint ?: if (selected) AuthAccentDeep else AuthInk
    val view = LocalView.current
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val scale by animateFloatAsState(if (pressed) 0.975f else 1f, label = "drawer-row")

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 10.dp, vertical = 1.dp)
            .graphicsLayer { scaleX = scale; scaleY = scale }
            .clip(RoundedCornerShape(13.dp))
            .background(if (selected) Color(0x142F6BFF) else Color.Transparent)
            .clickable(interactionSource = interaction, indication = null) {
                view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
                onClick()
            }
            .padding(horizontal = 9.dp, vertical = 5.dp),
    ) {
        // A tonal container instead of a bare grey glyph — the same device the
        // dashboard entry cards use, so the drawer belongs to the same app.
        LayoutBox(
            Modifier.size(32.dp).clip(RoundedCornerShape(10.dp))
                .background(
                    when {
                        tint != null -> tint.copy(alpha = 0.10f)
                        selected -> Color(0x1F2F6BFF)
                        else -> Color(0x0A0F172A)
                    }
                ),
            contentAlignment = Alignment.Center,
        ) {
            Icon(
                icon,
                contentDescription = null,
                tint = if (selected || tint != null) fg else Color(0xFF64748B),
                modifier = Modifier.size(17.dp),
            )
        }
        Spacer(Modifier.width(12.dp))
        Text(
            label,
            fontSize = 14.5.sp,
            fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
            color = fg,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f),
        )
        trailing?.invoke()
    }
}

/**
 * The branch switcher — a chip in the topbar that opens the outlet list.
 *
 * Lives beside the wordmark rather than in the actions row because it answers
 * "where am I", which is orientation, not an action. Switching is instant and
 * global: no partner should walk a settings tree to change what they're looking
 * at.
 *
 * Rendered only when there is more than one branch, so today's single-venue
 * partners see no change at all.
 */
@Composable
private fun BranchSwitcher(ctx: PartnerContext, selected: Long?, onSelect: (Long?) -> Unit) {
    var open by remember { mutableStateOf(false) }

    // LayoutBox, not Box: see CenteredPane — a bare `Box` here binds to a
    // fillMaxSize() helper and takes the whole top bar with it.
    LayoutBox {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier
                .clip(RoundedCornerShape(999.dp))
                .background(Color(0x142F6BFF))
                .clickable { open = true }
                .padding(start = 9.dp, end = 6.dp, top = 5.dp, bottom = 5.dp),
        ) {
            Icon(Icons.Filled.Place, null, tint = AuthAccentDeep, modifier = Modifier.size(13.dp))
            Spacer(Modifier.width(4.dp))
            Text(
                ctx.branchName(selected),
                fontSize = 12.5.sp,
                fontWeight = FontWeight.SemiBold,
                color = AuthAccentDeep,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                modifier = Modifier.widthIn(max = 104.dp),
            )
            Icon(Icons.Filled.ExpandMore, null, tint = AuthAccentDeep, modifier = Modifier.size(15.dp))
        }

        DropdownMenu(expanded = open, onDismissRequest = { open = false }) {
            DropdownMenuItem(
                text = { BranchMenuRow("All branches", "${ctx.branches.size} outlets", selected == null) },
                onClick = { onSelect(null); open = false },
            )
            ctx.branches.forEach { b ->
                val sub = listOfNotNull(
                    b.code ?: b.city,
                    if (b.isActive) null else "inactive",
                ).joinToString(" · ")
                DropdownMenuItem(
                    text = { BranchMenuRow(b.branch, sub, selected == b.id) },
                    onClick = { onSelect(b.id); open = false },
                )
            }
        }
    }
}

@Composable
private fun BranchMenuRow(name: String, sub: String, isOn: Boolean) {
    Column {
        Text(
            name,
            fontSize = 13.5.sp,
            fontWeight = if (isOn) FontWeight.Bold else FontWeight.SemiBold,
            color = if (isOn) AuthAccentDeep else AuthInk,
            maxLines = 1,
        )
        if (sub.isNotBlank()) Text(sub, fontSize = 11.sp, color = AuthMuted, maxLines = 1)
    }
}

private fun iconFor(tab: Tab) = when (tab) {
    Tab.Home -> Icons.Filled.Home
    Tab.Events -> Icons.Filled.CalendarMonth
    Tab.Venues -> Icons.Filled.Place
    Tab.Sales -> Icons.Filled.Payments
    Tab.Scan -> Icons.Filled.QrCodeScanner
}

/**
 * Tab labels follow the lane's vocabulary.
 *
 * A venue takes "Bookings" where a host makes "Sales"; a café runs "Outlets"
 * where a turf lists "Venues". The tab SET is already right for a café — it
 * keeps Events, which a sports venue doesn't get — so only the words change.
 */
private fun labelFor(tab: Tab, lane: Lane): String = when {
    tab == Tab.Sales && (lane == Lane.VENUE || lane == Lane.CAFE) -> "Bookings"
    tab == Tab.Venues && lane == Lane.CAFE -> "Outlets"
    else -> tab.label
}

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
private fun HomeTab(api: PartnerApi, token: String, name: String, lane: Lane, venueId: Long? = null, onPayouts: (() -> Unit)? = null, onCustomers: (() -> Unit)? = null, onPackages: (() -> Unit)? = null, onAcademy: (() -> Unit)? = null, onLane: (String?) -> Unit) {
    // The branch is part of the key, so picking one reloads rather than leaving
    // the previous outlet's numbers under a new title.
    RefreshableContent(token to venueId, load = { api.overview(token, venueId) }) { o ->
        // Report the server's authoritative type up so the tab bar matches.
        LaunchedEffect(o.type) { onLane(o.type) }
        // If the server knows a more specific lane than the cached one, honour it.
        val effective = if (o.type != null) laneOf(o.type) else lane
        val subtitle = when (effective) {
            Lane.EVENT -> "Event host"
            Lane.VENUE -> "Sports venue"
            Lane.CAFE -> "Café venue"
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
            // A café counts outlets and the nights it hosts — never "turfs".
            // Events are here and absent from the sports tiles above, which is
            // the difference between the two lanes in one glance.
            Lane.CAFE -> listOf(
                Tile(Icons.Filled.Place, "Outlets", o.venuesTotal.toString(), "cafés & spaces"),
                Tile(Icons.Filled.ConfirmationNumber, "Events", o.eventsTotal.toString(), "${o.eventsUpcoming} upcoming"),
                Tile(Icons.Filled.Today, "Today", o.bookingsToday.toString(), "bookings today"),
                Tile(Icons.Filled.ConfirmationNumber, "Bookings", o.bookingsTotal.toString(), "all-time"),
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
            if (onCustomers != null) {
                item { CustomersEntryCard(onCustomers) }
            }
            if (onPackages != null) {
                item { PackagesEntryCard(onPackages) }
            }
            if (onAcademy != null) {
                item { AcademyEntryCard(onAcademy) }
            }
            if (onPayouts != null) {
                item { PayoutsEntryCard(onPayouts) }
            }
            if (effective != Lane.EVENT) {
                item { BookingsMixCard(o) }
            }
        }
    }
}

/** Dashboard shortcut into Customers. */
@Composable
private fun CustomersEntryCard(onClick: () -> Unit) {
    PressableSurface(onClick = onClick) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            LayoutBox(
                Modifier.size(42.dp).clip(RoundedCornerShape(13.dp))
                    .background(Brush.linearGradient(listOf(Color(0xFFEAF1FF), Color(0xFFDCE8FF)))),
                contentAlignment = Alignment.Center,
            ) { Icon(Icons.Filled.People, contentDescription = null, tint = AuthAccent, modifier = Modifier.size(21.dp)) }
            Spacer(Modifier.width(13.dp))
            Column(Modifier.weight(1f)) {
                Text("Customers", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk)
                Spacer(Modifier.height(1.dp))
                Text("Who plays here, and how often", fontSize = 12.sp, color = AuthMuted)
            }
            Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = Color(0xFFB6C0D0), modifier = Modifier.size(20.dp))
        }
    }
}

/** Dashboard shortcut into Academy. */
@Composable
private fun AcademyEntryCard(onClick: () -> Unit) {
    PressableSurface(onClick = onClick) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            LayoutBox(
                Modifier.size(42.dp).clip(RoundedCornerShape(13.dp))
                    .background(Brush.linearGradient(listOf(Color(0xFFE6F7EE), Color(0xFFD3F0E0)))),
                contentAlignment = Alignment.Center,
            ) { Icon(Icons.Filled.People, contentDescription = null, tint = Color(0xFF15803D), modifier = Modifier.size(21.dp)) }
            Spacer(Modifier.width(13.dp))
            Column(Modifier.weight(1f)) {
                Text("Academy", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk)
                Spacer(Modifier.height(1.dp))
                Text("Coaching batches & attendance", fontSize = 12.sp, color = AuthMuted)
            }
            Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = Color(0xFFB6C0D0), modifier = Modifier.size(20.dp))
        }
    }
}

/** Dashboard shortcut into Packages. */
@Composable
private fun PackagesEntryCard(onClick: () -> Unit) {
    PressableSurface(onClick = onClick) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            LayoutBox(
                Modifier.size(42.dp).clip(RoundedCornerShape(13.dp))
                    .background(Brush.linearGradient(listOf(Color(0xFFF3ECFF), Color(0xFFE6DBFF)))),
                contentAlignment = Alignment.Center,
            ) { Icon(Icons.Filled.ConfirmationNumber, contentDescription = null, tint = Color(0xFF6D28D9), modifier = Modifier.size(21.dp)) }
            Spacer(Modifier.width(13.dp))
            Column(Modifier.weight(1f)) {
                Text("Packages", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk)
                Spacer(Modifier.height(1.dp))
                Text("Memberships & prepaid sessions", fontSize = 12.sp, color = AuthMuted)
            }
            Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = Color(0xFFB6C0D0), modifier = Modifier.size(20.dp))
        }
    }
}

/** Dashboard shortcut into Payouts — money owed deserves a row, not just a header icon. */
@Composable
private fun PayoutsEntryCard(onClick: () -> Unit) {
    PressableSurface(onClick = onClick) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            LayoutBox(
                Modifier.size(42.dp).clip(RoundedCornerShape(13.dp))
                    .background(Brush.linearGradient(listOf(Color(0xFFE7F7EE), Color(0xFFD3F0E0)))),
                contentAlignment = Alignment.Center,
            ) { Icon(Icons.Filled.Payments, contentDescription = null, tint = Color(0xFF0F766E), modifier = Modifier.size(21.dp)) }
            Spacer(Modifier.width(13.dp))
            Column(Modifier.weight(1f)) {
                Text("Payouts", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk)
                Spacer(Modifier.height(1.dp))
                Text("Balance, settlement account & history", fontSize = 12.sp, color = AuthMuted)
            }
            Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = Color(0xFFB6C0D0), modifier = Modifier.size(20.dp))
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
    PressableSurface(onClick = onClick, radius = 20.dp) {
        Column {
            // Photo band. No venue has an uploaded photo yet, so the fallback has to
            // carry the design rather than look like a broken image: the brand navy
            // with a soft glow and a large watermark glyph, which reads as a
            // deliberate cover until a real photo is added in /control.
            LayoutBox(Modifier.fillMaxWidth().height(132.dp)) {
                if (!v.image.isNullOrBlank()) {
                    AsyncImage(
                        model = v.image,
                        contentDescription = v.name,
                        contentScale = ContentScale.Crop,
                        modifier = Modifier.matchParentSize(),
                    )
                } else {
                    LayoutBox(
                        Modifier.matchParentSize()
                            .background(Brush.linearGradient(listOf(AuthInkTop, AuthInkMid, AuthInkBot))),
                    ) {
                        LayoutBox(
                            Modifier.matchParentSize().background(
                                Brush.radialGradient(
                                    listOf(Color(0x553B82F6), Color(0x00000000)),
                                    center = Offset(140f, 30f), radius = 420f,
                                )
                            )
                        )
                        // Bleeds off the right edge at low alpha so it reads as
                        // texture behind the title, not an icon sitting in a box.
                        Icon(
                            Icons.Filled.Place,
                            contentDescription = null,
                            tint = Color(0x14FFFFFF),
                            modifier = Modifier.align(Alignment.CenterEnd)
                                .offset(x = 26.dp, y = (-6).dp).size(150.dp),
                        )
                    }
                }
                // Scrim so the name stays readable over any photo, however bright.
                LayoutBox(
                    Modifier.matchParentSize().background(
                        Brush.verticalGradient(listOf(Color(0x00000000), Color(0x40000000), Color(0xB3000000)))
                    )
                )
                // Revenue rides top-right — the number the owner scans for.
                Text(
                    "₹" + formatInr(v.revenue),
                    fontSize = 13.sp, fontWeight = FontWeight.ExtraBold, color = Color.White,
                    modifier = Modifier.align(Alignment.TopEnd).padding(12.dp)
                        .clip(RoundedCornerShape(999.dp)).background(Color(0x66000000))
                        .padding(horizontal = 10.dp, vertical = 5.dp),
                )
                Column(Modifier.align(Alignment.BottomStart).padding(14.dp)) {
                    Text(
                        v.name,
                        fontSize = 16.5.sp, fontWeight = FontWeight.ExtraBold, color = Color.White,
                        letterSpacing = (-0.3).sp, maxLines = 1, overflow = TextOverflow.Ellipsis,
                    )
                    v.location?.takeIf { it.isNotBlank() }?.let {
                        Spacer(Modifier.height(2.dp))
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Filled.Place, contentDescription = null, tint = Color(0xCCFFFFFF), modifier = Modifier.size(12.dp))
                            Spacer(Modifier.width(4.dp))
                            Text(it, fontSize = 12.sp, color = Color(0xCCFFFFFF), maxLines = 1)
                        }
                    }
                }
            }
            // Footer strip: what the owner acts on.
            Row(
                Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x142F6BFF))
                        .padding(horizontal = 10.dp, vertical = 5.dp),
                ) {
                    Icon(Icons.Filled.ConfirmationNumber, contentDescription = null, tint = AuthAccentDeep, modifier = Modifier.size(12.dp))
                    Spacer(Modifier.width(6.dp))
                    Text("${v.bookings} bookings", fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = AuthAccentDeep)
                }
                if (v.sports.isNotEmpty()) {
                    Spacer(Modifier.width(7.dp))
                    Text(
                        v.sports.first() + if (v.sports.size > 1) "  +${v.sports.size - 1}" else "",
                        fontSize = 11.5.sp, color = AuthMuted, maxLines = 1,
                    )
                }
                Spacer(Modifier.weight(1f))
                Text("Open desk", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = AuthAccentDeep)
                Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = AuthAccentDeep, modifier = Modifier.size(17.dp))
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
    /** Decides whether this desk books courts or tables. */
    lane: Lane = Lane.VENUE,
) {
    var dayMillis by remember { mutableStateOf(todayMillis()) }
    var reload by remember { mutableStateOf(0) }
    var addForSlot by remember { mutableStateOf<DaySlot?>(null) }
    var addForCell by remember { mutableStateOf<CellTarget?>(null) }
    var cancelTarget by remember { mutableStateOf<DayBooking?>(null) }
    var showPricing by remember { mutableStateOf(false) }
    var booking by remember { mutableStateOf(false) }
    var pendingPay by remember { mutableStateOf<PendingPay?>(null) }
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
                                lane = lane,
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
            api = api,
            token = token,
            venueId = venueId,
            busy = booking,
            onDismiss = { addForSlot = null },
            onConfirm = { name, phone, method, passId ->
                booking = true
                scope.launch {
                    runCatching { api.createWalkIn(token, venueId, slot.slotId, date, name, phone, null, method, passId) }
                        .onSuccess { r ->
                            // Only a link needs watching; cash/UPI/card are already settled.
                            if (r.paymentLink != null) {
                                pendingPay = PendingPay(r.bookingId, r.paymentLink, r.paymentLinkId, r.amount)
                            }
                        }
                    booking = false; addForSlot = null; reload++
                }
            },
        )
    }

    addForCell?.let { t ->
        WalkInDialog(
            slotLabel = "${t.slot.time ?: t.slot.label} · ${t.courtName}",
            amount = t.price,
            api = api,
            token = token,
            venueId = venueId,
            busy = booking,
            onDismiss = { addForCell = null },
            onConfirm = { name, phone, method, passId ->
                booking = true
                scope.launch {
                    runCatching { api.createWalkIn(token, venueId, t.slot.slotId, date, name, phone, t.courtId, method, passId) }
                        .onSuccess { r ->
                            // Only a link needs watching; cash/UPI/card are already settled.
                            if (r.paymentLink != null) {
                                pendingPay = PendingPay(r.bookingId, r.paymentLink, r.paymentLinkId, r.amount)
                            }
                        }
                    booking = false; addForCell = null; reload++
                }
            },
        )
    }

    pendingPay?.let { p ->
        PaymentWaitDialog(p, api, token) { paid -> pendingPay = null; if (paid) reload++ }
    }

    cancelTarget?.let { b ->
        AlertDialog(
            onDismissRequest = { cancelTarget = null },
            title = { Text("Cancel booking?") },
            text = { Text("${b.customer} — this frees the ${resourceNoun(lane).lowercase()} for that time.") },
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
    lane: Lane = Lane.VENUE,
) {
    val timeW = 58.dp
    val cellW = 96.dp
    val courtName = { id: Long -> grid.courts.firstOrNull { it.id == id }?.name ?: resourceNoun(lane) }
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
                if (cell.price > 0) {
                    // Peak hours read amber so the desk can see the higher rate at a glance.
                    Text(
                        "₹${cell.price.toInt()}" + if (cell.isPeak) " ▲" else "",
                        fontSize = 10.sp,
                        fontWeight = if (cell.isPeak) FontWeight.Bold else FontWeight.Normal,
                        color = if (cell.isPeak) Color(0xFFB45309) else AuthMuted,
                    )
                }
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
    api: PartnerApi,
    token: String,
    /** The branch this desk is standing in — a pass locked elsewhere isn't offered. */
    venueId: Long,
    busy: Boolean = false,
    onDismiss: () -> Unit,
    onConfirm: (String, String, PayMethod, Long?) -> Unit,
) {
    var name by remember { mutableStateOf("") }
    var phone by remember { mutableStateOf("") }
    var method by remember { mutableStateOf(PayMethod.CASH) }
    var passes by remember { mutableStateOf<List<PackageHolder>>(emptyList()) }
    val view = LocalView.current

    // A full number is enough to know whether this customer already holds a pass —
    // the desk shouldn't have to remember, or charge someone who already paid.
    // Scoped to this branch: a pass locked to another outlet must never be offered
    // here, or the desk spends a session the offer never covered.
    LaunchedEffect(phone, venueId) {
        passes = if (phone.length == 10) {
            runCatching { api.packageHolder(token, phone, venueId) }.getOrDefault(emptyList())
        } else {
            emptyList()
        }
        if (passes.isEmpty() && method == PayMethod.PACKAGE) method = PayMethod.CASH
    }

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
            if (phone.length == 10) {
                Spacer(Modifier.height(8.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    LayoutBox(Modifier.size(7.dp).clip(RoundedCornerShape(99.dp)).background(Color(0xFF25D366)))
                    Spacer(Modifier.width(8.dp))
                    Text(
                        "Booking confirmation sent on WhatsApp",
                        fontSize = 11.5.sp, fontWeight = FontWeight.Medium, color = AuthMuted,
                    )
                }
            }

            Spacer(Modifier.height(18.dp))
            Text("PAYMENT", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = AuthMuted, letterSpacing = 1.4.sp)
            Spacer(Modifier.height(10.dp))
            // Two rows of two so every label stays readable at any width.
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                PayChip(PayMethod.CASH, method, Modifier.weight(1f)) { method = it; view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP) }
                PayChip(PayMethod.UPI, method, Modifier.weight(1f)) { method = it; view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP) }
                PayChip(PayMethod.CARD, method, Modifier.weight(1f)) { method = it; view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP) }
            }
            Spacer(Modifier.height(8.dp))
            PayChip(PayMethod.LINK, method, Modifier.fillMaxWidth()) { method = it; view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP) }
            passes.firstOrNull()?.let { pass ->
                Spacer(Modifier.height(8.dp))
                PayChip(PayMethod.PACKAGE, method, Modifier.fillMaxWidth()) { method = it; view.performHapticFeedback(HapticFeedbackConstants.CONFIRM) }
                Spacer(Modifier.height(6.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    LayoutBox(Modifier.size(7.dp).clip(RoundedCornerShape(99.dp)).background(GREEN))
                    Spacer(Modifier.width(8.dp))
                    Text(
                        "${pass.packageName} · ${pass.remaining} of ${pass.total} left",
                        fontSize = 11.5.sp, fontWeight = FontWeight.SemiBold, color = GREEN,
                    )
                }
            }

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
                onConfirm(name.trim(), phone.trim(), method, passes.firstOrNull()?.id)
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

/** What the desk is waiting on after sending a payment link. */
private data class PendingPay(
    val bookingId: Long,
    val link: String,
    val linkId: String?,
    val amount: Double,
)

/** How long the desk watches before the sheet stops counting down (link stays valid). */
private const val PAY_WAIT_SECONDS = 300

/**
 * The "waiting for payment" moment: Razorpay has texted the link, and the desk watches
 * it go green while the customer is still at the counter.
 *
 * Polls the server (which asks Razorpay directly, so this works with no webhook), and
 * flips to a confirmed state the instant the money lands.
 */
@Composable
private fun PaymentWaitDialog(pending: PendingPay, api: PartnerApi, token: String, onDismiss: (paid: Boolean) -> Unit) {
    val context = LocalContext.current
    val view = LocalView.current
    var paid by remember { mutableStateOf(false) }
    var checking by remember { mutableStateOf(false) }
    var left by remember { mutableStateOf(PAY_WAIT_SECONDS) }

    // Poll while we're waiting. Stops the moment it's paid or the window closes; the
    // link itself stays valid either way, so a timeout is not a failure.
    LaunchedEffect(pending.bookingId) {
        while (!paid && left > 0) {
            kotlinx.coroutines.delay(4_000)
            left = (left - 4).coerceAtLeast(0)
            if (pending.linkId == null) continue
            checking = true
            runCatching { api.paymentStatus(token, pending.bookingId, pending.linkId) }
                .onSuccess {
                    if (it.paid) {
                        paid = true
                        view.performHapticFeedback(HapticFeedbackConstants.CONFIRM)
                    }
                }
            checking = false
        }
    }

    Dialog(onDismissRequest = { onDismiss(paid) }) {
        Column(
            Modifier.fillMaxWidth().clip(RoundedCornerShape(24.dp)).background(Color.White).padding(22.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            if (paid) {
                // --- Money is in ------------------------------------------------
                LayoutBox(
                    Modifier.size(60.dp).clip(RoundedCornerShape(99.dp)).background(Color(0x1A16A34A)),
                    contentAlignment = Alignment.Center,
                ) { Text("✓", fontSize = 30.sp, fontWeight = FontWeight.Bold, color = GREEN) }
                Spacer(Modifier.height(14.dp))
                Text("Payment received", fontSize = 19.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                Spacer(Modifier.height(6.dp))
                Text(
                    "₹" + formatInr(pending.amount) + " paid. The booking is confirmed and the ticket has gone to the customer.",
                    fontSize = 13.sp, color = AuthMuted, textAlign = TextAlign.Center, lineHeight = 18.sp,
                )
                Spacer(Modifier.height(20.dp))
                GradientCta(text = "Done", enabled = true, loading = false) { onDismiss(true) }
            } else {
                // --- Waiting ----------------------------------------------------
                AuthIconBadge(Icons.Filled.Payments)
                Spacer(Modifier.height(14.dp))
                Text("Waiting for payment", fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                Spacer(Modifier.height(6.dp))
                Text(
                    "Razorpay has texted the link to the customer. This updates the moment they pay.",
                    fontSize = 13.sp, color = AuthMuted, textAlign = TextAlign.Center, lineHeight = 18.sp,
                )

                Spacer(Modifier.height(16.dp))
                Row(
                    Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp))
                        .background(Color(0xFFF6F8FC)).border(1.dp, CardBorder, RoundedCornerShape(14.dp))
                        .padding(horizontal = 14.dp, vertical = 12.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text("Amount", fontSize = 13.sp, color = AuthMuted)
                    Text("₹" + formatInr(pending.amount), fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                }

                Spacer(Modifier.height(14.dp))
                // Countdown + live pulse, so it never looks frozen.
                Row(verticalAlignment = Alignment.CenterVertically) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(15.dp),
                        color = if (checking) AuthAccent else Color(0xFFCBD5E1),
                        strokeWidth = 2.dp,
                    )
                    Spacer(Modifier.width(9.dp))
                    Text(
                        if (left > 0) "Checking… ${left / 60}:${(left % 60).toString().padStart(2, '0')} left"
                        else "Still unpaid — the link stays valid",
                        fontSize = 12.5.sp, color = AuthMuted, fontWeight = FontWeight.Medium,
                    )
                }

                Spacer(Modifier.height(18.dp))
                GradientCta(text = "Share link again", enabled = true, loading = false) {
                    val send = Intent(Intent.ACTION_SEND).apply {
                        type = "text/plain"
                        putExtra(Intent.EXTRA_TEXT, "Complete your booking payment: ${pending.link}")
                    }
                    context.startActivity(Intent.createChooser(send, "Share payment link"))
                }
                Spacer(Modifier.height(4.dp))
                TextButton(onClick = { onDismiss(false) }, modifier = Modifier.fillMaxWidth()) {
                    Text("Collect later", color = AuthMuted, fontWeight = FontWeight.SemiBold)
                }
            }
        }
    }
}

// ---- Venue pricing / slot editor ---------------------------------------

private val WEEK_DAYS = listOf("Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun")

/**
 * Per-court base rate + peak pricing — the "charge more at busy hours" lever
 * Playo has and we didn't expose. The server already charges the peak rate
 * ({@link VenueCourt::rateFor}); this is the missing way to configure it.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun CourtPricingScreen(api: PartnerApi, token: String, venueId: Long, venueName: String, onBack: () -> Unit) {
    var reload by remember { mutableStateOf(0) }
    var editing by remember { mutableStateOf<CourtPricing?>(null) }
    val scope = rememberCoroutineScope()
    val state by produceState<UiState<List<CourtPricing>>>(UiState.Loading, reload) {
        value = runCatchingUi { api.venueCourts(token, venueId) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White, scrolledContainerColor = Color.White),
                title = { Text("Courts & peak pricing", maxLines = 1, fontWeight = FontWeight.Bold, color = AuthInk, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = AuthInk) }
                },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().background(AuthPageBg).padding(padding)) {
            Text(venueName, Modifier.padding(horizontal = 16.dp, vertical = 10.dp), fontSize = 13.sp, color = AuthMuted)
            Loaded(state) { courts ->
                if (courts.isEmpty()) {
                    EmptyState("No courts on this venue yet.")
                } else {
                    LazyColumn(
                        Modifier.fillMaxSize().padding(horizontal = 16.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp),
                        contentPadding = androidx.compose.foundation.layout.PaddingValues(bottom = 24.dp),
                    ) {
                        items(courts) { c -> CourtPricingCard(c) { editing = c } }
                    }
                }
            }
        }
    }

    editing?.let { court ->
        PeakPricingDialog(
            court = court,
            onDismiss = { editing = null },
            onSave = { price, peak, days, start, end ->
                editing = null
                scope.launch {
                    runCatching { api.saveCourtPricing(token, venueId, court.id, price, peak, days, start, end) }
                    reload++
                }
            },
        )
    }
}

@Composable
private fun CourtPricingCard(c: CourtPricing, onClick: () -> Unit) {
    PressableSurface(onClick = onClick) {
        Column(Modifier.padding(16.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text(c.name, fontSize = 15.5.sp, fontWeight = FontWeight.Bold, color = AuthInk)
                    if (c.sports.isNotEmpty()) {
                        Spacer(Modifier.height(1.dp))
                        Text(c.sports.joinToString(" · "), fontSize = 12.sp, color = AuthMuted)
                    }
                }
                Column(horizontalAlignment = Alignment.End) {
                    Text("₹${c.price}", fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                    Text("per hour", fontSize = 11.sp, color = AuthMuted)
                }
            }
            Spacer(Modifier.height(12.dp))
            if (c.peakOn) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.clip(RoundedCornerShape(999.dp))
                        .background(Color(0x1AB45309)).padding(horizontal = 10.dp, vertical = 6.dp),
                ) {
                    Icon(Icons.AutoMirrored.Filled.TrendingUp, contentDescription = null, tint = Color(0xFFB45309), modifier = Modifier.size(13.dp))
                    Spacer(Modifier.width(6.dp))
                    Text(
                        "₹${c.peakPrice} peak · " + peakWhenLabel(c),
                        fontSize = 11.5.sp, fontWeight = FontWeight.SemiBold, color = Color(0xFF92400E),
                    )
                }
            } else {
                Text("No peak pricing — tap to add", fontSize = 12.sp, color = AuthMuted)
            }
        }
    }
}

/** "Sat, Sun 6:00 PM–10:00 PM" / "every day 6:00 PM–10:00 PM" / "Sat, Sun". */
private fun peakWhenLabel(c: CourtPricing): String {
    val days = if (c.peakDays.isEmpty()) "every day" else c.peakDays.joinToString(", ")
    val window = if (c.peakStart != null && c.peakEnd != null) " ${c.peakStart}–${c.peakEnd}" else ""
    return days + window
}

/** Editor for one court's base rate and its peak rule. */
@Composable
private fun PeakPricingDialog(
    court: CourtPricing,
    onDismiss: () -> Unit,
    onSave: (price: Int, peakPrice: Int?, days: List<String>, start: String?, end: String?) -> Unit,
) {
    var base by remember { mutableStateOf(court.price.toString()) }
    var peakOn by remember { mutableStateOf(court.peakOn) }
    var peak by remember { mutableStateOf(court.peakPrice?.toString() ?: "") }
    var days by remember { mutableStateOf(court.peakDays.toSet()) }
    var start by remember { mutableStateOf(court.peakStart ?: "18:00") }
    var end by remember { mutableStateOf(court.peakEnd ?: "22:00") }
    val view = LocalView.current

    val fieldColors = OutlinedTextFieldDefaults.colors(
        focusedBorderColor = AuthAccent, focusedLabelColor = AuthAccent,
        cursorColor = AuthAccent, unfocusedBorderColor = Color(0x1F0F172A),
    )
    // Peak needs a price AND a schedule, or the server ignores it — mirror that here
    // so the button can't promise something the backend will drop.
    val peakValid = !peakOn || (peak.toIntOrNull()?.let { it > 0 } == true &&
        (days.isNotEmpty() || (start.isNotBlank() && end.isNotBlank())))

    Dialog(onDismissRequest = onDismiss) {
        Column(
            Modifier.fillMaxWidth().clip(RoundedCornerShape(24.dp)).background(Color.White)
                .verticalScroll(rememberScrollState()).padding(22.dp),
        ) {
            Text(court.name, fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
            Spacer(Modifier.height(2.dp))
            Text("Hourly rate for this court", fontSize = 12.sp, color = AuthMuted)

            Spacer(Modifier.height(16.dp))
            OutlinedTextField(
                value = base,
                onValueChange = { base = it.filter { ch -> ch.isDigit() }.take(6) },
                label = { Text("Base price (₹/hour)") },
                singleLine = true,
                shape = RoundedCornerShape(12.dp),
                colors = fieldColors,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                modifier = Modifier.fillMaxWidth(),
            )

            Spacer(Modifier.height(18.dp))
            Row(
                Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp))
                    .background(Color(0xFFFFF8EE)).border(1.dp, Color(0x33B45309), RoundedCornerShape(14.dp))
                    .padding(horizontal = 14.dp, vertical = 10.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(Modifier.weight(1f)) {
                    Text("Peak pricing", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = Color(0xFF92400E))
                    Text("Charge more at busy hours", fontSize = 11.5.sp, color = Color(0xFFB45309))
                }
                Switch(
                    checked = peakOn,
                    onCheckedChange = { peakOn = it; view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP) },
                )
            }

            if (peakOn) {
                Spacer(Modifier.height(14.dp))
                OutlinedTextField(
                    value = peak,
                    onValueChange = { peak = it.filter { ch -> ch.isDigit() }.take(6) },
                    label = { Text("Peak price (₹/hour)") },
                    singleLine = true,
                    shape = RoundedCornerShape(12.dp),
                    colors = fieldColors,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    modifier = Modifier.fillMaxWidth(),
                )

                Spacer(Modifier.height(16.dp))
                Text("PEAK DAYS", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = AuthMuted, letterSpacing = 1.4.sp)
                Spacer(Modifier.height(4.dp))
                Text("None selected = every day", fontSize = 11.sp, color = AuthMuted)
                Spacer(Modifier.height(10.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(5.dp)) {
                    WEEK_DAYS.forEach { d ->
                        val on = d in days
                        LayoutBox(
                            Modifier.weight(1f).height(38.dp).clip(RoundedCornerShape(10.dp))
                                .background(if (on) AuthAccent else Color(0xFFF1F5F9))
                                .clickable {
                                    days = if (on) days - d else days + d
                                    view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
                                },
                            contentAlignment = Alignment.Center,
                        ) {
                            Text(
                                d.take(1),
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Bold,
                                color = if (on) Color.White else AuthMuted,
                            )
                        }
                    }
                }

                Spacer(Modifier.height(16.dp))
                Text("PEAK HOURS", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = AuthMuted, letterSpacing = 1.4.sp)
                Spacer(Modifier.height(10.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(
                        value = start,
                        onValueChange = { start = it.take(5) },
                        label = { Text("From") },
                        placeholder = { Text("18:00") },
                        singleLine = true,
                        shape = RoundedCornerShape(12.dp),
                        colors = fieldColors,
                        modifier = Modifier.weight(1f),
                    )
                    OutlinedTextField(
                        value = end,
                        onValueChange = { end = it.take(5) },
                        label = { Text("To") },
                        placeholder = { Text("22:00") },
                        singleLine = true,
                        shape = RoundedCornerShape(12.dp),
                        colors = fieldColors,
                        modifier = Modifier.weight(1f),
                    )
                }
                if (!peakValid) {
                    Spacer(Modifier.height(10.dp))
                    Text(
                        "Set a peak price and at least a day or an hours window.",
                        fontSize = 11.5.sp, color = RED,
                    )
                }
            }

            Spacer(Modifier.height(20.dp))
            GradientCta(text = "Save pricing", enabled = peakValid && base.isNotBlank(), loading = false) {
                view.performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
                onSave(
                    base.toIntOrNull() ?: court.price,
                    if (peakOn) peak.toIntOrNull() else null,
                    days.toList(),
                    if (peakOn && start.isNotBlank()) start else null,
                    if (peakOn && end.isNotBlank()) end else null,
                )
            }
            Spacer(Modifier.height(4.dp))
            TextButton(onClick = onDismiss, modifier = Modifier.fillMaxWidth()) {
                Text("Cancel", color = AuthMuted, fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun VenuePricingScreen(api: PartnerApi, token: String, venueId: Long, venueName: String, onBack: () -> Unit) {
    var reload by remember { mutableStateOf(0) }
    var editing by remember { mutableStateOf<SlotEdit?>(null) }
    var adding by remember { mutableStateOf(false) }
    var showCourts by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    val state by produceState<UiState<List<SlotEdit>>>(UiState.Loading, reload) {
        value = runCatchingUi { api.venueSlots(token, venueId) }
    }

    if (showCourts) {
        CourtPricingScreen(api, token, venueId, venueName, onBack = { showCourts = false })
        return
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White, scrolledContainerColor = Color.White),
                title = { Text("Pricing & slots", maxLines = 1, fontWeight = FontWeight.Bold, color = AuthInk, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = AuthInk) }
                },
                actions = {
                    HeaderIcon(Icons.Filled.Add, "Add slot") { adding = true }
                    Spacer(Modifier.width(4.dp))
                },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().background(AuthPageBg).padding(padding)) {
            Text(
                venueName,
                Modifier.padding(horizontal = 16.dp, vertical = 10.dp),
                fontSize = 13.sp,
                color = AuthMuted,
            )
            // Peak pricing lives on the court, not the slot — its own screen.
            LayoutBox(Modifier.padding(horizontal = 16.dp)) {
                PressableSurface(onClick = { showCourts = true }) {
                    Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                        LayoutBox(
                            Modifier.size(42.dp).clip(RoundedCornerShape(13.dp))
                                .background(Brush.linearGradient(listOf(Color(0xFFFFF1DC), Color(0xFFFFE3BC)))),
                            contentAlignment = Alignment.Center,
                        ) { Icon(Icons.AutoMirrored.Filled.TrendingUp, contentDescription = null, tint = Color(0xFFB45309), modifier = Modifier.size(21.dp)) }
                        Spacer(Modifier.width(13.dp))
                        Column(Modifier.weight(1f)) {
                            Text("Courts & peak pricing", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk)
                            Spacer(Modifier.height(1.dp))
                            Text("Charge more at busy hours", fontSize = 12.sp, color = AuthMuted)
                        }
                        Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = Color(0xFFB6C0D0), modifier = Modifier.size(20.dp))
                    }
                }
            }
            Spacer(Modifier.height(14.dp))
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

/** Notifications — the bell inbox broadcast from the Haraan team. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun NotificationsScreen(api: PartnerApi, token: String, onBack: () -> Unit) {
    var reload by remember { mutableStateOf(0) }
    val scope = rememberCoroutineScope()
    val state by produceState<UiState<NotificationsPage>>(UiState.Loading, reload) {
        value = runCatchingUi { api.notifications(token) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White, scrolledContainerColor = Color.White),
                title = { Text("Notifications", fontWeight = FontWeight.Bold, color = AuthInk, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = AuthInk) }
                },
                actions = {
                    val s = state
                    if (s is UiState.Data && s.value.unread > 0) {
                        TextButton(onClick = {
                            scope.launch { runCatching { api.markNotificationsRead(token) }; reload++ }
                        }) { Text("Mark all read", color = AuthAccent, fontWeight = FontWeight.SemiBold, fontSize = 13.sp) }
                    }
                },
            )
        },
    ) { padding ->
        LayoutBox(Modifier.fillMaxSize().background(AuthPageBg).padding(padding)) {
            Loaded(state) { p ->
                if (p.items.isEmpty()) {
                    EmptyState("No notifications yet.")
                } else {
                    LazyColumn(
                        Modifier.fillMaxSize().padding(horizontal = 16.dp),
                        verticalArrangement = Arrangement.spacedBy(10.dp),
                        contentPadding = androidx.compose.foundation.layout.PaddingValues(top = 14.dp, bottom = 28.dp),
                    ) {
                        items(p.items) { n ->
                            LayoutBox(Modifier.fillMaxWidth().premiumSurface(16.dp)) {
                                Row(Modifier.padding(15.dp)) {
                                    // Unread gets a dot; read rows stay quiet.
                                    LayoutBox(
                                        Modifier.padding(top = 5.dp).size(8.dp).clip(RoundedCornerShape(99.dp))
                                            .background(if (n.read) Color.Transparent else AuthAccent),
                                    )
                                    Spacer(Modifier.width(11.dp))
                                    Column(Modifier.weight(1f)) {
                                        Text(
                                            n.title,
                                            fontSize = 14.5.sp,
                                            fontWeight = if (n.read) FontWeight.SemiBold else FontWeight.Bold,
                                            color = AuthInk,
                                        )
                                        if (!n.body.isNullOrBlank()) {
                                            Spacer(Modifier.height(3.dp))
                                            Text(n.body, fontSize = 12.5.sp, color = AuthMuted, lineHeight = 17.sp)
                                        }
                                        n.createdAt?.let {
                                            Spacer(Modifier.height(6.dp))
                                            Text(it.take(10), fontSize = 11.sp, color = AuthMuted)
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

/** Support — the partner↔Haraan conversation, same thread the web panel shows. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun SupportScreen(api: PartnerApi, token: String, onBack: () -> Unit) {
    var reload by remember { mutableStateOf(0) }
    var draft by remember { mutableStateOf("") }
    var sending by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    val view = LocalView.current
    val state by produceState<UiState<List<SupportMessage>>>(UiState.Loading, reload) {
        value = runCatchingUi { api.supportThread(token) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White, scrolledContainerColor = Color.White),
                title = { Text("Support", fontWeight = FontWeight.Bold, color = AuthInk, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = AuthInk) }
                },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().background(AuthPageBg).padding(padding).imePadding()) {
            LayoutBox(Modifier.weight(1f)) {
                Loaded(state) { msgs ->
                    if (msgs.isEmpty()) {
                        EmptyState("Ask us anything — we usually reply within a few hours.")
                    } else {
                        LazyColumn(
                            Modifier.fillMaxSize().padding(horizontal = 16.dp),
                            verticalArrangement = Arrangement.spacedBy(8.dp),
                            contentPadding = androidx.compose.foundation.layout.PaddingValues(vertical = 14.dp),
                        ) {
                            items(msgs) { m ->
                                Row(
                                    Modifier.fillMaxWidth(),
                                    horizontalArrangement = if (m.fromAdmin) Arrangement.Start else Arrangement.End,
                                ) {
                                    Column(
                                        Modifier.widthIn(max = 290.dp)
                                            .clip(RoundedCornerShape(16.dp))
                                            .background(if (m.fromAdmin) Color.White else AuthAccent)
                                            .padding(horizontal = 14.dp, vertical = 10.dp),
                                    ) {
                                        Text(
                                            m.body,
                                            fontSize = 13.5.sp, lineHeight = 19.sp,
                                            color = if (m.fromAdmin) AuthInk else Color.White,
                                        )
                                        m.createdAt?.let {
                                            Spacer(Modifier.height(4.dp))
                                            Text(
                                                it.substring(11, 16.coerceAtMost(it.length)),
                                                fontSize = 10.sp,
                                                color = if (m.fromAdmin) AuthMuted else Color(0xB3FFFFFF),
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            Row(
                Modifier.fillMaxWidth().background(Color.White).padding(12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                OutlinedTextField(
                    value = draft,
                    onValueChange = { draft = it },
                    placeholder = { Text("Type a message") },
                    shape = RoundedCornerShape(22.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = AuthAccent, cursorColor = AuthAccent,
                        unfocusedBorderColor = Color(0x1F0F172A),
                    ),
                    maxLines = 4,
                    modifier = Modifier.weight(1f),
                )
                Spacer(Modifier.width(9.dp))
                LayoutBox(
                    Modifier.size(46.dp).clip(RoundedCornerShape(99.dp))
                        .background(if (draft.isNotBlank() && !sending) AuthAccent else Color(0xFFCBD5E1))
                        .clickable(enabled = draft.isNotBlank() && !sending) {
                            val body = draft.trim()
                            draft = ""
                            sending = true
                            view.performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
                            scope.launch {
                                runCatching { api.sendSupportMessage(token, body) }
                                sending = false
                                reload++
                            }
                        },
                    contentAlignment = Alignment.Center,
                ) { Text("→", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = Color.White) }
            }
        }
    }
}

/**
 * Academy — coaching batches, their roster, and daily attendance.
 * The desk's morning job: open today's batch, tick who turned up.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun AcademyScreen(api: PartnerApi, token: String, onBack: () -> Unit) {
    var reload by remember { mutableStateOf(0) }
    var creating by remember { mutableStateOf(false) }
    var openBatch by remember { mutableStateOf<BatchRow?>(null) }
    val scope = rememberCoroutineScope()
    val state by produceState<UiState<List<BatchRow>>>(UiState.Loading, reload) {
        value = runCatchingUi { api.academy(token) }
    }

    openBatch?.let { b ->
        BatchRosterScreen(api, token, b, onBack = { openBatch = null; reload++ })
        return
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White, scrolledContainerColor = Color.White),
                title = { Text("Academy", fontWeight = FontWeight.Bold, color = AuthInk, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = AuthInk) }
                },
                actions = { HeaderIcon(Icons.Filled.Add, "New batch") { creating = true }; Spacer(Modifier.width(4.dp)) },
            )
        },
    ) { padding ->
        LayoutBox(Modifier.fillMaxSize().background(AuthPageBg).padding(padding)) {
            Loaded(state) { batches ->
                if (batches.isEmpty()) {
                    EmptyState("No coaching batches yet. Tap + to add one.")
                } else {
                    LazyColumn(
                        Modifier.fillMaxSize().padding(horizontal = 16.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp),
                        contentPadding = androidx.compose.foundation.layout.PaddingValues(top = 14.dp, bottom = 28.dp),
                    ) {
                        items(batches) { b -> BatchCard(b) { openBatch = b } }
                    }
                }
            }
        }
    }

    if (creating) {
        NewBatchDialog(
            onDismiss = { creating = false },
            onSave = { name, coach, days, start, end, fee, cap ->
                creating = false
                scope.launch { runCatching { api.saveBatch(token, name, coach, days, start, end, fee, cap) }; reload++ }
            },
        )
    }
}

@Composable
private fun BatchCard(b: BatchRow, onClick: () -> Unit) {
    PressableSurface(onClick = onClick) {
        Column(Modifier.padding(16.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(b.name, fontSize = 15.5.sp, fontWeight = FontWeight.Bold, color = AuthInk, maxLines = 1)
                        if (b.runsToday) {
                            Spacer(Modifier.width(8.dp))
                            Text(
                                "TODAY",
                                fontSize = 9.sp, fontWeight = FontWeight.Bold, color = GREEN, letterSpacing = 0.8.sp,
                                modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x1A16A34A))
                                    .padding(horizontal = 6.dp, vertical = 2.dp),
                            )
                        }
                    }
                    Spacer(Modifier.height(3.dp))
                    Text(
                        listOfNotNull(
                            b.coach,
                            b.days.takeIf { it.isNotEmpty() }?.joinToString(", "),
                            listOfNotNull(b.startTime, b.endTime).takeIf { it.size == 2 }?.joinToString("–"),
                        ).joinToString(" · ").ifBlank { "No schedule set" },
                        fontSize = 12.sp, color = AuthMuted, maxLines = 2,
                    )
                }
                Column(horizontalAlignment = Alignment.End) {
                    Text("₹${b.monthlyFee}", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                    Text("per month", fontSize = 10.5.sp, color = AuthMuted)
                }
            }
            Spacer(Modifier.height(12.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x142F6BFF)).padding(horizontal = 9.dp, vertical = 4.dp),
                ) {
                    Icon(Icons.Filled.People, contentDescription = null, tint = AuthAccentDeep, modifier = Modifier.size(12.dp))
                    Spacer(Modifier.width(5.dp))
                    Text(
                        "${b.students}" + (b.capacity?.let { "/$it" } ?: "") + " students",
                        fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = AuthAccentDeep,
                    )
                }
                if (b.overdue > 0) {
                    Spacer(Modifier.width(8.dp))
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x14DC2626)).padding(horizontal = 9.dp, vertical = 4.dp),
                    ) {
                        LayoutBox(Modifier.size(6.dp).clip(RoundedCornerShape(99.dp)).background(RED))
                        Spacer(Modifier.width(6.dp))
                        Text("${b.overdue} fees due", fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = RED)
                    }
                }
                Spacer(Modifier.weight(1f))
                Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = Color(0xFFB6C0D0), modifier = Modifier.size(20.dp))
            }
        }
    }
}

/** Today's roster — tick who turned up. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun BatchRosterScreen(api: PartnerApi, token: String, batch: BatchRow, onBack: () -> Unit) {
    var dayMillis by remember { mutableStateOf(todayMillis()) }
    var reload by remember { mutableStateOf(0) }
    var enrolling by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    val view = LocalView.current
    val date = apiDate(dayMillis)
    val state by produceState<UiState<RosterPage>>(UiState.Loading, dayMillis, reload) {
        value = runCatchingUi { api.batchRoster(token, batch.id, date) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White, scrolledContainerColor = Color.White),
                title = { Text(batch.name, maxLines = 1, fontWeight = FontWeight.Bold, color = AuthInk, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = AuthInk) }
                },
                actions = { HeaderIcon(Icons.Filled.Add, "Enrol student") { enrolling = true }; Spacer(Modifier.width(4.dp)) },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().background(AuthPageBg).padding(padding)) {
            Row(
                Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp).premiumSurface(14.dp).padding(4.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                IconButton(onClick = { dayMillis -= DAY_MS }) { Icon(Icons.Filled.ChevronLeft, contentDescription = "Previous day", tint = AuthAccent) }
                Text(prettyDate(dayMillis), fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk)
                IconButton(onClick = { dayMillis += DAY_MS }) { Icon(Icons.Filled.ChevronRight, contentDescription = "Next day", tint = AuthAccent) }
            }
            Loaded(state) { r ->
                if (!r.runsToday) {
                    Text(
                        "This batch doesn't run on this day.",
                        fontSize = 12.5.sp, color = AuthMuted,
                        modifier = Modifier.padding(horizontal = 18.dp, vertical = 2.dp),
                    )
                }
                if (r.students.isEmpty()) {
                    EmptyState("No students enrolled yet. Tap + to add one.")
                } else {
                    val present = r.students.count { it.present }
                    LazyColumn(
                        Modifier.fillMaxSize().padding(horizontal = 16.dp),
                        verticalArrangement = Arrangement.spacedBy(10.dp),
                        contentPadding = androidx.compose.foundation.layout.PaddingValues(top = 8.dp, bottom = 28.dp),
                    ) {
                        item {
                            Text(
                                "$present of ${r.students.size} present",
                                fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = AuthMuted,
                                modifier = Modifier.padding(bottom = 2.dp),
                            )
                        }
                        items(r.students) { s ->
                            StudentCard(s) { nowPresent ->
                                view.performHapticFeedback(
                                    if (nowPresent) HapticFeedbackConstants.CONFIRM else HapticFeedbackConstants.KEYBOARD_TAP,
                                )
                                scope.launch {
                                    runCatching { api.markAttendance(token, s.id, date, nowPresent) }
                                    reload++
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if (enrolling) {
        EnrollStudentDialog(
            fee = batch.monthlyFee,
            onDismiss = { enrolling = false },
            onEnroll = { name, phone, months ->
                enrolling = false
                scope.launch { runCatching { api.enrollStudent(token, batch.id, name, phone, months) }; reload++ }
            },
        )
    }
}

@Composable
private fun StudentCard(s: StudentRow, onToggle: (Boolean) -> Unit) {
    LayoutBox(Modifier.fillMaxWidth().premiumSurface(16.dp)) {
        Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
            LayoutBox(
                Modifier.size(40.dp).clip(RoundedCornerShape(99.dp))
                    .background(if (s.present) Color(0x1A16A34A) else Color(0x0F0F172A)),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    s.name.take(1).uppercase(),
                    fontSize = 15.sp, fontWeight = FontWeight.Bold,
                    color = if (s.present) GREEN else AuthMuted,
                )
            }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(s.name, fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = AuthInk, maxLines = 1)
                    if (s.overdue) {
                        Spacer(Modifier.width(7.dp))
                        Text(
                            "FEE DUE",
                            fontSize = 8.5.sp, fontWeight = FontWeight.Bold, color = RED, letterSpacing = 0.8.sp,
                            modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x14DC2626))
                                .padding(horizontal = 6.dp, vertical = 2.dp),
                        )
                    }
                }
                Spacer(Modifier.height(2.dp))
                Text(
                    "${s.attended} classes" + (s.paidUntil?.let { " · paid to $it" } ?: ""),
                    fontSize = 11.5.sp, color = AuthMuted, maxLines = 1,
                )
            }
            // The tick is the whole job — big target, obvious state.
            LayoutBox(
                Modifier.size(38.dp).clip(RoundedCornerShape(12.dp))
                    .background(if (s.present) GREEN else Color(0xFFF1F5F9))
                    .clickable { onToggle(!s.present) },
                contentAlignment = Alignment.Center,
            ) {
                Text("✓", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = if (s.present) Color.White else Color(0xFFB6C0D0))
            }
        }
    }
}

@Composable
private fun NewBatchDialog(
    onDismiss: () -> Unit,
    onSave: (String, String?, List<String>, String?, String?, Int, Int?) -> Unit,
) {
    var name by remember { mutableStateOf("") }
    var coach by remember { mutableStateOf("") }
    var days by remember { mutableStateOf(setOf<String>()) }
    var start by remember { mutableStateOf("06:00") }
    var end by remember { mutableStateOf("07:00") }
    var fee by remember { mutableStateOf("") }
    var cap by remember { mutableStateOf("") }
    val view = LocalView.current
    val colors = OutlinedTextFieldDefaults.colors(
        focusedBorderColor = AuthAccent, focusedLabelColor = AuthAccent,
        cursorColor = AuthAccent, unfocusedBorderColor = Color(0x1F0F172A),
    )
    val valid = name.isNotBlank() && (fee.toIntOrNull() ?: -1) >= 0

    Dialog(onDismissRequest = onDismiss) {
        Column(Modifier.fillMaxWidth().clip(RoundedCornerShape(24.dp)).background(Color.White).verticalScroll(rememberScrollState()).padding(22.dp)) {
            Text("New batch", fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
            Spacer(Modifier.height(3.dp))
            Text("A recurring coaching class.", fontSize = 12.sp, color = AuthMuted)
            Spacer(Modifier.height(16.dp))
            OutlinedTextField(name, { name = it }, label = { Text("Batch name") }, placeholder = { Text("Junior Badminton") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(10.dp))
            OutlinedTextField(coach, { coach = it }, label = { Text("Coach") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(16.dp))
            Text("DAYS", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = AuthMuted, letterSpacing = 1.4.sp)
            Spacer(Modifier.height(9.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(5.dp)) {
                WEEK_DAYS.forEach { d ->
                    val on = d in days
                    LayoutBox(
                        Modifier.weight(1f).height(38.dp).clip(RoundedCornerShape(10.dp))
                            .background(if (on) AuthAccent else Color(0xFFF1F5F9))
                            .clickable { days = if (on) days - d else days + d; view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP) },
                        contentAlignment = Alignment.Center,
                    ) { Text(d.take(1), fontSize = 12.sp, fontWeight = FontWeight.Bold, color = if (on) Color.White else AuthMuted) }
                }
            }
            Spacer(Modifier.height(14.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                OutlinedTextField(start, { start = it.take(5) }, label = { Text("From") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.weight(1f))
                OutlinedTextField(end, { end = it.take(5) }, label = { Text("To") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.weight(1f))
            }
            Spacer(Modifier.height(10.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                OutlinedTextField(fee, { fee = it.filter { c -> c.isDigit() }.take(7) }, label = { Text("Fee ₹/month") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.weight(1f))
                OutlinedTextField(cap, { cap = it.filter { c -> c.isDigit() }.take(3) }, label = { Text("Capacity") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.weight(1f))
            }
            Spacer(Modifier.height(18.dp))
            GradientCta(text = "Create batch", enabled = valid, loading = false) {
                onSave(name.trim(), coach.trim().ifBlank { null }, days.toList(), start.ifBlank { null }, end.ifBlank { null }, fee.toInt(), cap.toIntOrNull())
            }
            Spacer(Modifier.height(4.dp))
            TextButton(onClick = onDismiss, modifier = Modifier.fillMaxWidth()) { Text("Cancel", color = AuthMuted, fontWeight = FontWeight.SemiBold) }
        }
    }
}

@Composable
private fun EnrollStudentDialog(fee: Int, onDismiss: () -> Unit, onEnroll: (String, String, Int) -> Unit) {
    var name by remember { mutableStateOf("") }
    var phone by remember { mutableStateOf("") }
    var months by remember { mutableStateOf(1) }
    val view = LocalView.current
    val colors = OutlinedTextFieldDefaults.colors(
        focusedBorderColor = AuthAccent, focusedLabelColor = AuthAccent,
        cursorColor = AuthAccent, unfocusedBorderColor = Color(0x1F0F172A),
    )
    Dialog(onDismissRequest = onDismiss) {
        Column(Modifier.fillMaxWidth().clip(RoundedCornerShape(24.dp)).background(Color.White).padding(22.dp)) {
            Text("Enrol student", fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
            Spacer(Modifier.height(3.dp))
            Text("Already enrolled? This extends their fees instead of adding them twice.", fontSize = 12.sp, color = AuthMuted, lineHeight = 16.sp)
            Spacer(Modifier.height(16.dp))
            OutlinedTextField(name, { name = it }, label = { Text("Student name") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(10.dp))
            OutlinedTextField(phone, { phone = it.filter { c -> c.isDigit() }.take(10) }, label = { Text("Phone") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone), modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(16.dp))
            Text("MONTHS PAID", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = AuthMuted, letterSpacing = 1.4.sp)
            Spacer(Modifier.height(9.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                listOf(1, 3, 6, 12).forEach { m ->
                    val on = months == m
                    LayoutBox(
                        Modifier.weight(1f).height(40.dp).clip(RoundedCornerShape(10.dp))
                            .background(if (on) Color(0x142F6BFF) else Color(0xFFF8FAFC))
                            .border(if (on) 1.5.dp else 1.dp, if (on) AuthAccent else Color(0x1F0F172A), RoundedCornerShape(10.dp))
                            .clickable { months = m; view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP) },
                        contentAlignment = Alignment.Center,
                    ) { Text("$m", fontSize = 13.sp, fontWeight = if (on) FontWeight.Bold else FontWeight.Medium, color = if (on) AuthAccentDeep else AuthInk) }
                }
            }
            Spacer(Modifier.height(18.dp))
            GradientCta(text = "Enrol · ₹" + formatInr((fee.toLong() * months).toDouble()), enabled = name.isNotBlank() && phone.length == 10, loading = false) {
                onEnroll(name.trim(), phone, months)
            }
            Spacer(Modifier.height(4.dp))
            TextButton(onClick = onDismiss, modifier = Modifier.fillMaxWidth()) { Text("Cancel", color = AuthMuted, fontWeight = FontWeight.SemiBold) }
        }
    }
}

/**
 * Packages — the memberships a venue sells, and who's currently on one.
 * Selling here is what makes "Use a session" appear on the walk-in sheet.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PackagesScreen(api: PartnerApi, token: String, venueId: Long? = null, onBack: () -> Unit) {
    var reload by remember { mutableStateOf(0) }
    var creating by remember { mutableStateOf(false) }
    var selling by remember { mutableStateOf<VenuePackageRow?>(null) }
    val scope = rememberCoroutineScope()
    val state by produceState<UiState<PackagesPage>>(UiState.Loading, reload, venueId) {
        value = runCatchingUi { api.packages(token, venueId) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White, scrolledContainerColor = Color.White),
                title = { Text("Packages", fontWeight = FontWeight.Bold, color = AuthInk, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = AuthInk) }
                },
                actions = { HeaderIcon(Icons.Filled.Add, "New package") { creating = true }; Spacer(Modifier.width(4.dp)) },
            )
        },
    ) { padding ->
        LayoutBox(Modifier.fillMaxSize().background(AuthPageBg).padding(padding)) {
            Loaded(state) { p ->
                LazyColumn(
                    Modifier.fillMaxSize().padding(horizontal = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                    contentPadding = androidx.compose.foundation.layout.PaddingValues(top = 14.dp, bottom = 28.dp),
                ) {
                    item { SectionLabel("WHAT YOU SELL") }
                    if (p.packages.isEmpty()) {
                        item {
                            LayoutBox(Modifier.fillMaxWidth().premiumSurface().padding(20.dp)) {
                                Text(
                                    "No packages yet. Tap + to create one — e.g. 10 sessions for ₹4,000.",
                                    fontSize = 13.sp, color = AuthMuted, lineHeight = 18.sp,
                                )
                            }
                        }
                    } else {
                        items(p.packages) { pkg -> PackageCard(pkg) { selling = pkg } }
                    }
                    item { SectionLabel("ON A PASS (${p.holders.size})") }
                    if (p.holders.isEmpty()) {
                        item {
                            LayoutBox(Modifier.fillMaxWidth().premiumSurface().padding(20.dp)) {
                                Text("Nobody is on a package yet.", fontSize = 13.sp, color = AuthMuted)
                            }
                        }
                    } else {
                        items(p.holders) { h -> HolderCard(h) }
                    }
                }
            }
        }
    }

    if (creating) {
        NewPackageDialog(
            onDismiss = { creating = false },
            onSave = { name, price, sessions, days ->
                creating = false
                scope.launch { runCatching { api.savePackage(token, name, price, sessions, days) }; reload++ }
            },
        )
    }
    selling?.let { pkg ->
        SellPackageDialog(
            pkg = pkg,
            onDismiss = { selling = null },
            onSell = { phone, name ->
                selling = null
                scope.launch { runCatching { api.sellPackage(token, pkg.id, phone, name, venueId = venueId) }; reload++ }
            },
        )
    }
}

@Composable
private fun SectionLabel(text: String) {
    Text(text, fontSize = 10.sp, fontWeight = FontWeight.Bold, color = AuthMuted, letterSpacing = 1.4.sp, modifier = Modifier.padding(top = 4.dp))
}

@Composable
private fun PackageCard(p: VenuePackageRow, onSell: () -> Unit) {
    PressableSurface(onClick = onSell) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(p.name, fontSize = 15.5.sp, fontWeight = FontWeight.Bold, color = AuthInk)
                Spacer(Modifier.height(3.dp))
                Text(
                    "${p.sessions} sessions · ₹${p.perSession}/session" +
                        (p.validityDays?.let { " · ${it}d validity" } ?: " · no expiry"),
                    fontSize = 12.sp, color = AuthMuted,
                )
            }
            Column(horizontalAlignment = Alignment.End) {
                Text("₹" + formatInr(p.price.toDouble()), fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                Spacer(Modifier.height(4.dp))
                Text(
                    "SELL",
                    fontSize = 10.sp, fontWeight = FontWeight.Bold, color = AuthAccentDeep, letterSpacing = 0.8.sp,
                    modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x142F6BFF)).padding(horizontal = 9.dp, vertical = 4.dp),
                )
            }
        }
    }
}

@Composable
private fun HolderCard(h: PackageHolder) {
    val frac = if (h.total > 0) h.remaining.toFloat() / h.total else 0f
    LayoutBox(Modifier.fillMaxWidth().premiumSurface().padding(16.dp)) {
        Column {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text(h.name, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk, maxLines = 1)
                    Spacer(Modifier.height(1.dp))
                    Text("${h.packageName} · +91 ${h.phone}", fontSize = 12.sp, color = AuthMuted, maxLines = 1)
                }
                Text("${h.remaining}/${h.total}", fontSize = 16.sp, fontWeight = FontWeight.ExtraBold, color = if (h.expired) RED else GREEN)
            }
            Spacer(Modifier.height(10.dp))
            LayoutBox(Modifier.fillMaxWidth().height(6.dp).clip(RoundedCornerShape(99.dp)).background(Color(0xFFEDF0F5))) {
                LayoutBox(Modifier.fillMaxWidth(frac).fillMaxHeight().clip(RoundedCornerShape(99.dp)).background(if (h.expired) RED else GREEN))
            }
            if (h.expiresAt != null) {
                Spacer(Modifier.height(8.dp))
                Text(
                    if (h.expired) "Expired ${h.expiresAt}" else "Valid until ${h.expiresAt}",
                    fontSize = 11.5.sp, color = if (h.expired) RED else AuthMuted,
                )
            }
        }
    }
}

@Composable
private fun NewPackageDialog(onDismiss: () -> Unit, onSave: (String, Int, Int, Int?) -> Unit) {
    var name by remember { mutableStateOf("") }
    var price by remember { mutableStateOf("") }
    var sessions by remember { mutableStateOf("") }
    var days by remember { mutableStateOf("") }
    val colors = OutlinedTextFieldDefaults.colors(
        focusedBorderColor = AuthAccent, focusedLabelColor = AuthAccent,
        cursorColor = AuthAccent, unfocusedBorderColor = Color(0x1F0F172A),
    )
    val valid = name.isNotBlank() && (price.toIntOrNull() ?: 0) > 0 && (sessions.toIntOrNull() ?: 0) > 0

    Dialog(onDismissRequest = onDismiss) {
        Column(Modifier.fillMaxWidth().clip(RoundedCornerShape(24.dp)).background(Color.White).verticalScroll(rememberScrollState()).padding(22.dp)) {
            Text("New package", fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
            Spacer(Modifier.height(3.dp))
            Text("A prepaid bundle of sessions customers can buy.", fontSize = 12.sp, color = AuthMuted)
            Spacer(Modifier.height(16.dp))
            OutlinedTextField(name, { name = it }, label = { Text("Name") }, placeholder = { Text("10 Session Pass") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(10.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                OutlinedTextField(price, { price = it.filter { c -> c.isDigit() }.take(7) }, label = { Text("Price ₹") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.weight(1f))
                OutlinedTextField(sessions, { sessions = it.filter { c -> c.isDigit() }.take(3) }, label = { Text("Sessions") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.weight(1f))
            }
            Spacer(Modifier.height(10.dp))
            OutlinedTextField(days, { days = it.filter { c -> c.isDigit() }.take(4) }, label = { Text("Validity in days (blank = never expires)") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.fillMaxWidth())
            if (valid) {
                Spacer(Modifier.height(10.dp))
                Text(
                    "That's ₹${(price.toInt() / sessions.toInt())} per session.",
                    fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = AuthAccentDeep,
                )
            }
            Spacer(Modifier.height(18.dp))
            GradientCta(text = "Create package", enabled = valid, loading = false) {
                onSave(name.trim(), price.toInt(), sessions.toInt(), days.toIntOrNull())
            }
            Spacer(Modifier.height(4.dp))
            TextButton(onClick = onDismiss, modifier = Modifier.fillMaxWidth()) { Text("Cancel", color = AuthMuted, fontWeight = FontWeight.SemiBold) }
        }
    }
}

@Composable
private fun SellPackageDialog(pkg: VenuePackageRow, onDismiss: () -> Unit, onSell: (String, String) -> Unit) {
    var phone by remember { mutableStateOf("") }
    var name by remember { mutableStateOf("") }
    val colors = OutlinedTextFieldDefaults.colors(
        focusedBorderColor = AuthAccent, focusedLabelColor = AuthAccent,
        cursorColor = AuthAccent, unfocusedBorderColor = Color(0x1F0F172A),
    )
    Dialog(onDismissRequest = onDismiss) {
        Column(Modifier.fillMaxWidth().clip(RoundedCornerShape(24.dp)).background(Color.White).padding(22.dp)) {
            Text("Sell ${pkg.name}", fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
            Spacer(Modifier.height(3.dp))
            Text("${pkg.sessions} sessions · ₹${formatInr(pkg.price.toDouble())}", fontSize = 12.5.sp, color = AuthMuted)
            Spacer(Modifier.height(16.dp))
            OutlinedTextField(name, { name = it }, label = { Text("Customer name") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(10.dp))
            OutlinedTextField(phone, { phone = it.filter { c -> c.isDigit() }.take(10) }, label = { Text("Phone") }, singleLine = true, shape = RoundedCornerShape(12.dp), colors = colors, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone), modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(10.dp))
            Text(
                "The pass is tied to this number — it appears automatically when they next book.",
                fontSize = 11.5.sp, color = AuthMuted, lineHeight = 16.sp,
            )
            Spacer(Modifier.height(18.dp))
            GradientCta(text = "Sell for ₹" + formatInr(pkg.price.toDouble()), enabled = phone.length == 10 && name.isNotBlank(), loading = false) {
                onSell(phone, name.trim())
            }
            Spacer(Modifier.height(4.dp))
            TextButton(onClick = onDismiss, modifier = Modifier.fillMaxWidth()) { Text("Cancel", color = AuthMuted, fontWeight = FontWeight.SemiBold) }
        }
    }
}

/**
 * Customers — who books here, how often, and what they're worth. Identity is the
 * customer's phone, so a person who books online and later walks in is one row.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun CustomersScreen(api: PartnerApi, token: String, venueId: Long? = null, onBack: () -> Unit) {
    var query by remember { mutableStateOf("") }
    var contacting by remember { mutableStateOf<CustomerRow?>(null) }
    val state by produceState<UiState<CustomersPage>>(UiState.Loading, query, venueId) {
        // Debounce so typing doesn't fire a request per keystroke.
        if (query.isNotBlank()) kotlinx.coroutines.delay(300)
        value = runCatchingUi { api.customers(token, query, venueId) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White, scrolledContainerColor = Color.White),
                title = { Text("Customers", fontWeight = FontWeight.Bold, color = AuthInk, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = AuthInk) }
                },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().background(AuthPageBg).padding(padding)) {
            OutlinedTextField(
                value = query,
                onValueChange = { query = it },
                placeholder = { Text("Search name or phone") },
                singleLine = true,
                shape = RoundedCornerShape(14.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = AuthAccent, cursorColor = AuthAccent,
                    unfocusedBorderColor = Color(0x1F0F172A),
                    focusedContainerColor = Color.White, unfocusedContainerColor = Color.White,
                ),
                modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp),
            )
            Loaded(state) { p ->
                if (p.data.isEmpty()) {
                    EmptyState(if (query.isBlank()) "No customers yet." else "No customer matches \"$query\".")
                } else {
                    LazyColumn(
                        Modifier.fillMaxSize().padding(horizontal = 16.dp),
                        verticalArrangement = Arrangement.spacedBy(10.dp),
                        contentPadding = androidx.compose.foundation.layout.PaddingValues(bottom = 24.dp),
                    ) {
                        item { CustomerSummaryStrip(p) }
                        items(p.data) { c -> CustomerCard(c) { contacting = c } }
                    }
                }
            }
        }
    }

    contacting?.let { c -> ContactCustomerDialog(c) { contacting = null } }
}

@Composable
private fun CustomerSummaryStrip(p: CustomersPage) {
    Row(
        Modifier.fillMaxWidth().premiumSurface().padding(vertical = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        listOf(
            "Customers" to p.total.toString(),
            "Repeat" to p.repeat.toString(),
            "No phone" to p.anonymous.toString(),
        ).forEachIndexed { i, (label, value) ->
            Column(Modifier.weight(1f), horizontalAlignment = Alignment.CenterHorizontally) {
                Text(value, fontSize = 20.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                Spacer(Modifier.height(1.dp))
                Text(label, fontSize = 11.5.sp, color = AuthMuted)
            }
            if (i < 2) LayoutBox(Modifier.width(1.dp).height(34.dp).background(Hairline))
        }
    }
}

@Composable
private fun CustomerCard(c: CustomerRow, onClick: () -> Unit) {
    PressableSurface(onClick = onClick, radius = 16.dp) {
        Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
            LayoutBox(
                Modifier.size(42.dp).clip(RoundedCornerShape(99.dp))
                    .background(if (c.isRepeat) Color(0x1A16A34A) else Color(0x142F6BFF)),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    c.name.take(1).uppercase(),
                    fontSize = 16.sp, fontWeight = FontWeight.Bold,
                    color = if (c.isRepeat) GREEN else AuthAccentDeep,
                )
            }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(c.name, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AuthInk, maxLines = 1)
                    if (c.isRepeat) {
                        Spacer(Modifier.width(7.dp))
                        Text(
                            "REGULAR",
                            fontSize = 9.sp, fontWeight = FontWeight.Bold, color = GREEN, letterSpacing = 0.8.sp,
                            modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x1A16A34A))
                                .padding(horizontal = 6.dp, vertical = 2.dp),
                        )
                    }
                }
                Spacer(Modifier.height(2.dp))
                Text(
                    "${c.bookings} booking${if (c.bookings == 1) "" else "s"}" +
                        (c.lastVisit?.let { " · last $it" } ?: ""),
                    fontSize = 12.sp, color = AuthMuted, maxLines = 1,
                )
            }
            Text("₹" + formatInr(c.spent), fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
        }
    }
}

/** Reach a customer straight from the list — the point of having their number. */
@Composable
private fun ContactCustomerDialog(c: CustomerRow, onDismiss: () -> Unit) {
    val context = LocalContext.current
    Dialog(onDismissRequest = onDismiss) {
        Column(
            Modifier.fillMaxWidth().clip(RoundedCornerShape(24.dp)).background(Color.White).padding(22.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            LayoutBox(
                Modifier.size(56.dp).clip(RoundedCornerShape(99.dp)).background(Color(0x142F6BFF)),
                contentAlignment = Alignment.Center,
            ) { Text(c.name.take(1).uppercase(), fontSize = 22.sp, fontWeight = FontWeight.Bold, color = AuthAccentDeep) }
            Spacer(Modifier.height(12.dp))
            Text(c.name, fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
            Spacer(Modifier.height(3.dp))
            Text("+91 ${c.phone}", fontSize = 13.sp, color = AuthMuted)
            Spacer(Modifier.height(14.dp))
            Row(
                Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(Color(0xFFF6F8FC))
                    .border(1.dp, CardBorder, RoundedCornerShape(14.dp)).padding(14.dp),
                horizontalArrangement = Arrangement.SpaceAround,
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text("${c.bookings}", fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                    Text("bookings", fontSize = 11.sp, color = AuthMuted)
                }
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text("₹" + formatInr(c.spent), fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                    Text("spent", fontSize = 11.sp, color = AuthMuted)
                }
            }
            Spacer(Modifier.height(18.dp))
            GradientCta(text = "Message on WhatsApp", enabled = true, loading = false) {
                runCatching {
                    context.startActivity(
                        Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/91${c.phone}"))
                            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
                    )
                }
                onDismiss()
            }
            Spacer(Modifier.height(10.dp))
            SocialButton(
                text = "Call",
                leading = { Icon(Icons.Filled.Phone, contentDescription = null, tint = AuthAccent, modifier = Modifier.size(19.dp)) },
            ) {
                runCatching {
                    context.startActivity(
                        Intent(Intent.ACTION_DIAL, Uri.parse("tel:+91${c.phone}"))
                            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
                    )
                }
                onDismiss()
            }
            Spacer(Modifier.height(4.dp))
            TextButton(onClick = onDismiss, modifier = Modifier.fillMaxWidth()) {
                Text("Close", color = AuthMuted, fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

/**
 * Payouts — the settlement home: what the venue is owed, where it's sent, and
 * what's already landed. Balance figures come from the same PartnerSettlement
 * service the web page and /control settle against, so the app can never show a
 * different "available" than the console.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PayoutsScreen(api: PartnerApi, token: String, onBack: () -> Unit) {
    var reload by remember { mutableStateOf(0) }
    var editing by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    val state by produceState<UiState<PayoutsPage>>(UiState.Loading, reload) {
        value = runCatchingUi { api.payouts(token) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White, scrolledContainerColor = Color.White),
                title = { Text("Payouts", fontWeight = FontWeight.Bold, color = AuthInk, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = AuthInk) }
                },
            )
        },
    ) { padding ->
        LayoutBox(Modifier.fillMaxSize().background(AuthPageBg).padding(padding)) {
            Loaded(state) { p ->
                LazyColumn(
                    Modifier.fillMaxSize().padding(horizontal = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(14.dp),
                    contentPadding = androidx.compose.foundation.layout.PaddingValues(top = 14.dp, bottom = 28.dp),
                ) {
                    item { PayoutBalanceHero(p) }
                    item { PayoutAccountCard(p.account) { editing = true } }
                    item {
                        Text(
                            "SETTLEMENT HISTORY",
                            fontSize = 10.sp, fontWeight = FontWeight.Bold,
                            color = AuthMuted, letterSpacing = 1.4.sp,
                            modifier = Modifier.padding(top = 4.dp),
                        )
                    }
                    if (p.batches.isEmpty()) {
                        item {
                            LayoutBox(Modifier.fillMaxWidth().premiumSurface().padding(20.dp)) {
                                Text(
                                    "No settlements yet. Money you collect shows as available until it's transferred.",
                                    fontSize = 13.sp, color = AuthMuted, lineHeight = 18.sp,
                                )
                            }
                        }
                    } else {
                        items(p.batches) { b -> PayoutBatchCard(b) }
                    }
                }
            }
        }
    }

    if (editing) {
        PayoutAccountDialog(
            onDismiss = { editing = false },
            onSave = { method, holder, bank, acct, ifsc, vpa ->
                editing = false
                scope.launch {
                    runCatching { api.savePayoutAccount(token, method, holder, bank, acct, ifsc, vpa) }
                    reload++
                }
            },
        )
    }
}

@Composable
private fun PayoutBalanceHero(p: PayoutsPage) {
    LayoutBox(
        Modifier.fillMaxWidth()
            .shadow(18.dp, RoundedCornerShape(22.dp), clip = false, spotColor = AuthInkTop)
            .clip(RoundedCornerShape(22.dp))
            .background(Brush.linearGradient(listOf(AuthInkTop, AuthInkMid, AuthInkBot))),
    ) {
        LayoutBox(
            Modifier.matchParentSize().background(
                Brush.radialGradient(listOf(Color(0x553B82F6), Color(0x00000000)), center = Offset(120f, 40f), radius = 520f)
            )
        )
        Column(Modifier.fillMaxWidth().padding(20.dp)) {
            Text("AVAILABLE TO SETTLE", color = Color(0xB3CFE0FF), fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.5.sp)
            Spacer(Modifier.height(8.dp))
            Text("₹" + formatInr(p.available), color = Color.White, fontSize = 34.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-1).sp)
            if (p.inFlight > 0) {
                Spacer(Modifier.height(8.dp))
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(Color(0x33F59E0B)).padding(horizontal = 10.dp, vertical = 5.dp),
                ) {
                    LayoutBox(Modifier.size(6.dp).clip(RoundedCornerShape(99.dp)).background(Color(0xFFFCD34D)))
                    Spacer(Modifier.width(7.dp))
                    Text("₹" + formatInr(p.inFlight) + " being transferred", fontSize = 11.5.sp, fontWeight = FontWeight.SemiBold, color = Color(0xFFFCD34D))
                }
            }
            Spacer(Modifier.height(18.dp))
            Row(Modifier.fillMaxWidth()) {
                PayoutStat(Modifier.weight(1f), "Collected", p.collected)
                LayoutBox(Modifier.width(1.dp).height(34.dp).background(Color(0x33FFFFFF)))
                PayoutStat(Modifier.weight(1f), "Settled", p.settled)
            }
        }
    }
}

@Composable
private fun PayoutStat(modifier: Modifier, label: String, value: Double) {
    Column(modifier, horizontalAlignment = Alignment.CenterHorizontally) {
        Text("₹" + formatInr(value), color = Color.White, fontSize = 16.sp, fontWeight = FontWeight.Bold)
        Spacer(Modifier.height(2.dp))
        Text(label, color = Color(0x99CFE0FF), fontSize = 11.sp)
    }
}

@Composable
private fun PayoutAccountCard(account: PayoutAccount?, onEdit: () -> Unit) {
    PressableSurface(onClick = onEdit) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            LayoutBox(
                Modifier.size(44.dp).clip(RoundedCornerShape(13.dp))
                    .background(Brush.linearGradient(listOf(Color(0xFFEAF1FF), Color(0xFFDCE8FF)))),
                contentAlignment = Alignment.Center,
            ) { Icon(Icons.Filled.Payments, contentDescription = null, tint = AuthAccent, modifier = Modifier.size(22.dp)) }
            Spacer(Modifier.width(13.dp))
            Column(Modifier.weight(1f)) {
                Text(
                    if (account == null) "Add settlement account" else "Money is sent to",
                    fontSize = 12.sp, color = AuthMuted,
                )
                Spacer(Modifier.height(2.dp))
                Text(
                    account?.masked ?: "No account yet — tap to add",
                    fontSize = 14.5.sp, fontWeight = FontWeight.Bold, color = AuthInk, maxLines = 1,
                )
                if (account != null) {
                    Spacer(Modifier.height(6.dp))
                    val tone = if (account.verified) GREEN else Color(0xFFB45309)
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(tone.copy(alpha = 0.12f)).padding(horizontal = 9.dp, vertical = 4.dp),
                    ) {
                        LayoutBox(Modifier.size(6.dp).clip(RoundedCornerShape(99.dp)).background(tone))
                        Spacer(Modifier.width(6.dp))
                        Text(
                            if (account.verified) "Verified" else "Pending verification",
                            fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = tone,
                        )
                    }
                }
            }
            Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = Color(0xFFB6C0D0), modifier = Modifier.size(20.dp))
        }
    }
}

@Composable
private fun PayoutBatchCard(b: PayoutBatchRow) {
    val tone = if (b.isPaid) GREEN else Color(0xFFB45309)
    LayoutBox(Modifier.fillMaxWidth().premiumSurface()) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text("₹" + formatInr(b.amount), fontSize = 16.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
                Spacer(Modifier.height(3.dp))
                Text(
                    listOfNotNull(b.date, b.period).joinToString(" · ").ifBlank { "—" },
                    fontSize = 12.sp, color = AuthMuted,
                )
                if (!b.reference.isNullOrBlank()) {
                    Spacer(Modifier.height(3.dp))
                    Text("Ref ${b.reference}", fontSize = 11.sp, color = AuthMuted)
                }
            }
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.clip(RoundedCornerShape(999.dp)).background(tone.copy(alpha = 0.12f)).padding(horizontal = 10.dp, vertical = 6.dp),
            ) {
                LayoutBox(Modifier.size(6.dp).clip(RoundedCornerShape(99.dp)).background(tone))
                Spacer(Modifier.width(6.dp))
                Text(b.status.replaceFirstChar { it.uppercase() }, fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = tone)
            }
        }
    }
}

/** Enter where settlements are sent. Never prefilled — changing the destination
 *  means re-entering it, and saving clears verification. */
@Composable
private fun PayoutAccountDialog(
    onDismiss: () -> Unit,
    onSave: (method: String, holder: String, bank: String?, acct: String?, ifsc: String?, vpa: String?) -> Unit,
) {
    var method by remember { mutableStateOf("bank") }
    var holder by remember { mutableStateOf("") }
    var bank by remember { mutableStateOf("") }
    var acct by remember { mutableStateOf("") }
    var ifsc by remember { mutableStateOf("") }
    var vpa by remember { mutableStateOf("") }
    val view = LocalView.current

    val colors = OutlinedTextFieldDefaults.colors(
        focusedBorderColor = AuthAccent, focusedLabelColor = AuthAccent,
        cursorColor = AuthAccent, unfocusedBorderColor = Color(0x1F0F172A),
    )
    val valid = holder.isNotBlank() && if (method == "bank") acct.length >= 6 && ifsc.length >= 6 else vpa.length >= 3

    Dialog(onDismissRequest = onDismiss) {
        Column(
            Modifier.fillMaxWidth().clip(RoundedCornerShape(24.dp)).background(Color.White)
                .verticalScroll(rememberScrollState()).padding(22.dp),
        ) {
            Text("Settlement account", fontSize = 17.sp, fontWeight = FontWeight.ExtraBold, color = AuthInk)
            Spacer(Modifier.height(3.dp))
            Text("Where your collected money is transferred.", fontSize = 12.sp, color = AuthMuted)

            Spacer(Modifier.height(16.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                listOf("bank" to "Bank account", "upi" to "UPI").forEach { (key, label) ->
                    val on = method == key
                    LayoutBox(
                        Modifier.weight(1f).clip(RoundedCornerShape(12.dp))
                            .background(if (on) Color(0x142F6BFF) else Color(0xFFF8FAFC))
                            .border(if (on) 1.5.dp else 1.dp, if (on) AuthAccent else Color(0x1F0F172A), RoundedCornerShape(12.dp))
                            .clickable { method = key; view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP) }
                            .padding(vertical = 11.dp),
                        contentAlignment = Alignment.Center,
                    ) {
                        Text(label, fontSize = 13.sp, fontWeight = if (on) FontWeight.Bold else FontWeight.Medium, color = if (on) AuthAccentDeep else AuthInk)
                    }
                }
            }

            Spacer(Modifier.height(14.dp))
            OutlinedTextField(
                value = holder, onValueChange = { holder = it },
                label = { Text("Account holder name") }, singleLine = true,
                shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth(),
            )

            if (method == "bank") {
                Spacer(Modifier.height(10.dp))
                OutlinedTextField(
                    value = bank, onValueChange = { bank = it },
                    label = { Text("Bank name") }, singleLine = true,
                    shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth(),
                )
                Spacer(Modifier.height(10.dp))
                OutlinedTextField(
                    value = acct, onValueChange = { acct = it.filter { c -> c.isDigit() }.take(18) },
                    label = { Text("Account number") }, singleLine = true,
                    shape = RoundedCornerShape(12.dp), colors = colors,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    modifier = Modifier.fillMaxWidth(),
                )
                Spacer(Modifier.height(10.dp))
                OutlinedTextField(
                    value = ifsc, onValueChange = { ifsc = it.uppercase().take(11) },
                    label = { Text("IFSC code") }, singleLine = true,
                    shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth(),
                )
            } else {
                Spacer(Modifier.height(10.dp))
                OutlinedTextField(
                    value = vpa, onValueChange = { vpa = it },
                    label = { Text("UPI ID") }, placeholder = { Text("name@bank") }, singleLine = true,
                    shape = RoundedCornerShape(12.dp), colors = colors, modifier = Modifier.fillMaxWidth(),
                )
            }

            Spacer(Modifier.height(12.dp))
            Text(
                "Saving sends this for re-verification before the next settlement.",
                fontSize = 11.5.sp, color = AuthMuted, lineHeight = 16.sp,
            )

            Spacer(Modifier.height(18.dp))
            GradientCta(text = "Save account", enabled = valid, loading = false) {
                view.performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
                onSave(method, holder.trim(), bank.trim().ifBlank { null }, acct.trim().ifBlank { null }, ifsc.trim().ifBlank { null }, vpa.trim().ifBlank { null })
            }
            Spacer(Modifier.height(4.dp))
            TextButton(onClick = onDismiss, modifier = Modifier.fillMaxWidth()) {
                Text("Cancel", color = AuthMuted, fontWeight = FontWeight.SemiBold)
            }
        }
    }
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
private fun SalesTab(api: PartnerApi, token: String, venueId: Long? = null) {
    RefreshableContent(token to venueId, load = { api.bookings(token, venueId) }) { list ->
        if (list.isEmpty()) EmptyState("No bookings yet") else
            LazyColumn(Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                items(list) { b ->
                    // Viewing all branches, a feed of bare amounts is unreadable —
                    // say which outlet each one came from.
                    val where = if (venueId == null) b.branch else null
                    ListCard(
                        title = b.label ?: (b.ticketCode ?: "Booking #${b.id}"),
                        subtitle = listOfNotNull(where, "${b.quantity} ×", b.status).joinToString(" · "),
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
        // A skeleton of the shape that's coming, not a spinner in the void: the
        // screen keeps its silhouette while the data lands, so switching sections
        // reads as one continuous surface instead of a blank flash.
        is UiState.Loading -> SkeletonList()
        is UiState.Error -> EmptyState(state.message)
        is UiState.Data -> ScreenEnter { content(state.value) }
    }
}

/**
 * The shared loading placeholder: card silhouettes with a light sweep moving
 * across them. Sized like the real rows so nothing jumps when data arrives.
 */
@Composable
private fun SkeletonList(rows: Int = 5) {
    val t = rememberInfiniteTransition(label = "shimmer")
    // Sweeps left→right forever; the gradient is wider than the card so the
    // highlight travels through rather than pulsing in place.
    val x by t.animateFloat(
        initialValue = -700f,
        targetValue = 1400f,
        animationSpec = infiniteRepeatable(tween(1250, easing = LinearEasing)),
        label = "sweep",
    )
    val sweep = Brush.linearGradient(
        colors = listOf(Color(0xFFEDF1F7), Color(0xFFF7FAFE), Color(0xFFEDF1F7)),
        start = Offset(x, 0f),
        end = Offset(x + 700f, 0f),
    )

    Column(
        Modifier.fillMaxSize().background(AuthPageBg).padding(horizontal = 16.dp, vertical = 14.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        repeat(rows) { i ->
            Row(
                Modifier.fillMaxWidth().premiumSurface(16.dp).padding(14.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                LayoutBox(Modifier.size(40.dp).clip(RoundedCornerShape(12.dp)).background(sweep))
                Spacer(Modifier.width(12.dp))
                Column(Modifier.weight(1f)) {
                    // Vary the widths so it reads as content, not a test pattern.
                    LayoutBox(
                        Modifier.fillMaxWidth(if (i % 2 == 0) 0.55f else 0.42f)
                            .height(13.dp).clip(RoundedCornerShape(99.dp)).background(sweep),
                    )
                    Spacer(Modifier.height(8.dp))
                    LayoutBox(
                        Modifier.fillMaxWidth(if (i % 2 == 0) 0.34f else 0.48f)
                            .height(10.dp).clip(RoundedCornerShape(99.dp)).background(sweep),
                    )
                }
                Spacer(Modifier.width(10.dp))
                LayoutBox(Modifier.width(52.dp).height(15.dp).clip(RoundedCornerShape(99.dp)).background(sweep))
            }
        }
    }
}

/**
 * The entrance every screen's content gets: a short fade with a small rise.
 * Keyed on nothing, so it plays once per composition — which is exactly when
 * the user has just switched section and needs to see that something changed.
 */
@Composable
private fun ScreenEnter(content: @Composable () -> Unit) {
    val anim = remember { Animatable(0f) }
    LaunchedEffect(Unit) {
        anim.animateTo(1f, tween(durationMillis = 260, easing = FastOutSlowInEasing))
    }
    LayoutBox(
        Modifier.graphicsLayer {
            alpha = anim.value
            translationY = (1f - anim.value) * 26f
        },
    ) { content() }
}

/**
 * Fills the pane and centres one thing in it — the loading/empty pedestal.
 *
 * Deliberately NOT named `Box`: this file aliases the real layout Box to
 * `LayoutBox`, so a helper called `Box` silently wins name resolution at every
 * bare `Box { }` call site and swaps a wrap-content container for a
 * `fillMaxSize()` one. That is exactly how the branch chip once stretched the
 * whole top bar to full height and squeezed the home screen to nothing.
 */
@Composable
private fun CenteredPane(content: @Composable () -> Unit) {
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

