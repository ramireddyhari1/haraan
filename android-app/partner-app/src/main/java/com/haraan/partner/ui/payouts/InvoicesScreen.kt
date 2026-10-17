package com.haraan.partner.ui.payouts

import android.annotation.SuppressLint
import android.content.Context
import android.content.Intent
import android.print.PrintAttributes
import android.print.PrintManager
import android.webkit.WebResourceRequest
import android.webkit.WebResourceResponse
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Toast
import androidx.activity.compose.BackHandler
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.rotate
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.FileProvider
import com.haraan.partner.BuildConfig
import com.haraan.partner.GstState
import com.haraan.partner.InvoiceQuarter
import com.haraan.partner.InvoiceSettlement
import com.haraan.partner.InvoicesPage
import com.haraan.partner.PartnerApi
import com.haraan.partner.TaxProfile
import com.haraan.partner.formatInr
import com.haraan.partner.ui.Haptics
import com.haraan.partner.ui.components.pressScale
import kotlinx.coroutines.launch
import java.io.File

private val Page = Color(0xFFF7F8FB)
private val Ink = Color(0xFF0F172A)
private val Ink2 = Color(0xFF334155)
private val Muted = Color(0xFF64748B)
private val Faint = Color(0xFF94A3B8)
private val Hair = Color(0x140F172A)
private val Brand = Color(0xFF2563EB)
private val BrandWash = Color(0xFFEEF3FE)
private val Good = Color(0xFF15803D)
private val Wait = Color(0xFFB45309)
private val Bad = Color(0xFFDC2626)

/** The document kinds, in the order Playo-style lists show them. */
private enum class Kind(val key: String, val label: String, val short: String) {
    Invoice("invoice", "Tax invoices", "Tax invoice"),
    Tds("tds", "TDS certificates", "TDS"),
    Statement("settlement", "Statements", "Statement"),
}

private sealed interface DocsLoad {
    data object Loading : DocsLoad
    data class Failed(val message: String) : DocsLoad
    data class Ready(val page: InvoicesPage) : DocsLoad
}

/** A document opened in the viewer: where it lives and what the saved PDF is called. */
private data class OpenDoc(val title: String, val path: String, val fileName: String)

