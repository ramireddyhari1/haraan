package com.example.thanna.ui.main

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.foundation.background
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.tween
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.animateIntAsState
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.BoxWithConstraints
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
import androidx.compose.foundation.layout.wrapContentHeight
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.itemsIndexed
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
import androidx.compose.material.icons.filled.Login
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.MyLocation
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.ArrowUpward
import androidx.compose.material.icons.filled.AutoAwesome
import androidx.compose.material.icons.filled.Campaign
import androidx.compose.material.icons.filled.SportsCricket
import androidx.compose.material.icons.filled.SportsFootball
import androidx.compose.material.icons.filled.SportsTennis
import androidx.compose.material.icons.filled.SportsBasketball
import androidx.compose.material.icons.filled.SportsVolleyball
import androidx.compose.material.icons.filled.Notifications
import androidx.compose.material.icons.filled.KeyboardArrowRight
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.filled.Bookmark
import androidx.compose.material.icons.filled.BookmarkBorder
import androidx.compose.material.icons.filled.KeyboardArrowDown
import androidx.compose.material.icons.filled.Movie
import androidx.compose.material.icons.filled.Restaurant
import androidx.compose.material.icons.filled.Mic
import androidx.compose.material.icons.filled.Share
import androidx.compose.material.icons.filled.Info
import androidx.compose.material.icons.filled.TrendingUp
import androidx.compose.material.icons.filled.Public
import androidx.compose.material.icons.filled.Speed
import androidx.compose.material.icons.filled.BarChart
import androidx.compose.material.icons.filled.TrackChanges
import androidx.compose.material.icons.filled.EmojiEvents
import androidx.compose.material.icons.filled.Verified
import androidx.compose.material.icons.outlined.SportsCricket
import androidx.compose.material.icons.outlined.TrackChanges
import androidx.compose.material.icons.outlined.EmojiEvents
import androidx.compose.material.icons.outlined.BarChart
import androidx.compose.material.icons.outlined.Speed
import androidx.compose.material.icons.outlined.TrendingUp
import androidx.compose.material.icons.outlined.LocationOn
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material.icons.outlined.ChatBubbleOutline
import androidx.compose.material.icons.outlined.CalendarMonth
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material.icons.outlined.Public
import androidx.compose.material.icons.outlined.WorkspacePremium
import androidx.compose.material.icons.filled.Apps
import androidx.compose.material.icons.outlined.Apps
import androidx.compose.material.icons.outlined.Home
import androidx.compose.material.icons.outlined.SportsTennis
import androidx.compose.material.icons.outlined.SportsFootball
import androidx.compose.material.icons.filled.Stadium
import androidx.compose.material.icons.filled.Groups
import androidx.compose.material.icons.filled.Whatshot
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.layout.requiredHeightIn
import androidx.compose.material3.TextButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Divider
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
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.ripple
import androidx.compose.ui.text.style.TextOverflow
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.SideEffect
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.blur
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.zIndex
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.IconButton
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.Shadow
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipRect
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.TransformOrigin
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.layout.onGloballyPositioned
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.res.painterResource
import coil.compose.AsyncImage
import coil.compose.AsyncImagePainter
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.example.thanna.EventDetail
import androidx.navigation3.runtime.NavKey
import com.example.thanna.data.DefaultDataRepository
import com.example.thanna.data.BookingRepository
import com.example.thanna.data.BookingResult
import com.example.thanna.data.TokenStore
import kotlinx.coroutines.launch
import kotlinx.coroutines.delay
import android.view.HapticFeedbackConstants
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import com.example.thanna.theme.ThannaTheme
import com.example.thanna.theme.Poppins
import android.widget.Toast
import android.app.Activity
import androidx.core.view.WindowCompat
import com.example.thanna.ui.components.AutoRefresh
import com.example.thanna.ui.components.SectionHeader
import com.example.thanna.ui.theme.HaraanColors
import com.example.thanna.ui.theme.HaraanRadius
import com.example.thanna.ui.theme.premiumCardShadow
import com.example.thanna.ui.theme.overlapAbove
import com.example.thanna.ui.theme.HaraanSpacing
import com.example.thanna.ui.theme.HaraanTypography
import com.example.thanna.ui.components.HaraanSearchBar
import com.example.thanna.ui.components.HaraanSegmentedControl
import com.example.thanna.ui.components.HaraanEventCard
import com.example.thanna.ui.components.HaraanCategoryCard
import com.example.thanna.ui.components.HaraanButton
import com.example.thanna.ui.components.HaraanCard
import com.example.thanna.ui.components.HaraanImage
import com.example.thanna.ui.animations.pressScale
import com.example.thanna.ui.animations.haraanShimmer

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

// ─────────────────────────────────────────────────────────────────────────────
// Design tokens  — one place to change everything
// ─────────────────────────────────────────────────────────────────────────────
private object T {
    // Brand
    val Red = Color(0xFFE8315A)

    // Surfaces
    val BgPage  = Color(0xFFEBEBF0)   // slightly cooler than pure grey — less cheap
    val Surface = Color(0xFFFFFFFF)
    val Divider = Color(0xFFEBEBEF)

    // Type
    val Text1   = Color(0xFF0A0A0A)   // headings, names, scores
    val Text2   = Color(0xFF5A5A6A)   // secondary values (Avg number, HS number)
    val Text3   = Color(0xFF9A9AA8)   // muted  (meta, district, inn)
    val TextTab = Color(0xFFA0A0AE)   // inactive tabs

    // Podium bar tints  (bg + border per rank)
    val Bar1Bg  = Color(0xFFFFF8E6); val Bar1Bdr = Color(0xFFF0D58A)
    val Bar2Bg  = Color(0xFFF5F5F8); val Bar2Bdr = Color(0xFFDCDCE6)
    val Bar3Bg  = Color(0xFFFAF8F5); val Bar3Bdr = Color(0xFFE0D0B8)

    // Rank chip (bg + text per rank)
    val Chip1Bg = Color(0xFFEAB308); val Chip1Tx = Color(0xFF5A3F00)
    val Chip2Bg = Color(0xFFDCDCE6); val Chip2Tx = Color(0xFF3A3A52)
    val Chip3Bg = Color(0xFFE0D0B8); val Chip3Tx = Color(0xFF6E4820)

