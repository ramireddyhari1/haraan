package com.haraan.app.data

import android.content.Context
import androidx.test.ext.junit.runners.AndroidJUnit4
import androidx.test.platform.app.InstrumentationRegistry
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith

/**
 * Instrumented tests for [AccountStore], the multi-account roster.
 *
 * These have to run on a device: the roster lives in EncryptedSharedPreferences, which
 * needs a real Context and the Android keystore.
 *
 * The invariant every test here ultimately defends is the one [AccountStore] documents:
 * **the active account's token and [TokenStore.getToken] are always the same string.**
 * Break it and the switcher names one account while requests carry another's session.
 */
@RunWith(AndroidJUnit4::class)
class AccountStoreTest {

  private val context: Context
    get() = InstrumentationRegistry.getInstrumentation().targetContext

  @Before
  fun clearStore() {
    // Clear through the store's own API rather than deleting the prefs file — clearAll
    // resets roster and session together, which is the only state the app is ever supposed
    // to be in. Deleting the file is NOT equivalent: EncryptedSharedPreferences keeps its
    // Tink keysets inside that same file, so removing it out from under a still-unflushed
    // `apply()` can leave a value encrypted under a keyset that has just been regenerated.
    // Both stores swallow decryption failures, so that race surfaces as a silent null read
    // in an unrelated test rather than as an error.
    AccountStore.clearAll(context)
  }

  // ---------------------------------------------------------------- adding & activating

  @Test
  fun upsert_addsAccountAndMakesItActive() {
    AccountStore.upsertAndActivate(context, alice)

    assertEquals(listOf(alice), AccountStore.accounts(context))
    assertEquals(alice.token, TokenStore.getToken(context))
    assertEquals(alice, AccountStore.active(context))
  }

  @Test
  fun upsert_keepsInsertionOrder() {
    AccountStore.upsertAndActivate(context, alice)
    AccountStore.upsertAndActivate(context, bob)
    AccountStore.upsertAndActivate(context, carol)

    assertEquals(listOf(alice, bob, carol), AccountStore.accounts(context))
  }

  /**
   * Re-signing in must REPLACE the entry, not append a second one. A duplicate would leave
   * the roster showing one person twice, with the older row holding a token the server
   * revoked the instant the newer one was issued.
   */
  @Test
  fun reAddingSameAccount_replacesInsteadOfDuplicating() {
    AccountStore.upsertAndActivate(context, alice)
    AccountStore.upsertAndActivate(context, bob)

    val aliceReSignedIn = alice.copy(token = "jwt-alice-v2", name = "Alice Renamed")
    AccountStore.upsertAndActivate(context, aliceReSignedIn)

    val accounts = AccountStore.accounts(context)
    assertEquals(2, accounts.size)
    assertEquals(1, accounts.count { it.playerId == alice.playerId })
    // The fresh row wins wholesale — token and display material both.
    assertTrue(accounts.contains(aliceReSignedIn))
    assertEquals("jwt-alice-v2", TokenStore.getToken(context))
    assertEquals(aliceReSignedIn, AccountStore.active(context))
  }

  /**
   * Adding past the ceiling must be refused OUTRIGHT, not half-applied. The dangerous
   * version stored the token without the roster row — the roster is truncated to
   * [AccountStore.MAX_ACCOUNTS] from the end and the new entry sits last — so requests
   * carried a session the switcher had no row for and `active()` answered null.
   * [AccountStore.isFull] lets the UI grey the button out first; it is no longer what
   * keeps the store correct.
   */
  @Test
  fun addingPastTheCeiling_isRefusedAndLeavesTheSessionAlone() {
    val roster = (1..AccountStore.MAX_ACCOUNTS).map { account("HRN-FULL-$it") }
    roster.forEach { AccountStore.upsertAndActivate(context, it) }

    assertTrue(AccountStore.isFull(context))
    val activeBefore = AccountStore.active(context)
    assertEquals(roster.last(), activeBefore)

    val overflow = account("HRN-OVERFLOW")
    val result = AccountStore.upsertAndActivate(context, overflow)

    assertEquals(AddAccountResult.RosterFull, result)
    assertEquals(roster, AccountStore.accounts(context))
    // The whole point: the session never moved to an account we refused to store.
    assertEquals(activeBefore!!.token, TokenStore.getToken(context))
    assertEquals(
      "the live token must still belong to a stored account",
      activeBefore,
      AccountStore.active(context),
    )
  }

