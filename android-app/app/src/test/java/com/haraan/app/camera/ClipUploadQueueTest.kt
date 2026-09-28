package com.haraan.app.camera

import com.haraan.app.data.CameraDeviceRepository
import com.haraan.app.data.ClipUploadResult
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.TestScope
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.rules.TemporaryFolder
import java.io.File

@OptIn(ExperimentalCoroutinesApi::class)
class ClipUploadQueueTest {

    @get:Rule
    val tempFolder = TemporaryFolder()

    private open class FakeCameraDeviceRepository(
        var result: ClipUploadResult = ClipUploadResult.Success,
    ) : CameraDeviceRepository() {
        var uploadCalls = 0
        var lastSessionToken: String? = null
        var lastFile: File? = null
        var lastDurationMs: Long? = null
        var lastOverBall: String? = null
        var lastBallSeq: Int? = null
        var lastTrack: String? = null

        override suspend fun uploadClip(
            sessionToken: String,
            file: File,
            durationMs: Long,
            overBall: String?,
            ballSeq: Int?,
            track: String?,
        ): ClipUploadResult {
            uploadCalls++
            lastTrack = track
            lastBallSeq = ballSeq
            lastSessionToken = sessionToken
            lastFile = file
            lastDurationMs = durationMs
            lastOverBall = overBall
            return result
        }
    }

    @Test
    fun `metadata serialization and deserialization preserves all fields`() {
        val meta = QueuedClipMeta(
            id = "clip-1726840000-xyz",
            sessionToken = "TOK_ABC_123",
            durationMs = 9500L,
            overBall = "14.2",
            enqueuedAtMs = 1726840000123L,
            ballSeq = 37,
            retryCount = 2,
            lastAttemptMs = 1726840005000L,
        )

        val json = meta.toJson()
        val parsed = QueuedClipMeta.fromJson(json)

        assertEquals(meta.id, parsed.id)
        assertEquals(meta.sessionToken, parsed.sessionToken)
        assertEquals(meta.durationMs, parsed.durationMs)
        assertEquals(meta.overBall, parsed.overBall)
        assertEquals(meta.enqueuedAtMs, parsed.enqueuedAtMs)
        assertEquals(meta.retryCount, parsed.retryCount)
        assertEquals(meta.lastAttemptMs, parsed.lastAttemptMs)
        // The BALL number survives a process restart, so REVIEW still finds the clip.
        assertEquals(37, parsed.ballSeq)
    }

    @Test
    fun `the camera's ball track survives the queue's disk round trip`() {
        val track = """{"v":1,"aspect":1.7778,"bounce":2,"points":[[0,0.1,0.5,0.8],[33,0.2,0.6,0.7],[66,0.3,0.7,0.9],[99,0.4,0.62,0.9]]}"""
        val meta = QueuedClipMeta(
            id = "clip-2",
            sessionToken = "TOK",
            durationMs = 8000L,
            overBall = "3.4",
            enqueuedAtMs = 1000L,
            trackJson = track,
        )
        val parsed = QueuedClipMeta.fromJson(org.json.JSONObject(meta.toJson().toString(2)))
        assertEquals(track, parsed.trackJson)
    }

    @Test
    fun `a clip with no track stores none`() {
        val meta = QueuedClipMeta(id = "c", sessionToken = "T", durationMs = 1L, overBall = null, enqueuedAtMs = 1L)
        assertFalse(meta.toJson().has("track"))
        assertNull(QueuedClipMeta.fromJson(meta.toJson()).trackJson)
    }

    @Test
    fun `metadata handles null or empty overBall correctly`() {
        val meta = QueuedClipMeta(
            id = "clip-1",
            sessionToken = "TOK",
            durationMs = 8000L,
            overBall = null,
            enqueuedAtMs = 1000L,
        )

        val json = meta.toJson()
        assertFalse(json.has("overBall"))
        val parsed = QueuedClipMeta.fromJson(json)
        assertNull(parsed.overBall)
    }