    // Avatar rings
    val Ring1 = Color(0xFFEAB308)
    val Ring2 = Color(0xFFC5C5D0)
    val Ring3 = Color(0xFFC8A878)
}


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
    com.example.thanna.ui.LoginRoute(
      onSkipClick = {
        com.example.thanna.data.TokenStore.saveToken(context, "skipped_guest")
        cachedToken = "skipped_guest"
      },
      onLoginSuccess = { token ->
        com.example.thanna.data.TokenStore.saveToken(context, token)
        cachedToken = token
      },
      modifier = Modifier.fillMaxSize()
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

// LoginScreen and its local helper methods have been migrated to com.example.thanna.ui package.


private data class TabInfo(val title: String, val icon: androidx.compose.ui.graphics.vector.ImageVector)

@Composable
internal fun MainAppContainer(
  token: String,
  onItemClick: (NavKey) -> Unit,
  onLogout: () -> Unit
) {
  val localContext = LocalContext.current
  // Saveable so returning from a pushed screen (e.g. Match Details) lands back on the
  // SAME place — GameHub/ActionBoard — instead of resetting to the Events/home tab.
  var selectedTab by androidx.compose.runtime.saveable.rememberSaveable { mutableStateOf(0) }
  var searchQuery by remember { mutableStateOf("") }
  var showLogoutDialog by remember { mutableStateOf(false) }
  var isSearchExpanded by remember { mutableStateOf(false) }
  var activeSubTab by androidx.compose.runtime.saveable.rememberSaveable { mutableStateOf("Events") }
  var showActionBoardDetail by androidx.compose.runtime.saveable.rememberSaveable { mutableStateOf(false) }
  // Common account profile (shared by Events + GameHub) + linked cricket profile.
  var showAccountProfile by remember { mutableStateOf(false) }
  var showCricketProfile by remember { mutableStateOf(false) }
  // Header utility icons: bell → notifications, calendar → the account's bookings.
  // Shared by both the Events and GameHub headers so the icons work wherever they appear.
  var showNotifications by remember { mutableStateOf(false) }
  // The booking whose entry-pass QR is currently open (null = none). Set from booking taps.
  var ticketPass by remember { mutableStateOf<com.example.thanna.data.BookingLite?>(null) }
  val accountRepository = remember { com.example.thanna.data.AccountRepository() }
  val playerProfileRepository = remember { com.example.thanna.data.ProfileRepository() }

  // Current user identity for the greeting header (avatar + name), shared by Events + GameHub.
  var accountName by remember { mutableStateOf("") }
  var accountAvatar by remember { mutableStateOf("") }
  LaunchedEffect(token) {
    if (token.isNotBlank()) {
      runCatching { accountRepository.fetchAccount(token) }.getOrNull()?.let {
        accountName = it.name
        accountAvatar = it.avatar ?: ""
      }
    }
  }

  // --- Location button state ---------------------------------------------------------------
  val locationRepository = remember { com.example.thanna.data.LocationRepository(localContext) }
  val locationScope = rememberCoroutineScope()
  var locationState by remember {
    mutableStateOf<com.example.thanna.data.LocationState>(
      locationRepository.cached() ?: com.example.thanna.data.LocationState.Idle
    )
  }
  var showLocationSheet by remember { mutableStateOf(false) }
  // Search radius for nearby content; 0 == "Any distance". (Phase 3)
  var searchRadiusKm by remember { mutableStateOf(10) }
  // Pull the latest city catalog (cities.json) once so the picker + normaliser are current.
  LaunchedEffect(Unit) { runCatching { locationRepository.refreshCatalog() } }

  fun detectLocation() {
    locationState = com.example.thanna.data.LocationState.Locating
    locationScope.launch {
      locationState = locationRepository.detectCurrent()
    }
  }

  val locationPermissionLauncher = rememberLauncherForActivityResult(
    ActivityResultContracts.RequestMultiplePermissions()
  ) { grants ->
    if (grants.values.any { it }) detectLocation()
    else locationState = com.example.thanna.data.LocationState.Denied
  }

  fun requestCurrentLocation() {
    if (locationRepository.hasPermission()) {
      detectLocation()
    } else {
      locationPermissionLauncher.launch(
        arrayOf(
          android.Manifest.permission.ACCESS_FINE_LOCATION,
          android.Manifest.permission.ACCESS_COARSE_LOCATION
        )
      )
    }
  }

  if (showLocationSheet) {
    LocationPickerSheet(
      state = locationState,
      recents = locationRepository.recents(),
      selectedRadiusKm = searchRadiusKm,
      onRadiusChange = { searchRadiusKm = it },
      onUseCurrentLocation = { requestCurrentLocation() },
      onSelectCity = { option ->
        locationState = locationRepository.selectCity(option)
        showLocationSheet = false
      },
      onDismiss = { showLocationSheet = false },
      popularCities = locationRepository.popularCities(),
      allCities = locationRepository.allCities()
    )
  }

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
    if (isEventsTab && !showActionBoardDetail) {
      Box(
        modifier = Modifier
          .fillMaxWidth()
          .height(280.dp)
          .background(
            Brush.verticalGradient(
              colors = listOf(
                Color(0xFF7DD3FC).copy(alpha = 0.45f),
                Color(0xFF38BDF8).copy(alpha = 0.15f),
                Color.Transparent
              )
            )
          )
      )
    }
    if (showActionBoardDetail) {
      DistrictActionBoardScreen(
        onBack = { showActionBoardDetail = false },
        onMatchClick = { matchId -> onItemClick(com.example.thanna.MatchDetails(id = matchId)) },
        onJoinByCode = { code -> onItemClick(com.example.thanna.MatchDetails(code = code)) },
        onHomeClick = {
          showActionBoardDetail = false
          activeSubTab = "GameHub"
        }
      )
    } else {
      val isGameHubTab = (selectedTab == 0 && activeSubTab == "GameHub")
      Column(modifier = Modifier.fillMaxSize()) {
        if (!isGameHubTab) {
          Column(
            modifier = Modifier
              .fillMaxWidth()
              .background(
                if (isEventsTab) {
                  Brush.verticalGradient(
                    colors = listOf(
                      Color(0xFFE0F2FE), // Light sky blue top
                      Color.White        // Blends into white content area
                    )
                  )
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
              .padding(start = 16.dp, end = 16.dp, top = 20.dp, bottom = 8.dp)
          ) {
            // Personalized greeting header on the light Events surface.
            GreetingHeader(
              name = accountName,
              avatarUrl = accountAvatar,
              locationState = locationState,
              onDark = false,
              onAvatarClick = { showAccountProfile = true },
              onLocationClick = { showLocationSheet = true },
              onChatClick = { onItemClick(com.example.thanna.SupportChat) },
              onNotificationClick = { showNotifications = true },
              onCalendarClick = { showAccountProfile = true },
            )

            Spacer(modifier = Modifier.height(14.dp))

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
                  fontFamily = Poppins,
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
              .padding(start = 16.dp, end = 16.dp, top = 20.dp, bottom = 8.dp),
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
                    fontFamily = Poppins,
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
                  .clickable { showAccountProfile = true },
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
              Box(
                modifier = Modifier.weight(1f),
                contentAlignment = Alignment.CenterStart
              ) {
                LocationPill(
                  state = locationState,
                  expanded = showLocationSheet,
                  style = LocationPillStyle.Home,
                  onClick = { showLocationSheet = true }
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
                    .clickable { showAccountProfile = true },
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
          PremiumSegmentedSwitch(
            selectedTab = activeSubTab,
            onTabSelected = { activeSubTab = it }
          )
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
                currentCity = (locationState as? com.example.thanna.data.LocationState.Resolved)?.city?.trim().orEmpty(),
                onEventClick = { event ->
                  onItemClick(
                    EventDetail(
                      id = event.id,
                      title = event.title,
                      date = event.date,
                      venue = event.venue,
                      price = event.price,
                      category = event.category,
                      imageUrl = event.imageUrl,
                      description = eventDescription(event.category, event.title, event.venue),
                      bookedThisWeek = eventBookedThisWeek(event.id),
                      infoNotes = listOf(
                        "Valid ID required at entry.",
                        "Standard safety guidelines apply.",
                        "Tickets are non-refundable.",
                      ),
                      organizer = "Haraan Events",
                      organizerSubtitle = "Verified ticketing partner",
                    )
                  )
                }
              )
            } else {
              GameHubTabScreen(
                searchQuery = searchQuery,
                onSearchQueryChange = { searchQuery = it },
                onProfileClick = { showAccountProfile = true },
                activeSubTab = activeSubTab,
                onTabSelected = { activeSubTab = it },
                onActionBoardClick = { showActionBoardDetail = true },
                locationState = locationState,
                showLocationSheet = showLocationSheet,
                onLocationClick = { showLocationSheet = true },
                searchRadiusKm = searchRadiusKm,
                onLocateClick = { requestCurrentLocation() },
                onMatchClick = { id -> onItemClick(com.example.thanna.MatchDetails(id)) },
                onSupportClick = { onItemClick(com.example.thanna.SupportChat) },
                onNotificationsClick = { showNotifications = true },
                onCalendarClick = { showAccountProfile = true },
                onVenueClick = { v ->
                  onItemClick(
                    com.example.thanna.VenueDetail(
                      id = v.id, title = v.title, location = v.location, rating = v.rating,
                      category = v.category, price = v.price, imageUrl = v.imageUrl,
                      tagline = v.tagline, distance = v.distance
                    )
                  )
                },
                userName = accountName,
                avatarUrl = accountAvatar
              )
            }
          }
          1 -> LeaderboardTabScreen()
        }
      }
    }

    if (showAccountProfile) {
      com.example.thanna.ui.profile.AccountProfileScreen(
        onClose = { showAccountProfile = false },
        fetchAccount = { accountRepository.fetchAccount(token) },
        fetchBookings = { accountRepository.fetchBookings(token) },
        onOpenPlayerProfile = { showCricketProfile = true },
        onOpenPass = { b -> ticketPass = b },
        onSignOut = { showAccountProfile = false; onLogout() },
        modifier = Modifier.statusBarsPadding(),
      )
    }

    if (showCricketProfile) {
      com.example.thanna.ui.profile.PlayerProfileScreen(
        onBack = { showCricketProfile = false },
        fetchProfile = { playerProfileRepository.fetchMe(token) },
        modifier = Modifier.statusBarsPadding(),
      )
    }

    // Header bell → notifications. The calendar icon used to open a separate
    // "My Schedule" sheet over the same /api/bookings data; bookings now live in
    // exactly one place, so it opens the account's Tickets lane instead.
    if (showNotifications) {
      NotificationsSheet(onDismiss = { showNotifications = false })
    }
    // Full-screen entry pass (QR) for a tapped booking — shown above everything else.
    ticketPass?.let { b ->
      TicketPassScreen(booking = b, onClose = { ticketPass = null })
    }
  }
}
}



@Composable
private fun EventsTabScreen(
  searchQuery: String,
  currentCity: String = "",
  onEventClick: (EventItem) -> Unit
) {
  var selectedCategory by remember { mutableStateOf("All") }
  val localContext = LocalContext.current
  
  val bannerEvents = listOf(
    BannerItem(
      "13",
      "Karthik Live In Hyderabad",
      "Quake Arena, Kondapur, Hyderabad",
      "Sat, 13 Jun • 7:00 PM",
      "₹2,999 onwards",
      "https://media.insider.in/image/upload/c_crop,g_custom,q_auto/v1777470598/ikf8alfkn0vutbb1ugxz.jpg"
    ),
    BannerItem(
      "14",
      "Amaal Mallik Live at Quake Arena",
      "Quake | Kondapur, Hyderabad",
      "Fri, 12 Jun • 7:00 PM",
      "₹599 onwards",
      "https://media.insider.in/image/upload/c_crop,g_custom,q_auto/v1778825095/gvfqqsvogxamj88yijun.jpg"
    ),
    BannerItem(
      "15",
      "Saturday Soiree ft. Merakee Live",
      "Raasta | Hitech City, Hyderabad",
      "Sat, 13 Jun • 9:30 PM",
      "₹1,000 onwards",
      "https://cdn.district.in/assets/events/publisher/event_cover_image_horizontal/01KTEMTANYJC4WW9B7XGPZKQM3.png"
    ),
    BannerItem(
      "16",
      "Tribute to Arijit Singh (Edition 2) Ft. Root 35",
      "Hard Rock Cafe | GVK One Mall, Banjara Hills",
      "Sat, 27 Jun • 9:00 PM",
      "₹249 onwards",
      "https://cdn.district.in/assets/events/publisher/event_cover_image_horizontal/01KS7X3SFGYW02JHNBXEYYB7KJ.jpg"
    )
  )

  val spotlightItems = listOf(
    SpotlightItem(
      "1",
      "Dune: Part Three",
      "Denis Villeneuve's highly anticipated sci-fi continuation.",
      "Now in theatres",
      "https://images.unsplash.com/photo-1536440136628-849c177e76a1?w=500&q=80"
    ),
    SpotlightItem(
      "2",
      "Spider-Man: Beyond the Spider-Verse",
      "Miles Morales returns in the epic conclusion to the trilogy.",
      "Now in theatres",
      "https://images.unsplash.com/photo-1635805737707-575885ab0820?w=500&q=80"
    ),
    SpotlightItem(
      "3",
      "The Batman Part II",
      "Matt Reeves' dark thriller continuation in Gotham City.",
      "Now in theatres",
      "https://images.unsplash.com/photo-1509248961158-e54f6934749c?w=500&q=80"
    ),
    SpotlightItem(
      "4",
      "Interstellar (70mm Re-release)",
      "Experience Christopher Nolan's space masterpiece again on the big screen.",
      "Now in theatres",
      "https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=500&q=80"
    )
  )
  
  // Bundled samples — only shown if the API is empty/unreachable so the tab is never blank.
  val sampleEvents = listOf(
    EventItem("13", "Karthik Live In Hyderabad", "Sat, 13 Jun, 7:00 PM", "Quake Arena, Kondapur", "₹2999 onwards", "Concerts", "https://media.insider.in/image/upload/c_crop,g_custom,q_auto/v1777470598/ikf8alfkn0vutbb1ugxz.jpg", isFillingFast = true),
    EventItem("14", "Amaal Mallik Live at Quake Arena", "Fri, 12 Jun, 7:00 PM", "Quake Arena, Kondapur", "₹599 onwards", "Concerts", "https://media.insider.in/image/upload/c_crop,g_custom,q_auto/v1778825095/gvfqqsvogxamj88yijun.jpg", isFillingFast = true),
    EventItem("15", "Saturday Soiree ft. Merakee Live", "Sat, 13 Jun, 9:30 PM", "Raasta, Hitech City", "₹1000 onwards", "Concerts", "https://cdn.district.in/assets/events/publisher/event_cover_image_horizontal/01KTEMTANYJC4WW9B7XGPZKQM3.png", isFillingFast = true),
    EventItem("16", "Tribute to Arijit Singh (Ed. 2) Ft. Root 35", "Sat, 27 Jun, 9:00 PM", "Hard Rock Cafe, Banjara Hills", "₹249 onwards", "Concerts", "https://cdn.district.in/assets/events/publisher/event_cover_image_horizontal/01KS7X3SFGYW02JHNBXEYYB7KJ.jpg", isFillingFast = true),
    EventItem("17", "Bassi Live - Show", "Sun, 14 Jun, 6:00 PM", "Shilpakala Vedika, Madhapur", "₹999 onwards", "Comedy", "https://images.unsplash.com/photo-1516280440614-37939bbacd6a?w=500&q=80", isFillingFast = true),
  )

  // Events tab is backend-driven: pull real events (real ids → real booking) from
  // GET /api/events, mapped to the card model. Falls back to samples on empty/failure.
  var eventsData by remember { mutableStateOf(sampleEvents) }
  val eventRepo = remember { com.example.thanna.data.EventRepository() }
  val loadEvents: suspend () -> Unit = remember {
    {
      val fetched = runCatching { eventRepo.getEvents() }.getOrNull().orEmpty()
      if (fetched.isNotEmpty()) {
        eventsData = fetched.map {
          EventItem(it.id, it.title, it.date, it.venue, it.price, it.category, it.imageUrl, it.isFillingFast, it.rating, it.placements, it.city)
        }
      }
    }
  }
  LaunchedEffect(Unit) { loadEvents() }
  // Refresh events when the tab is re-focused / app returns to foreground, and every
  // 30s while open — so newly published events appear without reopening the app.
  AutoRefresh(intervalMs = 30_000L) { loadEvents() }

  val filteredEvents = eventsData.filter {
    val matchesSearch = searchQuery.isEmpty() || it.title.contains(searchQuery, ignoreCase = true) || it.venue.contains(searchQuery, ignoreCase = true)
    val matchesCategory = when (selectedCategory) {
      "Concerts" -> it.category == "Concerts"
      "Standup" -> it.category == "Comedy"
      else -> true
    }
    matchesSearch && matchesCategory
  }.let { list ->
    // Local-first: float events in the user's city to the top (stable — original
    // order preserved within each group). Still shows every event. No-op when the
    // user hasn't set a location.
    if (currentCity.isBlank()) list
    else list.sortedByDescending { it.city.equals(currentCity, ignoreCase = true) }
  }

  // Sponsored ad from the Filament admin (GET /api/ads?placement=events);
  // falls back to the bundled sample creative if unset/unreachable.
  val liveAd by produceState(initialValue = sampleChatGptAd) {
    val ads = runCatching { com.example.thanna.data.ContentRepository().getAds("events") }.getOrNull()
    ads?.firstOrNull()?.let { a ->
      value = sampleChatGptAd.copy(
        advertiser = a.sponsor ?: a.title,
        tagline = a.subtitle ?: sampleChatGptAd.tagline,
        headline = a.title,
        ctaLabel = a.ctaText ?: sampleChatGptAd.ctaLabel,
        clickUrl = a.ctaUrl ?: sampleChatGptAd.clickUrl,
        imageUrl = a.image ?: sampleChatGptAd.imageUrl,
        promptText = a.title,
      )
    }
  }

  val feedListState = rememberLazyListState()
  // How far the ad (list item 0) has scrolled toward the top edge, 0f..1f.
  // While item 0 is still visible we map its scroll offset over its own height;
  // once it's scrolled past, the fold stays fully committed at 1f.
  var adHeightPx by remember { mutableStateOf(0) }
  val foldProgress by remember {
    derivedStateOf {
      if (feedListState.firstVisibleItemIndex > 0) 1f
      else if (adHeightPx <= 0) 0f
      else (feedListState.firstVisibleItemScrollOffset.toFloat() / adHeightPx).coerceIn(0f, 1f)
    }
  }

  androidx.compose.foundation.lazy.LazyColumn(
    state = feedListState,
    modifier = Modifier
      .fillMaxSize()
      .background(HaraanColors.Background),
    verticalArrangement = Arrangement.spacedBy(16.dp)
  ) {
    // 1. Sponsored ad slot (replaces the greeting header).
    item {
      AdSpaceBanner(
        creative = liveAd,
        modifier = Modifier
          .padding(horizontal = 16.dp, vertical = 12.dp)
          .onGloballyPositioned { adHeightPx = it.size.height },
        foldProgress = foldProgress,
        onImpression = { android.util.Log.d("Ad", "impression: ${it.advertiser}") }
      )
    }

    // Per-rail placement: an event shows in a rail when its admin-set `placements`
    // include that rail's key. Empty placements (older/untagged events) show
    // everywhere. The .ifEmpty guard keeps a rail from ever rendering blank.
    fun showsIn(rail: String, e: EventItem) = e.placements.isEmpty() || rail in e.placements
    val forYouEvents = filteredEvents.filter { showsIn("for_you", it) }.ifEmpty { filteredEvents }
    val trendingEvents = filteredEvents.filter { showsIn("trending", it) }.ifEmpty { filteredEvents }
    val nearbyEvents = filteredEvents.filter { showsIn("nearby", it) }.ifEmpty { filteredEvents }

    // 2. Hero Section: "Featured For You" with Infinite 3D Loop Pager
    item {
      SectionHeader(
        title = "For You"
      )
    }

    item {
      InfiniteLoopBookPager(events = forYouEvents, onEventClick = onEventClick)
    }

    // 3. Trending Section
    item {
      SectionHeader(
        title = "Trending"
      )
    }
    item {
      TrendingRowSection(events = trendingEvents, onEventClick = onEventClick)
    }

    // 4. Categories Section (Concerts, Standup, All)
    item {
      SectionHeader(
        title = "Categories"
      )
    }

    item {
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .padding(horizontal = 16.dp),
        horizontalArrangement = Arrangement.spacedBy(10.dp)
      ) {
        HaraanCategoryCard(
          title = "Concerts",
          statText = "245 Events",
          painter = painterResource(id = com.example.thanna.R.drawable.ic_live_music),
          selected = selectedCategory == "Concerts",
          onClick = { selectedCategory = "Concerts" },
          activeColor = HaraanColors.EventsBlue,
          modifier = Modifier.weight(1f)
        )
        HaraanCategoryCard(
          title = "Standup",
          statText = "54 Shows",
          painter = painterResource(id = com.example.thanna.R.drawable.ic_standup_comedy),
          selected = selectedCategory == "Standup",
          onClick = { selectedCategory = "Standup" },
          activeColor = HaraanColors.EventsBlue,
          modifier = Modifier.weight(1f)
        )
        HaraanCategoryCard(
          title = "All",
          statText = "310 Total",
          painter = painterResource(id = com.example.thanna.R.drawable.ic_select_all),
          selected = selectedCategory == "All",
          onClick = { selectedCategory = "All" },
          activeColor = HaraanColors.EventsBlue,
          modifier = Modifier.weight(1f)
        )
      }
    }

    // 5. Nearby Section / Grid Listings
    item {
      SectionHeader(
        title = "Explore Nearby",
        subtitle = "Handpicked experiences"
      )
    }

    if (nearbyEvents.isEmpty()) {
      item {
        Column(
          modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 40.dp, horizontal = 24.dp),
          horizontalAlignment = Alignment.CenterHorizontally,
          verticalArrangement = Arrangement.Center
        ) {
          Text(
            text = "No events found nearby",
            style = HaraanTypography.TitleMedium,
            color = HaraanColors.TextPrimary
          )
          Spacer(modifier = Modifier.height(8.dp))
          Text(
            text = "Try searching in adjacent cities or browse all categories.",
            style = HaraanTypography.BodyMedium,
            color = HaraanColors.TextSecondary,
            textAlign = TextAlign.Center
          )
          Spacer(modifier = Modifier.height(16.dp))
          HaraanButton(
            text = "Explore Vijayawada",
            onClick = { selectedCategory = "All" },
            containerColor = HaraanColors.EventsBlue
          )
        }
      }
    } else {
      val eventChunks = nearbyEvents.chunked(2)
      items(eventChunks.size) { i ->
        val chunk = eventChunks[i]
        Row(
          modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp),
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

    // 6. Continue Exploring Section
    item {
      SectionHeader(
        title = "Continue Exploring"
      )
    }

    item {
      LazyRow(
        contentPadding = PaddingValues(horizontal = 16.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier.fillMaxWidth()
      ) {
        item {
          ContinueExploringCard(
            title = "Saved Events",
            subtitle = "3 items saved",
            icon = Icons.Default.Bookmark,
            backgroundColor = Color(0xFFEFF6FF),
            iconColor = HaraanColors.EventsBlue,
            onClick = { /* action */ }
          )
        }
        item {
          ContinueExploringCard(
            title = "Recently Viewed",
            subtitle = "Karthik Live In Hyd...",
            icon = Icons.Default.Movie,
            backgroundColor = Color(0xFFF0FDF4),
            iconColor = HaraanColors.GameHubGreen,
            onClick = { /* action */ }
          )
        }
        item {
          ContinueExploringCard(
            title = "Top Venues",
            subtitle = "Search near you",
            icon = Icons.Default.LocationOn,
            backgroundColor = Color(0xFFFFF7ED),
            iconColor = Color(0xFFF97316),
            onClick = { /* action */ }
          )
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


@Immutable
private data class EventItem(
  val id: String,
  val title: String,
  val date: String,
  val venue: String,
  val price: String,
  val category: String,
  val imageUrl: String,
  val isFillingFast: Boolean = false,
  val rating: Double = 0.0,
  val placements: List<String> = emptyList(),
  val city: String = ""
)

// Category-aware blurb so the detail page reads about THIS event instead of one
// hardcoded music paragraph. (Replace with real backend copy when available.)
private fun eventDescription(category: String, title: String, venue: String): String = when {
  category.contains("Comedy", true) || category.contains("Standup", true) ->
    "A live stand-up night with $title at $venue. Expect sharp sets, crowd work and a packed house — come early for the best seats."
  category.contains("Concert", true) || category.contains("Music", true) ->
    "$title brings a full live production to $venue — a night of hits, lights and energy. Tickets are limited, so grab yours before they sell out."
  category.contains("Sport", true) || category.contains("Cricket", true) || category.contains("Turf", true) ->
    "Book your slot for $title at $venue. Quality turf, floodlights and gear on request — perfect for an evening game with your crew."
  else ->
    "Join $title at $venue for a memorable live experience. Reserve your spot now — popular dates fill up fast."
}

// Stable, varied "booked this week" count derived from the event id (so it's not a
// flat literal on every page). Swap for a real popularity metric when wired up.
private fun eventBookedThisWeek(id: String): Int = 40 + (kotlin.math.abs(id.hashCode()) % 160)

@Composable
private fun EventListCard(
  event: EventItem,
  modifier: Modifier = Modifier,
  onClick: () -> Unit
) {
  Column(
    modifier = modifier
      .fillMaxWidth()
      .clip(RoundedCornerShape(16.dp))
      .clickable(
        interactionSource = remember { MutableInteractionSource() },
        indication = ripple(color = Color(0xFF1A202C).copy(alpha = 0.08f)),
        onClick = onClick
      )
  ) {
    // Image Section
    Box(
      modifier = Modifier
        .fillMaxWidth()
        .aspectRatio(0.9f) // Tight, intentional modern image box
        .clip(RoundedCornerShape(16.dp))
        .background(Color(0xFFF7F7F9))
    ) {
      AsyncImage(
        model = event.imageUrl,
        contentDescription = event.title,
        contentScale = ContentScale.Crop,
        modifier = Modifier.fillMaxSize()
      )

      // High-End Frosted Dark Badge (Sleek, minimalist, premium app execution)
      if (event.isFillingFast) {
        Box(
          modifier = Modifier
            .padding(10.dp)
            .background(
              color = Color.Black.copy(alpha = 0.75f), // Dark premium overlay
              shape = RoundedCornerShape(6.dp) // Subtle curve matches high-end tech layouts
            )
            .border(
              width = 0.5.dp, 
              color = Color.White.copy(alpha = 0.15f), 
              shape = RoundedCornerShape(6.dp)
            )
            .padding(horizontal = 8.dp, vertical = 4.dp)
            .align(Alignment.TopStart)
        ) {
          Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(4.dp)
          ) {
            Text(
              text = "⚡",
              fontSize = 10.sp,
              color = Color(0xFFF6AD55) // Clean premium amber tint
            )
            Text(
              text = "FILLING FAST",
              fontSize = 9.sp,
              fontWeight = FontWeight.Bold,
              color = Color.White,
              letterSpacing = 0.5.sp
            )
          }
        }
      }
    }

    // Compressed Content Layer - Eliminates large layout gaps
    Column(
      modifier = Modifier
        .fillMaxWidth()
        .padding(top = 8.dp, start = 2.dp, end = 2.dp) // Tightened entry gap
    ) {
      // 1. Date
      Text(
        text = event.date.uppercase(),
        fontSize = 10.5.sp,
        fontWeight = FontWeight.Bold,
        color = LightAccentBlue, // Highlight with blue
        letterSpacing = 0.4.sp,
        maxLines = 1,
        overflow = TextOverflow.Ellipsis
      )

      // Extremely tight spacing to lock text groups together
      Spacer(modifier = Modifier.height(2.dp))

      // 2. Title - Dynamic wrap content to completely eliminate artificial blank spaces under 1-line titles
      Text(
        text = event.title,
        fontSize = 14.5.sp,
        fontWeight = FontWeight.Bold,
        color = Color(0xFF1A202C), // Ink Black
        lineHeight = 18.sp,
        maxLines = 2,
        overflow = TextOverflow.Ellipsis,
        modifier = Modifier.fillMaxWidth()
      )

      Spacer(modifier = Modifier.height(2.dp))

      // 3. Venue
      Text(
        text = event.venue,
        fontSize = 12.5.sp,
        fontWeight = FontWeight.Normal,
        color = Color(0xFF4A5568),
        maxLines = 1,
        overflow = TextOverflow.Ellipsis
      )

      Spacer(modifier = Modifier.height(3.dp))

      // 4. Price Layout
      Row(
        verticalAlignment = Alignment.Bottom,
        horizontalArrangement = Arrangement.spacedBy(3.dp)
      ) {
        val priceDisplay = if (event.price.endsWith(" onwards")) {
          event.price.substringBefore(" onwards")
        } else {
          event.price
        }
        val hasOnwards = event.price.endsWith(" onwards")

        Text(
          text = priceDisplay,
          fontSize = 14.sp,
          fontWeight = FontWeight.ExtraBold,
          color = LightAccentBlue, // Highlight with blue
          letterSpacing = (-0.2).sp
        )
        if (hasOnwards) {
          Text(
            text = "onwards",
            fontSize = 11.sp,
            fontWeight = FontWeight.Normal,
            color = LightAccentBlue.copy(alpha = 0.8f), // Highlight with blue
            modifier = Modifier.padding(bottom = 0.5.dp) // Precision alignment to currency baseline
          )
        }
      }
    }
  }
}








// Data models initialization structures
private data class Performer(val name: String, val type: String)

private val performersList = listOf(
  Performer("Main Artist", "Singer"),
  Performer("Supporting Act", "Band"),
  Performer("Opening DJ", "Musician")
)

@Composable
private fun GameHubTabScreen(
  searchQuery: String,
  onSearchQueryChange: (String) -> Unit,
  onProfileClick: () -> Unit,
  activeSubTab: String,
  onTabSelected: (String) -> Unit,
  onActionBoardClick: () -> Unit,
  locationState: com.example.thanna.data.LocationState,
  showLocationSheet: Boolean,
  onLocationClick: () -> Unit,
  searchRadiusKm: Int = 10,
  onLocateClick: () -> Unit = {},
  onMatchClick: (String) -> Unit = {},
  onVenueClick: (VenueItem) -> Unit = {},
  onSupportClick: () -> Unit = {},
  onNotificationsClick: () -> Unit = {},
  onCalendarClick: () -> Unit = {},
  userName: String = "",
  avatarUrl: String = ""
) {
  var selectedSport by remember { mutableStateOf("All") }

  // Full-screen search overlay (Phase 2): recents are kept for the session.
  var showSearch by remember { mutableStateOf(false) }
  var recentSearches by remember { mutableStateOf(listOf<String>()) }

  val sports = listOf("All", "Cricket", "Football", "Badminton", "Basketball")
  
  // Real live matches for the ActionBoard card — no scripted/mock fallback. Empty when
  // nothing is live, so the card shows an honest "no live matches" state instead. Loaded in
  // the main effect below so it shares the Reverb collector (refreshes on a "matches" push).
  val gameHubCtx = LocalContext.current
  var liveMatches by remember { mutableStateOf<List<com.example.thanna.data.LiveMatchRow>>(emptyList()) }

  // Live venues from the Filament admin (GET /api/venues). null = still loading (shows a
  // skeleton); an empty list after load means "none / unreachable" — an honest empty state.
  // No fake seed data: the old fallback showed Mumbai turfs (Andheri/Powai/…) with bogus
  // distances to every district user on first paint and on any network failure.
  var venuesData by remember { mutableStateOf<List<VenueItem>?>(null) }
  // Phase 0 — server-driven home layout (GET /api/home/layout). Empty/failed fetch keeps the
  // built-in order, so the screen is unchanged offline or against an old backend.
  var remoteBlocks by remember { mutableStateOf<List<com.example.thanna.data.HomeBlock>>(emptyList()) }
  // Curated feed (For You / Trending) + home ad strip — only rendered when the remote layout
  // includes a feed_section / ad_strip block, so they cost nothing when those blocks are absent.
  var feedSections by remember { mutableStateOf<Map<String, List<com.example.thanna.data.FeedCard>>>(emptyMap()) }
  var homeAds by remember { mutableStateOf<List<com.example.thanna.data.AdItem>>(emptyList()) }
  // Loaders hoisted so three refresh triggers can share them: the initial load, the
  // Reverb push (admin/content edits), and AutoRefresh (screen re-focus / app foreground
  // / periodic tick). remember{} keeps one instance each; they capture the MutableState
  // setters above, which are stable across recomposition.
  val venueRepo = remember { com.example.thanna.data.VenueRepository() }
  val layoutRepo = remember { com.example.thanna.data.HomeLayoutRepository() }
  val contentRepo = remember { com.example.thanna.data.ContentRepository() }
  val matchRepo = remember { com.example.thanna.data.MatchRepository() }
  val loadVenues: suspend () -> Unit = remember {
    {
      val api = runCatching { venueRepo.getVenues() }.getOrNull()
      // Always assign (even on failure → emptyList) so `null` strictly means "still loading"
      // and the skeleton gives way to a real list or an honest empty state.
      venuesData = api?.map { c ->
        VenueItem(
          id = c.id, title = c.name, location = c.location, rating = c.rating,
          category = c.category, sports = c.sports, price = c.price,
          imageUrl = c.image ?: venueCategoryImage(c.category),
          tagline = c.tagline, distance = c.distance, availableTonight = true
        )
      } ?: emptyList()
    }
  }
  val loadLive: suspend () -> Unit = remember {
    {
      val token = com.example.thanna.data.TokenStore.getToken(gameHubCtx)
      liveMatches = runCatching { matchRepo.getLiveMatches(token) }
        .getOrDefault(emptyList())
        .filter { it.isLive }
    }
  }
  val loadLayout: suspend () -> Unit = remember {
    { remoteBlocks = layoutRepo.fetch(com.example.thanna.data.TokenStore.getToken(gameHubCtx)) }
  }
  val loadFeed: suspend () -> Unit = remember {
    { feedSections = runCatching { contentRepo.getFeed() }.getOrDefault(emptyMap()) }
  }
  val loadAds: suspend () -> Unit = remember {
    { homeAds = runCatching { contentRepo.getAds("home") }.getOrDefault(emptyList()) }
  }
  LaunchedEffect(Unit) {
    loadVenues()
    loadLayout()
    loadFeed()
    loadAds()
    loadLive()
    // Live: re-pull when /control pushes a change over Reverb. `config` (incl. the
    // server_driven_home flag) is refreshed globally by RealtimeClient, which recomposes us.
    // Feed/ads don't broadcast their own domain, so we refresh them alongside the layout.
    com.example.thanna.data.RealtimeBus.updates.collect { domain ->
      when (domain) {
        "home" -> { loadLayout(); loadFeed(); loadAds() }
        "venues" -> loadVenues()
        // Keep the ActionBoard live score fresh when a match/score is broadcast.
        "matches", "live" -> loadLive()
      }
    }
  }
  // No-manual-refresh: re-pull the volatile data (live scores, venue availability)
  // whenever the user returns to this screen or the app comes back to the foreground,
  // and tick every 20s while it's on-screen. Paused entirely in the background.
  AutoRefresh(intervalMs = 20_000L) {
    loadLive()
    loadVenues()
  }

  val isVenuesLoading = venuesData == null
  val sportSearchVenues = venuesData.orEmpty().filter {
    (selectedSport == "All" || it.category == selectedSport) &&
    (searchQuery.isEmpty() || it.title.contains(searchQuery, ignoreCase = true) || it.location.contains(searchQuery, ignoreCase = true))
  }
  // Surface venues in the chosen city first; never hide everything if none match
  // (backend locations are neighbourhoods, so a city mismatch shouldn't empty the screen).
  val selectedCity = (locationState as? com.example.thanna.data.LocationState.Resolved)?.city
  val filteredVenues = if (selectedCity.isNullOrBlank()) {
    sportSearchVenues
  } else {
    val (near, rest) = sportSearchVenues.partition { it.location.contains(selectedCity, ignoreCase = true) }
    near + rest
  }

  androidx.compose.foundation.lazy.LazyColumn(
    modifier = Modifier
      .fillMaxSize()
      // Cooler/deeper slate than the old slate-50 (0xFFF8FAFC). The white cards were
      // near-invisible against that — this gives them a surface to lift off so the page
      // reads as distinct cards, not one merged sheet.
      .background(Color(0xFFE9EEF4)),
    verticalArrangement = Arrangement.spacedBy(16.dp)
  ) {
    // 1. Dark Green Header Section (edge-to-edge)
    item {
      Box(
        modifier = Modifier
          .fillMaxWidth()
          // Clip the whole node so every draw layer (incl. the sheen below) respects
          // the rounded bottom — otherwise the rectangular drawBehind squares it off.
          .clip(RoundedCornerShape(bottomStart = 32.dp, bottomEnd = 32.dp))
          // Vertical gradient gives the hero depth instead of a flat green fill.
          .background(
            brush = Brush.verticalGradient(
              colors = listOf(Color(0xFF0F3D15), Color(0xFF1B5E20), Color(0xFF227A2B))
            ),
            shape = RoundedCornerShape(bottomStart = 32.dp, bottomEnd = 32.dp)
          )
          // Top sheen — a soft radial highlight so the header reads as a lit surface
          // with depth, not a flat green slab.
          .drawBehind {
            drawRect(
              Brush.radialGradient(
                colors = listOf(Color.White.copy(alpha = 0.10f), Color.Transparent),
                center = Offset(size.width * 0.5f, 0f),
                radius = size.width * 0.95f
              )
            )
          }
      ) {
        // Brand watermark — giant faint "H" monogram bleeding off the top-right corner.
        // Identity-as-texture: kills the empty feel without competing with content.
        Image(
          painter = painterResource(id = com.example.thanna.R.drawable.haraan_copy),
          contentDescription = null,
          contentScale = ContentScale.Fit,
          colorFilter = androidx.compose.ui.graphics.ColorFilter.tint(Color.White),
          modifier = Modifier
            .align(Alignment.TopEnd)
            .offset(x = 58.dp, y = (-44).dp)
            .size(210.dp)
            .alpha(0.11f)
        )
      Column(
        modifier = Modifier
          .statusBarsPadding()
          .padding(start = 16.dp, end = 16.dp, top = 12.dp, bottom = 16.dp) // Tightened hero so the first venue card peeks above the fold (8pt grid)
      ) {
        // Personalized greeting header (avatar + name + location + utility icons) on the
        // dark hero. Replaces the old location-pill / bell / profile row.
        GreetingHeader(
          name = userName,
          avatarUrl = avatarUrl,
          locationState = locationState,
          onDark = true,
          onAvatarClick = onProfileClick,
          onLocationClick = onLocationClick,
          onChatClick = onSupportClick,
          onNotificationClick = onNotificationsClick,
          onCalendarClick = onCalendarClick,
        )

        Spacer(modifier = Modifier.height(16.dp))

        // Search Bar (using HaraanSearchBar) — tapping opens the full-screen overlay.
        val voiceCtx = androidx.compose.ui.platform.LocalContext.current
        Box(modifier = Modifier.fillMaxWidth()) {
          HaraanSearchBar(
            query = searchQuery,
            onQueryChange = onSearchQueryChange,
            placeholder = "Search grounds, matches, players...",
            activeColor = HaraanColors.GameHubGreen,
            modifier = Modifier.fillMaxWidth(),
            rotatingHints = listOf(
              "Search grounds near you",
              "Search live matches",
              "Search players",
              "Search venues"
            ),
            onVoiceClick = { showSearch = true }
          )
          // Transparent tap layer — the header bar behaves as a button into search.
          Box(
            modifier = Modifier
              .matchParentSize()
              .clip(RoundedCornerShape(HaraanRadius.Large))
              .clickable(
                interactionSource = remember { MutableInteractionSource() },
                indication = null
              ) { showSearch = true }
          )
        }

        if (showSearch) {
          Dialog(
            onDismissRequest = { showSearch = false },
            properties = DialogProperties(usePlatformDefaultWidth = false)
          ) {
            GameHubSearchOverlay(
              query = searchQuery,
              onQueryChange = onSearchQueryChange,
              venues = venuesData.orEmpty(),
              recents = recentSearches,
              onRecentsChange = { recentSearches = it },
              onVenueClick = { showSearch = false; onVenueClick(it) },
              onMatchClick = { showSearch = false; onMatchClick(it) },
              onDismiss = { showSearch = false },
              accent = HaraanColors.GameHubGreen
            )
          }
        }

        Spacer(modifier = Modifier.height(12.dp))

        // Tab switcher — moved below the search bar
        GameHubSegmentedSwitch(
          selectedTab = activeSubTab,
          onTabSelected = onTabSelected,
        )

        // Hero ends after the switch — the dense ActionBoard moved out of the green band
        // (below) so the band stays a clean three-element backdrop, not a crammed slab.
        // Tight gap here (the ActionBoard card overlaps 32dp up onto the seam anyway) so the
        // green band doesn't leave a dead void under the switch.
        Spacer(modifier = Modifier.height(HaraanSpacing.Small))
      }
      }
    }

    // ── Server-driven content blocks ─────────────────────────────────────────────
    // Each entry renders one home block. Order comes from /control (GET /api/home/layout)
    // when `server_driven_home` is on and the fetch succeeded; otherwise the built-in order
    // below — so the screen is byte-for-byte unchanged when remote layout is off, empty, or
    // unreachable. Types we don't render yet (hero/feed_section/ad_strip) resolve to null in
    // the map and are skipped, so the backend can add blocks that an old app safely ignores.
    // Order is resolved up-front so each block knows its position. Remote layout wins when the
    // server_driven_home flag is on and the fetch succeeded; otherwise this built-in order:
    // ActionBoard hugs the bottom of the hero (straddling the green seam), then the Top Player
    // leaderboard, then the sport chips, then venues. Chips no longer lead — that caused the
    // ActionBoard's seam-straddle to overlap them (the mess seen on device); the server layout
    // is kept in sync with this order so both paths look identical.
    val useRemoteLayout = com.example.thanna.data.RemoteConfigStore.isEnabled("server_driven_home") &&
      remoteBlocks.isNotEmpty()
    val orderedBlocks: List<com.example.thanna.data.HomeBlock> =
      if (useRemoteLayout) remoteBlocks
      else listOf("actionboard", "leaderboard", "sports_chips", "venues")
        .map { com.example.thanna.data.HomeBlock(id = it, type = it, title = null) }
    // The seam-straddle (overlapAbove) only makes sense when ActionBoard is the very first block,
    // touching the hero. When the chips lead, a negative top offset overlaps them — so off then.
    val actionBoardIsFirst = orderedBlocks.firstOrNull()?.type == "actionboard"
    // ActionBoard matches are cricket today (LiveMatchRow has no sport field yet), so the sport
    // filter treats them as Cricket: a non-cricket selection shows the honest empty state. Swap
    // for a real per-match sport filter once the backend tags matches with a sport.
    val actionBoardMatches = if (selectedSport == "All" || selectedSport == "Cricket") liveMatches else emptyList()

    val homeBlocks: Map<String, androidx.compose.foundation.lazy.LazyListScope.() -> Unit> = mapOf(
    "actionboard" to {
    // 1b. ActionBoard — lifted onto the light content surface, straddling the green seam with
    // a soft shadow so it reads as THE elevated hero card instead of one more stacked layer.
    item {
      val abInteraction = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
      val abPressed by abInteraction.collectIsPressedAsState()
      val abScale by animateFloatAsState(
        targetValue = if (abPressed) 0.985f else 1f,
        animationSpec = spring(dampingRatio = 0.72f, stiffness = 380f),
        label = "abCardPress"
      )
      Card(
        modifier = Modifier
          .fillMaxWidth()
          .padding(horizontal = 16.dp)
          .then(if (actionBoardIsFirst) Modifier.overlapAbove(32.dp) else Modifier) // seam straddle only when first
          .graphicsLayer { scaleX = abScale; scaleY = abScale }
          .premiumCardShadow(radius = UnifiedCornerRadius)
          .clickable(interactionSource = abInteraction, indication = null) { onActionBoardClick() },
        shape = RoundedCornerShape(UnifiedCornerRadius), // one card language — matches venue + widget cards (20dp)
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
      ) {
        Column(
          modifier = Modifier
            .fillMaxWidth()
            .padding(HaraanSpacing.Medium) // 16dp — unified standard-card inner padding
        ) {
          // Title + live/venue stat row.
          Row(verticalAlignment = Alignment.CenterVertically) {
            Image(
              painter = painterResource(id = com.example.thanna.R.drawable.haraan_copy),
              contentDescription = "Haraan",
              contentScale = ContentScale.Fit,
              modifier = Modifier.padding(end = 6.dp).size(22.dp)
            )
            Text(
              text = "ActionBoard",
              color = HaraanColors.TextPrimary,
              style = HaraanTypography.TitleMedium.copy(fontSize = 18.sp, fontWeight = FontWeight.ExtraBold),
              letterSpacing = 0.3.sp,
              maxLines = 1,
              softWrap = false,
              overflow = TextOverflow.Ellipsis
            )
            Spacer(modifier = Modifier.weight(1f))
            // Single live signal: "N LIVE" count (the per-match LIVE pill below is enough; the
            // venue count was redundant chrome, so it's gone).
            if (actionBoardMatches.isNotEmpty()) {
              Box(modifier = Modifier.size(6.dp).background(HaraanColors.LiveRed, CircleShape))
              Spacer(modifier = Modifier.width(5.dp))
              Text(
                text = "${actionBoardMatches.size} LIVE",
                color = HaraanColors.LiveRed,
                fontSize = 13.sp,
                fontWeight = FontWeight.ExtraBold,
                letterSpacing = 0.3.sp
              )
            }
          }

          // Real live score — or an honest empty state (sport-aware when a filter is active).
          val featuredLive = actionBoardMatches.firstOrNull()
          if (featuredLive != null) {
            Spacer(modifier = Modifier.height(10.dp))
            ActionboardLiveStrip(match = featuredLive, onClick = onActionBoardClick)
          } else {
            Text(
              text = if (selectedSport == "All") "No live matches right now · tap to open the board"
                     else "No live $selectedSport matches right now · tap to open the board",
              color = HaraanColors.TextSecondary,
              style = HaraanTypography.BodyMedium,
              modifier = Modifier.padding(top = 8.dp)
            )
          }
        }
      }
    }

    },
    "leaderboard" to {
    // 2. Leaderboard — sits right under the live ActionBoard (both are competitive/live in
    // flavor) so it no longer splits the two venue sections below.
    item {
      val homeDistrict = (locationState as? com.example.thanna.data.LocationState.Resolved)
        ?.let { it.district.ifBlank { it.city } }
      LeaderboardHomeWidget(district = homeDistrict)
    }

    },
    "sports_chips" to {
    // Sport filter chips — lead the content, directly under the hero, as a global filter for the
    // screen (venues + the ActionBoard). (The "Sports" section title was removed; the icon chips
    // are self-evident and a heading over-weighted it.)
    item {
      androidx.compose.foundation.lazy.LazyRow(
        contentPadding = PaddingValues(horizontal = 16.dp),
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier.fillMaxWidth()
      ) {
        items(sports.size) { i ->
          val sport = sports[i]
          val isSelected = sport == selectedSport
          
          val icon = when (sport) {
            "All" -> Icons.Default.List
            "Cricket" -> Icons.Default.SportsCricket
            "Football" -> Icons.Default.SportsFootball
            "Badminton" -> Icons.Default.SportsTennis
            "Basketball" -> Icons.Default.SportsBasketball
            else -> Icons.Default.SportsBasketball
          }

          // Smooth select crossfade + tactile press-scale.
          val chipInteraction = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
          val chipPressed by chipInteraction.collectIsPressedAsState()
          val chipScale by animateFloatAsState(
            targetValue = if (chipPressed) 0.94f else 1f,
            animationSpec = spring(dampingRatio = 0.72f, stiffness = 380f),
            label = "chipScale"
          )
          val chipBg by androidx.compose.animation.animateColorAsState(
            // Filled slate for unselected (was white) so chips read as solid pills against the
            // near-white page instead of floating with a faint hairline.
            targetValue = if (isSelected) HaraanColors.GameHubDeep else Color(0xFFF1F5F9),
            animationSpec = tween(200), label = "chipBg"
          )
          val chipContent by androidx.compose.animation.animateColorAsState(
            targetValue = if (isSelected) Color.White else HaraanColors.TextSecondary,
            animationSpec = tween(200), label = "chipContent"
          )

          Surface(
            onClick = { selectedSport = sport },
            interactionSource = chipInteraction,
            shape = RoundedCornerShape(50.dp),
            color = chipBg,
            border = BorderStroke(
              width = 1.dp,
              color = if (isSelected) Color.Transparent else HaraanColors.BorderLight
            ),
            modifier = Modifier
              .height(36.dp)
              .graphicsLayer { scaleX = chipScale; scaleY = chipScale }
          ) {
            Row(
              modifier = Modifier.padding(horizontal = 14.dp, vertical = 6.dp),
              verticalAlignment = Alignment.CenterVertically,
              horizontalArrangement = Arrangement.spacedBy(6.dp)
            ) {
              Icon(
                imageVector = icon,
                contentDescription = sport,
                tint = chipContent,
                modifier = Modifier.size(16.dp)
              )
              Text(
                text = sport,
                color = chipContent,
                fontSize = 14.sp,
                fontWeight = FontWeight.Medium
              )
            }
          }
        }
      }
    }

    },
    "venues" to {
    // One catalogue, two presentations — no venue is shown twice. The reel is a top-rated
    // highlight; "More venues" is strictly everything the reel did NOT show. (Previously
    // "Popular" and "Featured" overlapped heavily because every venue was flagged
    // availableTonight, so the same cards appeared in both sections.)
    val popularVenues = filteredVenues.sortedByDescending { it.rating.toFloatOrNull() ?: 0f }.take(5)
    val popularIds = popularVenues.map { it.id }.toSet()
    val moreVenues = filteredVenues.filterNot { it.id in popularIds }

    // 4. Header
    item {
      // The header bonds to the sport-filter chips directly above (they filter these venues).
      // overlapAbove reclaims most of the LazyColumn's 16dp inter-item spacing so there's no
      // dead band between the chips and this title.
      Box(modifier = Modifier.overlapAbove(12.dp)) {
      GameHubSectionHeader(
        title = "Popular Venues",
        // Contextual subtitle — carries a real venue count so the section feels inhabited.
        subtitle = when {
          isVenuesLoading -> "Finding venues near you…"
          filteredVenues.isEmpty() -> "Top rated near you"
          else -> "${filteredVenues.size} near you • top rated"
        },
        actionText = "View all",
        onActionClick = { showSearch = true },
        // Bond to the sport-filter chips directly above (they filter these venues) — the default
        // 12dp top pad on top of the list's 16dp spacing left the dead band flagged on GameHub.
        topPadding = 2.dp,
      )
      }
    }

    // 5. Highlight reel — skeleton while loading (null), honest empty note once loaded empty.
    item {
      when {
        isVenuesLoading -> GameHubVenuesSkeleton()
        filteredVenues.isEmpty() -> Box(
          modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 20.dp),
          contentAlignment = Alignment.Center
        ) {
          Text(
            text = "No venues match this filter yet.",
            color = HaraanColors.TextSecondary,
            style = HaraanTypography.BodyMedium
          )
        }
        else -> androidx.compose.foundation.lazy.LazyRow(
          contentPadding = PaddingValues(horizontal = 16.dp),
          horizontalArrangement = Arrangement.spacedBy(12.dp),
          modifier = Modifier.fillMaxWidth()
        ) {
          items(popularVenues.size) { i ->
            PopularArenaCard(
              venue = popularVenues[i],
              onClick = { onVenueClick(popularVenues[i]) },
              index = i
            )
          }
        }
      }
    }

    // 6. "More venues" — only the venues the reel didn't surface, so nothing repeats. Skipped
    // entirely when the reel already covered the whole catalogue (≤ 5 venues).
    if (!isVenuesLoading && moreVenues.isNotEmpty()) {
      item {
        GameHubSectionHeader(
          title = "More venues",
          subtitle = "${moreVenues.size} more to explore"
        )
      }
      items(moreVenues.size) { i ->
        Box(modifier = Modifier.padding(horizontal = 16.dp)) {
          VenueListCard(moreVenues[i], onClick = { onVenueClick(moreVenues[i]) })
        }
      }
    }
    },
    )

    // Render in the resolved order (hoisted above). Singleton blocks come from `homeBlocks`;
    // config-bearing blocks (feed/ad) read their `config` here. Unknown types match nothing and
    // are skipped (forward-compatible).
    orderedBlocks.forEach { block ->
      when (block.type) {
        "feed_section" -> {
          val cards = feedSections[block.config["section"] ?: "for_you"].orEmpty()
          if (cards.isNotEmpty()) {
            // overlapAbove trims the LazyColumn's inter-item spacing so "For you" sits closer
            // to the venue reel above it instead of leaving a wide dead band.
            item {
              Box(modifier = Modifier.overlapAbove(10.dp)) {
                GameHubSectionHeader(title = block.title ?: "For you")
              }
            }
            item {
              androidx.compose.foundation.lazy.LazyRow(
                contentPadding = PaddingValues(horizontal = 16.dp),
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                modifier = Modifier.fillMaxWidth().padding(top = 4.dp)
              ) {
                items(cards.size) { i -> FeedCardView(card = cards[i], onMatchClick = onMatchClick) }
              }
            }
          }
        }
        "ad_strip" -> {
          val ads = homeAds.filter { it.placement == (block.config["placement"] ?: "home") }
          if (ads.isNotEmpty()) {
            item {
              androidx.compose.foundation.lazy.LazyRow(
                contentPadding = PaddingValues(horizontal = 16.dp),
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                modifier = Modifier.fillMaxWidth()
              ) {
                items(ads.size) { i -> AdStripCard(ad = ads[i]) }
              }
            }
          }
        }
        else -> homeBlocks[block.type]?.invoke(this)
      }
    }

    item {
      Spacer(modifier = Modifier.height(16.dp))
    }
  }
}

// Animated shimmer brush for image placeholders — a soft diagonal sweep over a slate base so
// venue cards read as "loading" instead of popping in from a flat gray block.
@Composable
private fun rememberShimmerBrush(): Brush {
  val transition = rememberInfiniteTransition(label = "shimmer")
  val x by transition.animateFloat(
    initialValue = 0f,
    targetValue = 900f,
    animationSpec = infiniteRepeatable(tween(1100, easing = LinearEasing), RepeatMode.Restart),
    label = "shimmerX"
  )
  return Brush.linearGradient(
    colors = listOf(Color(0xFFE7EBF1), Color(0xFFF3F6FA), Color(0xFFE7EBF1)),
    start = Offset(x - 300f, 0f),
    end = Offset(x, 0f)
  )
}

// Loading placeholder for the venues reel — a row of shimmering card shells matching
// PopularArenaCard's footprint (220dp wide, 120dp image), so the section reserves its space
// instead of popping in (and no fake seed venues are shown while the API resolves).
@Composable
private fun GameHubVenuesSkeleton() {
  androidx.compose.foundation.lazy.LazyRow(
    contentPadding = PaddingValues(horizontal = 16.dp),
    horizontalArrangement = Arrangement.spacedBy(12.dp),
    modifier = Modifier.fillMaxWidth(),
    userScrollEnabled = false
  ) {
    items(3) {
      Card(
        modifier = Modifier
          .width(220.dp)
          .premiumCardShadow(radius = UnifiedCornerRadius, ambient = 14.dp, contact = 2.dp),
        shape = RoundedCornerShape(UnifiedCornerRadius),
        colors = CardDefaults.cardColors(containerColor = Color.White),
      ) {
        Column(modifier = Modifier.fillMaxWidth()) {
          Box(Modifier.fillMaxWidth().height(120.dp).haraanShimmer())
          Column(
            modifier = Modifier.padding(12.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp)
          ) {
            Box(Modifier.fillMaxWidth(0.7f).height(14.dp).clip(RoundedCornerShape(4.dp)).haraanShimmer())
            Box(Modifier.fillMaxWidth(0.45f).height(12.dp).clip(RoundedCornerShape(4.dp)).haraanShimmer())
          }
        }
      }
    }
  }
}

// Personalized greeting header — avatar + "Hey {name}!" on the left, a tappable location line
// (Plus Code + place) below, and a unified line-icon utility row (chat / bell / calendar) on the
// right. Shared by Events + GameHub; `onDark` flips colors for the green hero vs light surfaces.
@Composable
private fun GreetingHeader(
  name: String,
  avatarUrl: String,
  locationState: com.example.thanna.data.LocationState,
  onDark: Boolean,
  onAvatarClick: () -> Unit,
  onLocationClick: () -> Unit,
  onChatClick: () -> Unit = {},
  onNotificationClick: () -> Unit = {},
  onCalendarClick: () -> Unit = {},
  // No per-user notifications feed exists yet, so don't show a fake unread dot. Flip to true
  // (or wire to a real count) once the notifications API lands.
  hasUnread: Boolean = false,
  modifier: Modifier = Modifier,
) {
  val ink = if (onDark) Color.White else HaraanColors.TextPrimary
  // Location line sits on the mid-green gradient — keep it readable (was 0.72, too faint).
  val sub = if (onDark) Color.White.copy(alpha = 0.85f) else HaraanColors.TextSecondary
  val iconTint = if (onDark) Color.White.copy(alpha = 0.92f) else HaraanColors.TextPrimary.copy(alpha = 0.85f)
  val ringColor = if (onDark) Color.White.copy(alpha = 0.35f) else HaraanColors.BorderLight

  val firstName = name.trim().substringBefore(' ').takeIf { it.isNotBlank() } ?: "there"

  // Location line: "<PlusCode> <place>", mirroring the reference. Falls back gracefully.
  val city = when (locationState) {
    is com.example.thanna.data.LocationState.Resolved -> locationState.city
    com.example.thanna.data.LocationState.Locating -> "Locating…"
    else -> "Set location"
  }
  val area = (locationState as? com.example.thanna.data.LocationState.Resolved)?.area ?: ""
  val plus = (locationState as? com.example.thanna.data.LocationState.Resolved)?.plusCode ?: ""
  val place = area.ifBlank { city }
  val locationText = listOf(plus, place).filter { it.isNotBlank() }.joinToString(" ")

  Row(
    modifier = modifier.fillMaxWidth(),
    verticalAlignment = Alignment.CenterVertically
  ) {
    // Avatar — real photo with a subtle ring; initial-letter fallback when none.
    Box(
      modifier = Modifier
        .size(44.dp)
        .clip(CircleShape)
        .background(if (onDark) Color.White.copy(alpha = 0.14f) else HaraanColors.BorderLight)
        .border(1.5.dp, ringColor, CircleShape)
        .clickable { onAvatarClick() },
      contentAlignment = Alignment.Center
    ) {
      // Initial-letter fallback always sits underneath, so a blank OR failed/loading photo
      // still shows the letter instead of an empty circle (the "profile pic not showing" bug).
      Text(
        text = firstName.take(1).uppercase(),
        color = ink,
        fontWeight = FontWeight.Bold,
        fontSize = 18.sp
      )
      if (avatarUrl.isNotBlank()) {
        AsyncImage(
          model = avatarUrl,
          contentDescription = "Profile",
          contentScale = ContentScale.Crop,
          modifier = Modifier.fillMaxSize().clip(CircleShape)
        )
      }
    }

    Spacer(modifier = Modifier.width(12.dp))

    // Name + location lockup.
    Column(modifier = Modifier.weight(1f)) {
      Text(
        text = "Hey $firstName!",
        color = ink,
        style = HaraanTypography.TitleLarge.copy(fontSize = 17.sp),
        maxLines = 1,
        overflow = TextOverflow.Ellipsis
      )
      Spacer(modifier = Modifier.height(2.dp))
      Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
          .clip(RoundedCornerShape(6.dp))
          .clickable { onLocationClick() }
      ) {
        Text(
          text = locationText,
          color = sub,
          style = HaraanTypography.SectionSubtitle.copy(fontSize = 13.sp, fontWeight = FontWeight.SemiBold),
          maxLines = 1,
          overflow = TextOverflow.Ellipsis,
          modifier = Modifier.widthIn(max = 180.dp)
        )
        Icon(
          imageVector = Icons.Default.KeyboardArrowDown,
          contentDescription = "Change location",
          tint = sub,
          modifier = Modifier.size(18.dp)
        )
      }
    }

    // Utility icon row — one unified line-icon family (no emoji). Each icon sits in a 40dp
    // circular tap target (24dp glyph + padding) so the touch area clears the accessibility
    // minimum instead of being a bare 24dp hit box.
    Row(
      verticalAlignment = Alignment.CenterVertically,
      horizontalArrangement = Arrangement.spacedBy(HaraanSpacing.Micro)
    ) {
      Box(
        modifier = Modifier
          .size(40.dp)
          .clip(CircleShape)
          .clickable { onChatClick() },
        contentAlignment = Alignment.Center
      ) {
        Icon(
          imageVector = Icons.Outlined.ChatBubbleOutline,
          contentDescription = "Messages",
          tint = iconTint,
          modifier = Modifier.size(24.dp)
        )
      }
      Box(
        modifier = Modifier
          .size(40.dp)
          .clip(CircleShape)
          .clickable { onNotificationClick() },
        contentAlignment = Alignment.Center
      ) {
        Icon(
          imageVector = Icons.Outlined.Notifications,
          contentDescription = "Notifications",
          tint = iconTint,
          modifier = Modifier.size(24.dp)
        )
        if (hasUnread) {
          Box(
            modifier = Modifier
              .align(Alignment.Center)
              .offset(x = 9.dp, y = (-9).dp)
              .size(8.dp)
              .clip(CircleShape)
              .background(if (onDark) HaraanColors.GameHubDeep else Color.White)
              .padding(1.5.dp)
              .clip(CircleShape)
              .background(HaraanColors.LiveRed)
          )
        }
      }
      Box(
        modifier = Modifier
          .size(40.dp)
          .clip(CircleShape)
          .clickable { onCalendarClick() },
        contentAlignment = Alignment.Center
      ) {
        Icon(
          imageVector = Icons.Outlined.CalendarMonth,
          contentDescription = "Calendar",
          tint = iconTint,
          modifier = Modifier.size(24.dp)
        )
      }
    }
  }
}

// Shared on-page section header for the GameHub list — title (+ optional subtitle below) on
// the left, an optional tappable action on the right. Replaces three hand-rolled, near-identical
// header blocks so spacing, type and color stay consistent and the "View all" link is real.
@Composable
private fun GameHubSectionHeader(
  title: String,
  subtitle: String? = null,
  actionText: String? = null,
  onActionClick: (() -> Unit)? = null,
  // Space ABOVE the title. Defaults to 12dp (separates from an unrelated previous section);
  // pass a smaller value when the header should BOND to what precedes it — e.g. the venues
  // header sits right under the sport-filter chips that filter it, so the standard 12dp left a
  // dead band there (the extra gap flagged on GameHub).
  topPadding: androidx.compose.ui.unit.Dp = 12.dp,
) {
  Column(
    modifier = Modifier
      .fillMaxWidth()
      // Asymmetric padding: [topPadding] above (separates from the previous section) and none
      // below (bonds the title tightly to its own content) so sections read as groups.
      .padding(start = 16.dp, end = 16.dp, top = topPadding, bottom = 0.dp)
  ) {
    Row(
      modifier = Modifier.fillMaxWidth(),
      horizontalArrangement = Arrangement.SpaceBetween,
      verticalAlignment = Alignment.CenterVertically
    ) {
      Text(
        text = title,
        color = HaraanColors.TextPrimary,
        style = HaraanTypography.SectionTitle
      )
      if (actionText != null && onActionClick != null) {
        Text(
          text = actionText,
          color = HaraanColors.GameHubDeep,
          style = HaraanTypography.SectionAction,
          modifier = Modifier
            .clip(RoundedCornerShape(HaraanRadius.Small))
            .clickable(
              interactionSource = remember { androidx.compose.foundation.interaction.MutableInteractionSource() },
              indication = androidx.compose.material3.ripple(bounded = false)
            ) { onActionClick() }
            // Larger hit area (was 4/4 ≈ 21dp tall) so the link clears the touch-target minimum.
            .padding(horizontal = HaraanSpacing.Small, vertical = HaraanSpacing.Small)
        )
      }
    }
    if (!subtitle.isNullOrBlank()) {
      Text(
        text = subtitle,
        color = HaraanColors.TextSecondary,
        style = HaraanTypography.SectionSubtitle,
        modifier = Modifier.padding(top = 2.dp)
      )
    }
  }
}

// One curated feed card (For You / Trending), rendered for a `feed_section` home block.
// Image with optional badge, then title/subtitle/rating. A `match` link opens the match.
@Composable
private fun FeedCardView(
  card: com.example.thanna.data.FeedCard,
  onMatchClick: (String) -> Unit = {},
) {
  val feedCtx = LocalContext.current
  Card(
    modifier = Modifier
      // Fixed footprint so every "For you" card is identical — a title-only card and a
      // card with subtitle + rating no longer render at different heights in the row.
      .width(260.dp)
      .height(228.dp)
      .premiumCardShadow(radius = UnifiedCornerRadius)
      .clickable {
        // Central deep-link routing: match → in-app, url → browser, unknown → no-op.
        com.example.thanna.ui.openContentLink(feedCtx, card.linkType, card.linkId, onMatch = onMatchClick)
      },
    shape = RoundedCornerShape(UnifiedCornerRadius),
    colors = CardDefaults.cardColors(containerColor = Color.White),
    elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
  ) {
    Column(modifier = Modifier.fillMaxSize()) {
      Box(
        modifier = Modifier
          .fillMaxWidth()
          .height(132.dp)
      ) {
        if (card.image.isNullOrBlank()) {
          // No creative supplied — a branded gradient + faint monogram instead of blank white.
          Box(
            modifier = Modifier
              .fillMaxSize()
              .background(
                Brush.linearGradient(listOf(HaraanColors.GameHubDeep, HaraanColors.GameHubGreen))
              ),
            contentAlignment = Alignment.Center,
          ) {
            Image(
              painter = painterResource(id = com.example.thanna.R.drawable.haraan_copy),
              contentDescription = null,
              colorFilter = androidx.compose.ui.graphics.ColorFilter.tint(Color.White.copy(alpha = 0.55f)),
              contentScale = ContentScale.Fit,
              modifier = Modifier.size(44.dp),
            )
          }
        } else {
          HaraanImage(
            model = card.image,
            contentDescription = card.title,
            modifier = Modifier.fillMaxSize(),
          )
        }
        if (!card.badge.isNullOrBlank()) {
          Text(
            text = card.badge,
            color = Color.White,
            fontSize = 11.sp,
            fontWeight = FontWeight.Bold,
            modifier = Modifier
              .align(Alignment.TopStart)
              .padding(10.dp)
              .background(HaraanColors.GameHubDeep, RoundedCornerShape(6.dp))
              .padding(horizontal = 8.dp, vertical = 3.dp),
          )
        }
      }
      Column(modifier = Modifier.fillMaxWidth().weight(1f).padding(12.dp)) {
        Text(
          text = card.title,
          color = HaraanColors.TextPrimary,
          style = HaraanTypography.TitleMedium.copy(fontSize = 15.sp, fontWeight = FontWeight.Bold),
          maxLines = 1,
          overflow = TextOverflow.Ellipsis,
        )
        if (!card.subtitle.isNullOrBlank()) {
          Text(
            text = card.subtitle,
            color = HaraanColors.TextSecondary,
            style = HaraanTypography.BodyMedium,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.padding(top = 2.dp),
          )
        }
        if (!card.rating.isNullOrBlank()) {
          Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier.padding(top = 6.dp),
          ) {
            Icon(
              imageVector = Icons.Default.Star,
              contentDescription = null,
              tint = Color(0xFFF5A623),
              modifier = Modifier.size(14.dp),
            )
            Text(
              text = card.rating,
              color = HaraanColors.TextPrimary,
              fontSize = 12.sp,
              fontWeight = FontWeight.Bold,
              modifier = Modifier.padding(start = 4.dp),
            )
          }
        }
      }
    }
  }
}

// One sponsored card in an `ad_strip` home block. Sponsor label + headline over a creative;
// tapping the CTA opens the ad's URL. Honest "Sponsored" marker so it never masquerades as content.
@Composable
private fun AdStripCard(ad: com.example.thanna.data.AdItem) {
  val adCtx = LocalContext.current
  Card(
    modifier = Modifier
      // Same width as the "For you" feed cards so the sponsored row lines up with them
      // instead of reading as a differently-sized strip.
      .width(260.dp)
      .premiumCardShadow(radius = UnifiedCornerRadius)
      .clickable { com.example.thanna.ui.openExternalUrl(adCtx, ad.ctaUrl) },
    shape = RoundedCornerShape(UnifiedCornerRadius),
    colors = CardDefaults.cardColors(containerColor = Color.White),
    elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
  ) {
    Column {
      Box(
        modifier = Modifier
          .fillMaxWidth()
          .height(120.dp)
      ) {
        HaraanImage(
          model = ad.image,
          contentDescription = ad.title,
          modifier = Modifier.fillMaxSize(),
        )
        Text(
          text = "Sponsored",
          color = Color.White,
          fontSize = 10.sp,
          fontWeight = FontWeight.Bold,
          letterSpacing = 0.4.sp,
          modifier = Modifier
            .align(Alignment.TopEnd)
            .padding(10.dp)
            .background(Color.Black.copy(alpha = 0.55f), RoundedCornerShape(6.dp))
            .padding(horizontal = 8.dp, vertical = 3.dp),
        )
      }
      Column(modifier = Modifier.padding(12.dp)) {
        if (!ad.sponsor.isNullOrBlank()) {
          Text(
            text = ad.sponsor.uppercase(),
            color = HaraanColors.TextSecondary,
            fontSize = 10.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 0.5.sp,
          )
        }
        Text(
          text = ad.title,
          color = HaraanColors.TextPrimary,
          style = HaraanTypography.TitleMedium.copy(fontSize = 15.sp, fontWeight = FontWeight.Bold),
          maxLines = 2,
          overflow = TextOverflow.Ellipsis,
          modifier = Modifier.padding(top = 2.dp),
        )
        if (!ad.ctaText.isNullOrBlank()) {
          Text(
            text = ad.ctaText,
            color = HaraanColors.GameHubDeep,
            fontSize = 13.sp,
            fontWeight = FontWeight.ExtraBold,
            modifier = Modifier.padding(top = 8.dp),
          )
        }
      }
    }
  }
}

@Composable
private fun ActionboardLiveStrip(match: com.example.thanna.data.LiveMatchRow, onClick: () -> Unit) {
  // Real live match. Batting side leads the card (top row), mirroring the live list.
  val battingSecond = match.battingTeam == 2
  val topCode = com.example.thanna.ui.matches.teamShortCode(if (battingSecond) match.team2 else match.team1)
  val topName = if (battingSecond) match.team2 else match.team1
  val topScore = (if (battingSecond) match.score2 else match.score1).ifBlank { "0" }
  val topOvers = (if (battingSecond) match.overs2 else match.overs1)
  val botCode = com.example.thanna.ui.matches.teamShortCode(if (battingSecond) match.team1 else match.team2)
  val botName = if (battingSecond) match.team1 else match.team2
  val botScore = (if (battingSecond) match.score1 else match.score2)
  val botOvers = (if (battingSecond) match.overs1 else match.overs2)
  val botYetToBat = hasNotBatted(botScore, botOvers)
  // Team icon chosen at create time — uploaded logo URL wins, else the default emblem key.
  val topIcon = (if (battingSecond) match.team2Logo else match.team1Logo).ifBlank { if (battingSecond) match.team2Emblem else match.team1Emblem }
  val botIcon = (if (battingSecond) match.team1Logo else match.team2Logo).ifBlank { if (battingSecond) match.team1Emblem else match.team2Emblem }
  val meta = listOfNotNull(
    match.competition.takeIf { it.isNotBlank() },
    topOvers.takeIf { it.isNotBlank() }?.let { "$it ov" }
  ).joinToString(" • ")

  // Flat content block — no nested bordered box. It lives directly inside the ActionBoard card
  // now, separated by a hairline divider, so the screen drops one level of container nesting.
  Column(modifier = Modifier.fillMaxWidth()) {
    androidx.compose.material3.HorizontalDivider(color = HaraanColors.BorderLight, thickness = 1.dp)
    Spacer(modifier = Modifier.height(10.dp))
    if (meta.isNotBlank()) {
      Text(meta, color = HaraanColors.TextSecondary, fontSize = 11.sp, fontWeight = FontWeight.Medium)
      Spacer(modifier = Modifier.height(8.dp))
    }
    ActionboardLiveTeamRow(abbr = topCode, name = topName, score = topScore, overs = topOvers.takeIf { it.isNotBlank() }?.let { "($it)" } ?: "", emphasize = true, iconRef = topIcon)
    Spacer(modifier = Modifier.height(6.dp))
    ActionboardLiveTeamRow(abbr = botCode, name = botName, score = if (botYetToBat) "Yet to bat" else botScore.ifBlank { "0" }, overs = if (botYetToBat) "" else botOvers.takeIf { it.isNotBlank() }?.let { "($it)" } ?: "", emphasize = false, iconRef = botIcon)
  }
}

@Composable
private fun ActionboardLiveTeamRow(
  abbr: String,
  name: String,
  score: String,
  overs: String,
  emphasize: Boolean,
  scoreScale: Float = 1f,
  iconRef: String = ""
) {
  Row(
    modifier = Modifier.fillMaxWidth(),
    verticalAlignment = Alignment.CenterVertically
  ) {
    // Team icon chosen at create time (logo URL / emblem key), else monogram fallback.
    TeamLogo(team = abbr, logoUrl = iconRef, modifier = Modifier.size(24.dp))
    Spacer(modifier = Modifier.width(8.dp))
    Text(
      text = abbr,
      color = HaraanColors.TextPrimary,
      fontSize = 14.sp,
      fontWeight = if (emphasize) FontWeight.ExtraBold else FontWeight.Bold
    )
    Spacer(modifier = Modifier.width(6.dp))
    Text(
      text = name,
      color = HaraanColors.TextSecondary,
      fontSize = 12.sp,
      fontWeight = FontWeight.Medium,
      maxLines = 1,
      overflow = TextOverflow.Ellipsis,
      modifier = Modifier.weight(1f).padding(end = 6.dp)
    )
    // Trailing score column — right-aligned so the score's right edge sits on a
    // consistent vertical line across both team rows (Crex-style dense alignment).
    Row(verticalAlignment = Alignment.CenterVertically) {
      Text(
        text = score,
        color = HaraanColors.TextPrimary,
        fontSize = 15.sp,
        fontWeight = if (emphasize) FontWeight.ExtraBold else FontWeight.Bold,
        textAlign = TextAlign.End,
        style = TextStyle(fontFeatureSettings = "tnum"),
        modifier = Modifier.graphicsLayer { scaleX = scoreScale; scaleY = scoreScale }
      )
      if (overs.isNotBlank()) {
        Spacer(modifier = Modifier.width(6.dp))
        Text(
          text = overs,
          color = HaraanColors.TextMuted,
          fontSize = 11.sp,
          fontWeight = FontWeight.Medium,
          textAlign = TextAlign.End,
          style = TextStyle(fontFeatureSettings = "tnum"),
          modifier = Modifier.widthIn(min = 44.dp)
        )
      }
    }
  }
}



@Composable
private fun GameHubSegmentedSwitch(
  selectedTab: String,
  onTabSelected: (String) -> Unit,
  modifier: Modifier = Modifier
) {
  val tabs = listOf("Events" to "Events", "GameHub" to "GameHub")
  val containerBg = Color.White.copy(alpha = 0.15f)
  val activeBg = Color.White
  
  BoxWithConstraints(
    modifier = modifier
      .fillMaxWidth()
      .height(44.dp)
      .background(containerBg, RoundedCornerShape(22.dp))
      .padding(3.dp)
  ) {
    val containerWidth = maxWidth
    val tabWidth = containerWidth / tabs.size
    val selectedIndex = tabs.indexOfFirst { it.first == selectedTab }.coerceAtLeast(0)
    
    val indicatorOffset by animateDpAsState(
      targetValue = tabWidth * selectedIndex,
      animationSpec = spring(dampingRatio = 0.82f, stiffness = 400f),
      label = "SwitchSliderOffset"
    )
    
    Box(modifier = Modifier.fillMaxSize()) {
      Box(
        modifier = Modifier
          .offset(x = indicatorOffset)
          .width(tabWidth)
          .fillMaxHeight()
          // Soft lift so the active chip reads as a crafted physical pill on the header.
          // Lighter spot/ambient than before — on the dark-green band a heavy black shadow
          // read as a muddy halo; this keeps the lift but cleans the edge.
          .shadow(4.dp, RoundedCornerShape(19.dp), ambientColor = Color.Black.copy(0.10f), spotColor = Color.Black.copy(0.16f))
          .background(activeBg, RoundedCornerShape(19.dp))
      )
      
      Row(modifier = Modifier.fillMaxSize()) {
        tabs.forEach { (key, label) ->
          val isSelected = selectedTab == key
          Box(
            modifier = Modifier
              .weight(1f)
              .fillMaxHeight()
              .clickable(
                interactionSource = remember { androidx.compose.foundation.interaction.MutableInteractionSource() },
                indication = null
              ) { onTabSelected(key) },
            contentAlignment = Alignment.Center
          ) {
            Text(
              text = label,
              color = if (isSelected) Color(0xFF1B5E20) else Color.White.copy(alpha = 0.75f),
              fontSize = 13.sp,
              fontWeight = FontWeight.Bold
            )
          }
        }
      }
    }
  }
}

@Composable
private fun PopularArenaCard(
  venue: VenueItem,
  onClick: () -> Unit,
  index: Int = 0
) {
  // Tactile press-scale + layered "floating glass" depth.
  val interaction = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
  val pressed by interaction.collectIsPressedAsState()
  val scale by animateFloatAsState(
    targetValue = if (pressed) 0.97f else 1f,
    animationSpec = spring(dampingRatio = 0.72f, stiffness = 380f),
    label = "venuePress"
  )
  // Staggered entrance — each card fades up with a small index-based delay so the carousel
  // feels composed on load instead of appearing as a static block.
  var appeared by remember { mutableStateOf(false) }
  LaunchedEffect(Unit) {
    kotlinx.coroutines.delay(index * 70L)
    appeared = true
  }
  val enter by animateFloatAsState(
    targetValue = if (appeared) 1f else 0f,
    animationSpec = spring(dampingRatio = 0.85f, stiffness = 220f),
    label = "venueEnter"
  )
  Card(
    modifier = Modifier
      .width(220.dp)
      .graphicsLayer {
        scaleX = scale; scaleY = scale
        alpha = enter
        translationY = (1f - enter) * 24f
      }
      .premiumCardShadow(radius = UnifiedCornerRadius, ambient = 14.dp, contact = 2.dp)
      .clickable(interactionSource = interaction, indication = null) { onClick() },
    shape = RoundedCornerShape(UnifiedCornerRadius), // 20dp — same as the Featured list cards
    colors = CardDefaults.cardColors(containerColor = Color.White),
    // No border — the soft shadow alone separates the card (one unified card language).
  ) {
    Column(modifier = Modifier.fillMaxWidth()) {
      Box(
        modifier = Modifier
          .fillMaxWidth()
          .height(120.dp)
          .background(rememberShimmerBrush())
      ) {
        AsyncImage(
          model = venue.imageUrl,
          contentDescription = venue.title,
          contentScale = ContentScale.Crop,
          modifier = Modifier.fillMaxSize()
        )
        // Scrim for badge/rating legibility
        Box(
          modifier = Modifier
            .fillMaxSize()
            .background(
              Brush.verticalGradient(
                colors = listOf(Color.Transparent, Color.Black.copy(alpha = 0.30f)),
                startY = 70f
              )
            )
        )
        // Category badge (top-left)
        Box(
          modifier = Modifier
            .align(Alignment.TopStart)
            .padding(8.dp)
            .background(Color.White.copy(alpha = 0.92f), RoundedCornerShape(8.dp))
            .padding(horizontal = 8.dp, vertical = 4.dp)
        ) {
          Text(
            text = venue.category,
            color = HaraanColors.GameHubDeep,
            fontWeight = FontWeight.Bold,
            fontSize = 11.sp
          )
        }
        // Available-tonight badge (top-right)
        if (venue.availableTonight) {
          Row(
            modifier = Modifier
              .align(Alignment.TopEnd)
              .padding(8.dp)
              .background(HaraanColors.GameHubDeep, RoundedCornerShape(8.dp))
              .padding(horizontal = 8.dp, vertical = 4.dp),
            verticalAlignment = Alignment.CenterVertically
          ) {
            Box(modifier = Modifier.size(5.dp).background(Color(0xFF7CFFB0), CircleShape))
            Spacer(modifier = Modifier.width(4.dp))
            Text(text = "Tonight", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 10.sp)
          }
        }
        // Rating (bottom-left over scrim)
        Row(
          modifier = Modifier.align(Alignment.BottomStart).padding(8.dp),
          verticalAlignment = Alignment.CenterVertically
        ) {
          Text(text = "★", color = Color(0xFFFFC107), fontSize = 12.sp)
          Spacer(modifier = Modifier.width(3.dp))
          Text(text = venue.rating, color = Color.White, fontWeight = FontWeight.Bold, fontSize = 12.sp)
        }
      }

      Column(modifier = Modifier.padding(HaraanSpacing.Compact)) {
        Text(
          text = venue.title,
          color = HaraanColors.TextPrimary,
          fontWeight = FontWeight.Bold,
          fontSize = 14.sp,
          maxLines = 1,
          overflow = TextOverflow.Ellipsis
        )
        Spacer(modifier = Modifier.height(2.dp))
        Text(
          text = venue.tagline,
          color = HaraanColors.TextSecondary,
          fontSize = 11.sp,
          maxLines = 1,
          overflow = TextOverflow.Ellipsis
        )
        Spacer(modifier = Modifier.height(8.dp))
        Row(
          modifier = Modifier.fillMaxWidth(),
          horizontalArrangement = Arrangement.SpaceBetween,
          verticalAlignment = Alignment.CenterVertically
        ) {
          Row(verticalAlignment = Alignment.CenterVertically) {
            Row(verticalAlignment = Alignment.Bottom) {
              Text(
                text = "₹${venue.price}",
                color = HaraanColors.TextPrimary,
                fontWeight = FontWeight.ExtraBold,
                fontSize = 15.sp
              )
              Text(
                text = "/hr",
                color = HaraanColors.TextSecondary,
                fontSize = 11.sp,
                modifier = Modifier.padding(bottom = 2.dp)
              )
            }
            // Sports playable here — fills the gap between price and distance. Two icons max,
            // then a "+N" pill, so a multi-sport turf reads at a glance without crowding.
            if (venue.sports.isNotEmpty()) {
              Spacer(modifier = Modifier.width(8.dp))
              VenueSportsIcons(sports = venue.sports)
            }
          }
          if (venue.distance.isNotBlank()) {
            // Neutral slate chip — distance is metadata, not an accent. (Was brand-blue,
            // which competed with green/red/gold for attention.)
            Row(
              verticalAlignment = Alignment.CenterVertically,
              modifier = Modifier
                .clip(RoundedCornerShape(50))
                .background(Color(0xFFF1F5F9))
                .padding(horizontal = 8.dp, vertical = 3.dp)
            ) {
              Icon(
                imageVector = Icons.Default.LocationOn,
                contentDescription = null,
                tint = HaraanColors.TextSecondary,
                modifier = Modifier.size(11.dp)
              )
              Spacer(modifier = Modifier.width(3.dp))
              Text(
                text = venue.distance,
                color = HaraanColors.TextSecondary,
                fontSize = 11.sp,
                fontWeight = FontWeight.SemiBold,
                // Never wrap — a wrapped "2.4 km" made this card taller than its neighbours.
                maxLines = 1,
                softWrap = false
              )
            }
          }
        }
      }
    }
  }
}

// Compact sport-availability row for a venue card: up to two sport icons, then a "+N" when the
// venue offers more. Bare glyphs (no disc) so they stay light and take little width. De-duped by
// glyph so two sports that share an icon (e.g. badminton/tennis) never render the same icon twice.
@Composable
private fun VenueSportsIcons(sports: List<String>) {
  val distinct = sports.distinctBy { sportGlyphKey(it) }
  if (distinct.isEmpty()) return
  val shown = distinct.take(2)
  val extra = (distinct.size - shown.size).coerceAtLeast(0)
  Row(
    verticalAlignment = Alignment.CenterVertically,
    horizontalArrangement = Arrangement.spacedBy(4.dp)
  ) {
    shown.forEach { sport ->
      Icon(
        imageVector = venueSportIcon(sport),
        contentDescription = sport,
        tint = HaraanColors.GameHubDeep,
        modifier = Modifier.size(16.dp)
      )
    }
    if (extra > 0) {
      Text(
        text = "+$extra",
        color = HaraanColors.TextSecondary,
        fontSize = 11.sp,
        fontWeight = FontWeight.Bold
      )
    }
  }
}

// Sport name → glyph. Mirrors the sport-filter chips so a venue's icon matches its chip. Unknown
// sports fall back to a generic ball rather than dropping the icon.
private fun venueSportIcon(sport: String): androidx.compose.ui.graphics.vector.ImageVector = when (sportGlyphKey(sport)) {
  "cricket" -> Icons.Default.SportsCricket
  "football" -> Icons.Default.SportsFootball
  "racket" -> Icons.Default.SportsTennis
  "volleyball" -> Icons.Default.SportsVolleyball
  else -> Icons.Default.SportsBasketball // basketball + generic fallback
}

// Canonical glyph bucket for a sport — used to de-dupe icons (badminton & tennis share the racket
// glyph, so they collapse to one) and to pick the icon above.
private fun sportGlyphKey(sport: String): String = when {
  sport.contains("Cricket", true) -> "cricket"
  sport.contains("Football", true) || sport.contains("Soccer", true) -> "football"
  sport.contains("Badminton", true) || sport.contains("Tennis", true) -> "racket"
  sport.contains("Volley", true) -> "volleyball"
  else -> "basketball"
}

private data class VenueItem(
  val id: String,
  val title: String,
  val location: String,
  val rating: String,
  val category: String,
  // All sports playable here (category first). Rendered as icons on the card.
  val sports: List<String> = emptyList(),
  val price: Int,
  val imageUrl: String,
  val tagline: String,
  val distance: String,
  val availableTonight: Boolean = true
)

// Fallback photo when an admin-created venue has no image uploaded yet.
private fun venueCategoryImage(category: String): String = when {
  category.contains("Cricket", true) -> "https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?w=600&q=80"
  category.contains("Football", true) -> "https://images.unsplash.com/photo-1522778526097-ce0a22ceb253?w=600&q=80"
  category.contains("Badminton", true) -> "https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?w=600&q=80"
  else -> "https://images.unsplash.com/photo-1546519638-68e109498ffc?w=600&q=80"
}

@Composable
private fun VenueListCard(venue: VenueItem, onClick: () -> Unit = {}) {
  val interaction = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
  val pressed by interaction.collectIsPressedAsState()
  val scale by animateFloatAsState(
    targetValue = if (pressed) 0.98f else 1f,
    animationSpec = spring(dampingRatio = 0.72f, stiffness = 380f),
    label = "venueListPress"
  )
  Card(
    modifier = Modifier
      .fillMaxWidth()
      .graphicsLayer { scaleX = scale; scaleY = scale }
      .premiumCardShadow(radius = UnifiedCornerRadius, ambient = 14.dp, contact = 2.dp)
      .clickable(interactionSource = interaction, indication = null) { onClick() },
    shape = RoundedCornerShape(UnifiedCornerRadius),
    colors = CardDefaults.cardColors(containerColor = Color.White),
    // No border — unified shadow-based card language.
  ) {
    Column(modifier = Modifier.fillMaxWidth()) {
      Box(
        modifier = Modifier
          .fillMaxWidth()
          .height(150.dp)
          .background(rememberShimmerBrush())
      ) {
        AsyncImage(
          model = venue.imageUrl,
          contentDescription = venue.title,
          contentScale = ContentScale.Crop,
          modifier = Modifier.fillMaxSize()
        )
        // Top + bottom scrim for badge / tagline legibility
        Box(
          modifier = Modifier
            .fillMaxSize()
            .background(
              Brush.verticalGradient(
                colors = listOf(
                  Color.Black.copy(alpha = 0.18f),
                  Color.Transparent,
                  Color.Black.copy(alpha = 0.40f)
                )
              )
            )
        )
        // Category badge (top-left)
        Box(
          modifier = Modifier
            .align(Alignment.TopStart)
            .padding(10.dp)
            .background(Color.White.copy(alpha = 0.92f), RoundedCornerShape(8.dp))
            .padding(horizontal = 8.dp, vertical = 4.dp)
        ) {
          Text(text = venue.category, color = HaraanColors.GameHubDeep, fontWeight = FontWeight.Bold, fontSize = 11.sp)
        }
        // Available-tonight badge (top-right)
        if (venue.availableTonight) {
          Row(
            modifier = Modifier
              .align(Alignment.TopEnd)
              .padding(10.dp)
              .background(HaraanColors.GameHubDeep, RoundedCornerShape(8.dp))
              .padding(horizontal = 8.dp, vertical = 4.dp),
            verticalAlignment = Alignment.CenterVertically
          ) {
            Box(modifier = Modifier.size(5.dp).background(Color(0xFF7CFFB0), CircleShape))
            Spacer(modifier = Modifier.width(4.dp))
            Text(text = "Available tonight", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 10.sp)
          }
        }
        // Tagline (bottom-left over scrim)
        Text(
          text = venue.tagline,
          color = Color.White,
          fontWeight = FontWeight.SemiBold,
          fontSize = 12.sp,
          modifier = Modifier.align(Alignment.BottomStart).padding(10.dp)
        )
      }

      Column(modifier = Modifier.padding(HaraanSpacing.Medium)) {
        Row(
          modifier = Modifier.fillMaxWidth(),
          horizontalArrangement = Arrangement.SpaceBetween,
          verticalAlignment = Alignment.CenterVertically
        ) {
          Column(modifier = Modifier.weight(1f)) {
            Text(
              text = venue.title,
              color = HaraanColors.TextPrimary,
              fontWeight = FontWeight.Bold,
              fontSize = 16.sp
            )
            Row(verticalAlignment = Alignment.CenterVertically) {
              Icon(
                imageVector = Icons.Default.LocationOn,
                contentDescription = null,
                tint = HaraanColors.TextMuted,
                modifier = Modifier.size(13.dp)
              )
              Spacer(modifier = Modifier.width(2.dp))
              Text(
                text = "${venue.location} • ${venue.distance}",
                color = HaraanColors.TextSecondary,
                fontSize = 13.sp
              )
            }
          }
          Row(verticalAlignment = Alignment.CenterVertically) {
            Text(text = "★", color = Color(0xFFFFB000), fontSize = 14.sp)
            Spacer(modifier = Modifier.width(2.dp))
            Text(
              text = venue.rating,
              color = HaraanColors.TextPrimary,
              fontWeight = FontWeight.Bold,
              fontSize = 12.sp
            )
          }
        }

        Spacer(modifier = Modifier.height(12.dp))
        Row(
          modifier = Modifier.fillMaxWidth(),
          horizontalArrangement = Arrangement.SpaceBetween,
          verticalAlignment = Alignment.CenterVertically
        ) {
          Row(verticalAlignment = Alignment.Bottom) {
            Text(
              text = "₹${venue.price}",
              color = HaraanColors.TextPrimary,
              fontWeight = FontWeight.ExtraBold,
              fontSize = 17.sp
            )
            Text(
              text = "/hr",
              color = HaraanColors.TextSecondary,
              fontSize = 12.sp,
              modifier = Modifier.padding(bottom = 2.dp)
            )
          }
          // CTA — bright "commit" green (reserved for actions), tighter button radius than the
          // 20dp card so it doesn't read as a nested card.
          Box(
            modifier = Modifier
              .clip(RoundedCornerShape(HaraanRadius.Small))
              .background(HaraanColors.GameHubGreen)
              .padding(horizontal = 16.dp, vertical = 10.dp)
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
  ThannaTheme {
    com.example.thanna.ui.LoginScreen(
      uiState = com.example.thanna.ui.LoginUiState(),
      onEmailChange = {},
      onNameChange = {},
      onDobChange = {},
      onOtpChange = {},
      onContinueClick = {},
      onVerifyOtpClick = {},
      onCompleteProfileClick = {},
      onBackToEmailClick = {}
    )
  }
}

@Preview(showBackground = true, widthDp = 340)
@Composable
fun MainScreenPortraitPreview() {
  ThannaTheme {
    com.example.thanna.ui.LoginScreen(
      uiState = com.example.thanna.ui.LoginUiState(),
      onEmailChange = {},
      onNameChange = {},
      onDobChange = {},
      onOtpChange = {},
      onContinueClick = {},
      onVerifyOtpClick = {},
      onCompleteProfileClick = {},
      onBackToEmailClick = {}
    )
  }
}

@Composable
private fun DistrictActionBoardScreen(
  onBack: () -> Unit,
  onMatchClick: (String) -> Unit,
  onHomeClick: () -> Unit,
  onJoinByCode: (String) -> Unit = {},
) {
  CrexMatchesScreen(onBack, onMatchClick, onHomeClick, onJoinByCode)
}

@OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)
@Composable
private fun CrexMatchesScreen(
  onBack: () -> Unit,
  onMatchClick: (String) -> Unit,
  onHomeClick: () -> Unit,
  onJoinByCode: (String) -> Unit = {},
) {
  val view = LocalView.current

  SideEffect {
    val activity = view.context as? Activity ?: return@SideEffect
    val window = activity.window
    WindowCompat.setDecorFitsSystemWindows(window, false)
    window.statusBarColor = android.graphics.Color.TRANSPARENT
    window.navigationBarColor = android.graphics.Color.TRANSPARENT
  }

  val bg = LightBackground
  var selectedSport by remember { mutableStateOf("Cricket") }
  var selectedTab by remember { mutableStateOf(0) }
  var showCreateWizard by remember { mutableStateOf(false) }
  var isCreatingMatch by remember { mutableStateOf(false) }
  // Holds the share code after a private match is created (drives the share dialog).
  var createdJoinCode by remember { mutableStateOf<String?>(null) }
  // After a match is created, drive the toss flow (coin flip → bat/bowl → opening lineup → start).
  var tossSetup by remember { mutableStateOf<com.example.thanna.ui.matches.TossSetup?>(null) }
  // Drives the "Join private match by code" dialog.
  var showJoinDialog by remember { mutableStateOf(false) }
  val context = LocalContext.current
  val scope = rememberCoroutineScope()
  val matchRepository = remember { com.example.thanna.data.MatchRepository() }
  val playerRepository = remember { com.example.thanna.data.PlayerRepository() }
  val profileRepository = remember { com.example.thanna.data.ProfileRepository() }
  val accountRepository = remember { com.example.thanna.data.AccountRepository() }
  var showMenu by remember { mutableStateOf(false) }
  var showSettings by remember { mutableStateOf(false) }
  var showProfile by remember { mutableStateOf(false) }
  var selectedLeaderboardPlayer by remember { mutableStateOf<LeaderboardPlayer?>(null) }
  val listState = rememberLazyListState()

  // Real GameHub live feed — null = still loading. Any failure yields an empty list,
  // so the screen falls back to its demo leagues and never shows an error.
  // The token scopes the feed to the viewer's district (+ FEATURED); admins get all.
  var liveFeed by remember { mutableStateOf<List<com.example.thanna.data.LiveMatchRow>?>(null) }
  val loadLiveFeed: suspend () -> Unit = remember {
    {
      val token = com.example.thanna.data.TokenStore.getToken(context)
      // On refresh, keep the last good feed if a fetch fails rather than flashing back
      // to the demo leagues; only the initial null shows the loading state.
      runCatching { matchRepository.getLiveMatches(token) }.getOrNull()?.let { liveFeed = it }
    }
  }
  LaunchedEffect(Unit) { loadLiveFeed() }
  // Keep the live feed ticking without a manual pull: refresh on tab re-focus / app
  // foreground and every 20s while visible; paused entirely in the background.
  AutoRefresh(intervalMs = 20_000L) { loadLiveFeed() }

  // District Home snapshot — lazily loaded the first time the District tab opens.
  // null = not loaded / unavailable (guest or no district set); the card hides itself then.
  val districtRepository = remember { com.example.thanna.data.DistrictRepository() }
  var districtSummary by remember { mutableStateOf<com.example.thanna.data.DistrictSummary?>(null) }
  LaunchedEffect(selectedTab) {
    if ((selectedTab == 2 || selectedTab == 3) && districtSummary == null) {
      val token = com.example.thanna.data.TokenStore.getToken(context)
      districtSummary = districtRepository.fetchSummary(token)
    }
  }

  // Ranked-access gate: 0 = none, 1 = needs login, 2 = needs profile.
  var gateStep by remember { mutableStateOf(0) }
  var pendingRankedAction by remember { mutableStateOf<(() -> Unit)?>(null) }
  val requireRankedAccess: (() -> Unit) -> Unit = remember {
    { action ->
      scope.launch {
        val token = com.example.thanna.data.TokenStore.getToken(context)
        when {
          token.isNullOrBlank() -> { pendingRankedAction = action; gateStep = 1 }
          !profileRepository.isProfileComplete(token) -> { pendingRankedAction = action; gateStep = 2 }
          else -> action()
        }
      }
    }
  }

  Box(modifier = Modifier.fillMaxSize()) {
  Scaffold(
    containerColor = Color.Transparent,
    contentWindowInsets = WindowInsets(0),
    bottomBar = {
      CrexBottomBar(
        selectedSport = selectedSport,
        onSportSelected = { selectedSport = it },
        onHomeClick = onHomeClick,
        // Gate the profile behind sign-in + a completed player profile: an un-set-up
        // user is routed to login / profile setup instead of an empty profile screen.
        onOthersClick = { requireRankedAccess { showProfile = true } }
      )
    }
  ) { padding ->
    val currentBg = if (selectedTab >= 2) T.BgPage else bg
    Box(
      modifier = Modifier
        .fillMaxSize()
        .statusBarsPadding()
        .background(currentBg)
        .padding(padding)
    ) {
      Column(modifier = Modifier.fillMaxSize()) {
        // Fixed app bar that lifts (frost + elevation) as the list scrolls beneath it —
        // the scroll-aware top bar that reads as a polished, native-quality app.
        val scrolled = listState.firstVisibleItemIndex > 0 || listState.firstVisibleItemScrollOffset > 8
        val topElev by animateDpAsState(if (scrolled) 8.dp else 0.dp, label = "topBarElev")
        Column(
          modifier = Modifier
            .fillMaxWidth()
            .zIndex(1f)
            .shadow(topElev)
            .background(currentBg)
            .padding(horizontal = 16.dp)
            .padding(top = 12.dp, bottom = 2.dp)
        ) {
          CrexHeaderSection(onBack, onCreateMatch = { requireRankedAccess { showCreateWizard = true } }, onJoinByCode = { showJoinDialog = true })
          // Live/Finished/District/State board strip — back up top, directly under the header.
          CrexTabsSection(
            selectedTab = selectedTab,
            onTabSelected = { selectedTab = it }
          )
        }

      LazyColumn(
        state = listState,
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(
          start = 16.dp,
          end = 16.dp,
          top = 4.dp,
          bottom = 100.dp
        )
      ) {
        if (selectedTab == 0) {
          // ── LIVE: in-progress matches first, then what's coming up ──
          when (selectedSport) {
            "Cricket" -> {
              // Real live feed surfaces first. The server already scopes it to the
              // viewer: their own district's LOCAL matches + platform-wide FEATURED
              // ones. We split those into two sections so "your district" reads as a
              // distinct, local-identity space above the curated Featured matches.
              when {
                liveFeed == null -> item { GameHubFeedSkeleton() }
                else -> {
                  val districtMatches = liveFeed!!.filter { it.visibility == "LOCAL" }
                  val featuredMatches = liveFeed!!.filter { it.visibility == "FEATURED" }
                  if (districtMatches.isNotEmpty()) {
                    item { CrexLeagueTitle("Live in your district") }
                    item { LiveFeedGroup(rows = districtMatches, onMatchClick = onMatchClick) }
                    item { Spacer(modifier = Modifier.height(8.dp)) }
                  }
                  if (featuredMatches.isNotEmpty()) {
                    item { CrexLeagueTitle("Featured matches") }
                    item { LiveFeedGroup(rows = featuredMatches, onMatchClick = onMatchClick) }
                    item { Spacer(modifier = Modifier.height(8.dp)) }
                  }
                  if (districtMatches.isEmpty() && featuredMatches.isEmpty()) {
                    item {
                      Box(modifier = Modifier.fillMaxWidth().padding(vertical = 32.dp), contentAlignment = Alignment.Center) {
                        Text("No live matches in your area yet", color = Color(0xFF94A3B8), fontSize = 13.sp)
                      }
                    }
                  }
                }
              }
            }
            else -> {
              // Only Cricket has a real live feed today. Other sports show an honest
              // empty state instead of fabricated pro fixtures.
              item { CrexTabEmpty("No live $selectedSport matches yet") }
            }
          }
        } else if (selectedTab == 1) {
          // ── FINISHED: completed results only. No real finished-match feed is wired
          // yet, so every sport shows an honest empty state (no demo results). ──
          item { CrexTabEmpty("No finished $selectedSport matches yet") }
        } else if (selectedTab == 2) {
          districtSummary?.let { summary ->
            item { DistrictHomeCard(summary) }
            item { Spacer(modifier = Modifier.height(4.dp)) }
          }
          item {
            CrexLeaderboardSection(
              isStateBoard = false,
              location = districtSummary?.district,
              onPlayerClick = { selectedLeaderboardPlayer = it },
            )
          }
        } else if (selectedTab == 3) {
          item {
            CrexLeaderboardSection(
              isStateBoard = true,
              location = districtSummary?.state,
              onPlayerClick = { selectedLeaderboardPlayer = it },
            )
          }
        }
      }
      }
    }
  }

    if (showCreateWizard) {
      com.example.thanna.ui.matches.create.CreateMatchWizard(
        sport = selectedSport,
        onDismiss = { showCreateWizard = false },
        onCreate = { draft ->
          if (!isCreatingMatch) {
            isCreatingMatch = true
            scope.launch {
              try {
                val token = com.example.thanna.data.TokenStore.getToken(context)
                if (token.isNullOrBlank()) {
                  Toast.makeText(context, "Please sign in to create a match.", Toast.LENGTH_LONG).show()
                } else {
                  val emblems = com.example.thanna.ui.matches.create.teamEmblems
                  val result = matchRepository.createMatch(
                    token = token,
                    sport = draft.sport,
                    matchType = draft.type.serverValue,
                    overs = draft.overs,
                    ball = draft.ball.serverValue,
                    playersPerSide = draft.playersPerSide,
                    venue = draft.venue,
                    locality = draft.locality,
                    onHaraanTurf = draft.onHaraanTurf,
                    teamA = draft.teamA,
                    teamB = draft.teamB,
                    squadA = draft.squadA.toList(),
                    squadB = draft.squadB.toList(),
                    teamAEmblem = emblems.getOrNull(draft.teamAEmblem)?.key,
                    teamBEmblem = emblems.getOrNull(draft.teamBEmblem)?.key,
                    venueBookingId = draft.venueBookingId,
                    isPrivate = draft.isPrivate,
                  )
                  // Upload any custom team crests now that the match (and its id) exist.
                  // A failed upload doesn't fail the whole creation — the emblem stands in.
                  uploadTeamLogoIfPresent(context, matchRepository, token, result.matchId, "home", draft.teamAPhoto)
                  uploadTeamLogoIfPresent(context, matchRepository, token, result.matchId, "away", draft.teamBPhoto)
                  showCreateWizard = false
                  // Straight into the toss: coin flip → bat/bowl → opening lineup → start.
                  // The share code (private) is surfaced after the toss, not before.
                  tossSetup = com.example.thanna.ui.matches.TossSetup(
                    matchId = result.matchId.toString(),
                    teamA = draft.teamA,
                    teamB = draft.teamB,
                    squadA = draft.squadA.toList(),
                    squadB = draft.squadB.toList(),
                    isPrivate = result.isPrivate,
                    joinCode = result.joinCode,
                    teamAEmblem = emblems.getOrNull(draft.teamAEmblem)?.key,
                    teamBEmblem = emblems.getOrNull(draft.teamBEmblem)?.key,
                    teamAPhoto = draft.teamAPhoto,
                    teamBPhoto = draft.teamBPhoto,
                  )
                }
              } catch (e: Exception) {
                Toast.makeText(context, e.message ?: "Failed to create match.", Toast.LENGTH_LONG).show()
              } finally {
                isCreatingMatch = false
              }
            }
          }
        },
        lookupPlayer = { playerId ->
          val token = com.example.thanna.data.TokenStore.getToken(context)
          if (token.isNullOrBlank()) null else playerRepository.lookup(token, playerId)
        },
        loadBookings = {
          val token = com.example.thanna.data.TokenStore.getToken(context)
          if (token.isNullOrBlank()) emptyList() else accountRepository.fetchBookings(token)
        },
        modifier = Modifier.statusBarsPadding(),
      )
    }

    tossSetup?.let { setup ->
      com.example.thanna.ui.matches.TossScreen(
        matchId = setup.matchId,
        teamA = setup.teamA,
        teamB = setup.teamB,
        squadA = setup.squadA,
        squadB = setup.squadB,
        teamAEmblem = setup.teamAEmblem,
        teamBEmblem = setup.teamBEmblem,
        teamAPhoto = setup.teamAPhoto,
        teamBPhoto = setup.teamBPhoto,
        onStarted = {
          tossSetup = null
          // Private match → now surface the share code so the group can follow it.
          if (setup.isPrivate && setup.joinCode.isNotBlank()) createdJoinCode = setup.joinCode
        },
        onCancel = {
          tossSetup = null
          if (setup.isPrivate && setup.joinCode.isNotBlank()) createdJoinCode = setup.joinCode
          else Toast.makeText(context, "Match created — toss it later from the match.", Toast.LENGTH_SHORT).show()
        },
      )
    }

    createdJoinCode?.let { code ->
      PrivateMatchShareDialog(
        code = code,
        onDismiss = { createdJoinCode = null },
      )
    }

    if (showJoinDialog) {
      JoinByCodeDialog(
        onDismiss = { showJoinDialog = false },
        onJoin = { code ->
          showJoinDialog = false
          onJoinByCode(code)
        },
      )
    }

    if (showMenu) {
      com.example.thanna.ui.profile.ActionMenuScreen(
        onClose = { showMenu = false },
        onProfile = { showProfile = true },
        onLeaderboards = { showMenu = false; selectedTab = 2 },
        onSettings = { showMenu = false; showSettings = true },
        onSignOut = {
          com.example.thanna.data.TokenStore.clearToken(context)
          showMenu = false
          Toast.makeText(context, "Signed out.", Toast.LENGTH_SHORT).show()
        },
        fetchProfile = {
          val token = com.example.thanna.data.TokenStore.getToken(context)
            ?: throw IllegalStateException("Please sign in to view your profile.")
          profileRepository.fetchMe(token)
        },
        modifier = Modifier.statusBarsPadding(),
      )
    }

    if (showSettings) {
      com.example.thanna.ui.profile.SettingsScreen(
        onBack = { showSettings = false },
        modifier = Modifier.statusBarsPadding(),
      )
    }

    selectedLeaderboardPlayer?.let { player ->
      // Tapping any leaderboard player opens the SAME real profile screen used by
      // "Others" in the bottom bar — just loaded for that player by their Player ID.
      // The old fabricated Crex profile card is retired.
      com.example.thanna.ui.profile.PlayerProfileScreen(
        onBack = { selectedLeaderboardPlayer = null },
        fetchProfile = {
          val token = com.example.thanna.data.TokenStore.getToken(context)
          profileRepository.fetchPlayer(token, player.playerId)
        },
        modifier = Modifier.statusBarsPadding(),
      )
    }

    if (showProfile) {
      com.example.thanna.ui.profile.PlayerProfileScreen(
        onBack = { showProfile = false },
        fetchProfile = {
          val token = com.example.thanna.data.TokenStore.getToken(context)
            ?: throw IllegalStateException("Please sign in to view your profile.")
          profileRepository.fetchMe(token)
        },
        modifier = Modifier.statusBarsPadding(),
      )
    }

    // ── Ranked-access gate overlays ──────────────────────────────────────────
    if (gateStep == 1) {
      com.example.thanna.ui.LoginRoute(
        onSkipClick = { gateStep = 0; pendingRankedAction = null },
        onLoginSuccess = { token ->
          com.example.thanna.data.TokenStore.saveToken(context, token)
          scope.launch {
            if (profileRepository.isProfileComplete(token)) {
              gateStep = 0
              val a = pendingRankedAction; pendingRankedAction = null; a?.invoke()
            } else {
              gateStep = 2
            }
          }
        },
        modifier = Modifier.statusBarsPadding(),
      )
    }

    if (gateStep == 2) {
      com.example.thanna.ui.profile.PlayerProfileSetupScreen(
        onClose = { gateStep = 0; pendingRankedAction = null },
        onSave = { name, st, district, primarySport, sportAttributes, gender, dob, birthPlace, height, nationality, photoUri ->
          val token = com.example.thanna.data.TokenStore.getToken(context)
            ?: throw IllegalStateException("Please sign in again.")
          profileRepository.saveProfile(
            token, name, st, district, primarySport, sportAttributes,
            gender, dob, birthPlace, height, nationality,
          )
          uploadAvatarIfPresent(context, profileRepository, token, photoUri)
        },
        onDone = {
          gateStep = 0
          val a = pendingRankedAction; pendingRankedAction = null; a?.invoke()
        },
        modifier = Modifier.statusBarsPadding(),
      )
    }
  }
}

// Reads a picked image Uri and uploads it as a team crest. Best-effort: any failure is
// swallowed so a flaky upload never blocks match creation — the chosen emblem stands in.
private suspend fun uploadTeamLogoIfPresent(
  context: android.content.Context,
  repo: com.example.thanna.data.MatchRepository,
  token: String,
  matchId: Long,
  side: String,
  uri: android.net.Uri?,
) {
  if (uri == null || matchId <= 0L) return
  try {
    val resolver = context.contentResolver
    val mime = resolver.getType(uri) ?: "image/jpeg"
    val bytes = kotlinx.coroutines.withContext(kotlinx.coroutines.Dispatchers.IO) {
      resolver.openInputStream(uri)?.use { it.readBytes() }
    } ?: return
    repo.uploadTeamLogo(token, matchId, side, bytes, mime)
  } catch (_: Exception) {
    // Non-fatal — the emblem remains the team's icon.
  }
}

// Reads a picked image Uri and uploads it as the player's profile photo. Best-effort: a flaky
// upload never blocks profile creation — the player just keeps the generated portrait for now.
private suspend fun uploadAvatarIfPresent(
  context: android.content.Context,
  repo: com.example.thanna.data.ProfileRepository,
  token: String,
  uri: android.net.Uri?,
) {
  if (uri == null) return
  try {
    val resolver = context.contentResolver
    val mime = resolver.getType(uri) ?: "image/jpeg"
    val bytes = kotlinx.coroutines.withContext(kotlinx.coroutines.Dispatchers.IO) {
      resolver.openInputStream(uri)?.use { it.readBytes() }
    } ?: return
    repo.uploadAvatar(token, bytes, mime)
  } catch (_: Exception) {
    // Non-fatal — the profile is already saved; the photo can be added later.
  }
}

// Shown right after a private match is created: the share code is the only way in,
// so we surface it prominently with copy + system-share actions.
@Composable
private fun PrivateMatchShareDialog(code: String, onDismiss: () -> Unit) {
  val localContext = androidx.compose.ui.platform.LocalContext.current
  val clipboard = androidx.compose.ui.platform.LocalClipboardManager.current
  val shareText = "Follow my live match on Haraan — open the app and enter code $code"

  androidx.compose.material3.AlertDialog(
    onDismissRequest = onDismiss,
    title = { Text("Private match created", fontWeight = FontWeight.Bold) },
    text = {
      Column {
        Text(
          "Share this code so your group can follow the score. It's the only way to open this match.",
          fontSize = 14.sp,
          color = Color(0xFF475569),
        )
        Spacer(Modifier.height(16.dp))
        Box(
          modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(Color(0xFFF1F5F9))
            .padding(vertical = 16.dp),
          contentAlignment = Alignment.Center,
        ) {
          Text(
            code,
            fontSize = 24.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 2.sp,
            color = Color(0xFF0F172A),
          )
        }
      }
    },
    confirmButton = {
      androidx.compose.material3.TextButton(onClick = {
        val send = android.content.Intent(android.content.Intent.ACTION_SEND).apply {
          type = "text/plain"
          putExtra(android.content.Intent.EXTRA_TEXT, shareText)
        }
        localContext.startActivity(android.content.Intent.createChooser(send, "Share match code"))
      }) {
        Text("Share", color = Color(0xFF2563EB), fontWeight = FontWeight.Bold)
      }
    },
    dismissButton = {
      androidx.compose.material3.TextButton(onClick = {
        clipboard.setText(androidx.compose.ui.text.AnnotatedString(code))
        Toast.makeText(localContext, "Code copied", Toast.LENGTH_SHORT).show()
        onDismiss()
      }) {
        Text("Copy & close", color = Color(0xFF64748B))
      }
    },
  )
}

// Lets a viewer open a private match by typing the share code they were given.
@Composable
private fun JoinByCodeDialog(onDismiss: () -> Unit, onJoin: (String) -> Unit) {
  var code by remember { mutableStateOf("") }
  val trimmed = code.trim()

  androidx.compose.material3.AlertDialog(
    onDismissRequest = onDismiss,
    title = { Text("Join a private match", fontWeight = FontWeight.Bold) },
    text = {
      Column {
        Text(
          "Enter the share code you were given to follow a private match.",
          fontSize = 14.sp,
          color = Color(0xFF475569),
        )
        Spacer(Modifier.height(16.dp))
        androidx.compose.material3.OutlinedTextField(
          value = code,
          onValueChange = { code = it.uppercase() },
          singleLine = true,
          placeholder = { Text("e.g. HRN-7K2Q") },
          modifier = Modifier.fillMaxWidth(),
          shape = RoundedCornerShape(12.dp),
        )
      }
    },
    confirmButton = {
      androidx.compose.material3.TextButton(
        onClick = { onJoin(trimmed) },
        enabled = trimmed.length >= 4,
      ) {
        Text("Watch", color = if (trimmed.length >= 4) Color(0xFF2563EB) else Color(0xFF94A3B8), fontWeight = FontWeight.Bold)
      }
    },
    dismissButton = {
      androidx.compose.material3.TextButton(onClick = onDismiss) {
        Text("Cancel", color = Color(0xFF64748B))
      }
    },
  )
}

@Composable
private fun CrexHeaderSection(onBack: () -> Unit, onCreateMatch: () -> Unit, onJoinByCode: () -> Unit = {}) {
  Row(
    modifier = Modifier
      .fillMaxWidth()
      .padding(top = 8.dp, bottom = 12.dp),
    verticalAlignment = Alignment.CenterVertically,
    horizontalArrangement = Arrangement.spacedBy(10.dp)
  ) {
    Box(
      modifier = Modifier
        .size(36.dp)
        .clip(RoundedCornerShape(12.dp))
        .background(Color(0xFFF1F5F9)),
      contentAlignment = Alignment.Center
    ) {
      Image(
        painter = painterResource(id = com.example.thanna.R.drawable.haraan_copy),
        contentDescription = "Haraan logo",
        modifier = Modifier.size(22.dp),
        contentScale = ContentScale.Fit,
        colorFilter = ColorFilter.tint(LightAccentBlue)
      )
    }

    // Wordmark — gives the bar a branded identity instead of a lone icon button.
    Text(
      text = "Haraan",
      fontSize = 18.sp,
      fontWeight = FontWeight.ExtraBold,
      color = LightPrimaryText,
      letterSpacing = (-0.5).sp,
    )

    Spacer(modifier = Modifier.weight(1f))

    // Search demoted to an icon — on a leaderboard it's secondary, so this declutters the
    // header (brand left, actions right) and lets the board breathe.
    Box(
      modifier = Modifier
        .size(38.dp)
        .clip(RoundedCornerShape(12.dp))
        .background(Color(0xFFF1F5F9)),
      contentAlignment = Alignment.Center
    ) {
      Icon(
        imageVector = Icons.Default.Search,
        contentDescription = "Search",
        tint = LightSecondaryText,
        modifier = Modifier.size(18.dp)
      )
    }

    // Join a private match by its share code.
    Box(
      modifier = Modifier
        .size(38.dp)
        .clip(RoundedCornerShape(12.dp))
        .background(Color(0xFFF1F5F9))
        .clickable(onClick = onJoinByCode),
      contentAlignment = Alignment.Center
    ) {
      Icon(
        imageVector = Icons.Default.Login,
        contentDescription = "Join by code",
        tint = LightSecondaryText,
        modifier = Modifier.size(18.dp)
      )
    }

    Button(
      onClick = onCreateMatch,
      modifier = Modifier.height(38.dp),
      shape = RoundedCornerShape(22.dp),
      contentPadding = PaddingValues(horizontal = 14.dp, vertical = 0.dp),
      colors = ButtonDefaults.buttonColors(
        containerColor = LightAccentBlue,
        contentColor = Color.White
      )
    ) {
      Icon(
        imageVector = Icons.Default.Add,
        contentDescription = null,
        modifier = Modifier.size(16.dp)
      )
      Spacer(modifier = Modifier.width(4.dp))
      Text(text = "Create", fontSize = 13.5.sp, fontWeight = FontWeight.Bold)
    }
    // The menu (≡) that used to sit here now lives on the bottom-bar "Others" button.
  }
}


@Composable
private fun CrexTabsSection(
  selectedTab: Int,
  onTabSelected: (Int) -> Unit
) {
  data class TabItem(val title: String, val icon: ImageVector)

  val tabs = listOf(
    TabItem("Live", Icons.Default.PlayArrow),
    TabItem("Finished", Icons.Default.CheckCircle),
    TabItem("District", Icons.Default.Apartment),
    TabItem("State", Icons.Default.AccountBalance)
  )

  Column(
    modifier = Modifier
      .fillMaxWidth()
      .padding(top = 4.dp)
  ) {
    Row(
      modifier = Modifier.fillMaxWidth(),
      horizontalArrangement = Arrangement.SpaceEvenly,
    ) {
      tabs.forEachIndexed { index, tab ->
        val isSelected = selectedTab == index
        // Smooth color crossfade between active/inactive (vs. an instant cut).
        val tabColor by androidx.compose.animation.animateColorAsState(
          targetValue = if (isSelected) Color(0xFF2563EB) else Color(0xFF94A3B8),
          animationSpec = tween(200),
          label = "tabColor"
        )
        Column(
          modifier = Modifier
            .weight(1f)
            .clickable { onTabSelected(index) }
            .padding(top = 8.dp),
          horizontalAlignment = Alignment.CenterHorizontally,
          verticalArrangement = Arrangement.Center
        ) {
          Box(contentAlignment = Alignment.TopEnd) {
            Icon(
              imageVector = tab.icon,
              contentDescription = null,
              tint = tabColor,
              modifier = Modifier.size(17.dp)
            )
            if (tab.title == "Live") {
              LivePulseDot(Modifier.offset(x = 5.dp, y = (-3).dp))
            }
          }

          Spacer(modifier = Modifier.height(5.dp))

          Text(
            text = tab.title,
            fontSize = 12.5.sp,
            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.SemiBold,
            color = tabColor
          )

        }
      }
    }

    // Single sliding indicator that animates between tabs (vs. the old per-tab block
    // that just blinked on) — the motion is what reads "expensive".
    Spacer(Modifier.height(8.dp))
    BoxWithConstraints(Modifier.fillMaxWidth().height(2.5.dp)) {
      val slot = maxWidth / tabs.size
      val indW = slot * 0.5f
      val pos by animateDpAsState(
        targetValue = slot * selectedTab + (slot - indW) / 2f,
        // Emphasized easing — the "expensive" travel curve (fast out, gentle settle).
        animationSpec = tween(300, easing = androidx.compose.animation.core.CubicBezierEasing(0.2f, 0f, 0f, 1f)),
        label = "tabSlide",
      )
      Box(
        Modifier
          .offset(x = pos)
          .width(indW)
          .height(2.5.dp)
          .clip(RoundedCornerShape(topStart = 2.dp, topEnd = 2.dp))
          .background(Color(0xFF2563EB))
      )
    }
    Box(
      modifier = Modifier
        .fillMaxWidth()
        .height(1.dp)
        .background(Color(0xFFE2E8F0))
    )
  }
}


// Softly pulsing red dot — signals live activity on the Live tab.
@Composable
private fun LivePulseDot(modifier: Modifier = Modifier) {
  val t = rememberInfiniteTransition(label = "livePulse")
  val a by t.animateFloat(
    initialValue = 1f, targetValue = 0.25f,
    animationSpec = infiniteRepeatable(tween(750), RepeatMode.Reverse),
    label = "livePulseAlpha",
  )
  Box(
    modifier
      .size(7.dp)
      .graphicsLayer { alpha = a }
      .background(Color(0xFFEF4444), CircleShape)
  )
}


internal data class LeaderboardPlayer(
  val name: String,
  val team: String,
  val primaryStat: String,
  val secondaryStat: String,
  val rank: Int,
  // Uploaded profile photo URL. Blank → the profile falls back to a generated portrait.
  val photoUrl: String = "",
  // Stable Player ID (HRN…) from the ranked board. When present, tapping the row opens
  // the player's real profile (loaded by id); demo/seed rows leave this blank.
  val playerId: String = ""
)

@Composable
private fun PlayerTeamDetails(team: String) {
    val parts = team.split(Regex("[•·]"))
    val district = parts.firstOrNull()?.trim() ?: ""
    val extra = parts.getOrNull(1)?.trim() ?: ""
    Row(
        modifier = Modifier.padding(top = 2.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(5.dp)
    ) {
        if (district.isNotEmpty()) {
            Text(text = district, fontSize = 11.sp, fontWeight = FontWeight.Medium, color = T.Text3, maxLines = 1, softWrap = false, overflow = TextOverflow.Ellipsis)
        }
        if (extra.isNotEmpty()) {
            Box(Modifier.size(3.dp).clip(CircleShape).background(Color(0xFFCACAD5)))
            Text(text = extra, fontSize = 11.sp, fontWeight = FontWeight.Medium, color = T.Text3, maxLines = 1, softWrap = false)
        }
    }
}


// Parse a stat string ("Avg 41.5  HS 76" or "15 inn · Avg 42.8") into (label, value)
// pairs so they can render as aligned columns instead of a cramped middot string.
private fun parseStatPairs(s: String): List<Pair<String, String>> =
    s.split(Regex("\\s{2,}|·|•")).mapNotNull { part ->
        val t = part.trim().split(Regex("\\s+")).filter { it.isNotEmpty() }
        if (t.size < 2) return@mapNotNull null
        val firstIsValue = t[0].firstOrNull()?.isDigit() == true
        if (firstIsValue) t.drop(1).joinToString(" ") to t[0]
        else t[0] to t.drop(1).joinToString(" ")
    }

// One stacked stat column: value (tabular, bold) over a small caps label.
@Composable
private fun StatMini(label: String, value: String) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Text(
            value, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = T.Text1,
            letterSpacing = (-0.2).sp, style = TextStyle(fontFeatureSettings = "tnum"),
        )
        Spacer(Modifier.height(1.dp))
        Text(
            label.uppercase(), fontSize = 8.5.sp, fontWeight = FontWeight.SemiBold,
            color = T.Text3, letterSpacing = 0.5.sp,
        )
    }
}

// Week-over-week rank movement. Demo data has no history, so derive a stable delta
// from the player so the board reads as "alive / contested" rather than frozen.
@Composable
private fun RankMovement(seedKey: String) {
    val mv = remember(seedKey) { (kotlin.math.abs(seedKey.hashCode()) % 7) - 3 }
    when {
        mv > 0 -> Row(verticalAlignment = Alignment.CenterVertically) {
            TrendTriangle(up = true, color = Color(0xFF12824A), size = 6.dp)
            Spacer(Modifier.width(1.dp))
            Text("$mv", color = Color(0xFF12824A), fontSize = 8.sp, fontWeight = FontWeight.Bold,
                style = TextStyle(fontFeatureSettings = "tnum"))
        }
        mv < 0 -> Row(verticalAlignment = Alignment.CenterVertically) {
            TrendTriangle(up = false, color = Color(0xFFD23F57), size = 6.dp)
            Spacer(Modifier.width(1.dp))
            Text("${-mv}", color = Color(0xFFD23F57), fontSize = 8.sp, fontWeight = FontWeight.Bold,
                style = TextStyle(fontFeatureSettings = "tnum"))
        }
        else -> Text("–", color = T.Text3, fontSize = 9.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
private fun LeaderboardRowContent(player: LeaderboardPlayer, onClick: () -> Unit = {}) {
    Row(
      modifier = Modifier
        .fillMaxWidth()
        .clickable { onClick() }
        .padding(horizontal = 12.dp, vertical = 13.dp),
      verticalAlignment = Alignment.CenterVertically
    ) {
      // 1. Rank number + week-over-week movement
      Column(
        modifier = Modifier.width(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
      ) {
        Text(
          text = player.rank.toString(),
          fontSize = 13.sp,
          fontWeight = FontWeight.Bold,
          color = Color(0xFFB0B0BE),
          textAlign = TextAlign.Center,
          style = TextStyle(fontFeatureSettings = "tnum"),
        )
        Spacer(Modifier.height(2.dp))
        RankMovement(player.name + player.rank)
      }

      Spacer(modifier = Modifier.width(12.dp))

      // 2. Circular Avatar with initials — colour is the player's identity (stable
      // per name), not their current rank, so a player keeps their look across the app.
      val avatarBgColor = playerColor(player.name)
      val initials = player.name.split(" ")
        .mapNotNull { it.firstOrNull() }
        .take(2)
        .joinToString("")
        .uppercase()
      
      Box(
        modifier = Modifier
          .size(42.dp)
          .shadow(3.dp, CircleShape, ambientColor = Color(0x18000000), spotColor = Color(0x20000000))
          .clip(CircleShape)
          .background(avatarBgColor)
          // Subtle brand ring echoes the medal rings on the podium avatars.
          .border(2.dp, Color(0xFF2563EB).copy(alpha = 0.16f), CircleShape),
        contentAlignment = Alignment.Center
      ) {
        Text(
          text = initials,
          fontSize = 12.5.sp,
          fontWeight = FontWeight.Bold,
          color = Color.White
        )
      }

      Spacer(modifier = Modifier.width(12.dp))

      // 3. Player Details
      Column(modifier = Modifier.weight(1f)) {
        Text(
          text = player.name,
          fontSize = 14.sp,
          fontWeight = FontWeight.Bold,
          color = T.Text1,
          letterSpacing = (-0.2).sp,
          maxLines = 1,
          overflow = TextOverflow.Ellipsis
        )
        PlayerTeamDetails(player.team)
      }

      // 4. Stats details — aligned columns (Avg / HS) + the headline metric, so the
      // numbers line up down the whole list like a stats terminal.
      val pairs = remember(player.secondaryStat) { parseStatPairs(player.secondaryStat) }
      val pTokens = player.primaryStat.trim().split(Regex("\\s+")).filter { it.isNotEmpty() }
      val pValue = pTokens.firstOrNull { tok -> tok.any { it.isDigit() } } ?: player.primaryStat
      val pLabel = pTokens.drop(1).joinToString(" ").ifBlank { null }
      Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
      ) {
        // Cap to 2 secondary stats so the name/district column isn't squeezed on narrow screens.
        pairs.takeLast(2).forEach { (label, value) -> StatMini(label, value) }
        Column(horizontalAlignment = Alignment.End) {
          Text(
            text = pValue,
            fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = T.Text1,
            letterSpacing = (-0.5).sp, style = TextStyle(fontFeatureSettings = "tnum"),
          )
          if (pLabel != null) {
            Spacer(Modifier.height(1.dp))
            Text(
              pLabel.uppercase(), fontSize = 8.5.sp, fontWeight = FontWeight.SemiBold,
              color = T.Text3, letterSpacing = 0.5.sp,
            )
          }
        }
      }
    }
}

// Deterministic avatar colour from the player's name — gives every player a stable
// identity hue (no pink, to match the app's blue/green/medal system).
private fun playerColor(name: String): Color {
  val palette = listOf(
    Color(0xFF2563EB), // blue
    Color(0xFF6D28D9), // violet
    Color(0xFF0D9488), // teal
    Color(0xFF15803D), // emerald
    Color(0xFFB45309), // amber
    Color(0xFF334155), // slate
    Color(0xFF0E7490), // cyan
    Color(0xFF7C2D12), // brown
  )
  return palette[kotlin.math.abs(name.hashCode()) % palette.size]
}

// Staggered fade-up entrance for list rows — each row eases in slightly after the one
// above, the kind of choreography that reads as a polished, well-funded app.
@Composable
private fun StaggeredAppear(index: Int, content: @Composable () -> Unit) {
  var shown by remember { mutableStateOf(false) }
  LaunchedEffect(Unit) {
    delay(index * 55L)
    shown = true
  }
  val alpha by animateFloatAsState(if (shown) 1f else 0f, tween(280), label = "stagAlpha")
  val dy by animateDpAsState(if (shown) 0.dp else 10.dp, tween(280), label = "stagDy")
  Box(Modifier.offset(y = dy).graphicsLayer { this.alpha = alpha }) { content() }
}

// One grouped surface for the list — rows separated by inset hairlines instead of a
// stack of separate floating cards ("card soup"). Sits below the elevated podium.
@Composable
private fun LeaderboardListGroup(
  players: List<LeaderboardPlayer>,
  onPlayerClick: (LeaderboardPlayer) -> Unit,
) {
  Card(
    modifier = Modifier
      .fillMaxWidth()
      .shadow(2.dp, RoundedCornerShape(16.dp), ambientColor = Color(0x08000000), spotColor = Color(0x0F000000))
      .clip(RoundedCornerShape(16.dp)),
    shape = RoundedCornerShape(16.dp),
    colors = CardDefaults.cardColors(containerColor = T.Surface),
    elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
  ) {
    Column {
      players.forEachIndexed { i, p ->
        StaggeredAppear(i) {
          LeaderboardRowContent(p) { onPlayerClick(p) }
        }
        if (i != players.lastIndex) {
          Box(Modifier.fillMaxWidth().padding(horizontal = 16.dp).height(1.dp).background(T.Divider))
        }
      }
    }
  }
}

// Small filled crown for the champion slot — a signature flourish drawn as a path
// so it scales crisply and matches the gold medal system.
@Composable
private fun Crown(color: Color, modifier: Modifier = Modifier) {
    Canvas(modifier) {
        val w = size.width; val h = size.height
        val p = androidx.compose.ui.graphics.Path().apply {
            moveTo(0f, h)
            lineTo(w * 0.06f, h * 0.42f)
            lineTo(w * 0.27f, h * 0.60f)
            lineTo(w * 0.5f, h * 0.04f)
            lineTo(w * 0.73f, h * 0.60f)
            lineTo(w * 0.94f, h * 0.42f)
            lineTo(w, h)
            close()
        }
        drawPath(p, color)
    }
}

@Composable
private fun PodiumSlot(
    player: LeaderboardPlayer,
    pedestalH: androidx.compose.ui.unit.Dp,
    avSize: androidx.compose.ui.unit.Dp,
    isFirst: Boolean,
    modifier: Modifier = Modifier,
    onClick: () -> Unit = {},
) {
    val initials = player.name.split(" ")
        .mapNotNull { it.firstOrNull() }
        .take(2)
        .joinToString("")
        .uppercase()
    val medal = medalTheme(player.rank)
    val avatarBg = playerColor(player.name)

    // Count-up the headline score so the board feels alive on load.
    val target = remember(player) { firstNumber(player.primaryStat) }
    var play by remember { mutableStateOf(false) }
    LaunchedEffect(player) { play = true }
    val shown by animateIntAsState(
        targetValue = if (play) target else 0,
        animationSpec = tween(durationMillis = 850),
        label = "podiumScore",
    )

    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .clickable { onClick() },
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        // Crown row — reserved height on all three so names/scores share a baseline.
        Box(Modifier.height(18.dp), contentAlignment = Alignment.Center) {
            if (isFirst && medal != null) Crown(color = medal.grad[1], modifier = Modifier.size(28.dp, 16.dp))
        }
        Spacer(Modifier.height(4.dp))

        // Avatar with a medal coin badge sitting on its lower edge.
        Box(contentAlignment = Alignment.BottomEnd) {
            Box(
                modifier = Modifier
                    .size(avSize)
                    .shadow(6.dp, CircleShape, ambientColor = Color(0x22000000), spotColor = Color(0x28000000))
                    .clip(CircleShape)
                    .background(avatarBg)
                    .border(if (isFirst) 3.dp else 2.5.dp, medal?.rim ?: Color(0xFF94A3B8), CircleShape),
                contentAlignment = Alignment.Center,
            ) {
                Text(initials, fontSize = (avSize.value * 0.265f).sp, fontWeight = FontWeight.ExtraBold, color = Color.White)
            }
            if (medal != null) {
                Box(
                    Modifier
                        .size(if (isFirst) 22.dp else 19.dp)
                        .clip(CircleShape)
                        .background(Brush.linearGradient(medal.grad))
                        .border(1.5.dp, Color.White, CircleShape),
                    contentAlignment = Alignment.Center,
                ) {
                    Text("${player.rank}", color = medal.ink, fontSize = if (isFirst) 11.sp else 9.5.sp, fontWeight = FontWeight.Black)
                }
            }
        }

        Spacer(Modifier.height(7.dp))
        Text(
            text = player.name,
            modifier = Modifier.fillMaxWidth().padding(horizontal = 4.dp),
            fontSize = if (isFirst) 12.5.sp else 11.sp,
            fontWeight = if (isFirst) FontWeight.Bold else FontWeight.SemiBold,
            color = T.Text1, textAlign = TextAlign.Center, maxLines = 1,
            overflow = TextOverflow.Ellipsis, letterSpacing = (-0.2).sp,
        )
        Spacer(Modifier.height(1.dp))
        Text(
            text = "$shown",
            modifier = Modifier.fillMaxWidth(),
            fontSize = if (isFirst) 27.sp else 20.sp, fontWeight = FontWeight.ExtraBold,
            color = T.Text1, textAlign = TextAlign.Center, letterSpacing = (-1.5).sp,
            style = TextStyle(fontFeatureSettings = "tnum"),
        )
        Spacer(Modifier.height(3.dp))
        Text(
            text = player.secondaryStat,
            modifier = Modifier.fillMaxWidth(),
            fontSize = 10.sp, fontWeight = FontWeight.Medium, color = T.Text3, textAlign = TextAlign.Center,
        )

        Spacer(Modifier.height(10.dp))

        // Pedestal — a real, stepped, metallic-tinted platform with an embossed rank
        // numeral and a glossy top sheen, so the trio reads as a trophy stand.
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(pedestalH)
                .clip(RoundedCornerShape(topStart = 10.dp, topEnd = 10.dp))
                .background(
                    Brush.verticalGradient(
                        colors = if (medal != null)
                            listOf(medal.grad[0], medal.grad[1], medal.grad.last())
                        else listOf(Color(0xFFE8E8EF), Color(0xFFD8D8E2)),
                    )
                ),
            contentAlignment = Alignment.Center,
        ) {
            Box(
                Modifier.matchParentSize().background(
                    Brush.verticalGradient(
                        listOf(Color.White.copy(alpha = 0.45f), Color.White.copy(alpha = 0f)),
                        startY = 0f, endY = 46f,
                    )
                )
            )
            Text(
                "${player.rank}",
                color = (medal?.ink ?: Color(0xFF6B7280)).copy(alpha = 0.5f),
                fontSize = if (isFirst) 40.sp else 30.sp,
                fontWeight = FontWeight.Black,
                letterSpacing = (-2).sp,
            )
        }
    }
}

@Composable
private fun LeaderboardPodium(
  title: String,
  subtitle: String,
  topPlayers: List<LeaderboardPlayer>,
  modifier: Modifier = Modifier,
  onPlayerClick: (LeaderboardPlayer) -> Unit = {}
) {
  val first = topPlayers.find { it.rank == 1 }
  val second = topPlayers.find { it.rank == 2 }
  val third = topPlayers.find { it.rank == 3 }

  Card(
    modifier = modifier
      .fillMaxWidth()
      .padding(bottom = 4.dp)
      .shadow(
        elevation = 6.dp,
        shape = RoundedCornerShape(20.dp),
        ambientColor = Color(0x0F000000),
        spotColor = Color(0x18000000),
      ),
    shape = RoundedCornerShape(20.dp),
    colors = CardDefaults.cardColors(containerColor = T.Surface),
    elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
  ) {
    Column {
      // Header
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .padding(horizontal = 18.dp)
          .padding(top = 12.dp, bottom = 10.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
      ) {
        Column {
          Text(
            text = title,
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            color = T.Text1,
            letterSpacing = (-0.2).sp,
          )
          Spacer(Modifier.height(3.dp))
          // Freshness meta — the "this data is live" signal top apps always surface.
          Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
            Box(Modifier.size(5.dp).clip(CircleShape).background(Color(0xFF12824A)))
            Text("Updated just now", fontSize = 9.5.sp, color = T.Text3, fontWeight = FontWeight.Medium)
          }
        }
        Row(
            verticalAlignment     = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(4.dp),
        ) {
            val parts = subtitle.split(Regex("[•·]"))
            val part1 = parts.firstOrNull()?.trim() ?: ""
            val part2 = parts.getOrNull(1)?.trim() ?: ""
            Text(part1, fontSize = 10.5.sp, color = T.Text3, fontWeight = FontWeight.Medium)
            if (part2.isNotEmpty()) {
                Box(Modifier.size(3.dp).clip(CircleShape).background(Color(0xFFCACAD5)))
                Text(part2, fontSize = 10.5.sp, color = T.Text3, fontWeight = FontWeight.Medium)
            }
        }
      }

      // 1dp separator under header
      Box(Modifier.fillMaxWidth().height(1.dp).background(Color(0xFFF2F2F5)))

      // Three columns 2nd | 1st | 3rd
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .padding(horizontal = 8.dp)
          .padding(top = 20.dp),
        verticalAlignment = Alignment.Bottom,
      ) {
        if (second != null) {
          PodiumSlot(
            player = second,
            pedestalH = 64.dp,
            avSize = 48.dp,
            isFirst = false,
            modifier = Modifier.weight(1f),
            onClick = { onPlayerClick(second) },
          )
        } else {
          Spacer(modifier = Modifier.weight(1f))
        }

        if (first != null) {
          PodiumSlot(
            player = first,
            pedestalH = 90.dp,
            avSize = 62.dp,
            isFirst = true,
            modifier = Modifier.weight(1f),
            onClick = { onPlayerClick(first) },
          )
        } else {
          Spacer(modifier = Modifier.weight(1f))
        }

        if (third != null) {
          PodiumSlot(
            player = third,
            pedestalH = 48.dp,
            avSize = 48.dp,
            isFirst = false,
            modifier = Modifier.weight(1f),
            onClick = { onPlayerClick(third) },
          )
        } else {
          Spacer(modifier = Modifier.weight(1f))
        }
      }

      // Ground line — closes open bar bottoms sitting flush under the bars
      Box(
        Modifier
          .padding(horizontal = 8.dp)
          .fillMaxWidth()
          .height(1.dp)
          .background(T.Divider)
      )
    }
  }
}

// District Home — the local-identity snapshot at the top of the District tab. Turns
// the match list into a community: live activity, population, the district's batting/
// bowling leaders, and where the district ranks within its state.
@Composable
private fun DistrictHomeCard(summary: com.example.thanna.data.DistrictSummary) {
  Card(
    modifier = Modifier
      .fillMaxWidth()
      .padding(vertical = 8.dp)
      .premiumCardShadow(radius = 14.dp, ambient = 16.dp, contact = 2.dp),
    shape = RoundedCornerShape(16.dp),
    colors = CardDefaults.cardColors(containerColor = Color.White),
    border = BorderStroke(1.dp, Brush.verticalGradient(listOf(Color(0xFFF3F6FA), Color(0xFFD9DFEA)))),
  ) {
    Column(modifier = Modifier.padding(16.dp)) {
      Row(verticalAlignment = Alignment.CenterVertically) {
        Column(modifier = Modifier.weight(1f)) {
          Text(
            text = "YOUR DISTRICT",
            fontSize = 11.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 0.8.sp,
            color = Color(0xFF2563EB),
          )
          Spacer(Modifier.height(2.dp))
          Text(
            text = summary.district,
            fontSize = 22.sp,
            fontWeight = FontWeight.ExtraBold,
            color = T.Text1,
            maxLines = 1,
          )
        }
        // "#4 in Andhra Pradesh" — the district's standing within its state.
        if (summary.districtRank != null && !summary.state.isNullOrBlank()) {
          Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier
              .clip(RoundedCornerShape(12.dp))
              .background(Color(0xFF2563EB).copy(alpha = 0.08f))
              .padding(horizontal = 14.dp, vertical = 8.dp),
          ) {
            Text(
              text = "#${summary.districtRank}",
              fontSize = 20.sp,
              fontWeight = FontWeight.ExtraBold,
              color = Color(0xFF2563EB),
            )
            Text(
              text = "in ${summary.state}",
              fontSize = 10.sp,
              fontWeight = FontWeight.Medium,
              color = T.Text3,
            )
          }
        }
      }

      Spacer(Modifier.height(14.dp))

      // Snapshot tiles.
      Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        DistrictStatTile(Modifier.weight(1f), summary.liveMatches.toString(), "Live", Color(0xFF00C853))
        DistrictStatTile(Modifier.weight(1f), summary.players.toString(), "Players", T.Text1)
        DistrictStatTile(Modifier.weight(1f), summary.totalMatches.toString(), "Matches", T.Text1)
      }

      if (summary.topBatter != null || summary.topBowler != null) {
        Spacer(Modifier.height(14.dp))
        Box(Modifier.fillMaxWidth().height(1.dp).background(Color(0xFFEEF0F4)))
        Spacer(Modifier.height(12.dp))
        summary.topBatter?.let {
          DistrictLeaderRow("Top Batter", it.name.ifBlank { it.playerId }, "${it.value} runs")
        }
        summary.topBowler?.let {
          if (summary.topBatter != null) Spacer(Modifier.height(10.dp))
          DistrictLeaderRow("Top Bowler", it.name.ifBlank { it.playerId }, "${it.value} wkts")
        }
      }
    }
  }
}

@Composable
private fun DistrictStatTile(modifier: Modifier, value: String, label: String, valueColor: Color) {
  Column(
    modifier = modifier
      .clip(RoundedCornerShape(12.dp))
      .background(T.Surface)
      .padding(vertical = 12.dp),
    horizontalAlignment = Alignment.CenterHorizontally,
  ) {
    Text(value, fontSize = 20.sp, fontWeight = FontWeight.ExtraBold, color = valueColor)
    Spacer(Modifier.height(2.dp))
    Text(label, fontSize = 11.sp, fontWeight = FontWeight.Medium, color = T.Text3)
  }
}

@Composable
private fun DistrictLeaderRow(role: String, who: String, stat: String) {
  Row(verticalAlignment = Alignment.CenterVertically) {
    Text(role, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = T.Text3, modifier = Modifier.width(84.dp))
    Text(who, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = T.Text1, maxLines = 1, modifier = Modifier.weight(1f))
    Text(stat, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Color(0xFF2563EB))
  }
}

@Composable
private fun CrexLeaderboardSection(
  isStateBoard: Boolean,
  location: String? = null,
  onPlayerClick: (LeaderboardPlayer) -> Unit = {},
) {
  val categories = listOf("Batters", "Bowlers", "All-rounders")
  var selectedCategoryIndex by remember { mutableStateOf(0) }

  // Real ranked board (by RANKED XP) for this district/state. The API returns one
  // ranked list — not split by batter/bowler — so when we have it we show that and
  // hide the category switch; otherwise we fall back to the demo content below.
  val leaderboardRepo = remember { com.example.thanna.data.LeaderboardRepository() }
  // Re-rank on screen re-focus / app foreground (rank changes slowly, so no constant
  // poll — bumping this tick relaunches the producer, which keeps the last board on
  // screen until the fresh one arrives).
  var boardTick by remember { mutableStateOf(0) }
  AutoRefresh(intervalMs = 0L) { boardTick++ }
  val realPlayers by produceState<List<LeaderboardPlayer>?>(null, location, isStateBoard, boardTick) {
    value = if (location.isNullOrBlank()) {
      null
    } else {
      leaderboardRepo
        .fetchBoard(if (isStateBoard) "state" else "district", location)
        .map { row ->
          LeaderboardPlayer(
            name = row.name,
            team = if (isStateBoard) (row.district ?: "") else (row.state ?: location),
            primaryStat = "${row.xp} XP",
            secondaryStat = if (row.matches > 0) "${row.matches} matches" else "",
            rank = row.rank,
            photoUrl = row.avatar ?: "",
            playerId = row.playerId,
          )
        }
        .ifEmpty { null }
    }
  }
  Column(
    modifier = Modifier
      .fillMaxWidth()
      .padding(vertical = 12.dp)
  ) {
    // The leaderboard ranks by RANKED XP only — never by raw runs/wickets — so the
    // batter/bowler/all-rounder category switch is retired (kept here disabled).
    if (false) {
    Box(
      modifier = Modifier
        .fillMaxWidth()
        .height(38.dp)
        .clip(RoundedCornerShape(21.dp))
        .background(Color(0xFFE2E5EE))
        .padding(3.dp)
    ) {
      BoxWithConstraints(Modifier.fillMaxSize()) {
        val slot = maxWidth / categories.size
        val pos by animateDpAsState(
          targetValue = slot * selectedCategoryIndex,
          animationSpec = tween(260),
          label = "segSlide",
        )
        Box(
          Modifier
            .offset(x = pos)
            .width(slot)
            .fillMaxHeight()
            .shadow(2.dp, RoundedCornerShape(18.dp), ambientColor = Color(0x14000000), spotColor = Color(0x1A000000))
            .clip(RoundedCornerShape(18.dp))
            .background(T.Surface)
        )
        Row(Modifier.fillMaxSize()) {
          categories.forEachIndexed { index, category ->
            val isSelected = selectedCategoryIndex == index
            Box(
              modifier = Modifier
                .weight(1f)
                .fillMaxHeight()
                .clip(RoundedCornerShape(18.dp))
                .clickable { selectedCategoryIndex = index },
              contentAlignment = Alignment.Center,
            ) {
              Text(
                text = category,
                fontSize = 12.sp,
                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                color = if (isSelected) T.Text1 else T.Text3,
              )
            }
          }
        }
      }
    }
    }

    Spacer(modifier = Modifier.height(8.dp))

    // Real ranked board only — no demo players. Empty (still loading or no ranked
    // players in this scope yet) shows an honest empty state below.
    val players = realPlayers.orEmpty()

    val topThree = players.filter { it.rank <= 3 }.sortedBy { it.rank }
    val remainingPlayers = players.filter { it.rank > 3 }.sortedBy { it.rank }

    if (topThree.isNotEmpty()) {
      val title = when {
        !location.isNullOrBlank() -> if (isStateBoard) location else "$location District"
        isStateBoard -> "State Board"
        else -> "District Board"
      }
      val subtitle = "${players.size} ranked ${if (players.size == 1) "player" else "players"}"
      LeaderboardPodium(title = title, subtitle = subtitle, topPlayers = topThree, onPlayerClick = onPlayerClick)
      Spacer(modifier = Modifier.height(10.dp))
    }

    if (remainingPlayers.isNotEmpty()) {
      LeaderboardListGroup(remainingPlayers, onPlayerClick)
    }

    if (players.isEmpty()) {
      CrexTabEmpty("No ranked players in this ${if (isStateBoard) "state" else "district"} yet")
    }
  }
}

// ───────────── Crex-style player profile (opened from leaderboard tap) ──────────
private enum class PlayerRoleKind(val label: String) {
  BATTER("Batter"), BOWLER("Bowler"), ALLROUNDER("All-rounder")
}

private data class CrexCareerRow(
  val format: String,
  val matches: Int,
  val innings: Int,
  val runsOrWickets: Int,
  val best: String,        // HS for batting, BBI for bowling
  val avg: String,
  val rate: String,        // SR for batting, Econ for bowling
  val milestoneA: Int,     // 50s for batting, 4w for bowling
  val milestoneB: Int,     // 100s for batting, 5w for bowling
)

private data class CrexPlayerStats(
  val role: PlayerRoleKind,
  val region: String,
  val matches: Int,
  val headlineLabel: String,   // "Runs" / "Wickets"
  val headlineValue: Int,
  val avg: String,
  val rateLabel: String,       // "SR" / "Econ"
  val rateValue: String,
  val battingRows: List<CrexCareerRow>,
  val bowlingRows: List<CrexCareerRow>,
  val recentForm: List<String>,
  val about: List<Pair<String, String>>,
  val rankChips: List<RankChip>,
  val rankTrend: Int,
  val rankBoardLabel: String,
  val highlights: List<ProfileHighlight>,
  val recentMatches: List<ProfileMatch>,
  val photoUrl: String,
  val jersey: Int,
  val winRate: Int,
  val quickDeltas: List<String?>,
)

private data class ProfileHighlight(val icon: ImageVector, val tint: Color, val value: String, val label: String)

private data class RankChip(
  val label: String,        // "DISTRICT"
  val value: String,        // "#1" or "Top 7%"
  val icon: ImageVector,
  val board: Int?,          // tap target: 2 = District tab, 3 = State tab, null = not tappable
  val percent: Int? = null, // for the percentile bar
)

private data class ProfileMatch(
  val opponent: String,
  val meta: String,      // "District League · 12 Jun"
  val won: Boolean,
  val playerLine: String, // "54 (38)" or "3/24"
  val mom: Boolean,
)

private fun firstNumber(s: String): Int =
  Regex("\\d+").find(s)?.value?.toIntOrNull() ?: 0

private fun afterLabel(s: String, label: String): String? =
  Regex("$label\\s*([0-9.]+\\*?)").find(s)?.groupValues?.getOrNull(1)

@Composable
private fun CrexProfileTabs(selected: Int, onSelect: (Int) -> Unit) {
  val tabs = listOf("Overview", "Matches", "News")
  Row(Modifier.fillMaxWidth()) {
    tabs.forEachIndexed { i, label ->
      val sel = i == selected
      Column(
        modifier = Modifier
          .weight(1f)
          .clip(RoundedCornerShape(8.dp))
          .clickable { onSelect(i) }
          .padding(vertical = 6.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
      ) {
        Text(
          label,
          color = if (sel) Color(0xFF2563EB) else T.Text3,
          fontSize = 13.5.sp,
          fontWeight = if (sel) FontWeight.Bold else FontWeight.Medium,
        )
        Spacer(Modifier.height(6.dp))
        Box(
          Modifier
            .fillMaxWidth(0.55f)
            .height(2.5.dp)
            .clip(RoundedCornerShape(2.dp))
            .background(if (sel) Color(0xFF2563EB) else Color.Transparent)
        )
      }
    }
  }
}

private data class MedalTheme(val grad: List<Color>, val rim: Color, val ink: Color)

private fun medalTheme(rank: Int): MedalTheme? = when (rank) {
  1 -> MedalTheme(
    grad = listOf(Color(0xFFFFF3C0), Color(0xFFF3CB57), Color(0xFFCF9A1C)),
    rim = Color(0xFFFFEBA6), ink = Color(0xFF5A3F00),
  )
  2 -> MedalTheme(
    grad = listOf(Color(0xFFF6F9FD), Color(0xFFD4DBE6), Color(0xFFA5AFC0)),
    rim = Color(0xFFFFFFFF), ink = Color(0xFF374052),
  )
  3 -> MedalTheme(
    grad = listOf(Color(0xFFF6CFA3), Color(0xFFD99457), Color(0xFFA75F2B)),
    rim = Color(0xFFF8D8B4), ink = Color(0xFF4A2A0E),
  )
  else -> null
}

@Composable
private fun CrexProfileHero(player: LeaderboardPlayer, stats: CrexPlayerStats, onOpenBoard: (Int) -> Unit = {}) {
  val initials = player.name.split(" ").mapNotNull { it.firstOrNull() }.take(2).joinToString("").uppercase()
  var showPhoto by remember { mutableStateOf(false) }
  Box(
    Modifier
      .fillMaxWidth()
      .shadow(16.dp, RoundedCornerShape(22.dp), ambientColor = Color(0x26000000), spotColor = Color(0x591E3A8A))
      .clip(RoundedCornerShape(22.dp))
      .background(Brush.linearGradient(listOf(Color(0xFF0B1220), Color(0xFF15265F), Color(0xFF1E40AF))))
  ) {
    // Soft radial glow biased toward the avatar for depth.
    Box(
      Modifier
        .matchParentSize()
        .background(
          Brush.radialGradient(
            colors = listOf(Color.White.copy(alpha = 0.12f), Color.Transparent),
            center = Offset(230f, 140f),
            radius = 720f,
          )
        )
    )
    // Hairline top highlight — a "lit from above" sheen that reads as real material
    // depth, replacing the flat alpha-borders this card used to lean on.
    Box(
      Modifier
        .matchParentSize()
        .background(
          Brush.verticalGradient(
            colors = listOf(Color.White.copy(alpha = 0.10f), Color.Transparent),
            startY = 0f, endY = 130f,
          )
        )
    )
    // Giant faint jersey-number watermark for depth — kept as texture, not a number
    // you read, so the alpha stays very low.
    Text(
      "${stats.jersey}",
      color = Color.White.copy(alpha = 0.05f),
      fontSize = 158.sp,
      fontWeight = FontWeight.Black,
      letterSpacing = (-4).sp,
      modifier = Modifier.align(Alignment.CenterEnd).padding(end = 4.dp),
    )
   Column(Modifier.padding(20.dp)) {
    // Medal colours for podium ranks.
    val medal = when (player.rank) {
      1 -> Color(0xFFFFD24A)
      2 -> Color(0xFFCBD5E1)
      3 -> Color(0xFFE39A5B)
      else -> null
    }

    Row(verticalAlignment = Alignment.CenterVertically) {
      Box(contentAlignment = Alignment.BottomEnd) {
        Box(
          Modifier
            .size(72.dp)
            .clip(CircleShape)
            .background(Color.White.copy(alpha = 0.14f))
            .border(3.dp, medal ?: Color.White.copy(alpha = 0.4f), CircleShape)
            .clickable { showPhoto = true },
          contentAlignment = Alignment.Center,
        ) {
          // Initials show first; the real photo crossfades over them, and remains the
          // fallback if the image can't load.
          Text(initials, color = Color.White, fontSize = 26.sp, fontWeight = FontWeight.ExtraBold)
          AsyncImage(
            model = coil.request.ImageRequest.Builder(LocalContext.current)
              .data(stats.photoUrl).crossfade(true).build(),
            contentDescription = player.name,
            contentScale = ContentScale.Crop,
            modifier = Modifier.matchParentSize().clip(CircleShape),
          )
        }
        // Flag badge overlay — real vector flag, not an emoji (emoji flags render
        // inconsistently across OEM fonts).
        Image(
          painter = painterResource(id = com.example.thanna.R.drawable.ic_flag_india),
          contentDescription = "India",
          contentScale = ContentScale.Crop,
          modifier = Modifier
            .size(24.dp)
            .clip(CircleShape)
            .background(Color(0xFF0F172A))
            .border(1.5.dp, Color.White.copy(alpha = 0.7f), CircleShape),
        )
      }
      Spacer(Modifier.width(16.dp))
      Column(Modifier.weight(1f)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
          Text(player.name, color = Color.White, fontSize = 21.sp, fontWeight = FontWeight.Bold, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f, fill = false))
          Spacer(Modifier.width(6.dp))
          Icon(Icons.Default.Verified, "Verified", tint = Color(0xFF4DA3FF), modifier = Modifier.size(18.dp))
        }
        Spacer(Modifier.height(5.dp))
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
          Box(
            Modifier.clip(RoundedCornerShape(8.dp)).background(Color.White.copy(alpha = 0.16f))
              .padding(horizontal = 9.dp, vertical = 3.dp),
          ) { Text(stats.role.label, color = Color.White, fontSize = 11.sp, fontWeight = FontWeight.SemiBold) }
          Row(verticalAlignment = Alignment.CenterVertically) {
            Image(
              painter = painterResource(id = com.example.thanna.R.drawable.ic_flag_india),
              contentDescription = null,
              modifier = Modifier.size(14.dp).clip(RoundedCornerShape(3.dp)),
            )
            Spacer(Modifier.width(5.dp))
            Text(stats.region, color = Color.White.copy(alpha = 0.85f), fontSize = 12.sp)
          }
        }
      }
      // Rank medallion — a premium metallic medal for podium ranks, translucent otherwise.
      val medalSkin = medalTheme(player.rank)
      if (medalSkin != null) {
        Box(
          Modifier
            .shadow(10.dp, RoundedCornerShape(16.dp), ambientColor = Color(0x33000000), spotColor = medalSkin.grad.last().copy(alpha = 0.7f))
            .clip(RoundedCornerShape(16.dp))
            .background(Brush.linearGradient(medalSkin.grad, start = Offset(0f, 0f), end = Offset(110f, 220f)))
            .border(1.2.dp, medalSkin.rim, RoundedCornerShape(16.dp)),
        ) {
          // Glossy sheen across the top half.
          Box(
            Modifier
              .matchParentSize()
              .background(
                Brush.verticalGradient(
                  colors = listOf(Color.White.copy(alpha = 0.55f), Color.White.copy(alpha = 0.0f)),
                  startY = 0f, endY = 70f,
                )
              )
          )
          Column(
            Modifier.padding(horizontal = 16.dp, vertical = 10.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
          ) {
            Icon(Icons.Default.EmojiEvents, null, tint = medalSkin.ink, modifier = Modifier.size(19.dp))
            Spacer(Modifier.height(2.dp))
            Text("#${player.rank}", color = medalSkin.ink, fontSize = 21.sp, fontWeight = FontWeight.Black, letterSpacing = (-0.8).sp,
              style = TextStyle(fontFeatureSettings = "tnum"))
            Text(stats.rankBoardLabel.uppercase(), color = medalSkin.ink.copy(alpha = 0.72f), fontSize = 7.5.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.6.sp)
            Spacer(Modifier.height(6.dp))
            Row(
              modifier = Modifier
                .clip(RoundedCornerShape(7.dp))
                .background(Color(0xFFFFFFFF).copy(alpha = 0.65f))
                .padding(horizontal = 7.dp, vertical = 2.5.dp),
              verticalAlignment = Alignment.CenterVertically,
            ) {
              TrendTriangle(up = true, color = Color(0xFF1B7A3D), size = 7.dp)
              Spacer(Modifier.width(3.dp))
              Text("${stats.rankTrend} this mo", color = Color(0xFF145C2C), fontSize = 8.sp, fontWeight = FontWeight.Bold)
            }
          }
        }
      } else {
        Column(
          Modifier
            .clip(RoundedCornerShape(14.dp))
            .background(Color.White.copy(alpha = 0.12f))
            .padding(horizontal = 14.dp, vertical = 9.dp),
          horizontalAlignment = Alignment.CenterHorizontally,
        ) {
          Icon(Icons.Default.EmojiEvents, null, tint = Color.White, modifier = Modifier.size(18.dp))
          Spacer(Modifier.height(3.dp))
          Text("#${player.rank}", color = Color.White, fontSize = 19.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.5).sp,
            style = TextStyle(fontFeatureSettings = "tnum"))
          Text(stats.rankBoardLabel, color = Color.White.copy(alpha = 0.7f), fontSize = 8.sp, fontWeight = FontWeight.SemiBold)
          Spacer(Modifier.height(4.dp))
          Row(verticalAlignment = Alignment.CenterVertically) {
            TrendTriangle(up = true, color = Color(0xFF8FE3A8), size = 7.dp)
            Spacer(Modifier.width(2.dp))
            Text("${stats.rankTrend} this mo", color = Color(0xFF8FE3A8), fontSize = 8.sp, fontWeight = FontWeight.Bold)
          }
        }
      }
    }

    Spacer(Modifier.height(14.dp))
    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
      stats.rankChips.forEachIndexed { i, chip ->
        // Lead chip (current board rank) gets the gold treatment.
        val lead = i == 0
        val labelTint = if (lead) Color(0xFFFDE68A).copy(alpha = 0.85f) else Color.White.copy(alpha = 0.55f)
        val valueTint = if (lead) Color(0xFFFDE68A) else Color.White
        Column(
          Modifier
            .clip(RoundedCornerShape(12.dp))
            // Borderless translucent fills read as soft glass; only the lead chip keeps a
            // faint gold hairline so the eye lands there first.
            .background(if (lead) Color(0xFFFACC15).copy(alpha = 0.16f) else Color.White.copy(alpha = 0.07f))
            .then(if (lead) Modifier.border(1.dp, Color(0xFFFACC15).copy(alpha = 0.35f), RoundedCornerShape(12.dp)) else Modifier)
            .then(if (chip.board != null) Modifier.clickable { onOpenBoard(chip.board) } else Modifier)
            .padding(horizontal = 11.dp, vertical = 7.dp),
        ) {
          Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(chip.icon, null, tint = labelTint, modifier = Modifier.size(10.dp))
            Spacer(Modifier.width(4.dp))
            Text(chip.label, color = labelTint, fontSize = 8.sp, fontWeight = FontWeight.SemiBold, letterSpacing = 0.6.sp)
          }
          Spacer(Modifier.height(3.dp))
          Text(chip.value, color = valueTint, fontSize = 13.sp, fontWeight = FontWeight.Bold, letterSpacing = (-0.3).sp,
            style = TextStyle(fontFeatureSettings = "tnum"))
          if (chip.percent != null) {
            Spacer(Modifier.height(4.dp))
            // Percentile bar: fill = how far ahead of the field (100 − percentile).
            Box(
              Modifier.width(54.dp).height(3.dp).clip(RoundedCornerShape(2.dp)).background(Color.White.copy(alpha = 0.18f)),
            ) {
              Box(
                Modifier
                  .fillMaxWidth((100 - chip.percent) / 100f)
                  .fillMaxHeight()
                  .clip(RoundedCornerShape(2.dp))
                  .background(Color(0xFF60E29A))
              )
            }
          }
        }
      }
    }
   }

    if (showPhoto) {
      Dialog(
        onDismissRequest = { showPhoto = false },
        properties = DialogProperties(usePlatformDefaultWidth = false),
      ) {
        Box(
          Modifier
            .fillMaxSize()
            .background(Color(0xF2000000))
            .clickable(
              indication = null,
              interactionSource = remember { androidx.compose.foundation.interaction.MutableInteractionSource() },
            ) { showPhoto = false },
          contentAlignment = Alignment.Center,
        ) {
          Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Box(
              Modifier
                .fillMaxWidth(0.86f)
                .aspectRatio(1f)
                .clip(RoundedCornerShape(24.dp))
                .background(Color(0xFF1B2436)),
              contentAlignment = Alignment.Center,
            ) {
              Text(initials, color = Color.White.copy(alpha = 0.5f), fontSize = 64.sp, fontWeight = FontWeight.ExtraBold)
              AsyncImage(
                model = coil.request.ImageRequest.Builder(LocalContext.current)
                  .data(stats.photoUrl).crossfade(true).build(),
                contentDescription = player.name,
                contentScale = ContentScale.Fit,
                modifier = Modifier.matchParentSize(),
              )
            }
            Spacer(Modifier.height(18.dp))
            Text(player.name, color = Color.White, fontSize = 18.sp, fontWeight = FontWeight.Bold)
            Text("Tap anywhere to close", color = Color.White.copy(alpha = 0.6f), fontSize = 12.sp)
          }
          // Close button
          Box(
            Modifier
              .align(Alignment.TopEnd)
              .statusBarsPadding()
              .padding(16.dp)
              .size(40.dp)
              .clip(CircleShape)
              .background(Color.White.copy(alpha = 0.14f))
              .clickable { showPhoto = false },
            contentAlignment = Alignment.Center,
          ) { Icon(Icons.Default.Close, "Close", tint = Color.White, modifier = Modifier.size(20.dp)) }
        }
      }
    }
  }
}

