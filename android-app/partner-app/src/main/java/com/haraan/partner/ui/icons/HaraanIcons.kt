package com.haraan.partner.ui.icons

import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.graphics.vector.addPathNodes
import androidx.compose.ui.unit.dp

/**
 * Bespoke, handcrafted enterprise SaaS iconography for HARAAN Partner Platform.
 * Designed at Stripe, Linear, and Apple executive design standards.
 *
 * Geometric Specifications:
 * - Viewport: 24 × 24 dp coordinate space
 * - Uniform primary stroke: 1.7 dp with optical counter-balancing
 * - Caps & Joins: Round throughout for smooth athletic turf aesthetic
 * - Two-tone optical hierarchy: Primary structural strokes at 100% alpha,
 *   subtle internal metrics/accents at 40-55% alpha for rich depth.
 */
internal object HaraanIcons {

    private const val STROKE_PRIMARY = 1.7f
    private const val STROKE_SECONDARY = 1.4f

    // 1. Home: Stadium pavilion entrance with arched pitch gate
    val Home: ImageVector = icon("Home") {
        // Pavilion roof and outer columns
        stroke(
            "M3 10.5 L12 3.5 L21 10.5 " +
                "M5.5 9.5 V19.5 A1.5 1.5 0 0 0 7 21 H17 A1.5 1.5 0 0 0 18.5 19.5 V9.5",
            STROKE_PRIMARY,
        )
        // Arched turf entrance portal
        stroke("M9.5 21 V16 A2.5 2.5 0 0 1 14.5 16 V21", STROKE_PRIMARY)
        // Upper roof beam line
        stroke("M7.5 10.5 H16.5", STROKE_SECONDARY, alpha = 0.5f)
    }

    // 2. Venues: Arena perimeter pin enclosing turf center circle
    val Venues: ImageVector = icon("Venues") {
        // Teardrop landmark perimeter
        stroke(
            "M12 21.5 C12 21.5 19 15.5 19 10.5 A7 7 0 1 0 5 10.5 C5 15.5 12 21.5 12 21.5 Z",
            STROKE_PRIMARY,
        )
        // Pitch center spot and circle
        stroke("M9.5 10.5 A2.5 2.5 0 1 0 14.5 10.5 A2.5 2.5 0 1 0 9.5 10.5 Z", STROKE_PRIMARY)
    }

    // 3. Matches: Turf corner flag & pitch corner arc
    val Matches: ImageVector = icon("Matches") {
        // Flag staff
        stroke("M6.5 3.5 V20.5", STROKE_PRIMARY)
        // Aerodynamic competition pennant
        stroke("M6.5 4.5 H18.5 L14.5 8.5 L18.5 12.5 H6.5 Z", STROKE_PRIMARY)
        fill("M6.5 4.5 H18.5 L14.5 8.5 L18.5 12.5 H6.5 Z", alpha = 0.22f)
        // Corner turf arc & baseline
        stroke("M6.5 17 A3.5 3.5 0 0 1 10 20.5", STROKE_SECONDARY, alpha = 0.6f)
        stroke("M4 20.5 H11", STROKE_PRIMARY)
    }

    // 4. Bookings / Events: Reservation ledger with slot matrix
    val Bookings: ImageVector = icon("Bookings") {
        // Calendar ledger sheet
        stroke(
            "M5 5.5 H19 A1.5 1.5 0 0 1 20.5 7 V19.5 A1.5 1.5 0 0 1 19 21 H5 A1.5 1.5 0 0 1 3.5 19.5 V7 A1.5 1.5 0 0 1 5 5.5 Z",
            STROKE_PRIMARY,
        )
        // Binder loops
        stroke("M7.5 3 V6.5 M16.5 3 V6.5", STROKE_PRIMARY)
        // Header partition line
        stroke("M3.5 10 H20.5", STROKE_PRIMARY)
        // Time slot blocks
        stroke("M7.5 13.5 H11 M14 13.5 H16.5 M7.5 17 H11 M14 17 H15.5", STROKE_SECONDARY, alpha = 0.55f)
    }

    // 5. Sales / Revenue: Financial invoice slip with jagged tear foot
    val Sales: ImageVector = icon("Sales") {
        // Receipt body with serrated edge
        stroke(
            "M5.5 3.5 H18.5 V19.2 L16.3 17.8 L14.1 19.2 L12 17.8 L9.9 19.2 L7.7 17.8 L5.5 19.2 Z",
            STROKE_PRIMARY,
        )
        // Balance & transaction lines
        stroke("M8.5 7.5 H15.5 M8.5 11 H13.5 M8.5 14.5 H11.5", STROKE_SECONDARY, alpha = 0.6f)
    }