    @Test
    fun `exponential backoff scales with retry attempts and caps at 60 seconds`() {
        assertEquals(0L, ClipUploadQueue.calculateBackoffMs(0))
        assertEquals(2_000L, ClipUploadQueue.calculateBackoffMs(1))
        assertEquals(5_000L, ClipUploadQueue.calculateBackoffMs(2))
        assertEquals(10_000L, ClipUploadQueue.calculateBackoffMs(3))
        assertEquals(30_000L, ClipUploadQueue.calculateBackoffMs(4))
        assertEquals(60_000L, ClipUploadQueue.calculateBackoffMs(5))
        assertEquals(60_000L, ClipUploadQueue.calculateBackoffMs(10))
    }

    @Test
    fun `staging directory isolates partial files until finalized`() {
        val queueDir = tempFolder.newFolder("queue_staging_test")
        val fakeRepo = FakeCameraDeviceRepository()
        val testDispatcher = StandardTestDispatcher()
        val testScope = TestScope(testDispatcher)

        val queue = ClipUploadQueue(
            queueDir = queueDir,
            repo = fakeRepo,
            parentScope = testScope,
        )

        val stagedFile = queue.createClipFile().apply {
            writeBytes(ByteArray(200))
        }

        assertTrue("Staged file should exist in stagingDir", stagedFile.exists())
        assertEquals(queue.stagingDir.canonicalPath, stagedFile.parentFile?.canonicalPath)

        // It should NOT be returned by loadPendingItems or counted in status
        assertEquals(0, queue.status.value.pendingCount)

        // Once enqueued, it moves to queueDir
        queue.enqueue(
            file = stagedFile,
            sessionToken = "TOK",
            durationMs = 5000L,
            overBall = "1.1",
        )

        assertEquals(1, queue.status.value.pendingCount)
        assertFalse("Staged file should have moved out of staging", stagedFile.exists())
        assertTrue(File(queueDir, stagedFile.name).exists())
        assertTrue(File(queueDir, "${stagedFile.nameWithoutExtension}.meta.json").exists())

        queue.close()
    }

    @Test
    fun `enqueue stores file and metadata in queue directory`() {
        val queueDir = tempFolder.newFolder("queue_test")
        val fakeRepo = FakeCameraDeviceRepository(ClipUploadResult.RetryableFailure(503, "Offline"))
        val testDispatcher = StandardTestDispatcher()
        val testScope = TestScope(testDispatcher)

        val queue = ClipUploadQueue(
            queueDir = queueDir,
            repo = fakeRepo,
            parentScope = testScope,
        )

        val sourceFile = tempFolder.newFile("test_clip.mp4").apply {
            writeBytes(ByteArray(1024))
        }

        queue.enqueue(
            file = sourceFile,
            sessionToken = "TEST_TOKEN",
            durationMs = 9000L,
            overBall = "3.4",
        )

        val queuedVideo = File(queueDir, "test_clip.mp4")
        val queuedMeta = File(queueDir, "test_clip.meta.json")

        assertTrue("Video file should exist in queue dir", queuedVideo.exists())
        assertTrue("Meta file should exist in queue dir", queuedMeta.exists())
        assertEquals(1, queue.status.value.pendingCount)

        queue.close()
    }

    @Test
    fun `prune capacity drops oldest items when MAX_PENDING_CLIPS is exceeded`() {
        val queueDir = tempFolder.newFolder("queue_prune_test")
        val fakeRepo = FakeCameraDeviceRepository()
        val testDispatcher = StandardTestDispatcher()
        val testScope = TestScope(testDispatcher)

        val queue = ClipUploadQueue(
            queueDir = queueDir,
            repo = fakeRepo,
            parentScope = testScope,
        )

        // Create 28 items with distinct timestamps
        for (i in 1..28) {
            val video = File(queueDir, "clip-$i.mp4").apply { writeBytes(ByteArray(100)) }
            val meta = QueuedClipMeta(
                id = "clip-$i",
                sessionToken = "TOK",
                durationMs = 8000L,
                overBall = "1.$i",
                enqueuedAtMs = 1000L + i,
            )
            File(queueDir, "clip-$i.meta.json").writeText(meta.toJson().toString())
        }

        queue.pruneCapacity()

        val remainingMeta = queueDir.listFiles { f -> f.name.endsWith(".meta.json") } ?: emptyArray()
        assertEquals(ClipUploadQueue.MAX_PENDING_CLIPS, remainingMeta.size)

        // Oldest items (1, 2, 3) must have been deleted
        assertFalse(File(queueDir, "clip-1.mp4").exists())
        assertFalse(File(queueDir, "clip-1.meta.json").exists())
        assertFalse(File(queueDir, "clip-2.mp4").exists())
        assertFalse(File(queueDir, "clip-3.mp4").exists())

        // Newer items (e.g. clip-28) must still exist
        assertTrue(File(queueDir, "clip-28.mp4").exists())
        assertTrue(File(queueDir, "clip-28.meta.json").exists())

        queue.close()
    }

