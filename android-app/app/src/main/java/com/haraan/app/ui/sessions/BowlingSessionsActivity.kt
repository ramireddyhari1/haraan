package com.haraan.app.ui.sessions

import android.content.ContentUris
import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Environment
import android.provider.MediaStore
import android.util.Size
import android.view.HapticFeedbackConstants
import androidx.activity.ComponentActivity
import androidx.activity.compose.BackHandler
import androidx.activity.compose.setContent
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.GridItemSpan
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.outlined.DeleteOutline
import androidx.compose.material.icons.outlined.PlayArrow
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.FileProvider
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.repeatOnLifecycle
import com.haraan.app.camera.BowlingSessionStore
import com.haraan.app.camera.CameraDeviceActivity
import com.haraan.app.data.membership.BowlingSessionAccess
import com.haraan.app.data.membership.MembershipNav
import com.haraan.app.data.membership.PhoneFeatureAccess
import com.haraan.app.theme.ThannaTheme
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.withContext
import java.io.File
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/**
 * BOWLING SESSIONS (Hero): a place of their own for practice filmed with the LBW camera.
 *
 * "New session" asks for a name and which camera, then opens the camera with no match behind
 * it. Sessions whose camera is open now are under Live; the rest are History. Opening one shows
 * the tracked videos this phone saved for it — they live in the Gallery under
 * Movies/Haraan Sessions/<name>, so they are the member's files and nothing is uploaded.
 */
class BowlingSessionsActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            ThannaTheme {
                BowlingSessionsScreen(onExit = { finish() })
            }
        }
    }

    companion object {
        fun open(context: Context) {
            context.startActivity(Intent(context, BowlingSessionsActivity::class.java))
        }
    }
}

private val Ink = Color(0xFF0F172A)
private val Muted = Color(0xFF64748B)
private val Hairline = Color(0xFFE2E8F0)
private val Page = Color(0xFFF8FAFC)
private val Brand = Color(0xFF2563EB)
private val Rec = Color(0xFFDC2626)
private val HeroOnyx = Color(0xFF111111)
private val HeroGold = Color(0xFFE8C26A)

private fun android.view.View.tick() = performHapticFeedback(HapticFeedbackConstants.CLOCK_TICK)
private fun android.view.View.confirm() = performHapticFeedback(
    if (Build.VERSION.SDK_INT >= 30) HapticFeedbackConstants.CONFIRM else HapticFeedbackConstants.LONG_PRESS,
)

@Composable
private fun BowlingSessionsScreen(onExit: () -> Unit) {
    val context = LocalContext.current
    val view = LocalView.current
    val lifecycle = LocalLifecycleOwner.current

    var access by remember { mutableStateOf<PhoneFeatureAccess.State?>(null) }
    LaunchedEffect(Unit) { access = BowlingSessionAccess.check(context) }

    // Re-read whenever the screen comes back (the camera closed) and every few seconds while
    // shown, so a session drops out of Live on its own and the ball count keeps up.
    var sessions by remember { mutableStateOf(BowlingSessionStore.all(context)) }
    var now by remember { mutableStateOf(System.currentTimeMillis()) }
    LaunchedEffect(lifecycle) {
        lifecycle.lifecycle.repeatOnLifecycle(Lifecycle.State.RESUMED) {
            while (true) {
                sessions = BowlingSessionStore.all(context)
                now = System.currentTimeMillis()
                delay(5_000)
            }
        }
    }

    var creating by remember { mutableStateOf(false) }
    var openId by remember { mutableStateOf<String?>(null) }
    val opened = openId?.let { id -> sessions.firstOrNull { it.id == id } }
    BackHandler(enabled = opened != null) { openId = null }

    fun startCamera(id: String) {
        view.confirm()
        BowlingSessionStore.touch(context, id)
        CameraDeviceActivity.openBowlingSession(context, id)
    }

    Box(Modifier.fillMaxSize().background(Page)) {
        when (access) {
            null -> Unit
            PhoneFeatureAccess.State.ALLOWED -> AnimatedContent(
                targetState = opened,
                transitionSpec = { fadeIn(tween(200)) togetherWith fadeOut(tween(120)) },
                label = "sessionsPage",
            ) { session ->
                if (session == null) {
                    SessionList(
                        sessions = sessions,
                        now = now,
                        onBack = onExit,
                        onNew = { view.tick(); creating = true },
                        onOpen = { view.tick(); openId = it.id },
                        onCamera = { startCamera(it.id) },
                    )
                } else {
                    SessionDetail(
                        session = session,
                        live = session.isLive(now),
                        onBack = { view.tick(); openId = null },
                        onCamera = { startCamera(session.id) },
                        onDelete = {
                            BowlingSessionStore.delete(context, session.id)
                            sessions = BowlingSessionStore.all(context)
                            openId = null
                        },
                    )
                }
            }
            else -> LockedPage(
                onBack = onExit,
                onPlans = {
                    view.tick()
                    MembershipNav.open()
                    onExit()
                },
            )
        }
    }

    if (creating) {
        NewSessionSheet(
            onDismiss = { creating = false },
            onStart = { name, camera ->
                creating = false
                val made = BowlingSessionStore.create(context, name, camera)
                sessions = BowlingSessionStore.all(context)
                startCamera(made.id)
            },
        )
    }
}