/**
 * Invoices: every document Haraan issues for the venue's settlements — the settlement
 * statement, the tax invoice for Haraan's commission, the TDS / TCS certificate, the
 * quarter's TDS summary and the official Form 16A — plus the tax details they're billed to.
 * Each document opens here and saves as a PDF through Android's own Save as PDF.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun InvoicesScreen(api: PartnerApi, token: String, onBack: () -> Unit) {
    var reload by remember { mutableIntStateOf(0) }
    var filter by remember { mutableStateOf<Kind?>(null) }
    var open by remember { mutableStateOf<OpenDoc?>(null) }
    var editingTax by remember { mutableStateOf(false) }
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val state by produceState<DocsLoad>(DocsLoad.Loading, reload) {
        val fresh = try { DocsLoad.Ready(api.invoices(token)) } catch (e: Exception) { DocsLoad.Failed(e.message ?: "Couldn't load invoices") }
        value = if (fresh is DocsLoad.Failed && value is DocsLoad.Ready) value else fresh
    }

    BackHandler(enabled = open == null) { onBack() }

    // Files that aren't documents to read (CSV, Form 16A PDF) go straight to the share sheet,
    // where the partner saves them to Files, Drive or sends them to their accountant.
    fun fetchAndShare(path: String, name: String, mime: String) {
        Toast.makeText(context, "Downloading…", Toast.LENGTH_SHORT).show()
        scope.launch {
            try {
                val bytes = api.download(token, path)
                val dir = File(context.cacheDir, "documents").apply { mkdirs() }
                val file = File(dir, name).apply { writeBytes(bytes) }
                shareFile(context, file, mime)
            } catch (e: Exception) {
                Toast.makeText(context, e.message ?: "Couldn't download", Toast.LENGTH_LONG).show()
            }
        }
    }

    Box(Modifier.fillMaxSize()) {
    Scaffold(
        containerColor = Page,
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Page, scrolledContainerColor = Page),
                title = { Text("Invoices", fontWeight = FontWeight.Bold, color = Ink, fontSize = 18.sp) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = Ink) }
                },
            )
        },
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            when (val s = state) {
                DocsLoad.Loading -> CircularProgressIndicator(color = Brand, strokeWidth = 2.5.dp, modifier = Modifier.align(Alignment.Center).size(28.dp))
                is DocsLoad.Failed -> Column(Modifier.align(Alignment.Center).padding(32.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                    Text("Couldn't load invoices", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Ink)
                    Spacer(Modifier.height(4.dp))
                    Text(s.message, fontSize = 13.sp, color = Muted, textAlign = TextAlign.Center)
                    Spacer(Modifier.height(14.dp))
                    SolidButton("Try again") { reload++ }
                }
                is DocsLoad.Ready -> {
                    val p = s.page
                    if (!p.enabled) {
                        Column(Modifier.align(Alignment.Center).padding(32.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                            Canvas(Modifier.size(width = 92.dp, height = 72.dp)) { paperStack() }
                            Spacer(Modifier.height(12.dp))
                            Text("Invoices are paused", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Ink)
                            Text("Haraan has turned document downloads off for now. Your settlements are unaffected.", fontSize = 13.sp, color = Muted, textAlign = TextAlign.Center)
                        }
                        return@Box
                    }
                    val rows = p.settlements.filter { filter == null || it.docs.contains(filter!!.key) }
                    LazyColumn(
                        Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 4.dp, bottom = 32.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp),
                    ) {
                        item { TaxCard(p.taxProfile, p.canEditTax) { editingTax = true } }
                        item { Filters(filter) { filter = it } }
                        if (p.settlements.isEmpty()) {
                            item { EmptyCard() }
                        } else if (rows.isEmpty()) {
                            item {
                                Text(
                                    when (filter) {
                                        Kind.Invoice -> "No tax invoices yet. One is issued whenever Haraan keeps a commission from a settlement."
                                        Kind.Tds -> "No tax has been withheld from your settlements."
                                        else -> "Nothing here yet."
                                    },
                                    fontSize = 13.sp, color = Muted, modifier = Modifier.padding(horizontal = 4.dp, vertical = 8.dp),
                                )
                            }
                        }
                        rows.groupBy { it.month }.forEach { (month, list) ->
                            item(key = "m-$month") {
                                Text(month.uppercase(), fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Muted, letterSpacing = 0.8.sp, modifier = Modifier.padding(start = 4.dp, top = 6.dp))
                            }
                            item(key = "g-$month") {
                                Column(Modifier.fillMaxWidth().card()) {
                                    list.forEachIndexed { i, b ->
                                        if (i > 0) Box(Modifier.padding(start = 70.dp).fillMaxWidth().height(1.dp).background(Hair))
                                        SettlementRow(b, filter) { kind ->
                                            if (kind == "csv") {
                                                fetchAndShare("/api/partner/invoices/${b.id}/csv", "Haraan-Settlement-${b.id}-bookings.csv", "text/csv")
                                            } else {
                                                open = docFor(b, kind)
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        if (p.quarters.isNotEmpty() && (filter == null || filter == Kind.Tds)) {
                            item(key = "q-head") {
                                Text("TDS BY QUARTER", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Muted, letterSpacing = 0.8.sp, modifier = Modifier.padding(start = 4.dp, top = 6.dp))
                            }
                            item(key = "q-list") {
                                Column(Modifier.fillMaxWidth().card()) {
                                    p.quarters.forEachIndexed { i, q ->
                                        if (i > 0) Box(Modifier.padding(start = 18.dp).fillMaxWidth().height(1.dp).background(Hair))
                                        QuarterRow(
                                            q,
                                            onSummary = {
                                                open = OpenDoc(
                                                    "TDS summary · " + q.label,
                                                    "/api/partner/invoices/quarter/${q.financialYear}/${q.quarter}",
                                                    "Haraan-TDS-Summary-${q.financialYear}-Q${q.quarter}.pdf",
                                                )
                                            },
                                            onForm16a = q.form16aId?.let { id ->
                                                { fetchAndShare("/api/partner/invoices/form16a/$id", "Form16A-${q.financialYear}-Q${q.quarter}.pdf", "application/pdf") }
                                            },
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    open?.let { d -> DocumentViewer(api, token, d, onClose = { open = null }) }
    }

    if (editingTax) {
        val p = (state as? DocsLoad.Ready)?.page
        if (p != null) {
            TaxSheet(api, token, p.taxProfile, p.states, onDismiss = { editingTax = false }, onSaved = { editingTax = false; reload++ })
        }
    }
}

private fun docFor(b: InvoiceSettlement, kind: String): OpenDoc = when (kind) {
    "invoice" -> OpenDoc("Tax invoice " + (b.invoiceNumber ?: "#${b.id}"), "/api/partner/invoices/${b.id}/invoice", "Haraan-Tax-Invoice-" + (b.invoiceNumber?.replace(Regex("[^A-Za-z0-9]+"), "-") ?: "S${b.id}") + ".pdf")
    "tds" -> OpenDoc("TDS / TCS certificate", "/api/partner/invoices/${b.id}/tds", "Haraan-TDS-Certificate-S${b.id}.pdf")
    else -> OpenDoc("Settlement statement", "/api/partner/invoices/${b.id}/settlement", "Haraan-Settlement-Statement-S${b.id}.pdf")
}

// ─── Tax details ────────────────────────────────────────────────────────────

/**
 * Who the documents are billed to. The PAN line is the one that costs money when it's
 * missing, so it says so in plain words.
 */