    @Test
    fun `process restart recovers pending clips in strict FIFO order`() = runTest {
        val queueDir = tempFolder.newFolder("queue_recovery_test")
        val uploadedOrder = mutableListOf<String>()
        val fakeRepo = object : FakeCameraDeviceRepository(ClipUploadResult.Success) {
            override suspend fun uploadClip(
                sessionToken: String,
                file: File,
                durationMs: Long,
                overBall: String?,
                ballSeq: Int?,
                track: String?,
            ): ClipUploadResult {
                uploadedOrder.add(overBall ?: "")
                return super.uploadClip(sessionToken, file, durationMs, overBall, ballSeq, track)
            }
        }

        // Pre-populate queueDir with 3 clips created out of chronological order
        val clips = listOf(
            Triple("clip-b", 2000L, "1.2"),
            Triple("clip-a", 1000L, "1.1"),
            Triple("clip-c", 3000L, "1.3"),
        )
        for ((id, enqueuedAt, overBall) in clips) {
            File(queueDir, "$id.mp4").writeBytes(ByteArray(100))
            val meta = QueuedClipMeta(
                id = id,
                sessionToken = "RESTART_TOK",
                durationMs = 5000L,
                overBall = overBall,
                enqueuedAtMs = enqueuedAt,
            )
            File(queueDir, "$id.meta.json").writeText(meta.toJson().toString())
        }

        // Start a fresh ClipUploadQueue instance on the populated queueDir (simulating process restart)
        val restartedQueue = ClipUploadQueue(
            queueDir = queueDir,
            repo = fakeRepo,
            parentScope = this,
        )

        assertEquals(3, restartedQueue.status.value.pendingCount)

        // Drain items one by one
        restartedQueue.processNextPending()
        restartedQueue.processNextPending()
        restartedQueue.processNextPending()

        // Must upload in strict FIFO order based on enqueuedAtMs (clip-a 1000L -> clip-b 2000L -> clip-c 3000L)
        assertEquals(listOf("1.1", "1.2", "1.3"), uploadedOrder)
        assertEquals(0, restartedQueue.status.value.pendingCount)

        restartedQueue.close()
    }

    @Test
    fun `startWorker is idempotent and safe to call repeatedly`() {
        val queueDir = tempFolder.newFolder("queue_worker_test")
        val fakeRepo = FakeCameraDeviceRepository()
        val testDispatcher = StandardTestDispatcher()
        val testScope = TestScope(testDispatcher)

        val queue = ClipUploadQueue(
            queueDir = queueDir,
            repo = fakeRepo,
            parentScope = testScope,
        )

        repeat(5) {
            queue.startWorker()
        }

        queue.close()
    }

    @Test
    fun `listener registration receives success and permanent failure events`() = runTest {
        val queueDir = tempFolder.newFolder("queue_listener_test")
        val fakeRepo = FakeCameraDeviceRepository(ClipUploadResult.Success)

        val queue = ClipUploadQueue(
            queueDir = queueDir,
            repo = fakeRepo,
            parentScope = this,
        )

        var successBall: String? = null
        var failureMsg: String? = null
        val listener = object : ClipUploadQueue.UploadListener {
            override fun onUploadSuccess(overBall: String?) {
                successBall = overBall
            }
            override fun onUploadPermanentFailure(message: String) {
                failureMsg = message
            }
        }
        queue.addListener(listener)

        val clip1 = queue.createClipFile().apply { writeBytes(ByteArray(100)) }
        queue.enqueue(clip1, "TOK", 5000L, "4.1")
        queue.processNextPending()

        assertEquals("4.1", successBall)

        // Remove listener and trigger permanent failure
        queue.removeListener(listener)
        fakeRepo.result = ClipUploadResult.PermanentFailure(401, "Revoked")
        val clip2 = queue.createClipFile().apply { writeBytes(ByteArray(100)) }
        queue.enqueue(clip2, "TOK", 5000L, "4.2")
        queue.processNextPending()

        // Listener was removed, so failureMsg must still be null
        assertNull(failureMsg)

        queue.close()
    }