@Composable
private fun CrexProfileQuickStats(stats: CrexPlayerStats) {
  // Count-up: animate from 0 → target once on first composition.
  var play by remember { mutableStateOf(false) }
  LaunchedEffect(stats) { play = true }
  val progress by animateFloatAsState(
    targetValue = if (play) 1f else 0f,
    animationSpec = tween(durationMillis = 750),
    label = "countup",
  )

  val brand = Color(0xFF2563EB)
  val headlineIcon = if (stats.headlineLabel == "Wickets") Icons.Outlined.SportsCricket else Icons.Outlined.TrendingUp
  val cells = listOf(
    QuickStatCell(Icons.Outlined.SportsCricket, brand, stats.matches.toFloat(), { it.toInt().toString() }, "Matches"),
    QuickStatCell(headlineIcon, brand, stats.headlineValue.toFloat(), { it.toInt().toString() }, stats.headlineLabel),
    QuickStatCell(Icons.Outlined.BarChart, brand, stats.avg.toFloatOrNull() ?: 0f, { String.format("%.1f", it) }, "Average"),
    QuickStatCell(Icons.Outlined.Speed, brand, stats.rateValue.toFloatOrNull() ?: 0f, { String.format("%.1f", it) }, stats.rateLabel),
  )
  val rateIsNumeric = stats.rateValue.toFloatOrNull() != null

  Card(
    shape = RoundedCornerShape(16.dp),
    colors = CardDefaults.cardColors(containerColor = T.Surface),
    elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    modifier = Modifier
      .fillMaxWidth()
      .shadow(6.dp, RoundedCornerShape(16.dp), ambientColor = Color(0x0D000000), spotColor = Color(0x16000000)),
  ) {
    Row(Modifier.fillMaxWidth().padding(vertical = 16.dp), verticalAlignment = Alignment.CenterVertically) {
      cells.forEachIndexed { i, c ->
        Column(
          Modifier.weight(1f),
          horizontalAlignment = Alignment.CenterHorizontally,
        ) {
          Box(
            Modifier.size(30.dp).clip(CircleShape).background(c.tint.copy(alpha = 0.12f)),
            contentAlignment = Alignment.Center,
          ) { Icon(c.icon, null, tint = c.tint, modifier = Modifier.size(16.dp)) }
          Spacer(Modifier.height(7.dp))
          val display = if (i == 3 && !rateIsNumeric) stats.rateValue else c.display(c.target * progress)
          Text(display, color = T.Text1, fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.5).sp,
            style = TextStyle(fontFeatureSettings = "tnum"))
          Spacer(Modifier.height(3.dp))
          // Reserved-height delta row keeps every label on the same baseline.
          Box(Modifier.height(12.dp), contentAlignment = Alignment.Center) {
            stats.quickDeltas.getOrNull(i)?.let { d ->
              val pos = d.startsWith("+")
              val deltaColor = if (pos) Color(0xFF12824A) else Color(0xFFD23F57)
              Row(verticalAlignment = Alignment.CenterVertically) {
                TrendTriangle(up = pos, color = deltaColor, size = 7.dp)
                Spacer(Modifier.width(3.dp))
                Text(
                  d.drop(1),
                  color = deltaColor,
                  fontSize = 9.sp, fontWeight = FontWeight.Bold,
                  style = TextStyle(fontFeatureSettings = "tnum"),
                )
              }
            }
          }
          Spacer(Modifier.height(2.dp))
          Text(c.label, color = T.Text3, fontSize = 10.sp, fontWeight = FontWeight.Medium, letterSpacing = 0.3.sp)
        }
        if (i != cells.lastIndex) {
          Box(Modifier.width(1.dp).height(40.dp).background(T.Divider))
        }
      }
    }
  }
}