    // 6. Scan: Viewfinder corners with optical targeting beam
    val Scan: ImageVector = icon("Scan") {
        // Precision 4-corner viewfinder brackets
        stroke(
            "M3.5 8.5 V5.5 A2 2 0 0 1 5.5 3.5 H8.5 " +
                "M15.5 3.5 H18.5 A2 2 0 0 1 20.5 5.5 V8.5 " +
                "M20.5 15.5 V18.5 A2 2 0 0 1 18.5 20.5 H15.5 " +
                "M8.5 20.5 H5.5 A2 2 0 0 1 3.5 18.5 V15.5",
            STROKE_PRIMARY,
        )
        // Optical laser scan beam
        stroke("M4.5 12 H19.5", STROKE_PRIMARY)
        fill("M6 11.2 H18 A0.8 0.8 0 0 1 18 12.8 H6 A0.8 0.8 0 0 1 6 11.2 Z", alpha = 0.25f)
    }

    // 7. Operations Center: Executive sparkline with rising trajectory node
    val Operations: ImageVector = icon("Operations") {
        // Metric frame
        stroke("M3.5 4.5 V19.5 A1 1 0 0 0 4.5 20.5 H20.5", STROKE_PRIMARY)
        // Trajectory sparkline
        stroke("M6 15.5 L10 11.5 L14 14.5 L19.5 8", STROKE_PRIMARY)
        // Apex trend arrow
        stroke("M16.5 8 H19.5 V11", STROKE_PRIMARY)
    }

    // 8. WhatsApp Desk: Dual communication bubbles
    val WhatsAppDesk: ImageVector = icon("WhatsAppDesk") {
        // Main dialogue bubble
        stroke(
            "M3.5 5.5 H15.5 A2 2 0 0 1 17.5 7.5 V13.5 A2 2 0 0 1 15.5 15.5 H9.5 L5.5 18.5 V15.5 H3.5 A2 2 0 0 1 1.5 13.5 V7.5 A2 2 0 0 1 3.5 5.5 Z",
            STROKE_PRIMARY,
        )
        // Text stream lines
        stroke("M5.5 9.5 H13.5 M5.5 12.5 H10.5", STROKE_SECONDARY, alpha = 0.6f)
        // Secondary echo bubble behind
        stroke("M18.5 9.5 H20.5 A2 2 0 0 1 22.5 11.5 V17.5 A2 2 0 0 1 20.5 19.5 H19 V21.5 L16.5 19.5", STROKE_SECONDARY, alpha = 0.5f)
    }

    // 9. Cash Settlement: Till register with currency slot & security latch
    val CashSettlement: ImageVector = icon("CashSettlement") {
        // Till vault base
        stroke(
            "M3.5 8.5 H20.5 A1.5 1.5 0 0 1 22 10 V18.5 A1.5 1.5 0 0 1 20.5 20 H3.5 A1.5 1.5 0 0 1 2 18.5 V10 A1.5 1.5 0 0 1 3.5 8.5 Z",
            STROKE_PRIMARY,
        )
        // Till register top display
        stroke("M7 5 H17 L18.5 8.5 H5.5 Z", STROKE_PRIMARY)
        // Drawer separation line
        stroke("M2 14.5 H22", STROKE_PRIMARY)
        // Lock latch / till handle
        stroke("M10.5 17 H13.5", STROKE_SECONDARY)
    }

    // 10. Standing Slots: Recurring orbital cycle surrounding reservation slot
    val StandingSlots: ImageVector = icon("StandingSlots") {
        // Base calendar square
        stroke(
            "M4 7 H15 A1.5 1.5 0 0 1 16.5 8.5 V18 A1.5 1.5 0 0 1 15 19.5 H4 A1.5 1.5 0 0 1 2.5 18 V8.5 A1.5 1.5 0 0 1 4 7 Z",
            STROKE_PRIMARY,
        )
        stroke("M2.5 11 H16.5", STROKE_SECONDARY)
        // Reserved checkmark inside
        stroke("M6.5 15.5 L8.5 17.5 L13 13", STROKE_PRIMARY)
        // Recurrence orbit arrow
        stroke("M18 4.5 C20 6 21.5 8.5 21.5 11.5 C21.5 14 20.3 16.2 18.5 17.5", STROKE_PRIMARY)
        stroke("M16.5 3 H19.5 V6", STROKE_PRIMARY)
    }

    // 11. Pricing & Courts: Multi-pitch turf court layout
    val PricingCourts: ImageVector = icon("PricingCourts") {
        // Perimeter touchline & goal lines
        stroke("M3.5 4.5 H20.5 V19.5 H3.5 Z", STROKE_PRIMARY)
        // Halfway line
        stroke("M12 4.5 V19.5", STROKE_PRIMARY)
        // Pitch center circle
        stroke("M9.5 12 A2.5 2.5 0 1 0 14.5 12 A2.5 2.5 0 1 0 9.5 12 Z", STROKE_SECONDARY)
        // Court penalty box notches
        stroke("M3.5 8.5 H6.5 V15.5 H3.5 M20.5 8.5 H17.5 V15.5 H20.5", STROKE_SECONDARY, alpha = 0.5f)
    }

