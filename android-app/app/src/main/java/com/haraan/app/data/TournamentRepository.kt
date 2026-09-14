package com.haraan.app.data

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Matrix
import android.media.ExifInterface
import android.net.Uri
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.ByteArrayOutputStream
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder

/** A player-hosted cricket tournament, as `GET /api/tournaments/{id}` describes it. */
data class Tournament(
  val id: String,
  val sport: String,
  val sportLabel: String,
  val name: String,
  val category: String,
  val categoryLabel: String,
  val description: String?,
  /** Public-disk paths; resolve with [ApiConfig.mediaUrl]. */
  val banner: String?,
  val logo: String?,
  val city: String,
  val venue: String?,
  val district: String?,
  val state: String?,
  /** yyyy-MM-dd. */
  val startDate: String,
  val endDate: String,
  /** upcoming · ongoing · completed — computed by the server from the dates. */
  val phase: String,
  val matchFormat: String,
  /** The format's own name: "T20", "7-a-side", "Super tiebreak". */
  val formatShort: String,
  /** One line for cards and shares: "7-a-side · 2 × 25 min". */
  val formatLabel: String,
  /** "Men · Under 19", "Open age". */
  val eligibilityLabel: String,
  /** The tournament page's format section, already worded for the sport by the server. */
  val formatDetails: List<Pair<String, String>>,
  /** "team" or "entry" — what the count and the entry fee are per. */
  val entryNoun: String,
  val structure: String,
  val structureLabel: String,
  val teamsCount: Int?,
  val entryFee: Int?,
  val prizePool: String?,
  val organizerName: String,
  val organizerPhone: String,
  val hostPlayerId: String?,
  val hostName: String?,
  val hostUsername: String?,
  val mine: Boolean,
)

/** Everything the create wizard collects. Images are picked Uris; the repository encodes them. */
data class NewTournament(
  val sport: String,
  val name: String,
  val category: String,
  val categoryOther: String?,
  val description: String?,
  val banner: Uri?,
  val logo: Uri?,
  /** men | women | mixed for team sports; null for racquet sports, which enter events. */
  val gender: String?,
  val ageGroup: String,
  val city: String,
  val venue: String?,
  val district: String?,
  val state: String?,
  val latitude: Double?,
  val longitude: Double?,
  val startDate: String,
  val endDate: String,
  val matchFormat: String,
  val formatName: String?,
  /** Cricket only. */
  val oversPerInnings: Int? = null,
  val matchDays: Int? = null,
  val ballType: String? = null,
  /** Null for racquet sports. */
  val playersPerSide: Int?,
  val surface: String?,
  /** Non-cricket custom format numbers; ignored by the server for presets. */
  val formatRules: Map<String, String> = emptyMap(),
  val events: List<String> = emptyList(),
  val options: Map<String, String> = emptyMap(),
  val structure: String,
  val teamsCount: Int?,
  val entryFee: Int?,
  val prizePool: String?,
  val organizerName: String,
  val organizerPhone: String,
)

/** A create call that the server turned down, with its own words for why. */
class TournamentCreateException(message: String) : Exception(message)

class TournamentRepository(private val baseUrl: String = ApiConfig.BASE_URL) {