// ── The list: Live, then History ─────────────────────────────────────────────────

@Composable
private fun SessionList(
    sessions: List<BowlingSessionStore.Session>,
    now: Long,
    onBack: () -> Unit,
    onNew: () -> Unit,
    onOpen: (BowlingSessionStore.Session) -> Unit,
    onCamera: (BowlingSessionStore.Session) -> Unit,
) {
    val live = sessions.filter { it.isLive(now) }
    val history = sessions.filterNot { it.isLive(now) }
    LazyColumn(
        Modifier.fillMaxSize(),
        contentPadding = androidx.compose.foundation.layout.PaddingValues(bottom = 32.dp),
    ) {
        item {
            Column(Modifier.statusBarsPadding().padding(horizontal = 16.dp)) {
                TopBar("Bowling sessions", onBack)
                Spacer(Modifier.height(4.dp))
                Text(
                    "Film a practice spell with the LBW camera. Every ball is tracked and its video kept on this phone, under the session's name.",
                    color = Muted,
                    fontSize = 13.5.sp,
                    lineHeight = 19.sp,
                )
                Spacer(Modifier.height(16.dp))
                PrimaryButton("New session", leading = { Icon(Icons.Outlined.Add, null, tint = Color.White, modifier = Modifier.size(20.dp)) }, onClick = onNew)
                Spacer(Modifier.height(22.dp))
            }
        }
        if (live.isNotEmpty()) {
            item { SectionLabel("LIVE NOW", live = true) }
            items(live, key = { "live-" + it.id }) { s ->
                SessionRow(s, live = true, onClick = { onOpen(s) }, onCamera = { onCamera(s) })
            }
            item { Spacer(Modifier.height(18.dp)) }
        }
        item { SectionLabel("HISTORY", live = false) }
        if (history.isEmpty()) {
            item {
                Text(
                    if (live.isEmpty()) "No sessions yet. Start one and point the phone down the pitch from behind the bowler's stumps."
                    else "Finished sessions appear here once their camera is closed.",
                    color = Muted,
                    fontSize = 13.sp,
                    lineHeight = 18.sp,
                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 6.dp),
                )
            }
        }
        items(history, key = { it.id }) { s ->
            SessionRow(s, live = false, onClick = { onOpen(s) }, onCamera = null)
        }
    }
}