private data class QuickStatCell(
  val icon: ImageVector,
  val tint: Color,
  val target: Float,
  val display: (Float) -> String,
  val label: String,
)

@Composable
private fun CrexProfileForm(stats: CrexPlayerStats) {
  Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
    stats.recentForm.forEach { v ->
      val good = (firstNumber(v) >= 30 && stats.role != PlayerRoleKind.BOWLER) ||
        (stats.role == PlayerRoleKind.BOWLER && firstNumber(v) >= 2)
      Box(
        Modifier
          .clip(RoundedCornerShape(10.dp))
          .background(if (good) Color(0xFFE7F7EE) else T.Surface)
          .border(1.dp, if (good) Color(0xFF9FE3BC) else T.Divider, RoundedCornerShape(10.dp))
          .padding(horizontal = 12.dp, vertical = 9.dp),
      ) {
        Text(v, color = if (good) Color(0xFF12824A) else T.Text2, fontSize = 13.sp, fontWeight = FontWeight.Bold)
      }
    }
  }
}

@Composable
private fun CrexAboutSection(rows: List<Pair<String, String>>) {
  Card(
    shape = RoundedCornerShape(14.dp),
    colors = CardDefaults.cardColors(containerColor = T.Surface),
    elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
  ) {
    Column(Modifier.padding(vertical = 4.dp)) {
      rows.forEachIndexed { i, (label, value) ->
        Row(
          Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 13.dp),
          verticalAlignment = Alignment.CenterVertically,
        ) {
          Text(label, color = T.Text3, fontSize = 13.sp, fontWeight = FontWeight.Medium, modifier = Modifier.width(108.dp))
          Text(value, color = Color(0xFF2563EB), fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
        }
        if (i != rows.lastIndex) Box(Modifier.fillMaxWidth().padding(horizontal = 16.dp).height(1.dp).background(T.Divider))
      }
    }
  }
}

