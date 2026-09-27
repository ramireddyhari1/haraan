package com.haraan.partner.reports

import android.content.Context
import android.graphics.Color
import android.graphics.Paint
import android.graphics.RectF
import android.graphics.Typeface
import android.graphics.pdf.PdfDocument
import com.haraan.partner.ReportData
import com.haraan.partner.ReportRow
import com.haraan.partner.formatInr
import java.io.File
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/** The figures a report leads with — the same numbers on screen and on paper. */
data class ReportSummary(
    val gross: Double,
    val collected: Double,
    val due: Double,
    val sales: Int,
    val cancelled: Int,
    val walkIns: Int,
    val online: Int,
    val checkedIn: Int,
)

/**
 * What a booking was, short: "VADI · 6:00 AM – 7:00 AM". The slot label repeats the
 * weekday (the date column already says it); the venue or event name is added only when
 * the report spans more than one, where it is the thing that tells rows apart.
 */
fun bookingLabel(r: ReportRow, withItem: Boolean): String {
    val parts = r.slot.split("·").map { it.trim() }.filter { it.isNotEmpty() }
    val weekdays = setOf("monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday", "every day", "today")
    val slot = parts.filterNot { it.lowercase() in weekdays }.joinToString(" · ")
    return listOfNotNull(r.item.takeIf { withItem || slot.isBlank() }?.ifBlank { null }, slot.ifBlank { null })
        .joinToString(" · ")
}

/** True when the rows come from more than one venue or event. */
fun spansItems(rows: List<ReportRow>): Boolean = rows.map { it.item }.distinct().size > 1

fun summarize(rows: List<ReportRow>): ReportSummary {
    val sales = rows.filter { it.isSale }
    val gross = sales.sumOf { it.amount }
    val collected = sales.sumOf { it.amountPaid.coerceAtMost(it.amount) }
    return ReportSummary(
        gross = gross,
        collected = collected,
        due = (gross - collected).coerceAtLeast(0.0),
        sales = sales.size,
        cancelled = rows.count { it.status.lowercase() in setOf("cancelled", "refunded") },
        walkIns = sales.count { it.isWalkIn },
        online = sales.count { !it.isWalkIn },
        checkedIn = sales.count { it.checkedIn > 0 },
    )
}

/**
 * Draws the booking report as an A4 PDF: a branded header, the money summary, and
 * every booking as a table that runs over as many pages as it needs, each page footed
 * with the page number. Built on the phone with Android's own PdfDocument — nothing
 * extra to ship, and it works offline once the rows are in.
 */
object ReportPdf {

    private const val W = 595 // A4 in PostScript points
    private const val H = 842
    private const val M = 36f
    private const val ROW_H = 22f
    private const val TABLE_HEAD_H = 24f
    private const val FOOTER_H = 34f

    private val BLUE = Color.rgb(29, 78, 216)
    private val INK = Color.rgb(11, 18, 32)
    private val MUTED = Color.rgb(107, 118, 136)
    private val HAIRLINE = Color.rgb(229, 231, 235)
    private val ZEBRA = Color.rgb(247, 249, 252)
    private val GREEN = Color.rgb(22, 163, 74)
    private val RED = Color.rgb(220, 38, 38)
    private val AMBER = Color.rgb(217, 119, 6)

    /** Columns: title, share of the table width, right-aligned? */
    private val COLS = listOf(
        Triple("Date", 0.13f, false),
        Triple("Customer", 0.22f, false),
        Triple("Booking", 0.29f, false),
        Triple("Channel", 0.11f, false),
        Triple("Status", 0.12f, false),
        Triple("Amount", 0.13f, true),
    )

