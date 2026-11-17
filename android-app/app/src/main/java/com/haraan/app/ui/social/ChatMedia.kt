package com.haraan.app.ui.social

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Matrix
import android.media.ExifInterface
import android.net.Uri
import androidx.activity.compose.BackHandler
import androidx.compose.foundation.background
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.gestures.rememberTransformableState
import androidx.compose.foundation.gestures.transformable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material.icons.filled.Close
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.TextLinkStyles
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import coil.compose.AsyncImage
import com.haraan.app.ui.theme.HaraanColors
import com.haraan.app.ui.pressable
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.ByteArrayOutputStream

/**
 * A photo ready to send: shrunk, EXIF-upright JPEG bytes and the size they decode to.
 *
 * Shrunk on the phone because a modern camera photo is 4-12 MB; a chat photo looks the same
 * at 1600px and arrives in a second on a weak connection instead of half a minute.
 */
class PreparedPhoto(val uri: Uri, val jpeg: ByteArray, val width: Int, val height: Int)

/** Longest edge sent in a chat photo. */
private const val CHAT_PHOTO_EDGE = 1600

suspend fun prepareChatPhoto(context: Context, uri: Uri, maxEdge: Int = CHAT_PHOTO_EDGE): PreparedPhoto? =
    withContext(Dispatchers.IO) {
        try {
            val res = context.contentResolver
            val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
            res.openInputStream(uri)?.use { BitmapFactory.decodeStream(it, null, bounds) }
            if (bounds.outWidth <= 0 || bounds.outHeight <= 0) return@withContext null
            var sample = 1
            while (bounds.outWidth / (sample * 2) >= maxEdge || bounds.outHeight / (sample * 2) >= maxEdge) sample *= 2
            var bmp = res.openInputStream(uri)?.use {
                BitmapFactory.decodeStream(it, null, BitmapFactory.Options().apply { inSampleSize = sample })
            } ?: return@withContext null

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
            // Exact scale to the edge after the power-of-two decode got us close.
            val longest = maxOf(bmp.width, bmp.height)
            if (longest > maxEdge) {
                val f = maxEdge.toFloat() / longest
                m.postScale(f, f)
            }
            if (!m.isIdentity) {
                val turned = Bitmap.createBitmap(bmp, 0, 0, bmp.width, bmp.height, m, true)
                if (turned !== bmp) bmp.recycle()
                bmp = turned
            }
            val out = ByteArrayOutputStream()
            bmp.compress(Bitmap.CompressFormat.JPEG, 82, out)
            val w = bmp.width
            val h = bmp.height
            bmp.recycle()
            PreparedPhoto(uri, out.toByteArray(), w, h)
        } catch (_: Throwable) {
            // OutOfMemoryError included: a broken or huge file must never take the chat down.
            null
        }
    }

/**
 * The bubble's photo. The shape is reserved from the stored size before the image arrives,
 * so a thread doesn't jump as photos load. Extreme panoramas are clamped to a sane box.
 */
@Composable
internal fun ChatPhoto(
    model: Any?,
    width: Int?,
    height: Int?,
    onClick: (() -> Unit)?,
    onLongPress: (() -> Unit)? = null,
    modifier: Modifier = Modifier,
) {
    val ratio = if (width != null && height != null && width > 0 && height > 0) {
        (width.toFloat() / height).coerceIn(0.62f, 1.8f)
    } else {
        1f
    }
    Box(
        modifier
            .widthIn(max = 250.dp)
            .fillMaxWidth()
            .aspectRatio(ratio)
            .clip(RoundedCornerShape(12.dp))
            .background(Color.Black.copy(alpha = 0.06f))
            .pointerInput(onClick, onLongPress) {
                detectTapGestures(
                    onTap = { onClick?.invoke() },
                    onLongPress = { onLongPress?.invoke() },
                )
            },
    ) {
        AsyncImage(
            model = model,
            contentDescription = "Photo",
            contentScale = ContentScale.Crop,
            modifier = Modifier.fillMaxSize(),
        )
    }
}

/** Full-screen photo: pinch to zoom, drag when zoomed, double-tap to reset, Back to close. */
@Composable
internal fun ChatPhotoViewer(url: String, onClose: () -> Unit) {
    Dialog(onDismissRequest = onClose, properties = DialogProperties(usePlatformDefaultWidth = false)) {
        BackHandler(onBack = onClose)
        var scale by remember { mutableFloatStateOf(1f) }
        var offset by remember { mutableStateOf(Offset.Zero) }
        val state = rememberTransformableState { zoom, pan, _ ->
            scale = (scale * zoom).coerceIn(1f, 5f)
            offset = if (scale > 1f) offset + pan else Offset.Zero
        }
        Box(Modifier.fillMaxSize().background(Color.Black)) {
            AsyncImage(
                model = url,
                contentDescription = "Photo",
                contentScale = ContentScale.Fit,
                modifier = Modifier
                    .fillMaxSize()
                    .pointerInput(Unit) {
                        detectTapGestures(onDoubleTap = { scale = 1f; offset = Offset.Zero })
                    }
                    .transformable(state)
                    .graphicsLayer {
                        scaleX = scale; scaleY = scale
                        translationX = offset.x; translationY = offset.y
                    },
            )
            Box(
                Modifier
                    .statusBarsPadding()
                    .padding(12.dp)
                    .size(40.dp)
                    .clip(CircleShape)
                    .background(Color.Black.copy(alpha = 0.45f))
                    .pressable(onClick = onClose),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Filled.Close, "Close", tint = Color.White, modifier = Modifier.size(20.dp))
            }
        }
    }
}

