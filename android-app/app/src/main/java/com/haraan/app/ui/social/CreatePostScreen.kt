package com.haraan.app.ui.social

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Canvas
import android.graphics.Matrix
import android.graphics.Paint
import android.media.ExifInterface
import android.net.Uri
import android.widget.Toast
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectTransformGestures
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Close
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.IntSize
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.haraan.app.data.PlayerPost
import com.haraan.app.data.PlayerRepository
import com.haraan.app.data.TokenStore
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.ByteArrayOutputStream
import kotlin.math.min

/**
 * Instagram-style create-post flow.
 *
 *  - One photo  → CROP step (pinch-zoom + drag inside a square) → CAPTION → Share.
 *  - Many photos → REVIEW step (swipe the carousel; each auto centre-cropped to square) →
 *    CAPTION → Share, posting all as a carousel.
 *
 * Share renders 1080² JPEGs (EXIF-corrected, compressed) so uploads stay under the 8 MB cap.
 */
@Composable
fun CreatePostScreen(
    imageUris: List<Uri>,
    onClose: () -> Unit,
    onPosted: (PlayerPost) -> Unit,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val single = imageUris.size == 1

    var step by remember { mutableIntStateOf(0) } // 0 = crop/review, 1 = caption
    var caption by remember { mutableStateOf("") }
    var posting by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    // Manual crop transform — single-photo only.
    var scale by remember { mutableStateOf(1f) }
    var offset by remember { mutableStateOf(Offset.Zero) }
    var viewport by remember { mutableStateOf(IntSize.Zero) }
    var srcSize by remember { mutableStateOf(IntSize.Zero) }

    val pagerState = rememberPagerState(pageCount = { imageUris.size })

    LaunchedEffect(imageUris) {
        if (single) srcSize = withContext(Dispatchers.IO) { orientedSourceSize(context, imageUris[0]) }
    }

    val blue = Color(0xFF2563EB)
    val ink = Color(0xFF0F172A)
    val muted = Color(0xFF64748B)
    val canvas = Color(0xFF0B0B0F)

    fun clamp(next: Offset, s: Float): Offset {
        val v = viewport.width.toFloat()
        if (v <= 0f || srcSize.width <= 0 || srcSize.height <= 0) return next
        val cover = v / min(srcSize.width, srcSize.height)
        val maxX = ((srcSize.width * cover * s - v) / 2f).coerceAtLeast(0f)
        val maxY = ((srcSize.height * cover * s - v) / 2f).coerceAtLeast(0f)
        return Offset(next.x.coerceIn(-maxX, maxX), next.y.coerceIn(-maxY, maxY))
    }

    fun share() {
        posting = true
        error = null
        scope.launch {
            val token = TokenStore.getToken(context)
            if (!TokenStore.isSignedIn(token)) {
                error = "Please sign in to post."; posting = false; return@launch
            }
            val bytes = withContext(Dispatchers.IO) {
                if (single) {
                    listOfNotNull(exportSquare(context, imageUris[0], viewport.width, scale, offset.x, offset.y))
                } else {
                    imageUris.mapNotNull { centerCropSquare(context, it) }
                }
            }
            if (bytes.isEmpty()) {
                error = "Couldn't process the image(s). Try again."; posting = false; return@launch
            }
            val created = PlayerRepository().uploadPost(
                token = token!!,
                images = bytes,
                caption = caption.trim().ifBlank { null },
            )
            posting = false
            if (created != null) {
                Toast.makeText(context, "Posted", Toast.LENGTH_SHORT).show()
                onPosted(created)
            } else {
                error = "Upload failed. Check your connection and try again."
            }
        }
    }

    Surface(modifier = modifier.fillMaxSize(), color = Color.White) {
        Column(
            modifier = Modifier.fillMaxSize().statusBarsPadding().imePadding(),
        ) {
            // Top bar
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(start = 6.dp, end = 14.dp, top = 6.dp, bottom = 6.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(
                    modifier = Modifier
                        .size(40.dp)
                        .clickableNoRipple(enabled = !posting) { if (step == 0) onClose() else step = 0 },
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(
                        imageVector = if (step == 0) Icons.Filled.Close else Icons.AutoMirrored.Filled.ArrowBack,
                        contentDescription = if (step == 0) "Close" else "Back",
                        tint = ink,
                    )
                }
                Text(
                    text = "New post",
                    color = ink,
                    fontSize = 17.sp,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.weight(1f),
                )
                Text(
                    text = if (step == 0) "Next" else "Share",
                    color = if (posting) muted else blue,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.ExtraBold,
                    modifier = Modifier.clickableNoRipple(enabled = !posting) {
                        if (step == 0) step = 1 else share()
                    },
                )
            }
            HorizontalDivider(color = Color(0xFFEEF0F4), thickness = 1.dp)
            if (posting) {
                LinearProgressIndicator(modifier = Modifier.fillMaxWidth(), color = blue, trackColor = Color(0xFFE8F0FE))
            }

            if (step == 0) {
                // Dark, centred canvas — no dead white space.
                Box(
                    modifier = Modifier.fillMaxWidth().weight(1f).background(canvas),
                    contentAlignment = Alignment.Center,
                ) {
                    if (single) {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .aspectRatio(1f)
                                .onSizeChanged { viewport = it }
                                .pointerInput(srcSize, viewport) {
                                    detectTransformGestures { _, pan, zoom, _ ->
                                        val s = (scale * zoom).coerceIn(1f, 5f)
                                        scale = s
                                        offset = clamp(offset + pan, s)
                                    }
                                },
                        ) {
                            AsyncImage(
                                model = imageUris[0],
                                contentDescription = "Selected photo",
                                contentScale = ContentScale.Crop,
                                modifier = Modifier.fillMaxSize().graphicsLayer {
                                    scaleX = scale; scaleY = scale
                                    translationX = offset.x; translationY = offset.y
                                },
                            )
                        }
                        Text(
                            "Pinch to zoom · drag to reposition",
                            color = Color.White.copy(alpha = 0.72f),
                            fontSize = 12.5.sp,
                            modifier = Modifier.align(Alignment.BottomCenter).padding(bottom = 20.dp),
                        )
                    } else {
                        CarouselSquares(imageUris, pagerState)
                        Text(
                            "${imageUris.size} photos · swipe to review",
                            color = Color.White.copy(alpha = 0.72f),
                            fontSize = 12.5.sp,
                            modifier = Modifier.align(Alignment.BottomCenter).padding(bottom = 20.dp),
                        )
                    }
                }
            } else {
                Column(
                    modifier = Modifier.fillMaxWidth().weight(1f).verticalScroll(rememberScrollState()),
                ) {
                    if (single) {
                        Box(
                            modifier = Modifier.fillMaxWidth().aspectRatio(1f).background(Color.Black),
                        ) {
                            AsyncImage(
                                model = imageUris[0],
                                contentDescription = "Selected photo",
                                contentScale = ContentScale.Crop,
                                modifier = Modifier.fillMaxSize().graphicsLayer {
                                    scaleX = scale; scaleY = scale
                                    translationX = offset.x; translationY = offset.y
                                },
                            )
                        }
                    } else {
                        CarouselSquares(imageUris, pagerState)
                    }
                    OutlinedTextField(
                        value = caption,
                        onValueChange = { if (it.length <= 300) caption = it },
                        placeholder = { Text("Write a caption…", color = muted) },
                        modifier = Modifier.fillMaxWidth().padding(16.dp),
                        minLines = 3,
                        shape = RoundedCornerShape(12.dp),
                        enabled = !posting,
                    )
                    Text(
                        "${caption.length}/300",
                        color = muted,
                        fontSize = 11.sp,
                        modifier = Modifier.align(Alignment.End).padding(end = 20.dp),
                    )
                    error?.let {
                        Text(it, color = Color(0xFFDC2626), fontSize = 13.sp, modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp))
                    }
                }
            }
        }

        if (posting) {
            Box(Modifier.fillMaxSize(), contentAlignment = Alignment.BottomCenter) {
                Row(
                    modifier = Modifier.padding(bottom = 40.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(10.dp),
                ) {
                    CircularProgressIndicator(modifier = Modifier.size(18.dp), strokeWidth = 2.dp, color = blue)
                    Text("Posting…", color = muted, fontSize = 13.sp)
                }
            }
        }
    }
}