@Composable
private fun CrexProfileSectionTitle(title: String, trailing: String? = null) {
  Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
    Text(title, color = T.Text1, fontSize = 16.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
    if (trailing != null) Text(trailing, color = T.Text3, fontSize = 12.sp, fontWeight = FontWeight.Medium)
  }
}

@Composable
private fun CrexCareerTable(rows: List<CrexCareerRow>, batting: Boolean) {
  val headers = if (batting)
    listOf("", "M", "Inn", "Runs", "HS", "Avg", "SR", "50", "100")
  else
    listOf("", "M", "Inn", "Wkt", "BBI", "Avg", "Econ", "4w", "5w")
  // weights: first column wider
  val weights = listOf(1.7f, 0.8f, 0.9f, 1.1f, 1.2f, 1f, 1f, 0.7f, 0.8f)

  Card(
    shape = RoundedCornerShape(14.dp),
    colors = CardDefaults.cardColors(containerColor = T.Surface),
    elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
  ) {
    Column(Modifier.padding(vertical = 4.dp)) {
      // header row
      Row(
        Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 10.dp),
      ) {
        headers.forEachIndexed { i, h ->
          Text(
            h,
            modifier = Modifier.weight(weights[i]),
            color = T.Text3, fontSize = 10.5.sp, fontWeight = FontWeight.Bold,
            textAlign = if (i == 0) TextAlign.Start else TextAlign.Center,
          )
        }
      }
      Box(Modifier.fillMaxWidth().height(1.dp).background(T.Divider))

      val cellsByRow = rows.map { r ->
        listOf(
          r.format, r.matches.toString(), r.innings.toString(), r.runsOrWickets.toString(),
          r.best, r.avg, r.rate, r.milestoneA.toString(), r.milestoneB.toString(),
        )
      }
      // Career-best per column among single seasons (exclude the Overall summary row).
      val seasonIdx = rows.indices.filter { rows[it].format != "Overall" }
      val colBest = (1..8).associateWith { col ->
        seasonIdx.mapNotNull { cellsByRow[it][col].let { v -> Regex("[0-9.]+").find(v)?.value?.toFloatOrNull() } }
          .maxOrNull() ?: -1f
      }

      rows.forEachIndexed { idx, r ->
        val cells = cellsByRow[idx]
        val isOverall = r.format == "Overall"
        Row(
          Modifier
            .fillMaxWidth()
            .background(if (isOverall) Color(0xFFEFF3FF) else Color.Transparent)
            .padding(end = 12.dp, top = 11.dp, bottom = 11.dp),
          verticalAlignment = Alignment.CenterVertically,
        ) {
          // Left accent strip on the Overall row.
          Box(
            Modifier
              .width(3.dp)
              .height(16.dp)
              .clip(RoundedCornerShape(2.dp))
              .background(if (isOverall) Color(0xFF2563EB) else Color.Transparent)
          )
          Spacer(Modifier.width(9.dp))
          cells.forEachIndexed { i, c ->
            val v = Regex("[0-9.]+").find(c)?.value?.toFloatOrNull()
            val isBest = !isOverall && i in 1..8 && v != null && v >= 0f && v == colBest[i] && (colBest[i] ?: 0f) > 0f &&
              seasonIdx.size > 1
            Text(
              c,
              modifier = Modifier.weight(weights[i]),
              color = when {
                i == 0 -> T.Text2
                isBest -> Color(0xFF2563EB)
                isOverall -> T.Text1
                else -> T.Text2
              },
              fontSize = if (i == 0) 11.5.sp else 12.sp,
              fontWeight = if (isOverall || i == 0 || isBest) FontWeight.Bold else FontWeight.Medium,
              textAlign = if (i == 0) TextAlign.Start else TextAlign.Center,
            )
          }
        }
        if (idx != rows.lastIndex) Box(Modifier.fillMaxWidth().height(1.dp).background(T.Divider))
      }
    }
  }
}

