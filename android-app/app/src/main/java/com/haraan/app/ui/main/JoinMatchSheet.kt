package com.haraan.app.ui.main

import android.content.Intent
import android.net.Uri
import android.os.Build
import android.view.HapticFeedbackConstants
import android.view.View
import android.widget.Toast
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.animation.slideOutVertically
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.ContentPaste
import androidx.compose.material.icons.outlined.QrCodeScanner
import androidx.compose.material.icons.outlined.Videocam
import androidx.compose.material.icons.outlined.GroupAdd
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.codescanner.GmsBarcodeScannerOptions
import com.google.mlkit.vision.codescanner.GmsBarcodeScanning
import com.haraan.app.camera.CameraDeviceActivity
import com.haraan.app.data.CodeJoinResult
import com.haraan.app.data.CodeMatchPreview
import com.haraan.app.data.MatchRepository
import com.haraan.app.data.TokenStore
import com.haraan.app.ui.theme.HaraanColors
import kotlinx.coroutines.launch

/*
 * The ActionBoard header's one door for joining someone else's match.
 *
 * Two codes reach this sheet and they do different things:
 *   • `HRN-7K2Q` — a private match's share code. Opens the match to follow the score.
 *   • `H7XH4ZH4WJ` — a camera pairing code from the scorer's Devices sheet. Turns this
 *     phone into that match's second camera (CameraDeviceActivity).
 *
 * The person holding the phone should not have to know which is which. The sheet reads
 * whatever they type, paste or scan — a bare code, the WhatsApp share sentence, a camera
 * link — and the matching tile lights up before they commit.
 */

internal sealed interface JoinTarget {
  val code: String

  data class PrivateMatch(override val code: String) : JoinTarget
  data class Camera(override val code: String) : JoinTarget
}

// Mirrors MatchDevice::freshToken() — no 0/1/I/L/O, ten characters.
private val PairTokenRegex = Regex("^[2-9A-HJKMNP-Z]{10}$")
private val CameraLinkRegex = Regex("(?i)(?:haraan://camera/|/join/camera/)([A-Z0-9]{6,32})")
private val PrivateCodeRegex = Regex("(?i)\\bHRN-?([A-Z0-9]{4})\\b")

/** What [raw] points at, or null while it doesn't point at anything yet. */
internal fun parseJoinInput(raw: String): JoinTarget? {
  val text = raw.trim()
  if (text.isEmpty()) return null
  CameraLinkRegex.find(text)?.let { return JoinTarget.Camera(it.groupValues[1].uppercase()) }
  PrivateCodeRegex.find(text)?.let { return JoinTarget.PrivateMatch("HRN-" + it.groupValues[1].uppercase()) }

  val bare = text.uppercase()
  if (bare.any { !it.isLetterOrDigit() && it != '-' } || bare.length !in 4..16) return null
  if (PairTokenRegex.matches(bare)) return JoinTarget.Camera(bare)
  // Older and seeded private matches carry codes outside the HRN- shape; the server is
  // the judge of those, as it was before this sheet.
  return if (bare.startsWith("HRN")) null else JoinTarget.PrivateMatch(bare)
}

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Hairline = Color(0xFFE2E8F0)
private val Field = Color(0xFFF8FAFC)
private val Danger = Color(0xFFDC2626)
private val Accent = HaraanColors.EventsBlue

