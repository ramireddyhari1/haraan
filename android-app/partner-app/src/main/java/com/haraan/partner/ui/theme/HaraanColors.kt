package com.haraan.partner.ui.theme

import androidx.compose.runtime.Immutable
import androidx.compose.ui.graphics.Color

@Immutable
data class HaraanColors(
    val canvas: Color = Color(0xFFF8FAFC),
    val surfaceDefault: Color = Color(0xFFFFFFFF),
    val surfaceSubtle: Color = Color(0xFFF1F5F9),
    val surfaceElevated: Color = Color(0xFFFFFFFF),
    val borderHairline: Color = Color(0x140F172A),
    val borderSubtle: Color = Color(0xFFE2E8F0),
    val borderFocus: Color = Color(0xFF0F172A),

    // Text & Inks
    val textPrimary: Color = Color(0xFF0F172A),
    val textSecondary: Color = Color(0xFF64748B),
    val textMuted: Color = Color(0xFF94A3B8),
    val textOnDark: Color = Color(0xFFFFFFFF),
    val textOnDarkMuted: Color = Color(0xFF94A3B8),

    // Brand & Action Accents (Disciplined - Turf Emerald)
    val emeraldPrimary: Color = Color(0xFF059669),
    val emeraldLight: Color = Color(0xFF10B981),
    val emeraldSubtle: Color = Color(0xFFECFDF5),
    val emeraldBorder: Color = Color(0xFFA7F3D0),

    // Executive Slate / Navy
    val slateDark: Color = Color(0xFF0F172A),
    val slateHeader: Color = Color(0xFF1E293B),
    val navyGradientTop: Color = Color(0xFF0A1738),
    val navyGradientMid: Color = Color(0xFF0B1C46),
    val navyGradientBot: Color = Color(0xFF0A1230),

    // Semantic Amber (Temporary Hold & Pending)
    val amberPrimary: Color = Color(0xFFD97706),
    val amberLight: Color = Color(0xFFF59E0B),
    val amberSubtle: Color = Color(0xFFFFFBEB),
    val amberBorder: Color = Color(0xFFFDE68A),

    // Semantic Crimson (Alerts, Leakage & Destructive)
    val crimsonPrimary: Color = Color(0xFFDC2626),
    val crimsonLight: Color = Color(0xFFEF4444),
    val crimsonSubtle: Color = Color(0xFFFEF2F2),
    val crimsonBorder: Color = Color(0xFFFECACA),

    // WhatsApp Desk Brand Accents
    val waTeal: Color = Color(0xFF075E54),
    val waGreen: Color = Color(0xFF25D366),
    val waSubtle: Color = Color(0xFFE8F5E9),
    val waChatBackground: Color = Color(0xFFEFEAE2),
    val waCustomerBubble: Color = Color(0xFFFFFFFF),
    val waPartnerBubble: Color = Color(0xFFDCF8C6),
    val waSystemBubble: Color = Color(0xFFF1F5F9),
)
