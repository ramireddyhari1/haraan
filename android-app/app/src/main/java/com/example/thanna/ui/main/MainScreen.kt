package com.example.thanna.ui.main

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Apartment
import androidx.compose.material.icons.filled.AccountBalance
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.DateRange
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.List
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.SportsCricket
import androidx.compose.material.icons.filled.SportsFootball
import androidx.compose.material.icons.filled.SportsTennis
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.filled.Bookmark
import androidx.compose.material.icons.filled.BookmarkBorder
import androidx.compose.material.icons.filled.KeyboardArrowDown
import androidx.compose.material.icons.filled.Movie
import androidx.compose.material.icons.filled.Restaurant
import androidx.compose.material.icons.filled.Mic
import androidx.compose.material3.Icon
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.SideEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.res.painterResource
import coil.compose.AsyncImage
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.example.thanna.EventDetail
import androidx.navigation3.runtime.NavKey
import com.example.thanna.data.DefaultDataRepository
import com.example.thanna.theme.ThannaTheme
import android.widget.Toast
import android.app.Activity
import androidx.core.view.WindowCompat

private val Maroon = Color(0xFF7A1D2A)
private val DeepMaroon = Color(0xFF4D1019)
private val Gold = Color(0xFFC79A2D)
private val Cream = Color(0xFFF9F1E8)
private val Saffron = Color(0xFFE78A2F)
private val SoftRose = Color(0xFFF2D9DE)
private val SoftBlue = Color(0xFFE4F1F8)
private val AccentBlue = Color(0xFF7FB8D8)

private val LightBackground = Color(0xFFF5F5F5)
private val LightCard = Color.White
private val LightPrimaryText = Color(0xFF111827)
private val LightSecondaryText = Color(0xFF6B7280)
private val LightMutedText = Color(0xFF9CA3AF)
private val LightDivider = Color(0xFFEEF2F7)
private val LightAccentBlue = Color(0xFF2563EB)
private val LightAccentPink = Color(0xFFCC4B6C)
private val LightNavIndicator = Color(0xFFF1F5F9)

// Premium Light/Hybrid Color Palette
private val Midnight = Color(0xFFF8FAFC)        // Slate 50 background
private val MidnightAlt = Color(0xFFF1F5F9)     // Slate 100 card backgrounds
private val CardTint = Color(0xFFFFFFFF)        // Card container color
private val CardStroke = Color(0xFFE2E8F0)      // Slate 200 borders
private val MIBlue = Color(0xFF0F62FE)
private val MIGreen = Color(0xFF00C853)
private val AccentWhite = Color(0xFFFFFFFF)
private val BorderGray = Color(0xFFE2E8F0)
private val TextMuted = Color(0xFF64748B)       // Slate 500 secondary text
private val UnifiedCornerRadius = 20.dp

@Composable
fun MainScreen(
  onItemClick: (NavKey) -> Unit,
  modifier: Modifier = Modifier,
  viewModel: MainScreenViewModel = viewModel { MainScreenViewModel(DefaultDataRepository()) },
) {
  val context = LocalContext.current
  val loginState by viewModel.loginUiState.collectAsStateWithLifecycle()
  
  var cachedToken by remember { mutableStateOf<String?>(null) }
  var hasCheckedCache by remember { mutableStateOf(false) }

  LaunchedEffect(Unit) {
    cachedToken = com.example.thanna.data.TokenStore.getToken(context)
    hasCheckedCache = true
  }

  if (!hasCheckedCache) {
    Box(
      modifier = modifier.fillMaxSize().background(Midnight),
      contentAlignment = Alignment.Center
    ) {
      androidx.compose.material3.CircularProgressIndicator(color = MIBlue)
    }
    return
  }

  val isUserLoggedIn = !cachedToken.isNullOrBlank() || loginState.stage == LoginStage.Success || !loginState.token.isNullOrBlank()

  if (isUserLoggedIn) {
    val activeToken = cachedToken ?: loginState.token ?: ""
    MainAppContainer(
      token = activeToken,
      onItemClick = onItemClick,
      onLogout = {
        com.example.thanna.data.TokenStore.saveToken(context, "")
        cachedToken = null
        viewModel.onPhoneChanged("")
        viewModel.onOtpChanged("")
      }
    )
  } else {
    LoginScreen(
      onSkip = {
        cachedToken = "skipped_guest"
      },
      modifier = modifier,
      viewModel = viewModel
    )
  }
}

private data class MatchRow(
  val teamA: String,
  val scoreA: String,
  val oversA: String,
  val teamB: String,
  val scoreB: String,
  val oversB: String,
  val resultTitle: String,
  val resultSubtitle: String
)

@Composable
private fun SegmentTabs(selected: String, onSelect: (String) -> Unit) {
  val sections = listOf("Live (5)", "For You", "Upcoming", "Finished")
  Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
    sections.forEach { s ->
      val isSelected = s == selected
      Column(modifier = Modifier.weight(1f), horizontalAlignment = Alignment.CenterHorizontally) {
        Text(
          text = s,
          color = if (isSelected) Color.White else Color(0xFF7F97B0),
          fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
          fontSize = 14.sp
        )
        Spacer(modifier = Modifier.height(6.dp))
        Box(
          modifier = Modifier
            .height(3.dp)
            .width(48.dp)
            .clip(RoundedCornerShape(2.dp))
            .background(if (isSelected) Color(0xFFFF6B81) else Color.Transparent)
            .clickable { onSelect(s) }
        )
      }
    }
  }
}

@Composable
private fun TournamentSection(title: String, matches: List<MatchRow>) {
  Column(modifier = Modifier.fillMaxWidth()) {
    Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
      Text(text = title, color = Color.White, fontWeight = FontWeight.SemiBold, fontSize = 16.sp, modifier = Modifier.weight(1f))
      androidx.compose.material3.Icon(imageVector = Icons.Default.KeyboardArrowDown, contentDescription = "expand", tint = Color(0xFF94A3B8))
    }

    Spacer(modifier = Modifier.height(10.dp))

    matches.forEach { m ->
      MatchCard(match = m)
      Spacer(modifier = Modifier.height(12.dp))
    }
  }
}

@Composable
private fun MatchCard(match: MatchRow) {
  Card(
    modifier = Modifier
      .fillMaxWidth()
      .height(IntrinsicSize.Min),
    shape = RoundedCornerShape(16.dp),
    colors = CardDefaults.cardColors(containerColor = Color(0xFF0E161D)),
    border = BorderStroke(1.dp, Color.White.copy(alpha = 0.04f))
  ) {
    Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
      // Left teams column
      Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
          Box(
            modifier = Modifier
              .size(28.dp)
              .clip(RoundedCornerShape(6.dp))
              .background(Color(0xFF0B1220)),
            contentAlignment = Alignment.Center
          ) {
            Text(text = match.teamA.take(1), color = Color(0xFF9BE7FF), fontWeight = FontWeight.Bold)
          }
          Spacer(modifier = Modifier.width(8.dp))
          Text(text = match.teamA, color = Color.White, fontWeight = FontWeight.Bold)
          Spacer(modifier = Modifier.weight(1f))
          if (match.scoreA.isNotEmpty()) {
            Text(text = match.scoreA, color = Color.White, fontWeight = FontWeight.Bold)
            Spacer(modifier = Modifier.width(6.dp))
            Text(text = match.oversA, color = Color(0xFF94A3B8), fontSize = 12.sp)
          }
        }

        Row(verticalAlignment = Alignment.CenterVertically) {
          Box(
            modifier = Modifier
              .size(28.dp)
              .clip(RoundedCornerShape(6.dp))
              .background(Color(0xFF0B1220)),
            contentAlignment = Alignment.Center
          ) {
            Text(text = match.teamB.take(1), color = Color(0xFFFFC2D0), fontWeight = FontWeight.Bold)
          }
          Spacer(modifier = Modifier.width(8.dp))
          Text(text = match.teamB, color = Color.White, fontWeight = FontWeight.Bold)
          Spacer(modifier = Modifier.weight(1f))
          if (match.scoreB.isNotEmpty()) {
            Text(text = match.scoreB, color = Color.White, fontWeight = FontWeight.Bold)
            Spacer(modifier = Modifier.width(6.dp))
            Text(text = match.oversB, color = Color(0xFF94A3B8), fontSize = 12.sp)
          }
        }
      }

      // Divider
      Box(modifier = Modifier.width(1.dp).height(48.dp).background(Color.White.copy(alpha = 0.03f)))
      Spacer(modifier = Modifier.width(12.dp))

      // Right result area
      Column(horizontalAlignment = Alignment.End) {
        Text(text = match.resultTitle, color = Color(0xFFFF7A9A), fontWeight = FontWeight.Bold)
        Spacer(modifier = Modifier.height(6.dp))
        Text(text = match.resultSubtitle, color = Color(0xFF94A3B8))
      }
    }
  }
}

@Composable
internal fun LoginScreen(
  onSkip: () -> Unit,
  modifier: Modifier = Modifier,
  viewModel: MainScreenViewModel = viewModel { MainScreenViewModel(DefaultDataRepository()) },
) {
  val loginState by viewModel.loginUiState.collectAsStateWithLifecycle()
  val context = LocalContext.current

  LaunchedEffect(loginState.token) {
    val token = loginState.token
    if (!token.isNullOrBlank()) {
      com.example.thanna.data.TokenStore.saveToken(context, token)
    }
  }

  // ── Pure white canvas ──
  Box(
    modifier = modifier
      .fillMaxSize()
      .background(Color.White),
  ) {
    // Subtle top-right accent glow (MI Blue)
    Box(
      modifier = Modifier
        .size(320.dp)
        .offset(x = 140.dp, y = (-60).dp)
        .background(
          Brush.radialGradient(
            colors = listOf(
              MIBlue.copy(alpha = 0.07f),
              Color.Transparent
            ),
            radius = 500f
          )
        )
    )
    // Subtle bottom-left accent glow (Green)
    Box(
      modifier = Modifier
        .size(280.dp)
        .align(Alignment.BottomStart)
        .offset(x = (-80).dp, y = 60.dp)
        .background(
          Brush.radialGradient(
            colors = listOf(
              MIGreen.copy(alpha = 0.05f),
              Color.Transparent
            ),
            radius = 450f
          )
        )
    )

    Column(
      modifier = Modifier
        .fillMaxSize()
        .statusBarsPadding()
        .navigationBarsPadding()
    ) {
      // ── Top Bar: Skip button ──
      Box(
        modifier = Modifier
          .fillMaxWidth()
          .padding(top = 12.dp, end = 20.dp)
      ) {
        Box(
          modifier = Modifier
            .align(Alignment.CenterEnd)
            .clip(RoundedCornerShape(UnifiedCornerRadius))
            .background(MIBlue.copy(alpha = 0.06f))
            .clickable { onSkip() }
            .padding(horizontal = 18.dp, vertical = 8.dp)
        ) {
          Text(
            text = "Skip",
            color = MIBlue,
            fontWeight = FontWeight.SemiBold,
            fontSize = 14.sp
          )
        }
      }

      Spacer(modifier = Modifier.height(20.dp))

      // ── Brand Section ──
      Column(
        modifier = Modifier
          .fillMaxWidth()
          .padding(horizontal = 24.dp),
        horizontalAlignment = Alignment.CenterHorizontally
      ) {
        // Logo
        Image(
          painter = painterResource(id = com.example.thanna.R.drawable.haraan),
          contentDescription = "Haraan Logo",
          contentScale = ContentScale.Fit,
          modifier = Modifier
            .width(160.dp)
            .height(44.dp)
        )

        Spacer(modifier = Modifier.height(16.dp))

        // Illustration with soft circular backdrop
        Box(contentAlignment = Alignment.Center) {
          // Soft circle halo behind illustration
          Box(
            modifier = Modifier
              .size(170.dp)
              .background(
                brush = Brush.radialGradient(
                  colors = listOf(
                    MIBlue.copy(alpha = 0.08f),
                    MIGreen.copy(alpha = 0.04f),
                    Color.Transparent
                  )
                ),
                shape = RoundedCornerShape(999.dp)
              )
          )
          Image(
            painter = painterResource(id = com.example.thanna.R.drawable.going_out_illustration),
            contentDescription = "Going Out Illustration",
            contentScale = ContentScale.Fit,
            modifier = Modifier
              .size(140.dp)
              .clip(RoundedCornerShape(UnifiedCornerRadius))
          )
        }

        Spacer(modifier = Modifier.height(20.dp))

        // Headline
        Text(
          text = "For all your\ngoing out plans",
          color = Color.Black,
          fontSize = 26.sp,
          textAlign = TextAlign.Center,
          fontWeight = FontWeight.Black,
          lineHeight = 34.sp,
          modifier = Modifier.fillMaxWidth()
        )

        Spacer(modifier = Modifier.height(8.dp))

        // Two vibrant feature badges
        Row(
          modifier = Modifier.fillMaxWidth(),
          horizontalArrangement = Arrangement.Center,
          verticalAlignment = Alignment.CenterVertically
        ) {
          // Events badge (MI Blue)
          Box(
            modifier = Modifier
              .clip(RoundedCornerShape(UnifiedCornerRadius))
              .background(MIBlue.copy(alpha = 0.08f))
              .padding(horizontal = 14.dp, vertical = 6.dp)
          ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
              Box(
                modifier = Modifier
                  .size(8.dp)
                  .background(MIBlue, RoundedCornerShape(999.dp))
              )
              Spacer(modifier = Modifier.width(6.dp))
              Text(
                text = "Events",
                color = MIBlue,
                fontSize = 13.sp,
                fontWeight = FontWeight.Bold
              )
            }
          }

          Spacer(modifier = Modifier.width(10.dp))

          // GameHub badge (Green)
          Box(
            modifier = Modifier
              .clip(RoundedCornerShape(UnifiedCornerRadius))
              .background(MIGreen.copy(alpha = 0.08f))
              .padding(horizontal = 14.dp, vertical = 6.dp)
          ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
              Box(
                modifier = Modifier
                  .size(8.dp)
                  .background(MIGreen, RoundedCornerShape(999.dp))
              )
              Spacer(modifier = Modifier.width(6.dp))
              Text(
                text = "GameHub",
                color = Color(0xFF059669),
                fontSize = 13.sp,
                fontWeight = FontWeight.Bold
              )
            }
          }
        }
      }

      Spacer(modifier = Modifier.height(28.dp))

      // ── Login Form Card ──
      Column(
        modifier = Modifier
          .fillMaxWidth()
          .padding(horizontal = 24.dp),
        horizontalAlignment = Alignment.CenterHorizontally
      ) {
        // Divider with label
        Row(
          verticalAlignment = Alignment.CenterVertically,
          modifier = Modifier.fillMaxWidth()
        ) {
          Box(modifier = Modifier.weight(1f).height(1.dp).background(Color(0xFFE2E8F0)))
          Text(
            text = "  LOG IN OR SIGN UP  ",
            color = TextMuted,
            fontSize = 11.sp,
            fontWeight = FontWeight.Bold,
          )
          Box(modifier = Modifier.weight(1f).height(1.dp).background(Color(0xFFE2E8F0)))
        }

        Spacer(modifier = Modifier.height(20.dp))

        // Inner form card
        Card(
          modifier = Modifier.fillMaxWidth(),
          shape = RoundedCornerShape(UnifiedCornerRadius),
          colors = CardDefaults.cardColors(containerColor = Color(0xFFF8FAFC)),
          border = BorderStroke(1.dp, Color(0xFFE2E8F0)),
          elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
        ) {
          val isOtpStep = loginState.stage == LoginStage.VerifyOtp || loginState.stage == LoginStage.Success

          Column(modifier = Modifier.padding(20.dp)) {
            // Phone input
            PhoneInputField(
              phone = loginState.phone,
              onPhoneChange = viewModel::onPhoneChanged,
              modifier = Modifier.fillMaxWidth()
            )

            if (isOtpStep) {
              Spacer(modifier = Modifier.height(14.dp))
              AuthField(
                value = loginState.otp,
                onValueChange = viewModel::onOtpChanged,
                label = "Enter 6-digit OTP",
                modifier = Modifier.fillMaxWidth()
              )
            }

            // Error feedback
            if (!loginState.errorMessage.isNullOrEmpty()) {
              Spacer(modifier = Modifier.height(12.dp))
              Text(
                text = loginState.errorMessage!!,
                color = Color(0xFFDC2626),
                fontSize = 13.sp,
                fontWeight = FontWeight.Medium,
                modifier = Modifier.fillMaxWidth().padding(horizontal = 4.dp),
                textAlign = TextAlign.Start
              )
            }

            // Success feedback
            if (!loginState.successMessage.isNullOrEmpty()) {
              Spacer(modifier = Modifier.height(12.dp))
              Text(
                text = loginState.successMessage!!,
                color = Color(0xFF16A34A),
                fontSize = 13.sp,
                fontWeight = FontWeight.Medium,
                modifier = Modifier.fillMaxWidth().padding(horizontal = 4.dp),
                textAlign = TextAlign.Start
              )
            }

            Spacer(modifier = Modifier.height(18.dp))

            // CTA button
            ContinueButton(
              enabled = loginState.phone.filter { it.isDigit() }.length >= 10 && !loginState.isLoading,
              text = if (loginState.isLoading) "Please wait..." else if (isOtpStep) "Verify OTP" else "Continue",
              onClick = { if (isOtpStep) viewModel.verifyOtp() else viewModel.requestOtp() }
            )
          }
        }

        // Persist JWT when login succeeds
        val tokenSaved = remember { mutableStateOf(false) }
        if (loginState.stage == LoginStage.Success && !loginState.token.isNullOrBlank() && !tokenSaved.value) {
          com.example.thanna.data.TokenStore.saveToken(context, loginState.token!!)
          tokenSaved.value = true
        }

        Spacer(modifier = Modifier.height(20.dp))

        // OR divider
        Row(verticalAlignment = Alignment.CenterVertically) {
          Box(modifier = Modifier.weight(1f).height(1.dp).background(Color(0xFFE2E8F0)))
          Text(
            text = "  OR  ",
            color = TextMuted,
            fontSize = 12.sp,
            fontWeight = FontWeight.Bold
          )
          Box(modifier = Modifier.weight(1f).height(1.dp).background(Color(0xFFE2E8F0)))
        }

        Spacer(modifier = Modifier.height(16.dp))

        // Social login button
        SocialButton(
          modifier = Modifier
            .fillMaxWidth()
            .height(56.dp),
          text = "Login with Haraan",
          textColor = Color(0xFF0F172A),
          borderColor = Color(0xFFCBD5E1),
          iconResId = com.example.thanna.R.drawable.haraan,
          useGoogleG = false,
          containerColor = Color.White,
        )

        Spacer(modifier = Modifier.height(28.dp))

        // Terms
        Text(
          text = "By continuing, you agree to our Terms of Services & Privacy Policy",
          color = TextMuted,
          fontSize = 11.sp,
          textAlign = TextAlign.Center,
          lineHeight = 16.sp,
          modifier = Modifier.fillMaxWidth().padding(horizontal = 10.dp)
        )

        Spacer(modifier = Modifier.height(16.dp))
      }
    }
  }
}

