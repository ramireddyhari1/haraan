package com.haraan.app.data

import android.content.Context
import androidx.test.ext.junit.runners.AndroidJUnit4
import androidx.test.platform.app.InstrumentationRegistry
import java.io.File
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith

/**
 * Instrumented tests for [TokenStore].
 *
 * These have to run on a device: the store is backed by EncryptedSharedPreferences, which
 * needs the Android keystore and so cannot be exercised from a JVM unit test.
 */
@RunWith(AndroidJUnit4::class)
class TokenStoreTest {

  private val context: Context
    get() = InstrumentationRegistry.getInstrumentation().targetContext

  @Before
  fun clearStore() {
    // Clear through the store's own API rather than deleting the prefs file.
    // EncryptedSharedPreferences keeps its Tink keysets INSIDE that same file, so deleting
    // it out from under a still-unflushed `apply()` can leave a value encrypted under a
    // keyset that has just been regenerated. TokenStore swallows decryption failures, so
    // that race surfaces as a silent null read in an unrelated test rather than an error.
    TokenStore.clearToken(context)
  }

  @Test
  fun savedToken_roundTrips() {
    TokenStore.saveToken(context, SAMPLE_JWT)

    assertEquals(SAMPLE_JWT, TokenStore.getToken(context))
  }

  @Test
  fun clearToken_leavesNothingBehind() {
    TokenStore.saveToken(context, SAMPLE_JWT)

    TokenStore.clearToken(context)

    assertNull(TokenStore.getToken(context))
  }

  @Test
  fun emptyStore_readsAsNull() {
    assertNull(TokenStore.getToken(context))
  }

  /**
   * The whole reason this store is encrypted: a rooted or backed-up device must not yield
   * a usable session by reading the prefs file. Guards against someone "simplifying"
   * [TokenStore] down to plain SharedPreferences.
   */
  @Test
  fun tokenIsNotStoredInPlaintext() {
    val prefsFile = File(context.applicationInfo.dataDir, "shared_prefs/$PREFS_FILE.xml")
    val before = if (prefsFile.exists()) prefsFile.readText() else ""

    TokenStore.saveToken(context, SAMPLE_JWT)

    // saveToken uses apply(), which flushes on a background thread. Read too early and
    // this test would "pass" against a file that does not yet hold the token at all.
    val after = awaitChangedContent(prefsFile, before)

    assertFalse(
      "JWT found verbatim in $prefsFile — the store is no longer encrypted",
      after.contains(SAMPLE_JWT),
    )
  }

  /** Waits for an apply() to reach disk, and returns the file's new contents. */
  private fun awaitChangedContent(file: File, before: String): String {
    val deadline = System.currentTimeMillis() + FLUSH_TIMEOUT_MS
    while (System.currentTimeMillis() < deadline) {
      val now = if (file.exists()) file.readText() else ""
      if (now.isNotEmpty() && now != before) return now
      Thread.sleep(25)
    }
    throw AssertionError("$file never changed within ${FLUSH_TIMEOUT_MS}ms of saving a token")
  }

  /**
   * The guest marker is a local "browsing without an account" flag, never a credential.
   * Sending it to the API earns a 401, so gates must ask [TokenStore.isSignedIn] rather
   * than merely checking for a non-blank token.
   */
  @Test
  fun guestMarker_isStoredButNeverCountsAsSignedIn() {
    TokenStore.saveToken(context, TokenStore.GUEST_TOKEN)

    val token = TokenStore.getToken(context)
    assertEquals(TokenStore.GUEST_TOKEN, token)
    assertTrue(TokenStore.isGuest(token))
    assertFalse(TokenStore.isSignedIn(token))
  }

  @Test
  fun signedInIsTrueOnlyForARealToken() {
    assertFalse(TokenStore.isSignedIn(null))
    assertFalse(TokenStore.isSignedIn(""))
    assertFalse(TokenStore.isSignedIn("   "))
    assertFalse(TokenStore.isSignedIn(TokenStore.GUEST_TOKEN))
    assertTrue(TokenStore.isSignedIn(SAMPLE_JWT))
  }

  @Test
  fun guestIsTrueOnlyForTheExactMarker() {
    assertFalse(TokenStore.isGuest(null))
    assertFalse(TokenStore.isGuest(""))
    assertFalse(TokenStore.isGuest("guest"))
    assertTrue(TokenStore.isGuest(TokenStore.GUEST_TOKEN))
  }

  private companion object {
    /** Mirrors TokenStore's private PREFS_FILE. */
    const val PREFS_FILE = "haraan_secure_prefs"

    /** Generous — it only ever costs this long when the assertion is about to fail anyway. */
    const val FLUSH_TIMEOUT_MS = 5_000L

    /** Shaped like a JWT and distinctive enough to grep for in the raw prefs file. */
    const val SAMPLE_JWT =
      "eyJhbGciOiJIUzI1NiJ9.dG9rZW5zdG9yZXRlc3RwYXlsb2Fk.s1gnatur3-t0k3nst0r3-t3st"
  }
}
