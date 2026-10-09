package com.haraan.app.vision

import android.util.Log
import org.opencv.android.OpenCVLoader
import org.opencv.core.Core
import org.opencv.core.CvType
import org.opencv.core.Mat
import org.opencv.core.MatOfPoint
import org.opencv.core.MatOfPoint2f
import org.opencv.core.Point
import org.opencv.core.Rect
import org.opencv.core.Size
import org.opencv.imgproc.Imgproc
import kotlin.math.PI
import kotlin.math.abs
import kotlin.math.max
import kotlin.math.min
import kotlin.math.sqrt

/**
 * Ball tracking by classical computer vision, running while the delivery is filmed.
 *
 * THE PREMISE. Between two frames thirty milliseconds apart, almost nothing on a cricket
 * field changes except the ball. Bowler and batter move slowly and largely; the ball moves
 * fast and small. The tracker isolates motion disturbances in a frame difference, filters
 * out large human body clusters, and requires multi-frame temporal ballistic consistency
 * before confirming and emitting a track.
 *
 * THE PIPELINE:
 *
 *     luma → downscale → blur → frame difference → threshold → morphology
 *          → contours → body cluster exclusion → size filter → shape filter
 *          → multi-candidate hypothesis evaluation → state machine → candidate
 *
 * STATE MACHINE:
 *     LOST → TENTATIVE (accumulating speed & direction) → CONFIRMED (ballistic flight)
 *          → TEMPORARILY_LOST (coasting along extrapolated path) → REACQUIRE / LOST
 *
 * Nothing here interpolates and nothing smooths. Every coordinate returned was measured.
 */