private fun View.tick() = performHapticFeedback(HapticFeedbackConstants.CLOCK_TICK)
private fun View.confirm() = performHapticFeedback(
  if (Build.VERSION.SDK_INT >= 30) HapticFeedbackConstants.CONFIRM else HapticFeedbackConstants.LONG_PRESS,
)
private fun View.reject() = performHapticFeedback(
  if (Build.VERSION.SDK_INT >= 30) HapticFeedbackConstants.REJECT else HapticFeedbackConstants.LONG_PRESS,
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
internal fun JoinMatchSheet(
  repository: MatchRepository,
  onDismiss: () -> Unit,
  /** Open the match (by its share code) — after joining it, or to only watch. */
  onOpenMatch: (String) -> Unit,
  /** Not signed in / no player profile: the caller runs its gate, then reopens us with [code]. */
  onNeedsAccount: (code: String) -> Unit,
  initialCode: String = "",
) {
  val context = LocalContext.current
  val view = LocalView.current
  val clipboard = LocalClipboardManager.current
  val scope = rememberCoroutineScope()
  val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)

  var input by remember { mutableStateOf(initialCode) }
  var error by remember { mutableStateOf<String?>(null) }
  var busy by remember { mutableStateOf(false) }
  // Step two of a private code: the match it opened, waiting for a side to be picked.
  var picking by remember { mutableStateOf<CodeMatchPreview?>(null) }
  val target = remember(input) { parseJoinInput(input) }
  val shake = remember { Animatable(0f) }

  // A small tick the moment a code becomes recognisable — the phone agreeing with you
  // before you press anything.
  var lastKind by remember { mutableStateOf<Class<*>?>(null) }
  LaunchedEffect(target?.javaClass) {
    val kind = target?.javaClass
    if (kind != null && kind != lastKind) view.tick()
    lastKind = kind
  }

  fun fail(message: String) {
    error = message
    view.reject()
    scope.launch {
      shake.snapTo(0f)
      for (x in listOf(14f, -12f, 9f, -6f, 3f, 0f)) shake.animateTo(x, tween(45))
    }
  }

  fun commit(t: JoinTarget?) {
    when (t) {
      null -> fail(
        if (input.isBlank()) "Type, paste or scan a code first."
        else "That doesn't look like a match or camera code.",
      )
      is JoinTarget.PrivateMatch -> {
        if (busy) return
        view.tick()
        busy = true
        error = null
        scope.launch {
          val token = TokenStore.getSignedInToken(context)
          val result = if (token == null) CodeJoinResult.NeedsLogin else repository.previewPrivateMatch(token, t.code)
          busy = false
          when (result) {
            is CodeJoinResult.Ok -> {
              if (result.match.finished) fail("That match has already finished.")
              else picking = result.match
            }
            CodeJoinResult.NeedsLogin, CodeJoinResult.NeedsProfile -> onNeedsAccount(t.code)
            is CodeJoinResult.Failed -> fail(result.message)
          }
        }
      }
      is JoinTarget.Camera -> {
        view.confirm()
        val intent = Intent(Intent.ACTION_VIEW, Uri.parse("haraan://camera/${t.code}"))
          .setClass(context, CameraDeviceActivity::class.java)
        context.startActivity(intent)
        onDismiss()
      }
    }
  }

  // Pasted or scanned text is often a whole sentence or a link; keep only the code so
  // the field shows what will actually be used.
  fun accept(raw: String, source: String) {
    val parsed = parseJoinInput(raw)
    if (parsed == null) {
      fail("The $source didn't contain a Haraan match or camera code.")
      return
    }
    error = null
    input = parsed.code
  }

  ModalBottomSheet(
    onDismissRequest = onDismiss,
    sheetState = sheetState,
    containerColor = Color.White,
    shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp),
  ) {
    Column(
      Modifier
        .fillMaxWidth()
        .navigationBarsPadding()
        .imePadding()
        .padding(horizontal = 20.dp)
        .padding(bottom = 18.dp),
    ) {
     AnimatedContent(
      targetState = picking,
      transitionSpec = {
        val forward = targetState != null
        (fadeIn(tween(220)) + slideInHorizontally(tween(260)) { if (forward) it / 4 else -it / 4 }) togetherWith
          (fadeOut(tween(120)) + slideOutHorizontally(tween(200)) { if (forward) -it / 4 else it / 4 })
      },
      label = "joinStep",
     ) { match ->
      if (match != null) {
        TeamPickStep(
          match = match,
          code = input,
          repository = repository,
          onBack = { view.tick(); picking = null },
          onOpen = { onOpenMatch(input) },
          onNeedsAccount = { onNeedsAccount(input) },
        )
        return@AnimatedContent
      }
      Column {
      Text("Join a match", color = Ink, fontSize = 22.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.4).sp)
      Spacer(Modifier.height(4.dp))
      Text(
        "Use the code from the scorer's phone — we'll work out which kind it is.",
        color = Muted,
        fontSize = 13.5.sp,
        lineHeight = 19.sp,
      )

      Spacer(Modifier.height(18.dp))
      Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        ModeTile(
          icon = Icons.Outlined.GroupAdd,
          title = "Play",
          caption = "Private match\nHRN-XXXX",
          lit = target is JoinTarget.PrivateMatch,
          modifier = Modifier.weight(1f),
          onClick = {
            view.tick()
            error = null
            if (input.isBlank()) input = "HRN-"
          },
        )
        ModeTile(
          icon = Icons.Outlined.Videocam,
          title = "Film",
          caption = "Second camera\n10-letter code",
          lit = target is JoinTarget.Camera,
          modifier = Modifier.weight(1f),
          onClick = {
            view.tick()
            error = null
            if (input == "HRN-") input = ""
          },
        )
      }

      Spacer(Modifier.height(16.dp))
      val fieldBorder by animateColorAsState(
        when {
          error != null -> Danger
          target != null -> Accent
          else -> Hairline
        },
        tween(180),
        label = "joinFieldBorder",
      )
      Box(
        Modifier
          .fillMaxWidth()
          .offset { IntOffset(shake.value.dp.roundToPx(), 0) }
          .clip(RoundedCornerShape(16.dp))
          .background(Field)
          .border(if (target != null || error != null) 1.5.dp else 1.dp, fieldBorder, RoundedCornerShape(16.dp))
          .padding(horizontal = 16.dp, vertical = 16.dp),
        contentAlignment = Alignment.Center,
      ) {
        if (input.isEmpty()) {
          Text(
            "HRN-7K2Q  or  H7XH4ZH4WJ",
            color = Color(0xFFB6C0CE),
            fontSize = 19.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 1.5.sp,
          )
        }
        BasicTextField(
          value = input,
          onValueChange = { next ->
            // A long paste lands here too — let it through the parser before filtering.
            val parsed = if (next.length > 16) parseJoinInput(next) else null
            input = parsed?.code
              ?: next.uppercase().filter { it.isLetterOrDigit() || it == '-' }.take(16)
            error = null
          },
          singleLine = true,
          textStyle = TextStyle(
            color = Ink,
            fontSize = 22.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = 3.sp,
            textAlign = TextAlign.Center,
          ),
          cursorBrush = SolidColor(Accent),
          keyboardOptions = KeyboardOptions(
            capitalization = KeyboardCapitalization.Characters,
            autoCorrectEnabled = false,
            keyboardType = KeyboardType.Ascii,
            imeAction = ImeAction.Go,
          ),
          keyboardActions = KeyboardActions(onGo = { commit(target) }),
          modifier = Modifier.fillMaxWidth(),
        )
      }

      // One line under the field that always says something true: the error, what the
      // code will do, or nothing yet.
      AnimatedContent(
        targetState = error ?: when (target) {
          is JoinTarget.PrivateMatch -> "Pick your team and play in this private match."
          is JoinTarget.Camera -> "This phone becomes the match's second camera."
          null -> ""
        },
        transitionSpec = {
          (fadeIn(tween(160)) + slideInVertically { -it / 3 }) togetherWith
            (fadeOut(tween(100)) + slideOutVertically { it / 3 })
        },
        label = "joinHint",
      ) { line ->
        Text(
          line,
          color = if (error != null) Danger else Muted,
          fontSize = 12.5.sp,
          fontWeight = if (error != null) FontWeight.SemiBold else FontWeight.Medium,
          lineHeight = 17.sp,
          maxLines = 1,
          modifier = Modifier
            .fillMaxWidth()
            .padding(top = 8.dp, start = 4.dp)
            .height(22.dp),
        )
      }

      Spacer(Modifier.height(10.dp))
      Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        SoftAction(Icons.Outlined.ContentPaste, "Paste", Modifier.weight(1f)) {
          view.tick()
          val text = clipboard.getText()?.text.orEmpty()
          if (text.isBlank()) fail("Your clipboard is empty.") else accept(text, "clipboard")
        }
        SoftAction(Icons.Outlined.QrCodeScanner, "Scan QR", Modifier.weight(1f)) {
          view.tick()
          val options = GmsBarcodeScannerOptions.Builder()
            .setBarcodeFormats(Barcode.FORMAT_QR_CODE)
            .enableAutoZoom()
            .build()
          GmsBarcodeScanning.getClient(context, options).startScan()
            .addOnSuccessListener { barcode ->
              val raw = barcode.rawValue.orEmpty()
              val parsed = parseJoinInput(raw)
              if (parsed == null) {
                fail("That QR isn't a Haraan match or camera code.")
              } else {
                input = parsed.code
                error = null
                // A scan is already a deliberate act — don't make them press again.
                commit(parsed)
              }
            }
            .addOnFailureListener {
              Toast.makeText(context, "Scanner isn't available right now — type the code instead.", Toast.LENGTH_SHORT).show()
            }
        }
      }

      Spacer(Modifier.height(16.dp))
      PrimaryJoinButton(
        label = when (target) {
          is JoinTarget.PrivateMatch -> if (busy) "Finding the match…" else "Continue"
          is JoinTarget.Camera -> "Join as camera"
          null -> "Continue"
        },
        ready = target != null && !busy,
        onClick = { commit(target) },
      )
      }
     }
    }
  }
}