@Composable
private fun CountryCodeChip(code: String) {
  Card(
    shape = RoundedCornerShape(999.dp),
    colors = CardDefaults.cardColors(containerColor = Color(0xFFF1F5F9)),
    border = BorderStroke(1.dp, Color(0xFFE2E8F0))
  ) {
    Row(
      modifier = Modifier.padding(horizontal = 14.dp, vertical = 12.dp),
      verticalAlignment = Alignment.CenterVertically
    ) {
      Text(text = "🇮🇳", fontSize = 16.sp)
      Spacer(modifier = Modifier.width(6.dp))
      Text(text = code, color = Color(0xFF0F172A), fontWeight = FontWeight.Bold, fontSize = 15.sp)
    }
  }
}

@Composable
private fun ContinueButton(enabled: Boolean, text: String, onClick: () -> Unit) {
  Box(
    modifier = Modifier
      .fillMaxWidth()
      .height(56.dp)
      .clip(RoundedCornerShape(UnifiedCornerRadius))
      .background(
        Brush.horizontalGradient(
          colors = if (enabled) listOf(MIBlue, MIGreen) else listOf(Color(0xFFE2E8F0), Color(0xFFE2E8F0))
        )
      )
      .clickable(enabled = enabled) { onClick() },
    contentAlignment = Alignment.Center,
  ) {
    Text(
      text = text,
      color = if (enabled) AccentWhite else Color(0xFF94A3B8),
      fontSize = 16.sp,
      fontWeight = FontWeight.Bold
    )
  }
}

@Composable
private fun PhoneInputField(
  phone: String,
  onPhoneChange: (String) -> Unit,
  modifier: Modifier = Modifier
) {
  var focused by remember { mutableStateOf(false) }
  
  Row(
    modifier = modifier
      .fillMaxWidth()
      .height(56.dp)
      .background(Color.White, RoundedCornerShape(UnifiedCornerRadius))
      .border(
        BorderStroke(
          width = if (focused) 2.dp else 1.dp,
          color = if (focused) MIBlue else Color(0xFFCBD5E1)
        ),
        shape = RoundedCornerShape(UnifiedCornerRadius)
      )
      .padding(horizontal = 18.dp),
    verticalAlignment = Alignment.CenterVertically
  ) {
    Text(text = "🇮🇳", fontSize = 16.sp)
    Spacer(modifier = Modifier.width(8.dp))
    Text(
      text = "+91",
      color = Color(0xFF0F172A),
      fontWeight = FontWeight.Bold,
      fontSize = 15.sp
    )
    
    Spacer(modifier = Modifier.width(12.dp))
    Box(
      modifier = Modifier
        .width(1.dp)
        .height(20.dp)
        .background(Color(0xFFE2E8F0))
    )
    Spacer(modifier = Modifier.width(12.dp))
    
    androidx.compose.foundation.text.BasicTextField(
      value = phone,
      onValueChange = onPhoneChange,
      modifier = Modifier
        .weight(1f)
        .onFocusChanged { focusState ->
          focused = focusState.isFocused
        },
      singleLine = true,
      textStyle = androidx.compose.ui.text.TextStyle(
        color = Color(0xFF0F172A),
        fontSize = 15.sp,
        fontWeight = FontWeight.Medium
      ),
      keyboardOptions = KeyboardOptions(
        keyboardType = KeyboardType.Phone
      ),
      cursorBrush = SolidColor(MIBlue),
      decorationBox = { innerTextField ->
        Box(contentAlignment = Alignment.CenterStart) {
          if (phone.isEmpty()) {
            Text(
              text = "10-digit mobile number",
              color = Color(0xFF94A3B8),
              fontSize = 15.sp,
              fontWeight = FontWeight.Normal
            )
          }
          innerTextField()
        }
      }
    )
  }
}

@Composable
private fun AuthField(
  value: String,
  onValueChange: (String) -> Unit,
  label: String,
  modifier: Modifier = Modifier,
) {
  var focused by remember { mutableStateOf(false) }
  
  Row(
    modifier = modifier
      .fillMaxWidth()
      .height(56.dp)
      .background(Color.White, RoundedCornerShape(UnifiedCornerRadius))
      .border(
        BorderStroke(
          width = if (focused) 2.dp else 1.dp,
          color = if (focused) MIBlue else Color(0xFFCBD5E1)
        ),
        shape = RoundedCornerShape(UnifiedCornerRadius)
      )
      .padding(horizontal = 18.dp),
    verticalAlignment = Alignment.CenterVertically
  ) {
    androidx.compose.foundation.text.BasicTextField(
      value = value,
      onValueChange = onValueChange,
      modifier = Modifier
        .fillMaxWidth()
        .onFocusChanged { focusState ->
          focused = focusState.isFocused
        },
      singleLine = true,
      textStyle = androidx.compose.ui.text.TextStyle(
        color = Color(0xFF0F172A),
        fontSize = 15.sp,
        fontWeight = FontWeight.Medium
      ),
      keyboardOptions = KeyboardOptions(
        keyboardType = KeyboardType.Number
      ),
      cursorBrush = SolidColor(MIBlue),
      decorationBox = { innerTextField ->
        Box(contentAlignment = Alignment.CenterStart) {
          if (value.isEmpty()) {
            Text(
              text = label,
              color = Color(0xFF94A3B8),
              fontSize = 15.sp,
              fontWeight = FontWeight.Normal
            )
          }
          innerTextField()
        }
      }
    )
  }
}

@Composable
private fun SocialButton(
  modifier: Modifier = Modifier,
  text: String,
  textColor: Color,
  borderColor: Color,
  iconTint: Color = Color.Unspecified,
  iconText: String? = null,
  iconResId: Int? = null,
  useGoogleG: Boolean = false,
  containerColor: Color = Color.White.copy(alpha = 0.08f),
) {
  Card(
    modifier = modifier
      .clip(RoundedCornerShape(UnifiedCornerRadius))
      .clickable { /* Handle click */ },
    shape = RoundedCornerShape(UnifiedCornerRadius),
    colors = CardDefaults.cardColors(containerColor = containerColor),
    border = BorderStroke(1.dp, borderColor),
    elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
  ) {
    Box(
      modifier = Modifier.fillMaxSize().padding(horizontal = 16.dp),
      contentAlignment = Alignment.Center
    ) {
      Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.Center
      ) {
        if (useGoogleG) {
          Box(
            modifier = Modifier
              .size(28.dp)
              .clip(RoundedCornerShape(6.dp))
              .background(MIBlue),
            contentAlignment = Alignment.Center
          ) {
            Text(text = "G", color = AccentWhite, fontWeight = FontWeight.Bold, fontSize = 14.sp)
          }
        } else if (iconResId != null) {
          Image(
            painter = painterResource(id = iconResId),
            contentDescription = "Social Icon",
            contentScale = ContentScale.Fit,
            modifier = Modifier
              .height(20.dp)
              .width(76.dp)
          )
        } else if (iconText != null) {
          Box(
            modifier = Modifier
              .size(28.dp)
              .clip(RoundedCornerShape(6.dp))
              .background(iconTint),
            contentAlignment = Alignment.Center
          ) {
            Text(text = iconText, color = AccentWhite, fontWeight = FontWeight.Bold, fontSize = 14.sp)
          }
        }
        Spacer(modifier = Modifier.width(12.dp))
        Text(text = text, color = textColor, fontSize = 15.sp, fontWeight = FontWeight.Bold)
      }
    }
  }
}

@Composable
private fun CustomBottomNav(
  selectedTab: Int,
  activeSubTab: String,
  onTabSelected: (Int) -> Unit
) {
  val isDark = false // Force light theme everywhere for clean aesthetic
  Card(
    modifier = Modifier
      .fillMaxWidth()
      .navigationBarsPadding()
      .padding(12.dp)
      .height(64.dp),
    shape = RoundedCornerShape(UnifiedCornerRadius),
    colors = CardDefaults.cardColors(
      containerColor = Color.White
    ),
    border = BorderStroke(1.dp, Color(0xFFE2E8F0)),
    elevation = CardDefaults.cardElevation(defaultElevation = 8.dp)
  ) {
    Row(
      modifier = Modifier.fillMaxSize(),
      horizontalArrangement = Arrangement.SpaceAround,
      verticalAlignment = Alignment.CenterVertically
    ) {
      val tabs = listOf(
        TabInfo("Explore", Icons.Default.Home),
        TabInfo("Leaderboard", Icons.Default.List)
      )
      
      tabs.forEachIndexed { index, tab ->
        val selected = index == selectedTab
        val accentColor = if (index == 0) {
          if (activeSubTab == "Events") {
            Color(0xFFFFC83B) // Gold for Events subtab in dark mode
          } else {
            MIGreen
          }
        } else {
          if (isDark) Color(0xFFFFC83B) else MIBlue
        }
        val bubbleColor = if (selected) accentColor.copy(alpha = 0.08f) else Color.Transparent
        val textColor = if (selected) accentColor else (if (isDark) Color.Gray else Color(0xFF94A3B8))
        val iconColor = if (selected) accentColor else (if (isDark) Color.Gray else Color(0xFF94A3B8))
        
        Box(
          modifier = Modifier
            .weight(1f)
            .padding(horizontal = 4.dp, vertical = 2.dp)
            .clip(RoundedCornerShape(UnifiedCornerRadius))
            .background(bubbleColor)
            .clickable { onTabSelected(index) }
            .padding(vertical = 6.dp),
          contentAlignment = Alignment.Center
        ) {
          Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
          ) {
            androidx.compose.material3.Icon(
              imageVector = tab.icon,
              contentDescription = tab.title,
              tint = iconColor,
              modifier = Modifier.size(22.dp)
            )
            Spacer(modifier = Modifier.height(2.dp))
            Text(
              text = tab.title,
              color = textColor,
              fontSize = 10.sp,
              fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium
            )
          }
        }
      }
    }
  }
}

private data class TabInfo(val title: String, val icon: androidx.compose.ui.graphics.vector.ImageVector)