@Composable
private fun StatRing(percent: Int, color: Color, modifier: Modifier = Modifier) {
  var play by remember { mutableStateOf(false) }
  LaunchedEffect(percent) { play = true }
  val sweep by animateFloatAsState(
    targetValue = if (play) percent / 100f else 0f,
    animationSpec = tween(850),
    label = "ring",
  )
  Box(modifier, contentAlignment = Alignment.Center) {
    Canvas(Modifier.matchParentSize()) {
      val sw = 4.dp.toPx()
      drawArc(color.copy(alpha = 0.16f), -90f, 360f, false, style = Stroke(width = sw, cap = StrokeCap.Round))
      drawArc(color, -90f, 360f * sweep, false, style = Stroke(width = sw, cap = StrokeCap.Round))
    }
    Text("$percent%", color = color, fontSize = 9.sp, fontWeight = FontWeight.ExtraBold)
  }
}

// Crisp, baseline-stable trend arrow drawn as a triangle — replaces the unicode ▲/▼
// glyphs, which render off-baseline and at inconsistent sizes across fonts.
@Composable
private fun TrendTriangle(up: Boolean, color: Color, size: androidx.compose.ui.unit.Dp) {
  Canvas(Modifier.size(size)) {
    val w = this.size.width
    val h = this.size.height
    val path = androidx.compose.ui.graphics.Path().apply {
      if (up) {
        moveTo(w / 2f, 0f); lineTo(w, h); lineTo(0f, h)
      } else {
        moveTo(0f, 0f); lineTo(w, 0f); lineTo(w / 2f, h)
      }
      close()
    }
    drawPath(path, color)
  }
}

@Composable
private fun CrexHighlights(items: List<ProfileHighlight>) {
  Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
    items.chunked(2).forEach { rowItems ->
      Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
        rowItems.forEach { h ->
          Row(
            modifier = Modifier
              .weight(1f)
              .shadow(5.dp, RoundedCornerShape(14.dp), ambientColor = Color(0x0D000000), spotColor = Color(0x14000000))
              .clip(RoundedCornerShape(14.dp))
              .background(T.Surface)
              .padding(14.dp),
            verticalAlignment = Alignment.CenterVertically,
          ) {
            val isWinRate = h.label == "Win rate"
            if (isWinRate) {
              StatRing(percent = firstNumber(h.value), color = h.tint, modifier = Modifier.size(40.dp))
              Spacer(Modifier.width(10.dp))
              Column {
                Text("Win rate", color = T.Text1, fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.3).sp)
                Text("Across seasons", color = T.Text3, fontSize = 10.5.sp, fontWeight = FontWeight.Medium)
              }
            } else {
              Box(
                Modifier.size(38.dp).clip(RoundedCornerShape(11.dp)).background(h.tint.copy(alpha = 0.12f)),
                contentAlignment = Alignment.Center,
              ) { Icon(h.icon, null, tint = h.tint, modifier = Modifier.size(20.dp)) }
              Spacer(Modifier.width(10.dp))
              Column {
                Text(h.value, color = T.Text1, fontSize = 16.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.4).sp,
                  style = TextStyle(fontFeatureSettings = "tnum"))
                Text(h.label, color = T.Text3, fontSize = 10.5.sp, fontWeight = FontWeight.Medium)
              }
            }
          }
        }
        if (rowItems.size == 1) Spacer(Modifier.weight(1f))
      }
    }
  }
}

@Composable
private fun CrexProfileSparkline(stats: CrexPlayerStats) {
  val values = stats.recentForm.map { firstNumber(it).toFloat() }
  if (values.size < 2) return
  val maxV = values.max().coerceAtLeast(1f)
  val minV = values.min()
  val up = values.last() >= values.first()
  val lineColor = if (up) Color(0xFF12824A) else Color(0xFFE8315A)

  // Draw-in reveal: the curve sweeps left→right once on entry.
  var play by remember { mutableStateOf(false) }
  LaunchedEffect(stats) { play = true }
  val reveal by animateFloatAsState(
    targetValue = if (play) 1f else 0f,
    animationSpec = tween(durationMillis = 720),
    label = "spark",
  )

  Card(
    shape = RoundedCornerShape(14.dp),
    colors = CardDefaults.cardColors(containerColor = T.Surface),
    elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
  ) {
    Row(
      Modifier.fillMaxWidth().padding(16.dp),
      verticalAlignment = Alignment.CenterVertically,
    ) {
      Canvas(
        Modifier
          .weight(1f)
          .height(52.dp)
      ) {
        val n = values.size
        val stepX = if (n > 1) size.width / (n - 1) else size.width
        val range = (maxV - minV).coerceAtLeast(1f)
        val pad = 7f
        fun pt(i: Int): Offset {
          val x = stepX * i
          val norm = (values[i] - minV) / range
          val y = size.height - pad - norm * (size.height - pad * 2)
          return Offset(x, y)
        }

        // Catmull-Rom → cubic bézier for a smooth, organic curve instead of hard kinks.
        val line = androidx.compose.ui.graphics.Path().apply {
          moveTo(pt(0).x, pt(0).y)
          for (i in 0 until n - 1) {
            val p0 = pt((i - 1).coerceAtLeast(0))
            val p1 = pt(i)
            val p2 = pt(i + 1)
            val p3 = pt((i + 2).coerceAtMost(n - 1))
            val c1 = Offset(p1.x + (p2.x - p0.x) / 6f, p1.y + (p2.y - p0.y) / 6f)
            val c2 = Offset(p2.x - (p3.x - p1.x) / 6f, p2.y - (p3.y - p1.y) / 6f)
            cubicTo(c1.x, c1.y, c2.x, c2.y, p2.x, p2.y)
          }
        }
        // Area fill: the curve closed down to the baseline, washed with a fading gradient.
        val area = androidx.compose.ui.graphics.Path().apply {
          addPath(line)
          lineTo(pt(n - 1).x, size.height)
          lineTo(pt(0).x, size.height)
          close()
        }

        clipRect(right = size.width * reveal.coerceIn(0.0001f, 1f)) {
          drawPath(
            area,
            brush = Brush.verticalGradient(
              colors = listOf(lineColor.copy(alpha = 0.28f), lineColor.copy(alpha = 0.0f)),
              startY = 0f, endY = size.height,
            ),
          )
          drawPath(
            line,
            color = lineColor,
            style = Stroke(width = 3.5f, cap = StrokeCap.Round, join = StrokeJoin.Round),
          )
          for (i in 0 until n) {
            val p = pt(i)
            val last = i == n - 1
            drawCircle(if (last) lineColor else lineColor.copy(alpha = 0.5f), radius = if (last) 6f else 3.5f, center = p)
            if (last) drawCircle(Color.White, radius = 2.5f, center = p)
          }
        }
      }
      Spacer(Modifier.width(14.dp))
      Column(horizontalAlignment = Alignment.CenterHorizontally) {
        TrendTriangle(up = up, color = lineColor, size = 11.dp)
        Spacer(Modifier.height(3.dp))
        Text(if (up) "Rising" else "Dipping", color = T.Text3, fontSize = 10.sp, fontWeight = FontWeight.Medium)
      }
    }
  }
}

@Composable
private fun CrexMatchesTab(stats: CrexPlayerStats) {
  Column {
    CrexProfileSectionTitle("Recent matches", "${stats.recentMatches.size} games")
    Spacer(Modifier.height(10.dp))
    stats.recentMatches.forEachIndexed { i, m ->
      Row(
        Modifier
          .fillMaxWidth()
          .clip(RoundedCornerShape(14.dp))
          .background(T.Surface)
          .padding(14.dp),
        verticalAlignment = Alignment.CenterVertically,
      ) {
        Box(
          Modifier.width(4.dp).height(38.dp).clip(RoundedCornerShape(2.dp))
            .background(if (m.won) Color(0xFF12824A) else Color(0xFFE8315A))
        )
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
          Row(verticalAlignment = Alignment.CenterVertically) {
            Text("vs ${m.opponent}", color = T.Text1, fontSize = 14.sp, fontWeight = FontWeight.Bold)
            if (m.mom) {
              Spacer(Modifier.width(6.dp))
              Box(
                Modifier.clip(RoundedCornerShape(6.dp)).background(Color(0xFFFFF3D6)).padding(horizontal = 6.dp, vertical = 1.dp),
              ) { Text("⭐ MoM", color = Color(0xFFB8860B), fontSize = 9.5.sp, fontWeight = FontWeight.Bold) }
            }
          }
          Text(m.meta, color = T.Text3, fontSize = 11.5.sp, fontWeight = FontWeight.Medium)
        }
        Column(horizontalAlignment = Alignment.End) {
          Text(m.playerLine, color = T.Text1, fontSize = 14.sp, fontWeight = FontWeight.ExtraBold)
          Text(if (m.won) "Won" else "Lost", color = if (m.won) Color(0xFF12824A) else Color(0xFFE8315A),
            fontSize = 10.5.sp, fontWeight = FontWeight.Bold)
        }
      }
      if (i != stats.recentMatches.lastIndex) Spacer(Modifier.height(8.dp))
    }
  }
}

@Composable
private fun CrexNewsTab() {
  Column(
    Modifier.fillMaxWidth().padding(vertical = 48.dp),
    horizontalAlignment = Alignment.CenterHorizontally,
  ) {
    Box(
      Modifier.size(64.dp).clip(CircleShape).background(T.Surface),
      contentAlignment = Alignment.Center,
    ) { Text("📰", fontSize = 28.sp) }
    Spacer(Modifier.height(14.dp))
    Text("No news yet", color = T.Text1, fontSize = 15.sp, fontWeight = FontWeight.Bold)
    Spacer(Modifier.height(4.dp))
    Text("Match reports and milestones will appear here.", color = T.Text3, fontSize = 12.5.sp, textAlign = TextAlign.Center)
  }
}

@Composable
private fun ProfileShimmer() {
  val transition = rememberInfiniteTransition(label = "shimmer")
  val x by transition.animateFloat(
    initialValue = -300f,
    targetValue = 1100f,
    animationSpec = infiniteRepeatable(animation = tween(1100), repeatMode = RepeatMode.Restart),
    label = "shimmerX",
  )
  val brush = Brush.linearGradient(
    colors = listOf(Color(0xFFE6E8EE), Color(0xFFF3F4F8), Color(0xFFE6E8EE)),
    start = Offset(x, 0f),
    end = Offset(x + 300f, 0f),
  )

  @Composable
  fun bar(height: androidx.compose.ui.unit.Dp, widthFraction: Float = 1f, radius: Int = 12) {
    Box(
      Modifier
        .fillMaxWidth(widthFraction)
        .height(height)
        .clip(RoundedCornerShape(radius.dp))
        .background(brush)
    )
  }

  Column(Modifier.fillMaxSize().padding(16.dp)) {
    bar(120.dp, radius = 20)        // hero
    Spacer(Modifier.height(16.dp))
    Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
      repeat(4) { Box(Modifier.weight(1f).height(64.dp).clip(RoundedCornerShape(14.dp)).background(brush)) }
    }
    Spacer(Modifier.height(20.dp))
    bar(16.dp, 0.4f, 8)
    Spacer(Modifier.height(12.dp))
    repeat(5) {
      bar(46.dp, radius = 12)
      Spacer(Modifier.height(10.dp))
    }
  }
}

@Composable
private fun CrexLeagueTitle(title: String) {
  Row(
    modifier = Modifier
      .fillMaxWidth()
      .padding(top = 10.dp, bottom = 10.dp),
    verticalAlignment = Alignment.CenterVertically,
  ) {
    Box(
      modifier = Modifier
        .size(8.dp)
        .clip(CircleShape)
        .background(Color(0xFF2563EB))
    )
    Spacer(modifier = Modifier.width(9.dp))
    Text(
      text = title,
      color = Color(0xFF0F172A),
      fontSize = 15.sp,
      fontWeight = FontWeight.ExtraBold,
      letterSpacing = (-0.4).sp,
      modifier = Modifier.weight(1f),
    )
    Text(
      text = "See all",
      color = Color(0xFF2563EB),
      fontSize = 12.sp,
      fontWeight = FontWeight.SemiBold,
    )
  }
}