  /**
   * POST /api/tournaments as multipart, banner and logo included, so a tournament is never
   * saved without the images the organiser picked. Throws [TournamentCreateException] with
   * the server's message (first validation error, trust gate, daily limit) on refusal.
   */
  suspend fun create(context: Context, token: String, t: NewTournament): Tournament = withContext(Dispatchers.IO) {
    // Encoded before the connection opens: a photo that can't be read is the organiser's
    // problem to fix, and shouldn't cost a half-sent request.
    val bannerBytes = t.banner?.let { encodeImage(context, it, aspect = 16f / 9f, maxWidth = 1600) }
    val logoBytes = t.logo?.let { encodeImage(context, it, aspect = 1f, maxWidth = 600) }

    val boundary = "----HaraanTournament${System.currentTimeMillis()}"
    val connection = (URL("${baseUrl.trimEnd('/')}/api/tournaments").openConnection() as HttpURLConnection).apply {
      requestMethod = "POST"
      doOutput = true
      connectTimeout = 20000
      readTimeout = 30000
      setRequestProperty("Content-Type", "multipart/form-data; boundary=$boundary")
      setRequestProperty("Accept", "application/json")
      setRequestProperty("Authorization", "Bearer $token")
    }

    val fields = buildList {
      add("sport" to t.sport)
      add("name" to t.name)
      add("category" to t.category)
      t.categoryOther?.let { add("category_other" to it) }
      t.description?.let { add("description" to it) }
      t.gender?.let { add("gender" to it) }
      add("age_group" to t.ageGroup)
      add("city" to t.city)
      t.venue?.let { add("venue" to it) }
      t.district?.let { add("district" to it) }
      t.state?.let { add("state" to it) }
      t.latitude?.let { add("latitude" to it.toString()) }
      t.longitude?.let { add("longitude" to it.toString()) }
      add("start_date" to t.startDate)
      add("end_date" to t.endDate)
      add("match_format" to t.matchFormat)
      t.formatName?.let { add("format_name" to it) }
      t.oversPerInnings?.let { add("overs_per_innings" to it.toString()) }
      t.matchDays?.let { add("match_days" to it.toString()) }
      t.ballType?.let { add("ball_type" to it) }
      t.playersPerSide?.let { add("players_per_side" to it.toString()) }
      t.surface?.let { add("surface" to it) }
      t.formatRules.forEach { (key, value) -> add("format_rules[$key]" to value) }
      t.events.forEach { add("events[]" to it) }
      t.options.forEach { (key, value) -> add("options[$key]" to value) }
      add("structure" to t.structure)
      t.teamsCount?.let { add("teams_count" to it.toString()) }
      t.entryFee?.let { add("entry_fee" to it.toString()) }
      t.prizePool?.let { add("prize_pool" to it) }
      add("organizer_name" to t.organizerName)
      add("organizer_phone" to t.organizerPhone)
    }

    try {
      connection.outputStream.use { out ->
        val crlf = "\r\n"
        fields.forEach { (key, value) ->
          out.write("--$boundary$crlf".toByteArray())
          out.write("Content-Disposition: form-data; name=\"$key\"$crlf".toByteArray())
          out.write("Content-Type: text/plain; charset=UTF-8$crlf$crlf".toByteArray())
          out.write(value.toByteArray(Charsets.UTF_8))
          out.write(crlf.toByteArray())
        }
        listOf("banner" to bannerBytes, "logo" to logoBytes).forEach { (key, bytes) ->
          if (bytes == null) return@forEach
          out.write("--$boundary$crlf".toByteArray())
          out.write("Content-Disposition: form-data; name=\"$key\"; filename=\"$key.jpg\"$crlf".toByteArray())
          out.write("Content-Type: image/jpeg$crlf$crlf".toByteArray())
          out.write(bytes)
          out.write(crlf.toByteArray())
        }
        out.write("--$boundary--$crlf".toByteArray())
      }

      val code = connection.responseCode
      val body = readBody(connection)
      if (code !in 200..299) throw TournamentCreateException(errorMessage(code, body))
      parse(JSONObject(body).getJSONObject("data"))
    } finally {
      connection.disconnect()
    }
  }

  /** GET /api/tournaments/{id}. Null when it doesn't exist or the call failed. */
  suspend fun fetch(token: String?, id: String): Tournament? = withContext(Dispatchers.IO) {
    getJson("/api/tournaments/${URLEncoder.encode(id, "UTF-8")}", token)?.optJSONObject("data")?.let(::parse)
  }

  /**
   * The tournaments [playerId] hosts, for the profile's Tournaments tab. Null means the call
   * failed — kept distinct from an empty list, which means "hasn't hosted one".
   */
  suspend fun forPlayer(token: String?, playerId: String): List<Tournament>? = withContext(Dispatchers.IO) {
    val json = getJson("/api/players/${URLEncoder.encode(playerId.trim(), "UTF-8")}/tournaments", token)
      ?: return@withContext null
    val arr = json.optJSONArray("results") ?: return@withContext emptyList()
    buildList {
      for (i in 0 until arr.length()) arr.optJSONObject(i)?.let { runCatching { parse(it) }.getOrNull()?.let(::add) }
    }
  }

  private fun getJson(path: String, token: String?): JSONObject? {
    val connection = (URL(baseUrl.trimEnd('/') + path).openConnection() as HttpURLConnection).apply {
      requestMethod = "GET"
      connectTimeout = 10000
      readTimeout = 10000
      setRequestProperty("Accept", "application/json")
      if (!token.isNullOrBlank()) setRequestProperty("Authorization", "Bearer $token")
    }
    return try {
      if (connection.responseCode !in 200..299) null
      else JSONObject(BufferedReader(InputStreamReader(connection.inputStream)).use { it.readText() })
    } catch (_: Exception) {
      null
    } finally {
      connection.disconnect()
    }
  }

  private fun parse(o: JSONObject): Tournament {
    fun str(key: String): String? = o.optString(key).takeIf { !o.isNull(key) && it.isNotBlank() }
    fun int(key: String): Int? = if (o.isNull(key) || !o.has(key)) null else o.optInt(key)
    val host = o.optJSONObject("host")
    val details = o.optJSONArray("format_details")
    return Tournament(
      id = o.getString("id"),
      sport = o.optString("sport", "cricket"),
      sportLabel = o.optString("sport_label", "Cricket"),
      name = o.optString("name"),
      category = o.optString("category"),
      categoryLabel = o.optString("category_label"),
      description = str("description"),
      banner = str("banner"),
      logo = str("logo"),
      city = o.optString("city"),
      venue = str("venue"),
      district = str("district"),
      state = str("state"),
      startDate = o.optString("start_date"),
      endDate = o.optString("end_date"),
      phase = o.optString("phase", "upcoming"),
      matchFormat = o.optString("match_format"),
      formatShort = o.optString("format_short"),
      formatLabel = o.optString("format_label"),
      eligibilityLabel = o.optString("eligibility_label"),
      formatDetails = buildList {
        for (i in 0 until (details?.length() ?: 0)) {
          val row = details!!.optJSONObject(i) ?: continue
          val label = row.optString("label")
          val value = row.optString("value")
          if (label.isNotBlank() && value.isNotBlank()) add(label to value)
        }
      },
      entryNoun = o.optString("entry_noun", "team"),
      structure = o.optString("structure"),
      structureLabel = o.optString("structure_label"),
      teamsCount = int("teams_count"),
      entryFee = int("entry_fee"),
      prizePool = str("prize_pool"),
      organizerName = o.optString("organizer_name"),
      organizerPhone = o.optString("organizer_phone"),
      hostPlayerId = host?.optString("player_id")?.takeIf { it.isNotBlank() && it != "null" },
      hostName = host?.optString("name")?.takeIf { it.isNotBlank() && it != "null" },
      hostUsername = host?.optString("username")?.takeIf { it.isNotBlank() && it != "null" },
      mine = o.optBoolean("mine"),
    )
  }

