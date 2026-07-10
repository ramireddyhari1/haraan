package com.example.thanna.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

/** A legal document (Terms & Conditions, Privacy Policy) as authored in /control. */
data class LegalDocument(
  val slug: String,
  val title: String,
  val body: String,
)

/**
 * Legal copy lives on the server so wording can change without a Play Store
 * release. Public endpoint — no token, because the terms must be readable before
 * you have an account.
 */
class LegalRepository(
  private val baseUrl: String = ApiConfig.BASE_URL,
) {
  suspend fun fetch(slug: String): LegalDocument = withContext(Dispatchers.IO) {
    val connection = (URL("${baseUrl.trimEnd('/')}/api/legal/$slug").openConnection() as HttpURLConnection).apply {
      requestMethod = "GET"
      connectTimeout = 15000
      readTimeout = 15000
      setRequestProperty("Accept", "application/json")
    }

    try {
      val code = connection.responseCode
      val stream = if (code >= 400) connection.errorStream else connection.inputStream
      val body = stream?.let { BufferedReader(InputStreamReader(it)).use { r -> r.readText() } } ?: ""
      if (code !in 200..299) {
        throw IllegalStateException("Couldn't load this document.")
      }
      val o = JSONObject(body)
      LegalDocument(
        slug = o.optString("slug", slug),
        title = o.optString("title", ""),
        body = o.optString("body", ""),
      )
    } finally {
      connection.disconnect()
    }
  }
}
