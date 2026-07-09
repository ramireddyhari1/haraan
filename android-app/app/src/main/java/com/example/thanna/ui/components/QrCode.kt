package com.example.thanna.ui.components

import android.graphics.Bitmap
import android.graphics.Color
import androidx.compose.foundation.Image
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.asImageBitmap
import com.google.zxing.BarcodeFormat
import com.google.zxing.EncodeHintType
import com.google.zxing.qrcode.QRCodeWriter
import com.google.zxing.qrcode.decoder.ErrorCorrectionLevel

/**
 * Renders [content] as a QR code. The bitmap is generated once per content
 * value (cached with remember). Used for attendee ticket QRs the host scanner
 * reads — the payload is `haraan:ticket:<code>`.
 */
@Composable
fun QrImage(
  content: String,
  modifier: Modifier = Modifier,
  sizePx: Int = 640,
) {
  val bitmap = remember(content, sizePx) { generateQrBitmap(content, sizePx) }
  if (bitmap != null) {
    Image(bitmap = bitmap.asImageBitmap(), contentDescription = "Ticket QR code", modifier = modifier)
  }
}

/** Encode [content] into a square QR [Bitmap], or null if encoding fails. */
fun generateQrBitmap(content: String, size: Int): Bitmap? = try {
  val hints = mapOf(
    EncodeHintType.MARGIN to 1,
    // Level H (30% recovery) so a centered brand logo occluding the middle can't break a scan.
    EncodeHintType.ERROR_CORRECTION to ErrorCorrectionLevel.H,
  )
  val matrix = QRCodeWriter().encode(content, BarcodeFormat.QR_CODE, size, size, hints)
  val w = matrix.width
  val h = matrix.height
  val pixels = IntArray(w * h)
  for (y in 0 until h) {
    val offset = y * w
    for (x in 0 until w) {
      pixels[offset + x] = if (matrix[x, y]) Color.BLACK else Color.WHITE
    }
  }
  Bitmap.createBitmap(w, h, Bitmap.Config.RGB_565).apply {
    setPixels(pixels, 0, w, 0, 0, w, h)
  }
} catch (_: Exception) {
  null
}
