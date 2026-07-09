package com.example.thanna.data

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.location.Address
import android.location.Geocoder
import android.location.Location
import android.os.Build
import androidx.core.content.ContextCompat
import com.google.android.gms.location.LocationServices
import com.google.android.gms.location.Priority
import com.google.android.gms.tasks.CancellationTokenSource
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlin.coroutines.resume

/**
 * Location outcomes. Success is a single [Resolved] state; failures are split so the
 * UI can say what actually went wrong instead of a blanket "Denied".
 */
sealed class LocationState {
    object Idle : LocationState()
    object Locating : LocationState()
    /** Permission was refused — prompt the user to grant it. */
    object Denied : LocationState()
    /** Permission is granted but no fix could be obtained (GPS off / timed out). */
    object Unavailable : LocationState()
    data class Resolved(
        val city: String,
        val district: String = "",
        val area: String = "",
        val plusCode: String = "",
        val latitude: Double? = null,
        val longitude: Double? = null,
    ) : LocationState()
}

data class CityOption(val name: String, val district: String = "")

class LocationRepository(private val context: Context) {

    private val prefs = context.getSharedPreferences("location_prefs", Context.MODE_PRIVATE)

    init {
        // Make the cached city list available synchronously for the picker.
        CityCatalog.warm(context)
    }

    fun hasPermission(): Boolean =
        ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION) ==
            PackageManager.PERMISSION_GRANTED ||
        ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION) ==
            PackageManager.PERMISSION_GRANTED

    fun cached(): LocationState? {
        val city = prefs.getString("city", null) ?: return null
        return LocationState.Resolved(
            city = city,
            district = prefs.getString("district", "") ?: "",
            area = prefs.getString("area", "") ?: "",
            plusCode = prefs.getString("plusCode", "") ?: "",
            latitude = prefs.getString("lat", null)?.toDoubleOrNull(),
            longitude = prefs.getString("lng", null)?.toDoubleOrNull(),
        )
    }

    fun recents(): List<CityOption> {
        val raw = prefs.getString("recents", "") ?: return emptyList()
        return raw.split("|").filter { it.isNotBlank() }.map { CityOption(it) }
    }

    /** Full catalog + popular subset for the picker (from the shared cities.json). */
    fun allCities(): List<CityOption> = CityCatalog.all()
    fun popularCities(): List<CityOption> = CityCatalog.popular()

    /** Refresh the city catalog from the server (call once when the app opens). */
    suspend fun refreshCatalog() = CityCatalog.refresh(context)

    fun selectCity(option: CityOption): LocationState {
        prefs.edit()
            .putString("city", option.name)
            .putString("district", option.district)
            .putString("area", "")     // a manually picked city has no precise area
            .putString("plusCode", "") // …nor a precise Plus Code
            .remove("lat")
            .remove("lng")
            .apply()
        addRecent(option.name)
        return LocationState.Resolved(option.name, option.district)
    }

    suspend fun detectCurrent(): LocationState {
        if (!hasPermission()) return LocationState.Denied

        val loc = currentFix() ?: return LocationState.Unavailable

        val address = geocode(loc.latitude, loc.longitude)
        val rawCity = address?.locality ?: address?.subAdminArea ?: ""
        // Normalise to the catalog spelling so it matches how events store their city.
        val city = CityCatalog.normalize(rawCity).ifBlank { "Unknown" }
        val district = address?.subAdminArea ?: ""
        // Precise area: neighbourhood → street → landmark. Skip if it echoes the city.
        val area = listOfNotNull(address?.subLocality, address?.thoroughfare, address?.featureName)
            .firstOrNull { it.isNotBlank() && !it.equals(rawCity, ignoreCase = true) }
            ?: ""
        val plusCode = PlusCode.localCode(loc.latitude, loc.longitude)

        prefs.edit()
            .putString("city", city)
            .putString("district", district)
            .putString("area", area)
            .putString("plusCode", plusCode)
            .putString("lat", loc.latitude.toString())
            .putString("lng", loc.longitude.toString())
            .apply()
        addRecent(city)
        return LocationState.Resolved(city, district, area, plusCode, loc.latitude, loc.longitude)
    }

    /**
     * Get a location fix. Prefers a *fresh* fix from the fused provider; falls back to
     * its last-known, then the platform LocationManager (in case Play services is
     * unavailable). Null when nothing can be obtained.
     */
    @Suppress("MissingPermission")
    private suspend fun currentFix(): Location? =
        fusedCurrent() ?: fusedLast() ?: legacyLast()

    @Suppress("MissingPermission")
    private suspend fun fusedCurrent(): Location? = suspendCancellableCoroutine { cont ->
        try {
            val client = LocationServices.getFusedLocationProviderClient(context)
            val cts = CancellationTokenSource()
            client.getCurrentLocation(Priority.PRIORITY_BALANCED_POWER_ACCURACY, cts.token)
                .addOnSuccessListener { if (cont.isActive) cont.resume(it) }
                .addOnFailureListener { if (cont.isActive) cont.resume(null) }
            cont.invokeOnCancellation { cts.cancel() }
        } catch (e: Exception) {
            if (cont.isActive) cont.resume(null)
        }
    }

    @Suppress("MissingPermission")
    private suspend fun fusedLast(): Location? = suspendCancellableCoroutine { cont ->
        try {
            LocationServices.getFusedLocationProviderClient(context).lastLocation
                .addOnSuccessListener { if (cont.isActive) cont.resume(it) }
                .addOnFailureListener { if (cont.isActive) cont.resume(null) }
        } catch (e: Exception) {
            if (cont.isActive) cont.resume(null)
        }
    }

    @Suppress("MissingPermission")
    private fun legacyLast(): Location? = try {
        val manager = context.getSystemService(Context.LOCATION_SERVICE) as android.location.LocationManager
        manager.getProviders(true)
            .mapNotNull { manager.getLastKnownLocation(it) }
            .maxByOrNull { it.time }
    } catch (e: Exception) {
        null
    }

    /** Reverse-geocode a fix; async API on Android 13+, sync (deprecated) below. */
    private suspend fun geocode(lat: Double, lng: Double): Address? = try {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            suspendCancellableCoroutine { cont ->
                Geocoder(context).getFromLocation(lat, lng, 1, object : Geocoder.GeocodeListener {
                    override fun onGeocode(addresses: MutableList<Address>) {
                        if (cont.isActive) cont.resume(addresses.firstOrNull())
                    }

                    override fun onError(errorMessage: String?) {
                        if (cont.isActive) cont.resume(null)
                    }
                })
            }
        } else {
            @Suppress("DEPRECATION")
            Geocoder(context).getFromLocation(lat, lng, 1)?.firstOrNull()
        }
    } catch (e: Exception) {
        null
    }

    private fun addRecent(city: String) {
        if (city.isBlank() || city == "Unknown") return
        val current = recents().map { it.name }.toMutableList()
        current.remove(city)
        current.add(0, city)
        prefs.edit().putString("recents", current.take(5).joinToString("|")).apply()
    }
}
