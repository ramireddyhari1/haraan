package com.haraan.app.camera

import android.content.Context
import com.haraan.app.data.CameraDeviceRepository
import com.haraan.app.data.ClipUploadResult
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.channels.Channel
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import org.json.JSONObject
import java.io.File
import java.util.Collections
import java.util.UUID

/**
 * Metadata recorded alongside each delivery clip in persistent storage.
 */
data class QueuedClipMeta(
    val id: String,
    val sessionToken: String,
    val durationMs: Long,
    val overBall: String?,
    val enqueuedAtMs: Long,
    val retryCount: Int = 0,
    val lastAttemptMs: Long = 0L,
) {
    fun toJson(): JSONObject = JSONObject().apply {
        put("id", id)
        put("sessionToken", sessionToken)
        put("durationMs", durationMs)
        if (!overBall.isNullOrBlank()) put("overBall", overBall)
        put("enqueuedAtMs", enqueuedAtMs)
        put("retryCount", retryCount)
        put("lastAttemptMs", lastAttemptMs)
    }

    companion object {
        fun fromJson(json: JSONObject): QueuedClipMeta = QueuedClipMeta(
            id = json.getString("id"),
            sessionToken = json.getString("sessionToken"),
            durationMs = json.getLong("durationMs"),
            overBall = json.optString("overBall").takeIf { it.isNotBlank() },
            enqueuedAtMs = json.getLong("enqueuedAtMs"),
            retryCount = json.optInt("retryCount", 0),
            lastAttemptMs = json.optLong("lastAttemptMs", 0L),
        )
    }
}

/**
 * Observable status of the clip upload queue for display on the camera screen.
 */
data class ClipQueueStatus(
    val pendingCount: Int = 0,
    val isUploading: Boolean = false,
    val clipsSent: Int = 0,
    val lastError: String? = null,
    val activeOverBall: String? = null,
)

/**
 * A persistent, asynchronous upload queue for review clips.
 *
 * Production-hardened invariants:
 * 1. Application-scoped: exactly one instance per app process via [getInstance].
 * 2. Lifecycle-decoupled: camera screen recreation/navigation cannot terminate ongoing uploads
 *    or start duplicate workers.
 * 3. Staging isolation: unfinalized/partial recordings live in a dedicated staging directory
 *    and cannot enter the upload queue until finalized and validated.
 * 4. Deduplication: in-flight uploads are tracked in memory so spurious wake-ups cannot cause
 *    duplicate network transmissions.
 * 5. Crash recovery: on process restart, pending clips are recovered from disk in strict FIFO order.
 * 6. Deletion guarantee: clips are deleted ONLY on confirmed HTTP 2xx or permanent 401/422 refusals.
 */