@Composable
internal fun MainAppContainer(
  token: String,
  onItemClick: (NavKey) -> Unit,
  onLogout: () -> Unit
) {
  var selectedTab by remember { mutableStateOf(0) }
  var searchQuery by remember { mutableStateOf("") }
  var showLogoutDialog by remember { mutableStateOf(false) }
  var isSearchExpanded by remember { mutableStateOf(false) }
  var activeSubTab by remember { mutableStateOf("Events") }
  var showActionBoardDetail by remember { mutableStateOf(false) }

  if (showLogoutDialog) {
    androidx.compose.material3.AlertDialog(
      onDismissRequest = { showLogoutDialog = false },
      title = { Text("Logout", fontWeight = FontWeight.Bold) },
      text = { Text("Are you sure you want to log out of Haraan?") },
      confirmButton = {
        androidx.compose.material3.TextButton(
          onClick = {
            showLogoutDialog = false
            onLogout()
          }
        ) {
          Text("Logout", color = Color(0xFFDC2626), fontWeight = FontWeight.Bold)
        }
      },
      dismissButton = {
        androidx.compose.material3.TextButton(onClick = { showLogoutDialog = false }) {
          Text("Cancel", color = Color(0xFF64748B))
        }
      }
    )
  }

  val isEventsTab = (selectedTab == 0 && activeSubTab == "Events")
  val containerBackground = Brush.verticalGradient(
    colors = listOf(
      Color.White,
      Color(0xFFF8FAFC),
      Color.White
    )
  )

  Box(
    modifier = Modifier
      .fillMaxSize()
      .background(containerBackground)
  ) {
    if (isEventsTab) {
      // Atmospheric background light halos (soft vibrant glows)
      androidx.compose.foundation.Canvas(modifier = Modifier.fillMaxSize()) {
        drawCircle(
          color = MIBlue.copy(alpha = 0.05f),
          radius = 300.dp.toPx(),
          center = androidx.compose.ui.geometry.Offset(x = size.width * 0.8f, y = size.height * 0.15f)
        )
        drawCircle(
          color = MIGreen.copy(alpha = 0.04f),
          radius = 250.dp.toPx(),
          center = androidx.compose.ui.geometry.Offset(x = size.width * 0.1f, y = size.height * 0.4f)
        )
      }
    }
    if (showActionBoardDetail) {
      DistrictActionBoardScreen(
        onBack = { showActionBoardDetail = false },
        onMatchClick = { matchId -> onItemClick(com.example.thanna.MatchDetails(matchId)) }
      )
    } else {
      Column(modifier = Modifier.fillMaxSize()) {
      Column(
        modifier = Modifier
          .fillMaxWidth()
          .background(
            if (isEventsTab) {
              SolidColor(Color.Transparent)
            } else {
              Brush.horizontalGradient(
                colors = listOf(
                  Color(0xFFF8FAFC),
                  Color(0xFFEFF6FF),
                  Color(0xFFF5F3FF)
                )
              )
            }
          )
      ) {
        if (isEventsTab) {
          // Clean Minimal Header
          Column(
            modifier = Modifier
              .fillMaxWidth()
              .statusBarsPadding()
              .padding(horizontal = 16.dp, vertical = 8.dp)
          ) {
            Row(
              modifier = Modifier.fillMaxWidth(),
              verticalAlignment = Alignment.CenterVertically
            ) {
              // Location Pin Icon
              androidx.compose.material3.Icon(
                imageVector = Icons.Default.LocationOn,
                contentDescription = "Location",
                tint = Color(0xFF0F172A),
                modifier = Modifier.size(24.dp)
              )
              Spacer(modifier = Modifier.width(8.dp))
              
              // Location text column
              Column(
                modifier = Modifier.weight(1f)
              ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                  Text(
                    text = "Vaddeswaram",
                    color = Color(0xFF0F172A),
                    fontWeight = FontWeight.Bold,
                    fontSize = 16.sp
                  )
                  Spacer(modifier = Modifier.width(4.dp))
                  androidx.compose.material3.Icon(
                    imageVector = Icons.Default.KeyboardArrowDown,
                    contentDescription = "Select Location",
                    tint = Color(0xFF0F172A),
                    modifier = Modifier.size(16.dp)
                  )
                }
                Text(
                  text = "Kolanukonda",
                  color = Color.Gray,
                  fontSize = 12.sp
                )
              }
              
              // Bookmark outline button
              Box(
                modifier = Modifier
                  .size(40.dp)
                  .clip(RoundedCornerShape(UnifiedCornerRadius))
                  .background(Color(0xFFF1F5F9)) // Light gray background
                  .clickable { /* Bookmark action */ },
                contentAlignment = Alignment.Center
              ) {
                androidx.compose.material3.Icon(
                  imageVector = Icons.Default.BookmarkBorder,
                  contentDescription = "Bookmarks",
                  tint = Color(0xFF0F172A),
                  modifier = Modifier.size(20.dp)
                )
              }
              
              Spacer(modifier = Modifier.width(12.dp))
              
              // Profile avatar
              Box(
                modifier = Modifier
                  .size(40.dp)
                  .clip(RoundedCornerShape(UnifiedCornerRadius))
                  .background(Color(0xFFF1F5F9))
                  .clickable { showLogoutDialog = true },
                contentAlignment = Alignment.Center
              ) {
                androidx.compose.material3.Icon(
                  imageVector = Icons.Default.Person,
                  contentDescription = "Profile",
                  tint = Color(0xFF0F172A),
                  modifier = Modifier.size(20.dp)
                )
              }
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Search Bar (Redesigned: Clean minimal light theme)
            Row(
              modifier = Modifier
                .fillMaxWidth()
                .height(44.dp)
                .shadow(
                  elevation = 4.dp,
                  shape = RoundedCornerShape(UnifiedCornerRadius),
                  clip = false,
                  ambientColor = Color.Black.copy(alpha = 0.05f),
                  spotColor = Color.Black.copy(alpha = 0.1f)
                )
                .background(Color.White, RoundedCornerShape(UnifiedCornerRadius))
                .border(BorderStroke(1.dp, Color(0xFFE2E8F0)), RoundedCornerShape(UnifiedCornerRadius))
                .padding(horizontal = 14.dp),
              verticalAlignment = Alignment.CenterVertically
            ) {
              androidx.compose.material3.Icon(
                imageVector = Icons.Default.Search,
                contentDescription = "Search",
                tint = Color.Gray,
                modifier = Modifier.size(20.dp)
              )
              Spacer(modifier = Modifier.width(10.dp))
              androidx.compose.foundation.text.BasicTextField(
                value = searchQuery,
                onValueChange = { searchQuery = it },
                modifier = Modifier.fillMaxWidth(),
                singleLine = true,
                textStyle = androidx.compose.ui.text.TextStyle(
                  color = Color(0xFF0F172A),
                  fontSize = 14.sp
                ),
                cursorBrush = SolidColor(Color(0xFF0F172A)),
                decorationBox = { innerTextField ->
                  if (searchQuery.isEmpty()) {
                    Text(
                      text = "Search for 'Hokum'",
                      color = Color.Gray,
                      fontSize = 14.sp
                    )
                  }
                  innerTextField()
                }
              )
            }
          }
        } else {
          Row(
            modifier = Modifier
              .fillMaxWidth()
              .statusBarsPadding()
              .padding(horizontal = 16.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically
          ) {
            if (isSearchExpanded) {
              // Expanded search state
              Row(
                modifier = Modifier
                  .weight(1f)
                  .height(40.dp)
                  .background(Color(0xFFF1F5F9), RoundedCornerShape(UnifiedCornerRadius))
                  .border(BorderStroke(1.dp, Color(0xFFE2E8F0)), RoundedCornerShape(UnifiedCornerRadius))
                  .padding(horizontal = 12.dp),
                verticalAlignment = Alignment.CenterVertically
              ) {
                // Close / back icon
                androidx.compose.material3.Icon(
                  imageVector = Icons.Default.ArrowBack,
                  contentDescription = "Close Search",
                  tint = Color(0xFF64748B),
                  modifier = Modifier
                    .size(20.dp)
                    .clickable { 
                      isSearchExpanded = false 
                      searchQuery = "" // Reset query when closing
                    }
                )
                Spacer(modifier = Modifier.width(8.dp))
                androidx.compose.foundation.text.BasicTextField(
                  value = searchQuery,
                  onValueChange = { searchQuery = it },
                  modifier = Modifier.fillMaxWidth(),
                  singleLine = true,
                  textStyle = androidx.compose.ui.text.TextStyle(
                    color = Color(0xFF0F172A),
                    fontSize = 13.sp
                  ),
                  decorationBox = { innerTextField ->
                    if (searchQuery.isEmpty()) {
                      Text(
                        text = "Search events, venues...",
                        color = Color(0xFF94A3B8),
                        fontSize = 13.sp
                      )
                    }
                    innerTextField()
                  }
                )
              }
              
              Spacer(modifier = Modifier.width(12.dp))
              
              // Profile avatar
              Box(
                modifier = Modifier
                  .size(36.dp)
                  .clip(RoundedCornerShape(UnifiedCornerRadius))
                  .background(Color(0xFFE2E8F0))
                  .clickable { showLogoutDialog = true },
                contentAlignment = Alignment.Center
              ) {
                androidx.compose.material3.Icon(
                  imageVector = Icons.Default.Person,
                  contentDescription = "Profile",
                  tint = Color(0xFF64748B),
                  modifier = Modifier.size(20.dp)
                )
              }
            } else {
              // Normal state with location pill on left, logo in center, and search/profile icons on right
              // Left Column (Location pill)
              Column(
                modifier = Modifier.weight(1f),
                horizontalAlignment = Alignment.Start
              ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                  androidx.compose.material3.Icon(
                    imageVector = Icons.Default.LocationOn,
                    contentDescription = "Location",
                    tint = Color(0xFFE86830), // Accent orange
                    modifier = Modifier.size(16.dp)
                  )
                  Spacer(modifier = Modifier.width(4.dp))
                  Text(
                    text = "Mumbai",
                    color = Color(0xFF0F172A),
                    fontWeight = FontWeight.Bold,
                    fontSize = 14.sp
                  )
                }
                Text(
                  text = "India",
                  color = Color(0xFF64748B),
                  fontSize = 11.sp,
                  modifier = Modifier
                    .padding(start = 20.dp)
                    .offset(y = (-4).dp)
                )
              }

              // Center Column (Logo)
              Box(
                modifier = Modifier
                  .weight(1f)
                  .height(36.dp),
                contentAlignment = Alignment.Center
              ) {
                Image(
                  painter = painterResource(id = com.example.thanna.R.drawable.haraan),
                  contentDescription = "Haraan Logo",
                  modifier = Modifier.fillMaxHeight(),
                  contentScale = ContentScale.Fit
                )
              }

              // Right Column (Search & Profile actions Row)
              Row(
                modifier = Modifier.weight(1f),
                horizontalArrangement = Arrangement.End,
                verticalAlignment = Alignment.CenterVertically
              ) {
                // Search button
                Box(
                  modifier = Modifier
                    .size(36.dp)
                    .clip(RoundedCornerShape(UnifiedCornerRadius))
                    .background(Color(0xFFF1F5F9))
                    .clickable { isSearchExpanded = true },
                  contentAlignment = Alignment.Center
                ) {
                  androidx.compose.material3.Icon(
                    imageVector = Icons.Default.Search,
                    contentDescription = "Open Search",
                    tint = Color(0xFF64748B),
                    modifier = Modifier.size(20.dp)
                  )
                }

                Spacer(modifier = Modifier.width(12.dp))

                // Avatar/Logout trigger
                Box(
                  modifier = Modifier
                    .size(36.dp)
                    .clip(RoundedCornerShape(UnifiedCornerRadius))
                    .background(Color(0xFFE2E8F0))
                    .clickable { showLogoutDialog = true },
                  contentAlignment = Alignment.Center
                ) {
                  androidx.compose.material3.Icon(
                    imageVector = Icons.Default.Person,
                    contentDescription = "Profile",
                    tint = Color(0xFF64748B),
                    modifier = Modifier.size(20.dp)
                  )
                }
              }
            }
          }
        }

        // Switch button for Events and GameHub
        if (selectedTab == 0 && !isSearchExpanded) {
          Spacer(modifier = Modifier.height(8.dp)) // Added breathing space
          Row(
            modifier = Modifier
              .fillMaxWidth()
              .padding(start = 16.dp, end = 16.dp, bottom = 8.dp)
              .background(
                Color(0xFFF1F5F9), // Light gray slate background
                RoundedCornerShape(UnifiedCornerRadius)
              )
              .border(BorderStroke(1.dp, Color(0xFFE2E8F0)), RoundedCornerShape(UnifiedCornerRadius))
              .padding(4.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp)
          ) {
            // Events Switch Option
            Box(
              modifier = Modifier
                .weight(1f)
                .height(42.dp)
                .clip(RoundedCornerShape(UnifiedCornerRadius))
                .background(
                  if (activeSubTab == "Events") {
                    Color.White
                  } else Color.Transparent
                )
                .clickable { activeSubTab = "Events" },
              contentAlignment = Alignment.Center
            ) {
              Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                  text = "Events",
                  color = if (activeSubTab == "Events") Color(0xFF0F172A) else Color(0xFF64748B),
                  fontWeight = if (activeSubTab == "Events") FontWeight.SemiBold else FontWeight.Medium,
                  fontSize = 13.sp,
                  letterSpacing = 0.2.sp
                )
              }
            }

            // GameHub Switch Option
            Box(
              modifier = Modifier
                .weight(1f)
                .height(42.dp)
                .clip(RoundedCornerShape(UnifiedCornerRadius))
                .background(
                  if (activeSubTab == "GameHub") {
                    Color.White
                  } else Color.Transparent
                )
                .clickable { activeSubTab = "GameHub" },
              contentAlignment = Alignment.Center
            ) {
              Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                  text = "GameHub",
                  color = if (activeSubTab == "GameHub") Color(0xFF0F172A) else Color(0xFF64748B),
                  fontWeight = if (activeSubTab == "GameHub") FontWeight.SemiBold else FontWeight.Medium,
                  fontSize = 13.sp,
                  letterSpacing = 0.2.sp
                )
              }
            }
          }
        }
      }

      // Screen switcher
      Box(
        modifier = Modifier
          .weight(1f)
          .fillMaxWidth()
      ) {
        when (selectedTab) {
          0 -> {
            if (activeSubTab == "Events") {
              EventsTabScreen(
                searchQuery = searchQuery,
                onEventClick = { event ->
                  onItemClick(
                    EventDetail(
                      id = event.id,
                      title = event.title,
                      date = event.date,
                      venue = event.venue,
                      price = event.price,
                      category = event.category,
                      imageUrl = event.imageUrl
                    )
                  )
                }
              )
            } else {
              GameHubTabScreen(
                searchQuery = searchQuery,
                onActionBoardClick = { showActionBoardDetail = true }
              )
            }
          }
          1 -> LeaderboardTabScreen()
        }
      }

      // Floating bottom navigation
      CustomBottomNav(
        selectedTab = selectedTab,
        activeSubTab = activeSubTab,
        onTabSelected = { selectedTab = it }
      )
      }
    }
  }
}



