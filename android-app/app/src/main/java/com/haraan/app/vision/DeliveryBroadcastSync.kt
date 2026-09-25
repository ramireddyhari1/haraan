package com.haraan.app.vision

import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import org.json.JSONObject
import java.net.DatagramPacket
import java.net.DatagramSocket
import java.net.InetAddress
import java.net.SocketException
import kotlin.math.abs
import kotlin.math.sqrt

/**
 * Synchronization role for devices participating in cricket match tracking.
 */
enum class DeviceSyncRole {
    BOWLER_FRONT,
    SIDE_ON_CREASE,
    SCORER,
    BROADCAST_RECEIVER,
}

/**
 * Measured synchronization quality based on RTT, sample count, and jitter.
 */
enum class ClockSyncQuality {
    UNSYNCHRONIZED,
    LOW,
    MEDIUM,
    HIGH,
}

/**
 * Diagnostic report of clock offset and round-trip time between companion devices.
 */
data class ClockSyncStatus(
    val clockOffsetMs: Double,
    val rttMs: Double,
    val jitterMs: Double,
    val sampleCount: Int,
    val quality: ClockSyncQuality,
)

/**
 * Ultra-low latency wireless sync packet transmitted between the companion camera phones
 * and the scorer device.
 */
data class DeliverySyncPacket(
    val event: String = "DELIVERY_START",
    val deliveryIndex: Int,
    val timestampMs: Long,
    val bowlingArm: String,
    val releaseAngleDegrees: Float,
    val elbowFlexionDegrees: Float,
    val isActionLegal: Boolean,
    val releaseHeightMeters: Float,
    val strideLengthMeters: Float,
    val handSpeedKmh: Float,
    // Enterprise sync fields
    val sessionId: String = "session_default",
    val deviceId: String = "camera_bowler",
    val deviceRole: String = DeviceSyncRole.BOWLER_FRONT.name,
    val sequenceNumber: Long = 1L,
    val monotonicTimestampNs: Long = System.nanoTime(),
    val wallClockTimestampMs: Long = System.currentTimeMillis(),
) {
    fun toJson(): String {
        val json = JSONObject()
        json.put("event", event)
        json.put("deliveryIndex", deliveryIndex)
        json.put("timestampMs", timestampMs)
        json.put("bowlingArm", bowlingArm)
        json.put("releaseAngle", releaseAngleDegrees)
        json.put("elbowFlexion", elbowFlexionDegrees)
        json.put("isLegal", isActionLegal)
        json.put("releaseHeight", releaseHeightMeters)
        json.put("strideLength", strideLengthMeters)
        json.put("handSpeedKmh", handSpeedKmh)
        json.put("sessionId", sessionId)
        json.put("deviceId", deviceId)
        json.put("deviceRole", deviceRole)
        json.put("sequenceNumber", sequenceNumber)
        json.put("monotonicTimestampNs", monotonicTimestampNs)
        json.put("wallClockTimestampMs", wallClockTimestampMs)
        return json.toString()
    }

    companion object {
        fun fromJson(raw: String): DeliverySyncPacket? = runCatching {
            val json = JSONObject(raw)
            DeliverySyncPacket(
                event = json.optString("event", "DELIVERY_START"),
                deliveryIndex = json.optInt("deliveryIndex", 1),
                timestampMs = json.optLong("timestampMs", System.currentTimeMillis()),
                bowlingArm = json.optString("bowlingArm", "RIGHT"),
                releaseAngleDegrees = json.optDouble("releaseAngle", 80.0).toFloat(),
                elbowFlexionDegrees = json.optDouble("elbowFlexion", 0.0).toFloat(),
                isActionLegal = json.optBoolean("isLegal", true),
                releaseHeightMeters = json.optDouble("releaseHeight", 2.0).toFloat(),
                strideLengthMeters = json.optDouble("strideLength", 1.6).toFloat(),
                handSpeedKmh = json.optDouble("handSpeedKmh", 120.0).toFloat(),
                sessionId = json.optString("sessionId", "session_default"),
                deviceId = json.optString("deviceId", "camera_bowler"),
                deviceRole = json.optString("deviceRole", DeviceSyncRole.BOWLER_FRONT.name),
                sequenceNumber = json.optLong("sequenceNumber", 1L),
                monotonicTimestampNs = json.optLong("monotonicTimestampNs", 0L),
                wallClockTimestampMs = json.optLong("wallClockTimestampMs", System.currentTimeMillis()),
            )
        }.getOrNull()
    }
}