@Composable
private fun TopBar(title: String, onBack: () -> Unit, trailing: @Composable (() -> Unit)? = null) {
    Row(Modifier.fillMaxWidth().padding(top = 8.dp, bottom = 8.dp), verticalAlignment = Alignment.CenterVertically) {
        Box(
            Modifier
                .size(40.dp)
                .clip(RoundedCornerShape(12.dp))
                .background(Color.White)
                .border(1.dp, Hairline, RoundedCornerShape(12.dp))
                .clickable(onClick = onBack),
            contentAlignment = Alignment.Center,
        ) {
            Icon(Icons.AutoMirrored.Outlined.ArrowBack, contentDescription = "Back", tint = Ink, modifier = Modifier.size(18.dp))
        }
        Spacer(Modifier.width(12.dp))
        Text(
            title,
            color = Ink,
            fontSize = 21.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = (-0.4).sp,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f, fill = false),
        )
        Spacer(Modifier.width(8.dp))
        HeroChip()
        Spacer(Modifier.weight(1f))
        trailing?.invoke()
    }
}

@Composable
private fun HeroChip() {
    Text(
        "HERO",
        color = HeroGold,
        fontSize = 9.5.sp,
        fontWeight = FontWeight.ExtraBold,
        letterSpacing = 1.2.sp,
        modifier = Modifier
            .clip(RoundedCornerShape(999.dp))
            .background(HeroOnyx)
            .padding(horizontal = 7.dp, vertical = 2.dp),
    )
}