    @Test
    fun `upload success deletes local files and increments clipsSent`() = runTest {
        val queueDir = tempFolder.newFolder("queue_success_test")
        val fakeRepo = FakeCameraDeviceRepository(ClipUploadResult.Success)

        var successCallbackCalled = false
        val queue = ClipUploadQueue(
            queueDir = queueDir,
            repo = fakeRepo,
            parentScope = this,
            onUploadSuccess = { successCallbackCalled = true },
        )

        val videoFile = queue.createClipFile().apply {
            writeBytes(ByteArray(500))
        }

        queue.enqueue(
            file = videoFile,
            sessionToken = "SESSION_123",
            durationMs = 7500L,
            overBall = "0.1",
        )

        val result = queue.processNextPending()
        assertEquals(ClipUploadResult.Success, result)

        assertEquals(1, fakeRepo.uploadCalls)
        assertEquals("SESSION_123", fakeRepo.lastSessionToken)
        assertEquals("0.1", fakeRepo.lastOverBall)
        assertEquals(1, queue.status.value.clipsSent)
        assertEquals(0, queue.status.value.pendingCount)
        assertTrue(successCallbackCalled)

        // Local video and metadata must be purged upon confirmed 2xx
        val permanentVideoFile = File(queueDir, videoFile.name)
        assertFalse("Video should be deleted after 2xx", permanentVideoFile.exists())
        val metaFile = File(queueDir, "${videoFile.nameWithoutExtension}.meta.json")
        assertFalse("Meta should be deleted after 2xx", metaFile.exists())

        queue.close()
    }

    @Test
    fun `retryable failure retains video file on disk and increments retryCount`() = runTest {
        val queueDir = tempFolder.newFolder("queue_retry_test")
        val fakeRepo = FakeCameraDeviceRepository(ClipUploadResult.RetryableFailure(504, "Gateway Timeout"))

        val queue = ClipUploadQueue(
            queueDir = queueDir,
            repo = fakeRepo,
            parentScope = this,
        )

        val videoFile = queue.createClipFile().apply {
            writeBytes(ByteArray(500))
        }

        queue.enqueue(
            file = videoFile,
            sessionToken = "SESSION_123",
            durationMs = 9000L,
            overBall = "5.2",
        )

        val result = queue.processNextPending()
        assertTrue(result is ClipUploadResult.RetryableFailure)

        assertEquals(1, fakeRepo.uploadCalls)
        assertEquals("Gateway Timeout", queue.status.value.lastError)
        assertEquals(1, queue.status.value.pendingCount)

        // Crucial invariant: Video MUST NOT be deleted on network/5xx failure!
        val permanentVideoFile = File(queueDir, videoFile.name)
        assertTrue("Video must remain on disk after retryable failure", permanentVideoFile.exists())

        val metaFile = File(queueDir, "${videoFile.nameWithoutExtension}.meta.json")
        assertTrue("Meta file must remain on disk", metaFile.exists())

        val updatedMeta = QueuedClipMeta.fromJson(org.json.JSONObject(metaFile.readText()))
        assertEquals(1, updatedMeta.retryCount)
        assertTrue(updatedMeta.lastAttemptMs > 0L)

        queue.close()
    }

