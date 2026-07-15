package com.haraan.app.data

/**
 * Minimal Open Location Code (Google Plus Code) encoder.
 *
 * Uses the original range-narrowing algorithm: each pair of digits halves… er, twentieths
 * the lat/lng window (base-20), and digits past the 10th refine on a 5×4 grid. Accurate to
 * ~3 m at length 11, which is what the UI shows.
 *
 * We surface the *local* part of the code (everything after the first 4 area digits), e.g.
 * full "7MH2CJX6+2R2" → display "CJX6+2R2", paired with a human place name in the header.
 */
object PlusCode {
    private const val ALPHABET = "23456789CFGHJMPQRVWX"
    private const val BASE = 20
    private const val SEPARATOR = '+'
    private const val SEPARATOR_POSITION = 8
    private const val PAIR_LENGTH = 10
    private const val GRID_ROWS = 5
    private const val GRID_COLS = 4

    /** Full Open Location Code, e.g. "7MH2CJX6+2R2". */
    fun encode(latitude: Double, longitude: Double, codeLength: Int = 11): String {
        var lat = latitude.coerceIn(-90.0, 89.9999999)
        var lng = longitude
        // Normalize longitude into [-180, 180).
        while (lng < -180.0) lng += 360.0
        while (lng >= 180.0) lng -= 360.0

        val code = StringBuilder()
        var latLow = -90.0; var latHigh = 90.0
        var lngLow = -180.0; var lngHigh = 180.0
        var digit = 0
        while (digit < codeLength) {
            if (digit < PAIR_LENGTH) {
                // Pair encoding: latitude digit then longitude digit, base 20.
                val latPlace = (latHigh - latLow) / BASE
                val latNdx = ((lat - latLow) / latPlace).toInt().coerceIn(0, BASE - 1)
                latLow += latNdx * latPlace; latHigh = latLow + latPlace
                code.append(ALPHABET[latNdx]); digit++

                val lngPlace = (lngHigh - lngLow) / BASE
                val lngNdx = ((lng - lngLow) / lngPlace).toInt().coerceIn(0, BASE - 1)
                lngLow += lngNdx * lngPlace; lngHigh = lngLow + lngPlace
                code.append(ALPHABET[lngNdx]); digit++
            } else {
                // Grid refinement: 5 rows × 4 columns per cell.
                val latPlace = (latHigh - latLow) / GRID_ROWS
                val lngPlace = (lngHigh - lngLow) / GRID_COLS
                val row = ((lat - latLow) / latPlace).toInt().coerceIn(0, GRID_ROWS - 1)
                val col = ((lng - lngLow) / lngPlace).toInt().coerceIn(0, GRID_COLS - 1)
                latLow += row * latPlace; latHigh = latLow + latPlace
                lngLow += col * lngPlace; lngHigh = lngLow + lngPlace
                code.append(ALPHABET[row * GRID_COLS + col]); digit++
            }
        }
        code.insert(SEPARATOR_POSITION, SEPARATOR)
        return code.toString()
    }

    /** The local short form shown in the UI (drops the 4 area digits), e.g. "CJX6+2R2". */
    fun localCode(latitude: Double, longitude: Double): String =
        encode(latitude, longitude).substring(4)
}