/** A swipeable square carousel of the selected photos, with page dots. Auto centre-cropped. */
@Composable
private fun CarouselSquares(
    uris: List<Uri>,
    pagerState: androidx.compose.foundation.pager.PagerState,
) {
    Box(modifier = Modifier.fillMaxWidth()) {
        HorizontalPager(state = pagerState) { page ->
            Box(modifier = Modifier.fillMaxWidth().aspectRatio(1f).background(Color.Black)) {
                AsyncImage(
                    model = uris[page],
                    contentDescription = "Photo ${page + 1}",
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.fillMaxSize(),
                )
            }
        }
        Row(
            modifier = Modifier.align(Alignment.BottomCenter).padding(bottom = 10.dp),
            horizontalArrangement = Arrangement.spacedBy(6.dp),
        ) {
            repeat(uris.size) { i ->
                val on = i == pagerState.currentPage
                Box(
                    modifier = Modifier
                        .size(if (on) 7.dp else 6.dp)
                        .clip(CircleShape)
                        .background(if (on) Color.White else Color.White.copy(alpha = 0.45f)),
                )
            }
        }
    }
}

/** Tap target with no ripple; disabled taps are ignored. */
private fun Modifier.clickableNoRipple(enabled: Boolean = true, onClick: () -> Unit): Modifier =
    this.clickable(enabled = enabled, indication = null, interactionSource = MutableInteractionSource(), onClick = onClick)