/**
 * Step two for a private code: which side are you playing for?
 *
 * Holding the code is the invitation, so there is no request for the owner to approve —
 * pick a team, tap once, and you are in its squad (and the match opens).
 */
@Composable
private fun TeamPickStep(
  match: CodeMatchPreview,
  code: String,
  repository: MatchRepository,
  onBack: () -> Unit,
  onOpen: () -> Unit,
  onNeedsAccount: () -> Unit,
) {
  val context = LocalContext.current
  val view = LocalView.current
  val scope = rememberCoroutineScope()
  val alreadyIn = match.isOwner || match.mySide != null
  // Default to the lighter side, the same rule the owner's accept uses.
  var side by remember { mutableStateOf(match.mySide ?: if (match.homeCount <= match.awayCount) "home" else "away") }
  var busy by remember { mutableStateOf(false) }
  var error by remember { mutableStateOf<String?>(null) }
  val teamName = if (side == "home") match.home else match.away

  Column {
    Row(verticalAlignment = Alignment.CenterVertically) {
      Box(
        Modifier
          .size(36.dp)
          .clip(RoundedCornerShape(12.dp))
          .background(Field)
          .border(1.dp, Hairline, RoundedCornerShape(12.dp))
          .clickable(role = Role.Button, onClickLabel = "Back", onClick = onBack),
        contentAlignment = Alignment.Center,
      ) {
        Icon(Icons.AutoMirrored.Outlined.ArrowBack, contentDescription = "Back", tint = Ink, modifier = Modifier.size(18.dp))
      }
      Spacer(Modifier.width(12.dp))
      Column(Modifier.weight(1f)) {
        Text(
          if (alreadyIn) "You're in this match" else "Pick your team",
          color = Ink,
          fontSize = 21.sp,
          fontWeight = FontWeight.ExtraBold,
          letterSpacing = (-0.4).sp,
        )
        Text(
          listOf(match.sport.replaceFirstChar { it.uppercase() }, match.venue.takeIf { it.isNotBlank() }, code)
            .filterNotNull().joinToString(" · "),
          color = Muted,
          fontSize = 12.5.sp,
          maxLines = 1,
        )
      }
    }

    Spacer(Modifier.height(18.dp))
    Row(horizontalArrangement = Arrangement.spacedBy(10.dp), verticalAlignment = Alignment.CenterVertically) {
      TeamTile(
        name = match.home,
        players = match.homeCount,
        chosen = side == "home",
        mine = match.mySide == "home",
        enabled = !alreadyIn,
        modifier = Modifier.weight(1f),
      ) { if (side != "home") { view.tick(); side = "home"; error = null } }
      Text("vs", color = Muted, fontSize = 12.sp, fontWeight = FontWeight.Bold)
      TeamTile(
        name = match.away,
        players = match.awayCount,
        chosen = side == "away",
        mine = match.mySide == "away",
        enabled = !alreadyIn,
        modifier = Modifier.weight(1f),
      ) { if (side != "away") { view.tick(); side = "away"; error = null } }
    }

    AnimatedContent(
      targetState = error ?: when {
        match.isOwner -> "It's your match — you're already the scorer."
        match.mySide != null -> "You're already on ${if (match.mySide == "home") match.home else match.away}."
        else -> "You'll be added to $teamName's squad. The scorer sees you straight away."
      },
      transitionSpec = { fadeIn(tween(160)) togetherWith fadeOut(tween(100)) },
      label = "teamPickHint",
    ) { line ->
      Text(
        line,
        color = if (error != null) Danger else Muted,
        fontSize = 12.5.sp,
        lineHeight = 17.sp,
        fontWeight = if (error != null) FontWeight.SemiBold else FontWeight.Medium,
        modifier = Modifier
          .fillMaxWidth()
          .padding(top = 12.dp, start = 4.dp, end = 4.dp),
      )
    }

    Spacer(Modifier.height(18.dp))
    PrimaryJoinButton(
      label = when {
        alreadyIn -> "Open match"
        busy -> "Joining…"
        else -> "Join $teamName"
      },
      ready = !busy,
      onClick = {
        if (busy) return@PrimaryJoinButton
        if (alreadyIn) { view.confirm(); onOpen(); return@PrimaryJoinButton }
        busy = true
        error = null
        scope.launch {
          val token = TokenStore.getSignedInToken(context)
          val result = if (token == null) CodeJoinResult.NeedsLogin else repository.joinPrivateMatch(token, code, side)
          busy = false
          when (result) {
            is CodeJoinResult.Ok -> {
              view.confirm()
              Toast.makeText(context, "You're in — playing for $teamName", Toast.LENGTH_SHORT).show()
              onOpen()
            }
            CodeJoinResult.NeedsLogin, CodeJoinResult.NeedsProfile -> onNeedsAccount()
            is CodeJoinResult.Failed -> { view.reject(); error = result.message }
          }
        }
      },
    )
    if (!alreadyIn) {
      Spacer(Modifier.height(6.dp))
      Text(
        "Only follow the score",
        color = Accent,
        fontSize = 13.5.sp,
        fontWeight = FontWeight.SemiBold,
        textAlign = TextAlign.Center,
        modifier = Modifier
          .fillMaxWidth()
          .clip(RoundedCornerShape(12.dp))
          .clickable(role = Role.Button) { view.tick(); onOpen() }
          .padding(vertical = 12.dp),
      )
    }
  }
}