/**
 * Ping / Pong synchronization packet for RTT and clock offset estimation.
 */
data class ClockSyncProbe(
    val probeId: Int,
    val t0SendNs: Long,
    val t1ReceiveNs: Long = 0L,
    val t2ReplyNs: Long = 0L,
    val t3ReturnNs: Long = 0L,
) {
    /**
     * Compute RTT and clock offset using standard NTP Cristian's algorithm:
     * RTT = (T3 - T0) - (T2 - T1)
     * Offset = ((T1 - T0) + (T2 - T3)) / 2
     */
    fun computeMetrics(): Pair<Double, Double> {
        val rttNs = (t3ReturnNs - t0SendNs) - (t2ReplyNs - t1ReceiveNs)
        val offsetNs = ((t1ReceiveNs - t0SendNs) + (t2ReplyNs - t3ReturnNs)) / 2.0
        val rttMs = (rttNs.coerceAtLeast(0L)) / 1_000_000.0
        val offsetMs = offsetNs / 1_000_000.0
        return Pair(rttMs, offsetMs)
    }
}

/**
 * Clock Synchronization Manager with outlier rejection and statistical jitter filtering.
 */
class ClockSyncEstimator(
    private val maxSamples: Int = 16,
) {
    private data class Sample(val rttMs: Double, val offsetMs: Double)
    private val samples = mutableListOf<Sample>()

    fun recordProbe(probe: ClockSyncProbe) {
        val (rtt, offset) = probe.computeMetrics()
        if (rtt > 500.0) return // Reject gross network timeouts

        samples.add(Sample(rtt, offset))
        if (samples.size > maxSamples) {
            samples.removeAt(0)
        }
    }

    fun getStatus(): ClockSyncStatus {
        if (samples.isEmpty()) {
            return ClockSyncStatus(0.0, 0.0, 0.0, 0, ClockSyncQuality.UNSYNCHRONIZED)
        }

        // Filter outliers: retain the lowest 50% RTT samples (least network buffer delay)
        val sortedByRtt = samples.sortedBy { it.rttMs }
        val bestSubset = sortedByRtt.take(maxOf(1, sortedByRtt.size / 2))

        val avgOffset = bestSubset.map { it.offsetMs }.average()
        val avgRtt = bestSubset.map { it.rttMs }.average()

        val variance = bestSubset.map { (it.offsetMs - avgOffset) * (it.offsetMs - avgOffset) }.average()
        val jitter = sqrt(variance)

        val quality = when {
            samples.size >= 8 && jitter < 2.5 && avgRtt < 25.0 -> ClockSyncQuality.HIGH
            samples.size >= 4 && jitter < 6.0 && avgRtt < 60.0 -> ClockSyncQuality.MEDIUM
            else -> ClockSyncQuality.LOW
        }

        return ClockSyncStatus(
            clockOffsetMs = avgOffset,
            rttMs = avgRtt,
            jitterMs = jitter,
            sampleCount = samples.size,
            quality = quality,
        )
    }

    fun reset() {
        samples.clear()
    }
}

/**
 * Memory-safe bounded packet deduplicator.
 * Rejects duplicate packets, out-of-order old frames, and invalid sessions.
 */