  /**
   * A full roster must not lock out the people already on it. Re-signing in REPLACES a row
   * rather than growing the list, so there is nothing to truncate and it has to work.
   */
  @Test
  fun reAddingAnExistingAccountStillWorksAtTheCeiling() {
    val roster = (1..AccountStore.MAX_ACCOUNTS).map { account("HRN-FULL-$it") }
    roster.forEach { AccountStore.upsertAndActivate(context, it) }
    assertTrue(AccountStore.isFull(context))

    val refreshed = roster.first().copy(token = "jwt-refreshed")
    val result = AccountStore.upsertAndActivate(context, refreshed)

    assertEquals(AddAccountResult.Added(refreshed), result)
    assertEquals(AccountStore.MAX_ACCOUNTS, AccountStore.accounts(context).size)
    // Replaced in place as far as membership goes; it moves to the end, like any upsert.
    assertEquals(roster.drop(1) + refreshed, AccountStore.accounts(context))
    assertEquals("jwt-refreshed", TokenStore.getToken(context))
    assertEquals(refreshed, AccountStore.active(context))
  }

  /**
   * Adoption forwards to the same guarded path, so a pre-existing session that has no room
   * must leave BOTH the roster and the live token exactly as they were.
   */
  @Test
  fun adoptingIntoAFullRoster_isRefusedRatherThanCorrupting() {
    val roster = (1..AccountStore.MAX_ACCOUNTS).map { account("HRN-FULL-$it") }
    roster.forEach { AccountStore.upsertAndActivate(context, it) }

    val stranger = account("HRN-STRANGER")
    TokenStore.saveToken(context, stranger.token)

    val result = AccountStore.adoptExistingSessionIfNeeded(context, profileOf(stranger))

    assertEquals(AddAccountResult.RosterFull, result)
    assertEquals(roster, AccountStore.accounts(context))
    assertEquals(stranger.token, TokenStore.getToken(context))
  }

  /** Below the ceiling, the ordinary add reports what it stored. */
  @Test
  fun upsert_reportsWhatItStored() {
    assertEquals(AddAccountResult.Added(alice), AccountStore.upsertAndActivate(context, alice))
  }

  // -------------------------------------------------------------------------- switching

  @Test
  fun switchTo_rewritesTheLiveSession() {
    AccountStore.upsertAndActivate(context, alice)
    AccountStore.upsertAndActivate(context, bob)

    val switched = AccountStore.switchTo(context, alice.playerId)

    assertEquals(alice, switched)
    assertEquals(alice.token, TokenStore.getToken(context))
    assertEquals(alice, AccountStore.active(context))
  }

  @Test
  fun switchTo_unknownAccount_returnsNullAndLeavesSessionAlone() {
    AccountStore.upsertAndActivate(context, alice)

    assertNull(AccountStore.switchTo(context, "HRN-NOBODY"))
    assertEquals(alice.token, TokenStore.getToken(context))
  }

  /**
   * `active()` resolves by matching the live token rather than reading a stored flag. This
   * is what stops the switcher from drifting: rewrite the token behind its back and the
   * answer changes with it.
   */
  @Test
  fun active_followsTheTokenNotAStoredFlag() {
    AccountStore.upsertAndActivate(context, alice)
    AccountStore.upsertAndActivate(context, bob)
    assertEquals(bob, AccountStore.active(context))

    TokenStore.saveToken(context, alice.token)

    assertEquals(alice, AccountStore.active(context))
  }

  @Test
  fun active_isNullWhenTheLiveTokenBelongsToNobody() {
    AccountStore.upsertAndActivate(context, alice)

    TokenStore.saveToken(context, "jwt-belonging-to-no-stored-account")

    assertNull(AccountStore.active(context))
  }

  // --------------------------------------------------------------------------- removing

  /**
   * Removing the account you are acting as must hand the session to someone — otherwise
   * the app keeps a token for an account it has forgotten.
   */
  @Test
  fun removingActiveAccount_promotesFirstRemaining() {
    AccountStore.upsertAndActivate(context, alice)
    AccountStore.upsertAndActivate(context, bob)
    AccountStore.upsertAndActivate(context, carol) // carol is active

    val next = AccountStore.remove(context, carol.playerId)

    // "Next" means first in the roster, i.e. the oldest — not the most recently added.
    assertEquals(alice, next)
    assertEquals(alice.token, TokenStore.getToken(context))
    assertEquals(alice, AccountStore.active(context))
    assertEquals(listOf(alice, bob), AccountStore.accounts(context))
  }