@Composable
private fun EventsTabScreen(
  searchQuery: String,
  onEventClick: (EventItem) -> Unit
) {
  var selectedCategory by remember { mutableStateOf("Events") }
  
  val bannerEvents = listOf(
    BannerItem(
      "1",
      "An Epic Evening A Global Debut",
      "one8 GLOBAL PREMIERE",
      "21st June 2026",
      "Know more >",
      "https://images.unsplash.com/photo-1516280440614-37939bbacd81?w=600&q=80"
    ),
    BannerItem(
      "2",
      "Sunidhi Chauhan Live in Concert",
      "Mumbai Football Ground",
      "Sat, 28 Jun • 6:30 PM",
      "Book Tickets",
      "https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=600&q=80"
    ),
    BannerItem(
      "3",
      "Dua Lipa Live in BKC",
      "MMRDA Grounds, Mumbai",
      "Fri, 10 Jul • 7:00 PM",
      "Book Tickets",
      "https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=600&q=80"
    )
  )

  val spotlightItems = listOf(
    SpotlightItem(
      "1",
      "Blast",
      "An action-packed blockbuster featuring stellar performances.",
      "Now in theatres",
      "https://images.unsplash.com/photo-1536440136628-849c177e76a1?w=500&q=80"
    ),
    SpotlightItem(
      "2",
      "Obsession",
      "The word-of-mouth sensation horror fans are discovering",
      "Now in theatres",
      "https://images.unsplash.com/photo-1509248961158-e54f6934749c?w=500&q=80"
    ),
    SpotlightItem(
      "3",
      "Kattalan",
      "A thrilling suspense drama that keeps you on the edge of your seat.",
      "Now in theatres",
      "https://images.unsplash.com/photo-1505686994434-e3cc5abf1330?w=500&q=80"
    ),
    SpotlightItem(
      "4",
      "District L",
      "An immersive futuristic sci-fi adventure exploring new worlds.",
      "Now in theatres",
      "https://images.unsplash.com/photo-1478760329108-5c3ed9d495a0?w=500&q=80"
    )
  )
  
  val eventsData = listOf(
    EventItem("1", "Arijit Singh Live", "Sat, 14 Jun, 6:00 PM", "DY Patil Stadium", "₹999 onwards", "Concerts", "https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=500&q=80"),
    EventItem("2", "Premium Standup Comedy", "Sun, 15 Jun, 8:00 PM", "Habitat Mumbai", "₹499 onwards", "Comedy", "https://images.unsplash.com/photo-1527224857830-43a7acc85260?w=500&q=80"),
    EventItem("3", "Electronic Music Festival", "Fri, 20 Jun, 9:00 PM", "Nesco Center", "₹1499 onwards", "Nightlife", "https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=500&q=80"),
    EventItem("4", "Pottery & Clay Workshop", "Sat, 21 Jun, 11:00 AM", "Art Circle Bandra", "₹799 onwards", "Workshops", "https://images.unsplash.com/photo-1513364776144-60967b0f800f?w=500&q=80"),
    EventItem("5", "Mumbai Food & Vibe Festival", "Sun, 22 Jun, 12:00 PM", "Jio Gardens", "₹200 onwards", "Festivals", "https://images.unsplash.com/photo-1472653431158-6364773b2a56?w=500&q=80"),
  )
  
  val filteredEvents = eventsData.filter {
    (searchQuery.isEmpty() || it.title.contains(searchQuery, ignoreCase = true) || it.venue.contains(searchQuery, ignoreCase = true))
  }

  androidx.compose.foundation.lazy.LazyColumn(
    modifier = Modifier
      .fillMaxSize()
      .background(Color(0xFFF6F8FB)),
    verticalArrangement = Arrangement.spacedBy(20.dp)
  ) {
    // Abstract Background Shapes Layer (Cinematic backdrop)
    item {
      Box(
        modifier = Modifier
          .fillMaxWidth()
          .height(1.dp)
      ) {
        Canvas(modifier = Modifier.fillMaxSize()) {
          drawCircle(
            color = Color(0xFF2563EB).copy(alpha = 0.08f),
            radius = 200.dp.toPx(),
            center = androidx.compose.ui.geometry.Offset(x = size.width * 0.1f, y = -100f)
          )
          drawCircle(
            color = Color(0xFF00C853).copy(alpha = 0.06f),
            radius = 150.dp.toPx(),
            center = androidx.compose.ui.geometry.Offset(x = size.width * 0.9f, y = -80f)
          )
        }
      }
    }

    // Premium Hero Banner Carousel (HorizontalPager)
    item {
      val pagerState = rememberPagerState(pageCount = { bannerEvents.size })
      
      Column(modifier = Modifier.fillMaxWidth()) {
        HorizontalPager(
          state = pagerState,
          modifier = Modifier
            .fillMaxWidth()
            .height(280.dp),
          contentPadding = PaddingValues(horizontal = 20.dp),
          pageSpacing = 16.dp
        ) { page ->
          val banner = bannerEvents[page]
          Box(
            modifier = Modifier
              .fillMaxWidth()
              .height(280.dp)
          ) {
            // Cinematic Blurred Background Circles
            Canvas(
              modifier = Modifier
                .fillMaxWidth()
                .height(260.dp)
                .align(Alignment.TopCenter)
            ) {
              drawCircle(
                color = Color(0xFF2563EB).copy(alpha = 0.12f),
                radius = 180.dp.toPx(),
                center = androidx.compose.ui.geometry.Offset(x = size.width * 0.2f, y = size.height * 0.8f)
              )
              drawCircle(
                color = Color(0xFF00C853).copy(alpha = 0.08f),
                radius = 140.dp.toPx(),
                center = androidx.compose.ui.geometry.Offset(x = size.width * 0.8f, y = size.height * 0.4f)
              )
            }

            // Main Glassmorphic Card
            Card(
              modifier = Modifier
                .fillMaxWidth()
                .height(260.dp)
                .align(Alignment.TopCenter),
              shape = RoundedCornerShape(34.dp),
              colors = CardDefaults.cardColors(containerColor = Color.White.copy(alpha = 0.9f)),
              border = BorderStroke(1.dp, Color.White.copy(alpha = 0.5f)),
              elevation = CardDefaults.cardElevation(defaultElevation = 12.dp)
            ) {
              Box(modifier = Modifier.fillMaxSize()) {
                // Background with soft gradient
                Box(
                  modifier = Modifier
                    .fillMaxSize()
                    .background(
                      brush = Brush.verticalGradient(
                        colors = listOf(
                          Color.White,
                          Color(0xFFF6F8FB)
                        )
                      )
                    )
                )

                // Content Row: Left Info + Right Image
                Row(
                  modifier = Modifier
                    .fillMaxSize()
                    .padding(24.dp),
                  horizontalArrangement = Arrangement.spacedBy(20.dp)
                ) {
                  // Left Panel: Event Info with Glassmorphic Background
                  Column(
                    modifier = Modifier
                      .weight(0.6f)
                      .fillMaxHeight(),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                  ) {
                    // Event Category Badge
                    Box(
                      modifier = Modifier
                        .background(
                          Color.White.copy(alpha = 0.3f),
                          RoundedCornerShape(12.dp)
                        )
                        .border(
                          1.dp,
                          Color.White.copy(alpha = 0.5f),
                          RoundedCornerShape(12.dp)
                        )
                        .padding(horizontal = 12.dp, vertical = 6.dp)
                    ) {
                      Text(
                        text = banner.meta.substring(0, minOf(15, banner.meta.length)),
                        color = LightAccentBlue,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold
                      )
                    }

                    // Event Title
                    Text(
                      text = banner.title,
                      color = LightPrimaryText,
                      fontWeight = FontWeight.Bold,
                      fontSize = 22.sp,
                      lineHeight = 28.sp,
                      maxLines = 3
                    )

                    // Event Details
                    Row(
                      modifier = Modifier.fillMaxWidth(),
                      verticalAlignment = Alignment.CenterVertically,
                      horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                      Icon(
                        imageVector = Icons.Default.DateRange,
                        contentDescription = null,
                        tint = LightAccentBlue,
                        modifier = Modifier.size(16.dp)
                      )
                      Text(
                        text = banner.price,
                        color = LightSecondaryText,
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Medium
                      )
                    }

                    Spacer(modifier = Modifier.weight(1f))

                    // CTA Button
                    Button(
                      onClick = { /* TODO */ },
                      modifier = Modifier
                        .height(44.dp)
                        .fillMaxWidth(),
                      shape = RoundedCornerShape(22.dp),
                      colors = ButtonDefaults.buttonColors(
                        containerColor = LightAccentBlue,
                        contentColor = Color.White
                      )
                    ) {
                      Text(
                        text = "Book Now",
                        fontWeight = FontWeight.SemiBold,
                        fontSize = 14.sp,
                        letterSpacing = 0.3.sp
                      )
                    }
                  }

                  // Right Panel: Event Image
                  Box(
                    modifier = Modifier
                      .weight(0.4f)
                      .fillMaxHeight()
                      .clip(RoundedCornerShape(24.dp))
                      .background(Color(0xFFF1F5F9))
                  ) {
                    AsyncImage(
                      model = banner.imageUrl,
                      contentDescription = banner.title,
                      contentScale = ContentScale.Crop,
                      modifier = Modifier.fillMaxSize()
                    )
                  }
                }
              }
            }
          }
        }
        
        // Premium Pager Dots
        Row(
          horizontalArrangement = Arrangement.Center,
          modifier = Modifier
            .fillMaxWidth()
            .padding(top = 16.dp),
          verticalAlignment = Alignment.CenterVertically
        ) {
          repeat(bannerEvents.size) { index ->
            val selected = pagerState.currentPage == index
            Box(
              modifier = Modifier
                .padding(horizontal = 4.dp)
                .width(if (selected) 24.dp else 8.dp)
                .height(8.dp)
                .clip(RoundedCornerShape(4.dp))
                .background(if (selected) LightAccentBlue else Color(0xFFE2E8F0))
            )
          }
        }
      }
    }

    // Category Buttons Row (Dining, Movies, Events)
    item {
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .padding(horizontal = 12.dp)
          .padding(vertical = 4.dp),
        horizontalArrangement = Arrangement.spacedBy(10.dp)
      ) {
        CustomCategoryCard(
          title = "Dining",
          icon = Icons.Default.Restaurant,
          iconColor = Color(0xFFFF5B7F),
          glowColor = Color(0xFFFF5B7F),
          selected = selectedCategory == "Dining",
          onClick = { selectedCategory = "Dining" },
          modifier = Modifier.weight(1f)
        )
        CustomCategoryCard(
          title = "Movies",
          icon = Icons.Default.Movie,
          iconColor = Color(0xFF3F7FFF),
          glowColor = Color(0xFF3F7FFF),
          selected = selectedCategory == "Movies",
          onClick = { selectedCategory = "Movies" },
          modifier = Modifier.weight(1f)
        )
        CustomCategoryCard(
          title = "Events",
          icon = Icons.Default.Mic,
          iconColor = Color(0xFFFFC83B),
          glowColor = Color(0xFFFFC83B),
          selected = selectedCategory == "Events",
          onClick = { selectedCategory = "Events" },
          modifier = Modifier.weight(1f)
        )
      }
    }

    if (selectedCategory == "Events") {
      // "In the spotlight" title
      item {
        Text(
          text = "In the spotlight",
          color = Color(0xFF0F172A),
          fontWeight = FontWeight.Bold,
          fontSize = 18.sp,
          modifier = Modifier.padding(horizontal = 12.dp).padding(top = 8.dp)
        )
      }

      // Horizontal spotlight list
      item {
        val virtualPageCount = 10000
        val initialPage = (virtualPageCount / 2) - ((virtualPageCount / 2) % spotlightItems.size)
        val spotlightState = rememberPagerState(
          initialPage = initialPage,
          pageCount = { virtualPageCount }
        )
        
        // Auto scroll loop
        LaunchedEffect(spotlightState) {
          while (true) {
            kotlinx.coroutines.delay(3000) // auto scroll every 3 seconds
            if (!spotlightState.isScrollInProgress) {
              val nextPage = spotlightState.currentPage + 1
              spotlightState.animateScrollToPage(nextPage)
            }
          }
        }

        Column(modifier = Modifier.fillMaxWidth()) {
          HorizontalPager(
            state = spotlightState,
            contentPadding = PaddingValues(horizontal = 70.dp),
            pageSpacing = 16.dp,
            modifier = Modifier
              .fillMaxWidth()
              .height(400.dp)
          ) { virtualPage ->
            val pageIndex = virtualPage % spotlightItems.size
            val item = spotlightItems[pageIndex]
            
            // Calculate float page offset from the current page
            val pageOffset = kotlin.math.abs((spotlightState.currentPage - virtualPage) + spotlightState.currentPageOffsetFraction)
            
            // Scale and alpha factor calculations for adjacent pages
            val scale = 0.85f + 0.15f * (1f - pageOffset.coerceIn(0f, 1f))
            val posterAlpha = 0.6f + 0.4f * (1f - pageOffset.coerceIn(0f, 1f))
            val textAlpha = (1f - pageOffset * 2.5f).coerceIn(0f, 1f)

            Column(
              modifier = Modifier
                .fillMaxWidth(),
              horizontalAlignment = Alignment.CenterHorizontally
            ) {
              Box(
                modifier = Modifier
                  .width(220.dp)
                  .aspectRatio(1f / 1.414f)
                  .graphicsLayer {
                    scaleX = scale
                    scaleY = scale
                    alpha = posterAlpha
                  }
                  .clip(RoundedCornerShape(UnifiedCornerRadius))
                  .background(Color(0xFF1E1E20))
              ) {
                AsyncImage(
                  model = item.imageUrl,
                  contentDescription = item.title,
                  contentScale = ContentScale.Crop,
                  modifier = Modifier.fillMaxSize()
                )
                
                // Top-left badge: "Now in theatres"
                Box(
                  modifier = Modifier
                    .padding(12.dp)
                    .background(Color.Black.copy(alpha = 0.6f), RoundedCornerShape(UnifiedCornerRadius))
                    .padding(horizontal = 10.dp, vertical = 5.dp)
                    .align(Alignment.TopStart)
                ) {
                  Text(
                    text = item.badge,
                    color = Color.White,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold
                  )
                }
                
                // Top-right bookmark button
                Box(
                  modifier = Modifier
                    .padding(12.dp)
                    .size(36.dp)
                    .clip(RoundedCornerShape(UnifiedCornerRadius))
                    .background(Color.Black.copy(alpha = 0.6f))
                    .clickable { /* Toggle bookmark */ }
                    .align(Alignment.TopEnd),
                  contentAlignment = Alignment.Center
                ) {
                  androidx.compose.material3.Icon(
                    imageVector = Icons.Default.BookmarkBorder,
                    contentDescription = "Save",
                    tint = Color.White,
                    modifier = Modifier.size(18.dp)
                  )
                }
              }
              
              Spacer(modifier = Modifier.height(10.dp))
              
              // Content (Centered title and description) - faded for inactive items
              Column(
                modifier = Modifier
                  .fillMaxWidth()
                  .padding(horizontal = 4.dp)
                  .graphicsLayer {
                    alpha = textAlpha
                  },
                horizontalAlignment = Alignment.CenterHorizontally
              ) {
                Text(
                  text = item.title,
                  color = Color(0xFF0F172A),
                  fontWeight = FontWeight.Bold,
                  fontSize = 16.sp,
                  textAlign = TextAlign.Center,
                  modifier = Modifier.fillMaxWidth()
                )
                Text(
                  text = item.description,
                  color = Color.Gray,
                  fontSize = 12.sp,
                  maxLines = 2,
                  textAlign = TextAlign.Center,
                  modifier = Modifier.fillMaxWidth().padding(top = 2.dp)
                )
              }
            }
          }
          
          // Pager indicators (White for selected, DarkGray for unselected)
          Row(
            horizontalArrangement = Arrangement.Center,
            modifier = Modifier
              .fillMaxWidth()
              .padding(top = 12.dp),
            verticalAlignment = Alignment.CenterVertically
          ) {
            repeat(spotlightItems.size) { index ->
              val selected = (spotlightState.currentPage % spotlightItems.size) == index
              Box(
                modifier = Modifier
                  .padding(horizontal = 3.dp)
                  .width(if (selected) 16.dp else 6.dp)
                  .height(6.dp)
                  .clip(RoundedCornerShape(3.dp))
                  .background(if (selected) Color(0xFF0F172A) else Color(0xFFE2E8F0))
              )
            }
          }
        }
      }

      // "Offers for you" title
      item {
        Text(
          text = "Offers for you",
          color = Color(0xFF0F172A),
          fontWeight = FontWeight.Bold,
          fontSize = 18.sp,
          modifier = Modifier.padding(horizontal = 12.dp).padding(top = 8.dp)
        )
      }

      // Offers card
      item {
        Card(
          modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp)
            .height(130.dp),
            shape = RoundedCornerShape(UnifiedCornerRadius),
          colors = CardDefaults.cardColors(containerColor = Color.Transparent)
        ) {
          Box(
            modifier = Modifier
              .fillMaxSize()
              .background(
                Brush.horizontalGradient(
                  colors = listOf(
                    Color(0xFF800020), // Dark Burgundy
                    Color(0xFF4A0E17)  // Deeper crimson/burgundy
                  )
                )
              )
              .padding(16.dp)
          ) {
            Column(
              modifier = Modifier.fillMaxHeight(),
              verticalArrangement = Arrangement.SpaceBetween
            ) {
              Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
              ) {
                Text(
                  text = "HSBC",
                  color = Color.White,
                  fontWeight = FontWeight.ExtraBold,
                  fontSize = 18.sp
                )
                Text(
                  text = "CREDIT CARDS",
                  color = Color.White.copy(alpha = 0.7f),
                  fontWeight = FontWeight.Bold,
                  fontSize = 10.sp
                )
              }
              
              Column {
                Text(
                  text = "Save up to ₹250 on bookings",
                  color = Color.White,
                  fontWeight = FontWeight.Bold,
                  fontSize = 15.sp
                )
                Text(
                  text = "Using HSBC credit cards. Valid till 30 Jun.",
                  color = Color.White.copy(alpha = 0.7f),
                  fontSize = 11.sp
                )
              }
            }
          }
        }
      }

      // Handpicked / Title section
      item {
        Column(modifier = Modifier.padding(horizontal = 12.dp).padding(vertical = 4.dp)) {
          Text(
            text = "Handpicked experiences",
            color = Color.Gray,
            fontWeight = FontWeight.Bold,
            fontSize = 11.sp,
            letterSpacing = 1.sp
          )
          Text(
            text = "Trending events in Mumbai",
            color = Color(0xFF0F172A), // Dark slate for light mode
            fontWeight = FontWeight.ExtraBold,
            fontSize = 18.sp,
            modifier = Modifier.padding(top = 2.dp)
          )
        }
      }

      // Events listings
      if (filteredEvents.isEmpty()) {
        item {
          Box(
            modifier = Modifier
              .fillMaxWidth()
              .padding(vertical = 40.dp),
            contentAlignment = Alignment.Center
          ) {
            Text(text = "No events found matching filters.", color = Color.Gray)
          }
        }
      } else {
        val eventChunks = filteredEvents.chunked(2)
        items(eventChunks.size) { i ->
          val chunk = eventChunks[i]
          Row(
            modifier = Modifier.fillMaxWidth().padding(horizontal = 12.dp),
            horizontalArrangement = Arrangement.spacedBy(10.dp)
          ) {
            EventListCard(
              event = chunk[0],
              modifier = Modifier.weight(1f),
              onClick = { onEventClick(chunk[0]) }
            )
            if (chunk.size > 1) {
              EventListCard(
                event = chunk[1],
                modifier = Modifier.weight(1f),
                onClick = { onEventClick(chunk[1]) }
              )
            } else {
              Spacer(modifier = Modifier.weight(1f))
            }
          }
        }
      }
    } else {
      // Placeholder for Dining or Movies tabs
      item {
        Box(
          modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 80.dp),
          contentAlignment = Alignment.Center
        ) {
          Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Text(
              text = "No $selectedCategory found matching filters.",
              color = Color.Gray,
              fontSize = 15.sp,
              fontWeight = FontWeight.Medium
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
              text = "Explore the Events tab for trending experiences.",
              color = Color.DarkGray,
              fontSize = 13.sp
            )
          }
        }
      }
    }
    
    item {
      Spacer(modifier = Modifier.height(16.dp))
    }
  }
}