@Composable
private fun SectionLabel(text: String, live: Boolean) {
    Row(Modifier.padding(horizontal = 16.dp, vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
        if (live) {
            LiveDot()
            Spacer(Modifier.width(7.dp))
        }
        Text(text, color = if (live) Rec else Muted, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.4.sp)
    }
}

@Composable
private fun LiveDot() {
    val t = rememberInfiniteTransition(label = "liveDot")
    val a by t.animateFloat(0.35f, 1f, infiniteRepeatable(tween(800, easing = FastOutSlowInEasing), RepeatMode.Reverse), label = "liveDotA")
    Box(Modifier.size(8.dp).clip(CircleShape).background(Rec.copy(alpha = a)))
}

@Composable
private fun SessionRow(
    s: BowlingSessionStore.Session,
    live: Boolean,
    onClick: () -> Unit,
    onCamera: (() -> Unit)?,
) {
    Row(
        Modifier
            .padding(horizontal = 16.dp, vertical = 5.dp)
            .fillMaxWidth()
            .clip(RoundedCornerShape(18.dp))
            .background(Color.White)
            .border(1.dp, if (live) Rec.copy(alpha = 0.35f) else Hairline, RoundedCornerShape(18.dp))
            .clickable(onClick = onClick)
            .padding(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier.size(46.dp).clip(RoundedCornerShape(13.dp)).background(Brand.copy(alpha = 0.08f)),
            contentAlignment = Alignment.Center,
        ) {
            StumpsGlyph(Modifier.size(22.dp), if (s.camera == BowlingSessionStore.Camera.SIDE_ON) Muted else Brand)
        }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(s.name, color = Ink, fontSize = 15.sp, fontWeight = FontWeight.Bold, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Spacer(Modifier.height(2.dp))
            Text(
                listOf(s.camera.label, ballsLabel(s.balls), whenLabel(s.createdAtMs)).joinToString(" · "),
                color = Muted,
                fontSize = 12.5.sp,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }
        if (onCamera != null) {
            Spacer(Modifier.width(8.dp))
            Text(
                "Camera",
                color = Color.White,
                fontSize = 12.5.sp,
                fontWeight = FontWeight.Bold,
                modifier = Modifier
                    .clip(RoundedCornerShape(999.dp))
                    .background(Brand)
                    .clickable(onClick = onCamera)
                    .padding(horizontal = 12.dp, vertical = 7.dp),
            )
        }
    }
}

// ── One session: its videos on this phone ────────────────────────────────────────

private data class SessionVideo(val uri: Uri, val name: String, val takenAtMs: Long, val durationMs: Long)

@Composable
private fun SessionDetail(
    session: BowlingSessionStore.Session,
    live: Boolean,
    onBack: () -> Unit,
    onCamera: () -> Unit,
    onDelete: () -> Unit,
) {
    val context = LocalContext.current
    val view = LocalView.current
    // Re-listed as balls are saved, so a live session fills in while you watch.
    val videos by produceState<List<SessionVideo>?>(null, session.album, session.balls) {
        value = withContext(Dispatchers.IO) { runCatching { videosOf(context, session.album) }.getOrDefault(emptyList()) }
    }
    var confirmDelete by remember { mutableStateOf(false) }

    LazyVerticalGrid(
        columns = GridCells.Fixed(2),
        modifier = Modifier.fillMaxSize(),
        contentPadding = androidx.compose.foundation.layout.PaddingValues(start = 16.dp, end = 16.dp, bottom = 32.dp),
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        item(span = { GridItemSpan(2) }) {
            Column(Modifier.statusBarsPadding()) {
                TopBar(session.name, onBack) {
                    Box(
                        Modifier.size(40.dp).clip(RoundedCornerShape(12.dp)).clickable { view.tick(); confirmDelete = true },
                        contentAlignment = Alignment.Center,
                    ) {
                        Icon(Icons.Outlined.DeleteOutline, contentDescription = "Remove session", tint = Muted, modifier = Modifier.size(20.dp))
                    }
                }
                Row(verticalAlignment = Alignment.CenterVertically) {
                    if (live) {
                        LiveDot()
                        Spacer(Modifier.width(6.dp))
                        Text("Filming now", color = Rec, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                        Text("  ·  ", color = Muted, fontSize = 13.sp)
                    }
                    Text(
                        listOf(session.camera.label, ballsLabel(session.balls), whenLabel(session.createdAtMs)).joinToString(" · "),
                        color = Muted,
                        fontSize = 13.sp,
                    )
                }
                Spacer(Modifier.height(14.dp))
                PrimaryButton(
                    if (live) "Back to the camera" else "Film more balls",
                    leading = { Icon(Icons.Outlined.PlayArrow, null, tint = Color.White, modifier = Modifier.size(20.dp)) },
                    onClick = onCamera,
                )
                Spacer(Modifier.height(8.dp))
                Text(
                    "Saved in Gallery › Movies › ${BowlingSessionStore.ROOT_ALBUM} › ${session.album}",
                    color = Muted,
                    fontSize = 11.5.sp,
                    lineHeight = 16.sp,
                )
                Spacer(Modifier.height(14.dp))
            }
        }
        val list = videos
        when {
            list == null -> Unit
            list.isEmpty() -> item(span = { GridItemSpan(2) }) {
                Text(
                    if (live) "Each ball the camera tracks lands here a few seconds after it's bowled."
                    else "No videos on this phone for this session. Balls the camera couldn't track aren't saved, and videos deleted from the Gallery are gone.",
                    color = Muted,
                    fontSize = 13.sp,
                    lineHeight = 18.sp,
                )
            }
            else -> items(list, key = { it.uri.toString() }) { v ->
                VideoTile(v) {
                    view.tick()
                    runCatching {
                        context.startActivity(
                            Intent(Intent.ACTION_VIEW)
                                .setDataAndType(v.uri, "video/mp4")
                                .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION),
                        )
                    }.onFailure {
                        android.widget.Toast.makeText(context, "No video player on this phone.", android.widget.Toast.LENGTH_SHORT).show()
                    }
                }
            }
        }
    }

    if (confirmDelete) {
        AlertDialog(
            onDismissRequest = { confirmDelete = false },
            title = { Text("Remove this session?") },
            text = { Text("It leaves this list. Its videos stay in your Gallery.") },
            confirmButton = {
                TextButton(onClick = { confirmDelete = false; onDelete() }) { Text("Remove", color = Rec) }
            },
            dismissButton = { TextButton(onClick = { confirmDelete = false }) { Text("Keep", color = Brand) } },
        )
    }
}

@Composable
private fun VideoTile(v: SessionVideo, onClick: () -> Unit) {
    val context = LocalContext.current
    val thumb by produceState<Bitmap?>(null, v.uri) {
        value = withContext(Dispatchers.IO) { runCatching { thumbnailOf(context, v.uri) }.getOrNull() }
    }
    Column(
        Modifier
            .clip(RoundedCornerShape(16.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(16.dp))
            .clickable(onClick = onClick),
    ) {
        Box(Modifier.fillMaxWidth().aspectRatio(0.8f).background(Color(0xFF0E1A2E)), contentAlignment = Alignment.Center) {
            thumb?.let {
                Image(it.asImageBitmap(), contentDescription = null, contentScale = ContentScale.Crop, modifier = Modifier.fillMaxSize())
            }
            Box(Modifier.size(40.dp).clip(CircleShape).background(Color.Black.copy(alpha = 0.45f)), contentAlignment = Alignment.Center) {
                Icon(Icons.Outlined.PlayArrow, contentDescription = "Play", tint = Color.White, modifier = Modifier.size(24.dp))
            }
            if (v.durationMs > 0) {
                Text(
                    "%d:%02d".format(v.durationMs / 60_000, (v.durationMs / 1000) % 60),
                    color = Color.White,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier
                        .align(Alignment.BottomEnd)
                        .padding(6.dp)
                        .clip(RoundedCornerShape(6.dp))
                        .background(Color.Black.copy(alpha = 0.55f))
                        .padding(horizontal = 5.dp, vertical = 1.dp),
                )
            }
        }
        Column(Modifier.padding(horizontal = 10.dp, vertical = 8.dp)) {
            Text(ballOf(v.name), color = Ink, fontSize = 13.5.sp, fontWeight = FontWeight.Bold, maxLines = 1)
            Text(
                SimpleDateFormat("h:mm a", Locale.getDefault()).format(Date(v.takenAtMs)),
                color = Muted,
                fontSize = 11.5.sp,
            )
        }
    }
}

/** "Haraan Ball 3 2026-11-12 18-04-11.mp4" → "Ball 3". */
private fun ballOf(fileName: String): String =
    Regex("Ball \\d+").find(fileName)?.value ?: "Ball"

private fun videosOf(context: Context, album: String): List<SessionVideo> {
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
        val path = "${Environment.DIRECTORY_MOVIES}/${BowlingSessionStore.ROOT_ALBUM}/$album/"
        val out = mutableListOf<SessionVideo>()
        val projection = arrayOf(
            MediaStore.Video.Media._ID,
            MediaStore.Video.Media.DISPLAY_NAME,
            MediaStore.Video.Media.DATE_TAKEN,
            MediaStore.Video.Media.DATE_ADDED,
            MediaStore.Video.Media.DURATION,
        )
        context.contentResolver.query(
            MediaStore.Video.Media.getContentUri(MediaStore.VOLUME_EXTERNAL_PRIMARY),
            projection,
            "${MediaStore.Video.Media.RELATIVE_PATH} = ?",
            arrayOf(path),
            "${MediaStore.Video.Media.DATE_ADDED} DESC",
        )?.use { c ->
            val id = c.getColumnIndexOrThrow(MediaStore.Video.Media._ID)
            val name = c.getColumnIndexOrThrow(MediaStore.Video.Media.DISPLAY_NAME)
            val taken = c.getColumnIndexOrThrow(MediaStore.Video.Media.DATE_TAKEN)
            val added = c.getColumnIndexOrThrow(MediaStore.Video.Media.DATE_ADDED)
            val dur = c.getColumnIndexOrThrow(MediaStore.Video.Media.DURATION)
            while (c.moveToNext()) {
                val uri = ContentUris.withAppendedId(MediaStore.Video.Media.getContentUri(MediaStore.VOLUME_EXTERNAL_PRIMARY), c.getLong(id))
                val t = c.getLong(taken).takeIf { it > 0 } ?: (c.getLong(added) * 1000)
                out += SessionVideo(uri, c.getString(name).orEmpty(), t, c.getLong(dur))
            }
        }
        return out
    }
    val dir = File(context.getExternalFilesDir(Environment.DIRECTORY_MOVIES), "${BowlingSessionStore.ROOT_ALBUM}/$album")
    return dir.listFiles { f -> f.isFile && f.extension.equals("mp4", true) }.orEmpty()
        .sortedByDescending { it.lastModified() }
        .map { f ->
            val uri = FileProvider.getUriForFile(context, context.packageName + ".fileprovider", f)
            SessionVideo(uri, f.name, f.lastModified(), durationOf(context, uri))
        }
}

private fun durationOf(context: Context, uri: Uri): Long = runCatching {
    val r = android.media.MediaMetadataRetriever()
    try {
        r.setDataSource(context, uri)
        r.extractMetadata(android.media.MediaMetadataRetriever.METADATA_KEY_DURATION)?.toLongOrNull() ?: 0L
    } finally {
        r.release()
    }
}.getOrDefault(0L)

private fun thumbnailOf(context: Context, uri: Uri): Bitmap? =
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
        context.contentResolver.loadThumbnail(uri, Size(360, 450), null)
    } else {
        val r = android.media.MediaMetadataRetriever()
        try {
            r.setDataSource(context, uri)
            r.getFrameAtTime(1_000_000)
        } finally {
            r.release()
        }
    }

// ── New session ──────────────────────────────────────────────────────────────────

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun NewSessionSheet(
    onDismiss: () -> Unit,
    onStart: (String, BowlingSessionStore.Camera) -> Unit,
) {
    val view = LocalView.current
    val sheet = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    var name by remember { mutableStateOf("") }
    var camera by remember { mutableStateOf(BowlingSessionStore.Camera.LBW) }
    var tries by remember { mutableIntStateOf(0) }
    val error = tries > 0 && name.isBlank()
    fun start() {
        if (name.isBlank()) { tries++; view.tick(); return }
        onStart(name, camera)
    }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheet,
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
            Text("New bowling session", color = Ink, fontSize = 22.sp, fontWeight = FontWeight.ExtraBold, letterSpacing = (-0.4).sp)
            Spacer(Modifier.height(4.dp))
            Text("Its videos are saved on this phone under this name.", color = Muted, fontSize = 13.5.sp)
            Spacer(Modifier.height(16.dp))
            Box(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(16.dp))
                    .background(Page)
                    .border(if (error) 1.5.dp else 1.dp, if (error) Rec else Hairline, RoundedCornerShape(16.dp))
                    .padding(horizontal = 16.dp, vertical = 15.dp),
            ) {
                if (name.isEmpty()) Text("Session name, e.g. Nets — yorkers", color = Color(0xFFB6C0CE), fontSize = 16.sp)
                BasicTextField(
                    value = name,
                    onValueChange = { name = it.take(BowlingSessionStore.MAX_NAME) },
                    singleLine = true,
                    textStyle = TextStyle(color = Ink, fontSize = 16.sp, fontWeight = FontWeight.SemiBold),
                    cursorBrush = SolidColor(Brand),
                    keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences, imeAction = ImeAction.Done),
                    keyboardActions = KeyboardActions(onDone = { start() }),
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            if (error) {
                Text("Give the session a name.", color = Rec, fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.padding(top = 6.dp, start = 4.dp))
            }
            Spacer(Modifier.height(16.dp))
            Text("CAMERA", color = Muted, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.4.sp)
            Spacer(Modifier.height(8.dp))
            BowlingSessionStore.Camera.entries.forEach { c ->
                val on = c == camera
                Row(
                    Modifier
                        .padding(bottom = 8.dp)
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(16.dp))
                        .background(if (on) Brand.copy(alpha = 0.06f) else Color.White)
                        .border(if (on) 1.5.dp else 1.dp, if (on) Brand else Hairline, RoundedCornerShape(16.dp))
                        .clickable { if (!on) { view.tick(); camera = c } }
                        .padding(12.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    StumpsGlyph(Modifier.size(22.dp), if (on) Brand else Muted)
                    Spacer(Modifier.width(12.dp))
                    Column(Modifier.weight(1f)) {
                        Text(c.label, color = Ink, fontSize = 14.5.sp, fontWeight = FontWeight.Bold)
                        Text(c.blurb, color = Muted, fontSize = 12.5.sp, lineHeight = 17.sp)
                    }
                    Spacer(Modifier.width(8.dp))
                    Box(
                        Modifier.size(20.dp).clip(CircleShape).border(2.dp, if (on) Brand else Hairline, CircleShape),
                        contentAlignment = Alignment.Center,
                    ) {
                        if (on) Box(Modifier.size(10.dp).clip(CircleShape).background(Brand))
                    }
                }
            }
            Spacer(Modifier.height(10.dp))
            PrimaryButton("Start filming", onClick = { start() })
        }
    }
}

