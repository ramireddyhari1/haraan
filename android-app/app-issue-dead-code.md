# App Issue — Dead Code Removed

Date: 2026-07-06

Unused (unreferenced) `private` composables/functions excised from the Android app to reduce clutter. Archived here verbatim so they can be restored if any turn out to be needed. Each was confirmed unreferenced (single occurrence, file-private).

---

### ui/main/MainScreen.kt :: SegmentTabs  (orig lines 359-384)

```kotlin
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
```

### ui/main/MainScreen.kt :: TournamentSection  (orig lines 386-401)

```kotlin
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
```

### ui/main/MainScreen.kt :: CustomBottomNav  (orig lines 473-550)

```kotlin
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
```

### ui/main/MainScreen.kt :: CustomCategoryCard  (orig lines 1387-1467)

```kotlin
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
      color = if (selected) LightAccentBlue.copy(alpha = 0.6f) else Color(0xFFE2E8F0)
    ),
    elevation = CardDefaults.cardElevation(defaultElevation = if (selected) 3.dp else 0.dp)
  ) {
    val boxModifier = if (selected) {
      Modifier
        .fillMaxSize()
        .background(
          Brush.verticalGradient(
            colors = listOf(
              LightAccentBlue.copy(alpha = 0.60f), // Onwards blue color
              Color.White                          // Fading into pure white
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
```

### ui/main/MainScreen.kt :: CategoryChip  (orig lines 1648-1663)

```kotlin
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
```

### ui/main/MainScreen.kt :: InteractiveRowItem  (orig lines 1665-1686)

```kotlin
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
```

### ui/main/MainScreen.kt :: InfoQuestionBox  (orig lines 1688-1699)

```kotlin
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
```

### ui/main/MainScreen.kt :: PerformerCard  (orig lines 1701-1739)

```kotlin
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
```

### ui/main/MainScreen.kt :: GuidelineRowItem  (orig lines 1741-1755)

```kotlin
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
```

### ui/main/MainScreen.kt :: CategoryCard  (orig lines 2882-2943)

```kotlin
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
      containerColor = if (isSelected) Color(0xFF1B5E20).copy(alpha = 0.08f) else Color.White
    ),
    border = BorderStroke(
      width = 1.dp,
      color = if (isSelected) Color(0xFF1B5E20) else Color(0xFFE2E8F0)
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
              color = if (isSelected) Color(0xFF1B5E20).copy(alpha = 0.15f) else Color(0xFFF1F5F9),
              shape = RoundedCornerShape(10.dp)
            ),
          contentAlignment = Alignment.Center
        ) {
          Icon(
            imageVector = icon,
            contentDescription = title,
            tint = if (isSelected) Color(0xFF1B5E20) else Color(0xFF64748B),
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
```

### ui/main/MainScreen.kt :: CrexSearchBar  (orig lines 4431-4466)

```kotlin
@Composable
private fun CrexSearchBar(modifier: Modifier = Modifier) {
  Row(
    modifier = modifier
      .height(38.dp)
      .shadow(
        elevation = 3.dp,
        shape = RoundedCornerShape(22.dp),
        ambientColor = Color.Black.copy(alpha = 0.04f),
        spotColor = Color.Black.copy(alpha = 0.08f),
      )
      .clip(RoundedCornerShape(22.dp))
      .background(Color.White)
      .border(1.dp, Color(0xFFE9ECF0), RoundedCornerShape(22.dp))
      .padding(horizontal = 12.dp),
    verticalAlignment = Alignment.CenterVertically
  ) {
    Icon(
      imageVector = Icons.Default.Search,
      contentDescription = null,
      tint = Color(0xFFB0B7C3),
      modifier = Modifier.size(17.dp)
    )

    Spacer(modifier = Modifier.width(7.dp))

    Text(
      text = "Search players, teams",
      color = Color(0xFFB0B7C3),
      fontSize = 13.5.sp,
      fontWeight = FontWeight.Normal,
      maxLines = 1,
      overflow = TextOverflow.Ellipsis,
    )
  }
}
```

### ui/main/MainScreen.kt :: CrexTabsSection  (orig lines 4485-4577)

```kotlin
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
```

