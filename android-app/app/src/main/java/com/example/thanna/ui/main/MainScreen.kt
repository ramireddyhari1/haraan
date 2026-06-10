package com.example.thanna.ui.main

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.tween
import androidx.compose.animation.core.spring
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
import androidx.compose.foundation.layout.heightIn
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
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.SportsCricket
import androidx.compose.material.icons.filled.SportsFootball
import androidx.compose.material.icons.filled.SportsTennis
import androidx.compose.material.icons.filled.SportsBasketball
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
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.SideEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.blur
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.zIndex
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.IconButton
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
import androidx.compose.ui.text.TextStyle
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
import com.example.thanna.ui.components.SectionHeader

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
    com.example.thanna.ui.LoginRoute(
      onSkipClick = {
        com.example.thanna.data.TokenStore.saveToken(context, "skipped_guest")
        cachedToken = "skipped_guest"
      },
      onLoginSuccess = { token ->
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

// LoginScreen and its local helper methods have been migrated to com.example.thanna.ui package.

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
            Color(0xFF0288D1) // Premium sky blue for Events subtab
          } else {
            MIGreen
          }
        } else {
          if (isDark) Color(0xFF0288D1) else MIBlue
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
  val localContext = LocalContext.current
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
        onMatchClick = { matchId -> onItemClick(com.example.thanna.MatchDetails(matchId)) }
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
                onSearchQueryChange = { searchQuery = it },
                onProfileClick = { showLogoutDialog = true },
                activeSubTab = activeSubTab,
                onTabSelected = { activeSubTab = it },
                onActionBoardClick = { showActionBoardDetail = true }
              )
            }
          }
          1 -> LeaderboardTabScreen()
        }
      }
    }
  }
}
}