// ── Not on Hero ──────────────────────────────────────────────────────────────────

@Composable
private fun LockedPage(onBack: () -> Unit, onPlans: () -> Unit) {
    Column(Modifier.fillMaxSize().statusBarsPadding().padding(horizontal = 16.dp)) {
        TopBar("Bowling sessions", onBack)
        Spacer(Modifier.weight(1f))
        Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
            StumpsGlyph(Modifier.size(56.dp), Muted.copy(alpha = 0.5f))
        }
        Spacer(Modifier.height(18.dp))
        Text("Included with Hero", color = Ink, fontSize = 22.sp, fontWeight = FontWeight.ExtraBold, modifier = Modifier.align(Alignment.CenterHorizontally))
        Spacer(Modifier.height(6.dp))
        Text(
            "Name a practice session, film it with the LBW camera, and keep every tracked ball on your phone.",
            color = Muted,
            fontSize = 14.sp,
            lineHeight = 20.sp,
            textAlign = androidx.compose.ui.text.style.TextAlign.Center,
            modifier = Modifier.fillMaxWidth(),
        )
        Spacer(Modifier.weight(1f))
        PrimaryButton("See Hero", onClick = onPlans)
        Spacer(Modifier.navigationBarsPadding().height(20.dp))
    }
}

// ── Pieces ───────────────────────────────────────────────────────────────────────