### ui/main/MainScreen.kt :: StyledSecondaryStats  (orig lines 4609-4640)

```kotlin
@Composable
private fun StyledSecondaryStats(secondaryStat: String) {
    val parts = secondaryStat.split(Regex("\\s{2,}"))
    Row(
        modifier = Modifier.padding(top = 3.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        parts.forEach { part ->
            val tokens = part.trim().split(Regex("\\s+"))
            if (tokens.size >= 2) {
                val first = tokens[0]
                val second = tokens.subList(1, tokens.size).joinToString(" ")
                val isFirstValue = first.firstOrNull()?.isDigit() == true
                val label = if (isFirstValue) second else first
                val value = if (isFirstValue) first else second
                Row {
                    if (isFirstValue) {
                        Text(text = value, fontSize = 10.5.sp, fontWeight = FontWeight.SemiBold, color = T.Text2)
                        Text(text = " ", fontSize = 10.5.sp)
                        Text(text = label, fontSize = 10.5.sp, fontWeight = FontWeight.Medium, color = T.Text3)
                    } else {
                        Text(text = label, fontSize = 10.5.sp, fontWeight = FontWeight.Medium, color = T.Text3)
                        Text(text = " ", fontSize = 10.5.sp)
                        Text(text = value, fontSize = 10.5.sp, fontWeight = FontWeight.SemiBold, color = T.Text2)
                    }
                }
            } else if (part.isNotEmpty()) {
                Text(text = part, fontSize = 10.5.sp, fontWeight = FontWeight.Medium, color = T.Text3)
            }
        }
    }
}
```

### ui/main/MainScreen.kt :: CrexTeamScore_Unused  (orig lines 7288-7330)

```kotlin
@Composable
private fun CrexTeamScore_Unused(
  team: String,
  logoUrl: String,
  score: String,
  overs: String
) {
  Row(
    modifier = Modifier.fillMaxWidth(),
    verticalAlignment = Alignment.CenterVertically,
  ) {
    TeamLogo(team = team, logoUrl = logoUrl, modifier = Modifier.size(32.dp))

    Spacer(modifier = Modifier.width(12.dp))

    Text(
      text = team,
      color = LightPrimaryText,
      fontSize = 17.sp,
      fontWeight = FontWeight.ExtraBold,
      maxLines = 1,
      modifier = Modifier.weight(1f),
    )

    Column(horizontalAlignment = Alignment.End) {
      Text(
        text = score,
        color = LightPrimaryText,
        fontSize = 17.sp,
        fontWeight = FontWeight.Bold,
        letterSpacing = (-0.3).sp,
      )
      if (overs.isNotEmpty()) {
        Text(
          text = overs,
          color = LightSecondaryText.copy(alpha = 0.75f),
          fontSize = 11.sp,
          fontWeight = FontWeight.Medium,
        )
      }
    }
  }
}
```

### ui/main/MainScreen.kt :: CrexBottomNavItem  (orig lines 7557-7593)

```kotlin
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
```

### ui/main/MainScreen.kt :: CrexBottomNavCenterItem  (orig lines 7595-7623)

```kotlin
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
```

### ui/main/MainScreen.kt :: LeaderboardTabPanel  (orig lines 7625-7699)

```kotlin
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
```

### ui/main/MainScreen.kt :: StatPill  (orig lines 7701-7724)

```kotlin
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
```

### ui/main/MainScreen.kt :: TournamentsTabPanel  (orig lines 7728-7789)

```kotlin
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
```

### ui/main/MainScreen.kt :: LocalTalentsTabPanel  (orig lines 7793-7867)

```kotlin
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
```

### ui/main/MainScreen.kt :: DistrictStatsTabPanel  (orig lines 7871-7925)

```kotlin
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
```

### ui/main/MainScreen.kt :: GreetingStatPill  (orig lines 7929-7962)