/** Honest empty state for an ActionBoard tab/sport that has no real data yet. */
@Composable
private fun CrexTabEmpty(message: String) {
  Box(
    modifier = Modifier
      .fillMaxWidth()
      .padding(vertical = 40.dp),
    contentAlignment = Alignment.Center,
  ) {
    Text(message, color = Color(0xFF94A3B8), fontSize = 13.sp)
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
  matchContext: String = "Final",
  venue: String = "Narendra Modi Stadium, Ahmedabad",
  onClick: () -> Unit = {}
) {
  val team1IsWinner = result.startsWith(team1, ignoreCase = true)
  val team2IsWinner = result.startsWith(team2, ignoreCase = true)
  val isDraw = result.equals("Draw", ignoreCase = true)

  Card(
    modifier = Modifier
      .fillMaxWidth()
      .shadow(
        elevation = 3.dp,
        shape = RoundedCornerShape(14.dp),
        ambientColor = Color(0x14000000),
        spotColor   = Color(0x1A000000),
      )
      .clickable { onClick() },
    shape = RoundedCornerShape(14.dp),
    colors = CardDefaults.cardColors(containerColor = Color.White),
    border = BorderStroke(1.dp, Color(0xFFCFDEFF)),
  ) {
    Row {
      // Left accent bar
      Box(
        modifier = Modifier
          .width(4.dp)
          .fillMaxHeight()
          .background(
            Brush.verticalGradient(
              colors = listOf(Color(0xFF2563EB), Color(0xFF60A5FA))
            ),
            RoundedCornerShape(topStart = 14.dp, bottomStart = 14.dp)
          )
      )
      MatchResultContent(
        modifier = Modifier.weight(1f),
        team1 = team1, team1Logo = team1Logo, score1 = score1, overs1 = overs1,
        team2 = team2, team2Logo = team2Logo, score2 = score2, overs2 = overs2,
        result = result, subResult = subResult, matchContext = matchContext, venue = venue,
      )
    } // end outer Row (accent bar + content)
  }
}

// The body of a result card, without its own card/accent bar — so it can render either
// inside a standalone CrexMatchResultCard or as a row inside a grouped MatchGroup.
@Composable
private fun MatchResultContent(
  modifier: Modifier = Modifier,
  team1: String, team1Logo: String, score1: String, overs1: String,
  team2: String, team2Logo: String, score2: String, overs2: String,
  result: String, subResult: String, matchContext: String, venue: String,
) {
  val team1IsWinner = result.startsWith(team1, ignoreCase = true)
  val team2IsWinner = result.startsWith(team2, ignoreCase = true)
  val isDraw = result.equals("Draw", ignoreCase = true)
  Column(modifier = modifier) {
    // Context strip — quiet status label + venue on white.
    Row(
      modifier = Modifier
        .fillMaxWidth()
        .padding(horizontal = 14.dp, vertical = 9.dp),
      verticalAlignment = Alignment.CenterVertically,
      horizontalArrangement = Arrangement.spacedBy(6.dp),
    ) {
      Text(
        text = matchContext.uppercase(),
        color = Color(0xFF2563EB),
        fontSize = 10.sp,
        fontWeight = FontWeight.Bold,
        letterSpacing = 0.6.sp,
      )
      Box(Modifier.size(3.dp).clip(CircleShape).background(Color(0xFFCBD5E1)))
      Text(
        text = venue,
        color = Color(0xFF94A3B8),
        fontSize = 11.sp,
        maxLines = 1,
        overflow = TextOverflow.Ellipsis,
        modifier = Modifier.weight(1f),
      )
    }

    Box(Modifier.fillMaxWidth().padding(horizontal = 14.dp).height(1.dp).background(Color(0xFFF0F2F5)))

    Row(
      modifier = Modifier
        .fillMaxWidth()
        .padding(horizontal = 14.dp, vertical = 14.dp),
      verticalAlignment = Alignment.CenterVertically,
    ) {
      Column(
        modifier = Modifier.weight(1f),
        verticalArrangement = Arrangement.spacedBy(14.dp),
      ) {
        MatchScoreRow(
          team = team1, logoUrl = team1Logo, score = score1, overs = overs1,
          isWinner = team1IsWinner, isDraw = isDraw,
        )
        MatchScoreRow(
          team = team2, logoUrl = team2Logo, score = score2, overs = overs2,
          isWinner = team2IsWinner, isDraw = isDraw,
        )
      }

      Box(
        modifier = Modifier
          .padding(horizontal = 14.dp)
          .width(1.dp)
          .height(60.dp)
          .background(Color(0xFFF0F2F5))
      )

      Column(
        modifier = Modifier.width(90.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
      ) {
        val resultTxt = if (isDraw) Color(0xFF7C3AED) else Color(0xFF15803D)
        Text(
          text = result,
          color = resultTxt,
          fontSize = 13.sp,
          fontWeight = FontWeight.Bold,
          textAlign = TextAlign.Center,
          letterSpacing = (-0.1).sp,
        )
        Spacer(Modifier.height(3.dp))
        Text(
          text = subResult,
          color = Color(0xFF94A3B8),
          fontSize = 11.sp,
          textAlign = TextAlign.Center,
          lineHeight = 15.sp,
        )
      }
    }
  }
}

// The body of a LIVE match row — same shape as MatchResultContent, but with a live
// beacon in the context strip and the chase/status line where the result would be.
// Neither team is dimmed (both are still in it); the batting side reads bolder.
@Composable
private fun MatchLiveContent(
  modifier: Modifier = Modifier,
  team1: String, team1Logo: String, score1: String, overs1: String,
  team2: String, team2Logo: String, score2: String, overs2: String,
  statusLine: String, statusSub: String,
  matchContext: String, venue: String,
  battingTeam: Int,
  // Cricket-only: when set, a not-yet-batting side shows "Yet to bat" instead of 0.
  // Off for other sports where "0" is a real score (football goals, NBA points…).
  cricketInnings: Boolean = false,
  // Stamped district + optional locality (village/town) — the local-identity signal
  // shown with the venue on the meta line.
  district: String = "",
  locality: String = "",
  // True when the viewer created this match — tints the row and shows a "YOU" badge so
  // they can spot their own match in the feed.
  isMine: Boolean = false,
  onClick: (() -> Unit)? = null,
) {
  val team1YetToBat = cricketInnings && battingTeam != 1 && hasNotBatted(score1, overs1)
  val team2YetToBat = cricketInnings && battingTeam != 2 && hasNotBatted(score2, overs2)
  // Tactile press feedback — the whole row dips on touch (spring physics).
  val interaction = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
  val pressed by interaction.collectIsPressedAsState()
  val scale by animateFloatAsState(
    targetValue = if (pressed) 0.98f else 1f,
    animationSpec = spring(dampingRatio = 0.72f, stiffness = 380f),
    label = "matchRowPress"
  )
  Column(
    modifier = modifier
      .then(if (onClick != null) Modifier.graphicsLayer { scaleX = scale; scaleY = scale } else Modifier)
      // Cards created by the viewer stay white like the rest — only the "YOU" badge marks them.
      .then(
        if (onClick != null) Modifier.clickable(interactionSource = interaction, indication = null) { onClick() }
        else Modifier
      )
  ) {
    Row(
      modifier = Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 9.dp),
      verticalAlignment = Alignment.CenterVertically,
      horizontalArrangement = Arrangement.spacedBy(6.dp),
    ) {
      // Live beacon: a red dot inside a soft red halo.
      Box(contentAlignment = Alignment.Center) {
        Box(Modifier.size(10.dp).clip(CircleShape).background(Color(0x33E11D2A)))
        Box(Modifier.size(6.dp).clip(CircleShape).background(Color(0xFFE11D2A)))
      }
      Text("LIVE", color = Color(0xFFE11D2A), fontSize = 10.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 0.8.sp)
      Box(Modifier.size(3.dp).clip(CircleShape).background(Color(0xFFCBD5E1)))
      Text(matchContext.uppercase(), color = Color(0xFF2563EB), fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.6.sp)
      // Location — the local-identity signal: the most specific place we have (village/
      // locality, else a real venue) + the stamped district, with the bare "Custom Match"
      // placeholder dropped. Hidden when we have nothing.
      val locationText = run {
        val place = locality.takeIf { it.isNotBlank() }
          ?: venue.takeIf { it.isNotBlank() && !it.equals("Custom Match", ignoreCase = true) }
        listOfNotNull(place, district.takeIf { it.isNotBlank() }).joinToString(" · ")
      }
      if (locationText.isNotBlank()) {
        Box(Modifier.size(3.dp).clip(CircleShape).background(Color(0xFFCBD5E1)))
        Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.weight(1f)) {
          Icon(
            imageVector = Icons.Outlined.LocationOn,
            contentDescription = null,
            tint = Color(0xFF94A3B8),
            modifier = Modifier.size(12.dp),
          )
          Spacer(Modifier.width(3.dp))
          Text(locationText, color = Color(0xFF94A3B8), fontSize = 11.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
      } else {
        Spacer(Modifier.weight(1f))
      }
      // "YOU" badge — the viewer's own match, pinned to the right of the context strip.
      if (isMine) {
        Spacer(Modifier.width(6.dp))
        Row(
          verticalAlignment = Alignment.CenterVertically,
          modifier = Modifier
            .clip(RoundedCornerShape(6.dp))
            .background(Color(0xFF2563EB))
            .padding(horizontal = 6.dp, vertical = 2.dp),
        ) {
          Icon(
            imageVector = Icons.Outlined.Person,
            contentDescription = null,
            tint = Color.White,
            modifier = Modifier.size(11.dp),
          )
          Spacer(Modifier.width(2.dp))
          Text("YOU", color = Color.White, fontSize = 9.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 0.6.sp)
        }
      }
    }

    Box(Modifier.fillMaxWidth().padding(horizontal = 14.dp).height(1.dp).background(Color(0xFFF0F2F5)))

    Row(
      modifier = Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 14.dp),
      verticalAlignment = Alignment.CenterVertically,
    ) {
      Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        MatchScoreRow(
          team = team1, logoUrl = team1Logo, score = score1, overs = overs1,
          isWinner = battingTeam == 1, isDraw = battingTeam != 1,
          yetToBat = team1YetToBat,
        )
        MatchScoreRow(
          team = team2, logoUrl = team2Logo, score = score2, overs = overs2,
          isWinner = battingTeam == 2, isDraw = battingTeam != 2,
          yetToBat = team2YetToBat,
        )
      }

      Box(modifier = Modifier.padding(horizontal = 14.dp).width(1.dp).height(60.dp).background(Color(0xFFF0F2F5)))

      Column(
        modifier = Modifier.width(90.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
      ) {
        Text(
          text = statusLine,
          color = Color(0xFF15803D),
          fontSize = 13.sp,
          fontWeight = FontWeight.ExtraBold,
          textAlign = TextAlign.Center,
          letterSpacing = (-0.1).sp,
        )
        Spacer(Modifier.height(3.dp))
        Text(
          text = statusSub,
          color = Color(0xFF94A3B8),
          fontSize = 11.sp,
          textAlign = TextAlign.Center,
          lineHeight = 15.sp,
        )
      }
    }
  }
}

// Renders a list of real live-feed rows as one grouped surface. Shared by the
// "Live in your district" and "Featured matches" sections so both look identical.
@Composable
private fun LiveFeedGroup(
  rows: List<com.example.thanna.data.LiveMatchRow>,
  onMatchClick: (String) -> Unit,
) {
  MatchGroup {
    rows.forEachIndexed { i, m ->
      if (i > 0) MatchGroupDivider()
      // Put the batting side on top (first-innings team leads the card). The top row is
      // then always the batting one, so battingTeam = 1 for the laid-out order.
      val battingSecond = m.battingTeam == 2
      // Compact codes (KP, PP) instead of long stored names, matching the hero card.
      val code1 = com.example.thanna.ui.matches.teamShortCode(m.team1)
      val code2 = com.example.thanna.ui.matches.teamShortCode(m.team2)
      MatchLiveContent(
        modifier = Modifier.fillMaxWidth(),
        onClick = { onMatchClick(m.id) },
        team1 = if (battingSecond) code2 else code1,
        team1Logo = if (battingSecond) m.team2Logo.ifBlank { m.team2Emblem } else m.team1Logo.ifBlank { m.team1Emblem },
        score1 = if (battingSecond) m.score2 else m.score1,
        overs1 = if (battingSecond) m.overs2 else m.overs1,
        team2 = if (battingSecond) code1 else code2,
        team2Logo = if (battingSecond) m.team1Logo.ifBlank { m.team1Emblem } else m.team2Logo.ifBlank { m.team2Emblem },
        score2 = if (battingSecond) m.score1 else m.score2,
        overs2 = if (battingSecond) m.overs1 else m.overs2,
        statusLine = if (m.isLive) "LIVE" else m.status.ifBlank { "Scheduled" },
        statusSub = m.competition,
        matchContext = m.competition.ifBlank { "Match" },
        venue = m.venue,
        district = m.district,
        locality = m.locality,
        battingTeam = 1,
        cricketInnings = true,
        isMine = m.isMine,
      )
    }
  }
}

// One grouped surface for a league's matches — rows separated by inset hairlines,
// instead of a stack of separate floating cards.
@Composable
private fun MatchGroup(content: @Composable () -> Unit) {
  Card(
    modifier = Modifier
      .fillMaxWidth()
      // Layered "floating glass" depth — wide soft ambient + tight contact. Live groups
      // sit highest in the GameHub depth hierarchy.
      .premiumCardShadow(radius = 14.dp, ambient = 16.dp, contact = 2.dp),
    shape = RoundedCornerShape(14.dp),
    colors = CardDefaults.cardColors(containerColor = Color.White),
    // Lit bevel — a sub-1px two-tone edge (lighter top, darker bottom) reads as a
    // physical lip catching light, instead of a flat uniform stroke.
    border = BorderStroke(
      1.dp,
      Brush.verticalGradient(listOf(Color(0xFFF3F6FA), Color(0xFFD9DFEA)))
    ),
  ) {
    Column { content() }
  }
}

@Composable
private fun MatchGroupDivider() {
  Box(Modifier.fillMaxWidth().padding(horizontal = 14.dp).height(1.dp).background(Color(0xFFEEF0F4)))
}

// Shimmer skeleton shown while the real live feed loads — a premium stand-in for a
// spinner (Pillar 4). Mirrors a match group's shape so content swaps in seamlessly.
@Composable
private fun GameHubFeedSkeleton() {
  Card(
    modifier = Modifier
      .fillMaxWidth()
      .premiumCardShadow(radius = 14.dp, ambient = 16.dp, contact = 2.dp),
    shape = RoundedCornerShape(14.dp),
    colors = CardDefaults.cardColors(containerColor = Color.White),
    border = BorderStroke(1.dp, Color(0xFFE8EBF0)),
  ) {
    Column(
      modifier = Modifier.padding(14.dp),
      verticalArrangement = Arrangement.spacedBy(14.dp)
    ) {
      repeat(2) {
        Row(verticalAlignment = Alignment.CenterVertically) {
          Box(Modifier.size(30.dp).clip(CircleShape).haraanShimmer())
          Spacer(Modifier.width(10.dp))
          Box(Modifier.weight(1f).height(14.dp).clip(RoundedCornerShape(4.dp)).haraanShimmer())
          Spacer(Modifier.width(10.dp))
          Box(Modifier.width(48.dp).height(14.dp).clip(RoundedCornerShape(4.dp)).haraanShimmer())
        }
      }
    }
  }
}

@Composable
private fun MatchScoreRow(
  team: String,
  logoUrl: String,
  score: String,
  overs: String,
  isWinner: Boolean,
  isDraw: Boolean,
  yetToBat: Boolean = false,
) {
  val dimmed = !isWinner && !isDraw
  Row(
    modifier = Modifier.fillMaxWidth(),
    verticalAlignment = Alignment.CenterVertically,
  ) {
    TeamLogo(
      team = team,
      logoUrl = logoUrl,
      modifier = Modifier
        .size(30.dp)
        .graphicsLayer { alpha = if (dimmed) 0.4f else 1f },
    )
    Spacer(Modifier.width(10.dp))
    Text(
      text = team,
      color = if (dimmed) Color(0xFFB0BAC8) else Color(0xFF0F172A),
      fontSize = 16.sp,
      fontWeight = if (isWinner) FontWeight.ExtraBold else FontWeight.SemiBold,
      modifier = Modifier.weight(1f),
      maxLines = 1,
    )
    if (yetToBat) {
      // The second innings hasn't begun — a bare "0" reads like they batted and made
      // nothing, so show their state instead.
      Text(
        text = "Yet to bat",
        color = Color(0xFFB0BAC8),
        fontSize = 13.sp,
        fontWeight = FontWeight.Medium,
        textAlign = TextAlign.End,
      )
    } else {
      Column(horizontalAlignment = Alignment.End) {
        Text(
          text = score,
          color = if (dimmed) Color(0xFFB0BAC8) else Color(0xFF0F172A),
          fontSize = 18.sp,
          fontWeight = if (isWinner) FontWeight.ExtraBold else FontWeight.Normal,
          letterSpacing = (-0.5).sp,
          style = TextStyle(fontFeatureSettings = "tnum"),
        )
        if (overs.isNotEmpty()) {
          Text(
            text = overs,
            color = Color(0xFFB0BAC8),
            fontSize = 11.sp,
            fontWeight = FontWeight.Normal,
            textAlign = TextAlign.End,
            style = TextStyle(fontFeatureSettings = "tnum"),
          )
        }
      }
    }
  }
}

// A side "hasn't batted" when it holds no runs and no balls faced — used to show
// "Yet to bat" instead of a misleading 0 for the team waiting on the second innings.
private fun hasNotBatted(score: String, overs: String): Boolean {
  val s = score.trim()
  val o = overs.trim()
  val noRuns = s.isEmpty() || s == "0" || s == "0-0" || s == "0/0"
  val noOvers = o.isEmpty() || o == "0" || o.startsWith("0.0")
  return noRuns && noOvers
}

@Composable
private fun TeamLogo(team: String, logoUrl: String, modifier: Modifier = Modifier) {
  // A team icon explicitly chosen at create time — a default emblem key (action1..4) —
  // wins over everything. Uploaded custom logos arrive as a URL and flow through the
  // effectiveUrl/AsyncImage path below.
  val emblemRes = com.example.thanna.ui.matches.create.emblemDrawableFor(logoUrl)
  if (emblemRes != null) {
    // Frame the action photo in a clean white-bordered circle so it reads clearly
    // against the card instead of bleeding into it.
    Box(
      modifier = modifier
        .size(30.dp)
        .clip(CircleShape)
        .background(Color.White)
        .border(1.dp, Color(0xFFE2E8F0), CircleShape),
      contentAlignment = Alignment.Center,
    ) {
      Image(
        painter = painterResource(id = emblemRes),
        contentDescription = team,
        contentScale = ContentScale.Crop,
        modifier = Modifier.fillMaxSize().clip(CircleShape),
      )
    }
    return
  }
  val (bgColor, textColor) = when (team.uppercase()) {
    "GT"  -> Color(0xFF1B3F9E) to Color.White
    "RCB" -> Color(0xFF8B0000) to Color(0xFFFFD700)
    "RR"  -> Color(0xFF862D86) to Color(0xFFFFC0CB)
    "MI"  -> Color(0xFF005DA0) to Color(0xFFFFD700)
    "CSK" -> Color(0xFFF5A623) to Color(0xFF003E7E)
    "SRH" -> Color(0xFFEF5C1B) to Color.White
    "KKR" -> Color(0xFF3B1F6B) to Color(0xFFFFD700)
    "DC"  -> Color(0xFF004C97) to Color(0xFFEF1C21)
    "PBKS"-> Color(0xFFCC0001) to Color(0xFFFDB913)
    "PAK" -> Color(0xFF115740) to Color.White
    "AUS" -> Color(0xFF00843D) to Color(0xFFFFCD00)
    "IND" -> Color(0xFF003580) to Color(0xFFFF9933)
    "ENG" -> Color(0xFF002868) to Color.White
    "NZ"  -> Color(0xFF000000) to Color.White
    "SA"  -> Color(0xFF007A4D) to Color(0xFFFFB612)
    "WI"  -> Color(0xFF7B0041) to Color(0xFFFFC72C)
    "SL"  -> Color(0xFF002D62) to Color(0xFFFF671F)
    "ESP" -> Color(0xFFAA151B) to Color(0xFFF1BF00)
    "DEN" -> Color(0xFFC60C30) to Color.White
    "SGP" -> Color(0xFFEF3340) to Color.White
    "MAS" -> Color(0xFFCC0001) to Color(0xFFFFD700)
    "USA" -> Color(0xFF002868) to Color(0xFFBF0A30)
    "ITA" -> Color(0xFF009246) to Color.White
    "ARS" -> Color(0xFFEF0107) to Color.White
    "CHE" -> Color(0xFF034694) to Color.White
    "MNC" -> Color(0xFF6CABDD) to Color(0xFF1C2C5B)
    "LIV" -> Color(0xFFC8102E) to Color(0xFFF6EB61)
    "LAL" -> Color(0xFF552583) to Color(0xFFFDB927)
    "BOS" -> Color(0xFF007A33) to Color(0xFFBA9653)
    // Unmapped teams get a stable identity colour (same system as player avatars)
    // instead of a uniform grey blob.
    else  -> playerColor(team) to Color.White
  }

  // A bundled club crest (decoded from assets) is the most reliable source — use it
  // first so MI/CSK/KKR/SRH etc. always show a real logo even when no URL is passed.
  // Then a national flag (reliable CDN), then any passed remote URL, then the monogram.
  val context = LocalContext.current
  val assetPath = bundledLogoAsset(team)
  val assetBitmap = remember(assetPath) {
    assetPath?.let { p ->
      try { context.assets.open(p).use { android.graphics.BitmapFactory.decodeStream(it)?.asImageBitmap() } }
      catch (_: Exception) { null }
    }
  }
  val effectiveUrl = if (logoUrl.isNotEmpty()) logoUrl else nationalFlagUrl(team).orEmpty()

  // Track load success so we can fall back to a clean monogram on error/loading
  // (Wikipedia crest URLs frequently fail to hotlink → without this we'd get a blank
  // colored circle, which is the #1 "placeholder" tell).
  var loaded by remember(effectiveUrl) { mutableStateOf(false) }
  val hasImage = assetBitmap != null || (effectiveUrl.isNotEmpty() && loaded)
  // A loaded photo sits on a white roundel (so a light-background logo doesn't blend); the
  // monogram fallback keeps its identity colour. A defined ring crisps the edge either way.
  Box(
    modifier = modifier
      .size(30.dp)
      .clip(CircleShape)
      .background(if (hasImage) Color.White else bgColor)
      .border(1.dp, Color(0x1A0F172A), CircleShape),
    contentAlignment = Alignment.Center,
  ) {
    if (assetBitmap != null) {
      Image(
        bitmap = assetBitmap,
        contentDescription = team,
        modifier = Modifier.fillMaxSize().padding(3.dp),
        contentScale = ContentScale.Fit,
      )
    } else if (effectiveUrl.isNotEmpty()) {
      AsyncImage(
        model = coil.request.ImageRequest.Builder(LocalContext.current)
          .data(effectiveUrl)
          .crossfade(true)
          .setHeader("User-Agent", "Mozilla/5.0")
          .build(),
        contentDescription = team,
        onState = { loaded = it is AsyncImagePainter.State.Success },
        modifier = Modifier.fillMaxSize().padding(3.dp),
        contentScale = ContentScale.Fit,
      )
    }
    if (!hasImage) {
      Text(
        text = if (team.length <= 3) team else team.take(3),
        color = textColor,
        fontSize = 11.sp,
        fontWeight = FontWeight.ExtraBold,
        letterSpacing = (-0.5).sp,
        textAlign = TextAlign.Center,
      )
    }
  }
}

// Bundled club crest asset for an IPL team code (assets/logos/<code>.png), or null.
// Mirrors the match-detail crest so the live-scores list shows the same real logos.
private fun bundledLogoAsset(team: String): String? {
  val m = team.trim().uppercase()
  val code = when {
    m.contains("CSK") || m.contains("CHE") -> "csk"
    m == "MI" || m.contains("MUM") -> "mi"
    m.contains("RCB") || m.contains("BLR") || m.contains("BENG") -> "rcb"
    m.contains("KKR") || m.contains("KOL") -> "kkr"
    m.contains("SRH") || m.contains("HYD") -> "srh"
    m == "DC" || m.contains("DEL") -> "dc"
    m == "GT" || m.contains("GUJ") -> "gt"
    m.contains("LSG") || m.contains("LUCK") -> "lsg"
    m.contains("PBKS") || m.contains("PUN") -> "pbks"
    else -> return null
  }
  return "logos/$code.png"
}

// National-team flag from a reliable CDN (same look as the match-detail crest). Used
// when there's no bundled club logo and no passed URL — e.g. PAK/AUS.
private fun nationalFlagUrl(team: String): String? {
  val m = team.trim().uppercase().removeSuffix("-W").removeSuffix(" W")
  val code = when (m) {
    "IND" -> "in"; "AUS" -> "au"; "ENG" -> "gb-eng"; "NZ" -> "nz"
    "PAK" -> "pk"; "SA", "RSA" -> "za"; "SL" -> "lk"; "BAN" -> "bd"
    "IRE" -> "ie"; "AFG" -> "af"; "ZIM" -> "zw"; "NED" -> "nl"; "SCO" -> "gb-sct"
    else -> return null
  }
  return "https://flagcdn.com/w160/$code.png"
}


@Composable
private fun CrexUpcomingMatchCard(
  team1: String,
  team1Logo: String,
  team2: String,
  team2Logo: String,
  venue: String,
  timeLeft: String,
  onClick: () -> Unit = {}
) {
  // Upcoming sits one depth-tier below live groups — softer, flatter shadow.
  val cardInteraction = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
  val cardPressed by cardInteraction.collectIsPressedAsState()
  val cardScale by animateFloatAsState(
    targetValue = if (cardPressed) 0.98f else 1f,
    animationSpec = spring(dampingRatio = 0.72f, stiffness = 380f),
    label = "upcomingPress"
  )
  Card(
    modifier = Modifier
      .fillMaxWidth()
      .graphicsLayer { scaleX = cardScale; scaleY = cardScale }
      .premiumCardShadow(radius = 14.dp, ambient = 10.dp, contact = 1.dp)
      .clickable(interactionSource = cardInteraction, indication = null) { onClick() },
    shape = RoundedCornerShape(14.dp),
    colors = CardDefaults.cardColors(containerColor = Color.White),
    // Same lit-bevel edge as the live groups for a consistent material.
    border = BorderStroke(
      1.dp,
      Brush.verticalGradient(listOf(Color(0xFFF5F7FA), Color(0xFFE2E7EF)))
    ),
  ) {
    Column {
      // Context strip — quiet status label + venue on white (matches the result cards).
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .padding(horizontal = 14.dp, vertical = 9.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp),
      ) {
        Text(
          text = "UPCOMING",
          color = Color(0xFFEA580C),
          fontSize = 10.sp,
          fontWeight = FontWeight.Bold,
          letterSpacing = 0.6.sp,
        )
        Box(Modifier.size(3.dp).clip(CircleShape).background(Color(0xFFCBD5E1)))
        Text(
          text = venue,
          color = Color(0xFF94A3B8),
          fontSize = 11.sp,
          maxLines = 1,
          overflow = TextOverflow.Ellipsis,
          modifier = Modifier.weight(1f),
        )
      }

      Box(Modifier.fillMaxWidth().padding(horizontal = 14.dp).height(1.dp).background(Color(0xFFF0F2F5)))

      Row(
        modifier = Modifier
          .fillMaxWidth()
          .padding(horizontal = 14.dp, vertical = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
      ) {
        // Teams column
        Column(
          modifier = Modifier.weight(1f),
          verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
          Row(verticalAlignment = Alignment.CenterVertically) {
            TeamLogo(team = team1, logoUrl = team1Logo, modifier = Modifier.size(30.dp))
            Spacer(Modifier.width(10.dp))
            Text(team1, color = Color(0xFF0F172A), fontSize = 16.sp, fontWeight = FontWeight.ExtraBold)
          }
          Row(verticalAlignment = Alignment.CenterVertically) {
            TeamLogo(team = team2, logoUrl = team2Logo, modifier = Modifier.size(30.dp))
            Spacer(Modifier.width(10.dp))
            Text(team2, color = Color(0xFF0F172A), fontSize = 16.sp, fontWeight = FontWeight.ExtraBold)
          }
        }

        // Divider
        Box(
          modifier = Modifier
            .padding(horizontal = 14.dp)
            .width(1.dp)
            .height(60.dp)
            .background(Color(0xFFF0F2F5))
        )

        // Countdown
        Column(
          modifier = Modifier.width(90.dp),
          horizontalAlignment = Alignment.CenterHorizontally,
          verticalArrangement = Arrangement.Center,
        ) {
          Text(
            text = "Starting in",
            color = Color(0xFF94A3B8),
            fontSize = 11.sp,
            fontWeight = FontWeight.Medium,
          )
          Spacer(Modifier.height(6.dp))
          // Gentle breathing pulse — the countdown feels "alive" without moving layout.
          val countdownPulse = rememberInfiniteTransition(label = "countdownPulse")
          val cdScale by countdownPulse.animateFloat(
            initialValue = 1f, targetValue = 1.03f,
            animationSpec = infiniteRepeatable(tween(1100), RepeatMode.Reverse),
            label = "cdScale"
          )
          Box(
            modifier = Modifier
              .graphicsLayer { scaleX = cdScale; scaleY = cdScale }
              .clip(RoundedCornerShape(8.dp))
              .background(Color(0xFFFFF3E9))
              .border(1.dp, Color(0xFFFFE0C8), RoundedCornerShape(8.dp))
              .padding(horizontal = 10.dp, vertical = 6.dp),
            contentAlignment = Alignment.Center,
          ) {
            Text(
              text = timeLeft,
              color = Color(0xFFEA580C),
              fontSize = 13.sp,
              fontWeight = FontWeight.ExtraBold,
              textAlign = TextAlign.Center,
              letterSpacing = (-0.2).sp,
              style = TextStyle(fontFeatureSettings = "tnum"),
            )
          }
        }
      }
    }
  }
}

@Composable
private fun CrexBottomBar(
  selectedSport: String,
  onSportSelected: (String) -> Unit,
  onHomeClick: () -> Unit,
  onOthersClick: () -> Unit = {}
) {
  // (label, filled icon, outlined icon) — outline when idle, filled when active, one
  // coherent family; "Others" gets a proper apps/grid metaphor instead of a play arrow.
  val items = listOf(
    Triple("Home", Icons.Filled.Home, Icons.Outlined.Home),
    Triple("Cricket", Icons.Filled.SportsCricket, Icons.Outlined.SportsCricket),
    Triple("Badminton", Icons.Filled.SportsTennis, Icons.Outlined.SportsTennis),
    Triple("Football", Icons.Filled.SportsFootball, Icons.Outlined.SportsFootball),
    Triple("Others", Icons.Filled.Apps, Icons.Outlined.Apps),
  )

  NavigationBar(
    modifier = Modifier
      .fillMaxWidth()
      .navigationBarsPadding()
      .padding(horizontal = 8.dp, vertical = 8.dp)
      // Softer, lighter float — refined elevation + a single hairline border.
      .shadow(
        elevation = 7.dp,
        shape = RoundedCornerShape(26.dp),
        ambientColor = Color.Black.copy(alpha = 0.04f),
        spotColor = Color.Black.copy(alpha = 0.06f)
      )
      .border(BorderStroke(1.dp, Color(0xFFEDEFF3)), RoundedCornerShape(26.dp)),
    containerColor = Color.White,
    tonalElevation = 0.dp,
    contentColor = Color.White,
  ) {
    items.forEach { (label, filledIcon, outlinedIcon) ->
      val isSelected = label == selectedSport
      NavigationBarItem(
        selected = isSelected,
        onClick = {
          when (label) {
            "Home" -> onHomeClick()
            "Others" -> onOthersClick()
            else -> onSportSelected(label)
          }
        },
        icon = {
          // Springy scale + lift on the active icon — the bit of motion that reads premium.
          val sel by animateFloatAsState(
            targetValue = if (isSelected) 1f else 0f,
            animationSpec = spring(dampingRatio = 0.5f, stiffness = 320f),
            label = "navSel",
          )
          Icon(
            imageVector = if (isSelected) filledIcon else outlinedIcon,
            contentDescription = label,
            modifier = Modifier
              .size(24.dp)
              .graphicsLayer {
                val s = 1f + 0.14f * sel
                scaleX = s
                scaleY = s
                translationY = -4f * sel
              }
          )
        },
        label = {
          Text(
            text = label,
            fontSize = 10.sp,
            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
            maxLines = 1,
            softWrap = false
          )
        },
        alwaysShowLabel = true,
        colors = NavigationBarItemDefaults.colors(
          selectedIconColor = Color(0xFF2563EB),
          unselectedIconColor = Color(0xFF9AA0AC),
          selectedTextColor = Color(0xFF2563EB),
          unselectedTextColor = Color(0xFF9AA0AC),
          indicatorColor = Color(0xFFE8F0FE)
        )
      )
    }
  }
}





private data class LeaderboardRow(val rk: Int, val name: String, val district: String, val runs: Int, val wickets: Int, val avg: String)


private data class TournamentRow(val title: String, val status: String, val desc: String, val date: String, val statusColor: Color)


private data class TalentRow(val name: String, val role: String, val stars: Int, val avatarUrl: String)


private data class StatCardRow(val label: String, val value: String, val accentColor: Color)


// One ad creative — drives the native slot. Swap these fields (or rotate a list of
// them) to serve different campaigns through the same layout. An empty [imageUrl]
// (or a failed load) gracefully falls back to the compact banner.
data class AdCreative(
  val advertiser: String,      // brand name → hero lockup + action-bar title
  val tagline: String,         // short line → action-bar subtitle / compact sub
  val headline: String,        // bold promo line → compact variant
  val ctaLabel: String,
  val clickUrl: String,
  val logoRes: Int,
  val imageUrl: String = "",
  val promptText: String? = null,
)

private val sampleChatGptAd = AdCreative(
  advertiser = "ChatGPT",
  tagline = "Answers & Inspiration",
  headline = "Your AI assistant — free",
  ctaLabel = "Try Now",
  clickUrl = "https://chatgpt.com/",
  logoRes = com.example.thanna.R.drawable.ic_openai_logo,
  imageUrl = "https://images.unsplash.com/photo-1521017432531-fbd92d768814?w=900&q=80",
  promptText = "Help me develop a strategy to drive repeat customers on hot summer afternoons.",
)

// Open the advertiser's click-through URL (default action when the card is tapped).
private fun openAdClickUrl(context: android.content.Context, url: String) {
  try {
    context.startActivity(
      android.content.Intent(android.content.Intent.ACTION_VIEW, android.net.Uri.parse(url))
        .addFlags(android.content.Intent.FLAG_ACTIVITY_NEW_TASK)
    )
  } catch (_: Exception) {
    Toast.makeText(context, "Couldn't open the ad.", Toast.LENGTH_SHORT).show()
  }
}

// Sponsored ad slot — a rich native-ad creative (hero image + brand overlay + dark
// action bar), styled like a premium Google native ad. Polished with depth, a
// legibility scrim, and motion (entrance, press-scale, Ken-Burns, shimmer load).
// Falls back to a compact banner when the hero image is missing or fails to load.
// [onImpression] fires once when the slot first renders; [onClick] overrides the
// default click-through (open URL).
// One ad component, three placements — pick the format that fits the slot.
enum class AdFormat { LARGE, MEDIUM, COMPACT }

@Composable
private fun AdSpaceBanner(
  creative: AdCreative,
  modifier: Modifier = Modifier,
  format: AdFormat = AdFormat.LARGE,
  foldProgress: Float = 0f,
  onImpression: (AdCreative) -> Unit = {},
  onClick: ((AdCreative) -> Unit)? = null,
) {
  val context = LocalContext.current

  // Impression — fire once per creative/format when the slot enters composition.
  LaunchedEffect(creative, format) { onImpression(creative) }
  val handleClick: () -> Unit = { (onClick ?: { c -> openAdClickUrl(context, c.clickUrl) }).invoke(creative) }

  // Dispatch to the right layout. No image (or a failed load below) always degrades
  // to the compact banner so the slot never looks broken.
  var imageFailed by remember(creative.imageUrl) { mutableStateOf(false) }
  if (format == AdFormat.COMPACT || creative.imageUrl.isBlank() || imageFailed) {
    AdCompactBanner(creative = creative, modifier = modifier, onClick = handleClick)
    return
  }
  if (format == AdFormat.MEDIUM) {
    AdMediumBanner(creative = creative, modifier = modifier, onClick = handleClick)
    return
  }

  // Press feedback for the whole card.
  val cardInteraction = remember { MutableInteractionSource() }
  val cardPressed by cardInteraction.collectIsPressedAsState()
  val cardScale by animateFloatAsState(if (cardPressed) 0.985f else 1f, label = "adCardScale")

  // One-time entrance — fade + rise as the slot appears.
  var appeared by remember { mutableStateOf(false) }
  LaunchedEffect(Unit) { appeared = true }
  val enterAlpha by animateFloatAsState(if (appeared) 1f else 0f, tween(420), label = "adEnterAlpha")
  val enterY by animateFloatAsState(if (appeared) 0f else 28f, tween(420), label = "adEnterY")

  // Slow Ken-Burns drift on the hero image — the high-end brand-ad touch.
  val kenBurns = rememberInfiniteTransition(label = "adKenBurns")
  val kbScale by kenBurns.animateFloat(
    initialValue = 1f, targetValue = 1.07f,
    animationSpec = infiniteRepeatable(tween(9000), RepeatMode.Reverse),
    label = "adKbScale"
  )

  // Scroll-linked "fold up": as the card heads toward the top edge it hinges
  // backward along its top, parallaxes slower than the list, shrinks and fades —
  // tucking itself away instead of sliding off flat. Reverses on scroll-down.
  val fold = foldProgress.coerceIn(0f, 1f)
  val foldEased = fold * fold * (3f - 2f * fold) // smoothstep

  Column(
    modifier = modifier
      .fillMaxWidth()
      .graphicsLayer {
        // Hinge at the top edge.
        transformOrigin = TransformOrigin(0.5f, 0f)
        cameraDistance = 14f * density
        rotationX = -24f * foldEased
        // Parallax: drift up slower than the list so it appears to tuck under.
        translationY = enterY - (size.height * 0.40f * foldEased)
        val foldScale = 1f - 0.10f * foldEased
        scaleX = cardScale * foldScale
        scaleY = cardScale * foldScale
        // Fade is finished by ~85% of the fold so the tail isn't a ghost.
        alpha = enterAlpha * (1f - (fold / 0.85f).coerceIn(0f, 1f))
      }
      .premiumCardShadow(radius = 18.dp)
      .clip(RoundedCornerShape(18.dp))
      .background(Color(0xFF0D0D0F))
      .border(1.dp, Color(0x14000000), RoundedCornerShape(18.dp))
      .clickable(interactionSource = cardInteraction, indication = null) { handleClick() }
  ) {
    // ── Hero image with overlays ──────────────────────────────────────────────
    Box(
      modifier = Modifier
        .fillMaxWidth()
        .height(140.dp)
    ) {
      var imgLoaded by remember { mutableStateOf(false) }
      AsyncImage(
        model = coil.request.ImageRequest.Builder(context)
          .data(creative.imageUrl)
          .crossfade(true)
          .setHeader("User-Agent", "Mozilla/5.0")
          .build(),
        contentDescription = creative.advertiser,
        contentScale = ContentScale.Crop,
        onState = { st ->
          imgLoaded = st is AsyncImagePainter.State.Success
          if (st is AsyncImagePainter.State.Error) imageFailed = true
        },
        modifier = Modifier
          .fillMaxSize()
          .graphicsLayer { scaleX = kbScale; scaleY = kbScale }
      )
      // Shimmer placeholder until the hero loads — never pops in blank.
      if (!imgLoaded) {
        Box(modifier = Modifier.fillMaxSize().haraanShimmer())
      }

      // Targeted scrims: a top band behind the lockup + a bottom fade — keeps the
      // white overlays legible over any photo without greying out the whole image.
      Box(
        modifier = Modifier
          .fillMaxWidth()
          .height(66.dp)
          .align(Alignment.TopCenter)
          .background(Brush.verticalGradient(listOf(Color.Black.copy(alpha = 0.45f), Color.Transparent)))
      )
      Box(
        modifier = Modifier
          .fillMaxWidth()
          .height(70.dp)
          .align(Alignment.BottomCenter)
          .background(Brush.verticalGradient(listOf(Color.Transparent, Color.Black.copy(alpha = 0.35f))))
      )

      // Top-centre ChatGPT lockup.
      Row(
        modifier = Modifier
          .align(Alignment.TopCenter)
          .padding(top = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp)
      ) {
        Icon(
          painter = painterResource(id = creative.logoRes),
          contentDescription = null,
          tint = Color.White,
          modifier = Modifier.size(22.dp)
        )
        Text(creative.advertiser, color = Color.White, fontSize = 16.sp, fontWeight = FontWeight.Bold)
      }

      // Centre prompt bubble — "+" circle, the prompt (max 2 lines), and a send button.
      if (creative.promptText != null) {
      Row(
        modifier = Modifier
          .align(Alignment.Center)
          .fillMaxWidth()
          .padding(horizontal = 16.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp)
      ) {
        Box(
          modifier = Modifier
            .size(34.dp)
            .clip(CircleShape)
            .background(Color.White),
          contentAlignment = Alignment.Center
        ) {
          Icon(Icons.Default.Add, null, tint = Color(0xFF1A1A1A), modifier = Modifier.size(18.dp))
        }
        Row(
          modifier = Modifier
            .weight(1f)
            .clip(RoundedCornerShape(22.dp))
            .background(Color.White)
            .padding(start = 14.dp, end = 6.dp, top = 6.dp, bottom = 6.dp),
          verticalAlignment = Alignment.CenterVertically,
          horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
          Text(
            text = creative.promptText,
            color = Color(0xFF1A1A1A),
            fontSize = 12.sp,
            fontWeight = FontWeight.Medium,
            lineHeight = 15.sp,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f)
          )
          Box(
            modifier = Modifier
              .size(30.dp)
              .clip(CircleShape)
              .background(Color(0xFF111111)),
            contentAlignment = Alignment.Center
          ) {
            Icon(Icons.Default.ArrowUpward, null, tint = Color.White, modifier = Modifier.size(16.dp))
          }
        }
      }
      }
    }

    // ── Dark action bar ───────────────────────────────────────────────────────
    Row(
      modifier = Modifier
        .fillMaxWidth()
        .padding(horizontal = 14.dp, vertical = 10.dp),
      verticalAlignment = Alignment.CenterVertically
    ) {
      // App icon tile.
      Box(
        modifier = Modifier
          .size(42.dp)
          .clip(RoundedCornerShape(12.dp))
          .background(Color.White),
        contentAlignment = Alignment.Center
      ) {
        Icon(
          painter = painterResource(id = creative.logoRes),
          contentDescription = null,
          tint = Color(0xFF0D0D0F),
          modifier = Modifier.size(32.dp)
        )
      }
      Spacer(modifier = Modifier.width(12.dp))
      Column(modifier = Modifier.weight(1f)) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
          Box(
            modifier = Modifier
              .clip(RoundedCornerShape(4.dp))
              .background(Color(0xFFE8C463))
              .padding(horizontal = 5.dp, vertical = 1.dp)
          ) {
            Text("Ad", color = Color(0xFF5C4A12), fontSize = 11.sp, fontWeight = FontWeight.ExtraBold)
          }
          Text(
            creative.advertiser,
            color = Color.White,
            fontSize = 16.sp,
            fontWeight = FontWeight.Bold,
            maxLines = 1,
            softWrap = false,
            overflow = TextOverflow.Ellipsis
          )
        }
        Spacer(modifier = Modifier.height(1.dp))
        Text(
          creative.tagline,
          color = Color(0xFFA8AEB4),
          fontSize = 12.sp,
          maxLines = 1,
          overflow = TextOverflow.Ellipsis
        )
      }
      Spacer(modifier = Modifier.width(10.dp))
      // CTA button — with its own press-scale feedback.
      val ctaInteraction = remember { MutableInteractionSource() }
      val ctaPressed by ctaInteraction.collectIsPressedAsState()
      val ctaScale by animateFloatAsState(if (ctaPressed) 0.94f else 1f, label = "adCtaScale")
      Box(
        modifier = Modifier
          .graphicsLayer { scaleX = ctaScale; scaleY = ctaScale }
          .clip(RoundedCornerShape(12.dp))
          .background(Color(0xFF2C2C2E))
          .clickable(interactionSource = ctaInteraction, indication = null) { handleClick() }
          .padding(horizontal = 14.dp, vertical = 11.dp)
      ) {
        Text(creative.ctaLabel, color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold, maxLines = 1, softWrap = false)
      }
    }
  }
}

