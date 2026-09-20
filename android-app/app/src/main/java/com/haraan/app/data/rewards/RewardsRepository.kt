package com.haraan.app.data.rewards

import com.haraan.app.data.net.HaraanHttp
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.channels.BufferOverflow
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow
import kotlinx.serialization.json.Json
import retrofit2.HttpException
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.POST
import retrofit2.http.Path
import java.io.IOException

interface RewardsApi {
    @GET("api/matches/{id}/rewards")
    suspend fun forMatch(@Header("Authorization") auth: String, @Path("id") matchId: String): RewardsEnvelope<MatchRewards>

    @POST("api/matches/{id}/rewards/seen")
    suspend fun seen(@Header("Authorization") auth: String, @Path("id") matchId: String)

    @POST("api/rewards/{id}/claim")
    suspend fun claim(@Header("Authorization") auth: String, @Path("id") grantId: Long): RewardsEnvelope<RewardItem>

    @GET("api/rewards/{id}")
    suspend fun show(@Header("Authorization") auth: String, @Path("id") grantId: Long): RewardsEnvelope<RewardItem>

    @POST("api/rewards/{id}/ad-session")
    suspend fun startAd(@Header("Authorization") auth: String, @Path("id") grantId: Long): RewardsEnvelope<AdSession>

    @GET("api/rewards/ad-sessions/{nonce}")
    suspend fun adStatus(@Header("Authorization") auth: String, @Path("nonce") nonce: String): RewardsEnvelope<AdSessionStatus>
}

/** A failed rewards call, in words fit to show the player, with the server's code. */
class RewardsException(override val message: String, val code: String? = null, val httpStatus: Int? = null) : Exception(message)

/**
 * Post-match rewards over /api. Nothing here decides who gets what — the server does; the app
 * shows it, claims it, and asks the server whether Google verified a video.
 */
class RewardsRepository(
    private val api: RewardsApi = HaraanHttp.create(RewardsApi::class.java),
    private val json: Json = HaraanHttp.json,
) {
    suspend fun forMatch(token: String, matchId: String): MatchRewards = call { api.forMatch(bearer(token), matchId).data }

    suspend fun markSeen(token: String, matchId: String) {
        runCatching { api.seen(bearer(token), matchId) }
    }

    suspend fun claim(token: String, grantId: Long): RewardItem = call { api.claim(bearer(token), grantId).data }

    suspend fun detail(token: String, grantId: Long): RewardItem = call { api.show(bearer(token), grantId).data }

    suspend fun startAd(token: String, grantId: Long): AdSession = call { api.startAd(bearer(token), grantId).data }

    suspend fun adStatus(token: String, nonce: String): AdSessionStatus = call { api.adStatus(bearer(token), nonce).data }

    private fun bearer(token: String) = "Bearer $token"

    private suspend fun <T> call(block: suspend () -> T): T = try {
        block()
    } catch (e: CancellationException) {
        throw e
    } catch (e: HttpException) {
        throw toRewardsException(e.code(), e.response()?.errorBody()?.string(), json)
    } catch (e: IOException) {
        throw RewardsException("Couldn't reach Haraan. Check your connection and try again.")
    } catch (e: kotlinx.serialization.SerializationException) {
        throw RewardsException("Something went wrong loading your rewards.")
    }

    companion object {
        fun toRewardsException(status: Int, body: String?, json: Json): RewardsException {
            val parsed = body?.takeIf { it.isNotBlank() }?.let {
                runCatching { json.decodeFromString(RewardsErrorBody.serializer(), it) }.getOrNull()
            }
            val fallback = when (status) {
                401 -> "Your session has expired. Sign in again."
                404 -> "Rewards are for the players in this match."
                409 -> "This reward can't be claimed right now."
                429 -> "That's enough for today. Try again tomorrow."
                in 500..599 -> "Rewards are having trouble right now. Try again shortly."
                else -> "Something went wrong. Try again."
            }
            return RewardsException(parsed?.error?.takeIf { it.isNotBlank() } ?: fallback, parsed?.code, status)
        }
    }
}

/**
 * "Show this player their rewards for match N" from anywhere — the scorer finishing a match, a
 * push notification, the match page. MainNavigation pushes the rewards screen.
 */
object RewardsNav {
    private val _requests = MutableSharedFlow<String>(extraBufferCapacity = 1, onBufferOverflow = BufferOverflow.DROP_OLDEST)
    val requests: SharedFlow<String> = _requests.asSharedFlow()

    fun open(matchId: String) {
        if (matchId.isNotBlank()) _requests.tryEmit(matchId)
    }
}