@Composable
private fun PrimaryButton(label: String, leading: (@Composable () -> Unit)? = null, onClick: () -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .height(52.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(Brand)
            .clickable(onClick = onClick),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        leading?.let { it(); Spacer(Modifier.width(8.dp)) }
        Text(label, color = Color.White, fontSize = 15.5.sp, fontWeight = FontWeight.Bold)
    }
}

/** Three stumps and the bails: the LBW camera's subject. */
@Composable
private fun StumpsGlyph(modifier: Modifier, tint: Color) {
    Canvas(modifier) {
        val w = size.width
        val h = size.height
        val sw = w * 0.1f
        listOf(0.3f, 0.5f, 0.7f).forEach { x ->
            drawLine(tint, Offset(w * x, h * 0.22f), Offset(w * x, h * 0.92f), sw, StrokeCap.Round)
        }
        drawLine(tint, Offset(w * 0.24f, h * 0.12f), Offset(w * 0.76f, h * 0.12f), sw * 0.8f, StrokeCap.Round)
    }
}

private fun ballsLabel(n: Int) = if (n == 1) "1 ball" else "$n balls"

private fun whenLabel(ms: Long): String {
    val day = SimpleDateFormat("yyyyMMdd", Locale.US)
    val today = day.format(Date())
    val that = day.format(Date(ms))
    val time = SimpleDateFormat("h:mm a", Locale.getDefault()).format(Date(ms))
    return when {
        that == today -> "Today $time"
        that == day.format(Date(System.currentTimeMillis() - 86_400_000L)) -> "Yesterday $time"
        else -> SimpleDateFormat("d MMM, h:mm a", Locale.getDefault()).format(Date(ms))
    }
}