private data class BannerItem(
  val id: String,
  val title: String,
  val venue: String,
  val meta: String,
  val price: String,
  val imageUrl: String
)

private data class SpotlightItem(
  val id: String,
  val title: String,
  val description: String,
  val badge: String,
  val imageUrl: String
)

@Composable
private fun CustomCategoryCard(
  title: String,
  icon: androidx.compose.ui.graphics.vector.ImageVector,
  iconColor: Color,
  glowColor: Color,
  selected: Boolean,
  onClick: () -> Unit,
  modifier: Modifier = Modifier
) {
  Card(
    modifier = modifier
      .height(100.dp)
      .clickable { onClick() },
    shape = RoundedCornerShape(UnifiedCornerRadius),
    colors = CardDefaults.cardColors(
      containerColor = if (selected) Color.White else Color(0xFFF1F5F9) // Light gray for unselected
    ),
    border = BorderStroke(
      width = if (selected) 2.dp else 1.dp,
      color = if (selected) MIBlue else Color(0xFFE2E8F0)
    ),
    elevation = CardDefaults.cardElevation(defaultElevation = if (selected) 4.dp else 0.dp)
  ) {
    Box(
      modifier = Modifier
        .fillMaxSize()
        .padding(8.dp),
      contentAlignment = Alignment.Center
    ) {
      Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
      ) {
        // Simple minimal backdrop behind icon (No glow)
        Box(
          modifier = Modifier
            .size(44.dp)
            .background(
              if (selected) MIBlue.copy(alpha = 0.1f) else Color.Transparent,
              RoundedCornerShape(UnifiedCornerRadius)
            ),
          contentAlignment = Alignment.Center
        ) {
          androidx.compose.material3.Icon(
            imageVector = icon,
            contentDescription = title,
            tint = if (selected) MIBlue else Color(0xFF64748B),
            modifier = Modifier.size(26.dp)
          )
        }
        
        Spacer(modifier = Modifier.height(8.dp))
        
        Text(
          text = title,
          color = if (selected) Color(0xFF0F172A) else Color(0xFF64748B),
          fontSize = 13.sp,
          fontWeight = FontWeight.Bold,
          textAlign = TextAlign.Center
        )
      }
    }
  }
}

private data class EventItem(
  val id: String,
  val title: String,
  val date: String,
  val venue: String,
  val price: String,
  val category: String,
  val imageUrl: String
)

@Composable
private fun EventListCard(
  event: EventItem,
  modifier: Modifier = Modifier,
  onClick: () -> Unit
) {
  Column(
    modifier = modifier
      .fillMaxWidth()
      .clickable { onClick() }
  ) {
    Box(
      modifier = Modifier
        .fillMaxWidth()
        .aspectRatio(0.75f) // 3:4 aspect ratio
        .clip(RoundedCornerShape(UnifiedCornerRadius))
        .background(Color(0xFFF1F5F9)) // Light gray slate
        .border(BorderStroke(1.dp, Color(0xFFE2E8F0)), RoundedCornerShape(UnifiedCornerRadius))
    ) {
      AsyncImage(
        model = event.imageUrl,
        contentDescription = event.title,
        contentScale = ContentScale.Crop,
        modifier = Modifier.fillMaxSize()
      )
    }
    
    Column(
      modifier = Modifier
        .fillMaxWidth()
        .padding(horizontal = 4.dp, vertical = 6.dp)
    ) {
      Text(
        text = event.date,
        color = Color.Gray,
        fontWeight = FontWeight.Medium,
        fontSize = 11.sp
      )
      
      Text(
        text = event.title,
        color = Color(0xFF0F172A),
        fontWeight = FontWeight.Bold,
        fontSize = 13.sp,
        maxLines = 2,
        lineHeight = 16.sp,
        modifier = Modifier.padding(top = 2.dp, bottom = 2.dp)
      )
      
      Text(
        text = event.venue,
        color = Color.Gray,
        fontSize = 11.sp,
        maxLines = 1
      )
      
      Text(
        text = event.price,
        color = Color(0xFF0F172A), // Dark slate for price in light mode
        fontWeight = FontWeight.Bold,
        fontSize = 12.sp,
        modifier = Modifier.padding(top = 2.dp)
      )
    }
  }
}

@Composable
fun EventDetailScreen(
  event: EventDetail,
  onBack: () -> Unit
) {
  val context = LocalContext.current
  var isSaved by remember { mutableStateOf(false) }
  val detailPoints = listOf(
    "Instant ticket confirmation",
    "Mobile entry and secure checkout",
    "Curated venue experience"
  )
  val inclusions = listOf(
    "Live performance access",
    "Dedicated seating / standing zone",
    "Entry support on event day"
  )
  val eventFacts = listOf(
    "Venue" to event.venue,
    "Date" to event.date,
    "Category" to event.category,
    "Price" to event.price,
  )
  val aboutText = when (event.category.lowercase()) {
    "concerts" -> "A high-energy live concert experience with premium sound, easy entry, and a crowd-ready atmosphere built for fans who want the full show-night feel."
    "comedy" -> "A polished night of stand-up with comfortable seating, smooth entry, and a relaxed venue setup designed for easy laughs and a great view."
    "nightlife" -> "A vibrant evening event with immersive lighting, strong production value, and a venue flow that keeps the experience moving from arrival to encore."
    "festivals" -> "A large-format festival experience with multiple highlights, lively venue energy, and a seamless event flow built for discovery and celebration."
    "workshops" -> "A hands-on experience with structured sessions, guided participation, and a comfortable venue environment that keeps the focus on learning and creating."
    else -> "A premium event experience designed around smooth entry, clear venue information, and a booking flow that feels easy from start to finish."
  }

  Box(
    modifier = Modifier
      .fillMaxSize()
      .background(
        Brush.verticalGradient(
          colors = listOf(
            Color(0xFFF8FAFC),
            Color.White,
            Color(0xFFF1F5F9)
          )
        )
      )
  ) {
    androidx.compose.foundation.lazy.LazyColumn(
      modifier = Modifier.fillMaxSize(),
      contentPadding = PaddingValues(bottom = 104.dp)
    ) {
      item {
        Box(
          modifier = Modifier
            .fillMaxWidth()
            .height(390.dp)
        ) {
          AsyncImage(
            model = event.imageUrl,
            contentDescription = event.title,
            contentScale = ContentScale.Crop,
            modifier = Modifier.fillMaxSize()
          )

          Box(
            modifier = Modifier
              .align(Alignment.TopEnd)
              .statusBarsPadding()
              .padding(top = 12.dp, end = 16.dp)
              .clip(RoundedCornerShape(999.dp))
              .background(Color.White.copy(alpha = 0.14f))
              .padding(horizontal = 12.dp, vertical = 8.dp)
          ) {
            Text(
              text = "Live on ${event.date}",
              color = Color.White,
              fontSize = 11.sp,
              fontWeight = FontWeight.SemiBold
            )
          }

          Box(
            modifier = Modifier
              .fillMaxSize()
              .background(
                Brush.verticalGradient(
                  colors = listOf(
                    Color.Transparent,
                    Color(0xAA0F172A)
                  )
                )
              )
          )

          Row(
            modifier = Modifier
              .fillMaxWidth()
              .statusBarsPadding()
              .padding(horizontal = 8.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
          ) {
            androidx.compose.material3.IconButton(onClick = onBack) {
              androidx.compose.material3.Icon(
                imageVector = Icons.Default.ArrowBack,
                contentDescription = "Back",
                tint = Color.White
              )
            }

            androidx.compose.material3.IconButton(onClick = {
              isSaved = !isSaved
            }) {
              androidx.compose.material3.Icon(
                imageVector = if (isSaved) Icons.Default.Bookmark else Icons.Default.BookmarkBorder,
                contentDescription = if (isSaved) "Remove save" else "Save event",
                tint = Color.White
              )
            }

            Box(
              modifier = Modifier
                .clip(RoundedCornerShape(999.dp))
                .background(Color.White.copy(alpha = 0.16f))
                .padding(horizontal = 14.dp, vertical = 8.dp)
            ) {
              Text(
                text = event.category,
                color = Color.White,
                fontSize = 12.sp,
                fontWeight = FontWeight.Medium
              )
            }
          }

          Column(
            modifier = Modifier
              .align(Alignment.BottomStart)
              .padding(16.dp)
          ) {
            Text(
              text = event.title,
              color = Color.White,
              fontSize = 30.sp,
              fontWeight = FontWeight.Bold,
              lineHeight = 34.sp
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
              text = "${event.date}  •  ${event.venue}",
              color = Color.White.copy(alpha = 0.9f),
              fontSize = 13.sp,
              fontWeight = FontWeight.Medium
            )
          }
        }
      }

      item {
        Column(
          modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 16.dp),
          verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
          Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(UnifiedCornerRadius),
            colors = CardDefaults.cardColors(containerColor = Color.White),
            border = BorderStroke(1.dp, Color(0xFFE2E8F0)),
            elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
          ) {
            Row(
              modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
              horizontalArrangement = Arrangement.SpaceBetween,
              verticalAlignment = Alignment.CenterVertically
            ) {
              Column {
                Text(
                  text = "Starting from",
                  color = Color(0xFF64748B),
                  fontSize = 12.sp,
                  fontWeight = FontWeight.Medium
                )
                Text(
                  text = event.price,
                  color = Color(0xFF0F172A),
                  fontSize = 22.sp,
                  fontWeight = FontWeight.Bold
                )
              }

              Column(horizontalAlignment = Alignment.End) {
                Box(
                  modifier = Modifier
                    .clip(RoundedCornerShape(999.dp))
                    .background(MIBlue.copy(alpha = 0.08f))
                    .padding(horizontal = 12.dp, vertical = 8.dp)
                ) {
                  Text(
                    text = if (isSaved) "Saved" else "Live event",
                    color = MIBlue,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold
                  )
                }
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                  text = if (isSaved) "You can revisit this event later." else "Tap save to keep it on your list.",
                  color = Color(0xFF64748B),
                  fontSize = 11.sp,
                  fontWeight = FontWeight.Medium
                )
              }
            }
          }

          Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            DetailInfoChip(label = "Date", value = event.date, modifier = Modifier.weight(1f))
            DetailInfoChip(label = "Venue", value = event.venue, modifier = Modifier.weight(1f))
          }

          Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(UnifiedCornerRadius),
            colors = CardDefaults.cardColors(containerColor = Color.White),
            border = BorderStroke(1.dp, Color(0xFFE2E8F0)),
            elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
          ) {
            Column(
              modifier = Modifier.padding(16.dp),
              verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
              Text(
                text = "Event snapshot",
                color = Color(0xFF0F172A),
                fontSize = 17.sp,
                fontWeight = FontWeight.Bold
              )
              eventFacts.forEach { (label, value) ->
                Row(
                  modifier = Modifier.fillMaxWidth(),
                  horizontalArrangement = Arrangement.SpaceBetween,
                  verticalAlignment = Alignment.Top
                ) {
                  Text(
                    text = label,
                    color = Color(0xFF64748B),
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Medium
                  )
                  Spacer(modifier = Modifier.width(12.dp))
                  Text(
                    text = value,
                    color = Color(0xFF0F172A),
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    textAlign = TextAlign.End
                  )
                }
              }
            }
          }

          Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(UnifiedCornerRadius),
            colors = CardDefaults.cardColors(containerColor = Color.White),
            border = BorderStroke(1.dp, Color(0xFFE2E8F0)),
            elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
          ) {
            Column(
              modifier = Modifier.padding(16.dp),
              verticalArrangement = Arrangement.spacedBy(10.dp)
            ) {
              Text(
                text = "About this event",
                color = Color(0xFF0F172A),
                fontSize = 17.sp,
                fontWeight = FontWeight.Bold
              )
              Text(
                text = aboutText,
                color = Color(0xFF475569),
                fontSize = 13.sp,
                lineHeight = 18.sp
              )
            }
          }

          Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(UnifiedCornerRadius),
            colors = CardDefaults.cardColors(containerColor = Color.White),
            border = BorderStroke(1.dp, Color(0xFFE2E8F0)),
            elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
          ) {
            Column(
              modifier = Modifier.padding(16.dp),
              verticalArrangement = Arrangement.spacedBy(10.dp)
            ) {
              Text(
                text = "Highlights",
                color = Color(0xFF0F172A),
                fontSize = 17.sp,
                fontWeight = FontWeight.Bold
              )
              detailPoints.forEach { point ->
                Row(verticalAlignment = Alignment.CenterVertically) {
                  Box(
                    modifier = Modifier
                      .size(6.dp)
                      .clip(androidx.compose.foundation.shape.CircleShape)
                      .background(MIGreen)
                  )
                  Spacer(modifier = Modifier.width(10.dp))
                  Text(
                    text = point,
                    color = Color(0xFF334155),
                    fontSize = 13.sp
                  )
                }
              }
            }
          }

          Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(UnifiedCornerRadius),
            colors = CardDefaults.cardColors(containerColor = Color.White),
            border = BorderStroke(1.dp, Color(0xFFE2E8F0)),
            elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
          ) {
            Column(
              modifier = Modifier.padding(16.dp),
              verticalArrangement = Arrangement.spacedBy(10.dp)
            ) {
              Text(
                text = "What you get",
                color = Color(0xFF0F172A),
                fontSize = 17.sp,
                fontWeight = FontWeight.Bold
              )
              inclusions.forEach { item ->
                Text(
                  text = "• $item",
                  color = Color(0xFF475569),
                  fontSize = 13.sp
                )
              }
            }
          }

          Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(UnifiedCornerRadius),
            colors = CardDefaults.cardColors(containerColor = MIBlue.copy(alpha = 0.06f)),
            border = BorderStroke(1.dp, MIBlue.copy(alpha = 0.16f)),
            elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
          ) {
            Column(
              modifier = Modifier.padding(16.dp),
              verticalArrangement = Arrangement.spacedBy(10.dp)
            ) {
              Text(
                text = "Need help deciding?",
                color = Color(0xFF0F172A),
                fontSize = 15.sp,
                fontWeight = FontWeight.Bold
              )
              Text(
                text = "This page is ready for booking handoff. The next step can open seat selection, payment, or a checkout sheet when you connect it.",
                color = Color(0xFF475569),
                fontSize = 12.sp,
                lineHeight = 17.sp
              )
            }
          }
        }
      }
    }

    Card(
      modifier = Modifier
        .align(Alignment.BottomCenter)
        .navigationBarsPadding()
        .padding(16.dp)
        .fillMaxWidth(),
      shape = RoundedCornerShape(24.dp),
      colors = CardDefaults.cardColors(containerColor = Color.White),
      border = BorderStroke(1.dp, Color(0xFFE2E8F0)),
      elevation = CardDefaults.cardElevation(defaultElevation = 10.dp)
    ) {
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .padding(16.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalAlignment = Alignment.CenterVertically
      ) {
        Column(modifier = Modifier.weight(1f)) {
          Text(
            text = "From ${event.price}",
            color = Color(0xFF0F172A),
            fontSize = 18.sp,
            fontWeight = FontWeight.Bold
          )
          Text(
            text = "Secure checkout and instant confirmation",
            color = Color(0xFF64748B),
            fontSize = 11.sp,
            fontWeight = FontWeight.Medium
          )
        }

        androidx.compose.material3.Button(
          onClick = {
            Toast.makeText(context, "Booking flow can be connected next.", Toast.LENGTH_SHORT).show()
          },
          modifier = Modifier.height(52.dp),
          shape = RoundedCornerShape(16.dp)
        ) {
          Text(
            text = "Book Tickets",
            fontSize = 15.sp,
            fontWeight = FontWeight.SemiBold
          )
        }
      }
    }
  }
}

