package com.haraan.app.data.devices

import android.content.Context
import com.haraan.app.data.net.HaraanHttp
import kotlinx.coroutines.CancellationException
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import retrofit2.HttpException
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.HeaderMap
import retrofit2.http.POST
import retrofit2.http.Path
import java.io.IOException

// Wire shapes for /api/account/devices. The server decides the limit, who is held and what
// the upgrade is; these only carry the answers.

@Serializable
data class DeviceState(
    val enforced: Boolean = true,
    val blocked: Boolean = false,
    @SerialName("this_device_id") val thisDeviceId: String? = null,
    val limit: Int? = null,
    @SerialName("limit_label") val limitLabel: String = "",
    val used: Int = 0,
    val plan: DevicePlan = DevicePlan(),
    val upgrade: DeviceUpgrade? = null,
    val title: String = "",
    val message: String = "",
    val devices: List<SignedInDevice> = emptyList(),
)

@Serializable
data class DevicePlan(val code: String = "free", val name: String = "Free")

@Serializable
data class DeviceUpgrade(
    val code: String,
    val name: String,
    val limit: Int? = null,
    @SerialName("limit_label") val limitLabel: String = "",
)

@Serializable
data class SignedInDevice(
    val id: String,
    val name: String,
    val platform: String = "android",
    val surface: String = "app",
    val status: String = "active",
    @SerialName("this_device") val thisDevice: Boolean = false,
    @SerialName("last_active_label") val lastActiveLabel: String = "",
)

@Serializable
private data class EnrollResponse(val token: String)

@Serializable
private data class SignOutResponse(@SerialName("signed_out") val signedOut: Boolean = false)

interface DeviceSessionApi {
    @GET("api/account/devices")
    suspend fun state(@Header("Authorization") auth: String): DeviceState

    @DELETE("api/account/devices/{id}")
    suspend fun signOut(@Header("Authorization") auth: String, @Path("id") id: String): retrofit2.Response<okhttp3.ResponseBody>

    @POST("api/account/devices/enroll")
    suspend fun enroll(@Header("Authorization") auth: String, @HeaderMap headers: Map<String, String>): retrofit2.Response<okhttp3.ResponseBody>
}

/** What a device check found. */
sealed interface DeviceCheck {
    data class Ok(val state: DeviceState) : DeviceCheck
    /** Over the plan's limit — show the chooser. */
    data class Held(val state: DeviceState) : DeviceCheck
    /** This phone was signed out from another device, the website or support. */
    data object SignedOut : DeviceCheck
    /** Couldn't tell (offline, server trouble). Never lock anyone out on this. */
    data object Unknown : DeviceCheck
}

class DeviceSessionRepository(
    private val api: DeviceSessionApi = HaraanHttp.create(DeviceSessionApi::class.java),
) {
    suspend fun check(token: String): DeviceCheck = guard {
        val state = api.state(bearer(token))
        if (state.blocked) DeviceCheck.Held(state) else DeviceCheck.Ok(state)
    }

    /**
     * Sign a device out. Returns the fresh check for this phone, or [DeviceCheck.SignedOut]
     * when the device signed out was this one.
     */
    suspend fun signOut(token: String, deviceId: String): DeviceCheck = guard {
        val response = api.signOut(bearer(token), deviceId)
        val body = response.body()?.string() ?: response.errorBody()?.string().orEmpty()
        when {
            response.code() == 401 -> DeviceCheck.SignedOut
            response.code() == 404 -> check(token)
            !response.isSuccessful -> DeviceCheck.Unknown
            HaraanHttp.json.decodeFromString(SignOutResponse.serializer(), body).signedOut -> DeviceCheck.SignedOut
            else -> {
                val state = HaraanHttp.json.decodeFromString(DeviceState.serializer(), body)
                if (state.blocked) DeviceCheck.Held(state) else DeviceCheck.Ok(state)
            }
        }
    }

    /** Re-issue a pre-device-limit session bound to this phone. Null when it couldn't. */
    suspend fun enroll(context: Context, token: String): String? = try {
        val response = api.enroll(bearer(token), DeviceIdentity.headers(context))
        if (response.isSuccessful) {
            response.body()?.string()?.let {
                HaraanHttp.json.decodeFromString(EnrollResponse.serializer(), it).token.takeIf(String::isNotBlank)
            }
        } else {
            null
        }
    } catch (e: CancellationException) {
        throw e
    } catch (_: Exception) {
        null
    }

    private fun bearer(token: String) = "Bearer $token"

    private suspend fun guard(block: suspend () -> DeviceCheck): DeviceCheck = try {
        block()
    } catch (e: CancellationException) {
        throw e
    } catch (e: HttpException) {
        val body = e.response()?.errorBody()?.string().orEmpty()
        if (e.code() == 401 && body.contains("device_signed_out")) DeviceCheck.SignedOut else DeviceCheck.Unknown
    } catch (_: IOException) {
        DeviceCheck.Unknown
    } catch (_: kotlinx.serialization.SerializationException) {
        DeviceCheck.Unknown
    }
}
