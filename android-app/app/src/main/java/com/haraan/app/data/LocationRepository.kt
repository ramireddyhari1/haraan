package com.haraan.app.data

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
import kotlinx.coroutines.withTimeoutOrNull
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
    /**
     * The device's own location switch is OFF. Distinct from [Unavailable] because the
     * fix is different: no amount of retrying helps until the user turns it on, and an
     * app that says "couldn't get a fix" while the master switch is off is sending
     * people to look for a problem that isn't theirs to find.
     */
    object ServicesOff : LocationState()

    /** Permission is granted, services are on, but no fix could be obtained. */
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

    private companion object {
        /**
         * Every await here is time-boxed, because none of these APIs promise to come
         * back. `getCurrentLocation` waits for a fix that may never arrive indoors, and
         * `Geocoder`'s listener can simply never fire — on an emulator both answer
         * instantly from a mock provider, which is why this only ever bites real users.
         * A wrong answer fast beats a spinner that never resolves.
         */
        const val QUICK_FIX_MS = 6_000L
        const val GPS_FIX_MS = 12_000L
        const val GEOCODE_MS = 5_000L
    }

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

    /**
     * Instant city selection with no coordinates — used to update the UI the moment
     * the user taps a city, before the (async) geocode returns. Distance features
     * stay in city-string mode until [selectCity] upgrades this with real coords.
     */
    fun selectCityQuick(option: CityOption): LocationState {
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

    /**
     * Select a city and pin it to real coordinates via the Google Geocoding API
     * (the same key that powers the venue map). Those coords let events sort by true
     * km distance and the GameHub 30 km venue filter work off a chosen city, not just
     * a GPS fix. Falls back to the coordinate-less selection when geocoding fails.
     */
    suspend fun selectCity(option: CityOption): LocationState {
        val quick = selectCityQuick(option)
        val query = listOf(option.name, option.district, "India")
            .filter { it.isNotBlank() }
            .distinct()
            .joinToString(", ")
        val coords = com.haraan.app.ui.util.VenueMap.geocode(query) ?: return quick

        val plusCode = PlusCode.localCode(coords.first, coords.second)
        prefs.edit()
            .putString("plusCode", plusCode)
            .putString("lat", coords.first.toString())
            .putString("lng", coords.second.toString())
            .apply()
        return LocationState.Resolved(
            city = option.name,
            district = option.district,
            plusCode = plusCode,
            latitude = coords.first,
            longitude = coords.second,
        )
    }

    suspend fun detectCurrent(): LocationState {
        if (!hasPermission()) return LocationState.Denied
        // Ask BEFORE burning twelve seconds on a fix that cannot arrive.
        if (!servicesEnabled()) return LocationState.ServicesOff

        val loc = currentFix() ?: return LocationState.Unavailable

        // Name the coordinates. The platform geocoder first (free, offline-capable on
        // devices that ship a backend), then the web API, and only "Unknown" if both
        // fail — a real fix labelled "Unknown" reads to the user as no fix at all.
        val address = withTimeoutOrNull(GEOCODE_MS) { geocode(loc.latitude, loc.longitude) }
        var rawCity = address?.locality ?: address?.subAdminArea ?: ""
        var district = address?.subAdminArea ?: ""
        var area = listOfNotNull(address?.subLocality, address?.thoroughfare, address?.featureName)
            .firstOrNull { it.isNotBlank() && !it.equals(rawCity, ignoreCase = true) }
            ?: ""

        if (rawCity.isBlank()) {
            val web = com.haraan.app.ui.util.VenueMap.reverseGeocode(loc.latitude, loc.longitude)
            if (web != null) {
                rawCity = web.city.ifBlank { web.district }
                if (district.isBlank()) district = web.district
                if (area.isBlank() && !web.area.equals(rawCity, ignoreCase = true)) area = web.area
            }
        }

        // Normalise to the catalog spelling so it matches how events store their city.
        val city = CityCatalog.normalize(rawCity).ifBlank { "Unknown" }
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
     * Is the device's own location switch on? Nothing below works while it is off, and
     * `getCurrentLocation` does not fail fast in that state — it simply never answers.
     */
    private fun servicesEnabled(): Boolean = try {
        val manager = context.getSystemService(Context.LOCATION_SERVICE) as android.location.LocationManager
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            manager.isLocationEnabled
        } else {
            @Suppress("DEPRECATION")
            manager.isProviderEnabled(android.location.LocationManager.GPS_PROVIDER) ||
                manager.isProviderEnabled(android.location.LocationManager.NETWORK_PROVIDER)
        }
    } catch (e: Exception) {
        // Can't tell — assume on and let the fix attempt be the judge, rather than
        // blocking a user whose device just answers this question oddly.
        true
    }

    /**
     * Get a location fix, escalating and time-boxed at every step:
     *
     *  1. a quick balanced fix (wi-fi / cell — usually instant outdoors AND indoors),
     *  2. the fused last-known (free and immediate when there is one),
     *  3. a real GPS attempt, which is the slow one and so goes third, not first,
     *  4. the platform LocationManager, for handsets where Play services is missing
     *     or crippled — common on the budget devices this app is actually used on.
     *
     * The previous version awaited step 1 with NO timeout, so a phone that could not
     * produce a balanced fix never reached steps 2-4 and the UI sat on "Reading GPS…"
     * forever. City-level accuracy is all any caller needs, so the earliest answer wins.
     */
    @Suppress("MissingPermission")
    private suspend fun currentFix(): Location? =
        withTimeoutOrNull(QUICK_FIX_MS) { fusedCurrent(Priority.PRIORITY_BALANCED_POWER_ACCURACY) }
            ?: fusedLast()
            ?: withTimeoutOrNull(GPS_FIX_MS) { fusedCurrent(Priority.PRIORITY_HIGH_ACCURACY) }
            ?: legacyLast()

    @Suppress("MissingPermission")
    private suspend fun fusedCurrent(priority: Int): Location? = suspendCancellableCoroutine { cont ->
        try {
            val client = LocationServices.getFusedLocationProviderClient(context)
            val cts = CancellationTokenSource()
            client.getCurrentLocation(priority, cts.token)
                .addOnSuccessListener { if (cont.isActive) cont.resume(it) }
                .addOnFailureListener { if (cont.isActive) cont.resume(null) }
            // Also cancels on TIMEOUT, so an abandoned request stops holding the radio.
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

    /**
     * Reverse-geocode a fix with the PLATFORM geocoder; async API on Android 13+, sync
     * (deprecated) below. Returns null on any failure — the caller then falls back to
     * the web API. Guarded on [Geocoder.isPresent], which is false on handsets that
     * ship no geocoder backend at all and where this would otherwise always throw.
     */
    private suspend fun geocode(lat: Double, lng: Double): Address? = try {
        if (!Geocoder.isPresent()) {
            null
        } else if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
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