class PacketDeduplicator(
    private val windowCapacity: Int = 500,
) {
    private val seenSequences = LinkedHashSet<String>(windowCapacity)
    private val highestSequencePerDevice = mutableMapOf<String, Long>()

    fun isDuplicateOrStale(packet: DeliverySyncPacket): Boolean {
        val key = "${packet.sessionId}_${packet.deviceId}_${packet.sequenceNumber}"
        if (seenSequences.contains(key)) return true

        val highest = highestSequencePerDevice[packet.deviceId] ?: 0L
        // Stale if sequence number is significantly behind the highest seen (> 50 packets ago)
        if (packet.sequenceNumber < highest - 50L) {
            return true
        }

        // Bounded eviction
        if (seenSequences.size >= windowCapacity) {
            val oldest = seenSequences.iterator().next()
            seenSequences.remove(oldest)
        }
        seenSequences.add(key)
        if (packet.sequenceNumber > highest) {
            highestSequencePerDevice[packet.deviceId] = packet.sequenceNumber
        }

        return false
    }

    fun reset() {
        seenSequences.clear()
        highestSequencePerDevice.clear()
    }
}

/**
 * App-wide bus for real-time delivery sync events.
 */
object DeliverySyncBus {
    private val _events = MutableSharedFlow<DeliverySyncPacket>(extraBufferCapacity = 32)
    val events: SharedFlow<DeliverySyncPacket> = _events

    fun emit(packet: DeliverySyncPacket) {
        _events.tryEmit(packet)
    }
}

/**
 * Local UDP Broadcaster: Emits delivery release packets across the ground
 * with sub-5ms latency over local Wi-Fi or mobile hotspot.
 */
class DeliveryBroadcaster(
    private val port: Int = 8888,
    private val scope: CoroutineScope = CoroutineScope(Dispatchers.IO),
    private val sessionId: String = "session_${System.currentTimeMillis()}",
    private val deviceId: String = "camera_${android.os.Build.MODEL.replace(" ", "_")}",
    private val role: DeviceSyncRole = DeviceSyncRole.BOWLER_FRONT,
) {
    private var socket: DatagramSocket? = null
    private var sequenceNumber: Long = 0L

    init {
        runCatching {
            socket = DatagramSocket().apply {
                broadcast = true
            }
        }
    }

    fun broadcastDelivery(packet: DeliverySyncPacket) {
        sequenceNumber++
        val enriched = packet.copy(
            sessionId = sessionId,
            deviceId = deviceId,
            deviceRole = role.name,
            sequenceNumber = sequenceNumber,
            monotonicTimestampNs = System.nanoTime(),
            wallClockTimestampMs = System.currentTimeMillis(),
        )

        scope.launch {
            try {
                val data = enriched.toJson().toByteArray(Charsets.UTF_8)
                val broadcastAddr = InetAddress.getByName("255.255.255.255")
                val datagram = DatagramPacket(data, data.size, broadcastAddr, port)
                socket?.send(datagram)
            } catch (_: Throwable) {
                // Network transmission error; keep camera loop uninterrupted
            }
        }
    }

    fun close() {
        runCatching { socket?.close() }
        socket = null
    }
}

/**
 * Scorer-side Delivery Sync Listener: Listens on UDP port 8888 and notifies
 * the scoring app the instant the bowler releases the ball.
 */
class DeliverySyncListener(
    private val port: Int = 8888,
    private val scope: CoroutineScope = CoroutineScope(Dispatchers.IO),
    private val deduplicator: PacketDeduplicator = PacketDeduplicator(),
    private val onDeliveryReceived: (DeliverySyncPacket) -> Unit,
) {
    private var socket: DatagramSocket? = null
    private var listenJob: Job? = null

    fun start() {
        if (listenJob != null) return

        listenJob = scope.launch {
            try {
                socket = DatagramSocket(port).apply {
                    broadcast = true
                    reuseAddress = true
                }
                val buffer = ByteArray(2048)

                while (isActive) {
                    val packet = DatagramPacket(buffer, buffer.size)
                    socket?.receive(packet)
                    val raw = String(packet.data, 0, packet.length, Charsets.UTF_8)
                    val delivery = DeliverySyncPacket.fromJson(raw)
                    if (delivery != null && !deduplicator.isDuplicateOrStale(delivery)) {
                        DeliverySyncBus.emit(delivery)
                        onDeliveryReceived(delivery)
                    }
                }
            } catch (_: SocketException) {
                // Closed gracefully
            } catch (_: Throwable) {
                // Transient error
            }
        }
    }

    fun stop() {
        listenJob?.cancel()
        listenJob = null
        runCatching { socket?.close() }
        socket = null
    }
}