  /** Removing someone else must not move the session out from under the user. */
  @Test
  fun removingInactiveAccount_leavesActiveUntouched() {
    AccountStore.upsertAndActivate(context, alice)
    AccountStore.upsertAndActivate(context, bob) // bob is active

    val stillActive = AccountStore.remove(context, alice.playerId)

    assertEquals(bob, stillActive)
    assertEquals(bob.token, TokenStore.getToken(context))
    assertEquals(listOf(bob), AccountStore.accounts(context))
  }

  /** The last sign-out must leave no token behind — the login wall has to appear. */
  @Test
  fun removingLastAccount_clearsTheSession() {
    AccountStore.upsertAndActivate(context, alice)

    val next = AccountStore.remove(context, alice.playerId)

    assertNull(next)
    assertNull(TokenStore.getToken(context))
    assertTrue(AccountStore.accounts(context).isEmpty())
    assertNull(AccountStore.active(context))
  }

  @Test
  fun removingUnknownAccount_isANoOp() {
    AccountStore.upsertAndActivate(context, alice)
    AccountStore.upsertAndActivate(context, bob)

    val stillActive = AccountStore.remove(context, "HRN-NOBODY")

    assertEquals(bob, stillActive)
    assertEquals(listOf(alice, bob), AccountStore.accounts(context))
    assertEquals(bob.token, TokenStore.getToken(context))
  }

  @Test
  fun clearAll_forgetsRosterAndSession() {
    AccountStore.upsertAndActivate(context, alice)
    AccountStore.upsertAndActivate(context, bob)

    AccountStore.clearAll(context)

    assertTrue(AccountStore.accounts(context).isEmpty())
    assertNull(TokenStore.getToken(context))
    assertNull(AccountStore.active(context))
  }

  // ------------------------------------------------------------------------ guest marker

  /**
   * The guest marker is a local "browsing without an account" flag, not a credential.
   * If it ever landed in the roster the switcher would offer "sign in as guest" and the
   * app would send `skipped_guest` as a bearer token, which the API answers with 401.
   */
  @Test
  fun guestMarker_neverEntersTheRoster() {
    TokenStore.saveToken(context, TokenStore.GUEST_TOKEN)

    AccountStore.adoptExistingSessionIfNeeded(context, profileOf(alice))

    assertTrue(AccountStore.accounts(context).isEmpty())
    assertEquals(TokenStore.GUEST_TOKEN, TokenStore.getToken(context))
  }

  @Test
  fun guestSession_isNeverReportedAsAnActiveAccount() {
    AccountStore.upsertAndActivate(context, alice)

    TokenStore.saveToken(context, TokenStore.GUEST_TOKEN)

    assertNull(AccountStore.active(context))
    assertFalse(AccountStore.hasMultiple(context))
  }

  @Test
  fun noSession_adoptsNothing() {
    AccountStore.adoptExistingSessionIfNeeded(context, profileOf(alice))

    assertTrue(AccountStore.accounts(context).isEmpty())
  }

  // ---------------------------------------------------------------------------- adoption

  /**
   * An app that was signed in before the switcher shipped has a live token and an empty
   * roster. Without adoption the switcher would claim "no accounts" to a signed-in user.
   */
  @Test
  fun adopt_backfillsRosterForAPreExistingSession() {
    TokenStore.saveToken(context, alice.token)

    AccountStore.adoptExistingSessionIfNeeded(context, profileOf(alice))

    assertEquals(listOf(alice), AccountStore.accounts(context))
    assertEquals(alice, AccountStore.active(context))
    assertEquals(alice.token, TokenStore.getToken(context))
  }

  @Test
  fun adopt_isIdempotentAndDoesNotDisturbAnExistingRoster() {
    AccountStore.upsertAndActivate(context, alice)
    AccountStore.upsertAndActivate(context, bob) // bob active

    AccountStore.adoptExistingSessionIfNeeded(context, profileOf(bob))
    AccountStore.adoptExistingSessionIfNeeded(context, profileOf(bob))

    assertEquals(listOf(alice, bob), AccountStore.accounts(context))
    assertEquals(bob, AccountStore.active(context))
  }

