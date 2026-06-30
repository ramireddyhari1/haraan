package com.example.thanna.ui.theme

import androidx.compose.ui.graphics.Color

object HaraanColors {
    val Background = Color(0xFFF4F7FB)        // Custom premium slate-tinted background (no generic SaaS gray)
    val Surface = Color(0xFFFFFFFF)           // Pure white container cards
    val TextPrimary = Color(0xFF0F172A)       // Midnight slate
    val TextSecondary = Color(0xFF64748B)     // Cool grey
    val TextMuted = Color(0xFF94A3B8)         // Slate 400
    val BorderLight = Color(0xFFE2E8F0)       // Clean divider borders
    
    // Dynamic Mode Branding
    val EventsBlue = Color(0xFF2563EB)        // Brand blue for Events
    val GameHubGreen = Color(0xFF00C853)      // Haraan Green for GameHub & CTAs
    val GameHubDeep = Color(0xFF1B5E20)       // Deep forest green — hero band, headers, selected chips
    val LiveRed = Color(0xFFD32F2F)           // Single canonical "live" red (was split EF4444/D32F2F)
}