/**
 * The step between picking a photo and sending it: see what you're about to send and add a
 * caption — the screen every messenger has, because a wrong photo sent is hard to take back.
 */
@Composable
internal fun ChatPhotoPreview(
    photo: PreparedPhoto,
    recipient: String,
    onSend: (caption: String) -> Unit,
    onCancel: () -> Unit,
) {
    Dialog(onDismissRequest = onCancel, properties = DialogProperties(usePlatformDefaultWidth = false, decorFitsSystemWindows = false)) {
        BackHandler(onBack = onCancel)
        var caption by remember { mutableStateOf("") }
        Box(Modifier.fillMaxSize().background(Color.Black)) {
            AsyncImage(
                model = photo.uri,
                contentDescription = "Photo to send",
                contentScale = ContentScale.Fit,
                modifier = Modifier.fillMaxSize(),
            )
            Row(
                Modifier.fillMaxWidth().statusBarsPadding().padding(12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(
                    Modifier.size(40.dp).clip(CircleShape).background(Color.Black.copy(alpha = 0.45f)).pressable(onClick = onCancel),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(Icons.Filled.Close, "Cancel", tint = Color.White, modifier = Modifier.size(20.dp))
                }
                Spacer(Modifier.width(12.dp))
                Text(
                    "To $recipient",
                    color = Color.White,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.SemiBold,
                    maxLines = 1,
                )
            }
            Row(
                Modifier
                    .align(Alignment.BottomCenter)
                    .fillMaxWidth()
                    .background(Color.Black.copy(alpha = 0.55f))
                    .navigationBarsPadding()
                    .imePadding()
                    .padding(horizontal = 12.dp, vertical = 10.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(
                    Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(22.dp))
                        .background(Color.White.copy(alpha = 0.16f))
                        .padding(horizontal = 16.dp, vertical = 12.dp),
                ) {
                    if (caption.isEmpty()) Text("Add a caption", color = Color.White.copy(alpha = 0.6f), fontSize = 14.5.sp)
                    BasicTextField(
                        value = caption,
                        onValueChange = { caption = it.take(2000) },
                        textStyle = TextStyle(color = Color.White, fontSize = 14.5.sp),
                        cursorBrush = SolidColor(Color.White),
                        maxLines = 4,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
                Spacer(Modifier.width(9.dp))
                Box(
                    Modifier
                        .size(48.dp)
                        .clip(CircleShape)
                        .background(HaraanColors.EventsBlue)
                        .pressable(haptic = com.haraan.app.ui.Feel.COMMIT) { onSend(caption.trim()) },
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(Icons.AutoMirrored.Filled.Send, "Send photo", tint = Color.White, modifier = Modifier.size(20.dp))
                }
            }
        }
    }
}

/** "Ravi added Kiran" — centred and quiet, because it is news about the chat, not a message in it. */
@Composable
internal fun SystemLine(text: String) {
    Box(Modifier.fillMaxWidth().padding(vertical = 6.dp), contentAlignment = Alignment.Center) {
        Text(
            text,
            color = HaraanColors.TextSecondary,
            fontSize = 12.sp,
            textAlign = TextAlign.Center,
            modifier = Modifier
                .clip(RoundedCornerShape(50))
                .background(HaraanColors.Field)
                .padding(horizontal = 12.dp, vertical = 5.dp),
        )
    }
}

private val URL_PATTERN = android.util.Patterns.WEB_URL

/**
 * A message body with its web links made tappable. Matched with Android's own WEB_URL
 * pattern; a bare "www.x.com" gets https:// so the link actually opens.
 */
internal fun linkified(body: String, mine: Boolean): androidx.compose.ui.text.AnnotatedString {
    val matcher = URL_PATTERN.matcher(body)
    if (!matcher.find()) return androidx.compose.ui.text.AnnotatedString(body)
    matcher.reset()
    val linkStyle = TextLinkStyles(
        style = SpanStyle(
            color = if (mine) Color.White else HaraanColors.EventsBlue,
            textDecoration = TextDecoration.Underline,
        ),
    )
    return buildAnnotatedString {
        var last = 0
        while (matcher.find()) {
            val start = matcher.start()
            val end = matcher.end()
            val raw = body.substring(start, end)
            // Skip things like "6.30" that only look like a domain to the pattern.
            if (!raw.contains('.') || raw.none { it.isLetter() }) continue
            append(body.substring(last, start))
            val url = if (raw.startsWith("http://", true) || raw.startsWith("https://", true)) raw else "https://$raw"
            pushLink(androidx.compose.ui.text.LinkAnnotation.Url(url, linkStyle))
            append(raw)
            pop()
            last = end
        }
        append(body.substring(last))
    }
}
