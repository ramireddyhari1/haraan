package com.haraan.app.data.membership

import android.content.ComponentName
import android.content.Context
import android.content.pm.PackageManager
import androidx.annotation.DrawableRes
import com.haraan.app.R

/**
 * The home-screen icons a member can wear. Each is an `<activity-alias>` onto HomeActivity in
 * the manifest; exactly one is enabled at a time.
 *
 * Who may wear which is the server's call, through two ordinary member features edited per plan
 * in /control ([feature] below) — never a plan-code check here. [DEFAULT] needs no feature and is
 * always available, so a member can always go back.
 */
enum class AppIcon(
    /** The alias's name, relative to the package — must match AndroidManifest.xml. */
    val alias: String,
    /** The member feature that unlocks it; null for the icon everyone has. */
    val feature: String?,
    val label: String,
    /**
     * The adaptive icon's two layers (108dp canvas, 72dp visible), for drawing the icon in-app.
     * The mipmap itself is an `<adaptive-icon>`, which Compose's painterResource can't load.
     */
    @param:DrawableRes val plate: Int,
    @param:DrawableRes val mark: Int,
) {
    DEFAULT(".MainActivity", null, "Haraan", R.drawable.ic_launcher_background, R.drawable.ic_launcher_foreground),
    PRO(".LauncherPro", "app.icon_pro", "Pro", R.drawable.ic_launcher_pro_background, R.drawable.ic_launcher_pro_foreground),
    HERO(".LauncherHero", "app.icon_hero", "Hero", R.drawable.ic_launcher_hero_background, R.drawable.ic_launcher_hero_foreground);

    companion object {
        /**
         * The icons [entitlements] unlock, in display order. Only an entitlement the server marked
         * enabled counts; a missing row (older server, feature switched off) locks the icon.
         */
        fun unlockedBy(entitlements: List<PlanFeature>): Set<AppIcon> {
            val on = entitlements.filter { it.enabled }.map { it.key }.toSet()
            return entries.filter { it.feature == null || it.feature in on }.toSet()
        }

        /**
         * What should be on the home screen: [current] if the plan still unlocks it, otherwise the
         * default. Null when nothing needs to change.
         */
        fun correction(current: AppIcon, entitlements: List<PlanFeature>): AppIcon? =
            if (current in unlockedBy(entitlements)) null else DEFAULT
    }
}

object AppIcons {

    private fun component(context: Context, icon: AppIcon) =
        ComponentName(context.packageName, context.packageName + icon.alias)

    /** The icon on the home screen now. Reads the manifest default for an alias never touched. */
    fun current(context: Context): AppIcon {
        val pm = context.packageManager
        return AppIcon.entries.firstOrNull { icon ->
            when (pm.getComponentEnabledSetting(component(context, icon))) {
                PackageManager.COMPONENT_ENABLED_STATE_ENABLED -> true
                PackageManager.COMPONENT_ENABLED_STATE_DEFAULT -> icon == AppIcon.DEFAULT
                else -> false
            }
        } ?: AppIcon.DEFAULT
    }

    /**
     * Put [icon] on the home screen. The new alias is enabled BEFORE the others are disabled:
     * a moment with no launcher entry at all makes some launchers drop the app from the home
     * screen entirely. DONT_KILL_APP keeps the running app alive through the swap.
     *
     * Returns false if the system refused (it never should for our own components).
     */
    fun apply(context: Context, icon: AppIcon): Boolean = try {
        val pm = context.packageManager
        pm.setComponentEnabledSetting(
            component(context, icon),
            PackageManager.COMPONENT_ENABLED_STATE_ENABLED,
            PackageManager.DONT_KILL_APP,
        )
        AppIcon.entries.filter { it != icon }.forEach { other ->
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
     * The icon to hand back when the plan behind the current one has lapsed, or null when the
     * home screen is fine as it is. Cheap for everyone on the default icon: no network call
     * unless a member icon is showing.
     *
     * [membership] is fetched lazily and only trusted when it answers — a failed or offline read
     * never strips a paying member's icon. Signed out, the icon goes back (a guest has no plan).
     *
     * Only works it out; the caller applies it once the app is in the background, because
     * disabling the alias the app was launched from can end the task on some OEM builds.
     */
    suspend fun lapsed(context: Context, token: String?, membership: suspend (String) -> Membership): AppIcon? {
        val current = current(context)
        if (current == AppIcon.DEFAULT) return null
        val entitlements = if (token == null) {
            emptyList()
        } else {
            runCatching { membership(token) }.getOrNull()?.entitlements ?: return null
        }
        return AppIcon.correction(current, entitlements)
    }
}