class OpenCvBallTracker(
    /**
     * Analysis width. The camera records 1080p; this does not analyse it.
     *
     * A cricket ball at 480px wide is still several pixels across — enough to find, cheap
     * enough to process inside a frame interval on a mid-range phone. Recording quality is
     * untouched: this is a separate, smaller stream.
     */
    private val analysisWidth: Int = 480,
) : CricketVisionEngine {

    private var previous: Mat? = null

    /*
     * HOW FINELY TO LOOK.
     *
     * At 480 wide (detail 1) a ball near the far stumps is two or three pixels after the
     * blur, and much past 12 m it is gone. 1.5 analyses 720 wide: the same ball is half as
     * big again across and more than twice the pixels, at 2.25 times the work. A phone that
     * can afford it is given it by the camera screen; one that cannot is stepped back.
     */
    @Volatile private var detail = 1.0
    @Volatile private var pendingDetail: Double? = null
    private var areaScale = 1.0
    private var bodyMargin = 4

    /** The analysis resolution as a multiple of the 480-wide baseline. Takes effect next frame. */
    fun setDetail(factor: Double) {
        val f = factor.coerceIn(1.0, MAX_DETAIL)
        if (f != detail) pendingDetail = f
    }

    /** The detail in use now. */
    fun detail(): Double = detail
    private var scratchLuma: Mat? = null
    private var scratchPacked: ByteArray? = null

    /**
     * The rotation the previous frame was turned by.
     *
     * A frame difference is only meaningful between two pictures of the same thing in the
     * same orientation. If the phone is turned mid-session the next frame arrives upright
     * by a different quarter turn, and differencing it against the last one would light up
     * the entire image — which the global-motion guard would then read as a camera shake
     * and blame, rather than the orientation change it is.
     */
    private var previousRotation: Int? = null

    private val sightings = mutableListOf<BallSighting>()
    private var framesSeen = 0
    private var framesWithCandidate = 0
    private var rejectedGlobalMotion = 0

    /*
     * THE EMPTY SCENE, learned.
     *
     * Frame differencing marks two places for every moving ball: where it is now, and where
     * it was a frame ago — the "ghost". Ghosts line up into a perfectly ball-like path one
     * frame behind the real one, and on a real throw (Realme RMX3933, 2026-10-09) the tracker
     * promoted that ghost path the moment the real one missed a frame, and reported where the
     * ball HAD been. A running model of the still background answers the one question the
     * difference cannot: is something actually there NOW? It is learned only from pixels
     * that are not moving, so a passing ball is never absorbed into it.
     */
    private var background: Mat? = null
    private var background8: Mat? = null
    private var backgroundFrames = 0
    private var rejectedGhost = 0
    private var rejectedSize = 0
    private var rejectedShape = 0
    private var rejectedTrajectory = 0
    private var rejectedStationary = 0
    private var rejectedCluster = 0
    private var totalProcessingMs = 0L
    private var maxProcessingMs = 0L
    private var released = false

    // Tracking state model
    private var trackingState: TrackingState = TrackingState.LOST
    private val tentativeTracks = mutableListOf<TrackHypothesis>()
    private var confirmedTrack: TrackHypothesis? = null
    private var missedConfirmedFrames = 0

    val available: Boolean = ensureLoaded()

    override fun onFrame(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        rotationDegrees: Int,
        timestampMs: Long,
    ): BallSighting? {
        if (!available || released || width <= 0 || height <= 0) return null

        val startedAt = System.currentTimeMillis()
        try {
            val turn = ((rotationDegrees % 360) + 360) % 360
            pendingDetail?.let { next ->
                // A new resolution is a new picture: start the difference and the empty
                // scene again. The sightings so far are in frame units and stay valid.
                pendingDetail = null
                detail = next
                previous?.release()
                previous = null
                forgetBackground()
                resetTrackingState()
            }
            areaScale = detail * detail
            bodyMargin = (4 * detail).toInt()
            val gray = toGray(luma, width, height, rowStride, turn) ?: return null
            framesSeen++

            // The phone was turned. Start the difference again from this frame rather than
            // compare two orientations, and drop the track with it: a path that jumps a
            // quarter turn mid-flight is not one ball.
            if (previousRotation != null && previousRotation != turn) {
                previous?.release()
                previous = null
                forgetBackground()
                resetTrackingState()
                sightings.clear()
            }
            previousRotation = turn

            val prev = previous
            if (prev == null) {
                previous = gray
                return null
            }

            val sighting = detect(prev, gray, timestampMs)
            learnBackground(gray)
            prev.release()
            previous = gray

            if (sighting != null) {
                framesWithCandidate++
                if (sightings.isEmpty() || sightings.last().timestampMs != sighting.timestampMs) {
                    sightings.add(sighting)
                }
            }
            return sighting
        } catch (t: Throwable) {
            // A vision failure must never take the recording down with it. The camera is
            // the product; this is an analysis layer bolted to the side of it.
            runCatching { Log.w(TAG, "frame analysis failed", t) }
            return null
        } finally {
            val elapsed = System.currentTimeMillis() - startedAt
            totalProcessingMs += elapsed
            if (elapsed > maxProcessingMs) maxProcessingMs = elapsed
        }
    }

    /**
     * Luma bytes into a downscaled, blurred, UPRIGHT greyscale Mat.
     *
     * Row stride is honoured rather than assumed equal to width: on many devices the
     * camera pads each row, and ignoring that shears the image diagonally — which then
     * looks exactly like fast horizontal motion and produces a beautiful false track.
     *
     * The turn is applied last, on the smallest image, because rotating 480px costs a
     * fraction of rotating a full sensor frame and the result is identical.
     */
    private fun toGray(
        luma: ByteArray,
        width: Int,
        height: Int,
        rowStride: Int,
        turn: Int,
    ): Mat? {
        val packed = if (rowStride == width) {
            luma
        } else {
            // Reused between frames like [scratchLuma] beside it: a delivery is thirty
            // frames and a match is several hundred deliveries, and each of these was a
            // fresh quarter-megabyte array.
            val out = scratchPacked?.takeIf { it.size == width * height }
                ?: ByteArray(width * height).also { scratchPacked = it }
            for (row in 0 until height) {
                val from = row * rowStride
                if (from + width > luma.size) {
                    // Short buffer. Into a fresh array the rest was zeroes; into a reused
                    // one it is the previous frame, and differencing a frame against a
                    // seam of itself is a guaranteed false candidate.
                    return null
                }
                System.arraycopy(luma, from, out, row * width, width)
            }
            out
        }

        val full = scratchLuma ?: Mat(height, width, CvType.CV_8UC1).also { scratchLuma = it }
        if (full.rows() != height || full.cols() != width) {
            full.release()
            scratchLuma = Mat(height, width, CvType.CV_8UC1)
        }
        scratchLuma!!.put(0, 0, packed)

        val scale = analysisWidth * detail / width
        if (scale >= 1.0) {
            // Already small enough; blur in place on a copy.
            val out = Mat()
            Imgproc.GaussianBlur(scratchLuma!!, out, Size(5.0, 5.0), 0.0)
            return upright(out, turn)
        }

        val small = Mat()
        Imgproc.resize(
            scratchLuma!!,
            small,
            Size(width * scale, (height * scale)),
            0.0,
            0.0,
            Imgproc.INTER_AREA,
        )
        val blurred = Mat()
        // Blur before differencing: sensor noise is high-frequency and would otherwise
        // survive the threshold as dozens of one-pixel "candidates".
        Imgproc.GaussianBlur(small, blurred, Size(5.0, 5.0), 0.0)
        small.release()
        return upright(blurred, turn)
    }

    /**
     * The sensor's picture turned the way the viewer holds it. Consumes [source].
     *
     * Same convention as [OpenCvPitchDetector], deliberately: the two engines' outputs are
     * drawn over one another on the camera screen, so they have to agree on which way up
     * the world is.
     */
    private fun upright(source: Mat, turn: Int): Mat = when (turn) {
        90 -> Mat().also { Core.rotate(source, it, Core.ROTATE_90_CLOCKWISE); source.release() }
        180 -> Mat().also { Core.rotate(source, it, Core.ROTATE_180); source.release() }
        270 -> Mat().also { Core.rotate(source, it, Core.ROTATE_90_COUNTERCLOCKWISE); source.release() }
        else -> source
    }

    /** The frame difference, contour extraction, and state-machine track association. */
    private fun detect(prev: Mat, cur: Mat, timestampMs: Long): BallSighting? {
        if (prev.size() != cur.size()) return null

        val diff = Mat()
        Core.absdiff(prev, cur, diff)

        val mask = Mat()
        // Otsu picks the threshold from this frame's own histogram, so the same code holds
        // in flat evening light and harsh midday sun. A fixed number does not travel.
        val otsu = Imgproc.threshold(diff, mask, 0.0, 255.0, Imgproc.THRESH_BINARY + Imgproc.THRESH_OTSU)
        /*
         * ...BUT OTSU ALWAYS SPLITS, EVEN WHEN THERE IS NOTHING TO SPLIT.
         *
         * Found on a real phone standing still in front of the stumps: between two frames of
         * a still scene the difference is sensor noise, at most a few grey levels, and Otsu
         * put its threshold at ZERO — every pixel that changed by one level was "moving", a
         * fifth of the frame, which is the camera-shake rule below. Every frame of every
         * clip was thrown away as a panning camera, and no ball could ever be tracked.
         * Real motion — a ball, a bat, a leg — differs by tens of grey levels, so a floor
         * well above the noise and well below any ball is the right floor.
         */
        if (otsu < MIN_MOTION_DIFF) {
            Imgproc.threshold(diff, mask, MIN_MOTION_DIFF, 255.0, Imgproc.THRESH_BINARY)
        }
        diff.release()

        val totalPx = mask.rows() * mask.cols()
        val movingPx = Core.countNonZero(mask)
        if (movingPx > totalPx * GLOBAL_MOTION_LIMIT) {
            // Most of the frame moved: the camera panned or shook. Tracking that yields a
            // smooth, convincing, entirely fictional path.
            mask.release()
            rejectedGlobalMotion++
            forgetBackground()
            handleMissOnGlobalMotion()
            return null
        }

        val kernel = Imgproc.getStructuringElement(Imgproc.MORPH_ELLIPSE, Size(3.0, 3.0))
        Imgproc.morphologyEx(mask, mask, Imgproc.MORPH_OPEN, kernel)
        kernel.release()

        val contours = ArrayList<MatOfPoint>()
        Imgproc.findContours(mask, contours, Mat(), Imgproc.RETR_EXTERNAL, Imgproc.CHAIN_APPROX_SIMPLE)
        mask.release()
        if (contours.isEmpty()) {
            handleMissOnNoCandidate()
            return null
        }

        // 1. Identify large body clusters (human bodies, torso/legs)
        val bodyBoxes = ArrayList<Rect>()
        for (contour in contours) {
            val area = Imgproc.contourArea(contour) / areaScale
            if (area >= BODY_CLUSTER_MIN_AREA) {
                bodyBoxes.add(Imgproc.boundingRect(contour))
            }
        }

        // 2. Extract valid ball candidate contours
        val candidates = ArrayList<Candidate>()
        val imgCols = cur.cols().toDouble()
        val imgRows = cur.rows().toDouble()

        for (contour in contours) {
            /*
             * Two units. [rawArea] is processed pixels, and the smallest blob worth judging is
             * a matter of pixels — below it a shape is noise, at any resolution. Everything
             * else ([area]) is a physical size, kept in the units of the 480-wide analysis the
             * limits were measured in, so they mean the same ball at any [detail].
             */
            val rawArea = Imgproc.contourArea(contour)
            val area = rawArea / areaScale
            /*
             * A BALL NEAR THE CAMERA IS BIG. Filmed from behind the bowler's arm, the first
             * metres of every delivery are close to the lens: on a real throw (Realme
             * RMX3933, 480-wide analysis) the ball was 430-670 px², perfectly round, and every
             * frame of it was thrown out against a 180 px² ceiling that only fits a ball 3 to
             * 12 m away. Bigger is allowed now — but only when it is unmistakably a ball:
             * round and square-boxed. Arms, legs and bodies on the same clip measured 0.1-0.5
             * round; the ball 0.81-0.90.
             */
            val nearBall = area > MAX_AREA_PX && area <= MAX_NEAR_BALL_AREA_PX
            // The floor rises with the detail, but only linearly: finer analysis is for
            // smaller balls, yet a few-pixel speck of sensor noise is still a speck.
            if (rawArea < MIN_AREA_PX * detail || (area > MAX_AREA_PX && !nearBall)) {
                rejectedSize++
                contour.release()
                continue
            }

            val bRect = Imgproc.boundingRect(contour)
            if (nearBall) {
                val per = Imgproc.arcLength(MatOfPoint2f(*contour.toArray()), true)
                val round = if (per > 0.0) (4.0 * PI * rawArea) / (per * per) else 0.0
                val box = max(bRect.width.toDouble() / max(1, bRect.height), bRect.height.toDouble() / max(1, bRect.width))
                if (round < NEAR_BALL_MIN_CIRCULARITY || box > NEAR_BALL_MAX_ASPECT) {
                    rejectedSize++
                    contour.release()
                    continue
                }
            }

            // Reject candidates deeply embedded inside a large moving body cluster (e.g. torso/shoulder)
            var inBody = false
            for (bb in bodyBoxes) {
                if (bRect.x >= bb.x + bodyMargin && (bRect.x + bRect.width) <= (bb.x + bb.width - bodyMargin) &&
                    bRect.y >= bb.y + bodyMargin && (bRect.y + bRect.height) <= (bb.y + bb.height - bodyMargin)
                ) {
                    inBody = true
                    break
                }
            }
            if (inBody) {
                rejectedCluster++
                contour.release()
                continue
            }

            // Circularity: 4*pi*area / perimeter^2.
            val perimeter = Imgproc.arcLength(MatOfPoint2f(*contour.toArray()), true)
            if (perimeter <= 0.0) {
                contour.release()
                continue
            }
            val circularity = (4.0 * PI * rawArea) / (perimeter * perimeter)
            if (circularity < MIN_CIRCULARITY) {
                rejectedShape++
                contour.release()
                continue
            }

            // Aspect ratio: fast motion elongates the ball slightly, but should not exceed 3.2:1
            val aspect = max(
                bRect.width.toDouble() / max(1, bRect.height),
                bRect.height.toDouble() / max(1, bRect.width),
            )
            if (aspect > MAX_ASPECT_RATIO) {
                rejectedShape++
                contour.release()
                continue
            }

            val moments = Imgproc.moments(contour)
            if (moments.m00 == 0.0) {
                contour.release()
                continue
            }
            var cx = (moments.m10 / moments.m00 / imgCols).toFloat().coerceIn(0f, 1f)
            var cy = (moments.m01 / moments.m00 / imgRows).toFloat().coerceIn(0f, 1f)

            // Against the learned empty scene: a ghost (the ball has left this spot) is
            // dropped, and a real ball's centre is taken from what is there NOW rather than
            // from the smear of now-and-a-frame-ago the difference gives.
            val present = presence(cur, bRect)
            if (present != null) {
                if (present.fraction < GHOST_MAX_PRESENCE) {
                    rejectedGhost++
                    contour.release()
                    continue
                }
                cx = (present.cx / imgCols).toFloat().coerceIn(0f, 1f)
                cy = (present.cy / imgRows).toFloat().coerceIn(0f, 1f)
            }

            // Ignore extreme image boundaries (decoding/scaling edge artifacts)
            if (cx < 0.025f || cx > 0.975f || cy < 0.025f || cy > 0.975f) {
                contour.release()
                continue
            }

            candidates.add(
                Candidate(
                    x = cx,
                    y = cy,
                    area = area,
                    circularity = circularity,
                    aspect = aspect,
                    timestampMs = timestampMs,
                ),
            )
            contour.release()
        }

        if (candidates.isEmpty()) {
            handleMissOnNoCandidate()
            return null
        }

        // 3. Multi-candidate association & state-machine update
        return updateTracking(candidates, timestampMs)
    }

    /**
     * Updates active tracks and state machine against current frame candidates.
     */
    private fun updateTracking(candidates: List<Candidate>, timestampMs: Long): BallSighting? {
        val usedCandidates = HashSet<Int>()
        var emittedSighting: BallSighting? = null

        // Step 1: Update confirmed track if active
        val confirmed = confirmedTrack
        if (confirmed != null) {
            val dt = (timestampMs - confirmed.lastPoint.timestampMs).coerceAtLeast(1)
            if (dt > MAX_COAST_GAP_MS) {
                confirmedTrack = null
                trackingState = TrackingState.LOST
            } else {
                val predX = confirmed.lastPoint.x + confirmed.velocityX * dt
                val predY = confirmed.lastPoint.y + confirmed.velocityY * dt
                val gate = (GATE_RADIUS_BASE * (dt / 33f)).coerceIn(0.04f, 0.12f)

                var bestIdx: Int? = null
                var bestScore = -1f
                var bestVx = 0f
                var bestVy = 0f

                for ((idx, cand) in candidates.withIndex()) {
                    val dx = cand.x - confirmed.lastPoint.x
                    val dy = cand.y - confirmed.lastPoint.y
                    val dist = sqrt((dx * dx + dy * dy).toDouble()).toFloat()
                    val speed = dist / dt.toFloat()

                    // Minimum speed: must not be stationary / lingering body part
                    if (speed < MIN_FLIGHT_SPEED_PER_MS) {
                        rejectedStationary++
                        continue
                    }
                    if (speed > MAX_FLIGHT_SPEED_PER_MS) {
                        rejectedTrajectory++
                        continue
                    }

                    // Distance from extrapolated trajectory position
                    val pdx = cand.x - predX
                    val pdy = cand.y - predY
                    val distPred = sqrt((pdx * pdx + pdy * pdy).toDouble()).toFloat()
                    /*
                     * THE BOUNCE IS WHERE A STRAIGHT-LINE PREDICTION IS MOST WRONG. Off the
                     * pitch the ball's vertical motion in the picture reverses, so the first
                     * sighting after it lands up to twice that motion away from where the
                     * track was heading. Seen on a phone: every sighting past the bounce was
                     * refused here, the track died, a new one began after it, and the trail
                     * showed half a delivery. A candidate still moving the same way across
                     * and reversed up-down — the bounce test below — is given that much
                     * more room. Anything else keeps the tight gate.
                     */
                    val reversesY = confirmed.velocityY * dy < 0f &&
                        (confirmed.velocityX * dx >= 0f || abs(confirmed.velocityX) < BOUNCE_STILL_ACROSS)
                    val bounceSlack = if (reversesY && confirmed.points.size >= MIN_POINTS_FOR_CONFIRMATION) {
                        2f * abs(confirmed.velocityY) * dt
                    } else {
                        0f
                    }
                    if (distPred > gate + bounceSlack) {
                        rejectedTrajectory++
                        continue
                    }

                    // Area stability check
                    val areaRatio = max(cand.area, confirmed.lastPoint.area) /
                        max(1.0, min(cand.area, confirmed.lastPoint.area))
                    if (areaRatio > 3.2) {
                        rejectedSize++
                        continue
                    }

                    val candVx = dx / dt.toFloat()
                    val candVy = dy / dt.toFloat()
                    val candNorm = sqrt((candVx * candVx + candVy * candVy).toDouble()).toFloat()
                    val trackNorm = sqrt((confirmed.velocityX * confirmed.velocityX + confirmed.velocityY * confirmed.velocityY).toDouble()).toFloat()

                    var cosSim = if (candNorm > 0f && trackNorm > 0f) {
                        (confirmed.velocityX * candVx + confirmed.velocityY * candVy) / (candNorm * trackNorm)
                    } else {
                        1f
                    }

                    // Pitch bounce detection: lateral vx aligned, vertical vy inverted
                    var isBounce = false
                    if (cosSim < 0.20f) {
                        if ((confirmed.velocityX * candVx) > 0f && (confirmed.velocityY * candVy) < 0f) {
                            isBounce = true
                            cosSim = 0.70f
                        }
                    }

                    if (cosSim < 0.30f && !isBounce) {
                        rejectedTrajectory++
                        continue
                    }

                    val spatialScore = (1f - (distPred / gate)).coerceIn(0f, 1f)
                    val motionScore = cosSim.coerceIn(0f, 1f)
                    val score = (cand.circularity.toFloat() * 0.3f) + (spatialScore * 0.4f) + (motionScore * 0.3f)

                    if (score > bestScore) {
                        bestScore = score
                        bestIdx = idx
                        bestVx = candVx
                        bestVy = candVy
                    }
                }

                if (bestIdx != null) {
                    usedCandidates.add(bestIdx)
                    val cand = candidates[bestIdx]
                    confirmed.points.add(cand)

                    // If we were coasting (TEMPORARILY_LOST), reacquire
                    if (missedConfirmedFrames > 0) {
                        trackingState = TrackingState.REACQUIRE
                    }
                    missedConfirmedFrames = 0
                    trackingState = TrackingState.CONFIRMED

                    // Smooth velocity update (alpha filter)
                    val alpha = 0.75f
                    confirmed.velocityX = alpha * bestVx + (1f - alpha) * confirmed.velocityX
                    confirmed.velocityY = alpha * bestVy + (1f - alpha) * confirmed.velocityY

                    emittedSighting = BallSighting(
                        timestampMs = timestampMs,
                        x = cand.x,
                        y = cand.y,
                        trackingConfidence = bestScore.coerceIn(0f, 1f),
                        areaPx = cand.area.toInt(),
                    )
                } else {
                    missedConfirmedFrames++
                    if (missedConfirmedFrames <= MAX_COAST_FRAMES) {
                        trackingState = TrackingState.TEMPORARILY_LOST
                    } else {
                        confirmedTrack = null
                        trackingState = TrackingState.LOST
                    }
                }
            }
        }

        // Step 2: Update tentative tracks (must be strictly consecutive frames)
        val nextTentative = mutableListOf<TrackHypothesis>()
        var promotedTrack: TrackHypothesis? = null

        for (tentative in tentativeTracks) {
            val dt = timestampMs - tentative.lastPoint.timestampMs
            if (dt > MAX_TENTATIVE_GAP_MS) {
                // Tentative tracks cannot coast across gaps; must be strictly consecutive
                continue
            }

            var bestIdx: Int? = null
            var bestScore = -1f
            var bestVx = 0f
            var bestVy = 0f

            for ((idx, cand) in candidates.withIndex()) {
                if (idx in usedCandidates) continue

                val dx = cand.x - tentative.lastPoint.x
                val dy = cand.y - tentative.lastPoint.y
                val dist = sqrt((dx * dx + dy * dy).toDouble()).toFloat()
                val speed = dist / dt.toFloat()

                if (speed < MIN_FLIGHT_SPEED_PER_MS || speed > MAX_FLIGHT_SPEED_PER_MS) {
                    continue
                }

                val areaRatio = max(cand.area, tentative.lastPoint.area) /
                    max(1.0, min(cand.area, tentative.lastPoint.area))
                if (areaRatio > 2.5) continue

                val candVx = dx / dt.toFloat()
                val candVy = dy / dt.toFloat()

                // Check vector consistency if tentative track has >= 2 points
                var dirScore = 1f
                if (tentative.points.size >= 2) {
                    val p0 = tentative.points[tentative.points.size - 2]
                    val p1 = tentative.points.last()
                    val pDt = p1.timestampMs - p0.timestampMs
                    if (pDt > 0) {
                        val pVx = (p1.x - p0.x) / pDt.toFloat()
                        val pVy = (p1.y - p0.y) / pDt.toFloat()

                        // Horizontal motion direction sign consistency
                        if (abs(pVx * pDt) > 0.006f && abs(candVx * dt) > 0.006f) {
                            if ((pVx * candVx) < 0f) {
                                // Reversing horizontal direction is human limb oscillation, not cricket ball
                                continue
                            }
                        }

                        val pNorm = sqrt((pVx * pVx + pVy * pVy).toDouble()).toFloat()
                        val cNorm = sqrt((candVx * candVx + candVy * candVy).toDouble()).toFloat()
                        if (pNorm > 0f && cNorm > 0f) {
                            val cosSim = (pVx * candVx + pVy * candVy) / (pNorm * cNorm)
                            if (cosSim < 0.60f) continue
                            dirScore = cosSim.coerceIn(0f, 1f)
                        }

                        // Colinearity / straightness check: reject circular limb swings
                        val lineLen = sqrt(((p1.x - p0.x) * (p1.x - p0.x) + (p1.y - p0.y) * (p1.y - p0.y)).toDouble()).toFloat()
                        if (lineLen > 0.012f) {
                            val perpDev = abs((p1.y - p0.y) * cand.x - (p1.x - p0.x) * cand.y + p1.x * p0.y - p1.y * p0.x) / lineLen
                            if (perpDev > 0.025f) {
                                rejectedTrajectory++
                                continue
                            }
                        }
                    }
                }

                val score = (cand.circularity.toFloat() * 0.4f) + (dirScore * 0.4f) +
                    min(1f, speed / 0.002f) * 0.2f
                if (score > bestScore) {
                    bestScore = score
                    bestIdx = idx
                    bestVx = candVx
                    bestVy = candVy
                }
            }

            if (bestIdx != null) {
                usedCandidates.add(bestIdx)
                val cand = candidates[bestIdx]
                tentative.points.add(cand)
                tentative.velocityX = bestVx
                tentative.velocityY = bestVy
                nextTentative.add(tentative)

                // Check promotion criteria: >= 3 points with net displacement >= 0.035
                if (tentative.points.size >= MIN_POINTS_FOR_CONFIRMATION) {
                    val totDx = tentative.points.last().x - tentative.points.first().x
                    val totDy = tentative.points.last().y - tentative.points.first().y
                    val totDist = sqrt((totDx * totDx + totDy * totDy).toDouble()).toFloat()
                    if (totDist >= MIN_CONFIRMATION_DISPLACEMENT) {
                        if (promotedTrack == null || tentative.points.size > promotedTrack.points.size) {
                            promotedTrack = tentative
                        }
                    }
                }
            }
        }

        // Step 3: Promote qualified tentative track
        if (promotedTrack != null) {
            if (confirmedTrack == null || missedConfirmedFrames > 0) {
                confirmedTrack = promotedTrack
                missedConfirmedFrames = 0
                trackingState = TrackingState.CONFIRMED

                // Start a fresh flight trail for this confirmed delivery
                sightings.clear()
                for (p in promotedTrack.points) {
                    val s = BallSighting(
                        timestampMs = p.timestampMs,
                        x = p.x,
                        y = p.y,
                        trackingConfidence = 0.90f,
                        areaPx = p.area.toInt(),
                    )
                    sightings.add(s)
                }
                emittedSighting = sightings.last()
                nextTentative.remove(promotedTrack)
            }
        }

        // Step 4: Seed new tentative tracks from unused high-circularity candidates
        val unusedCands = candidates.indices
            .filter { it !in usedCandidates && candidates[it].circularity >= SEED_MIN_CIRCULARITY }
            .map { candidates[it] }
            .sortedByDescending { it.circularity }

        for (cand in unusedCands.take(2)) {
            if (nextTentative.size < MAX_TENTATIVE_TRACKS) {
                nextTentative.add(TrackHypothesis(cand))
            }
        }

        tentativeTracks.clear()
        tentativeTracks.addAll(nextTentative)

        if (confirmedTrack == null) {
            trackingState = if (tentativeTracks.isNotEmpty()) TrackingState.TENTATIVE else TrackingState.LOST
        }

        return emittedSighting
    }

    private fun handleMissOnGlobalMotion() {
        if (confirmedTrack != null) {
            missedConfirmedFrames++
            if (missedConfirmedFrames <= MAX_COAST_FRAMES) {
                trackingState = TrackingState.TEMPORARILY_LOST
            } else {
                confirmedTrack = null
                trackingState = TrackingState.LOST
            }
        }
        tentativeTracks.clear()
    }

    private fun handleMissOnNoCandidate() {
        if (confirmedTrack != null) {
            missedConfirmedFrames++
            if (missedConfirmedFrames <= MAX_COAST_FRAMES) {
                trackingState = TrackingState.TEMPORARILY_LOST
            } else {
                confirmedTrack = null
                trackingState = TrackingState.LOST
            }
        }
        tentativeTracks.clear()
    }

    private fun resetTrackingState() {
        trackingState = TrackingState.LOST
        tentativeTracks.clear()
        confirmedTrack = null
        missedConfirmedFrames = 0
    }

    override fun track(): List<BallSighting> = sightings.toList()

    override fun quality(): TrackQuality {
        if (sightings.size < MIN_POINTS_FOR_TRACK) return TrackQuality.UNCERTAIN

        var jumps = 0
        for (i in 1 until sightings.size) {
            val a = sightings[i - 1]
            val b = sightings[i]
            val gapMs = (b.timestampMs - a.timestampMs).coerceAtLeast(1)
            if (gapMs > TRACK_GAP_LIMIT_MS) continue
            val dx = b.x - a.x
            val dy = b.y - a.y
            if (sqrt((dx * dx + dy * dy).toDouble()) > MAX_STEP_PER_FRAME) jumps++
        }
        if (jumps > sightings.size / 3) return TrackQuality.UNCERTAIN

        val mean = sightings.map { it.trackingConfidence }.average()
        return if (mean >= 0.5 && sightings.size >= 6) TrackQuality.RELIABLE else TrackQuality.PARTIAL
    }

    override fun diagnostics() = VisionDiagnostics(
        framesSeen = framesSeen,
        framesWithCandidate = framesWithCandidate,
        rejectedGlobalMotion = rejectedGlobalMotion,
        rejectedSize = rejectedSize,
        rejectedShape = rejectedShape,
        rejectedTrajectory = rejectedTrajectory,
        averageProcessingMs = if (framesSeen == 0) 0.0 else totalProcessingMs.toDouble() / framesSeen,
        maxProcessingMs = maxProcessingMs,
        rejectedStationary = rejectedStationary,
        rejectedCluster = rejectedCluster,
        trackingState = trackingState.name,
    )

    private class Presence(val fraction: Double, val cx: Double, val cy: Double)

    /**
     * How much of [box] differs from the learned background in [cur], and the centre of what
     * does — or null while the background is still being learned (then nothing is judged).
     */
    private fun presence(cur: Mat, box: Rect): Presence? {
        val bg = background8 ?: return null
        if (backgroundFrames < BACKGROUND_WARMUP_FRAMES) return null
        val pad = 2
        val x0 = (box.x - pad).coerceAtLeast(0)
        val y0 = (box.y - pad).coerceAtLeast(0)
        val x1 = (box.x + box.width + pad).coerceAtMost(cur.cols())
        val y1 = (box.y + box.height + pad).coerceAtMost(cur.rows())
        if (x1 <= x0 || y1 <= y0) return null
        val roi = Rect(x0, y0, x1 - x0, y1 - y0)
        val diff = Mat()
        val fg = Mat()
        val curRoi = Mat(cur, roi)
        val bgRoi = Mat(bg, roi)
        try {
            Core.absdiff(curRoi, bgRoi, diff)
            Imgproc.threshold(diff, fg, MIN_MOTION_DIFF, 255.0, Imgproc.THRESH_BINARY)
            val on = Core.countNonZero(fg)
            // Fraction of the ORIGINAL box (not the padded one) that is occupied now.
            val fraction = on.toDouble() / max(1, box.width * box.height)
            if (on == 0) return Presence(0.0, 0.0, 0.0)
            val m = Imgproc.moments(fg, true)
            return Presence(fraction, x0 + m.m10 / m.m00, y0 + m.m01 / m.m00)
        } finally {
            diff.release(); fg.release(); curRoi.release(); bgRoi.release()
        }
    }

    /**
     * Fold this frame into the empty-scene model: quickly where nothing is moving, not at all
     * where something is — so a ball crossing the frame is never learned as background.
     */
    private fun learnBackground(gray: Mat) {
        val bg = background
        if (bg == null || bg.rows() != gray.rows() || bg.cols() != gray.cols()) {
            forgetBackground()
            background = Mat().also { gray.convertTo(it, CvType.CV_32F) }
            background8 = gray.clone()
            backgroundFrames = 1
            return
        }
        val bg8 = background8!!
        val diff = Mat()
        val still = Mat()
        try {
            Core.absdiff(gray, bg8, diff)
            Imgproc.threshold(diff, still, MIN_MOTION_DIFF, 255.0, Imgproc.THRESH_BINARY_INV)
            val rate = if (backgroundFrames < BACKGROUND_WARMUP_FRAMES) 0.5 else BACKGROUND_LEARN_RATE
            Imgproc.accumulateWeighted(gray, bg, rate, still)
            bg.convertTo(bg8, CvType.CV_8U)
            backgroundFrames++
        } finally {
            diff.release(); still.release()
        }
    }

    private fun forgetBackground() {
        background?.release()
        background = null
        background8?.release()
        background8 = null
        backgroundFrames = 0
    }

    override fun reset() {
        forgetBackground()
        rejectedGhost = 0
        previous?.release()
        previous = null
        previousRotation = null
        sightings.clear()
        resetTrackingState()
        framesSeen = 0
        framesWithCandidate = 0
        rejectedGlobalMotion = 0
        rejectedSize = 0
        rejectedShape = 0
        rejectedTrajectory = 0
        rejectedStationary = 0
        rejectedCluster = 0
        totalProcessingMs = 0
        maxProcessingMs = 0
    }

    override fun release() {
        released = true
        forgetBackground()
        previous?.release()
        previous = null
        previousRotation = null
        scratchLuma?.release()
        scratchLuma = null
        scratchPacked = null
        resetTrackingState()
    }

    private data class Candidate(
        val x: Float,
        val y: Float,
        val area: Double,
        val circularity: Double,
        val aspect: Double,
        val timestampMs: Long,
    )

    private class TrackHypothesis(seed: Candidate) {
        val points = mutableListOf(seed)
        var velocityX = 0f
        var velocityY = 0f

        val lastPoint: Candidate get() = points.last()
    }

    companion object {
        private const val TAG = "OpenCvBallTracker"

        /**
         * Loaded once per process. When this fails — an ABI without the native library,
         * a stripped APK — the engine reports unavailable and the app records exactly as
         * it did before. Vision is an enhancement, never a prerequisite for filming.
         */
        @Volatile
        private var loaded: Boolean? = null

        fun ensureLoaded(): Boolean {
            loaded?.let { return it }
            synchronized(this) {
                loaded?.let { return it }
                val ok = runCatching { OpenCVLoader.initLocal() }.getOrDefault(false)
                if (!ok) runCatching { Log.w(TAG, "OpenCV native library unavailable; vision disabled") }
                loaded = ok
                return ok
            }
        }

        /** Below this a candidate is sensor noise; above it, it is a person or a shadow. */
        const val MIN_AREA_PX = 14.0

        /** The finest [setDetail] allowed: 720 wide off a 1280 frame is 1.5. */
        const val MAX_DETAIL = 2.0
        const val MAX_AREA_PX = 180.0

        /** Frames before the empty-scene model is trusted to call a ghost. */
        const val BACKGROUND_WARMUP_FRAMES = 6

        /** How fast still pixels are learned into the empty scene, per frame. */
        const val BACKGROUND_LEARN_RATE = 0.08

        /** Below this share of its box actually occupied NOW, a moving blob is a ghost. */
        const val GHOST_MAX_PRESENCE = 0.18

        /** A ball close to the lens, allowed only when round: see the size gate in [detect]. */
        const val MAX_NEAR_BALL_AREA_PX = 1100.0
        const val NEAR_BALL_MIN_CIRCULARITY = 0.78
        const val NEAR_BALL_MAX_ASPECT = 1.5

        /** 1.0 is a perfect circle. Motion blur stretches a ball, so this cannot be strict. */
        const val MIN_CIRCULARITY = 0.45

        /** Above this share of moving pixels, the camera moved rather than the subject. */
        const val GLOBAL_MOTION_LIMIT = 0.12

        /**
         * Smallest frame difference, in grey levels after the 5×5 blur, that counts as
         * motion. A still scene on a Realme RMX3933 differs by at most 7; a ball by tens.
         */
        const val MIN_MOTION_DIFF = 12.0

        /** Fraction of the frame a ball can cross between adjacent frames. */
        const val MAX_STEP_PER_FRAME = 0.30f

        /** Longer than this and the next sighting is a new flight, not a continuation. */
        const val TRACK_GAP_LIMIT_MS = 400L

        const val MIN_POINTS_FOR_TRACK = 3

        // Ballistic flight constraints
        const val MIN_FLIGHT_SPEED_PER_MS = 0.00050f
        const val MAX_FLIGHT_SPEED_PER_MS = 0.00650f
        const val BODY_CLUSTER_MIN_AREA = 700.0
        const val MAX_ASPECT_RATIO = 3.2
        const val GATE_RADIUS_BASE = 0.05f
        const val MAX_COAST_FRAMES = 2
        const val MAX_COAST_GAP_MS = 160L
        /*
         * One dropped frame allowed while a ball is being picked up: at 30 fps that is a
         * 67 ms gap, and the analyser drops frames by design when it falls behind
         * (KEEP_ONLY_LATEST). At 55 ms a single drop at release threw the start away.
         */
        const val MAX_TENTATIVE_GAP_MS = 75L
        const val MIN_POINTS_FOR_CONFIRMATION = 3

        /** Across-the-frame speed (frame widths per ms) below which a ball counts as going straight. */
        const val BOUNCE_STILL_ACROSS = 0.00005f
        const val MIN_CONFIRMATION_DISPLACEMENT = 0.045f
        const val SEED_MIN_CIRCULARITY = 0.70
        const val MAX_TENTATIVE_TRACKS = 5
    }
}