```kotlin
@Composable
private fun GreetingStatPill(
  icon: ImageVector,
  value: String,
  label: String,
  tint: Color
) {
  Row(
    modifier = Modifier
      .clip(RoundedCornerShape(50))
      .background(tint.copy(alpha = 0.08f))
      .border(BorderStroke(1.dp, tint.copy(alpha = 0.16f)), RoundedCornerShape(50))
      .padding(horizontal = 12.dp, vertical = 7.dp),
    verticalAlignment = Alignment.CenterVertically,
    horizontalArrangement = Arrangement.spacedBy(6.dp)
  ) {
    Icon(
      imageVector = icon,
      contentDescription = null,
      tint = tint,
      modifier = Modifier.size(14.dp)
    )
    Text(
      text = value,
      style = HaraanTypography.LabelSmall.copy(fontSize = 12.5.sp, fontWeight = FontWeight.ExtraBold),
      color = HaraanColors.TextPrimary
    )
    Text(
      text = label,
      style = HaraanTypography.BodyMedium.copy(fontSize = 12.sp),
      color = HaraanColors.TextSecondary
    )
  }
}
```

### ui/main/MainScreen.kt :: QuickActionChip  (orig lines 8560-8586)

```kotlin
@Composable
private fun QuickActionChip(
  label: String,
  icon: ImageVector,
  onClick: () -> Unit
) {
  Surface(
    onClick = onClick,
    shape = RoundedCornerShape(HaraanRadius.Medium),
    color = Color.White.copy(alpha = 0.15f),
    border = BorderStroke(1.dp, Color.White.copy(alpha = 0.2f)),
    modifier = Modifier.height(38.dp)
  ) {
    Row(
      modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
      verticalAlignment = Alignment.CenterVertically,
      horizontalArrangement = Arrangement.spacedBy(6.dp)
    ) {
      Icon(imageVector = icon, contentDescription = null, tint = Color.White, modifier = Modifier.size(15.dp))
      Text(
        text = label,
        color = Color.White,
        style = HaraanTypography.BodyMedium.copy(fontWeight = FontWeight.Bold, fontSize = 12.sp)
      )
    }
  }
}
```

### ui/matches/CrexUI.kt :: BatsmanMini  (orig lines 490-505)

```kotlin
@Composable
private fun BatsmanMini(name: String, runs: Int, balls: Int, sr: Float, isStriker: Boolean) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
        if (isStriker) {
            Box(modifier = Modifier.size(6.dp).clip(CircleShape).background(CrexColors.AccentGreen))
        }
        Column {
            Text(
                name + if (isStriker) " 🏏" else "",
                color = if (isStriker) CrexColors.TextPrimary else CrexColors.TextSecondary,
                fontSize = 12.sp, fontWeight = FontWeight.SemiBold
            )
            Text("$runs ($balls) • SR $sr", color = CrexColors.TextMuted, fontSize = 10.sp)
        }
    }
}
```

### ui/matches/CrexUI.kt :: BallDot  (orig lines 507-526)

```kotlin
@Composable
private fun BallDot(label: String) {
    val bg = when (label) {
        "6" -> CrexColors.SixBall
        "4" -> CrexColors.FourBall
        "W" -> CrexColors.WicketBall
        "•" -> CrexColors.DotBall
        else -> CrexColors.NormalBall
    }
    Box(
        modifier = Modifier
            .size(28.dp)
            .clip(CircleShape)
            .background(bg.copy(alpha = 0.2f))
            .border(1.dp, bg, CircleShape),
        contentAlignment = Alignment.Center
    ) {
        Text(label, color = bg, fontSize = 10.sp, fontWeight = FontWeight.Bold)
    }
}
```

### ui/matches/tabs/LiveTab.kt :: OverSummaryStrip  (orig lines 92-114)

```kotlin
@Composable
private fun OverSummaryStrip(state: MatchUiState) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .horizontalScroll(rememberScrollState()),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(18.dp)
    ) {
        state.recentOvers.takeLast(4).forEach { over ->
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OverBallChip(over.balls.lastOrNull() ?: "•")
                Text(
                    buildAnnotatedString {
                        withStyle(SpanStyle(color = CrexColors.TextPrimary, fontWeight = FontWeight.Bold)) { append("= ${over.runs}") }
                    },
                    fontSize = 12.sp
                )
                Text("Over ${over.label}", color = CrexColors.TextMuted, fontSize = 12.sp)
            }
        }
    }
}
```