    // 12. Customers: Dual executive member profiles
    val Customers: ImageVector = icon("Customers") {
        // Primary user head
        stroke("M9 5 A3 3 0 1 0 9 11 A3 3 0 1 0 9 5 Z", STROKE_PRIMARY)
        // Primary user shoulders
        stroke("M3 20.5 C3 16.8 5.7 14 9 14 C12.3 14 15 16.8 15 20.5", STROKE_PRIMARY)
        // Companion user head
        stroke("M16 6.5 A2.3 2.3 0 1 0 16 11.1", STROKE_SECONDARY, alpha = 0.7f)
        // Companion user shoulder
        stroke("M15.5 14.5 C17.5 15.1 19.5 16.8 20 20.5", STROKE_SECONDARY, alpha = 0.7f)
    }

    // 13. Packages: Multi-pass tiered membership cards
    val Packages: ImageVector = icon("Packages") {
        // Front pass card
        stroke(
            "M3.5 8.5 H18.5 A1.5 1.5 0 0 1 20 10 V19 A1.5 1.5 0 0 1 18.5 20.5 H3.5 A1.5 1.5 0 0 1 2 19 V10 A1.5 1.5 0 0 1 3.5 8.5 Z",
            STROKE_PRIMARY,
        )
        // Magnetic strip
        stroke("M2 12.5 H20", STROKE_SECONDARY, alpha = 0.5f)
        // Smart chip / star seal
        stroke("M5.5 16.5 H9", STROKE_PRIMARY)
        // Background card offset
        stroke("M6 5.5 H19.5 A1.5 1.5 0 0 1 21 7 V15.5", STROKE_SECONDARY, alpha = 0.6f)
    }

    // 14. Academy: Coaching pitch target & ball trajectory
    val Academy: ImageVector = icon("Academy") {
        // Concentric target outer ring
        stroke("M12 3 A9 9 0 1 0 21 12 A9 9 0 1 0 12 3 Z", STROKE_PRIMARY)
        // Intermediate ring
        stroke("M12 6.5 A5.5 5.5 0 1 0 17.5 12 A5.5 5.5 0 1 0 12 6.5 Z", STROKE_SECONDARY, alpha = 0.6f)
        // Bullseye target center
        stroke("M12 10.5 A1.5 1.5 0 1 0 13.5 12 A1.5 1.5 0 1 0 12 10.5 Z", STROKE_PRIMARY)
        fill("M12 10.5 A1.5 1.5 0 1 0 13.5 12 A1.5 1.5 0 1 0 12 10.5 Z")
    }

    // 15. Staff: Credential ID lanyard badge
    val Staff: ImageVector = icon("Staff") {
        // Lanyard badge card
        stroke(
            "M5.5 6.5 H18.5 A1.5 1.5 0 0 1 20 8 V20 A1.5 1.5 0 0 1 18.5 21.5 H5.5 A1.5 1.5 0 0 1 4 20 V8 A1.5 1.5 0 0 1 5.5 6.5 Z",
            STROKE_PRIMARY,
        )
        // Top lanyard clip
        stroke("M10 6.5 V4 A1.5 1.5 0 0 1 11.5 2.5 H12.5 A1.5 1.5 0 0 1 14 4 V6.5", STROKE_PRIMARY)
        // Staff silhouette
        stroke("M12 11.5 A2 2 0 1 0 12 7.5 A2 2 0 1 0 12 11.5 Z", STROKE_SECONDARY)
        stroke("M8 18 C8 15.8 9.8 14.5 12 14.5 C14.2 14.5 16 15.8 16 18", STROKE_SECONDARY)
    }

    // 16. Payouts: Neoclassical banking vault & settlement platform
    val Payouts: ImageVector = icon("Payouts") {
        // Pediment roof
        stroke("M2.5 9 L12 3.5 L21.5 9", STROKE_PRIMARY)
        // Entablature architrave line
        stroke("M3.5 9.5 H20.5", STROKE_PRIMARY)
        // Foundation plinth
        stroke("M2.5 19.5 H21.5", STROKE_PRIMARY)
        // Banking pillars
        stroke("M6 10.5 V18.5 M10 10.5 V18.5 M14 10.5 V18.5 M18 10.5 V18.5", STROKE_PRIMARY)
    }

