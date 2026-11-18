package com.haraan.app.data.membership

import android.content.ComponentName
import android.content.Context
import android.content.pm.PackageManager
import androidx.annotation.DrawableRes
import com.haraan.app.R
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * The home-screen icons built into this app. Each is an `<activity-alias>` onto HomeActivity in
 * the manifest; exactly one is enabled at a time.
 *
 * This is only the library of ART. Who may wear which, and which goes on automatically, is set
 * in /control → Finance → App icons and arrives as an [AppIconCatalog]; nothing here checks a plan.
 * [key] is how the server names a slot and must match `app_icon_slots.key`.
 */
enum class AppIconSlot(
    val key: String,
    /** The alias's name, relative to the package; must match AndroidManifest.xml. */
    val alias: String,
    /**
     * The adaptive icon's two layers (108dp canvas, 72dp visible), for drawing the icon in-app.
     * The mipmap itself is an `<adaptive-icon>`, which Compose's painterResource can't load.
     */
    @param:DrawableRes val plate: Int,
    @param:DrawableRes val mark: Int,
) {
    DEFAULT("default", ".MainActivity", R.drawable.ic_launcher_background, R.drawable.ic_launcher_foreground),
    PRO("pro", ".LauncherPro", R.drawable.ic_launcher_pro_background, R.drawable.ic_launcher_pro_foreground),
    HERO("hero", ".LauncherHero", R.drawable.ic_launcher_hero_background, R.drawable.ic_launcher_hero_foreground),
    ONYX("onyx", ".LauncherOnyx", R.drawable.ic_launcher_onyx_background, R.drawable.ic_launcher_onyx_foreground),
    PAPER("paper", ".LauncherPaper", R.drawable.ic_launcher_paper_background, R.drawable.ic_launcher_paper_foreground),
    TURF("turf", ".LauncherTurf", R.drawable.ic_launcher_turf_background, R.drawable.ic_launcher_turf_foreground),
    LEATHER("leather", ".LauncherLeather", R.drawable.ic_launcher_leather_background, R.drawable.ic_launcher_leather_foreground),
    FESTIVE("festive", ".LauncherFestive", R.drawable.ic_launcher_festive_background, R.drawable.ic_launcher_festive_foreground),
    SUNRISE("sunrise", ".LauncherSunrise", R.drawable.ic_launcher_sunrise_background, R.drawable.ic_launcher_sunrise_foreground),
    MONSOON("monsoon", ".LauncherMonsoon", R.drawable.ic_launcher_monsoon_background, R.drawable.ic_launcher_monsoon_foreground),
    ROYAL("royal", ".LauncherRoyal", R.drawable.ic_launcher_royal_background, R.drawable.ic_launcher_royal_foreground),
    SILVER("silver", ".LauncherSilver", R.drawable.ic_launcher_silver_background, R.drawable.ic_launcher_silver_foreground);

    companion object {
        /** A key this build has art for, or null (a newer server's icon: ignored). */
        fun of(key: String?): AppIconSlot? = entries.firstOrNull { it.key == key }
    }
}

/** GET /api/membership/app-icons. */
@Serializable
data class AppIconCatalog(
    /** The icon to put on automatically, or null to leave it to the member. */
    val auto: String? = null,
    @SerialName("min_hours_between_auto") val minHoursBetweenAuto: Int = 72,
    val icons: List<AppIconOffer> = emptyList(),
) {
    /** Offers this build can draw, in the server's order. */
    val known: List<Pair<AppIconSlot, AppIconOffer>>
        get() = icons.mapNotNull { o -> AppIconSlot.of(o.key)?.let { it to o } }

    val unlocked: Set<AppIconSlot>
        get() = known.filter { it.second.unlocked }.map { it.first }.toSet() + AppIconSlot.DEFAULT

    val autoSlot: AppIconSlot?
        get() = AppIconSlot.of(auto)?.takeIf { it in unlocked }
}

@Serializable
data class AppIconOffer(
    val key: String,
    val name: String,
    val unlocked: Boolean = false,
    val seasonal: Boolean = false,
    @SerialName("unlocked_by") val unlockedBy: AppIconPlanRef? = null,
)

@Serializable
data class AppIconPlanRef(val code: String, val name: String)

/** What to do with the home screen: swap to [apply] (if any) and remember [recordAuto]. */
data class AppIconDecision(val apply: AppIconSlot?, val recordAuto: String?)