    fun build(context: Context, data: ReportData, rangeLabel: String, basisLabel: String): File {
        val doc = PdfDocument()
        val summary = summarize(data.rows)
        val withItem = spansItems(data.rows)
        val rows = data.rows.sortedWith(compareBy({ it.slotDate.ifBlank { it.bookedAt } }, { it.bookedAt }))

        // Page 1 carries the header + summary; the rest are table only.
        val firstPageRows = ((H - M - FOOTER_H - 250f - TABLE_HEAD_H) / ROW_H).toInt().coerceAtLeast(1)
        val otherPageRows = ((H - M * 2 - FOOTER_H - TABLE_HEAD_H) / ROW_H).toInt()
        val pages = mutableListOf<List<ReportRow>>()
        pages += rows.take(firstPageRows)
        var rest = rows.drop(firstPageRows)
        while (rest.isNotEmpty()) {
            pages += rest.take(otherPageRows)
            rest = rest.drop(otherPageRows)
        }
        val generated = SimpleDateFormat("d MMM yyyy, h:mm a", Locale.ENGLISH).format(Date())

        pages.forEachIndexed { index, pageRows ->
            val page = doc.startPage(PdfDocument.PageInfo.Builder(W, H, index + 1).create())
            val c = page.canvas
            var y: Float

            if (index == 0) {
                y = drawHeader(c, data.partner, rangeLabel, basisLabel)
                y = drawSummary(c, y, summary)
            } else {
                y = M
            }

            y = drawTableHead(c, y)
            if (pageRows.isEmpty()) {
                c.drawText("No bookings in this period.", M + 8f, y + 18f, paint(11f, MUTED))
            }
            pageRows.forEachIndexed { i, r -> drawRow(c, y + i * ROW_H, r, zebra = i % 2 == 1, withItem = withItem) }

            // Footer
            val fy = H - M + 6f
            c.drawLine(M, fy - 16f, W - M, fy - 16f, line(HAIRLINE))
            c.drawText("Generated $generated · Haraan Partner", M, fy, paint(8.5f, MUTED))
            val pageLabel = "Page ${index + 1} of ${pages.size}"
            val pp = paint(8.5f, MUTED)
            c.drawText(pageLabel, W - M - pp.measureText(pageLabel), fy, pp)

            doc.finishPage(page)
        }

        val dir = File(context.cacheDir, "reports").apply { mkdirs() }
        val file = File(dir, "haraan_bookings_${data.from}_to_${data.to}.pdf")
        file.outputStream().use { doc.writeTo(it) }
        doc.close()
        return file
    }

    private fun drawHeader(c: android.graphics.Canvas, partner: String, range: String, basis: String): Float {
        val band = 118f
        c.drawRect(0f, 0f, W.toFloat(), band, fill(BLUE))
        c.drawText("HARAAN", M, 40f, paint(9f, Color.argb(200, 255, 255, 255), bold = true, spacing = 0.28f))
        c.drawText("Booking report", M, 72f, paint(24f, Color.WHITE, bold = true))
        val sub = listOf(partner.ifBlank { null }, range).filterNotNull().joinToString("  ·  ")
        c.drawText(ellipsize(sub, paint(11f, Color.argb(230, 255, 255, 255)), W - M * 2), M, 96f, paint(11f, Color.argb(230, 255, 255, 255)))
        val bp = paint(9f, Color.argb(210, 255, 255, 255))
        c.drawText(basis, W - M - bp.measureText(basis), 40f, bp)
        return band + 26f
    }

    private fun drawSummary(c: android.graphics.Canvas, top: Float, s: ReportSummary): Float {
        // Three figures, divided by hairlines rather than boxed.
        val colW = (W - M * 2) / 3f
        val figures = listOf(
            Triple("GROSS SALES", "₹" + formatInr(s.gross), INK),
            Triple("COLLECTED", "₹" + formatInr(s.collected), GREEN),
            Triple("STILL DUE", "₹" + formatInr(s.due), if (s.due > 0) RED else INK),
        )
        figures.forEachIndexed { i, (label, value, color) ->
            val x = M + i * colW + if (i == 0) 0f else 16f
            c.drawText(label, x, top + 12f, paint(8.5f, MUTED, bold = true, spacing = 0.12f))
            c.drawText(value, x, top + 40f, paint(20f, color, bold = true))
            if (i > 0) c.drawLine(M + i * colW, top, M + i * colW, top + 46f, line(HAIRLINE))
        }

        // Collected share of gross.
        val barY = top + 60f
        val barW = W - M * 2
        c.drawRoundRect(RectF(M, barY, M + barW, barY + 6f), 3f, 3f, fill(HAIRLINE))
        if (s.gross > 0) {
            val share = (s.collected / s.gross).toFloat().coerceIn(0f, 1f)
            c.drawRoundRect(RectF(M, barY, M + barW * share, barY + 6f), 3f, 3f, fill(GREEN))
        }

        val facts = buildString {
            append("${s.sales} booking${if (s.sales == 1) "" else "s"}")
            append("  ·  ${s.online} online, ${s.walkIns} walk-in")
            append("  ·  ${s.checkedIn} checked in")
            if (s.cancelled > 0) append("  ·  ${s.cancelled} cancelled")
        }
        c.drawText(facts, M, barY + 26f, paint(10f, INK))
        return barY + 48f
    }