/** EXIF-oriented pixel dimensions of the source, without decoding the full bitmap. */
private fun orientedSourceSize(context: Context, uri: Uri): IntSize {
    return try {
        val res = context.contentResolver
        val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
        res.openInputStream(uri)?.use { BitmapFactory.decodeStream(it, null, bounds) }
        var w = bounds.outWidth
        var h = bounds.outHeight
        val orientation = res.openInputStream(uri)?.use {
            ExifInterface(it).getAttributeInt(ExifInterface.TAG_ORIENTATION, ExifInterface.ORIENTATION_NORMAL)
        } ?: ExifInterface.ORIENTATION_NORMAL
        if (orientation == ExifInterface.ORIENTATION_ROTATE_90 || orientation == ExifInterface.ORIENTATION_ROTATE_270) {
            val t = w; w = h; h = t
        }
        IntSize(w.coerceAtLeast(1), h.coerceAtLeast(1))
    } catch (_: Exception) {
        IntSize(1, 1)
    }
}

/** Decode a memory-safe, EXIF-corrected bitmap (longest edge ≈ [maxEdge]). */
private fun loadOrientedBitmap(context: Context, uri: Uri, maxEdge: Int = 1600): Bitmap? {
    val res = context.contentResolver
    val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
    res.openInputStream(uri)?.use { BitmapFactory.decodeStream(it, null, bounds) }
    var sample = 1
    while (bounds.outWidth / sample > maxEdge || bounds.outHeight / sample > maxEdge) sample *= 2
    val opts = BitmapFactory.Options().apply { inSampleSize = sample }
    var bmp = res.openInputStream(uri)?.use { BitmapFactory.decodeStream(it, null, opts) } ?: return null

    val orientation = res.openInputStream(uri)?.use {
        ExifInterface(it).getAttributeInt(ExifInterface.TAG_ORIENTATION, ExifInterface.ORIENTATION_NORMAL)
    } ?: ExifInterface.ORIENTATION_NORMAL
    val m = Matrix()
    when (orientation) {
        ExifInterface.ORIENTATION_ROTATE_90 -> m.postRotate(90f)
        ExifInterface.ORIENTATION_ROTATE_180 -> m.postRotate(180f)
        ExifInterface.ORIENTATION_ROTATE_270 -> m.postRotate(270f)
        ExifInterface.ORIENTATION_FLIP_HORIZONTAL -> m.postScale(-1f, 1f)
        ExifInterface.ORIENTATION_FLIP_VERTICAL -> m.postScale(1f, -1f)
    }
    if (!m.isIdentity) bmp = Bitmap.createBitmap(bmp, 0, 0, bmp.width, bmp.height, m, true)
    return bmp
}

/** Plain centre-square crop → 1080² JPEG. Used for every image in a multi-photo carousel. */
private fun centerCropSquare(context: Context, uri: Uri): ByteArray? {
    return try {
        val bmp = loadOrientedBitmap(context, uri) ?: return null
        val side = min(bmp.width, bmp.height)
        val left = (bmp.width - side) / 2
        val top = (bmp.height - side) / 2
        var square = Bitmap.createBitmap(bmp, left, top, side, side)
        if (square.width > 1080) square = Bitmap.createScaledBitmap(square, 1080, 1080, true)
        ByteArrayOutputStream().use { s ->
            square.compress(Bitmap.CompressFormat.JPEG, 88, s)
            s.toByteArray()
        }
    } catch (_: Exception) {
        null
    }
}

/**
 * Render the manual-crop framing (single photo) to a 1080² JPEG. Replicates the preview's
 * transform: cover the square (ContentScale.Crop), then the user's zoom [scale] and pan
 * [(tx, ty)] measured in the [viewportPx]-wide viewport.
 */
private fun exportSquare(context: Context, uri: Uri, viewportPx: Int, scale: Float, tx: Float, ty: Float): ByteArray? {
    return try {
        if (viewportPx <= 0) return centerCropSquare(context, uri)
        val bmp = loadOrientedBitmap(context, uri) ?: return null
        val out = 1080f
        val r = out / viewportPx.toFloat()
        val cover = out / min(bmp.width, bmp.height)
        val total = cover * scale
        val m = Matrix().apply {
            postTranslate(-bmp.width / 2f, -bmp.height / 2f)
            postScale(total, total)
            postTranslate(out / 2f + tx * r, out / 2f + ty * r)
        }
        val result = Bitmap.createBitmap(1080, 1080, Bitmap.Config.ARGB_8888)
        Canvas(result).apply {
            drawColor(android.graphics.Color.BLACK)
            drawBitmap(bmp, m, Paint(Paint.FILTER_BITMAP_FLAG or Paint.ANTI_ALIAS_FLAG))
        }
        ByteArrayOutputStream().use { s ->
            result.compress(Bitmap.CompressFormat.JPEG, 88, s)
            s.toByteArray()
        }
    } catch (_: Exception) {
        null
    }
}