@Composable
private fun TeamTile(
  name: String,
  players: Int,
  chosen: Boolean,
  mine: Boolean,
  enabled: Boolean,
  modifier: Modifier = Modifier,
  onClick: () -> Unit,
) {
  val interaction = remember { MutableInteractionSource() }
  val pressed by interaction.collectIsPressedAsState()
  val scale by animateFloatAsState(
    when {
      pressed -> 0.95f
      chosen -> 1.03f
      else -> 1f
    },
    spring(dampingRatio = Spring.DampingRatioMediumBouncy, stiffness = Spring.StiffnessMedium),
    label = "teamTileScale",
  )
  val border by animateColorAsState(if (chosen) Accent else Hairline, tween(180), label = "teamTileBorder")
  val wash by animateColorAsState(if (chosen) Accent.copy(alpha = 0.07f) else Color.White, tween(180), label = "teamTileWash")
  val crest by animateColorAsState(if (chosen) Accent else Accent.copy(alpha = 0.10f), tween(180), label = "teamTileCrest")
  val crestInk by animateColorAsState(if (chosen) Color.White else Accent, tween(180), label = "teamTileCrestInk")
  val initials = name.trim().split(Regex("\\s+")).filter { it.isNotBlank() }
    .let { words -> if (words.size >= 2) "${words[0][0]}${words[1][0]}" else name.trim().take(2) }
    .uppercase().ifBlank { "?" }

  Column(
    modifier
      .graphicsLayer { scaleX = scale; scaleY = scale }
      .clip(RoundedCornerShape(18.dp))
      .background(wash)
      .border(if (chosen) 1.5.dp else 1.dp, border, RoundedCornerShape(18.dp))
      .clickable(
        interactionSource = interaction,
        indication = null,
        enabled = enabled,
        role = Role.RadioButton,
        onClick = onClick,
      )
      .padding(vertical = 16.dp, horizontal = 12.dp),
    horizontalAlignment = Alignment.CenterHorizontally,
  ) {
    Box(
      Modifier
        .size(48.dp)
        .clip(RoundedCornerShape(16.dp))
        .background(crest),
      contentAlignment = Alignment.Center,
    ) {
      Text(initials, color = crestInk, fontSize = 17.sp, fontWeight = FontWeight.ExtraBold)
    }
    Spacer(Modifier.height(10.dp))
    Text(name, color = Ink, fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, maxLines = 1, textAlign = TextAlign.Center)
    Spacer(Modifier.height(2.dp))
    Text(
      when {
        mine -> "You're here"
        players == 1 -> "1 player"
        else -> "$players players"
      },
      color = if (mine) Accent else Muted,
      fontSize = 12.sp,
      fontWeight = if (mine) FontWeight.Bold else FontWeight.Medium,
    )
  }
}