    // 17. Reports: Financial audit document with metric trend bars
    val Reports: ImageVector = icon("Reports") {
        // Document outline
        stroke(
            "M5 3.5 H15 L19.5 8 V20 A1.5 1.5 0 0 1 18 21.5 H5 A1.5 1.5 0 0 1 3.5 20 V5 A1.5 1.5 0 0 1 5 3.5 Z",
            STROKE_PRIMARY,
        )
        // Folded ear
        stroke("M15 3.5 V8 H19.5", STROKE_PRIMARY)
        // Metric data bars
        stroke("M7.5 12 H15.5 M7.5 15.5 H13.5 M7.5 18.5 H10.5", STROKE_SECONDARY, alpha = 0.6f)
    }

    // 18. Settings: Precision mechanical equalizer tuner (dual rail)
    val Settings: ImageVector = icon("Settings") {
        // Top slider rail
        stroke("M3.5 8 H20.5", STROKE_PRIMARY)
        // Top adjuster block (offset left)
        stroke("M7 5.5 H10.5 V10.5 H7 Z", STROKE_PRIMARY)
        fill("M7 5.5 H10.5 V10.5 H7 Z", alpha = 0.35f)
        // Bottom slider rail
        stroke("M3.5 16 H20.5", STROKE_PRIMARY)
        // Bottom adjuster block (offset right)
        stroke("M13.5 13.5 H17 V18.5 H13.5 Z", STROKE_PRIMARY)
        fill("M13.5 13.5 H17 V18.5 H13.5 Z", alpha = 0.35f)
    }

    // 19. Support: Concierge operator headset
    val Support: ImageVector = icon("Support") {
        // Headband arch
        stroke("M4 12 A8 8 0 0 1 20 12", STROKE_PRIMARY)
        // Left ear cushion
        stroke("M3 11 H5 A1.5 1.5 0 0 1 6.5 12.5 V15.5 A1.5 1.5 0 0 1 5 17 H3 A1.5 1.5 0 0 1 1.5 15.5 V12.5 A1.5 1.5 0 0 1 3 11 Z", STROKE_PRIMARY)
        // Right ear cushion
        stroke("M19 11 H21 A1.5 1.5 0 0 1 22.5 12.5 V15.5 A1.5 1.5 0 0 1 21 17 H19 A1.5 1.5 0 0 1 17.5 15.5 V12.5 A1.5 1.5 0 0 1 19 11 Z", STROKE_PRIMARY)
        // Mic boom
        stroke("M19 16 V18 A3 3 0 0 1 16 21 H12.5", STROKE_PRIMARY)
        // Mic capsule
        stroke("M10.5 20 H12.5 V22 H10.5 Z", STROKE_PRIMARY)
    }

    // 20. SignOut: Directional egress doorway portal
    val SignOut: ImageVector = icon("SignOut") {
        // Open doorway frame
        stroke("M10 4 H5 A1.5 1.5 0 0 0 3.5 5.5 V18.5 A1.5 1.5 0 0 0 5 20 H10", STROKE_PRIMARY)
        // Egress arrow
        stroke("M13.5 8 L18.5 12 L13.5 16 M8 12 H18", STROKE_PRIMARY)
    }

    // 21. Check: Geometric precision checkmark
    val Check: ImageVector = icon("Check") {
        stroke("M4.5 12.5 L9.5 17.5 L19.5 6.5", STROKE_PRIMARY)
    }

    // 22. Outlet: Compact arena venue pin for multi-branch listings
    val Outlet: ImageVector = icon("Outlet") {
        stroke(
            "M12 21 C12 21 18.5 15.5 18.5 10.5 A6.5 6.5 0 1 0 5.5 10.5 C5.5 15.5 12 21 12 21 Z",
            STROKE_PRIMARY,
        )
        stroke("M10 10.5 A2 2 0 1 0 14 10.5 A2 2 0 1 0 10 10.5 Z", STROKE_SECONDARY)
    }

    // ---- Vector Builder Utilities -------------------------------------------

    private fun icon(name: String, body: ImageVector.Builder.() -> Unit): ImageVector =
        ImageVector.Builder(
            name = name,
            defaultWidth = 24.dp,
            defaultHeight = 24.dp,
            viewportWidth = 24f,
            viewportHeight = 24f,
        ).apply(body).build()

    private fun ImageVector.Builder.stroke(
        pathData: String,
        width: Float,
        alpha: Float = 1f,
    ) {
        addPath(
            pathData = addPathNodes(pathData),
            fill = null,
            fillAlpha = 1f,
            stroke = SolidColor(Color.Black),
            strokeAlpha = alpha,
            strokeLineWidth = width,
            strokeLineCap = StrokeCap.Round,
            strokeLineJoin = StrokeJoin.Round,
        )
    }

    private fun ImageVector.Builder.fill(
        pathData: String,
        alpha: Float = 1f,
    ) {
        addPath(
            pathData = addPathNodes(pathData),
            fill = SolidColor(Color.Black),
            fillAlpha = alpha,
            stroke = null,
        )
    }
}