@Composable
private fun DetailInfoChip(
  label: String,
  value: String,
  modifier: Modifier = Modifier
) {
  Card(
    modifier = modifier,
    shape = RoundedCornerShape(UnifiedCornerRadius),
    colors = CardDefaults.cardColors(containerColor = Color.White),
    border = BorderStroke(1.dp, Color(0xFFE2E8F0)),
    elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
  ) {
    Column(
      modifier = Modifier.padding(14.dp),
      verticalArrangement = Arrangement.spacedBy(4.dp)
    ) {
      Text(
        text = label,
        color = Color(0xFF64748B),
        fontSize = 11.sp,
        fontWeight = FontWeight.Medium
      )
      Text(
        text = value,
        color = Color(0xFF0F172A),
        fontSize = 13.sp,
        fontWeight = FontWeight.SemiBold,
        lineHeight = 16.sp
      )
    }
  }
}

@Composable
private fun GameHubTabScreen(
  searchQuery: String,
  onActionBoardClick: () -> Unit
) {
  var selectedSport by remember { mutableStateOf("All") }
  
  val sports = listOf("All", "Cricket", "Football", "Badminton", "Swimming", "Tennis", "Basketball")
  
  val venuesData = listOf(
    VenueItem("1", "Stryker Turf Center", "Andheri West", "4.8", "Cricket", 1200),
    VenueItem("2", "Kick Sports Arena", "Powai", "4.6", "Football", 1500),
    VenueItem("3", "Badminton Club Pro", "Bandra", "4.9", "Badminton", 600),
    VenueItem("4", "Aqua Olympic Pool", "Juhu", "4.5", "Swimming", 400),
    VenueItem("5", "Deuce Tennis Academy", "Colaba", "4.7", "Tennis", 800),
    VenueItem("6", "Hoop City Court", "Ghatkopar", "4.6", "Basketball", 1000),
  )

  val filteredVenues = venuesData.filter {
    (selectedSport == "All" || it.category == selectedSport) &&
    (searchQuery.isEmpty() || it.title.contains(searchQuery, ignoreCase = true) || it.location.contains(searchQuery, ignoreCase = true))
  }

  androidx.compose.foundation.lazy.LazyColumn(
    modifier = Modifier
      .fillMaxSize()
      .padding(horizontal = 16.dp),
    verticalArrangement = Arrangement.spacedBy(16.dp)
  ) {
    item {
      Spacer(modifier = Modifier.height(8.dp))
      Card(
        modifier = Modifier
          .fillMaxWidth()
          .height(90.dp)
          .clickable { onActionBoardClick() },
        shape = RoundedCornerShape(UnifiedCornerRadius),
        colors = CardDefaults.cardColors(containerColor = Color(0xFFEEFBF3)),
        border = BorderStroke(1.dp, Color(0xFFD1F2DE))
      ) {
        Row(
          modifier = Modifier
            .fillMaxSize()
            .padding(14.dp),
          verticalAlignment = Alignment.CenterVertically
        ) {
          Box(
            modifier = Modifier
              .size(48.dp)
              .clip(RoundedCornerShape(UnifiedCornerRadius))
              .background(MIGreen.copy(alpha = 0.15f)),
            contentAlignment = Alignment.Center
          ) {
            androidx.compose.material3.Icon(
              imageVector = Icons.Default.Star,
              contentDescription = "Live Match",
              tint = MIGreen,
              modifier = Modifier.size(24.dp)
            )
          }
          Spacer(modifier = Modifier.width(12.dp))
          Column(modifier = Modifier.weight(1f)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
              Text(
                text = "ActionBoard",
                color = Color(0xFF0F172A),
                fontWeight = FontWeight.Bold,
                fontSize = 15.sp
              )
              Spacer(modifier = Modifier.width(8.dp))
              Box(
                modifier = Modifier
                  .background(Color(0xFFDC2626), RoundedCornerShape(4.dp))
                  .padding(horizontal = 6.dp, vertical = 2.dp)
              ) {
                Text(
                  text = "LIVE MATCH",
                  color = Color.White,
                  fontSize = 8.sp,
                  fontWeight = FontWeight.Bold
                )
              }
            }
            Text(
              text = "Join active open play cricket/turf sessions",
              color = Color(0xFF64748B),
              fontSize = 12.sp
            )
          }
        }
      }
    }

    item {
      androidx.compose.foundation.lazy.LazyRow(
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier.fillMaxWidth()
      ) {
        items(sports.size) { i ->
          val sport = sports[i]
          val selected = sport == selectedSport
          
          Box(
            modifier = Modifier
              .clip(RoundedCornerShape(999.dp))
              .background(if (selected) MIGreen else Color(0xFFF1F5F9))
              .clickable { selectedSport = sport }
              .padding(horizontal = 16.dp, vertical = 8.dp)
          ) {
            Text(
              text = sport,
              color = if (selected) Color.White else Color(0xFF64748B),
              fontSize = 13.sp,
              fontWeight = FontWeight.Bold
            )
          }
        }
      }
    }

    item {
      Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
      ) {
        Text(
          text = "Featured Arenas",
          color = Color(0xFF0F172A),
          fontWeight = FontWeight.Bold,
          fontSize = 18.sp
        )
        Text(
          text = "View all",
          color = MIGreen,
          fontWeight = FontWeight.Bold,
          fontSize = 13.sp
        )
      }
    }

    if (filteredVenues.isEmpty()) {
      item {
        Box(
          modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 40.dp),
          contentAlignment = Alignment.Center
        ) {
          Text(text = "No venues found matching filters.", color = Color(0xFF64748B))
        }
      }
    } else {
      items(filteredVenues.size) { i ->
        val venue = filteredVenues[i]
        VenueListCard(venue)
      }
    }
    
    item {
      Spacer(modifier = Modifier.height(16.dp))
    }
  }
}

private data class VenueItem(
  val id: String,
  val title: String,
  val location: String,
  val rating: String,
  val category: String,
  val price: Int
)

@Composable
private fun VenueListCard(venue: VenueItem) {
  Card(
    modifier = Modifier.fillMaxWidth(),
    shape = RoundedCornerShape(UnifiedCornerRadius),
    colors = CardDefaults.cardColors(containerColor = Color.White),
    border = BorderStroke(1.dp, Color(0xFFE2E8F0))
  ) {
    Column(modifier = Modifier.fillMaxWidth()) {
      Box(
        modifier = Modifier
          .fillMaxWidth()
          .height(120.dp)
          .background(
            Brush.linearGradient(
              colors = listOf(Color(0xFFE2E8F0), Color(0xFFEEFBF3))
            )
          ),
        contentAlignment = Alignment.Center
      ) {
        Text(
          text = venue.title.first().toString(),
          color = MIGreen.copy(alpha = 0.5f),
          fontWeight = FontWeight.Bold,
          fontSize = 48.sp
        )
      }
      
      Column(modifier = Modifier.padding(14.dp)) {
        Row(
          modifier = Modifier.fillMaxWidth(),
          horizontalArrangement = Arrangement.SpaceBetween,
          verticalAlignment = Alignment.CenterVertically
        ) {
          Text(
            text = venue.category,
            color = MIGreen,
            fontWeight = FontWeight.Bold,
            fontSize = 11.sp
          )
          Row(verticalAlignment = Alignment.CenterVertically) {
            Text(text = "★", color = Color(0xFFFFB000), fontSize = 14.sp)
            Spacer(modifier = Modifier.width(2.dp))
            Text(
              text = venue.rating,
              color = Color(0xFF0F172A),
              fontWeight = FontWeight.Bold,
              fontSize = 12.sp
            )
          }
        }
        
        Spacer(modifier = Modifier.height(2.dp))
        Text(
          text = venue.title,
          color = Color(0xFF0F172A),
          fontWeight = FontWeight.Bold,
          fontSize = 16.sp
        )
        Text(
          text = venue.location,
          color = Color(0xFF64748B),
          fontSize = 13.sp
        )
        
        Spacer(modifier = Modifier.height(10.dp))
        Row(
          modifier = Modifier.fillMaxWidth(),
          horizontalArrangement = Arrangement.SpaceBetween,
          verticalAlignment = Alignment.CenterVertically
        ) {
          Text(
            text = "₹${venue.price}/hr",
            color = Color(0xFF0F172A),
            fontWeight = FontWeight.Bold,
            fontSize = 15.sp
          )
          Box(
            modifier = Modifier
              .background(MIGreen, RoundedCornerShape(UnifiedCornerRadius))
              .padding(horizontal = 14.dp, vertical = 6.dp)
          ) {
            Text(
              text = "Book Slot",
              color = Color.White,
              fontSize = 12.sp,
              fontWeight = FontWeight.Bold
            )
          }
        }
      }
    }
  }
}

@Composable
private fun LeaderboardTabScreen() {
  val players = listOf(
    LeaderboardItem(1, "Rohan Sharma", 1850, "5 Match Win Streak", "https://cdn-icons-png.flaticon.com/512/2815/2815428.png"),
    LeaderboardItem(2, "Amit Patil", 1720, "2 Match Win Streak", ""),
    LeaderboardItem(3, "Vikram Malhotra", 1680, "Streak Broken", ""),
    LeaderboardItem(4, "Priya Nair", 1590, "3 Match Win Streak", ""),
    LeaderboardItem(5, "Rajesh Iyer", 1450, "1 Match Win Streak", ""),
  )

  androidx.compose.foundation.lazy.LazyColumn(
    modifier = Modifier
      .fillMaxSize()
      .padding(horizontal = 16.dp),
    verticalArrangement = Arrangement.spacedBy(12.dp)
  ) {
    item {
      Spacer(modifier = Modifier.height(8.dp))
      
      Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(UnifiedCornerRadius),
        colors = CardDefaults.cardColors(containerColor = Color(0xFF0F172A))
      ) {
        Column(
          modifier = Modifier.padding(18.dp),
          horizontalAlignment = Alignment.CenterHorizontally
        ) {
          Text(
            text = "🏆 LEADERS ARENA 🏆",
            color = Gold,
            fontWeight = FontWeight.Bold,
            fontSize = 12.sp,
            letterSpacing = 1.sp
          )
          Spacer(modifier = Modifier.height(4.dp))
          Text(
            text = "Monthly Leaderboard",
            color = Color.White,
            fontWeight = FontWeight.ExtraBold,
            fontSize = 20.sp
          )
          Text(
            text = "Top performing turf players in Mumbai",
            color = Color(0xFF94A3B8),
            fontSize = 12.sp
          )
        }
      }
    }

    item {
      Spacer(modifier = Modifier.height(4.dp))
      Text(
        text = "Top Players",
        color = Color(0xFF0F172A),
        fontWeight = FontWeight.Bold,
        fontSize = 16.sp
      )
    }

    items(players.size) { i ->
      val player = players[i]
      LeaderboardPlayerCard(player)
    }
    
    item {
      Spacer(modifier = Modifier.height(16.dp))
    }
  }
}

