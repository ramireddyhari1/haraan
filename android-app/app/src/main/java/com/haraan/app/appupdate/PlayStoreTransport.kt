package com.haraan.app.appupdate

import android.app.Activity
import android.content.Context
import android.os.Build
import com.google.android.play.core.appupdate.AppUpdateManager
import com.google.android.play.core.appupdate.AppUpdateManagerFactory
import com.google.android.play.core.appupdate.AppUpdateOptions
import com.google.android.play.core.install.InstallStateUpdatedListener
import com.google.android.play.core.install.model.AppUpdateType
import com.google.android.play.core.install.model.InstallStatus
import com.google.android.play.core.install.model.UpdateAvailability

/**
 * Google Play's flexible in-app update, for a phone that installed Haraan from Play. Play
 * downloads in the background and reports bytes, which drive the same dots as our own
 * download; the update installs when the person taps Restart.
 *
 * Play's update can't be replaced by our APK there: Play signs the app with its own key, and
 * Play policy forbids an app updating itself any other way.
 */
class PlayStoreTransport : StoreTransport {
    private var manager: AppUpdateManager? = null
    private var listener: InstallStateUpdatedListener? = null

    override fun handles(context: Context): Boolean = installerOf(context) == "com.android.vending"

    override fun start(activity: Activity, onState: (UpdatePhase, Long, Long, String?) -> Unit, fallback: () -> Unit) {
        val m = manager(activity)
        m.appUpdateInfo
            .addOnSuccessListener { info ->
                when {
                    info.installStatus() == InstallStatus.DOWNLOADED -> onState(UpdatePhase.RESTART, 0, -1, null)
                    info.updateAvailability() == UpdateAvailability.UPDATE_AVAILABLE &&
                        info.isUpdateTypeAllowed(AppUpdateType.FLEXIBLE) -> {
                        listen(m, onState)
                        m.startUpdateFlow(info, activity, AppUpdateOptions.defaultOptions(AppUpdateType.FLEXIBLE))
                    }
                    // Play hasn't got the release to this phone yet: open the listing instead.
                    else -> fallback()
                }
            }
            .addOnFailureListener { fallback() }
    }

    override fun resume(activity: Activity, onState: (UpdatePhase, Long, Long, String?) -> Unit) {
        val m = manager ?: return
        m.appUpdateInfo.addOnSuccessListener { info ->
            if (info.installStatus() == InstallStatus.DOWNLOADED) onState(UpdatePhase.RESTART, 0, -1, null)
        }
    }

    override fun complete() {
        manager?.completeUpdate()
    }

    private fun manager(context: Context): AppUpdateManager =
        manager ?: AppUpdateManagerFactory.create(context.applicationContext).also { manager = it }

    private fun listen(m: AppUpdateManager, onState: (UpdatePhase, Long, Long, String?) -> Unit) {
        listener?.let(m::unregisterListener)
        val l = InstallStateUpdatedListener { st ->
            when (st.installStatus()) {
                InstallStatus.PENDING -> onState(UpdatePhase.DOWNLOADING, 0, -1, null)
                InstallStatus.DOWNLOADING -> onState(UpdatePhase.DOWNLOADING, st.bytesDownloaded(), st.totalBytesToDownload(), null)
                InstallStatus.DOWNLOADED -> onState(UpdatePhase.RESTART, st.totalBytesToDownload(), st.totalBytesToDownload(), null)
                InstallStatus.INSTALLING -> onState(UpdatePhase.INSTALLING, 0, -1, null)
                InstallStatus.FAILED -> onState(UpdatePhase.FAILED, 0, -1, "Google Play couldn’t download the update (error ${st.installErrorCode()}). Try again.")
                InstallStatus.CANCELED -> onState(UpdatePhase.OFFER, 0, -1, null)
                else -> Unit
            }
        }
        listener = l
        m.registerListener(l)
    }

    private fun installerOf(context: Context): String? = runCatching {
        val pm = context.packageManager
        if (Build.VERSION.SDK_INT >= 30) {
            pm.getInstallSourceInfo(context.packageName).installingPackageName
        } else {
            @Suppress("DEPRECATION")
            pm.getInstallerPackageName(context.packageName)
        }
    }.getOrNull()
}