@Composable
private fun TaxCard(t: TaxProfile, canEdit: Boolean, onEdit: () -> Unit) {
    Column(Modifier.fillMaxWidth().card().padding(18.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text("BILLED TO", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Muted, letterSpacing = 0.8.sp)
                Spacer(Modifier.height(3.dp))
                Text(t.legalName ?: "Your business name", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = if (t.legalName == null) Faint else Ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
            if (canEdit) {
                Text(
                    if (t.pan == null && t.gstin == null) "Add" else "Edit",
                    fontSize = 14.sp, fontWeight = FontWeight.Bold, color = Brand,
                    modifier = Modifier.clip(RoundedCornerShape(10.dp)).background(BrandWash).clickable(onClick = onEdit).padding(horizontal = 14.dp, vertical = 8.dp),
                )
            }
        }
        Spacer(Modifier.height(12.dp))
        TaxLine("PAN", t.pan, if (t.pan == null) "Not added — TDS is withheld at a higher rate" else null)
        TaxLine("GSTIN", t.gstin, if (t.gstin == null) "Not registered" else null)
        if (t.state != null) TaxLine("State", t.state, null)
        if (!canEdit) {
            Spacer(Modifier.height(8.dp))
            Text("Only the venue owner can change these.", fontSize = 12.sp, color = Muted)
        }
    }
}

@Composable
private fun TaxLine(label: String, value: String?, missing: String?) {
    Row(Modifier.fillMaxWidth().padding(vertical = 3.dp), verticalAlignment = Alignment.CenterVertically) {
        Text(label, fontSize = 13.sp, color = Muted, modifier = Modifier.width(64.dp))
        Text(
            value ?: missing ?: "—",
            fontSize = 13.5.sp,
            fontWeight = if (value != null) FontWeight.SemiBold else FontWeight.Normal,
            color = when { value != null -> Ink; label == "PAN" -> Wait; else -> Faint },
            style = TextStyle(fontFeatureSettings = "tnum", letterSpacing = if (value != null) 0.4.sp else 0.sp),
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun TaxSheet(api: PartnerApi, token: String, current: TaxProfile, states: List<GstState>, onDismiss: () -> Unit, onSaved: () -> Unit) {
    val sheet = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    val scope = rememberCoroutineScope()
    val view = LocalView.current
    var legal by remember { mutableStateOf(current.legalName ?: "") }
    var gstin by remember { mutableStateOf(current.gstin ?: "") }
    var pan by remember { mutableStateOf(current.pan ?: "") }
    var address by remember { mutableStateOf(current.address ?: "") }
    var stateCode by remember { mutableStateOf(current.stateCode) }
    var stateMenu by remember { mutableStateOf(false) }
    var saving by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    // A GSTIN starts with its state code and carries the PAN inside it: fill both as it's typed.
    val gstinClean = gstin.uppercase().filter { it.isLetterOrDigit() }
    val gstinState = gstinClean.take(2).takeIf { gstinClean.length >= 2 }?.let { c -> states.firstOrNull { it.code == c } }

    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = sheet, containerColor = Color.White) {
        Column(
            Modifier.fillMaxWidth().verticalScroll(rememberScrollState()).padding(horizontal = 20.dp).navigationBarsPadding().imePadding(),
        ) {
            Text("Tax details", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = Ink)
            Text("Printed on your tax invoices and TDS certificates. Settlements not yet paid use the new details.", fontSize = 13.sp, color = Muted, lineHeight = 18.sp)
            Spacer(Modifier.height(16.dp))
            Field("Legal business name", legal, { legal = it }, capitalization = KeyboardCapitalization.Words)
            Field("GSTIN (if registered)", gstin, { v -> gstin = v.uppercase().take(15); if (gstinState != null) stateCode = gstinState.code }, placeholder = "37ABCDE1234F1Z5", capitalization = KeyboardCapitalization.Characters)
            Field(
                "PAN", if (gstinClean.length == 15 && pan.isBlank()) gstinClean.substring(2, 12) else pan, { pan = it.uppercase().take(10) },
                placeholder = "ABCDE1234F", capitalization = KeyboardCapitalization.Characters,
                hint = "Without a PAN, TDS on each settlement is withheld at a higher rate.",
            )
            Box {
                Field(
                    "State", states.firstOrNull { it.code == (gstinState?.code ?: stateCode) }?.name ?: "", {},
                    readOnly = true, placeholder = "Choose your state", onTap = { if (gstinState == null) stateMenu = true },
                    hint = if (gstinState != null) "Taken from your GSTIN." else null,
                )
                DropdownMenu(expanded = stateMenu, onDismissRequest = { stateMenu = false }, modifier = Modifier.height(320.dp)) {
                    states.forEach { st -> DropdownMenuItem(text = { Text(st.name) }, onClick = { stateCode = st.code; stateMenu = false }) }
                }
            }
            Field("Billing address", address, { address = it.take(300) }, singleLine = false)
            error?.let {
                Spacer(Modifier.height(4.dp))
                Text(it, fontSize = 13.sp, color = Bad)
            }
            Spacer(Modifier.height(14.dp))
            SolidButton(if (saving) "Saving…" else "Save", Modifier.fillMaxWidth(), enabled = !saving) {
                saving = true; error = null
                scope.launch {
                    try {
                        val panOut = pan.ifBlank { if (gstinClean.length == 15) gstinClean.substring(2, 12) else "" }
                        api.saveTaxProfile(token, TaxProfile(legal.trim(), gstinClean, panOut.trim(), address.trim(), gstinState?.code ?: stateCode))
                        Haptics.confirm(view)
                        onSaved()
                    } catch (e: Exception) {
                        error = e.message ?: "Couldn't save"
                        saving = false
                    }
                }
            }
            Spacer(Modifier.height(18.dp))
        }
    }
}

@Composable
private fun Field(
    label: String,
    value: String,
    onChange: (String) -> Unit,
    placeholder: String? = null,
    hint: String? = null,
    capitalization: KeyboardCapitalization = KeyboardCapitalization.None,
    singleLine: Boolean = true,
    readOnly: Boolean = false,
    onTap: (() -> Unit)? = null,
) {
    Column(Modifier.fillMaxWidth().padding(bottom = 10.dp)) {
        Text(label, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Ink2)
        Spacer(Modifier.height(5.dp))
        Box {
            OutlinedTextField(
                value = value,
                onValueChange = onChange,
                readOnly = readOnly,
                singleLine = singleLine,
                minLines = if (singleLine) 1 else 2,
                placeholder = placeholder?.let { { Text(it, color = Faint) } },
                keyboardOptions = KeyboardOptions(capitalization = capitalization),
                shape = RoundedCornerShape(12.dp),
                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Brand, unfocusedBorderColor = Color(0x260F172A), cursorColor = Brand),
                textStyle = TextStyle(fontSize = 15.sp, color = Ink, letterSpacing = if (capitalization == KeyboardCapitalization.Characters) 0.6.sp else 0.sp),
                modifier = Modifier.fillMaxWidth(),
            )
            if (onTap != null) Box(Modifier.matchParentSize().clickable(onClick = onTap))
        }
        hint?.let { Text(it, fontSize = 12.sp, color = Muted, modifier = Modifier.padding(top = 4.dp)) }
    }
}

// ─── The list ───────────────────────────────────────────────────────────────

@Composable
private fun Filters(selected: Kind?, onPick: (Kind?) -> Unit) {
    Row(Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        Chip("All", selected == null) { onPick(null) }
        Kind.entries.forEach { k -> Chip(k.label, selected == k) { onPick(k) } }
    }
}

@Composable
private fun Chip(text: String, on: Boolean, onClick: () -> Unit) {
    Text(
        text,
        fontSize = 13.sp,
        fontWeight = FontWeight.SemiBold,
        color = if (on) Color.White else Ink2,
        modifier = Modifier
            .clip(RoundedCornerShape(50))
            .background(if (on) Brand else Color.White)
            .border(1.dp, if (on) Brand else Color(0x1F0F172A), RoundedCornerShape(50))
            .clickable(onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 8.dp),
    )
}

/**
 * One settlement: when, what reached the bank, what was kept back, and its documents.
 * With a type filter on, the row leads with that one document instead.
 */
@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun SettlementRow(b: InvoiceSettlement, filter: Kind?, onDoc: (String) -> Unit) {
    val (day, mon) = dayMonth(b.date)
    val tone = when (b.status) { "Paid" -> Good; "Cancelled" -> Bad; else -> Wait }
    Column(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 14.dp)) {
        Row(verticalAlignment = Alignment.Top) {
            Column(Modifier.width(40.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                Text(day, fontSize = 19.sp, lineHeight = 21.sp, fontWeight = FontWeight.Bold, color = Ink, style = TextStyle(fontFeatureSettings = "tnum"))
                Text(mon, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = Muted)
            }
            Spacer(Modifier.width(14.dp))
            Column(Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        when (filter) {
                            Kind.Invoice -> b.invoiceNumber ?: "Tax invoice"
                            Kind.Tds -> "TDS ₹" + money(b.tds + b.tcs)
                            else -> "₹" + money(b.net)
                        },
                        fontSize = 15.5.sp, fontWeight = FontWeight.Bold, color = Ink, style = TextStyle(fontFeatureSettings = "tnum"),
                        modifier = Modifier.weight(1f, fill = false),
                    )
                    Spacer(Modifier.width(8.dp))
                    Text(b.status, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = tone)
                }
                Text(
                    when (filter) {
                        Kind.Invoice -> "Commission ₹" + money(b.commission) + (if (b.commissionGst > 0) " + GST ₹" + money(b.commissionGst) else "")
                        Kind.Tds -> listOfNotNull(
                            if (b.tds > 0) "194-O ₹" + money(b.tds) else null,
                            if (b.tcs > 0) "GST TCS ₹" + money(b.tcs) else null,
                        ).joinToString(" · ")
                        else -> if (b.deducted > 0) "of ₹" + money(b.gross) + " · ₹" + money(b.deducted) + " deducted" else "Nothing deducted"
                    },
                    fontSize = 12.5.sp, color = Muted, maxLines = 1, overflow = TextOverflow.Ellipsis,
                )
                b.period?.let { Text(it, fontSize = 12.sp, color = Faint, maxLines = 1) }
            }
        }
        Spacer(Modifier.height(10.dp))
        FlowRow(Modifier.padding(start = 54.dp), horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            val keys = if (filter != null) listOf(filter.key) else b.docs
            keys.filter { it in b.docs }.forEach { key ->
                DocPill(
                    when (key) { "invoice" -> "Tax invoice"; "tds" -> "TDS certificate"; "csv" -> "Bookings CSV"; else -> "Statement" },
                    sheet = key == "csv",
                ) { onDoc(key) }
            }
        }
    }
}