private data class LeaderboardItem(
  val rank: Int,
  val name: String,
  val points: Int,
  val streak: String,
  val avatar: String
)

@Composable
private fun LeaderboardPlayerCard(player: LeaderboardItem) {
  val rankColor = when (player.rank) {
    1 -> Gold
    2 -> Color(0xFFC0C0C0)
    3 -> Color(0xFFCD7F32)
    else -> Color(0xFF64748B)
  }

  Card(
    modifier = Modifier.fillMaxWidth(),
    shape = RoundedCornerShape(UnifiedCornerRadius),
    colors = CardDefaults.cardColors(containerColor = Color.White),
    border = BorderStroke(1.dp, Color(0xFFE2E8F0))
  ) {
    Row(
      modifier = Modifier
        .fillMaxWidth()
        .padding(12.dp),
      verticalAlignment = Alignment.CenterVertically
    ) {
      Box(
        modifier = Modifier
          .size(36.dp)
          .clip(RoundedCornerShape(UnifiedCornerRadius))
          .background(rankColor.copy(alpha = 0.15f)),
        contentAlignment = Alignment.Center
      ) {
        Text(
          text = "#${player.rank}",
          color = rankColor,
          fontWeight = FontWeight.Bold,
          fontSize = 14.sp
        )
      }
      
      Spacer(modifier = Modifier.width(12.dp))
      
      Box(
        modifier = Modifier
          .size(40.dp)
          .clip(RoundedCornerShape(UnifiedCornerRadius))
          .background(Color(0xFFF1F5F9)),
        contentAlignment = Alignment.Center
      ) {
        androidx.compose.material3.Icon(
          imageVector = Icons.Default.Person,
          contentDescription = "Avatar",
          tint = Color(0xFF94A3B8),
          modifier = Modifier.size(24.dp)
        )
      }
      
      Spacer(modifier = Modifier.width(12.dp))
      
      Column(modifier = Modifier.weight(1f)) {
        Text(
          text = player.name,
          color = Color(0xFF0F172A),
          fontWeight = FontWeight.Bold,
          fontSize = 15.sp
        )
        Text(
          text = player.streak,
          color = if (player.streak.contains("Broken")) Color(0xFFDC2626) else MIGreen,
          fontSize = 11.sp,
          fontWeight = FontWeight.Medium
        )
      }
      
      Text(
        text = "${player.points} pts",
        color = Color(0xFF0F172A),
        fontWeight = FontWeight.ExtraBold,
        fontSize = 15.sp
      )
    }
  }
}

@Preview(showBackground = true)
@Composable
fun MainScreenPreview() {
  ThannaTheme { LoginScreen(onSkip = {}) }
}

@Preview(showBackground = true, widthDp = 340)
@Composable
fun MainScreenPortraitPreview() {
  ThannaTheme { LoginScreen(onSkip = {}) }
}

@Composable
private fun DistrictActionBoardScreen(onBack: () -> Unit, onMatchClick: (String) -> Unit) {
  CrexMatchesScreen(onBack, onMatchClick)
}

@Composable
private fun CrexMatchesScreen(onBack: () -> Unit, onMatchClick: (String) -> Unit) {
  val view = LocalView.current

  SideEffect {
    val activity = view.context as? Activity ?: return@SideEffect
    val window = activity.window
    WindowCompat.setDecorFitsSystemWindows(window, false)
    window.statusBarColor = android.graphics.Color.TRANSPARENT
    window.navigationBarColor = android.graphics.Color.TRANSPARENT
  }

  val bg = LightBackground

  Scaffold(
    containerColor = Color.Transparent,
    contentWindowInsets = WindowInsets(0),
    bottomBar = { CrexBottomBar() }
  ) { padding ->
    Box(
      modifier = Modifier
        .fillMaxSize()
        .statusBarsPadding()
        .background(bg)
        .padding(padding)
    ) {
      LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(
          start = 16.dp,
          end = 16.dp,
          top = 12.dp,
          bottom = 100.dp
        )
      ) {
        item { CrexHeaderSection(onBack) }
        item { CrexTabsSection() }
        item { Spacer(modifier = Modifier.height(22.dp)) }
        item { CrexLeagueTitle("Indian Premier League 2026") }
        item {
          CrexMatchResultCard(
            team1 = "GT",
            team1Logo = "https://upload.wikimedia.org/wikipedia/en/thumb/9/9e/Gujarat_Titans_Logo.svg/1200px-Gujarat_Titans_Logo.svg.png",
            score1 = "155-8",
            overs1 = "20.0",
            team2 = "RCB",
            team2Logo = "https://upload.wikimedia.org/wikipedia/en/thumb/5/5e/Royal_Challengers_Bengaluru_Logo.svg/1200px-Royal_Challengers_Bengaluru_Logo.svg.png",
            score2 = "161-5",
            overs2 = "18.0",
            result = "RCB Won",
            subResult = "by 5 wickets",
            onClick = { onMatchClick("ind-aus-1") }
          )
        }
        item { Spacer(modifier = Modifier.height(18.dp)) }
        item {
          CrexMatchResultCard(
            team1 = "RR",
            team1Logo = "https://upload.wikimedia.org/wikipedia/en/thumb/6/6d/Rajasthan_Royals_Logo.svg/1200px-Rajasthan_Royals_Logo.svg.png",
            score1 = "214-6",
            overs1 = "20.0",
            team2 = "GT",
            team2Logo = "https://upload.wikimedia.org/wikipedia/en/thumb/9/9e/Gujarat_Titans_Logo.svg/1200px-Gujarat_Titans_Logo.svg.png",
            score2 = "219-3",
            overs2 = "18.4",
            result = "GT Won",
            subResult = "by 7 wickets",
            onClick = { onMatchClick("ind-aus-2") }
          )
        }
        item { Spacer(modifier = Modifier.height(28.dp)) }
        item { CrexLeagueTitle("Australia tour of Pakistan 2026") }
        item { CrexUpcomingMatchCard(onClick = { onMatchClick("pak-aus-1") }) }
      }
    }
  }
}

@Composable
private fun CrexHeaderSection(onBack: () -> Unit) {
  Row(
    modifier = Modifier
      .fillMaxWidth()
      .padding(top = 10.dp, bottom = 10.dp),
    verticalAlignment = Alignment.CenterVertically,
    horizontalArrangement = Arrangement.SpaceBetween
  ) {
    Row(verticalAlignment = Alignment.CenterVertically) {
      Image(
        painter = painterResource(id = com.example.thanna.R.drawable.haraan_copy),
        contentDescription = "Haraan logo",
        modifier = Modifier.size(28.dp),
        contentScale = ContentScale.Fit,
        colorFilter = ColorFilter.tint(LightPrimaryText)
      )
    }

    Row(
      verticalAlignment = Alignment.CenterVertically,
      horizontalArrangement = Arrangement.spacedBy(12.dp)
    ) {
      CrexSearchBar()
      Button(
        onClick = { /* TODO: launch create match flow */ },
        modifier = Modifier.height(38.dp),
        shape = RoundedCornerShape(22.dp),
        colors = ButtonDefaults.buttonColors(
          containerColor = LightAccentPink,
          contentColor = Color.White
        )
      ) {
        Icon(
          imageVector = Icons.Default.Add,
          contentDescription = null,
          modifier = Modifier.size(18.dp)
        )
        Spacer(modifier = Modifier.width(6.dp))
        Text(text = "Create", fontSize = 14.sp)
      }
      Icon(
        imageVector = Icons.Default.List,
        contentDescription = null,
        tint = LightSecondaryText
      )
    }
  }
}

@Composable
private fun CrexSearchBar() {
  Row(
    modifier = Modifier
      .width(108.dp)
      .height(38.dp)
      .clip(RoundedCornerShape(22.dp))
      .background(Color.White)
      .border(1.dp, Color(0xFFE5E7EB), RoundedCornerShape(22.dp))
      .padding(horizontal = 14.dp),
    verticalAlignment = Alignment.CenterVertically
  ) {
    Icon(
      imageVector = Icons.Default.Search,
      contentDescription = null,
      tint = LightMutedText,
      modifier = Modifier.size(18.dp)
    )

    Spacer(modifier = Modifier.width(8.dp))

    Text(text = "Search", color = LightSecondaryText, fontSize = 14.sp)
  }
}

@Composable
private fun CrexTabsSection() {
  data class TabItem(val title: String, val icon: ImageVector)

  val tabs = listOf(
    TabItem("Live", Icons.Default.PlayArrow),
    TabItem("Finished", Icons.Default.CheckCircle),
    TabItem("District Board", Icons.Default.Apartment),
    TabItem("State Board", Icons.Default.AccountBalance)
  )
  var selected by remember { mutableStateOf(0) }

  LazyRow(
    modifier = Modifier
      .fillMaxWidth()
      .padding(horizontal = 16.dp),
    horizontalArrangement = Arrangement.spacedBy(28.dp),
    verticalAlignment = Alignment.CenterVertically
  ) {
    items(tabs.size) { index ->
      val tab = tabs[index]
      Column(
        modifier = Modifier
          .clickable { selected = index },
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
      ) {
        Icon(
          imageVector = tab.icon,
          contentDescription = null,
          tint = if (selected == index) LightPrimaryText else LightSecondaryText,
          modifier = Modifier.size(18.dp)
        )

        Spacer(modifier = Modifier.height(6.dp))

        Text(
          text = tab.title,
          fontSize = 13.sp,
          fontWeight = FontWeight.SemiBold,
          color = if (selected == index) LightPrimaryText else LightSecondaryText
        )

        Spacer(modifier = Modifier.height(10.dp))

        if (selected == index) {
          Box(
            modifier = Modifier
              .width(36.dp)
              .height(3.dp)
              .clip(RoundedCornerShape(20.dp))
              .background(LightAccentPink)
          )
        }
      }
    }
  }
}

@Composable
private fun CrexLeagueTitle(title: String) {
  Row(
    modifier = Modifier
      .fillMaxWidth()
      .padding(vertical = 24.dp),
    verticalAlignment = Alignment.CenterVertically
  ) {
    Box(
      modifier = Modifier
        .size(10.dp)
        .clip(RoundedCornerShape(999.dp))
        .background(LightAccentBlue)
    )

    Spacer(modifier = Modifier.width(10.dp))

    Text(
      text = title,
      color = LightPrimaryText,
      fontSize = 18.sp,
      fontWeight = FontWeight.SemiBold
    )
  }
}

@Composable
private fun CrexMatchResultCard(
  team1: String,
  team1Logo: String,
  score1: String,
  overs1: String,
  team2: String,
  team2Logo: String,
  score2: String,
  overs2: String,
  result: String,
  subResult: String,
  onClick: () -> Unit = {}
) {
  Card(
    modifier = Modifier
      .fillMaxWidth()
      .height(168.dp)
      .shadow(
        elevation = 6.dp,
        shape = RoundedCornerShape(28.dp),
        ambientColor = Color.Black.copy(alpha = 0.08f),
        spotColor = Color.Black.copy(alpha = 0.08f)
      )
      .clickable { onClick() },
    shape = RoundedCornerShape(28.dp),
    colors = CardDefaults.cardColors(containerColor = LightCard),
    border = BorderStroke(1.dp, LightDivider)
  ) {
    Column(
      modifier = Modifier
        .fillMaxSize()
        .padding(24.dp),
      verticalArrangement = Arrangement.SpaceBetween
    ) {
      Text(
        text = "Final, Narendra Modi Stadium, Ahmedabad",
        color = LightMutedText,
        fontSize = 13.sp
      )

      Row(
        modifier = Modifier.fillMaxWidth()
      ) {
        Column(
          modifier = Modifier.weight(1f),
          verticalArrangement = Arrangement.spacedBy(18.dp)
        ) {
          CrexTeamScore(team1, team1Logo, score1, overs1)
          CrexTeamScore(team2, team2Logo, score2, overs2)
        }

        Spacer(modifier = Modifier.width(20.dp))

        Box(
          modifier = Modifier
            .width(1.dp)
            .height(72.dp)
            .background(LightDivider)
        )

        Spacer(modifier = Modifier.width(20.dp))

        Column(
          modifier = Modifier.width(92.dp),
          horizontalAlignment = Alignment.End,
          verticalArrangement = Arrangement.Center
        ) {
          Text(
            text = result,
            color = LightAccentPink,
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold
          )

          Spacer(modifier = Modifier.height(6.dp))

          Text(
            text = subResult,
            color = LightSecondaryText,
            fontSize = 14.sp
          )
        }
      }
    }
  }
}

@Composable
private fun CrexTeamScore(
  team: String,
  logoUrl: String,
  score: String,
  overs: String
) {
  Row(verticalAlignment = Alignment.CenterVertically) {
    AsyncImage(
      model = logoUrl,
      contentDescription = team,
      modifier = Modifier
        .size(28.dp)
        .clip(RoundedCornerShape(999.dp))
        .background(Color.White.copy(alpha = 0.08f)),
      contentScale = ContentScale.Fit
    )

    Spacer(modifier = Modifier.width(14.dp))

    Text(
      text = team,
      color = LightPrimaryText,
      fontSize = 19.sp,
      fontWeight = FontWeight.Bold
    )

    Spacer(modifier = Modifier.width(12.dp))

    Text(
      text = score,
      color = LightPrimaryText,
      fontSize = 18.sp
    )

    Spacer(modifier = Modifier.width(8.dp))

    Text(
      text = overs,
      color = LightMutedText,
      fontSize = 13.sp,
      maxLines = 1,
      softWrap = false,
      modifier = Modifier.width(34.dp)
    )
  }
}

@Composable
private fun CrexUpcomingMatchCard(onClick: () -> Unit = {}) {
  Card(
    modifier = Modifier
      .fillMaxWidth()
      .height(168.dp)
      .shadow(
        elevation = 20.dp,
        shape = RoundedCornerShape(28.dp),
        ambientColor = Color.Black.copy(alpha = 0.6f),
        spotColor = Color.Black.copy(alpha = 0.6f)
      )
      .clickable { onClick() },
    shape = RoundedCornerShape(28.dp),
    colors = CardDefaults.cardColors(containerColor = Color(0xFF111827)),
    border = BorderStroke(1.dp, Color.White.copy(alpha = 0.04f))
  ) {
    Column(
      modifier = Modifier
        .fillMaxSize()
        .padding(22.dp),
      verticalArrangement = Arrangement.SpaceBetween
    ) {
      Text(
        text = "2nd ODI, Gaddafi Stadium, Lahore",
        color = Color(0xFF8A93A0),
        fontSize = 13.sp
      )

      Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
      ) {
        Column(
          verticalArrangement = Arrangement.spacedBy(18.dp)
        ) {
          Text("Pakistan", color = Color.White, fontSize = 20.sp)
          Text("Australia", color = Color.White, fontSize = 20.sp)
        }

        Box(
          modifier = Modifier
            .width(1.dp)
            .height(72.dp)
            .background(Color.White.copy(alpha = 0.05f))
        )

        Column(
          modifier = Modifier.width(92.dp),
          horizontalAlignment = Alignment.End,
          verticalArrangement = Arrangement.Center
        ) {
          Text("Starting in:", color = Color(0xFF8A93A0), fontSize = 13.sp)
          Spacer(modifier = Modifier.height(6.dp))
          Text(
            "56m : 14s",
            color = Color(0xFFFFC857),
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold
          )
        }
      }
    }
  }
}