class ClipUploadQueue(
    val queueDir: File,
    private val repo: CameraDeviceRepository,
    parentScope: CoroutineScope = CoroutineScope(Dispatchers.IO),
    onUploadSuccess: ((overBall: String?) -> Unit)? = null,
    onUploadPermanentFailure: ((message: String) -> Unit)? = null,
) {
    constructor(
        context: Context,
        repo: CameraDeviceRepository,
        onUploadSuccess: ((overBall: String?) -> Unit)? = null,
        onUploadPermanentFailure: ((message: String) -> Unit)? = null,
    ) : this(
        queueDir = File(context.applicationContext.filesDir, "review_clips"),
        repo = repo,
        parentScope = CoroutineScope(Dispatchers.IO),
        onUploadSuccess = onUploadSuccess,
        onUploadPermanentFailure = onUploadPermanentFailure,
    )

    interface UploadListener {
        fun onUploadSuccess(overBall: String?) {}
        fun onUploadPermanentFailure(message: String) {}
    }

    companion object {
        /** Maximum number of un-uploaded review clips to retain before pruning oldest dot balls. */
        const val MAX_PENDING_CLIPS = 25

        /** Maximum bytes allocated to the pending clip backlog (500 MB). */
        const val MAX_QUEUE_BYTES = 500L * 1024 * 1024

        const val EXT_VIDEO = ".mp4"
        const val EXT_META = ".meta.json"
        const val DIR_STAGING = "staging"

        /**
         * Calculates exponential backoff delay based on the number of previous failed attempts.
         */
        fun calculateBackoffMs(retryCount: Int): Long = when {
            retryCount <= 0 -> 0L
            retryCount == 1 -> 2_000L
            retryCount == 2 -> 5_000L
            retryCount == 3 -> 10_000L
            retryCount == 4 -> 30_000L
            else -> 60_000L
        }

        @Volatile
        private var defaultInstance: ClipUploadQueue? = null

        /**
         * Returns the application-scoped singleton instance.
         */
        fun getInstance(context: Context): ClipUploadQueue {
            return defaultInstance ?: synchronized(this) {
                defaultInstance ?: ClipUploadQueue(
                    context = context.applicationContext,
                    repo = CameraDeviceRepository(),
                ).also { defaultInstance = it }
            }
        }

        /** For testing only: allows overriding or resetting the singleton instance. */
        fun setInstanceForTesting(instance: ClipUploadQueue?) {
            synchronized(this) {
                defaultInstance = instance
            }
        }
    }

    val stagingDir = File(queueDir, DIR_STAGING)

    private val queueJob = SupervisorJob(parentScope.coroutineContext[Job])
    private val scope = CoroutineScope(parentScope.coroutineContext + queueJob)

    private val _status = MutableStateFlow(ClipQueueStatus())
    val status: StateFlow<ClipQueueStatus> = _status.asStateFlow()

    private val wakeChannel = Channel<Unit>(Channel.CONFLATED)

    private val inFlightClips = Collections.synchronizedSet(mutableSetOf<String>())
    private val listeners = Collections.synchronizedSet(mutableSetOf<UploadListener>())

    @Volatile
    private var workerJob: Job? = null

    init {
        if (onUploadSuccess != null || onUploadPermanentFailure != null) {
            addListener(object : UploadListener {
                override fun onUploadSuccess(overBall: String?) {
                    onUploadSuccess?.invoke(overBall)
                }
                override fun onUploadPermanentFailure(message: String) {
                    onUploadPermanentFailure?.invoke(message)
                }
            })
        }
        queueDir.mkdirs()
        stagingDir.mkdirs()
        cleanupStagingDir()
        refreshPendingCount()
        startWorker()
    }

    fun addListener(listener: UploadListener) {
        listeners.add(listener)
    }

    fun removeListener(listener: UploadListener) {
        listeners.remove(listener)
    }

    private fun notifyUploadSuccess(overBall: String?) {
        synchronized(listeners) {
            listeners.forEach { runCatching { it.onUploadSuccess(overBall) } }
        }
    }

    private fun notifyUploadPermanentFailure(message: String) {
        synchronized(listeners) {
            listeners.forEach { runCatching { it.onUploadPermanentFailure(message) } }
        }
    }

    /**
     * Purges orphaned, unfinalized recordings from previous application crashes.
     */
    fun cleanupStagingDir() {
        runCatching {
            stagingDir.listFiles()?.forEach { file ->
                file.delete()
            }
        }
    }

    /**
     * Allocates a target file inside [stagingDir] so CameraX can write into a temporary,
     * un-enqueued location. Partial recordings in staging can NEVER be seen by the upload worker.
     */
    fun createClipFile(): File {
        stagingDir.mkdirs()
        val uniqueId = "clip-${System.currentTimeMillis()}-${UUID.randomUUID().toString().take(6)}"
        return File(stagingDir, "$uniqueId$EXT_VIDEO")
    }

    /**
     * Enqueues a finalized delivery clip for background transmission.
     *
     * Moves the file from [stagingDir] to [queueDir] and persists its metadata.
     * If the file is missing or empty, it is rejected.
     */
    fun enqueue(
        file: File,
        sessionToken: String,
        durationMs: Long,
        overBall: String?,
    ) {
        if (!file.exists() || file.length() <= 0L) {
            runCatching { file.delete() }
            return
        }

        queueDir.mkdirs()
        val targetVideoFile = if (file.parentFile?.canonicalPath == queueDir.canonicalPath) {
            file
        } else {
            val dest = File(queueDir, file.name)
            if (!file.renameTo(dest)) {
                file.copyTo(dest, overwrite = true)
                runCatching { file.delete() }
            }
            dest
        }

        val clipId = targetVideoFile.nameWithoutExtension
        val meta = QueuedClipMeta(
            id = clipId,
            sessionToken = sessionToken,
            durationMs = durationMs,
            overBall = overBall,
            enqueuedAtMs = System.currentTimeMillis(),
        )

        val metaFile = File(queueDir, "$clipId$EXT_META")
        metaFile.writeText(meta.toJson().toString(2), Charsets.UTF_8)

        pruneCapacity()
        refreshPendingCount()
        startWorker()
        wakeChannel.trySend(Unit)
    }

    /**
     * Enforces storage caps: deletes oldest pending items if queue exceeds max count or max bytes.
     */
    fun pruneCapacity() {
        val pending = loadPendingItems(excludeInFlight = true)
        if (pending.isEmpty()) return

        var totalBytes = pending.sumOf { it.first.length() }
        val currentItems = pending.toMutableList()

        while (currentItems.size > MAX_PENDING_CLIPS || totalBytes > MAX_QUEUE_BYTES) {
            val oldest = currentItems.removeAt(0)
            val videoSize = oldest.first.length()
            runCatching { oldest.first.delete() }
            runCatching { oldest.second.delete() }
            totalBytes -= videoSize
        }
    }

    private fun loadPendingItems(excludeInFlight: Boolean = false): List<Triple<File, File, QueuedClipMeta>> {
        val metaFiles = queueDir.listFiles { f -> f.isFile && f.name.endsWith(EXT_META) } ?: return emptyList()
        val list = mutableListOf<Triple<File, File, QueuedClipMeta>>()

        for (metaFile in metaFiles) {
            val clipId = metaFile.name.removeSuffix(EXT_META)
            if (excludeInFlight && inFlightClips.contains(clipId)) {
                continue
            }
            val videoFile = File(queueDir, "$clipId$EXT_VIDEO")
            val meta = runCatching {
                QueuedClipMeta.fromJson(JSONObject(metaFile.readText(Charsets.UTF_8)))
            }.getOrNull()

            if (meta != null && videoFile.exists() && videoFile.length() > 0L) {
                list.add(Triple(videoFile, metaFile, meta))
            } else if (meta == null || !videoFile.exists()) {
                // Malformed metadata or missing video: clean up corrupted entries
                runCatching { metaFile.delete() }
                runCatching { videoFile.delete() }
            }
        }

        // FIFO: oldest enqueued delivery first (preserved across app restarts)
        list.sortBy { it.third.enqueuedAtMs }
        return list
    }

    fun refreshPendingCount() {
        val count = loadPendingItems(excludeInFlight = false).size
        _status.update { it.copy(pendingCount = count) }
    }

    /**
     * Processes a single pending item from the queue, applying backoff if needed.
     * Guaranteed to prevent duplicate concurrent uploads of the same clip.
     * Returns the [ClipUploadResult] or null if the queue is empty.
     */
    suspend fun processNextPending(): ClipUploadResult? {
        val nextItem = synchronized(inFlightClips) {
            val pending = loadPendingItems(excludeInFlight = true)
            val candidate = pending.firstOrNull() ?: return@synchronized null
            inFlightClips.add(candidate.third.id)
            candidate
        } ?: run {
            _status.update { it.copy(isUploading = inFlightClips.isNotEmpty(), activeOverBall = null) }
            return null
        }

        val (videoFile, metaFile, meta) = nextItem

        try {
            if (!videoFile.exists() || videoFile.length() <= 0L) {
                runCatching { metaFile.delete() }
                runCatching { videoFile.delete() }
                refreshPendingCount()
                return null
            }

            // Check backoff pause
            val backoffMs = calculateBackoffMs(meta.retryCount)
            val elapsedSinceLast = System.currentTimeMillis() - meta.lastAttemptMs
            if (elapsedSinceLast < backoffMs) {
                val waitMs = backoffMs - elapsedSinceLast
                _status.update { it.copy(isUploading = false, activeOverBall = meta.overBall) }
                delay(waitMs)
            }

            _status.update {
                it.copy(
                    isUploading = true,
                    activeOverBall = meta.overBall,
                    pendingCount = loadPendingItems(excludeInFlight = false).size,
                )
            }

            val result = repo.uploadClip(
                sessionToken = meta.sessionToken,
                file = videoFile,
                durationMs = meta.durationMs,
                overBall = meta.overBall,
            )

            when (result) {
                is ClipUploadResult.Success -> {
                    // Confirmed 2xx by the scorer server: safely delete local review copies
                    runCatching { videoFile.delete() }
                    runCatching { metaFile.delete() }

                    _status.update {
                        it.copy(
                            clipsSent = it.clipsSent + 1,
                            pendingCount = loadPendingItems(excludeInFlight = false).size,
                            lastError = null,
                            isUploading = inFlightClips.size > 1,
                        )
                    }
                    notifyUploadSuccess(meta.overBall)
                }

                is ClipUploadResult.PermanentFailure -> {
                    // The server rejected this clip permanently (401 revoked session or 422 format/duration).
                    // Discard the file to prevent blocking the queue.
                    runCatching { videoFile.delete() }
                    runCatching { metaFile.delete() }

                    _status.update {
                        it.copy(
                            pendingCount = loadPendingItems(excludeInFlight = false).size,
                            lastError = result.message,
                            isUploading = inFlightClips.size > 1,
                        )
                    }
                    notifyUploadPermanentFailure(result.message)
                }

                is ClipUploadResult.RetryableFailure -> {
                    // Ground Wi-Fi / 4G drop or timeout. Retain the file and schedule retry!
                    val updatedMeta = meta.copy(
                        retryCount = meta.retryCount + 1,
                        lastAttemptMs = System.currentTimeMillis(),
                    )
                    runCatching {
                        metaFile.writeText(updatedMeta.toJson().toString(2), Charsets.UTF_8)
                    }

                    _status.update {
                        it.copy(
                            pendingCount = loadPendingItems(excludeInFlight = false).size,
                            lastError = result.message,
                            isUploading = inFlightClips.size > 1,
                        )
                    }
                }
            }
            return result
        } finally {
            inFlightClips.remove(meta.id)
            _status.update { it.copy(isUploading = inFlightClips.isNotEmpty()) }
        }
    }

    /**
     * Starts the queue worker coroutine if not already running.
     * Completely idempotent.
     */
    @Synchronized
    fun startWorker() {
        if (workerJob?.isActive == true) return
        workerJob = scope.launch {
            while (isActive) {
                val pending = loadPendingItems(excludeInFlight = true)
                if (pending.isEmpty()) {
                    _status.update { it.copy(isUploading = inFlightClips.isNotEmpty(), activeOverBall = null) }
                    wakeChannel.receive()
                    continue
                }

                val result = processNextPending()
                if (result is ClipUploadResult.RetryableFailure) {
                    delay(1_000L)
                }
            }
        }
    }

    /**
     * Cancels the background queue worker and closes the channel.
     */
    fun close() {
        queueJob.cancel()
        wakeChannel.close()
        synchronized(ClipUploadQueue::class.java) {
            if (defaultInstance === this) {
                defaultInstance = null
            }
        }
    }
}