@Composable
private fun ModeTile(
  icon: ImageVector,
  title: String,
  caption: String,
  lit: Boolean,
  modifier: Modifier = Modifier,
  onClick: () -> Unit,
) {
  val interaction = remember { MutableInteractionSource() }
  val pressed by interaction.collectIsPressedAsState()
  val scale by animateFloatAsState(
    when {
      pressed -> 0.95f
      lit -> 1.02f
      else -> 1f
    },
    spring(dampingRatio = Spring.DampingRatioMediumBouncy, stiffness = Spring.StiffnessMedium),
    label = "modeTileScale",
  )
  val border by animateColorAsState(if (lit) Accent else Hairline, tween(180), label = "modeTileBorder")
  val wash by animateColorAsState(if (lit) Accent.copy(alpha = 0.07f) else Color.White, tween(180), label = "modeTileWash")
  val chip by animateColorAsState(if (lit) Accent else Accent.copy(alpha = 0.10f), tween(180), label = "modeTileChip")
  val glyph by animateColorAsState(if (lit) Color.White else Accent, tween(180), label = "modeTileGlyph")

  Column(
    modifier
      .graphicsLayer { scaleX = scale; scaleY = scale }
      .clip(RoundedCornerShape(18.dp))
      .background(wash)
      .border(if (lit) 1.5.dp else 1.dp, border, RoundedCornerShape(18.dp))
      .clickable(interactionSource = interaction, indication = null, role = Role.Button, onClick = onClick)
      .padding(14.dp),
  ) {
    Box(
      Modifier
        .size(34.dp)
        .clip(RoundedCornerShape(11.dp))
        .background(chip),
      contentAlignment = Alignment.Center,
    ) {
      Icon(icon, contentDescription = null, tint = glyph, modifier = Modifier.size(19.dp))
    }
    Spacer(Modifier.height(10.dp))
    Text(title, color = Ink, fontSize = 15.sp, fontWeight = FontWeight.ExtraBold)
    Spacer(Modifier.height(2.dp))
    Text(caption, color = Muted, fontSize = 11.5.sp, lineHeight = 15.sp)
  }
}

