package com.haraan.app.data.membership

import com.haraan.app.data.net.HaraanHttp
import kotlinx.coroutines.CancellationException
import kotlinx.serialization.json.Json
import retrofit2.HttpException
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.POST
import retrofit2.http.PUT
import java.io.IOException

interface MembershipApi {
    @GET("api/membership/plans")
    suspend fun plans(@Header("Authorization") auth: String?): DataEnvelope<MembershipCatalogue>

    @GET("api/membership")
    suspend fun membership(@Header("Authorization") auth: String): DataEnvelope<Membership>

    @POST("api/membership/subscribe")
    suspend fun subscribe(@Header("Authorization") auth: String, @Body body: SubscribeBody): DataEnvelope<CheckoutSession>

    @POST("api/membership/verify")
    suspend fun verify(@Header("Authorization") auth: String, @Body body: VerifyBody): DataEnvelope<VerifyResult>

    @POST("api/membership/abandon")
    suspend fun abandon(@Header("Authorization") auth: String, @Body body: AbandonBody): DataEnvelope<Membership>

    @POST("api/membership/cancel")
    suspend fun cancel(@Header("Authorization") auth: String, @Body body: CancelBody): DataEnvelope<Membership>

    @GET("api/membership/insight-sports")
    suspend fun insightSports(@Header("Authorization") auth: String): DataEnvelope<InsightSportsStatus>

    @PUT("api/membership/insight-sports")
    suspend fun saveInsightSports(@Header("Authorization") auth: String, @Body body: InsightSportsBody): DataEnvelope<InsightSportsStatus>

    @GET("api/membership/payments")
    suspend fun payments(@Header("Authorization") auth: String): DataEnvelope<List<MemberPaymentItem>>
}

/** A failed membership call, with the server's own words and code when it gave them. */
class MembershipException(
    override val message: String,
    val code: String? = null,
    val httpStatus: Int? = null,
) : Exception(message)

/**
 * Member plans over /api/membership. Every call throws [MembershipException] with a message
 * fit to show the member; nothing here decides what a plan includes.
 */
class MembershipRepository(
    private val api: MembershipApi = HaraanHttp.create(MembershipApi::class.java),
    private val json: Json = HaraanHttp.json,
) {
    suspend fun catalogue(token: String?): MembershipCatalogue =
        call { api.plans(token?.let(::bearer)).data }

    suspend fun membership(token: String): Membership =
        call { api.membership(bearer(token)).data }

    suspend fun subscribe(token: String, priceId: Long): CheckoutSession =
        call { api.subscribe(bearer(token), SubscribeBody(priceId)).data }

    suspend fun verify(token: String, paymentId: String, subscriptionId: String, signature: String): VerifyResult =
        call { api.verify(bearer(token), VerifyBody(paymentId, subscriptionId, signature)).data }

    suspend fun abandon(token: String, subscriptionId: String): Membership =
        call { api.abandon(bearer(token), AbandonBody(subscriptionId)).data }

    suspend fun cancel(token: String, atPeriodEnd: Boolean): Membership =
        call { api.cancel(bearer(token), CancelBody(atPeriodEnd)).data }

    suspend fun payments(token: String): List<MemberPaymentItem> =
        call { api.payments(bearer(token)).data }

    suspend fun insightSports(token: String): InsightSportsStatus =
        call { api.insightSports(bearer(token)).data }

    suspend fun saveInsightSports(token: String, sports: List<String>): InsightSportsStatus =
        call { api.saveInsightSports(bearer(token), InsightSportsBody(sports)).data }

    private fun bearer(token: String) = "Bearer $token"

    private suspend fun <T> call(block: suspend () -> T): T = try {
        block()
    } catch (e: CancellationException) {
        throw e
    } catch (e: HttpException) {
        throw toMembershipException(e.code(), e.response()?.errorBody()?.string(), json)
    } catch (e: IOException) {
        throw MembershipException("Couldn't reach Haraan. Check your connection and try again.")
    } catch (e: kotlinx.serialization.SerializationException) {
        throw MembershipException("Something went wrong loading your plan.")
    }

    companion object {
        /** Pure, so the mapping is unit-tested without a network. */
        fun toMembershipException(status: Int, body: String?, json: Json): MembershipException {
            val parsed = body?.takeIf { it.isNotBlank() }?.let {
                runCatching { json.decodeFromString(MembershipErrorBody.serializer(), it) }.getOrNull()
            }
            val fallback = when (status) {
                401 -> "Your session has expired. Sign in again."
                409 -> "That change is already in progress."
                429 -> "Too many attempts. Wait a moment and try again."
                in 500..599 -> "Payments are having trouble right now. Try again shortly."
                else -> "Something went wrong. Try again."
            }
            return MembershipException(
                message = parsed?.error?.takeIf { it.isNotBlank() } ?: fallback,
                code = parsed?.code,
                httpStatus = status,
            )
        }
    }
}
