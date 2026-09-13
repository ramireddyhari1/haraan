package com.haraan.app.data.net

import com.haraan.app.BuildConfig
import com.haraan.app.data.ApiConfig
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import java.util.concurrent.TimeUnit

/**
 * The one typed HTTP client for the app's Retrofit services.
 *
 * Older repositories still hand-roll HttpURLConnection; new endpoints go through here so
 * timeouts, the `X-App-Version` header and JSON leniency are decided once. Migrate the
 * older repos onto it as they are touched, not in one sweep.
 */
object HaraanHttp {

    /**
     * Lenient on purpose: the backend adds fields ahead of app releases, and an old build
     * must keep decoding what it understands instead of failing the whole payload.
     */
    val json: Json = Json {
        ignoreUnknownKeys = true
        explicitNulls = false
        coerceInputValues = true
    }

    val client: OkHttpClient by lazy {
        OkHttpClient.Builder()
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(15, TimeUnit.SECONDS)
            .addInterceptor { chain ->
                chain.proceed(
                    chain.request().newBuilder()
                        .header("Accept", "application/json")
                        .header("X-App-Version", BuildConfig.VERSION_NAME)
                        .build()
                )
            }
            .build()
    }

    private val retrofit: Retrofit by lazy {
        Retrofit.Builder()
            // Retrofit requires the trailing slash; ApiConfig strips it.
            .baseUrl(ApiConfig.BASE_URL + "/")
            .client(client)
            .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
            .build()
    }

    fun <T> create(service: Class<T>): T = retrofit.create(service)
}
