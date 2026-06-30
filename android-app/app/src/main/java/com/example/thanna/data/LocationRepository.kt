package com.example.thanna.data

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.location.Geocoder
import androidx.core.content.ContextCompat
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlin.coroutines.resume

sealed class LocationState {
    object Idle : LocationState()
    object Locating : LocationState()
    object Denied : LocationState()
    data class City(val name: String, val district: String = "", val area: String = "", val plusCode: String = "") : LocationState()
    // Alias used in some screens
    data class Resolved(val city: String, val district: String = "", val area: String = "", val plusCode: String = "") : LocationState()
}

data class CityOption(val name: String, val district: String = "")

class LocationRepository(private val context: Context) {

    private val prefs = context.getSharedPreferences("location_prefs", Context.MODE_PRIVATE)

    fun hasPermission(): Boolean =
        ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION) ==
            PackageManager.PERMISSION_GRANTED ||
        ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION) ==
            PackageManager.PERMISSION_GRANTED

    fun cached(): LocationState? {
        val city = prefs.getString("city", null) ?: return null
        val district = prefs.getString("district", "") ?: ""
        val area = prefs.getString("area", "") ?: ""
        val plusCode = prefs.getString("plusCode", "") ?: ""
        return LocationState.City(city, district, area, plusCode)
    }

    fun recents(): List<CityOption> {
        val raw = prefs.getString("recents", "") ?: return emptyList()
        return raw.split("|").filter { it.isNotBlank() }.map { CityOption(it) }
    }

    fun selectCity(option: CityOption): LocationState {
        prefs.edit()
            .putString("city", option.name)
            .putString("district", option.district)
            .putString("area", "") // a manually picked city has no precise area
            .putString("plusCode", "") // …nor a precise Plus Code
            .apply()
        addRecent(option.name)
        return LocationState.City(option.name, option.district)
    }

    suspend fun detectCurrent(): LocationState {
        return try {
            val loc = getLastKnownLocation() ?: return LocationState.Denied
            @Suppress("DEPRECATION")
            val geocoder = Geocoder(context)
            val addresses = geocoder.getFromLocation(loc.latitude, loc.longitude, 1)
            val first = addresses?.firstOrNull()
            val city = first?.locality
                ?: first?.subAdminArea
                ?: "Unknown"
            val district = first?.subAdminArea ?: ""
            // Precise area: neighbourhood → street → landmark. Skip if it just echoes the city.
            val area = listOfNotNull(first?.subLocality, first?.thoroughfare, first?.featureName)
                .firstOrNull { it.isNotBlank() && !it.equals(city, ignoreCase = true) }
                ?: ""
            // Hyper-local Plus Code from the GPS fix — the precise local-identity signal
            // shown in the header alongside the human place name.
            val plusCode = PlusCode.localCode(loc.latitude, loc.longitude)
            prefs.edit()
                .putString("city", city)
                .putString("district", district)
                .putString("area", area)
                .putString("plusCode", plusCode)
                .apply()
            addRecent(city)
            LocationState.City(city, district, area, plusCode)
        } catch (e: Exception) {
            LocationState.Denied
        }
    }

    @Suppress("MissingPermission")
    private suspend fun getLastKnownLocation(): android.location.Location? =
        suspendCancellableCoroutine { cont ->
            try {
                val manager = context.getSystemService(Context.LOCATION_SERVICE) as android.location.LocationManager
                val providers = manager.getProviders(true)
                val loc = providers.mapNotNull { manager.getLastKnownLocation(it) }
                    .maxByOrNull { it.time }
                cont.resume(loc)
            } catch (e: Exception) {
                cont.resume(null)
            }
        }

    private fun addRecent(city: String) {
        val current = recents().map { it.name }.toMutableList()
        current.remove(city)
        current.add(0, city)
        prefs.edit().putString("recents", current.take(5).joinToString("|")).apply()
    }
}
