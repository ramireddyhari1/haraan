package com.haraan.app.vision

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test

class DeliveryBroadcastSyncTest {

    @Test
    fun `packet json serialization and deserialization roundtrip preserves all telemetry`() {
        val packet = DeliverySyncPacket(
            event = "DELIVERY_START",
            deliveryIndex = 42,
            timestampMs = 1727084530000L,
            bowlingArm = "RIGHT",
            releaseAngleDegrees = 82.5f,
            elbowFlexionDegrees = 6.2f,
            isActionLegal = true,
            releaseHeightMeters = 2.08f,
            strideLengthMeters = 1.65f,
            handSpeedKmh = 138.5f,
            sessionId = "match_blr_vs_csk",
            deviceId = "cam_bowler_1",
            deviceRole = DeviceSyncRole.BOWLER_FRONT.name,
            sequenceNumber = 104L,
            monotonicTimestampNs = 884719230000L,
            wallClockTimestampMs = 1727084530000L,
        )

        val json = packet.toJson()
        val parsed = DeliverySyncPacket.fromJson(json)

        assertNotNull(parsed)
        assertEquals(packet.event, parsed!!.event)
        assertEquals(packet.deliveryIndex, parsed.deliveryIndex)
        assertEquals(packet.sessionId, parsed.sessionId)
        assertEquals(packet.deviceId, parsed.deviceId)
        assertEquals(packet.deviceRole, parsed.deviceRole)
        assertEquals(packet.sequenceNumber, parsed.sequenceNumber)
        assertEquals(packet.bowlingArm, parsed.bowlingArm)
        assertEquals(packet.handSpeedKmh, parsed.handSpeedKmh, 0.01f)
        assertEquals(packet.releaseAngleDegrees, parsed.releaseAngleDegrees, 0.01f)
        assertEquals(packet.isActionLegal, parsed.isActionLegal)
    }

    @Test
    fun `clock sync estimator accurately filters jitter and computes offset`() {
        val estimator = ClockSyncEstimator()

        // 10 probes with 20ms RTT and +5ms clock offset
        repeat(10) { i ->
            val probe = ClockSyncProbe(
                probeId = i,
                t0SendNs = 10_000_000L,
                t1ReceiveNs = 25_000_000L, // clock +5ms ahead (true 20ms -> 25ms)
                t2ReplyNs = 26_000_000L,   // held 1ms on companion device
                t3ReturnNs = 31_000_000L,  // returned at 31ms on sender
            )
            estimator.recordProbe(probe)
        }

        val status = estimator.getStatus()
        assertEquals(10, status.sampleCount)
        assertTrue("Estimated RTT must be around 20ms", status.rttMs in 18.0..22.0)
        assertTrue("Estimated offset must be around +4ms to +6ms", status.clockOffsetMs in 4.5..5.5)
        assertEquals(ClockSyncQuality.HIGH, status.quality)
    }

    @Test
    fun `packet deduplicator rejects duplicates and very stale out-of-order packets`() {
        val deduplicator = PacketDeduplicator(windowCapacity = 50)

        fun createPacket(seq: Long) = DeliverySyncPacket(
            deliveryIndex = 1,
            timestampMs = 1000L,
            bowlingArm = "RIGHT",
            releaseAngleDegrees = 80f,
            elbowFlexionDegrees = 5f,
            isActionLegal = true,
            releaseHeightMeters = 2f,
            strideLengthMeters = 1.6f,
            handSpeedKmh = 120f,
            sequenceNumber = seq,
        )

        // 1. First packet accepted
        assertFalse(deduplicator.isDuplicateOrStale(createPacket(10L)))

        // 2. Duplicate packet rejected
        assertTrue(deduplicator.isDuplicateOrStale(createPacket(10L)))

        // 3. Sequential packets accepted
        assertFalse(deduplicator.isDuplicateOrStale(createPacket(11L)))
        assertFalse(deduplicator.isDuplicateOrStale(createPacket(12L)))

        // 4. Stale packet far behind (> 50 sequence numbers ago) rejected
        assertTrue(deduplicator.isDuplicateOrStale(createPacket(100L)).not())
        // Now highest is 100. Packet with sequence 20 is > 50 behind:
        assertTrue(deduplicator.isDuplicateOrStale(createPacket(20L)))
    }
}