  // ------------------------------------------------------------------------- persistence

  @Test
  fun rosterSurvivesSerialisation_includingNullDisplayFields() {
    val handleless = StoredAccount(
      playerId = "HRN-NULLS",
      name = "No Handle",
      username = null,
      avatar = null,
      token = "jwt-nulls",
    )
    AccountStore.upsertAndActivate(context, handleless)

    val reloaded = AccountStore.accounts(context).single()
    assertEquals(handleless, reloaded)
    assertNull(reloaded.username)
    assertNull(reloaded.avatar)
    // Falls back to the HRN id rather than rendering "@null".
    assertEquals("HRN-NULLS", reloaded.handleOrId)
  }

  @Test
  fun handleOrId_prefersTheUsername() {
    assertEquals("@alice", alice.handleOrId)
  }

  @Test
  fun hasMultiple_onlyOnceThereIsSomethingToSwitchTo() {
    assertFalse(AccountStore.hasMultiple(context))

    AccountStore.upsertAndActivate(context, alice)
    assertFalse(AccountStore.hasMultiple(context))

    AccountStore.upsertAndActivate(context, bob)
    assertTrue(AccountStore.hasMultiple(context))
  }

  /** Tokens are the reason the roster is encrypted; they must not sit in the file verbatim. */
  @Test
  fun rosterIsNotStoredInPlaintext() {
    val prefsFile =
      java.io.File(context.applicationInfo.dataDir, "shared_prefs/$PREFS_FILE.xml")
    val before = if (prefsFile.exists()) prefsFile.readText() else ""

    AccountStore.upsertAndActivate(context, bob)

    // write() uses apply(), which flushes on a background thread. Read too early and this
    // test would "pass" against a file that does not yet hold the roster at all.
    val raw = awaitChangedContent(prefsFile, before)
    assertFalse("token found verbatim in $prefsFile", raw.contains(bob.token))
    assertFalse("player id found verbatim in $prefsFile", raw.contains(bob.playerId))
  }

  /** Waits for an apply() to reach disk, and returns the file's new contents. */
  private fun awaitChangedContent(file: java.io.File, before: String): String {
    val deadline = System.currentTimeMillis() + FLUSH_TIMEOUT_MS
    while (System.currentTimeMillis() < deadline) {
      val now = if (file.exists()) file.readText() else ""
      if (now.isNotEmpty() && now != before) return now
      Thread.sleep(25)
    }
    throw AssertionError("$file never changed within ${FLUSH_TIMEOUT_MS}ms of writing the roster")
  }

  // ----------------------------------------------------------------------------- fixtures

  private fun account(id: String) = StoredAccount(
    playerId = id,
    name = "Player $id",
    username = id.lowercase(),
    avatar = null,
    token = "jwt-$id",
  )

  /** A profile whose identity fields match [account], for the adoption path. */
  private fun profileOf(account: StoredAccount) = PlayerProfile(
    id = 1,
    playerId = account.playerId,
    username = account.username,
    name = account.name,
    avatar = account.avatar,
    district = null,
    state = null,
    isOrganizer = false,
    rankedXp = 0,
    casualXp = 0,
    trustScore = 0,
    monthRankedXp = 0,
    rankDistrict = null,
    rankState = null,
    rankCountry = null,
    careerMatches = 0,
    careerRuns = 0,
    careerWickets = 0,
    profileComplete = false,
    recentMatches = emptyList(),
  )

  private val alice = StoredAccount(
    playerId = "HRN-ALICE",
    name = "Alice",
    username = "alice",
    avatar = "https://haraan.app/avatars/alice.png",
    token = "jwt-alice-v1",
  )

  private val bob = StoredAccount(
    playerId = "HRN-BOB",
    name = "Bob",
    username = "bob",
    avatar = null,
    token = "jwt-bob",
  )

  private val carol = StoredAccount(
    playerId = "HRN-CAROL",
    name = "Carol",
    username = "carol",
    avatar = null,
    token = "jwt-carol",
  )

  private companion object {
    /** Mirrors the private PREFS_FILE that AccountStore and TokenStore share. */
    const val PREFS_FILE = "haraan_secure_prefs"

    /** Generous — it only ever costs this long when the assertion is about to fail anyway. */
    const val FLUSH_TIMEOUT_MS = 5_000L
  }
}