@Composable
private fun SoftAction(icon: ImageVector, label: String, modifier: Modifier = Modifier, onClick: () -> Unit) {
  val interaction = remember { MutableInteractionSource() }
  val pressed by interaction.collectIsPressedAsState()
  val scale by animateFloatAsState(
    if (pressed) 0.95f else 1f,
    spring(dampingRatio = 0.55f, stiffness = 900f),
    label = "softActionScale",
  )
  Row(
    modifier
      .height(44.dp)
      .graphicsLayer { scaleX = scale; scaleY = scale }
      .clip(RoundedCornerShape(14.dp))
      .background(if (pressed) Accent.copy(alpha = 0.14f) else Accent.copy(alpha = 0.08f))
      .clickable(interactionSource = interaction, indication = null, role = Role.Button, onClick = onClick),
    horizontalArrangement = Arrangement.Center,
    verticalAlignment = Alignment.CenterVertically,
  ) {
    Icon(icon, contentDescription = null, tint = Accent, modifier = Modifier.size(18.dp))
    Spacer(Modifier.width(8.dp))
    Text(label, color = Accent, fontSize = 14.sp, fontWeight = FontWeight.Bold)
  }
}

@Composable
private fun PrimaryJoinButton(label: String, ready: Boolean, onClick: () -> Unit) {
  val interaction = remember { MutableInteractionSource() }
  val pressed by interaction.collectIsPressedAsState()
  val scale by animateFloatAsState(
    if (pressed) 0.97f else 1f,
    spring(dampingRatio = 0.6f, stiffness = 800f),
    label = "joinButtonScale",
  )
  val lift by animateFloatAsState(if (ready && !pressed) 8f else if (ready) 2f else 0f, tween(160), label = "joinButtonLift")
  val fill by animateColorAsState(if (ready) Accent else Color(0xFFCBD5E1), tween(200), label = "joinButtonFill")

  // Stays tappable while not ready: pressing it then explains what's missing (with a
  // shake), which is kinder than a grey button that silently does nothing.
  Box(
    Modifier
      .fillMaxWidth()
      .height(54.dp)
      .graphicsLayer { scaleX = scale; scaleY = scale }
      .shadow(lift.dp, RoundedCornerShape(16.dp), clip = false, ambientColor = Accent.copy(alpha = 0.25f), spotColor = Accent.copy(alpha = 0.45f))
      .clip(RoundedCornerShape(16.dp))
      .background(fill)
      .clickable(interactionSource = interaction, indication = null, role = Role.Button, onClick = onClick),
    contentAlignment = Alignment.Center,
  ) {
    AnimatedContent(
      targetState = label,
      transitionSpec = {
        (fadeIn(tween(150)) + slideInVertically { it / 2 }) togetherWith
          (fadeOut(tween(90)) + slideOutVertically { -it / 2 })
      },
      label = "joinButtonLabel",
    ) { text ->
      Text(text, color = Color.White, fontSize = 16.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = 0.2.sp)
    }
  }
}