// Compact fallback variant — used when a hero image is absent or fails to load.
// Same data, smaller footprint: logo tile, headline + tagline, and a CTA pill.
@Composable
private fun AdCompactBanner(
  creative: AdCreative,
  modifier: Modifier = Modifier,
  onClick: () -> Unit,
) {
  val interaction = remember { MutableInteractionSource() }
  val pressed by interaction.collectIsPressedAsState()
  val scale by animateFloatAsState(if (pressed) 0.985f else 1f, label = "adCompactScale")
  Row(
    modifier = modifier
      .fillMaxWidth()
      .graphicsLayer { scaleX = scale; scaleY = scale }
      .premiumCardShadow(radius = 18.dp)
      .clip(RoundedCornerShape(18.dp))
      .background(
        Brush.linearGradient(listOf(Color(0xFF0B0F0E), Color(0xFF0E5C49), Color(0xFF10A37F)))
      )
      .clickable(interactionSource = interaction, indication = null) { onClick() }
      .padding(14.dp),
    verticalAlignment = Alignment.CenterVertically
  ) {
    Box(
      modifier = Modifier.size(46.dp).clip(RoundedCornerShape(12.dp)).background(Color.White),
      contentAlignment = Alignment.Center
    ) {
      Icon(
        painter = painterResource(id = creative.logoRes),
        contentDescription = null,
        tint = Color(0xFF10A37F),
        modifier = Modifier.size(30.dp)
      )
    }
    Spacer(modifier = Modifier.width(12.dp))
    Column(modifier = Modifier.weight(1f)) {
      Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
        Box(
          modifier = Modifier
            .clip(RoundedCornerShape(4.dp))
            .background(Color(0xFFE8C463))
            .padding(horizontal = 5.dp, vertical = 1.dp)
        ) {
          Text("Ad", color = Color(0xFF5C4A12), fontSize = 10.sp, fontWeight = FontWeight.ExtraBold)
        }
        Text(creative.advertiser, color = Color.White.copy(alpha = 0.75f), fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
      }
      Spacer(modifier = Modifier.height(2.dp))
      Text(creative.headline, color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
      Text(creative.tagline, color = Color.White.copy(alpha = 0.75f), fontSize = 11.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
    Spacer(modifier = Modifier.width(10.dp))
    Box(
      modifier = Modifier.clip(RoundedCornerShape(50)).background(Color.White).padding(horizontal = 16.dp, vertical = 8.dp)
    ) {
      Text(creative.ctaLabel, color = Color(0xFF0E7C62), fontSize = 13.sp, fontWeight = FontWeight.ExtraBold)
    }
  }
}

// Medium variant — image-left, text-right. The middle ground between the full hero
// and the compact banner. Degrades to the compact banner if its image fails.
@Composable
private fun AdMediumBanner(
  creative: AdCreative,
  modifier: Modifier = Modifier,
  onClick: () -> Unit,
) {
  val context = LocalContext.current
  val interaction = remember { MutableInteractionSource() }
  val pressed by interaction.collectIsPressedAsState()
  val scale by animateFloatAsState(if (pressed) 0.985f else 1f, label = "adMediumScale")
  var imgFailed by remember(creative.imageUrl) { mutableStateOf(false) }
  if (creative.imageUrl.isBlank() || imgFailed) {
    AdCompactBanner(creative = creative, modifier = modifier, onClick = onClick)
    return
  }
  Row(
    modifier = modifier
      .fillMaxWidth()
      .height(104.dp)
      .graphicsLayer { scaleX = scale; scaleY = scale }
      .premiumCardShadow(radius = 18.dp)
      .clip(RoundedCornerShape(18.dp))
      .background(Color(0xFF0D0D0F))
      .border(1.dp, Color(0x14000000), RoundedCornerShape(18.dp))
      .clickable(interactionSource = interaction, indication = null) { onClick() },
    verticalAlignment = Alignment.CenterVertically
  ) {
    // Image (left).
    Box(modifier = Modifier.width(112.dp).fillMaxHeight()) {
      var loaded by remember { mutableStateOf(false) }
      AsyncImage(
        model = coil.request.ImageRequest.Builder(context)
          .data(creative.imageUrl)
          .crossfade(true)
          .setHeader("User-Agent", "Mozilla/5.0")
          .build(),
        contentDescription = creative.advertiser,
        contentScale = ContentScale.Crop,
        onState = { st ->
          loaded = st is AsyncImagePainter.State.Success
          if (st is AsyncImagePainter.State.Error) imgFailed = true
        },
        modifier = Modifier.fillMaxSize()
      )
      if (!loaded) Box(modifier = Modifier.fillMaxSize().haraanShimmer())
      // "Ad" badge over the image.
      Box(
        modifier = Modifier
          .align(Alignment.TopStart)
          .padding(6.dp)
          .clip(RoundedCornerShape(4.dp))
          .background(Color(0xFFE8C463))
          .padding(horizontal = 5.dp, vertical = 1.dp)
      ) {
        Text("Ad", color = Color(0xFF5C4A12), fontSize = 10.sp, fontWeight = FontWeight.ExtraBold)
      }
    }
    // Text (right).
    Column(
      modifier = Modifier
        .weight(1f)
        .padding(horizontal = 12.dp),
    ) {
      Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
        Icon(
          painter = painterResource(id = creative.logoRes),
          contentDescription = null,
          tint = Color.White,
          modifier = Modifier.size(16.dp)
        )
        Text(creative.advertiser, color = Color.White.copy(alpha = 0.75f), fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
      }
      Spacer(modifier = Modifier.height(3.dp))
      Text(creative.headline, color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
      Text(creative.tagline, color = Color(0xFFA8AEB4), fontSize = 11.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
    // CTA pill.
    Box(
      modifier = Modifier
        .padding(end = 12.dp)
        .clip(RoundedCornerShape(10.dp))
        .background(Color(0xFF2C2C2E))
        .padding(horizontal = 16.dp, vertical = 10.dp)
    ) {
      Text(creative.ctaLabel, color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
    }
  }
}

@Composable
private fun InfiniteLoopBookPager(
  events: List<EventItem>,
  onEventClick: (EventItem) -> Unit
) {
  if (events.isEmpty()) return

  // Fake infinite loop setup
  val virtualPageCount = Int.MAX_VALUE
  val initialPage = virtualPageCount / 2 - (virtualPageCount / 2 % events.size)
  val pagerState = rememberPagerState(
    initialPage = initialPage,
    pageCount = { virtualPageCount }
  )

  // Auto-scroll loop
  LaunchedEffect(pagerState) {
    while (true) {
      kotlinx.coroutines.delay(3000)
      if (!pagerState.isScrollInProgress) {
        val nextPage = pagerState.currentPage + 1
        pagerState.animateScrollToPage(nextPage)
      }
    }
  }

  HorizontalPager(
    state = pagerState,
    modifier = Modifier
      .fillMaxWidth()
      .wrapContentHeight(),
    contentPadding = PaddingValues(start = 32.dp, end = 48.dp),
    pageSpacing = 0.dp
  ) { page ->
    val actualIndex = page % events.size
    val event = events[actualIndex]

    // Calculate exact relative scroll position (-1.0 to 1.0)
    val pageOffset = (
      (pagerState.currentPage - page) + pagerState.currentPageOffsetFraction
    )

    Box(
      modifier = Modifier
        .fillMaxWidth()
        .graphicsLayer {
          if (pageOffset < 0) {
            // Target: Upcoming cards on the right edge.
            // Pull them back to the left dynamically to build a tightly packed stack.
            translationX = pageOffset * size.width * 0.85f
            
            // Scale down sequentially based on distance from focal point
            val scale = 0.88f + (1.0f - 0.88f) * (1f - kotlin.math.abs(pageOffset)).coerceIn(0f, 1f)
            scaleX = scale
            scaleY = scale
          } else {
            // Target: Cards swiped away towards the left side.
            // Let them slide off natively without dragging them back.
            translationX = 0f
            scaleX = 1f
            scaleY = 1f
          }
        }
        // Delivers clean overlapping rendering logic (Front card stays on top)
        .zIndex(if (kotlin.math.abs(pageOffset) < 0.5f) 2f else 1f)
        .padding(top = 4.dp, bottom = 8.dp, start = 6.dp, end = 6.dp)
    ) {
      HaraanEventCard(
        title = event.title,
        date = event.date,
        venue = event.venue,
        price = event.price,
        category = event.category,
        imageUrl = event.imageUrl,
        rating = event.rating,
        onClick = { onEventClick(event) },
        modifier = Modifier.fillMaxWidth()
      )
    }
  }
}

@Composable
private fun ContinueExploringCard(
  title: String,
  subtitle: String,
  icon: androidx.compose.ui.graphics.vector.ImageVector,
  backgroundColor: Color,
  iconColor: Color,
  onClick: () -> Unit
) {
  Card(
    modifier = Modifier
      .width(180.dp)
      .height(72.dp)
      .clickable { onClick() },
    shape = RoundedCornerShape(HaraanRadius.Medium),
    colors = CardDefaults.cardColors(containerColor = HaraanColors.Surface),
    border = BorderStroke(1.dp, HaraanColors.BorderLight),
    elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
  ) {
    Row(
      modifier = Modifier
        .fillMaxSize()
        .padding(HaraanSpacing.Small),
      verticalAlignment = Alignment.CenterVertically,
      horizontalArrangement = Arrangement.spacedBy(10.dp)
    ) {
      Box(
        modifier = Modifier
          .size(40.dp)
          .background(backgroundColor, RoundedCornerShape(HaraanRadius.Small)),
        contentAlignment = Alignment.Center
      ) {
        Icon(
          imageVector = icon,
          contentDescription = title,
          tint = iconColor,
          modifier = Modifier.size(20.dp)
        )
      }
      Column {
        Text(
          text = title,
          style = HaraanTypography.TitleMedium.copy(fontSize = 13.sp, fontWeight = FontWeight.Bold),
          color = HaraanColors.TextPrimary
        )
        Text(
          text = subtitle,
          style = HaraanTypography.BodyMedium.copy(fontSize = 11.sp),
          color = HaraanColors.TextSecondary,
          maxLines = 1,
          overflow = TextOverflow.Ellipsis
        )
      }
    }
  }
}


@Composable
private fun LeaderboardHomeWidget(district: String?) {
  // Real top-ranked player in the user's district (rank #1 from the public board). No more
  // hardcoded "Hariharan • +248 XP" placeholder — falls back to an honest empty state.
  val top by androidx.compose.runtime.produceState<com.example.thanna.data.LeaderboardRow?>(
    initialValue = null, key1 = district
  ) {
    value = if (district.isNullOrBlank()) null
    else runCatching {
      com.example.thanna.data.LeaderboardRepository().fetchBoard("district", district, limit = 1)
    }.getOrNull()?.firstOrNull()
  }

  val place = district?.takeIf { it.isNotBlank() }

  Card(
    modifier = Modifier
      .fillMaxWidth()
      .padding(horizontal = 16.dp)
      .premiumCardShadow(radius = UnifiedCornerRadius), // 20dp — same as venue content cards
    shape = RoundedCornerShape(UnifiedCornerRadius),
    colors = CardDefaults.cardColors(containerColor = HaraanColors.Surface),
    elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
  ) {
    Row(
      modifier = Modifier
        .fillMaxWidth()
        .padding(HaraanSpacing.Medium),
      verticalAlignment = Alignment.CenterVertically,
      horizontalArrangement = Arrangement.SpaceBetween
    ) {
      Row(
        modifier = Modifier.weight(1f),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp)
      ) {
        // The #1 player's avatar when we have one, else the award icon (line-icon, gold).
        Box(
          modifier = Modifier
            .size(48.dp)
            .clip(CircleShape)
            .background(Color(0xFFFEF3C7)),
          contentAlignment = Alignment.Center
        ) {
          val avatar = top?.avatar
          if (!avatar.isNullOrBlank()) {
            AsyncImage(
              model = avatar,
              contentDescription = null,
              contentScale = ContentScale.Crop,
              modifier = Modifier.fillMaxSize().clip(CircleShape)
            )
          } else {
            Icon(
              imageVector = Icons.Outlined.WorkspacePremium,
              contentDescription = null,
              tint = Color(0xFFD4A017),
              modifier = Modifier.size(26.dp)
            )
          }
        }
        Column(modifier = Modifier.weight(1f)) {
          Text(
            text = "Top Player",
            style = HaraanTypography.TitleMedium.copy(fontSize = 14.sp, fontWeight = FontWeight.Bold),
            color = HaraanColors.TextPrimary
          )
          Spacer(modifier = Modifier.height(2.dp))
          if (top != null) {
            Text(
              text = "${top!!.name}  •  +${top!!.xp} XP",
              style = HaraanTypography.BodyMedium,
              color = HaraanColors.TextSecondary,
              maxLines = 1,
              overflow = TextOverflow.Ellipsis
            )
            Text(
              text = "#${top!!.rank}${place?.let { " in $it" } ?: ""}",
              style = HaraanTypography.LabelSmall.copy(color = HaraanColors.GameHubDeep, fontSize = 10.sp)
            )
          } else {
            Text(
              text = if (place != null) "No ranked players in $place yet" else "Play a ranked match to appear here",
              style = HaraanTypography.BodyMedium,
              color = HaraanColors.TextSecondary,
              maxLines = 2,
              overflow = TextOverflow.Ellipsis
            )
          }
        }
      }

      Spacer(modifier = Modifier.width(12.dp))

      Surface(
        color = HaraanColors.GameHubDeep.copy(alpha = 0.08f),
        shape = RoundedCornerShape(HaraanRadius.Small)
      ) {
        Text(
          text = "View Standings",
          color = HaraanColors.GameHubDeep,
          style = HaraanTypography.LabelSmall.copy(fontSize = 11.sp),
          maxLines = 1,
          softWrap = false,
          modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
        )
      }
    }
  }
}

@Composable
private fun TrendingRowSection(
  events: List<EventItem>,
  onEventClick: (EventItem) -> Unit
) {
  LazyRow(
    contentPadding = PaddingValues(start = 36.dp, end = 24.dp),
    horizontalArrangement = Arrangement.spacedBy(32.dp),
    modifier = Modifier.fillMaxWidth()
  ) {
    itemsIndexed(events) { index, event ->
      Box(
        modifier = Modifier
          .width(140.dp)
          .height(160.dp)
          .clickable { onEventClick(event) },
        contentAlignment = Alignment.BottomStart
      ) {
        // --- THE POSTER CARD ---
        Card(
          modifier = Modifier
            .fillMaxWidth()
            .fillMaxHeight(),
          shape = RoundedCornerShape(24.dp),
          elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
          colors = CardDefaults.cardColors(containerColor = Color.Black)
        ) {
          Box(modifier = Modifier.fillMaxSize()) {
            AsyncImage(
              model = event.imageUrl,
              contentDescription = event.title,
              modifier = Modifier.fillMaxSize(),
              contentScale = ContentScale.Crop
            )

            // Scrim overlay for subtitle readability
            Box(
              modifier = Modifier
                .fillMaxSize()
                .background(
                  Brush.verticalGradient(
                    colors = listOf(Color.Transparent, Color.Black.copy(alpha = 0.85f)),
                    startY = 80f
                  )
                )
            )

            Text(
              text = event.title,
              color = Color.White,
              fontSize = 12.sp,
              fontWeight = FontWeight.Bold,
              lineHeight = 15.sp,
              maxLines = 2,
              modifier = Modifier
                .align(Alignment.BottomStart)
                .padding(start = 24.dp, end = 8.dp, bottom = 12.dp) // Keeps text safe from number overlap
            )
          }
        }

        // --- THE OUTLINED RANK NUMBER (drop shadow + dark stroke + blue gradient fill) ---
        val rankText = (index + 1).toString()
        val rankFontSize = 96.sp
        val rankOutline = Color(0xB3203A5C) // Soft semi-transparent navy — light, premium edge
        Box(
          modifier = Modifier
            .align(Alignment.BottomStart)
            .offset(x = (-24).dp, y = 14.dp) // Perfect center-gap bleed alignment
            .zIndex(5f) // Keeps it layered firmly over adjacent layout containers
        ) {
          // 1) Shadow caster — this glyph is fully covered by the layers above;
          //    only its soft blurred shadow shows, lifting the number off the page.
          Text(
            text = rankText,
            style = androidx.compose.ui.text.TextStyle(
              fontFamily = Poppins,
              fontSize = rankFontSize,
              fontWeight = FontWeight.Black,
              color = rankOutline,
              shadow = Shadow(
                color = Color(0x33000000),
                offset = Offset(0f, 6f),
                blurRadius = 22f
              )
            )
          )
          // 2) Dark outline — the crisp "blackboard line" around the number.
          Text(
            text = rankText,
            style = androidx.compose.ui.text.TextStyle(
              fontFamily = Poppins,
              fontSize = rankFontSize,
              fontWeight = FontWeight.Black,
              color = rankOutline,
              drawStyle = Stroke(
                width = 6f,
                join = StrokeJoin.Round,
                cap = StrokeCap.Round
              )
            )
          )
          // 3) Vivid blue → white gradient fill on top (stays colorful, no wash-out).
          Text(
            text = rankText,
            style = androidx.compose.ui.text.TextStyle(
              fontFamily = Poppins,
              fontSize = rankFontSize,
              fontWeight = FontWeight.Black,
              brush = Brush.verticalGradient(
                colors = listOf(
                  Color(0xFF1D4ED8), // Deep onwards blue at the top
                  Color(0xFF3B82F6), // Bright mid blue
                  Color(0xFFFFFFFF)  // Crisp white at the baseline
                )
              )
            )
          )
        }
      }
    }
  }
}

@Composable
fun PremiumSegmentedSwitch(
    selectedTab: String,
    onTabSelected: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    val tabs = listOf("Events" to "Events", "GameHub" to "GameHub")
    
    // --- COLOR PALETTE ALIGNED WITH YOUR SCREENSHOT ---
    val containerBg = Color(0xFFF1F5F9)       // Clean, soft capsule background
    val strokeColor = Color(0xFFE2E8F0)       // Thin outer bounding border
    val activeText = Color.White               // Crisp white text for readability on the blue gradient
    val inactiveText = Color(0xFF64748B)       // Muted slate gray for unselected states

    // The beautiful onwards blue gradient
    val activeGradient = Brush.verticalGradient(
        colors = listOf(
            Color(0xFF3B82F6), // Slightly lighter onwards blue
            LightAccentBlue    // Onwards blue color (Color(0xFF2563EB))
        )
    )

    BoxWithConstraints(
        modifier = modifier
            .fillMaxWidth()
            .padding(start = 24.dp, end = 24.dp, top = 8.dp, bottom = 2.dp)
            .height(50.dp) // Perfect ergonomic height matching your app header proportions
            .background(containerBg, RoundedCornerShape(25.dp))
            .border(1.dp, strokeColor, RoundedCornerShape(25.dp))
            .padding(3.dp) // Creates a crisp, precise inset margin for the active pill
    ) {
        val containerWidth = maxWidth
        val tabWidth = containerWidth / tabs.size
        val selectedIndex = tabs.indexOfFirst { it.first == selectedTab }.coerceAtLeast(0)

        // Fluid spring physics animation makes the pill slide organically
        val indicatorOffset by animateDpAsState(
            targetValue = tabWidth * selectedIndex,
            animationSpec = spring(
                dampingRatio = 0.82f, // Adds a premium, high-end microscopic bounce
                stiffness = 400f      // Snappy response time
            ),
            label = "PillSliderOffset"
        )

        Box(modifier = Modifier.fillMaxSize()) {
            
            // --- ANIMATED SLIDING ACCENT PILL ---
            Box(
                modifier = Modifier
                    .offset(x = indicatorOffset)
                    .width(tabWidth)
                    .fillMaxHeight()
                    .clip(RoundedCornerShape(22.dp))
                    .background(activeGradient)
                    // Soft drop shadow makes the active choice pop forward visually
                    .shadow(1.dp, RoundedCornerShape(22.dp)) 
            )

            // --- INTERACTIVE LABELS LAYER ---
            Row(modifier = Modifier.fillMaxSize()) {
                tabs.forEach { (key, title) ->
                    val isSelected = selectedTab == key

                    // Seamlessly crossfade the text colors
                    val textColor by animateColorAsState(
                        targetValue = if (isSelected) activeText else inactiveText,
                        animationSpec = tween(durationMillis = 180),
                        label = "LabelColorTransition"
                    )

                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .fillMaxHeight()
                            .clip(RoundedCornerShape(22.dp))
                            // Strips away default platform ripples for a high-end customized feel
                            .clickable(
                                interactionSource = remember { MutableInteractionSource() },
                                indication = null
                            ) { onTabSelected(key) },
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = title,
                            color = textColor,
                            fontSize = 15.sp,
                            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                            letterSpacing = 0.1.sp
                        )
                    }
                }
            }
        }
    }
}

@Composable
fun SportsMiniBadge(emoji: String, label: String, isDarkBackground: Boolean = true) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier.padding(vertical = 4.dp)
    ) {
        Text(text = emoji, fontSize = 24.sp) // Render the emoji directly
        Spacer(modifier = Modifier.height(8.dp))
        Text(
            text = label,
            color = if (isDarkBackground) Color.White.copy(alpha = 0.8f) else Color(0xFF64748B),
            fontSize = 10.sp,
            fontWeight = FontWeight.Medium
        )
    }
}

// ─────────────────────────────────────────────
//  PHASE 2 — GAMEHUB SEARCH OVERLAY
//  Full-screen search: recents + trending while idle, then scoped
//  Grounds / Live Matches / Players results as you type. Venues are passed in;
//  live matches are fetched lazily; players are a curated seed for now.
// ─────────────────────────────────────────────
private data class PlayerHit(val name: String, val role: String, val team: String)

@Composable
private fun GameHubSearchOverlay(
  query: String,
  onQueryChange: (String) -> Unit,
  venues: List<VenueItem>,
  recents: List<String>,
  onRecentsChange: (List<String>) -> Unit,
  onVenueClick: (VenueItem) -> Unit,
  onMatchClick: (String) -> Unit,
  onDismiss: () -> Unit,
  accent: Color,
) {
  val focus = remember { FocusRequester() }
  LaunchedEffect(Unit) { kotlinx.coroutines.delay(150); runCatching { focus.requestFocus() } }

  val matches by produceState(initialValue = emptyList<com.example.thanna.data.LiveMatchRow>()) {
    value = runCatching { com.example.thanna.data.MatchRepository().getLiveMatches() }.getOrDefault(emptyList())
  }

  val trending = listOf("Box cricket", "Turf near me", "Badminton court", "Football", "Cricket nets")
  val players = remember {
    listOf(
      PlayerHit("Virat Kohli", "Batter", "RCB"),
      PlayerHit("Rohit Sharma", "Batter", "MI"),
      PlayerHit("Jasprit Bumrah", "Bowler", "MI"),
      PlayerHit("Hardik Pandya", "All-rounder", "MI"),
      PlayerHit("Ravindra Jadeja", "All-rounder", "CSK"),
      PlayerHit("Suryakumar Yadav", "Batter", "MI"),
      PlayerHit("Mohammed Siraj", "Bowler", "RCB"),
      PlayerHit("Ruturaj Gaikwad", "Batter", "CSK"),
    )
  }

  val q = query.trim()
  val groundHits = if (q.isBlank()) emptyList()
    else venues.filter { it.title.contains(q, true) || it.location.contains(q, true) || it.category.contains(q, true) }.take(6)
  val matchHits = if (q.isBlank()) emptyList()
    else matches.filter { it.team1.contains(q, true) || it.team2.contains(q, true) || it.venue.contains(q, true) || it.competition.contains(q, true) }.take(6)
  val playerHits = if (q.isBlank()) emptyList()
    else players.filter { it.name.contains(q, true) || it.team.contains(q, true) || it.role.contains(q, true) }.take(6)

  val addRecent = { term: String ->
    val t = term.trim()
    if (t.isNotEmpty()) onRecentsChange((listOf(t) + recents.filter { !it.equals(t, true) }).take(8))
  }

  Surface(modifier = Modifier.fillMaxSize(), color = HaraanColors.Background) {
    Column(modifier = Modifier.fillMaxSize().statusBarsPadding()) {
      // ── Search header ──
      Row(
        modifier = Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp)
      ) {
        Icon(
          imageVector = Icons.Default.ArrowBack,
          contentDescription = "Back",
          tint = HaraanColors.TextPrimary,
          modifier = Modifier.size(40.dp).clip(CircleShape).clickable { onDismiss() }.padding(8.dp)
        )
        Row(
          modifier = Modifier.weight(1f).height(48.dp)
            .clip(RoundedCornerShape(HaraanRadius.Large))
            .background(HaraanColors.Surface)
            .border(BorderStroke(1.5.dp, accent), RoundedCornerShape(HaraanRadius.Large))
            .padding(horizontal = 14.dp),
          verticalAlignment = Alignment.CenterVertically
        ) {
          Icon(Icons.Default.Search, null, tint = HaraanColors.TextSecondary, modifier = Modifier.size(20.dp))
          Spacer(Modifier.width(10.dp))
          androidx.compose.foundation.text.BasicTextField(
            value = query,
            onValueChange = onQueryChange,
            modifier = Modifier.weight(1f).focusRequester(focus),
            singleLine = true,
            textStyle = HaraanTypography.BodyLarge.copy(color = HaraanColors.TextPrimary, fontSize = 15.sp),
            cursorBrush = androidx.compose.ui.graphics.SolidColor(accent),
            decorationBox = { inner ->
              Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.CenterStart) {
                if (query.isEmpty()) {
                  Text("Search grounds, matches, players", color = HaraanColors.TextMuted, fontSize = 15.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
                }
                inner()
              }
            }
          )
          if (query.isNotEmpty()) {
            Icon(
              Icons.Default.Close, "Clear",
              tint = HaraanColors.TextSecondary,
              modifier = Modifier.size(30.dp).clip(CircleShape).clickable { onQueryChange("") }.padding(5.dp)
            )
          }
        }
      }

      if (q.isBlank()) {
        // ── Idle: recents + trending ──
        LazyColumn(
          modifier = Modifier.fillMaxSize(),
          contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
          verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
          if (recents.isNotEmpty()) {
            item {
              Row(Modifier.fillMaxWidth().padding(vertical = 4.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Text("Recent searches", color = HaraanColors.TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                Text(
                  "Clear all", color = accent, fontSize = 12.sp, fontWeight = FontWeight.SemiBold,
                  modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { onRecentsChange(emptyList()) }.padding(horizontal = 6.dp, vertical = 2.dp)
                )
              }
            }
            item {
              LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                items(recents) { term ->
                  SearchChip(text = term, accent = accent, removable = true, onClick = { onQueryChange(term) }, onRemove = { onRecentsChange(recents.filter { it != term }) })
                }
              }
            }
          }
          item {
            Text("Trending near you", color = HaraanColors.TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 8.dp, bottom = 2.dp))
          }
          item {
            LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
              items(trending) { term -> SearchChip(text = term, accent = accent, onClick = { onQueryChange(term) }) }
            }
          }
        }
      } else if (groundHits.isEmpty() && matchHits.isEmpty() && playerHits.isEmpty()) {
        // ── Empty state ──
        Column(Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
          Icon(Icons.Default.Search, null, tint = HaraanColors.TextMuted, modifier = Modifier.size(44.dp))
          Spacer(Modifier.height(10.dp))
          Text("No results for \"$q\"", color = HaraanColors.TextPrimary, fontSize = 15.sp, fontWeight = FontWeight.Bold)
          Text("Try a venue, team, or player name", color = HaraanColors.TextSecondary, fontSize = 13.sp)
        }
      } else {
        // ── Scoped results ──
        LazyColumn(
          modifier = Modifier.fillMaxSize(),
          contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
          verticalArrangement = Arrangement.spacedBy(6.dp)
        ) {
          if (groundHits.isNotEmpty()) {
            item { SearchSectionLabel("Grounds") }
            items(groundHits) { v -> VenueResultRow(v) { addRecent(q); onVenueClick(v) } }
          }
          if (matchHits.isNotEmpty()) {
            item { SearchSectionLabel("Live Matches") }
            items(matchHits) { m -> MatchResultRow(m) { addRecent(q); onMatchClick(m.id) } }
          }
          if (playerHits.isNotEmpty()) {
            item { SearchSectionLabel("Players") }
            items(playerHits) { p -> PlayerResultRow(p, accent) { addRecent(p.name) } }
          }
        }
      }
    }
  }
}

@Composable
private fun SearchSectionLabel(text: String) {
  Text(text, color = HaraanColors.TextSecondary, fontSize = 12.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.5.sp, modifier = Modifier.padding(top = 10.dp, bottom = 2.dp))
}

@Composable
private fun SearchChip(text: String, accent: Color, removable: Boolean = false, onClick: () -> Unit, onRemove: (() -> Unit)? = null) {
  Row(
    modifier = Modifier.clip(RoundedCornerShape(50)).background(HaraanColors.Surface).border(1.dp, HaraanColors.BorderLight, RoundedCornerShape(50)).clickable { onClick() }.padding(horizontal = 14.dp, vertical = 8.dp),
    verticalAlignment = Alignment.CenterVertically,
    horizontalArrangement = Arrangement.spacedBy(6.dp)
  ) {
    Text(text, color = HaraanColors.TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.Medium, maxLines = 1)
    if (removable && onRemove != null) {
      Icon(Icons.Default.Close, "Remove", tint = HaraanColors.TextMuted, modifier = Modifier.size(14.dp).clickable { onRemove() })
    }
  }
}

@Composable
private fun VenueResultRow(venue: VenueItem, onClick: () -> Unit) {
  Row(
    modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(HaraanColors.Surface).border(1.dp, HaraanColors.BorderLight, RoundedCornerShape(14.dp)).clickable { onClick() }.padding(10.dp),
    verticalAlignment = Alignment.CenterVertically,
    horizontalArrangement = Arrangement.spacedBy(12.dp)
  ) {
    AsyncImage(model = venue.imageUrl, contentDescription = null, contentScale = ContentScale.Crop, modifier = Modifier.size(48.dp).clip(RoundedCornerShape(10.dp)).background(HaraanColors.BorderLight))
    Column(Modifier.weight(1f)) {
      Text(venue.title, color = HaraanColors.TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.Bold, maxLines = 1, overflow = TextOverflow.Ellipsis)
      Text(venue.location, color = HaraanColors.TextSecondary, fontSize = 12.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(2.dp)) {
      Icon(Icons.Default.Star, null, tint = Color(0xFFF59E0B), modifier = Modifier.size(14.dp))
      Text(venue.rating, color = HaraanColors.TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.Bold)
    }
  }
}

@Composable
private fun MatchResultRow(m: com.example.thanna.data.LiveMatchRow, onClick: () -> Unit) {
  Row(
    modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(HaraanColors.Surface).border(1.dp, HaraanColors.BorderLight, RoundedCornerShape(14.dp)).clickable { onClick() }.padding(12.dp),
    verticalAlignment = Alignment.CenterVertically,
    horizontalArrangement = Arrangement.spacedBy(10.dp)
  ) {
    if (m.isLive) Box(Modifier.size(8.dp).clip(CircleShape).background(Color(0xFFEF4444)))
    Column(Modifier.weight(1f)) {
      Text("${m.team1} vs ${m.team2}", color = HaraanColors.TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.Bold, maxLines = 1, overflow = TextOverflow.Ellipsis)
      val sub = listOf(m.competition, m.venue).filter { it.isNotBlank() }.joinToString(" • ")
      if (sub.isNotBlank()) Text(sub, color = HaraanColors.TextSecondary, fontSize = 12.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
    if (m.isLive) Text("LIVE", color = Color(0xFFD32F2F), fontSize = 10.sp, fontWeight = FontWeight.ExtraBold)
  }
}

@Composable
private fun PlayerResultRow(p: PlayerHit, accent: Color, onClick: () -> Unit) {
  Row(
    modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(HaraanColors.Surface).border(1.dp, HaraanColors.BorderLight, RoundedCornerShape(14.dp)).clickable { onClick() }.padding(10.dp),
    verticalAlignment = Alignment.CenterVertically,
    horizontalArrangement = Arrangement.spacedBy(12.dp)
  ) {
    Box(Modifier.size(44.dp).clip(CircleShape).background(accent.copy(alpha = 0.12f)), contentAlignment = Alignment.Center) {
      Text(p.name.take(1), color = accent, fontSize = 18.sp, fontWeight = FontWeight.Bold)
    }
    Column(Modifier.weight(1f)) {
      Text(p.name, color = HaraanColors.TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.Bold, maxLines = 1, overflow = TextOverflow.Ellipsis)
      Text("${p.role} • ${p.team}", color = HaraanColors.TextSecondary, fontSize = 12.sp, maxLines = 1)
    }
  }
}