@Composable
private fun QuarterRow(q: InvoiceQuarter, onSummary: () -> Unit, onForm16a: (() -> Unit)?) {
    Column(Modifier.fillMaxWidth().padding(horizontal = 18.dp, vertical = 14.dp)) {
        Text(q.label, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink)
        Text(
            listOfNotNull(
                "TDS ₹" + money(q.tds),
                if (q.tcs > 0) "GST TCS ₹" + money(q.tcs) else null,
                "${q.settlements} settlement" + if (q.settlements == 1) "" else "s",
            ).joinToString(" · "),
            fontSize = 12.5.sp, color = Muted,
        )
        Spacer(Modifier.height(10.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            DocPill("Summary", sheet = false, onClick = onSummary)
            if (onForm16a != null) {
                DocPill("Form 16A" + (q.form16aNo?.let { " · $it" } ?: ""), sheet = false, onClick = onForm16a)
            } else {
                Text("Form 16A after the quarter is filed", fontSize = 12.sp, color = Faint, modifier = Modifier.align(Alignment.CenterVertically))
            }
        }
    }
}

/** A document chip with a drawn page (or ruled sheet for the CSV) and a down arrow. */
@Composable
private fun DocPill(text: String, sheet: Boolean, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    Row(
        Modifier
            .pressScale(interaction, pressedScale = 0.96f)
            .clip(RoundedCornerShape(10.dp))
            .background(BrandWash)
            .clickable(interactionSource = interaction, indication = null, onClick = onClick)
            .padding(start = 10.dp, end = 12.dp, top = 8.dp, bottom = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Canvas(Modifier.size(width = 13.dp, height = 16.dp)) { if (sheet) sheetGlyph() else pageGlyph() }
        Spacer(Modifier.width(7.dp))
        Text(text, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Brand)
    }
}

@Composable
private fun EmptyCard() {
    Column(Modifier.fillMaxWidth().card().padding(24.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Canvas(Modifier.size(width = 92.dp, height = 72.dp)) { paperStack() }
        Spacer(Modifier.height(12.dp))
        Text("No invoices yet", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Ink)
        Spacer(Modifier.height(4.dp))
        Text(
            "Every settlement Haraan sends you comes with a statement here — plus a tax invoice for any commission, and a TDS certificate when tax is withheld.",
            fontSize = 13.sp, lineHeight = 18.sp, color = Muted, textAlign = TextAlign.Center,
        )
    }
}

// ─── Viewer ─────────────────────────────────────────────────────────────────

/**
 * The document, rendered by the server exactly as it prints, with Save as PDF going through
 * Android's print framework — the saved file is the same A4 page, not a screenshot.
 */
@OptIn(ExperimentalMaterial3Api::class)
@SuppressLint("SetJavaScriptEnabled")
@Composable
private fun DocumentViewer(api: PartnerApi, token: String, doc: OpenDoc, onClose: () -> Unit) {
    val context = LocalContext.current
    var loading by remember { mutableStateOf(true) }
    var failed by remember { mutableStateOf<String?>(null) }
    var web by remember { mutableStateOf<WebView?>(null) }
    BackHandler { onClose() }

    Box(Modifier.fillMaxSize().background(Color(0xFFEEF1F6))) {
        Scaffold(
            containerColor = Color(0xFFEEF1F6),
            topBar = {
                TopAppBar(
                    colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White),
                    title = { Text(doc.title, fontWeight = FontWeight.Bold, color = Ink, fontSize = 16.sp, maxLines = 1, overflow = TextOverflow.Ellipsis) },
                    navigationIcon = {
                        IconButton(onClick = onClose) { Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = Ink) }
                    },
                    actions = {
                        SolidButton("Save PDF", Modifier.padding(end = 12.dp), enabled = !loading && failed == null, compact = true) {
                            val w = web ?: return@SolidButton
                            val pm = context.getSystemService(Context.PRINT_SERVICE) as PrintManager
                            val name = doc.fileName.removeSuffix(".pdf")
                            pm.print(
                                name,
                                w.createPrintDocumentAdapter(name),
                                PrintAttributes.Builder().setMediaSize(PrintAttributes.MediaSize.ISO_A4).build(),
                            )
                        }
                    },
                )
            },
        ) { pad ->
            Box(Modifier.fillMaxSize().padding(pad)) {
                AndroidView(
                    modifier = Modifier.fillMaxSize(),
                    factory = { ctx ->
                        WebView(ctx).apply {
                            // The documents are plain HTML; nothing on them needs script.
                            settings.javaScriptEnabled = false
                            settings.allowFileAccess = false
                            settings.loadWithOverviewMode = true
                            settings.useWideViewPort = true
                            settings.builtInZoomControls = true
                            settings.displayZoomControls = false
                            webViewClient = object : WebViewClient() {
                                override fun onPageFinished(view: WebView?, url: String?) { loading = false }
                                override fun onReceivedHttpError(view: WebView?, request: WebResourceRequest?, errorResponse: WebResourceResponse?) {
                                    if (request?.isForMainFrame == true) {
                                        failed = when (errorResponse?.statusCode) {
                                            404 -> "This document isn’t available."
                                            401, 403 -> "You don’t have access to this document."
                                            else -> "Couldn’t open this document."
                                        }
                                    }
                                }
                                // Nothing on a document should navigate the viewer away.
                                override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean = true
                            }
                            loadUrl(
                                api.absoluteUrl(doc.path) + "?bare=1",
                                mapOf("Authorization" to "Bearer $token", "X-Partner-App-Version" to BuildConfig.VERSION_NAME),
                            )
                            web = this
                        }
                    },
                    onRelease = { it.destroy() },
                )
                if (loading && failed == null) CircularProgressIndicator(color = Brand, strokeWidth = 2.5.dp, modifier = Modifier.align(Alignment.Center).size(28.dp))
                failed?.let {
                    Column(Modifier.fillMaxSize().background(Page).padding(32.dp), verticalArrangement = Arrangement.Center, horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(it, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = Ink, textAlign = TextAlign.Center)
                    }
                }
            }
        }
    }
}

private fun shareFile(context: Context, file: File, mime: String) {
    val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
    val send = Intent(Intent.ACTION_SEND).apply {
        type = mime
        putExtra(Intent.EXTRA_STREAM, uri)
        putExtra(Intent.EXTRA_SUBJECT, file.nameWithoutExtension.replace('-', ' '))
        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
    }
    context.startActivity(Intent.createChooser(send, "Save or share").addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
}

// ─── Drawn bits ─────────────────────────────────────────────────────────────

/** A page with a folded corner and two text lines. */
private fun DrawScope.pageGlyph() {
    val st = Stroke(1.4.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round)
    val w = size.width
    val h = size.height
    val fold = w * 0.36f
    val p = Path().apply { moveTo(0f, 0f); lineTo(w - fold, 0f); lineTo(w, fold); lineTo(w, h); lineTo(0f, h); close() }
    drawPath(p, Color.White)
    drawPath(p, Brand, style = st)
    drawPath(Path().apply { moveTo(w - fold, 0f); lineTo(w - fold, fold); lineTo(w, fold) }, Brand, style = st)
    drawLine(Brand, Offset(w * 0.22f, h * 0.55f), Offset(w * 0.78f, h * 0.55f), 1.2.dp.toPx(), StrokeCap.Round)
    drawLine(Brand, Offset(w * 0.22f, h * 0.75f), Offset(w * 0.6f, h * 0.75f), 1.2.dp.toPx(), StrokeCap.Round)
}

/** A ruled sheet: the CSV. */
private fun DrawScope.sheetGlyph() {
    val st = Stroke(1.4.dp.toPx())
    drawRoundRect(Color.White, cornerRadius = CornerRadius(2.dp.toPx()))
    drawRoundRect(Brand, cornerRadius = CornerRadius(2.dp.toPx()), style = st)
    for (k in 1..2) drawLine(Brand, Offset(0f, size.height * k / 3f), Offset(size.width, size.height * k / 3f), 1.dp.toPx())
    drawLine(Brand, Offset(size.width * 0.42f, 0f), Offset(size.width * 0.42f, size.height), 1.dp.toPx())
}

/** Three fanned papers — the top one a tax invoice with a ₹ stamp. */
private fun DrawScope.paperStack() {
    val st = Stroke(1.6.dp.toPx(), cap = StrokeCap.Round, join = StrokeJoin.Round)
    val pw = size.width * 0.5f
    val ph = size.height * 0.86f
    fun paper(dx: Float, deg: Float, fill: Color, ink: Color) {
        val left = size.width / 2 - pw / 2 + dx
        val top = size.height * 0.06f
        rotate(deg, Offset(left + pw / 2, top + ph)) {
            drawRoundRect(fill, Offset(left, top), Size(pw, ph), CornerRadius(4.dp.toPx()))
            drawRoundRect(ink, Offset(left, top), Size(pw, ph), CornerRadius(4.dp.toPx()), style = st)
        }
    }
    paper(-size.width * 0.16f, -12f, Color(0xFFF1F5F9), Faint)
    paper(size.width * 0.16f, 10f, Color(0xFFF1F5F9), Faint)
    paper(0f, 0f, Color.White, Ink2)
    val left = size.width / 2 - pw / 2
    val top = size.height * 0.06f
    for (k in 0 until 3) {
        val y = top + ph * (0.22f + k * 0.13f)
        drawLine(Faint, Offset(left + pw * 0.16f, y), Offset(left + pw * (if (k == 2) 0.55f else 0.84f), y), 1.4.dp.toPx(), StrokeCap.Round)
    }
    drawCircle(Brand, pw * 0.17f, Offset(left + pw * 0.68f, top + ph * 0.76f), style = Stroke(1.6.dp.toPx()))
    val c = Offset(left + pw * 0.68f, top + ph * 0.76f)
    val r = pw * 0.08f
    drawLine(Brand, Offset(c.x - r, c.y - r), Offset(c.x + r, c.y - r), 1.4.dp.toPx(), StrokeCap.Round)
    drawLine(Brand, Offset(c.x - r, c.y - r * 0.3f), Offset(c.x + r, c.y - r * 0.3f), 1.4.dp.toPx(), StrokeCap.Round)
    drawPath(Path().apply { moveTo(c.x - r, c.y - r * 0.3f); quadraticTo(c.x + r * 0.6f, c.y, c.x - r * 0.6f, c.y + r * 0.3f); lineTo(c.x + r * 0.7f, c.y + r * 1.2f) }, Brand, style = Stroke(1.4.dp.toPx(), cap = StrokeCap.Round))
}

// ─── Small parts ────────────────────────────────────────────────────────────

private fun Modifier.card(): Modifier = this
    .shadow(6.dp, RoundedCornerShape(18.dp), clip = false, spotColor = Color(0x140F172A), ambientColor = Color(0x0A0F172A))
    .clip(RoundedCornerShape(18.dp))
    .background(Color.White)
    .border(1.dp, Hair, RoundedCornerShape(18.dp))

@Composable
private fun SolidButton(text: String, modifier: Modifier = Modifier, enabled: Boolean = true, compact: Boolean = false, onClick: () -> Unit) {
    val interaction = remember { MutableInteractionSource() }
    Box(
        modifier
            .pressScale(interaction, pressedScale = 0.97f)
            .clip(RoundedCornerShape(if (compact) 10.dp else 14.dp))
            .background(if (enabled) Brand else Color(0xFFBFD0F5))
            .clickable(interactionSource = interaction, indication = null, enabled = enabled) { onClick() }
            .padding(horizontal = if (compact) 14.dp else 20.dp, vertical = if (compact) 9.dp else 14.dp),
        contentAlignment = Alignment.Center,
    ) { Text(text, fontSize = if (compact) 13.5.sp else 15.sp, fontWeight = FontWeight.Bold, color = Color.White) }
}

/** ₹ amounts with paise only when there are any: 8,760 / 10.50. */
private fun money(v: Double): String {
    val paise = Math.round(v * 100) % 100
    return formatInr(v) + if (paise != 0L) "." + paise.toString().padStart(2, '0') else ""
}

private fun dayMonth(iso: String): Pair<String, String> {
    val d = iso.take(10).split("-")
    if (d.size != 3) return "—" to ""
    val months = listOf("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec")
    return (d[2].toIntOrNull()?.toString() ?: "—") to (d[1].toIntOrNull()?.let { months.getOrNull(it - 1) } ?: "")
}