/** The header's entry point — a white key with a blue scan glyph that presses in. */
@Composable
internal fun JoinHeaderButton(onClick: () -> Unit) {
  val view = LocalView.current
  val interaction = remember { MutableInteractionSource() }
  val pressed by interaction.collectIsPressedAsState()
  val scale by animateFloatAsState(
    if (pressed) 0.88f else 1f,
    spring(dampingRatio = 0.5f, stiffness = 900f),
    label = "joinHeaderScale",
  )
  val lift by animateFloatAsState(if (pressed) 0.5f else 3f, tween(110), label = "joinHeaderLift")
  Box(
    Modifier
      .size(38.dp)
      .graphicsLayer { scaleX = scale; scaleY = scale }
      .shadow(lift.dp, RoundedCornerShape(12.dp), clip = false, ambientColor = Color.Black.copy(alpha = 0.05f), spotColor = Accent.copy(alpha = 0.22f))
      .clip(RoundedCornerShape(12.dp))
      .background(if (pressed) Accent.copy(alpha = 0.10f) else Color.White)
      .border(1.dp, if (pressed) Accent.copy(alpha = 0.45f) else Hairline, RoundedCornerShape(12.dp))
      .clickable(
        interactionSource = interaction,
        indication = null,
        role = Role.Button,
        onClickLabel = "Join a match or camera",
      ) {
        view.tick()
        onClick()
      },
    contentAlignment = Alignment.Center,
  ) {
    Icon(
      imageVector = Icons.Outlined.QrCodeScanner,
      contentDescription = "Join a match or camera",
      tint = Accent,
      modifier = Modifier.size(19.dp),
    )
  }
}