@Composable
private fun EventsTabScreen(
  searchQuery: String,
  onEventClick: (EventItem) -> Unit
) {
  var selectedCategory by remember { mutableStateOf("All") }
  val localContext = LocalContext.current
  
  val bannerEvents = listOf(
    BannerItem(
      "9",
      "Karthik Live In Hyderabad",
      "Quake Arena, Kondapur, Hyderabad",
      "Sat, 13 Jun • 7:00 PM",
      "₹2,999 onwards",
      "https://media.insider.in/image/upload/c_crop,g_custom,q_auto/v1777470598/ikf8alfkn0vutbb1ugxz.jpg"
    ),
    BannerItem(
      "10",
      "Amaal Mallik Live at Quake Arena",
      "Quake | Kondapur, Hyderabad",
      "Fri, 12 Jun • 7:00 PM",
      "₹599 onwards",
      "https://media.insider.in/image/upload/c_crop,g_custom,q_auto/v1778825095/gvfqqsvogxamj88yijun.jpg"
    ),
    BannerItem(
      "11",
      "Saturday Soiree ft. Merakee Live",
      "Raasta | Hitech City, Hyderabad",
      "Sat, 13 Jun • 9:30 PM",
      "₹1,000 onwards",
      "https://cdn.district.in/assets/events/publisher/event_cover_image_horizontal/01KTEMTANYJC4WW9B7XGPZKQM3.png"
    ),
    BannerItem(
      "12",
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
  
  val eventsData = listOf(
    EventItem("9", "Karthik Live In Hyderabad", "Sat, 13 Jun, 7:00 PM", "Quake Arena, Kondapur", "₹2999 onwards", "Concerts", "https://media.insider.in/image/upload/c_crop,g_custom,q_auto/v1777470598/ikf8alfkn0vutbb1ugxz.jpg", isFillingFast = true),
    EventItem("10", "Amaal Mallik Live at Quake Arena", "Fri, 12 Jun, 7:00 PM", "Quake Arena, Kondapur", "₹599 onwards", "Concerts", "https://media.insider.in/image/upload/c_crop,g_custom,q_auto/v1778825095/gvfqqsvogxamj88yijun.jpg", isFillingFast = true),
    EventItem("11", "Saturday Soiree ft. Merakee Live", "Sat, 13 Jun, 9:30 PM", "Raasta, Hitech City", "₹1000 onwards", "Concerts", "https://cdn.district.in/assets/events/publisher/event_cover_image_horizontal/01KTEMTANYJC4WW9B7XGPZKQM3.png", isFillingFast = true),
    EventItem("12", "Tribute to Arijit Singh (Ed. 2) Ft. Root 35", "Sat, 27 Jun, 9:00 PM", "Hard Rock Cafe, Banjara Hills", "₹249 onwards", "Concerts", "https://cdn.district.in/assets/events/publisher/event_cover_image_horizontal/01KS7X3SFGYW02JHNBXEYYB7KJ.jpg", isFillingFast = true),
  )
  
  val filteredEvents = eventsData.filter {
    val matchesSearch = searchQuery.isEmpty() || it.title.contains(searchQuery, ignoreCase = true) || it.venue.contains(searchQuery, ignoreCase = true)
    val matchesCategory = when (selectedCategory) {
      "Concerts" -> it.category == "Concerts"
      "Standup" -> it.category == "Comedy"
      else -> true
    }
    matchesSearch && matchesCategory
  }

  androidx.compose.foundation.lazy.LazyColumn(
    modifier = Modifier
      .fillMaxSize()
      .background(Color(0xFFF6F8FB)),
    verticalArrangement = Arrangement.spacedBy(20.dp)
  ) {
    // Abstract background shapes removed as requested

    if (selectedCategory == "All") {
      // "For you" section with Infinite 3D Loop Pager
      item {
        SectionHeader(
          title = "For you"
        )
      }

      item {
        InfiniteLoopBookPager(events = filteredEvents, onEventClick = onEventClick)
      }
    } else {
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
    }

    // Category Buttons Row (Concerts, Standup, All)
    item {
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .padding(horizontal = 12.dp)
          .padding(vertical = 4.dp),
        horizontalArrangement = Arrangement.spacedBy(10.dp)
      ) {
        CustomCategoryCard(
          title = "Concerts",
          painter = painterResource(id = com.example.thanna.R.drawable.ic_live_music),
          iconColor = Color(0xFFFF5B7F),
          glowColor = Color(0xFFFF5B7F),
          selected = selectedCategory == "Concerts",
          onClick = { selectedCategory = "Concerts" },
          modifier = Modifier.weight(1f)
        )
        CustomCategoryCard(
          title = "Standup",
          painter = painterResource(id = com.example.thanna.R.drawable.ic_standup_comedy),
          iconColor = Color(0xFF3F7FFF),
          glowColor = Color(0xFF3F7FFF),
          selected = selectedCategory == "Standup",
          onClick = { selectedCategory = "Standup" },
          modifier = Modifier.weight(1f)
        )
        CustomCategoryCard(
          title = "All",
          painter = painterResource(id = com.example.thanna.R.drawable.ic_select_all),
          iconColor = Color(0xFFFFC83B),
          glowColor = Color(0xFFFFC83B),
          selected = selectedCategory == "All",
          onClick = { selectedCategory = "All" },
          modifier = Modifier.weight(1f)
        )
      }
    }

    if (true) {


      // "Trending" Section (Small Horizontal Cards)
      item {
        SectionHeader(
          title = "Trending"
        )
      }
      item {
        TrendingRowSection(events = filteredEvents, onEventClick = onEventClick)
      }

      // Handpicked / Title section
      item {
        SectionHeader(
          title = "Trending events in Mumbai",
          subtitle = "Handpicked experiences"
        )
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
  painter: androidx.compose.ui.graphics.painter.Painter,
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
      containerColor = if (selected) Color.Transparent else Color(0xFFF1F5F9) // Set transparent for gradient overlay
    ),
    border = BorderStroke(
      width = if (selected) 1.5.dp else 1.dp,
      color = if (selected) Color(0xFF56B4E9).copy(alpha = 0.6f) else Color(0xFFE2E8F0)
    ),
    elevation = CardDefaults.cardElevation(defaultElevation = if (selected) 3.dp else 0.dp)
  ) {
    val boxModifier = if (selected) {
      Modifier
        .fillMaxSize()
        .background(
          Brush.verticalGradient(
            colors = listOf(
              Color(0xFF56B4E9).copy(alpha = 0.85f), // Premium Sky Blue
              Color.White                            // Fading into pure white
            )
          )
        )
        .padding(8.dp)
    } else {
      Modifier
        .fillMaxSize()
        .padding(8.dp)
    }

    Box(
      modifier = boxModifier,
      contentAlignment = Alignment.Center
    ) {
      Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
      ) {
        // Simple minimal backdrop behind icon (No glow)
        Box(
          modifier = Modifier
            .size(50.dp)
            .background(
              if (selected) Color.White.copy(alpha = 0.5f) else Color.Transparent,
              RoundedCornerShape(UnifiedCornerRadius)
            ),
          contentAlignment = Alignment.Center
        ) {
          androidx.compose.material3.Icon(
            painter = painter,
            contentDescription = title,
            tint = Color.Unspecified,
            modifier = Modifier.size(38.dp)
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

@Immutable
private data class EventItem(
  val id: String,
  val title: String,
  val date: String,
  val venue: String,
  val price: String,
  val category: String,
  val imageUrl: String,
  val isFillingFast: Boolean = false
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

@OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)
@Composable
fun EventDetailScreen(
  event: EventDetail,
  onBack: () -> Unit
) {
  val context = LocalContext.current
  val scrollState = rememberScrollState()
  var isSaved by remember { mutableStateOf(false) }

  val configuration = androidx.compose.ui.platform.LocalConfiguration.current
  val screenHeight = configuration.screenHeightDp.dp
  val posterHeight = screenHeight * 0.6f

  Box(modifier = Modifier.fillMaxSize().background(Color(0xFFF9F9F9))) {
    
    // --- 1. BACKGROUND / POSTER ZONE (60% Screen Layout Base) ---
    Box(
      modifier = Modifier
        .fillMaxWidth()
        .height(posterHeight + 50.dp)
        .graphicsLayer {
          // Parallax movement at 50% scroll speed
          translationY = -scrollState.value * 0.5f
          // Smooth fade out as card scrolls to the top
          alpha = (1f - (scrollState.value / (posterHeight.toPx())).coerceIn(0f, 1f))
        }
    ) {
      AsyncImage(
        model = event.imageUrl,
        contentDescription = event.title,
        contentScale = ContentScale.Crop,
        modifier = Modifier.fillMaxSize()
      )
      
      // Poster Scrolling Indicators & Gallery Button Placements
      Row(
        modifier = Modifier
          .align(Alignment.BottomCenter)
          .padding(bottom = 16.dp) // Offset clear of the card overlap
          .fillMaxWidth()
          .padding(horizontal = 16.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
      ) {
        // Page Indicator Dots
        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
          Box(modifier = Modifier.size(8.dp).background(Color.White, CircleShape))
          Box(modifier = Modifier.size(8.dp).background(Color.White.copy(alpha = 0.5f), CircleShape))
        }
        
        // Gallery Action Target Button
        Button(
          onClick = { Toast.makeText(context, "Gallery view connection coming soon.", Toast.LENGTH_SHORT).show() },
          colors = ButtonDefaults.buttonColors(containerColor = Color.Black.copy(alpha = 0.6f)),
          contentPadding = PaddingValues(horizontal = 16.dp, vertical = 6.dp),
          shape = RoundedCornerShape(20.dp)
        ) {
          Icon(Icons.Default.Movie, contentDescription = null, modifier = Modifier.size(16.dp), tint = Color.White)
          Spacer(modifier = Modifier.width(6.dp))
          Text("Gallery", fontSize = 12.sp, color = Color.White)
        }
      }
    }

    // --- 2. MAIN EVENT CARD CONTENT CONTAINER ---
    // Overlaps smoothly with the base poster layer using dynamic scrolling content padding
    Column(
      modifier = Modifier
        .fillMaxSize()
        .verticalScroll(scrollState)
    ) {
      // Dynamic spacer forcing details block to begin at the 60% mark
      Spacer(modifier = Modifier.height(posterHeight))

      // Scrollable Info Details Block Card
      Column(
        modifier = Modifier
          .fillMaxWidth()
          .clip(RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp))
          .background(Color.White)
          .padding(top = 8.dp, bottom = 100.dp) // Extra bottom padding blocks overlap from bottom action bar
      ) {
        
        // Blur Background Header indicator
        Box(
          modifier = Modifier
            .fillMaxWidth()
            .height(30.dp)
            .background(Color.Gray.copy(alpha = 0.05f))
            .padding(horizontal = 16.dp),
          contentAlignment = Alignment.CenterStart
        ) {
          Text(
            text = "${event.category} • Music Experience", 
            fontSize = 11.sp, 
            color = Color.Gray
          )
        }

        Column(modifier = Modifier.padding(horizontal = 16.dp)) {
          Spacer(modifier = Modifier.height(16.dp))

          Spacer(modifier = Modifier.height(12.dp))

          // Main Header Event Title Typography Text Frame
          Text(
            text = event.title,
            fontSize = 24.sp,
            fontWeight = FontWeight.Bold,
            color = Color(0xFF1A1A1A)
          )

          Spacer(modifier = Modifier.height(6.dp))
          
          // Date Timestamp Label Element
          Text(
            text = event.date,
            fontSize = 14.sp,
            color = LightAccentBlue, // Stylized semantic highlight
            fontWeight = FontWeight.SemiBold
          )

          Spacer(modifier = Modifier.height(16.dp))
          Divider(color = Color(0xFFEEEEEE))
          Spacer(modifier = Modifier.height(16.dp))

          // Location Intermediary Quick-Link Callout
          InteractiveRowItem(
            painter = painterResource(id = com.example.thanna.R.drawable.ic_pin),
            title = event.venue,
            subtitle = "Click to view full map directions"
          )

          Spacer(modifier = Modifier.height(12.dp))

          // Schedule & Time-Stamp Program Breakdown Reference Row
          InteractiveRowItem(
            painter = painterResource(id = com.example.thanna.R.drawable.ic_schedule),
            title = "Schedule & Timeline",
            subtitle = "Doors Open: 6:00 PM • Main Act: 8:00 PM"
          )

          Spacer(modifier = Modifier.height(20.dp))

          // Info Question boxes layout
          Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
          ) {
            InfoQuestionBox(
              title = "Why this event?", 
              desc = "Exclusive open-air live acoustic setups.",
              modifier = Modifier.weight(1f)
            )
            InfoQuestionBox(
              title = "Vibe Check", 
              desc = "Energetic mass crowds, neon themes.",
              modifier = Modifier.weight(1f)
            )
          }

          Spacer(modifier = Modifier.height(24.dp))

          // Performer Showcase Row
          Text(
            text = "Who's taking the stage",
            fontSize = 18.sp,
            fontWeight = FontWeight.Bold,
            color = Color(0xFF222222)
          )
          
          Spacer(modifier = Modifier.height(12.dp))
          
          // Performer List Display Matrix
          LazyRow(
            horizontalArrangement = Arrangement.spacedBy(16.dp),
            contentPadding = PaddingValues(end = 16.dp)
          ) {
            items(performersList) { PerformerCard(it) }
          }

          Spacer(modifier = Modifier.height(24.dp))

          // Overview and Guidelines Info
          Text(
            text = "About the event",
            fontSize = 18.sp,
            fontWeight = FontWeight.Bold,
            color = Color(0xFF222222)
          )
          
          Spacer(modifier = Modifier.height(8.dp))
          
          val aboutText = when (event.category.lowercase()) {
            "concerts" -> "A high-energy live concert experience with premium sound, easy entry, and a crowd-ready atmosphere built for fans who want the full show-night feel."
            "comedy" -> "A polished night of stand-up with comfortable seating, smooth entry, and a relaxed venue setup designed for easy laughs and a great view."
            "nightlife" -> "A vibrant evening event with immersive lighting, strong production value, and a venue flow that keeps the experience moving from arrival to encore."
            "festivals" -> "A large-format festival experience with multiple highlights, lively venue energy, and a seamless event flow built for discovery and celebration."
            "workshops" -> "A hands-on experience with structured sessions, guided participation, and a comfortable venue environment that keeps the focus on learning and creating."
            else -> "A premium event experience designed around smooth entry, clear venue information, and a booking flow that feels easy from start to finish."
          }

          Text(
            text = aboutText,
            fontSize = 14.sp,
            color = Color(0xFF666666),
            lineHeight = 20.sp,
            maxLines = 3,
            overflow = TextOverflow.Ellipsis
          )
          
          TextButton(
            onClick = { Toast.makeText(context, "Expanded details available soon.", Toast.LENGTH_SHORT).show() },
            contentPadding = PaddingValues(0.dp),
            modifier = Modifier.align(Alignment.Start)
          ) {
            Text("Read more", color = Color(0xFF007AFF), fontWeight = FontWeight.SemiBold)
          }

          Spacer(modifier = Modifier.height(16.dp))
          Divider(color = Color(0xFFEEEEEE))
          Spacer(modifier = Modifier.height(20.dp))

          // Informational Meta Guidelines section block
          Text(
            text = "Must Know",
            fontSize = 17.sp,
            fontWeight = FontWeight.Bold,
            color = Color(0xFF222222)
          )
          
          Spacer(modifier = Modifier.height(12.dp))

          // Custom Data points matching labeled symbols
          GuidelineRowItem(
            painter = painterResource(id = com.example.thanna.R.drawable.ic_language), 
            text = "Event will be in Telugu, English & Hindi"
          )
          GuidelineRowItem(
            painter = painterResource(id = com.example.thanna.R.drawable.ic_age), 
            text = "Ticket needed for all ages above 5 years"
          )
          GuidelineRowItem(
            painter = painterResource(id = com.example.thanna.R.drawable.ic_online_ticket), 
            text = "Entry allowed for valid digital pass holders only"
          )
          GuidelineRowItem(
            painter = androidx.compose.ui.graphics.vector.rememberVectorPainter(Icons.Default.Info), 
            text = "Open to all public profiles",
            tint = Color(0xFF555555)
          )
        }
      }
    }

    // --- 3. TOP NAVIGATION OVERLAY ACTION BAR (Rendered on top of everything) ---
    Row(
      modifier = Modifier
        .fillMaxWidth()
        .statusBarsPadding()
        .padding(start = 16.dp, end = 16.dp, top = 2.dp, bottom = 12.dp),
      horizontalArrangement = Arrangement.SpaceBetween,
      verticalAlignment = Alignment.CenterVertically
    ) {
      // Back Button
      IconButton(
        onClick = onBack,
        modifier = Modifier.background(Color.White.copy(alpha = 0.9f), CircleShape)
      ) {
        Icon(
          painter = painterResource(id = com.example.thanna.R.drawable.ic_back),
          contentDescription = "Back Button",
          tint = Color.Unspecified,
          modifier = Modifier.size(24.dp)
        )
      }
      
      // Action Buttons Right Side (Save & Share)
      Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        IconButton(
          onClick = { isSaved = !isSaved },
          modifier = Modifier.background(Color.White.copy(alpha = 0.9f), CircleShape)
        ) {
          Icon(
            imageVector = if (isSaved) Icons.Default.Bookmark else Icons.Default.BookmarkBorder,
            contentDescription = "Save Button",
            tint = if (isSaved) Color(0xFF0F62FE) else Color.Black
          )
        }
        IconButton(
          onClick = {
            val sendIntent = android.content.Intent().apply {
              action = android.content.Intent.ACTION_SEND
              putExtra(android.content.Intent.EXTRA_TEXT, "Check out this event: ${event.title}")
              type = "text/plain"
            }
            context.startActivity(android.content.Intent.createChooser(sendIntent, null))
          },
          modifier = Modifier.background(Color.White.copy(alpha = 0.9f), CircleShape)
        ) {
          Icon(
            painter = painterResource(id = com.example.thanna.R.drawable.ic_share),
            contentDescription = "Share Button",
            tint = Color.Unspecified,
            modifier = Modifier.size(24.dp)
          )
        }
      }
    }

    // --- STICKY FIXATION FOOTER BAR: Absolute overlay base layout position ---
    Surface(
      modifier = Modifier
        .align(Alignment.BottomCenter)
        .fillMaxWidth(),
      tonalElevation = 8.dp,
      shadowElevation = 16.dp,
      color = Color.White
    ) {
      Row(
        modifier = Modifier
          .navigationBarsPadding()
          .padding(horizontal = 20.dp, vertical = 14.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
      ) {
        // Price Tag Display System Frame
        Column {
          Text("Price", fontSize = 12.sp, color = Color.Gray)
          Text(event.price, fontSize = 18.sp, fontWeight = FontWeight.Bold, color = Color(0xFF111111))
        }

        // Action Conversion Button Frame
        Button(
          onClick = { Toast.makeText(context, "Booking flow can be connected next.", Toast.LENGTH_SHORT).show() },
          colors = ButtonDefaults.buttonColors(containerColor = Color.Transparent),
          shape = RoundedCornerShape(12.dp),
          contentPadding = PaddingValues(horizontal = 28.dp, vertical = 14.dp),
          modifier = Modifier.background(
            brush = Brush.horizontalGradient(
              colors = listOf(
                Color(0xFF1E3A8A), // Dark blue
                Color(0xFF2563EB)  // Accent blue
              )
            ),
            shape = RoundedCornerShape(12.dp)
          )
        ) {
          Text(
            text = "Book tickets",
            fontSize = 16.sp,
            fontWeight = FontWeight.Bold,
            color = Color.White
          )
        }
      }
    }
  }
}

@Composable
private fun CategoryChip(label: String) {
  Surface(
    color = Color(0xFFF0F0F0),
    shape = RoundedCornerShape(6.dp),
    modifier = Modifier.padding(vertical = 2.dp)
  ) {
    Text(
      text = label,
      fontSize = 12.sp,
      color = Color(0xFF444444),
      modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp),
      fontWeight = FontWeight.Medium
    )
  }
}

@Composable
private fun InteractiveRowItem(painter: androidx.compose.ui.graphics.painter.Painter, title: String, subtitle: String) {
  Row(
    modifier = Modifier.fillMaxWidth(),
    verticalAlignment = Alignment.CenterVertically
  ) {
    Box(
      modifier = Modifier
        .size(40.dp)
        .background(Color(0xFFE3F2FD), RoundedCornerShape(8.dp)),
      contentAlignment = Alignment.Center
    ) {
      Icon(painter = painter, contentDescription = null, tint = LightAccentBlue, modifier = Modifier.size(20.dp))
    }
    Spacer(modifier = Modifier.width(14.dp))
    Column(modifier = Modifier.weight(1f)) {
      Text(title, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = Color(0xFF333333))
      Text(subtitle, fontSize = 13.sp, color = Color.Gray)
    }
    Icon(Icons.Default.KeyboardArrowDown, contentDescription = "View Details icon arrow", tint = Color.LightGray, modifier = Modifier.size(20.dp))
  }
}

@Composable
private fun InfoQuestionBox(title: String, desc: String, modifier: Modifier = Modifier) {
  Column(
    modifier = modifier
      .background(Color(0xFFF7F7F9), RoundedCornerShape(12.dp))
      .padding(14.dp)
  ) {
    Text(title, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = Color(0xFF111111))
    Spacer(modifier = Modifier.height(4.dp))
    Text(desc, fontSize = 12.sp, color = Color(0xFF555555), lineHeight = 16.sp)
  }
}

@Composable
private fun PerformerCard(performer: Performer) {
  Box(
    modifier = Modifier
      .width(140.dp)
      .height(190.dp)
      .clip(RoundedCornerShape(14.dp))
      .background(Color.DarkGray)
  ) {
    // Image item placeholder frame base layer
    Box(modifier = Modifier.fillMaxSize().background(Color.Gray))

    // Text data footer alignment card overlay item
    Column(
      modifier = Modifier
        .fillMaxWidth()
        .align(Alignment.BottomCenter)
        .background(Color.Black.copy(alpha = 0.7f))
        .padding(8.dp)
    ) {
      Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
      ) {
        Column {
          Text(performer.name, color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
          Text(performer.type, color = Color.LightGray, fontSize = 11.sp)
        }
        Icon(
          Icons.Default.BookmarkBorder, 
          contentDescription = "Save Performer icon state", 
          tint = Color.White,
          modifier = Modifier.size(16.dp)
        )
      }
    }
  }
}

@Composable
private fun GuidelineRowItem(
  painter: androidx.compose.ui.graphics.painter.Painter,
  text: String,
  tint: Color = Color.Unspecified
) {
  Row(
    modifier = Modifier.fillMaxWidth().padding(vertical = 6.dp),
    verticalAlignment = Alignment.CenterVertically
  ) {
    Icon(painter = painter, contentDescription = null, tint = tint, modifier = Modifier.size(18.dp))
    Spacer(modifier = Modifier.width(12.dp))
    Text(text, fontSize = 13.sp, color = Color(0xFF444444))
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
  onActionBoardClick: () -> Unit
) {
  var selectedSport by remember { mutableStateOf("All") }
  
  val sports = listOf("All", "Cricket", "Football", "Badminton", "Basketball")
  
  val venuesData = listOf(
    VenueItem("1", "Stryker Turf Center", "Andheri West", "4.8", "Cricket", 1200),
    VenueItem("2", "Kick Sports Arena", "Powai", "4.6", "Football", 1500),
    VenueItem("3", "Badminton Club Pro", "Bandra", "4.9", "Badminton", 600),
    VenueItem("4", "Hoop City Court", "Ghatkopar", "4.6", "Basketball", 1000),
  )

  val filteredVenues = venuesData.filter {
    (selectedSport == "All" || it.category == selectedSport) &&
    (searchQuery.isEmpty() || it.title.contains(searchQuery, ignoreCase = true) || it.location.contains(searchQuery, ignoreCase = true))
  }

  androidx.compose.foundation.lazy.LazyColumn(
    modifier = Modifier
      .fillMaxSize()
      .background(Color(0xFFF8FAFC)),
    verticalArrangement = Arrangement.spacedBy(16.dp)
  ) {
    // 1. Dark Green Header Section (edge-to-edge)
    item {
      Column(
        modifier = Modifier
          .fillMaxWidth()
          .background(
            color = Color(0xFF144D3D),
            shape = RoundedCornerShape(bottomStart = 28.dp, bottomEnd = 28.dp)
          )
          .statusBarsPadding()
          .padding(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 24.dp)
      ) {
        // Tab switcher
        GameHubSegmentedSwitch(
          selectedTab = activeSubTab,
          onTabSelected = onTabSelected,
          modifier = Modifier.padding(bottom = 16.dp)
        )

        // Location & Profile row
        Row(
          modifier = Modifier.fillMaxWidth(),
          verticalAlignment = Alignment.CenterVertically
        ) {
          Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier.weight(1f)
          ) {
            Box(
              modifier = Modifier
                .size(36.dp)
                .background(Color.White.copy(alpha = 0.15f), RoundedCornerShape(999.dp)),
              contentAlignment = Alignment.Center
            ) {
              Icon(
                imageVector = Icons.Default.LocationOn,
                contentDescription = "Location",
                tint = Color.White,
                modifier = Modifier.size(18.dp)
              )
            }
            Spacer(modifier = Modifier.width(10.dp))
            Column {
              Text(
                text = "Your Location",
                color = Color.White.copy(alpha = 0.65f),
                fontSize = 11.sp
              )
              Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                  text = "Vaddeswaram, AP",
                  color = Color.White,
                  fontWeight = FontWeight.Bold,
                  fontSize = 14.sp
                )
                Spacer(modifier = Modifier.width(4.dp))
                Icon(
                  imageVector = Icons.Default.KeyboardArrowDown,
                  contentDescription = "Select Location",
                  tint = Color.White,
                  modifier = Modifier.size(16.dp)
                )
              }
            }
          }

          // Notification Icon
          Box(
            modifier = Modifier
              .size(38.dp)
              .clip(RoundedCornerShape(12.dp))
              .background(Color.White.copy(alpha = 0.15f))
              .clickable { /* Notification */ },
            contentAlignment = Alignment.Center
          ) {
            Icon(
              imageVector = Icons.Default.Notifications,
              contentDescription = "Notifications",
              tint = Color.White,
              modifier = Modifier.size(18.dp)
            )
            Box(
              modifier = Modifier
                .size(6.dp)
                .background(Color(0xFFEF4444), RoundedCornerShape(999.dp))
                .align(Alignment.TopEnd)
                .offset(x = (-8).dp, y = 8.dp)
            )
          }

          Spacer(modifier = Modifier.width(10.dp))

          // Profile avatar
          Box(
            modifier = Modifier
              .size(38.dp)
              .clip(RoundedCornerShape(12.dp))
              .background(Color.White.copy(alpha = 0.15f))
              .clickable { onProfileClick() },
            contentAlignment = Alignment.Center
          ) {
            Icon(
              imageVector = Icons.Default.Person,
              contentDescription = "Profile",
              tint = Color.White,
              modifier = Modifier.size(18.dp)
            )
          }
        }

        Spacer(modifier = Modifier.height(18.dp))

        // Search Bar
        Row(
          modifier = Modifier
            .fillMaxWidth()
            .height(48.dp)
            .background(Color.White, RoundedCornerShape(14.dp))
            .padding(horizontal = 14.dp),
          verticalAlignment = Alignment.CenterVertically
        ) {
          Icon(
            imageVector = Icons.Default.Search,
            contentDescription = "Search",
            tint = Color.Gray,
            modifier = Modifier.size(20.dp)
          )
          Spacer(modifier = Modifier.width(10.dp))
          androidx.compose.foundation.text.BasicTextField(
            value = searchQuery,
            onValueChange = onSearchQueryChange,
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
                  text = "Search for arenas, sports...",
                  color = Color.Gray,
                  fontSize = 14.sp
                )
              }
              innerTextField()
            }
          )
        }

        Spacer(modifier = Modifier.height(18.dp))

        // Explore Promo Banner Card
        Card(
          modifier = Modifier.fillMaxWidth(),
          shape = RoundedCornerShape(20.dp),
          colors = CardDefaults.cardColors(containerColor = Color.White.copy(alpha = 0.08f)),
          border = BorderStroke(1.dp, Color.White.copy(alpha = 0.15f))
        ) {
          Row(
            modifier = Modifier
              .fillMaxWidth()
              .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
          ) {
            Column(
              modifier = Modifier.weight(1f)
            ) {
              Text(
                text = "YOUR SOLUTION,\nONE TAP AWAY!",
                color = Color.White,
                fontWeight = FontWeight.Bold,
                fontSize = 18.sp,
                lineHeight = 22.sp
              )
              Spacer(modifier = Modifier.height(6.dp))
              Text(
                text = "Seamless, Fast & Reliable\nBookings at Your Fingertip",
                color = Color.White.copy(alpha = 0.7f),
                fontSize = 11.sp,
                lineHeight = 14.sp
              )
              Spacer(modifier = Modifier.height(14.dp))
              Box(
                modifier = Modifier
                  .clip(RoundedCornerShape(8.dp))
                  .background(Color.White)
                  .clickable { onActionBoardClick() }
                  .padding(horizontal = 14.dp, vertical = 8.dp)
              ) {
                Text(
                  text = "Explore",
                  color = Color(0xFF144D3D),
                  fontSize = 12.sp,
                  fontWeight = FontWeight.Bold
                )
              }
            }

            Image(
              painter = painterResource(id = com.example.thanna.R.drawable.sports_booking_banner),
              contentDescription = "Explore sports",
              modifier = Modifier
                .size(110.dp)
                .clip(RoundedCornerShape(12.dp)),
              contentScale = ContentScale.Crop
            )
          }
        }
      }
    }

    // 2. Sport Categories Header
    item {
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .padding(horizontal = 16.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
      ) {
        Text(
          text = "Sport Categories",
          color = Color(0xFF0F172A),
          fontWeight = FontWeight.Bold,
          fontSize = 16.sp
        )
        Text(
          text = "View all",
          color = Color(0xFF144D3D),
          fontWeight = FontWeight.Bold,
          fontSize = 13.sp
        )
      }
    }

    // 3. Sport Categories 2x2 Grid
    item {
      Column(
        modifier = Modifier
          .fillMaxWidth()
          .padding(horizontal = 16.dp)
      ) {
        Row(modifier = Modifier.fillMaxWidth()) {
          CategoryCard(
            title = "Cricket",
            icon = Icons.Default.SportsCricket,
            isSelected = selectedSport == "Cricket",
            onClick = { selectedSport = if (selectedSport == "Cricket") "All" else "Cricket" },
            modifier = Modifier.weight(1f)
          )
          Spacer(modifier = Modifier.width(12.dp))
          CategoryCard(
            title = "Football",
            icon = Icons.Default.SportsFootball,
            isSelected = selectedSport == "Football",
            onClick = { selectedSport = if (selectedSport == "Football") "All" else "Football" },
            modifier = Modifier.weight(1f)
          )
        }
        Spacer(modifier = Modifier.height(12.dp))
        Row(modifier = Modifier.fillMaxWidth()) {
          CategoryCard(
            title = "Badminton",
            icon = Icons.Default.SportsTennis,
            isSelected = selectedSport == "Badminton",
            onClick = { selectedSport = if (selectedSport == "Badminton") "All" else "Badminton" },
            modifier = Modifier.weight(1f)
          )
          Spacer(modifier = Modifier.width(12.dp))
          CategoryCard(
            title = "Basketball",
            icon = Icons.Default.SportsBasketball,
            isSelected = selectedSport == "Basketball",
            onClick = { selectedSport = if (selectedSport == "Basketball") "All" else "Basketball" },
            modifier = Modifier.weight(1f)
          )
        }
      }
    }

    // 4. Popular Arenas Header
    item {
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .padding(start = 16.dp, end = 16.dp, top = 8.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
      ) {
        Text(
          text = "Popular Arenas",
          color = Color(0xFF0F172A),
          fontWeight = FontWeight.Bold,
          fontSize = 16.sp
        )
        Text(
          text = "View all",
          color = Color(0xFF144D3D),
          fontWeight = FontWeight.Bold,
          fontSize = 13.sp
        )
      }
    }

    // 5. Popular Arenas Horizontal Scroll
    item {
      if (filteredVenues.isEmpty()) {
        Box(
          modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 20.dp),
          contentAlignment = Alignment.Center
        ) {
          Text(text = "No arenas found matching filters.", color = Color(0xFF64748B))
        }
      } else {
        androidx.compose.foundation.lazy.LazyRow(
          contentPadding = PaddingValues(horizontal = 16.dp),
          horizontalArrangement = Arrangement.spacedBy(12.dp),
          modifier = Modifier.fillMaxWidth()
        ) {
          items(filteredVenues.size) { i ->
            PopularArenaCard(
              venue = filteredVenues[i],
              onClick = { /* Detail action */ }
            )
          }
        }
      }
    }

    // 6. Featured Arenas Header
    item {
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .padding(start = 16.dp, end = 16.dp, top = 8.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
      ) {
        Text(
          text = "Featured Arenas",
          color = Color(0xFF0F172A),
          fontWeight = FontWeight.Bold,
          fontSize = 16.sp
        )
        Text(
          text = "View all",
          color = Color(0xFF144D3D),
          fontWeight = FontWeight.Bold,
          fontSize = 13.sp
        )
      }
    }

    // 7. Featured Arenas Vertical List
    if (filteredVenues.isEmpty()) {
      item {
        Box(
          modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 20.dp),
          contentAlignment = Alignment.Center
        ) {
          Text(text = "No arenas found matching filters.", color = Color(0xFF64748B))
        }
      }
    } else {
      items(filteredVenues.size) { i ->
        Box(modifier = Modifier.padding(horizontal = 16.dp)) {
          VenueListCard(filteredVenues[i])
        }
      }
    }

    item {
      Spacer(modifier = Modifier.height(16.dp))
    }
  }
}