/**
 * The rules, kept pure so they're tested without a phone:
 *  1. An icon the member may no longer wear (plan lapsed, icon switched off, season over) is
 *     replaced at once with their automatic icon, or the default.
 *  2. A NEW automatic icon (they just joined a plan, or the admin changed it) is applied once —
 *     but no sooner than [AppIconCatalog.minHoursBetweenAuto] after the last automatic swap,
 *     because some launchers move or drop the icon when it changes.
 *  3. Otherwise the member's own choice stands.
 */
object AppIconPolicy {
    fun decide(
        current: AppIconSlot,
        catalog: AppIconCatalog,
        lastAuto: String?,
        lastAutoAtMs: Long,
        nowMs: Long,
    ): AppIconDecision? {
        val auto = catalog.autoSlot
        if (current !in catalog.unlocked) {
            val target = auto ?: AppIconSlot.DEFAULT
            return AppIconDecision(target, auto?.key ?: lastAuto)
        }
        if (auto == null || auto.key == lastAuto) return null
        val waitMs = catalog.minHoursBetweenAuto.coerceAtLeast(0) * 3_600_000L
        if (lastAutoAtMs > 0 && nowMs - lastAutoAtMs < waitMs) return null
        return AppIconDecision(auto.takeIf { it != current }, auto.key)
    }
}

object AppIcons {

    private const val PREFS = "app_icons"
    private const val LAST_AUTO = "last_auto"
    private const val LAST_AUTO_AT = "last_auto_at"

    private fun component(context: Context, icon: AppIconSlot) =
        ComponentName(context.packageName, context.packageName + icon.alias)

    /** The icon on the home screen now. Reads the manifest default for an alias never touched. */
    fun current(context: Context): AppIconSlot {
        val pm = context.packageManager
        return AppIconSlot.entries.firstOrNull { icon ->
            when (pm.getComponentEnabledSetting(component(context, icon))) {
                PackageManager.COMPONENT_ENABLED_STATE_ENABLED -> true
                PackageManager.COMPONENT_ENABLED_STATE_DEFAULT -> icon == AppIconSlot.DEFAULT
                else -> false
            }
        } ?: AppIconSlot.DEFAULT
    }

    /**
     * Put [icon] on the home screen. The new alias is enabled BEFORE the others are disabled:
     * a moment with no launcher entry at all makes some launchers drop the app from the home
     * screen entirely. DONT_KILL_APP keeps the running app alive through the swap.
     *
     * Returns false if the system refused (it never should for our own components).
     */
    fun apply(context: Context, icon: AppIconSlot): Boolean = try {
        val pm = context.packageManager
        pm.setComponentEnabledSetting(
            component(context, icon),
            PackageManager.COMPONENT_ENABLED_STATE_ENABLED,
            PackageManager.DONT_KILL_APP,
        )
        AppIconSlot.entries.filter { it != icon }.forEach { other ->
            pm.setComponentEnabledSetting(
                component(context, other),
                PackageManager.COMPONENT_ENABLED_STATE_DISABLED,
                PackageManager.DONT_KILL_APP,
            )
        }
        true
    } catch (_: Exception) {
        false
    }

    /**
     * Work out what the home screen should wear, from the server's [catalog] fetch. A failed or
     * offline read returns null, so it never strips a paying member's icon.
     *
     * Only works it out; the caller applies it with [commit] once the app is in the background,
     * because disabling the alias the app was launched from can end the task on some OEM builds.
     */
    suspend fun plan(context: Context, catalog: suspend () -> AppIconCatalog): AppIconDecision? {
        val fetched = runCatching { catalog() }.getOrNull() ?: return null
        val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        return AppIconPolicy.decide(
            current = current(context),
            catalog = fetched,
            lastAuto = prefs.getString(LAST_AUTO, null),
            lastAutoAtMs = prefs.getLong(LAST_AUTO_AT, 0L),
            nowMs = System.currentTimeMillis(),
        )
    }

    /** Apply a [plan] decision and remember the automatic icon it settled on. */
    fun commit(context: Context, decision: AppIconDecision) {
        val swapped = decision.apply?.let { apply(context, it) } ?: true
        if (!swapped) return
        val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        if (decision.recordAuto != prefs.getString(LAST_AUTO, null) || decision.apply != null) {
            prefs.edit()
                .putString(LAST_AUTO, decision.recordAuto)
                .putLong(LAST_AUTO_AT, if (decision.apply != null) System.currentTimeMillis() else prefs.getLong(LAST_AUTO_AT, 0L))
                .apply()
        }
    }
}