@Composable
private fun CrexBottomBar() {
  var selectedIndex by remember { mutableStateOf(0) }
  val items = listOf(
    "Home" to Icons.Default.Home,
    "Cricket" to Icons.Default.SportsCricket,
    "Badmentain" to Icons.Default.SportsTennis,
    "Football" to Icons.Default.SportsFootball,
    "Others" to Icons.Default.PlayArrow,
  )

  NavigationBar(
    modifier = Modifier
      .fillMaxWidth()
      .navigationBarsPadding()
      .padding(horizontal = 16.dp, vertical = 10.dp)
      .height(72.dp)
      .clip(RoundedCornerShape(28.dp)),
    containerColor = Color(0xFF151C28),
    tonalElevation = 0.dp,
    contentColor = Color.White
  ) {
    items.forEachIndexed { index, item ->
      NavigationBarItem(
        selected = selectedIndex == index,
        onClick = { selectedIndex = index },
        icon = {
          Icon(
            imageVector = item.second,
            contentDescription = item.first,
            modifier = Modifier.size(22.dp),
            tint = if (selectedIndex == index) Color.White else Color(0xFF7B8190)
          )
        },
        label = {
          Text(
            text = item.first,
            fontSize = 11.sp,
            fontWeight = if (selectedIndex == index) FontWeight.SemiBold else FontWeight.Medium
          )
        },
        alwaysShowLabel = false,
        colors = NavigationBarItemDefaults.colors(
          indicatorColor = Color(0xFF2A3142)
        )
      )
    }
  }
}

@Composable
private fun CrexBottomNavItem(
  label: String,
  icon: androidx.compose.ui.graphics.vector.ImageVector,
  selected: Boolean,
  badge: String? = null
) {
  Column(horizontalAlignment = Alignment.CenterHorizontally) {
    Box(contentAlignment = Alignment.TopEnd) {
      Icon(
        imageVector = icon,
        contentDescription = label,
        tint = if (selected) Color(0xFF7FB8D8) else Color.White
      )
      if (badge != null) {
        Box(
          modifier = Modifier
            .offset(x = 10.dp, y = (-6).dp)
            .clip(RoundedCornerShape(999.dp))
            .background(Color(0xFFFF5A3D))
            .padding(horizontal = 7.dp, vertical = 2.dp)
        ) {
          Text(text = badge, color = Color.White, fontSize = 9.sp, fontWeight = FontWeight.Bold)
        }
      }
    }

    Spacer(modifier = Modifier.height(4.dp))

    Text(
      text = label,
      color = if (selected) Color(0xFF7FB8D8) else Color.White,
      fontSize = 12.sp,
      fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium
    )
  }
}

@Composable
private fun CrexBottomNavCenterItem(label: String, icon: ImageVector) {
  Column(horizontalAlignment = Alignment.CenterHorizontally) {
    Box(
      modifier = Modifier
        .size(46.dp)
        .clip(RoundedCornerShape(22.dp))
        .background(Color.White.copy(alpha = 0.10f))
        .border(BorderStroke(1.dp, Color.White.copy(alpha = 0.06f)), RoundedCornerShape(22.dp)),
      contentAlignment = Alignment.Center
    ) {
      Icon(
        imageVector = icon,
        contentDescription = label,
        tint = Color.White,
        modifier = Modifier.size(28.dp)
      )
    }

    Spacer(modifier = Modifier.height(4.dp))

    Text(
      text = label,
      color = Color(0xFF7FB8D8),
      fontSize = 12.sp,
      fontWeight = FontWeight.Bold
    )
  }
}

@Composable
private fun LeaderboardTabPanel() {
  Card(
    modifier = Modifier.fillMaxWidth(),
    shape = RoundedCornerShape(24.dp),
    colors = CardDefaults.cardColors(containerColor = Color(0xFF102133)),
    border = BorderStroke(1.dp, Color.White.copy(alpha = 0.08f))
  ) {
    Column(modifier = Modifier.padding(14.dp)) {
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .padding(vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically
      ) {
        Text(text = "Rk", color = Color(0xFF7F97B0), fontWeight = FontWeight.Bold, fontSize = 11.sp, modifier = Modifier.width(32.dp), textAlign = TextAlign.Center)
        Text(text = "Player", color = Color(0xFF7F97B0), fontWeight = FontWeight.Bold, fontSize = 11.sp, modifier = Modifier.weight(1.5f))
        Text(text = "District", color = Color(0xFF7F97B0), fontWeight = FontWeight.Bold, fontSize = 11.sp, modifier = Modifier.weight(1.1f))
        Text(text = "Runs", color = Color(0xFF7F97B0), fontWeight = FontWeight.Bold, fontSize = 11.sp, modifier = Modifier.weight(0.9f), textAlign = TextAlign.End)
        Text(text = "Wkts", color = Color(0xFF7F97B0), fontWeight = FontWeight.Bold, fontSize = 11.sp, modifier = Modifier.weight(0.8f), textAlign = TextAlign.End)
        Text(text = "Avg", color = Color(0xFF7F97B0), fontWeight = FontWeight.Bold, fontSize = 11.sp, modifier = Modifier.weight(0.9f), textAlign = TextAlign.End)
      }
      
      Box(modifier = Modifier.fillMaxWidth().height(1.dp).background(Color.White.copy(alpha = 0.08f)))
      
      val list = listOf(
        LeaderboardRow(1, "R. Hari", "Kadapa", 482, 12, "68.8"),
        LeaderboardRow(2, "P. Naidu", "Chittoor", 410, 8, "51.2"),
        LeaderboardRow(3, "K. Reddy", "Kurnool", 385, 19, "42.7"),
        LeaderboardRow(4, "S. Khan", "Anantapur", 340, 15, "37.8"),
        LeaderboardRow(5, "M. Prasad", "Nellore", 298, 22, "29.8")
      )
      
      list.forEachIndexed { index, row ->
        Row(
          modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 12.dp),
          verticalAlignment = Alignment.CenterVertically
        ) {
          val bgCircle = when (row.rk) {
            1 -> Gold
            2 -> Color(0xFFC0C0C0)
            3 -> Color(0xFFCD7F32)
            else -> Color(0xFF20364F)
          }
          Box(
            modifier = Modifier
              .width(32.dp)
              .height(24.dp)
              .clip(RoundedCornerShape(999.dp))
              .background(bgCircle.copy(alpha = if (row.rk <= 3) 1f else 0.45f)),
            contentAlignment = Alignment.Center
          ) {
            Text(
              text = row.rk.toString(),
              color = if (row.rk <= 3) Color.Black else Color.White,
              fontWeight = FontWeight.Bold,
              fontSize = 11.sp
            )
          }
          Spacer(modifier = Modifier.width(8.dp))
          Text(text = row.name, color = Color.White, fontWeight = FontWeight.Bold, fontSize = 13.sp, modifier = Modifier.weight(1.5f))
          Text(text = row.district, color = Color(0xFF9FB2C8), fontSize = 12.sp, modifier = Modifier.weight(1.1f))
          Text(text = row.runs.toString(), color = Color(0xFF68F29F), fontWeight = FontWeight.Bold, fontSize = 13.sp, modifier = Modifier.weight(0.9f), textAlign = TextAlign.End)
          Text(text = row.wickets.toString(), color = Color.White, fontSize = 13.sp, modifier = Modifier.weight(0.8f), textAlign = TextAlign.End)
          Text(text = row.avg, color = Color.White, fontSize = 12.sp, modifier = Modifier.weight(0.9f), textAlign = TextAlign.End)
        }
        if (index < list.lastIndex) {
          Box(modifier = Modifier.fillMaxWidth().height(0.5.dp).background(Color.White.copy(alpha = 0.05f)))
        }
      }
    }
  }
}

@Composable
private fun StatPill(label: String, value: String, modifier: Modifier = Modifier) {
  Column(
    modifier = modifier
      .clip(RoundedCornerShape(18.dp))
      .background(Color.White.copy(alpha = 0.06f))
      .border(BorderStroke(1.dp, Color.White.copy(alpha = 0.06f)), RoundedCornerShape(18.dp))
      .padding(horizontal = 10.dp, vertical = 10.dp)
  ) {
    Text(
      text = label,
      color = Color(0xFF8FA8C0),
      fontSize = 10.sp,
      fontWeight = FontWeight.Medium
    )
    Spacer(modifier = Modifier.height(4.dp))
    Text(
      text = value,
      color = Color.White,
      fontSize = 12.sp,
      fontWeight = FontWeight.Bold
    )
  }
}

private data class LeaderboardRow(val rk: Int, val name: String, val district: String, val runs: Int, val wickets: Int, val avg: String)

@Composable
private fun TournamentsTabPanel() {
  Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
    val tourneys = listOf(
      TournamentRow("Kadapa District T20 Championship", "ONGOING", "12 Local Clubs • Matches played daily at District Stadium", "May - June", Color(0xFF00C853)),
      TournamentRow("Rayalaseema State Selection Cup", "ONGOING", "Knockout stages underway • Dynamic Live stats tracking", "May 28 - Jun 10", Color(0xFF00C853)),
      TournamentRow("Nellore Inter-District Invitation League", "UPCOMING", "Registrations open for certified players ID", "Starts Jun 15", Color(0xFFFFB000))
    )
    
    tourneys.forEach { t ->
      Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(24.dp),
        colors = CardDefaults.cardColors(containerColor = Color(0xFF102133)),
        border = BorderStroke(1.dp, Color.White.copy(alpha = 0.08f))
      ) {
        Column(modifier = Modifier.padding(14.dp)) {
          Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
          ) {
            Box(
              modifier = Modifier
                .background(t.statusColor.copy(alpha = 0.15f), RoundedCornerShape(4.dp))
                .border(BorderStroke(1.dp, t.statusColor), RoundedCornerShape(4.dp))
                .padding(horizontal = 8.dp, vertical = 3.dp)
            ) {
              Text(
                text = t.status,
                color = t.statusColor,
                fontSize = 9.sp,
                fontWeight = FontWeight.Bold
              )
            }
            Text(
              text = t.date,
              color = Color(0xFF94A3B8),
              fontSize = 11.sp,
              fontWeight = FontWeight.Medium
            )
          }
          
          Spacer(modifier = Modifier.height(8.dp))
          Text(
            text = t.title,
            color = Color.White,
            fontWeight = FontWeight.Bold,
            fontSize = 14.sp
          )
          Spacer(modifier = Modifier.height(4.dp))
          Text(
            text = t.desc,
            color = Color(0xFF94A3B8),
            fontSize = 12.sp,
            lineHeight = 16.sp
          )
        }
      }
    }
  }
}

private data class TournamentRow(val title: String, val status: String, val desc: String, val date: String, val statusColor: Color)

@Composable
private fun LocalTalentsTabPanel() {
  Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
    val talents = listOf(
      TalentRow("R. Hari", "All-Rounder (Kadapa)", 5, "https://api.dicebear.com/7.x/avataaars/svg?seed=Hari"),
      TalentRow("K. Reddy", "Bowler (Kurnool)", 4, "https://api.dicebear.com/7.x/avataaars/svg?seed=Kiran"),
      TalentRow("P. Naidu", "Batter (Chittoor)", 4, "https://api.dicebear.com/7.x/avataaars/svg?seed=Naidu")
    )
    
    talents.forEach { p ->
      Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(24.dp),
        colors = CardDefaults.cardColors(containerColor = Color(0xFF102133)),
        border = BorderStroke(1.dp, Color.White.copy(alpha = 0.08f))
      ) {
        Row(
          modifier = Modifier
            .fillMaxWidth()
            .padding(12.dp),
          verticalAlignment = Alignment.CenterVertically
        ) {
          Box(
            modifier = Modifier
              .size(44.dp)
              .clip(RoundedCornerShape(UnifiedCornerRadius))
              .background(Color(0xFF0F172A)),
            contentAlignment = Alignment.Center
          ) {
            Text(
              text = p.name.first().toString(),
              color = Color(0xFF00C853),
              fontWeight = FontWeight.Bold,
              fontSize = 18.sp
            )
          }
          
          Spacer(modifier = Modifier.width(12.dp))
          
          Column(modifier = Modifier.weight(1f)) {
            Text(
              text = p.name,
              color = Color.White,
              fontWeight = FontWeight.Bold,
              fontSize = 14.sp
            )
            Text(
              text = p.role,
              color = Color(0xFF94A3B8),
              fontSize = 12.sp
            )
          }
          
          Column(horizontalAlignment = Alignment.End) {
            Text(
              text = "Form",
              color = Color(0xFF64748B),
              fontSize = 10.sp,
              fontWeight = FontWeight.Bold
            )
            Row {
              repeat(5) { index ->
                Text(
                  text = "★",
                  color = if (index < p.stars) Color(0xFFFFB000) else Color(0xFF475569),
                  fontSize = 12.sp
                )
              }
            }
          }
        }
      }
    }
  }
}

private data class TalentRow(val name: String, val role: String, val stars: Int, val avatarUrl: String)

@Composable
private fun DistrictStatsTabPanel() {
  val stats = listOf(
    StatCardRow("Registered Players", "1,248", Color(0xFF38BDF8)),
    StatCardRow("Active Tournaments", "14", Color(0xFF00C853)),
    StatCardRow("Matches Logged", "3,892", Color(0xFFFFB000)),
    StatCardRow("Total Boundary Fours", "8,410", Color(0xFFF472B6))
  )
  
  Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
    val chunks = stats.chunked(2)
    chunks.forEach { chunk ->
      Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(10.dp)
      ) {
        chunk.forEach { s ->
          Card(
            modifier = Modifier
              .weight(1f)
              .height(90.dp),
            shape = RoundedCornerShape(24.dp),
            colors = CardDefaults.cardColors(containerColor = Color(0xFF102133)),
            border = BorderStroke(1.dp, Color.White.copy(alpha = 0.08f))
          ) {
            Column(
              modifier = Modifier
                .fillMaxSize()
                .padding(12.dp),
              verticalArrangement = Arrangement.Center
            ) {
              Text(
                text = s.label,
                color = Color(0xFF94A3B8),
                fontSize = 11.sp,
                fontWeight = FontWeight.Medium,
                maxLines = 1
              )
              Spacer(modifier = Modifier.height(4.dp))
              Text(
                text = s.value,
                color = s.accentColor,
                fontWeight = FontWeight.Black,
                fontSize = 20.sp
              )
            }
          }
        }
        if (chunk.size < 2) {
          Spacer(modifier = Modifier.weight(1f))
        }
      }
    }
  }
}

private data class StatCardRow(val label: String, val value: String, val accentColor: Color)