@Composable
private fun CategoryCard(
  title: String,
  icon: androidx.compose.ui.graphics.vector.ImageVector,
  isSelected: Boolean,
  onClick: () -> Unit,
  modifier: Modifier = Modifier
) {
  Card(
    modifier = modifier
      .height(64.dp)
      .clickable { onClick() },
    shape = RoundedCornerShape(14.dp),
    colors = CardDefaults.cardColors(
      containerColor = if (isSelected) Color(0xFF144D3D).copy(alpha = 0.08f) else Color.White
    ),
    border = BorderStroke(
      width = 1.dp,
      color = if (isSelected) Color(0xFF144D3D) else Color(0xFFE2E8F0)
    )
  ) {
    Row(
      modifier = Modifier
        .fillMaxSize()
        .padding(horizontal = 12.dp),
      verticalAlignment = Alignment.CenterVertically,
      horizontalArrangement = Arrangement.SpaceBetween
    ) {
      Row(verticalAlignment = Alignment.CenterVertically) {
        Box(
          modifier = Modifier
            .size(36.dp)
            .background(
              color = if (isSelected) Color(0xFF144D3D).copy(alpha = 0.15f) else Color(0xFFF1F5F9),
              shape = RoundedCornerShape(10.dp)
            ),
          contentAlignment = Alignment.Center
        ) {
          Icon(
            imageVector = icon,
            contentDescription = title,
            tint = if (isSelected) Color(0xFF144D3D) else Color(0xFF64748B),
            modifier = Modifier.size(18.dp)
          )
        }
        Spacer(modifier = Modifier.width(10.dp))
        Text(
          text = title,
          color = Color(0xFF0F172A),
          fontWeight = FontWeight.Bold,
          fontSize = 13.sp
        )
      }
      Icon(
        imageVector = Icons.Default.KeyboardArrowRight,
        contentDescription = "Go",
        tint = Color(0xFF94A3B8),
        modifier = Modifier.size(16.dp)
      )
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
              color = if (isSelected) Color(0xFF144D3D) else Color.White.copy(alpha = 0.75f),
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
  onClick: () -> Unit
) {
  Card(
    modifier = Modifier
      .width(220.dp)
      .clickable { onClick() },
    shape = RoundedCornerShape(16.dp),
    colors = CardDefaults.cardColors(containerColor = Color.White),
    border = BorderStroke(1.dp, Color(0xFFE2E8F0))
  ) {
    Column(modifier = Modifier.fillMaxWidth()) {
      Box(
        modifier = Modifier
          .fillMaxWidth()
          .height(110.dp)
          .background(
            Brush.linearGradient(
              colors = listOf(Color(0xFFEEFBF3), Color(0xFFD1F2DE))
            )
          ),
        contentAlignment = Alignment.Center
      ) {
        Box(
          modifier = Modifier
            .background(Color.White.copy(alpha = 0.8f), RoundedCornerShape(8.dp))
            .padding(horizontal = 10.dp, vertical = 6.dp)
        ) {
          Text(
            text = venue.category,
            color = Color(0xFF144D3D),
            fontWeight = FontWeight.Bold,
            fontSize = 11.sp
          )
        }
      }
      
      Column(modifier = Modifier.padding(12.dp)) {
        Text(
          text = venue.title,
          color = Color(0xFF0F172A),
          fontWeight = FontWeight.Bold,
          fontSize = 13.sp,
          maxLines = 1
        )
        Spacer(modifier = Modifier.height(2.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
          Icon(
            imageVector = Icons.Default.LocationOn,
            contentDescription = null,
            tint = Color.Gray,
            modifier = Modifier.size(12.dp)
          )
          Spacer(modifier = Modifier.width(2.dp))
          Text(
            text = venue.location,
            color = Color.Gray,
            fontSize = 11.sp
          )
        }
        Spacer(modifier = Modifier.height(8.dp))
        Row(
          modifier = Modifier.fillMaxWidth(),
          horizontalArrangement = Arrangement.SpaceBetween,
          verticalAlignment = Alignment.CenterVertically
        ) {
          Text(
            text = "₹${venue.price}/hr",
            color = Color(0xFF144D3D),
            fontWeight = FontWeight.Bold,
            fontSize = 12.sp
          )
          Row(verticalAlignment = Alignment.CenterVertically) {
            Text(text = "★", color = Color(0xFFFFB000), fontSize = 12.sp)
            Spacer(modifier = Modifier.width(2.dp))
            Text(
              text = venue.rating,
              color = Color(0xFF0F172A),
              fontWeight = FontWeight.Bold,
              fontSize = 11.sp
            )
          }
        }
      }
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
  ThannaTheme {
    com.example.thanna.ui.LoginScreen(
      uiState = com.example.thanna.ui.LoginUiState(),
      onPhoneNumberChange = {},
      onOtpChange = {},
      onContinueClick = {},
      onVerifyOtpClick = {},
      onBackToPhoneClick = {}
    )
  }
}

@Preview(showBackground = true, widthDp = 340)
@Composable
fun MainScreenPortraitPreview() {
  ThannaTheme {
    com.example.thanna.ui.LoginScreen(
      uiState = com.example.thanna.ui.LoginUiState(),
      onPhoneNumberChange = {},
      onOtpChange = {},
      onContinueClick = {},
      onVerifyOtpClick = {},
      onBackToPhoneClick = {}
    )
  }
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
      .height(460.dp),
    // Crucial padding structure: exposes a small peek on the left, but leaves 
    // a larger buffer on the right to show the upcoming card stack cleanly.
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
        .fillMaxSize()
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
        .padding(vertical = 8.dp, horizontal = 6.dp)
        .clip(RoundedCornerShape(28.dp))
        .background(Color.Black)
        .clickable { onEventClick(event) }
    ) {
      // 1. Full Bleed Background Image
      AsyncImage(
        model = event.imageUrl,
        contentDescription = event.title,
        contentScale = ContentScale.Crop,
        modifier = Modifier.fillMaxSize()
      )

      // 2. Minimalist Category Tag (Top-Left)
      androidx.compose.material3.Surface(
        color = Color.Black.copy(alpha = 0.4f),
        shape = CircleShape,
        modifier = Modifier
          .padding(16.dp)
          .align(Alignment.TopStart)
      ) {
        Text(
          text = event.category,
          color = Color.White,
          style = MaterialTheme.typography.labelMedium.copy(
            fontWeight = FontWeight.Medium,
            letterSpacing = 0.5.sp
          ),
          modifier = Modifier.padding(horizontal = 14.dp, vertical = 6.dp)
        )
      }

      // 3. The Glassmorphic Bottom Panel
      Box(
        modifier = Modifier
          .fillMaxWidth()
          .height(150.dp) // Covers the lower portion beautifully
          .align(Alignment.BottomCenter)
          .padding(12.dp) // Inset padding makes it look like a floating glass capsule
          .clip(RoundedCornerShape(20.dp))
          // The secret to glassmorphism: Blur layer + Translucent tint layer
          .blur(16.dp) 
          .background(
            Brush.verticalGradient(
              colors = listOf(
                Color.White.copy(alpha = 0.08f),
                Color.White.copy(alpha = 0.15f)
              )
            )
          )
      )

      // 4. Foreground Content Layer (Sits exactly over the glass panel for crisp text)
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .height(150.dp)
          .align(Alignment.BottomCenter)
          .padding(24.dp), // Aligned inside the glass panel bounds
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
      ) {
        // Left Column: Metadata and Text details
        Column(
          modifier = Modifier.weight(1f),
          verticalArrangement = Arrangement.Center
        ) {
          // Date Status Line
          Text(
            text = event.date.replace(", ", "  •  ").uppercase(),
            color = Color.White.copy(alpha = 0.9f),
            style = MaterialTheme.typography.labelSmall.copy(
              fontWeight = FontWeight.Bold,
              letterSpacing = 0.8.sp
            )
          )
          
          Spacer(modifier = Modifier.height(6.dp))

          // Title
          Text(
            text = event.title,
            color = Color.White,
            style = MaterialTheme.typography.titleMedium.copy(
              fontWeight = FontWeight.SemiBold,
              lineHeight = 22.sp
            ),
            maxLines = 2
          )

          Spacer(modifier = Modifier.height(8.dp))

          // Venue and Pricing Grouped Cleanly
          Text(
            text = "${event.venue}  •  ${event.price}",
            color = Color.White.copy(alpha = 0.65f),
            style = MaterialTheme.typography.bodySmall.copy(
              fontWeight = FontWeight.Medium
            )
          )
        }

        // Right Column: A singular, elegant circular button instead of chaotic scattered ones
        androidx.compose.material3.Surface(
          onClick = { onEventClick(event) },
          shape = CircleShape,
          color = Color.White,
          modifier = Modifier
            .padding(start = 12.dp)
            .size(46.dp),
          shadowElevation = 2.dp
        ) {
          Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier.fillMaxSize()
          ) {
            Icon(
              imageVector = Icons.Default.PlayArrow, // Clean, high-contrast action icon
              contentDescription = "Watch Promo",
              tint = Color.Black,
              modifier = Modifier.size(20.dp)
            )
          }
        }
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

        // --- THE BLUE-TO-WHITE GRADIENT OVERLAY NUMBER ---
        Text(
          text = (index + 1).toString(),
          style = androidx.compose.ui.text.TextStyle(
            fontSize = 95.sp,
            fontWeight = FontWeight.Black,
            brush = Brush.verticalGradient(
              colors = listOf(
                Color(0xFF56B4E9), // Bright premium sky blue at the top
                Color(0xFFFFFFFF)  // Fades down cleanly into pure white at the baseline
              )
            )
          ),
          modifier = Modifier
            .align(Alignment.BottomStart)
            .offset(x = (-24).dp, y = 14.dp) // Perfect center-gap bleed alignment
            .zIndex(5f) // Keeps it layered firmly over adjacent layout containers
        )
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

    // The beautiful luminous blue gradient
    val activeGradient = Brush.verticalGradient(
        colors = listOf(
            Color(0xFF38BDF8), // Top luminous blue-sky tint
            Color(0xFF0284C7)  // Bottom deeper premium blue
        )
    )

    BoxWithConstraints(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = 24.dp, vertical = 12.dp)
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