    private fun drawTableHead(c: android.graphics.Canvas, top: Float): Float {
        c.drawRect(M, top, W - M, top + TABLE_HEAD_H, fill(ZEBRA))
        c.drawLine(M, top + TABLE_HEAD_H, W - M, top + TABLE_HEAD_H, line(HAIRLINE))
        var x = M + 8f
        val tableW = W - M * 2 - 16f
        for ((title, share, right) in COLS) {
            val w = tableW * share
            val p = paint(8.5f, MUTED, bold = true, spacing = 0.08f)
            val t = title.uppercase()
            c.drawText(t, if (right) x + w - p.measureText(t) else x, top + 16f, p)
            x += w
        }
        return top + TABLE_HEAD_H
    }

    private fun drawRow(c: android.graphics.Canvas, top: Float, r: ReportRow, zebra: Boolean, withItem: Boolean) {
        if (zebra) c.drawRect(M, top, W - M, top + ROW_H, fill(ZEBRA))
        val status = r.status.lowercase()
        val statusColor = when {
            status in setOf("cancelled", "refunded", "failed", "expired") -> RED
            r.paymentStatus.equals("paid", true) -> GREEN
            else -> AMBER
        }
        val statusText = when {
            status in setOf("cancelled", "refunded", "failed", "expired") -> status.replaceFirstChar { it.uppercase() }
            r.paymentStatus.equals("paid", true) -> "Paid"
            r.paymentStatus.equals("part", true) -> "Part paid"
            status == "pending" -> "Pending"
            else -> "Unpaid"
        }
        val values = listOf(
            shortDate(r.slotDate.ifBlank { r.bookedAt.take(10) }) to INK,
            r.customer.ifBlank { "Guest" } to INK,
            bookingLabel(r, withItem) to INK,
            (if (r.isWalkIn) "Walk-in" else r.channel.replaceFirstChar { it.uppercase() }) to MUTED,
            statusText to statusColor,
            "₹" + formatInr(r.amount) to INK,
        )
        var x = M + 8f
        val tableW = W - M * 2 - 16f
        values.forEachIndexed { i, (text, color) ->
            val (_, share, right) = COLS[i]
            val w = tableW * share
            val p = paint(9.5f, color, bold = i == 5 || i == 4)
            val t = ellipsize(text, p, w - 6f)
            c.drawText(t, if (right) x + w - p.measureText(t) else x, top + 15f, p)
            x += w
        }
        c.drawLine(M, top + ROW_H, W - M, top + ROW_H, line(HAIRLINE))
    }

    private fun shortDate(iso: String): String = runCatching {
        val d = SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(iso.take(10)) ?: return iso
        SimpleDateFormat("d MMM yy", Locale.ENGLISH).format(d)
    }.getOrDefault(iso)

    private fun ellipsize(text: String, p: Paint, maxW: Float): String {
        if (p.measureText(text) <= maxW) return text
        val n = p.breakText(text, true, maxW - p.measureText("…"), null)
        return text.take(n.coerceAtLeast(0)).trimEnd() + "…"
    }

    private fun paint(size: Float, color: Int, bold: Boolean = false, spacing: Float = 0f) = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        textSize = size
        this.color = color
        typeface = Typeface.create(Typeface.SANS_SERIF, if (bold) Typeface.BOLD else Typeface.NORMAL)
        letterSpacing = spacing
    }

    private fun fill(color: Int) = Paint(Paint.ANTI_ALIAS_FLAG).apply { this.color = color; style = Paint.Style.FILL }

    private fun line(color: Int) = Paint(Paint.ANTI_ALIAS_FLAG).apply { this.color = color; strokeWidth = 0.7f }
}