    @Test
    fun `permanent failure deletes files and reports error to avoid stuck queue`() = runTest {
        val queueDir = tempFolder.newFolder("queue_fatal_test")
        val fakeRepo = FakeCameraDeviceRepository(ClipUploadResult.PermanentFailure(401, "This device is no longer paired."))

        var failureMessage: String? = null
        val queue = ClipUploadQueue(
            queueDir = queueDir,
            repo = fakeRepo,
            parentScope = this,
            onUploadPermanentFailure = { failureMessage = it },
        )

        val videoFile = queue.createClipFile().apply {
            writeBytes(ByteArray(500))
        }

        queue.enqueue(
            file = videoFile,
            sessionToken = "EXPIRED_TOKEN",
            durationMs = 9000L,
            overBall = "8.1",
        )

        val result = queue.processNextPending()
        assertTrue(result is ClipUploadResult.PermanentFailure)

        assertEquals(1, fakeRepo.uploadCalls)
        assertEquals("This device is no longer paired.", queue.status.value.lastError)
        assertEquals(0, queue.status.value.pendingCount)
        assertEquals("This device is no longer paired.", failureMessage)

        // Permanent failures must not block the queue
        val permanentVideoFile = File(queueDir, videoFile.name)
        assertFalse("Video should be removed on permanent rejection", permanentVideoFile.exists())
        val metaFile = File(queueDir, "${videoFile.nameWithoutExtension}.meta.json")
        assertFalse("Meta should be removed on permanent rejection", metaFile.exists())

        queue.close()
    }

    private suspend fun sentQueue(scope: kotlinx.coroutines.CoroutineScope, name: String): Pair<ClipUploadQueue, File> {
        val queueDir = tempFolder.newFolder(name)
        val queue = ClipUploadQueue(queueDir = queueDir, repo = FakeCameraDeviceRepository(ClipUploadResult.Success), parentScope = scope)
        val video = queue.createClipFile().apply { writeBytes(ByteArray(500)) }
        queue.enqueue(file = video, sessionToken = "S", durationMs = 7000L, overBall = "2.3",
            trackJson = """{"v":1,"aspect":1.7,"points":[[0,0.1,0.5,1],[1,0.2,0.5,1],[2,0.3,0.5,1]],"wickets":{"verdict":"HITTING"}}""")
        return queue to video
    }

    @Test
    fun `a sent clip moves to the gallery instead of being deleted`() = runTest {
        val (queue, video) = sentQueue(this, "kept_move")
        assertEquals(ClipUploadResult.Success, queue.processNextPending())

        assertTrue("kept for the gallery", File(queue.keptDir, video.name).exists())
        val clip = queue.gallery.value.single()
        assertEquals(GalleryClip.State.SENT, clip.state)
        assertEquals("2.3", clip.overBall)
        assertEquals("HITTING", clip.wicketsVerdict)
        queue.close()
    }

    @Test
    fun `keep hours of zero deletes a sent clip at once`() = runTest {
        val (queue, video) = sentQueue(this, "kept_zero")
        queue.setKeepHours(0)
        queue.processNextPending()

        assertFalse(File(queue.keptDir, video.name).exists())
        assertTrue(queue.gallery.value.isEmpty())
        queue.close()
    }

    @Test
    fun `sent clips past their hours are pruned, waiting ones never are`() = runTest {
        val (queue, video) = sentQueue(this, "kept_expiry")
        queue.processNextPending()
        // A second clip that is still waiting to go.
        val waiting = queue.createClipFile().apply { writeBytes(ByteArray(300)) }
        queue.enqueue(file = waiting, sessionToken = "S", durationMs = 5000L, overBall = "2.4")

        queue.pruneKept(nowMs = System.currentTimeMillis() + 25 * 3_600_000L)
        queue.refreshGallery(nowMs = System.currentTimeMillis() + 25 * 3_600_000L)

        assertFalse("a day and an hour later the sent clip is gone", File(queue.keptDir, video.name).exists())
        assertTrue("the waiting clip is untouched", File(queue.queueDir, waiting.name).exists())
        assertEquals(listOf(GalleryClip.State.WAITING), queue.gallery.value.map { it.state })
        queue.close()
    }

    @Test
    fun `only a sent clip can be deleted from the gallery`() = runTest {
        val (queue, video) = sentQueue(this, "kept_delete")
        queue.processNextPending()
        queue.deleteKept(video.nameWithoutExtension)
        assertFalse(File(queue.keptDir, video.name).exists())
        assertTrue(queue.gallery.value.isEmpty())
        queue.close()
    }
}
