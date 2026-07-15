package com.haraan.app.theme

import androidx.compose.ui.text.font.Font
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import com.haraan.app.R

// Single app typeface: Plus Jakarta Sans. The symbol is kept named `Poppins` so the
// existing type scale (Type.kt) reads unchanged while every weight now resolves to
// the bundled Plus Jakarta static weights (res/font). FontWeight.Black requests fall
// back to the nearest available weight (ExtraBold), which still reads heavy.
val Poppins = FontFamily(
    Font(R.font.plusjakartasans_regular, FontWeight.Normal),
    Font(R.font.plusjakartasans_medium, FontWeight.Medium),
    Font(R.font.plusjakartasans_semibold, FontWeight.SemiBold),
    Font(R.font.plusjakartasans_bold, FontWeight.Bold),
    Font(R.font.plusjakartasans_extrabold, FontWeight.ExtraBold),
)

// Display face for the scoreboard "punch" — big match scores, innings totals, hero
// numbers. Archivo Black is a single heavy weight; use it only for marquee numerals,
// never body text. Pair with Poppins (Plus Jakarta) for everything else.
val ArchivoDisplay = FontFamily(
    Font(R.font.archivo_black, FontWeight.Black),
)