  private fun readBody(connection: HttpURLConnection): String {
    val stream = (if (connection.responseCode >= 400) connection.errorStream else connection.inputStream) ?: return ""
    return BufferedReader(InputStreamReader(stream)).use { it.readText() }
  }

  /** The first validation error when there is one — it names the field — else the server's message. */
  private fun errorMessage(code: Int, body: String): String {
    val fallback = if (code == 413) "Those images are too large. Pick smaller ones." else "Couldn't create the tournament. Try again."
    return try {
      val json = JSONObject(body)
      val errors = json.optJSONObject("errors")
      val firstField = errors?.keys()?.asSequence()?.firstOrNull()
      val firstError = firstField?.let { errors.optJSONArray(it)?.optString(0) }
      firstError?.takeIf { it.isNotBlank() }
        ?: json.optString("error").takeIf { it.isNotBlank() }
        ?: json.optString("message").takeIf { it.isNotBlank() }
        ?: fallback
    } catch (_: Exception) {
      fallback
    }
  }

  /**
   * Reads a picked photo, applies its EXIF rotation, centre-crops it to [aspect] (width ÷
   * height) — the same framing the wizard previews with ContentScale.Crop — and returns a
   * JPEG no wider than [maxWidth]. A phone camera photo is 4–12 MB; the server caps uploads.
   */
  private fun encodeImage(context: Context, uri: Uri, aspect: Float, maxWidth: Int): ByteArray {
    val resolver = context.contentResolver
    val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
    resolver.openInputStream(uri)?.use { BitmapFactory.decodeStream(it, null, bounds) }
    if (bounds.outWidth <= 0 || bounds.outHeight <= 0) {
      throw TournamentCreateException("Couldn't read that image. Pick another one.")
    }
    var sample = 1
    while (bounds.outWidth / (sample * 2) >= maxWidth && bounds.outHeight / (sample * 2) >= maxWidth / aspect) sample *= 2
    var bmp = resolver.openInputStream(uri)?.use {
      BitmapFactory.decodeStream(it, null, BitmapFactory.Options().apply { inSampleSize = sample })
    } ?: throw TournamentCreateException("Couldn't read that image. Pick another one.")

    val orientation = resolver.openInputStream(uri)?.use {
      runCatching { ExifInterface(it).getAttributeInt(ExifInterface.TAG_ORIENTATION, ExifInterface.ORIENTATION_NORMAL) }
        .getOrDefault(ExifInterface.ORIENTATION_NORMAL)
    } ?: ExifInterface.ORIENTATION_NORMAL
    val m = Matrix()
    when (orientation) {
      ExifInterface.ORIENTATION_ROTATE_90 -> m.postRotate(90f)
      ExifInterface.ORIENTATION_ROTATE_180 -> m.postRotate(180f)
      ExifInterface.ORIENTATION_ROTATE_270 -> m.postRotate(270f)
      ExifInterface.ORIENTATION_FLIP_HORIZONTAL -> m.postScale(-1f, 1f)
      ExifInterface.ORIENTATION_FLIP_VERTICAL -> m.postScale(1f, -1f)
    }
    if (!m.isIdentity) bmp = Bitmap.createBitmap(bmp, 0, 0, bmp.width, bmp.height, m, true)

    // Centre-crop to the target shape.
    val srcAspect = bmp.width.toFloat() / bmp.height
    val (cw, ch) = if (srcAspect > aspect) {
      (bmp.height * aspect).toInt() to bmp.height
    } else {
      bmp.width to (bmp.width / aspect).toInt()
    }
    var out = Bitmap.createBitmap(bmp, (bmp.width - cw) / 2, (bmp.height - ch) / 2, cw.coerceAtLeast(1), ch.coerceAtLeast(1))
    if (out.width > maxWidth) {
      out = Bitmap.createScaledBitmap(out, maxWidth, (maxWidth / aspect).toInt().coerceAtLeast(1), true)
    }
    return ByteArrayOutputStream().use { s ->
      out.compress(Bitmap.CompressFormat.JPEG, 86, s)
      s.toByteArray()
    }
  }
}
