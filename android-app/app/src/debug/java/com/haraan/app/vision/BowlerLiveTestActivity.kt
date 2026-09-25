package com.haraan.app.vision

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.os.VibrationEffect
import android.os.Vibrator
import android.util.Size
import androidx.activity.ComponentActivity
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.camera.core.CameraSelector
import androidx.camera.core.ExperimentalGetImage
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.Preview
import androidx.camera.core.resolutionselector.AspectRatioStrategy
import androidx.camera.core.resolutionselector.ResolutionSelector
import androidx.camera.core.resolutionselector.ResolutionStrategy
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.scaleIn
import androidx.compose.animation.scaleOut
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import com.google.mlkit.vision.common.InputImage
import com.google.mlkit.vision.pose.Pose
import com.google.mlkit.vision.pose.PoseDetection
import com.google.mlkit.vision.pose.PoseLandmark
import com.google.mlkit.vision.pose.defaults.PoseDetectorOptions
import com.haraan.app.BuildConfig
import java.util.concurrent.Executors
import kotlin.math.max

/**
 * Real-time Bowler Body Tracking & Kinematic Analysis Activity.
 *
 * Broadcast-grade UI overlay with Hawk-Eye styling:
 * - Ultra-fast CameraX pipeline with optimal resolution for 30+ FPS pose inference
 * - Aspect-ratio correct projection aligning skeleton to live body contours
 * - Exponential Moving Average (EMA) landmark temporal smoothing
 * - Dual-layer glowing neon skeletal visualization with active wrist crosshair
 * - Real-time Phase Step Progress Bar (IDLE -> RUN-UP -> GATHER -> STRIDE -> RELEASE -> FOLLOW-THRU)
 * - Biomechanical Telemetry: Arm Angle, Elbow Straightness (ICC 15° legal check), Hand Velocity
 */
class BowlerLiveTestActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!BuildConfig.DEBUG) {
            finish()
            return
        }
        setContent { BowlerLiveCameraScreen() }
    }
}

@OptIn(ExperimentalGetImage::class)
@Composable
private fun BowlerLiveCameraScreen() {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current

    var hasCameraPermission by remember {
        mutableStateOf(
            ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA) ==
                PackageManager.PERMISSION_GRANTED
        )
    }
    val permissionLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) {
        hasCameraPermission = it
    }
    LaunchedEffect(Unit) {
        if (!hasCameraPermission) permissionLauncher.launch(Manifest.permission.CAMERA)
    }

    var cameraSelector by remember { mutableStateOf(CameraSelector.DEFAULT_BACK_CAMERA) }
    val isFrontCamera = cameraSelector == CameraSelector.DEFAULT_FRONT_CAMERA

    val analysisExecutor = remember { Executors.newSingleThreadExecutor() }
    val bowlerTracker = remember { BowlerDeliveryTracker() }

    val poseDetector = remember {
        val options = PoseDetectorOptions.Builder()
            .setDetectorMode(PoseDetectorOptions.STREAM_MODE)
            .build()
        PoseDetection.getClient(options)
    }

    var currentSkeleton by remember { mutableStateOf<BowlerSkeleton?>(null) }
    var smoothedSkeleton by remember { mutableStateOf<BowlerSkeleton?>(null) }
    var currentSighting by remember {
        mutableStateOf(
            BowlerDeliverySighting(
                phase = BowlingPhase.IDLE,
                timestampMs = 0L,
                isReleaseMoment = false,
                bowlingArm = BowlingArm.RIGHT,
                armAngleDegrees = 0f,
                strideWidthNormalized = 0f,
                releaseZenithY = 1f,
                elbowAngleDegrees = 180f,
                handSpeedNormalized = 0f,
            )
        )
    }

    // Camera preview and analysis dimensions for coordinate mapping
    var analysisWidth by remember { mutableStateOf(480) }
    var analysisHeight by remember { mutableStateOf(640) }

    var fps by remember { mutableStateOf(0.0) }
    var frameCount by remember { mutableStateOf(0) }
    var lastFpsTimestamp by remember { mutableStateOf(System.currentTimeMillis()) }
    var releaseFlash by remember { mutableStateOf<String?>(null) }
    var deliveriesCount by remember { mutableStateOf(0) }
    var lastClipSaved by remember { mutableStateOf<String?>(null) }

    // High-speed 60 FPS & Camera2 sports shutter controls
    var highFpsEnabled by remember { mutableStateOf(true) }
    var sportsShutterEnabled by remember { mutableStateOf(false) }

    // Low-latency UDP wireless sync broadcaster
    val deliveryBroadcaster = remember { DeliveryBroadcaster() }
    var syncBroadcastStatus by remember { mutableStateOf("READY (PORT 8888)") }

    // Enterprise Kinematic & Delivery Pipeline
    val bowlerFilter = remember { BowlerKinematicFilter() }
    val deliveryStateMachine = remember { DeliveryStateMachine() }
    val clipBuffer = remember { DeliveryClipBuffer() }
    val thermalManager = remember { ThermalPolicyManager(context) }

    var bowlerCandidate by remember { mutableStateOf<PersonKinematicCandidate?>(null) }
    var deliveryLifecycleState by remember { mutableStateOf(DeliveryLifecycleState.IDLE) }
    var latestReplayPackage by remember { mutableStateOf<DeliveryReplayPackage?>(null) }
    var isStealthEcoMode by remember { mutableStateOf(false) }
    var showCreaseCalibration by remember { mutableStateOf(false) }
    var thermalState by remember { mutableStateOf(ThermalOperationalState.NOMINAL) }

    var pitchHomography by remember {
        val defaultRef = PitchCalibrationReference(
            bowlingCreaseLeft = Point2(0.20, 0.85),
            bowlingCreaseRight = Point2(0.80, 0.85),
            poppingCreaseLeft = Point2(0.30, 0.65),
            poppingCreaseRight = Point2(0.70, 0.65),
        )
        mutableStateOf(PitchHomography.fromCalibration(defaultRef).getOrNull())
    }

    val vibrator = remember {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            context.getSystemService(Context.VIBRATOR_MANAGER_SERVICE) as? android.os.VibratorManager
                ?: context.getSystemService(Context.VIBRATOR_SERVICE) as Vibrator
        } else {
            @Suppress("DEPRECATION")
            context.getSystemService(Context.VIBRATOR_SERVICE) as Vibrator
        }
    }

    DisposableEffect(Unit) {
        onDispose {
            deliveryBroadcaster.close()
            analysisExecutor.shutdown()
            poseDetector.close()
        }
    }

    val analyzer = remember(isFrontCamera) {
        ImageAnalysis.Analyzer { imageProxy ->
            val mediaImage = imageProxy.image
            if (mediaImage != null) {
                val rotationDegrees = imageProxy.imageInfo.rotationDegrees
                val inputImage = InputImage.fromMediaImage(mediaImage, rotationDegrees)
                val rotW = if (rotationDegrees == 90 || rotationDegrees == 270) imageProxy.height else imageProxy.width
                val rotH = if (rotationDegrees == 90 || rotationDegrees == 270) imageProxy.width else imageProxy.height

                analysisWidth = rotW
                analysisHeight = rotH

                poseDetector.process(inputImage)
                    .addOnSuccessListener { pose ->
                        val rawSkeleton = extractSkeleton(pose, rotW, rotH, isFrontCamera)
                        currentSkeleton = rawSkeleton

                        // Apply temporal smoothing (EMA alpha = 0.65)
                        val smoothed = smoothSkeleton(smoothedSkeleton, rawSkeleton, alpha = 0.65f)
                        smoothedSkeleton = smoothed

                        val activeSkeleton = smoothed ?: rawSkeleton
                        val timestamp = imageProxy.imageInfo.timestamp / 1_000_000

                        // Bowler Kinematic Filtering (Distinguish Bowler from Umpire & Non-striker)
                        val candidateMap = if (activeSkeleton != null) mapOf(1 to activeSkeleton) else emptyMap()
                        val candidates = bowlerFilter.processFrame(candidateMap, pitchHomography, timestamp)
                        val primaryCandidate = candidates.firstOrNull()
                        bowlerCandidate = primaryCandidate

                        // Record frame into memory-safe circular replay buffer
                        clipBuffer.onFrame(
                            timestampMs = timestamp,
                            skeleton = activeSkeleton
                        )

                        val sighting = bowlerTracker.onFrame(activeSkeleton, timestamp)
                        currentSighting = sighting

                        // Central Delivery Lifecycle State Machine
                        val deliveryEvent = deliveryStateMachine.onFrame(
                            bowlerCandidate = primaryCandidate,
                            bowlerSighting = sighting,
                            ballInFlight = false,
                            ballBounceDetected = false,
                            stumpImpact = null,
                            timestampMs = timestamp
                        )
                        deliveryLifecycleState = deliveryEvent.state

                        if (sighting.isReleaseMoment || deliveryEvent.state == DeliveryLifecycleState.RELEASE_CONFIRMED) {
                            deliveriesCount++
                            val legality = if (sighting.isActionLegal) "LEGAL ACTION" else "SUSPECT FLEXION (>15°)"
                            val flexDeg = sighting.elbowFlexionDegrees.toInt()
                            val ht = "%.2f".format(sighting.releaseHeightMeters)
                            val stride = "%.2f".format(sighting.strideLengthMeters)
                            val speed = sighting.handSpeedKmh.toInt()
                            releaseFlash = "BALL #$deliveriesCount LOCKED\n${sighting.bowlingArm} • HT: ${ht}m @ ${sighting.armAngleDegrees.toInt()}°\nSPEED: $speed km/h • STRIDE: ${stride}m\nFLEXION: Δ$flexDeg° [$legality]"
                            lastClipSaved = "CLIP #$deliveriesCount (3.5s REPLAY READY)"

                            // Ultra-low latency UDP broadcast to companion scorer device with monotonic sync
                            val packet = DeliverySyncPacket(
                                event = "DELIVERY_START",
                                deliveryIndex = deliveriesCount,
                                timestampMs = System.currentTimeMillis(),
                                bowlingArm = sighting.bowlingArm.name,
                                releaseAngleDegrees = sighting.armAngleDegrees,
                                elbowFlexionDegrees = sighting.elbowFlexionDegrees,
                                isActionLegal = sighting.isActionLegal,
                                releaseHeightMeters = sighting.releaseHeightMeters,
                                strideLengthMeters = sighting.strideLengthMeters,
                                handSpeedKmh = sighting.handSpeedKmh,
                                sessionId = "session_${System.currentTimeMillis() / 1000}",
                                deviceId = "camera_bowler_front",
                                deviceRole = DeviceSyncRole.BOWLER_FRONT.name,
                                sequenceNumber = deliveriesCount.toLong(),
                            )
                            deliveryBroadcaster.broadcastDelivery(packet)
                            DeliverySyncBus.emit(packet)
                            syncBroadcastStatus = "TX #$deliveriesCount ($speed km/h)"

                            // Slice immutable delivery replay package
                            val replay = clipBuffer.sliceDeliveryClip(
                                deliveryIndex = deliveriesCount,
                                releaseTimestampMs = timestamp,
                                matchId = "MATCH_LIVE",
                                sessionId = "session_live",
                                deviceId = "camera_bowler_front",
                                cameraRole = DeviceSyncRole.BOWLER_FRONT.name,
                                bowlerId = primaryCandidate?.candidateId ?: 1,
                                bowlerConfidence = primaryCandidate?.bowlerConfidence ?: 0.90f,
                                releaseMetrics = sighting,
                                syncPacket = packet
                            )
                            latestReplayPackage = replay

                            try {
                                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                                    val vib = if (vibrator is android.os.VibratorManager && Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                                        vibrator.defaultVibrator
                                    } else {
                                        vibrator as Vibrator
                                    }
                                    vib.vibrate(VibrationEffect.createOneShot(160, VibrationEffect.DEFAULT_AMPLITUDE))
                                }
                            } catch (_: Throwable) {}
                        }

                        // Periodic thermal policy check
                        val thermalReport = thermalManager.evaluateThermalStatus()
                        thermalState = thermalReport.state

                        frameCount++
                        val now = System.currentTimeMillis()
                        if (now - lastFpsTimestamp >= 1000) {
                            fps = frameCount * 1000.0 / (now - lastFpsTimestamp)
                            frameCount = 0
                            lastFpsTimestamp = now
                        }
                    }
                    .addOnCompleteListener {
                        imageProxy.close()
                    }
            } else {
                imageProxy.close()
            }
        }
    }

    LaunchedEffect(releaseFlash) {
        if (releaseFlash != null) {
            kotlinx.coroutines.delay(1800)
            releaseFlash = null
        }
    }

    Box(Modifier.fillMaxSize().background(Color.Black)) {
        if (hasCameraPermission) {
            val bindCamera: (PreviewView, ProcessCameraProvider) -> Unit = { view, provider ->
                val targetFps = if (highFpsEnabled) 60 else 30
                val resSelector = ResolutionSelector.Builder()
                    .setResolutionStrategy(
                        ResolutionStrategy(
                            Size(640, 480),
                            ResolutionStrategy.FALLBACK_RULE_CLOSEST_HIGHER
                        )
                    )
                    .setAspectRatioStrategy(AspectRatioStrategy.RATIO_16_9_FALLBACK_AUTO_STRATEGY)
                    .build()

                val previewBuilder = Preview.Builder()
                CameraHardwareConfig.configurePreview(
                    previewBuilder,
                    targetFps = targetFps,
                    lockShutterSpeed = sportsShutterEnabled
                )
                val preview = previewBuilder.build().also {
                    it.setSurfaceProvider(view.surfaceProvider)
                }

                val imageAnalysisBuilder = ImageAnalysis.Builder()
                    .setResolutionSelector(resSelector)
                    .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                CameraHardwareConfig.configureAnalysis(
                    imageAnalysisBuilder,
                    targetFps = targetFps
                )
                val imageAnalysis = imageAnalysisBuilder.build()
                    .also { it.setAnalyzer(analysisExecutor, analyzer) }

                runCatching {
                    provider.unbindAll()
                    provider.bindToLifecycle(
                        lifecycleOwner,
                        cameraSelector,
                        preview,
                        imageAnalysis
                    )
                }
            }

            AndroidView(
                modifier = Modifier.fillMaxSize(),
                factory = { ctx ->
                    PreviewView(ctx).also { view ->
                        view.scaleType = PreviewView.ScaleType.FILL_CENTER
                        val cameraProviderFuture = ProcessCameraProvider.getInstance(ctx)
                        cameraProviderFuture.addListener({
                            val cameraProvider = cameraProviderFuture.get()
                            bindCamera(view, cameraProvider)
                        }, ContextCompat.getMainExecutor(ctx))
                    }
                },
                update = { view ->
                    val cameraProviderFuture = ProcessCameraProvider.getInstance(view.context)
                    cameraProviderFuture.addListener({
                        val cameraProvider = cameraProviderFuture.get()
                        bindCamera(view, cameraProvider)
                    }, ContextCompat.getMainExecutor(view.context))
                }
            )

            // LIVE SKELETON CANVAS WITH CORRECT ASPECT RATIO FILL PROJECTION
            SkeletonBroadcastOverlay(
                skeleton = smoothedSkeleton ?: currentSkeleton,
                activeArm = currentSighting.bowlingArm,
                armAngle = currentSighting.armAngleDegrees,
                frameW = analysisWidth,
                frameH = analysisHeight,
                modifier = Modifier.fillMaxSize()
            )

            // PITCH CREASE & FRONT-FOOT NO-BALL OVERLAY
            if (showCreaseCalibration) {
                val frontAnkle = if (currentSighting.bowlingArm == BowlingArm.RIGHT) {
                    (smoothedSkeleton ?: currentSkeleton)?.leftAnkle
                } else {
                    (smoothedSkeleton ?: currentSkeleton)?.rightAnkle
                }
                val frontFootGround = if (frontAnkle != null && frontAnkle.confidence >= 0.40f && pitchHomography != null) {
                    val proj = pitchHomography?.toGround(Point2(frontAnkle.x.toDouble(), frontAnkle.y.toDouble()), isKnownAirborne = false)
                    if (proj is HomographyProjection.GroundSurface) proj.groundPoint else null
                } else null
                val isBehindCrease = frontFootGround?.let { pitchHomography?.isFrontFootBehindPoppingCrease(it) }

                PitchCreaseOverlay(
                    pitchHomography = pitchHomography,
                    frontFootBehindCrease = isBehindCrease,
                    modifier = Modifier.fillMaxSize()
                )
            }

            // TOP HUD & TELEMETRY STRIP (Safe from status bar icons & camera cutouts)
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .statusBarsPadding()
                    .padding(horizontal = 14.dp, vertical = 6.dp)
            ) {
                // Header Bar
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            modifier = Modifier
                                .size(8.dp)
                                .background(Color(0xFF00FF66), CircleShape)
                        )
                        Spacer(Modifier.width(6.dp))
                        Text(
                            text = "HAWK-EYE BOWLER VISION",
                            color = Color.White,
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Black,
                            letterSpacing = 1.1.sp
                        )
                    }

                    // Camera Switch & FPS Chip
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Box(
                            modifier = Modifier
                                .background(Color(0xFF0F172A).copy(alpha = 0.8f), RoundedCornerShape(8.dp))
                                .border(1.dp, Color(0xFF00FF66).copy(alpha = 0.4f), RoundedCornerShape(8.dp))
                                .padding(horizontal = 8.dp, vertical = 4.dp)
                        ) {
                            Text(
                                text = "${"%.1f".format(fps)} FPS",
                                color = Color(0xFF00FF66),
                                fontSize = 11.sp,
                                fontFamily = FontFamily.Monospace,
                                fontWeight = FontWeight.Bold
                            )
                        }

                        Box(
                            modifier = Modifier
                                .background(Color(0xFF1E293B).copy(alpha = 0.85f), RoundedCornerShape(8.dp))
                                .border(1.dp, Color.White.copy(alpha = 0.25f), RoundedCornerShape(8.dp))
                                .clickable {
                                    cameraSelector = if (isFrontCamera) {
                                        CameraSelector.DEFAULT_BACK_CAMERA
                                    } else {
                                        CameraSelector.DEFAULT_FRONT_CAMERA
                                    }
                                    bowlerTracker.reset()
                                    bowlerFilter.reset()
                                    deliveryStateMachine.reset()
                                    clipBuffer.reset()
                                }
                                .padding(horizontal = 10.dp, vertical = 5.dp)
                        ) {
                            Text(
                                text = if (isFrontCamera) "FRONT" else "BACK",
                                color = Color.White,
                                fontSize = 11.sp,
                                fontWeight = FontWeight.ExtraBold
                            )
                        }
                    }
                }

                Spacer(Modifier.height(8.dp))

                // HIGH-SPEED 60 FPS + 1/500S SPORTS SHUTTER + UDP SYNC CONTROL ROW
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    // 1. 60 FPS Toggle Button
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .clip(RoundedCornerShape(8.dp))
                            .background(
                                if (highFpsEnabled) Color(0xFF064E3B) else Color(0xFF1E293B).copy(alpha = 0.85f)
                            )
                            .border(
                                1.dp,
                                if (highFpsEnabled) Color(0xFF10B981) else Color.White.copy(alpha = 0.2f),
                                RoundedCornerShape(8.dp)
                            )
                            .clickable { highFpsEnabled = !highFpsEnabled }
                            .padding(vertical = 5.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = if (highFpsEnabled) "⚡ 60 FPS" else "30 FPS",
                            color = if (highFpsEnabled) Color(0xFF34D399) else Color(0xFF94A3B8),
                            fontSize = 10.sp,
                            fontWeight = FontWeight.ExtraBold,
                            fontFamily = FontFamily.Monospace
                        )
                    }

                    // 2. 1/500s Sports Shutter Speed Lock Toggle
                    Box(
                        modifier = Modifier
                            .weight(1.3f)
                            .clip(RoundedCornerShape(8.dp))
                            .background(
                                if (sportsShutterEnabled) Color(0xFF78350F) else Color(0xFF1E293B).copy(alpha = 0.85f)
                            )
                            .border(
                                1.dp,
                                if (sportsShutterEnabled) Color(0xFFF59E0B) else Color.White.copy(alpha = 0.2f),
                                RoundedCornerShape(8.dp)
                            )
                            .clickable { sportsShutterEnabled = !sportsShutterEnabled }
                            .padding(vertical = 5.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = if (sportsShutterEnabled) "🎯 1/500s LOCK" else "SHUTTER AUTO",
                            color = if (sportsShutterEnabled) Color(0xFFFBBF24) else Color(0xFF94A3B8),
                            fontSize = 10.sp,
                            fontWeight = FontWeight.ExtraBold,
                            fontFamily = FontFamily.Monospace
                        )
                    }

                    // 3. UDP Wireless Sync Status & Ping Button
                    Box(
                        modifier = Modifier
                            .weight(1.7f)
                            .clip(RoundedCornerShape(8.dp))
                            .background(Color(0xFF0F172A).copy(alpha = 0.9f))
                            .border(1.dp, Color(0xFF38BDF8).copy(alpha = 0.45f), RoundedCornerShape(8.dp))
                            .clickable {
                                val pingPacket = DeliverySyncPacket(
                                    event = "PING_TEST",
                                    deliveryIndex = deliveriesCount,
                                    timestampMs = System.currentTimeMillis(),
                                    bowlingArm = currentSighting.bowlingArm.name,
                                    releaseAngleDegrees = currentSighting.armAngleDegrees,
                                    elbowFlexionDegrees = currentSighting.elbowFlexionDegrees,
                                    isActionLegal = currentSighting.isActionLegal,
                                    releaseHeightMeters = currentSighting.releaseHeightMeters,
                                    strideLengthMeters = currentSighting.strideLengthMeters,
                                    handSpeedKmh = currentSighting.handSpeedKmh,
                                )
                                deliveryBroadcaster.broadcastDelivery(pingPacket)
                                DeliverySyncBus.emit(pingPacket)
                                syncBroadcastStatus = "PING UDP 8888 (SENT)"
                            }
                            .padding(horizontal = 8.dp, vertical = 5.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(
                                modifier = Modifier
                                    .size(6.dp)
                                    .background(Color(0xFF38BDF8), CircleShape)
                            )
                            Spacer(Modifier.width(4.dp))
                            Text(
                                text = "SYNC: $syncBroadcastStatus",
                                color = Color(0xFF38BDF8),
                                fontSize = 9.sp,
                                fontWeight = FontWeight.Bold,
                                fontFamily = FontFamily.Monospace,
                                maxLines = 1
                            )
                        }
                    }
                }

                Spacer(Modifier.height(6.dp))

                // ENTERPRISE CALIBRATION + OLED STEALTH ECO + THERMAL SHIELD ROW
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    // Crease Calibration Toggle
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .clip(RoundedCornerShape(8.dp))
                            .background(
                                if (showCreaseCalibration) Color(0xFF0369A1) else Color(0xFF1E293B).copy(alpha = 0.85f)
                            )
                            .border(
                                1.dp,
                                if (showCreaseCalibration) Color(0xFF38BDF8) else Color.White.copy(alpha = 0.2f),
                                RoundedCornerShape(8.dp)
                            )
                            .clickable { showCreaseCalibration = !showCreaseCalibration }
                            .padding(vertical = 5.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = if (showCreaseCalibration) "📐 CREASE ON" else "📐 CREASE OFF",
                            color = if (showCreaseCalibration) Color(0xFF7DD3FC) else Color(0xFF94A3B8),
                            fontSize = 10.sp,
                            fontWeight = FontWeight.ExtraBold,
                            fontFamily = FontFamily.Monospace
                        )
                    }

                    // OLED Eco Stealth Mode Toggle
                    Box(
                        modifier = Modifier
                            .weight(1.1f)
                            .clip(RoundedCornerShape(8.dp))
                            .background(Color(0xFF064E3B).copy(alpha = 0.9f))
                            .border(1.dp, Color(0xFF10B981).copy(alpha = 0.6f), RoundedCornerShape(8.dp))
                            .clickable { isStealthEcoMode = true }
                            .padding(vertical = 5.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = "🌿 STEALTH ECO",
                            color = Color(0xFF34D399),
                            fontSize = 10.sp,
                            fontWeight = FontWeight.ExtraBold,
                            fontFamily = FontFamily.Monospace
                        )
                    }

                    // Thermal Status Indicator
                    val thermalColor = when (thermalState) {
                        ThermalOperationalState.NOMINAL -> Color(0xFF00FF66)
                        ThermalOperationalState.WARM -> Color(0xFFFBBF24)
                        ThermalOperationalState.HOT -> Color(0xFFFB923C)
                        ThermalOperationalState.CRITICAL -> Color(0xFFEF4444)
                    }
                    Box(
                        modifier = Modifier
                            .weight(1.2f)
                            .clip(RoundedCornerShape(8.dp))
                            .background(Color(0xFF0F172A).copy(alpha = 0.9f))
                            .border(1.dp, thermalColor.copy(alpha = 0.45f), RoundedCornerShape(8.dp))
                            .padding(horizontal = 6.dp, vertical = 5.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(
                                modifier = Modifier
                                    .size(6.dp)
                                    .background(thermalColor, CircleShape)
                            )
                            Spacer(Modifier.width(4.dp))
                            Text(
                                text = "TEMP: ${thermalState.name}",
                                color = thermalColor,
                                fontSize = 9.sp,
                                fontWeight = FontWeight.Bold,
                                fontFamily = FontFamily.Monospace,
                                maxLines = 1
                            )
                        }
                    }
                }

                Spacer(Modifier.height(8.dp))

                // Phase Progress Tracker
                DeliveryPhaseStepBar(currentPhase = currentSighting.phase)

                Spacer(Modifier.height(10.dp))

                // Hawk-Eye Kinematic Telemetry Card
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(
                            Brush.verticalGradient(
                                listOf(Color(0xFF0B192E).copy(alpha = 0.92f), Color(0xFF060D17).copy(alpha = 0.92f))
                            ),
                            RoundedCornerShape(14.dp)
                        )
                        .border(1.dp, Color(0xFF38BDF8).copy(alpha = 0.35f), RoundedCornerShape(14.dp))
                        .padding(horizontal = 12.dp, vertical = 10.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    // 1. Arm & Side
                    Column {
                        Text("BOWLER", color = Color(0xFF94A3B8), fontSize = 9.sp, fontWeight = FontWeight.Bold)
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(
                                modifier = Modifier
                                    .size(6.dp)
                                    .background(Color(0xFF00FF66), CircleShape)
                            )
                            Spacer(Modifier.width(4.dp))
                            Text(
                                text = "${currentSighting.bowlingArm} ARM",
                                color = Color.White,
                                fontSize = 12.sp,
                                fontWeight = FontWeight.ExtraBold,
                                fontFamily = FontFamily.Monospace
                            )
                        }
                    }

                    // 2. Elevation Angle
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text("ELEVATION", color = Color(0xFF94A3B8), fontSize = 9.sp, fontWeight = FontWeight.Bold)
                        Text(
                            text = "${currentSighting.armAngleDegrees.toInt()}°",
                            color = Color(0xFF00FF66),
                            fontSize = 15.sp,
                            fontWeight = FontWeight.Black,
                            fontFamily = FontFamily.Monospace
                        )
                    }

                    // 3. ICC 15° Flexion Check
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text("ICC 15° FLEX", color = Color(0xFF94A3B8), fontSize = 9.sp, fontWeight = FontWeight.Bold)
                        val flex = currentSighting.elbowFlexionDegrees.toInt()
                        val isLegal = currentSighting.isActionLegal
                        Text(
                            text = "Δ$flex° • ${if (isLegal) "LEGAL" else "CHUCK"}",
                            color = if (isLegal) Color(0xFF00E5FF) else Color(0xFFEF4444),
                            fontSize = 12.sp,
                            fontWeight = FontWeight.ExtraBold,
                            fontFamily = FontFamily.Monospace
                        )
                    }

                    // 4. Deliveries Count & Auto-Record status
                    val isAutoRecording = currentSighting.phase == BowlingPhase.GATHER || currentSighting.phase == BowlingPhase.DELIVERY_STRIDE
                    Column(horizontalAlignment = Alignment.End) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            if (isAutoRecording) {
                                Box(
                                    modifier = Modifier
                                        .size(6.dp)
                                        .background(Color(0xFFEF4444), CircleShape)
                                )
                                Spacer(Modifier.width(3.dp))
                            }
                            Text(
                                text = if (isAutoRecording) "REC" else "BALLS",
                                color = if (isAutoRecording) Color(0xFFEF4444) else Color(0xFF94A3B8),
                                fontSize = 9.sp,
                                fontWeight = FontWeight.Bold
                            )
                        }
                        Text(
                            text = "#$deliveriesCount",
                            color = Color.White,
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Black,
                            fontFamily = FontFamily.Monospace
                        )
                    }
                }

                Spacer(Modifier.height(6.dp))

                // Secondary Athletic Biometrics Rail (Release Height, Stride Length, Hand Speed)
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(Color(0xFF0F172A).copy(alpha = 0.75f), RoundedCornerShape(10.dp))
                        .border(1.dp, Color.White.copy(alpha = 0.10f), RoundedCornerShape(10.dp))
                        .padding(horizontal = 14.dp, vertical = 6.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    // Release Height
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text("RELEASE HT: ", color = Color(0xFF94A3B8), fontSize = 10.sp, fontWeight = FontWeight.Bold)
                        Text(
                            text = "${"%.2f".format(currentSighting.releaseHeightMeters)}m",
                            color = Color(0xFF00FF66),
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Black,
                            fontFamily = FontFamily.Monospace
                        )
                    }

                    // Stride Length
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text("STRIDE: ", color = Color(0xFF94A3B8), fontSize = 10.sp, fontWeight = FontWeight.Bold)
                        Text(
                            text = "${"%.2f".format(currentSighting.strideLengthMeters)}m",
                            color = Color(0xFF38BDF8),
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Black,
                            fontFamily = FontFamily.Monospace
                        )
                    }

                    // Hand Speed
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text("HAND: ", color = Color(0xFF94A3B8), fontSize = 10.sp, fontWeight = FontWeight.Bold)
                        Text(
                            text = "${currentSighting.handSpeedKmh.toInt()} km/h",
                            color = Color(0xFFFFB703),
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Black,
                            fontFamily = FontFamily.Monospace
                        )
                    }
                }
            }

            // FULL-SCREEN RELEASE EVENT ANNOUNCEMENT BANNER
            AnimatedVisibility(
                visible = releaseFlash != null,
                enter = fadeIn() + scaleIn(initialScale = 0.8f),
                exit = fadeOut() + scaleOut(targetScale = 1.1f),
                modifier = Modifier
                    .align(Alignment.Center)
                    .padding(20.dp)
            ) {
                Box(
                    modifier = Modifier
                        .background(
                            Brush.horizontalGradient(listOf(Color(0xFF00E5FF), Color(0xFF00FF66))),
                            RoundedCornerShape(20.dp)
                        )
                        .border(3.dp, Color.White, RoundedCornerShape(20.dp))
                        .padding(horizontal = 24.dp, vertical = 16.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(
                            text = "BALL RELEASE LOCKED",
                            color = Color.Black,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Black,
                            letterSpacing = 1.5.sp
                        )
                        Spacer(Modifier.height(4.dp))
                        Text(
                            text = releaseFlash ?: "",
                            color = Color.Black,
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Black,
                            fontFamily = FontFamily.Monospace
                        )
                    }
                }
            }

            // REPLAY CARD OVERLAY
            latestReplayPackage?.let { replay ->
                DeliveryReplayCard(
                    replayPackage = replay,
                    onDismiss = { latestReplayPackage = null },
                    modifier = Modifier
                        .align(Alignment.BottomCenter)
                        .navigationBarsPadding()
                        .padding(bottom = 72.dp)
                )
            }

            // BOTTOM STATUS BADGE: BOWLER KINEMATIC IDENTIFICATION & DELIVERY STATE
            val isPersonLocked = currentSkeleton != null
            val isUmpire = bowlerCandidate?.role == PersonRole.UMPIRE
            val isBowlerLocked = bowlerCandidate?.isLockedBowler == true || bowlerCandidate?.role == PersonRole.BOWLER

            val statusDotColor = when {
                isUmpire -> Color(0xFFF59E0B)
                isBowlerLocked -> Color(0xFF00FF66)
                isPersonLocked -> Color(0xFF38BDF8)
                else -> Color(0xFF64748B)
            }

            val bowlerLabel = when {
                isUmpire -> "UMPIRE FILTERED (${"%.1f".format(bowlerCandidate?.velocityKmh ?: 0f)} km/h)"
                isBowlerLocked -> "BOWLER LOCKED #${bowlerCandidate?.candidateId ?: 1} (${"%.1f".format(bowlerCandidate?.velocityKmh ?: 0f)} km/h)"
                isPersonLocked -> "PERSON DETECTED • EVALUATING"
                else -> "SCANNING • NO PERSON DETECTED"
            }

            Row(
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .navigationBarsPadding()
                    .padding(bottom = 20.dp)
                    .background(Color(0xFF0B192E).copy(alpha = 0.90f), RoundedCornerShape(20.dp))
                    .border(
                        1.dp,
                        statusDotColor.copy(alpha = 0.50f),
                        RoundedCornerShape(20.dp)
                    )
                    .padding(horizontal = 14.dp, vertical = 7.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Box(
                    modifier = Modifier
                        .size(8.dp)
                        .background(statusDotColor, CircleShape)
                )
                Spacer(Modifier.width(7.dp))
                Text(
                    text = bowlerLabel,
                    color = Color.White,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    fontFamily = FontFamily.Monospace,
                    letterSpacing = 0.6.sp
                )
                Spacer(Modifier.width(10.dp))
                Box(
                    modifier = Modifier
                        .background(Color.White.copy(alpha = 0.15f), RoundedCornerShape(6.dp))
                        .padding(horizontal = 6.dp, vertical = 2.dp)
                ) {
                    Text(
                        text = deliveryLifecycleState.name,
                        color = Color(0xFF38BDF8),
                        fontSize = 9.sp,
                        fontWeight = FontWeight.Black,
                        fontFamily = FontFamily.Monospace
                    )
                }
            }

            // OLED ECO STEALTH OVERLAY (Saves ~95% screen power outdoors)
            if (isStealthEcoMode) {
                OledStealthOverlay(
                    deliveriesCount = deliveriesCount,
                    state = deliveryLifecycleState,
                    fps = fps,
                    onWake = { isStealthEcoMode = false }
                )
            }
        } else {
            Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                Text("Camera permission required", color = Color.White)
            }
        }
    }
}

/**
 * Modern Segmented Delivery Phase Step Bar
 */
@Composable
private fun DeliveryPhaseStepBar(currentPhase: BowlingPhase) {
    val phases = listOf(
        BowlingPhase.IDLE to "IDLE",
        BowlingPhase.RUN_UP to "APPROACH",
        BowlingPhase.GATHER to "GATHER",
        BowlingPhase.DELIVERY_STRIDE to "STRIDE",
        BowlingPhase.RELEASE to "RELEASE",
        BowlingPhase.FOLLOW_THROUGH to "FOLLOW"
    )

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .background(Color(0xFF0B192E).copy(alpha = 0.80f), RoundedCornerShape(10.dp))
            .border(1.dp, Color.White.copy(alpha = 0.12f), RoundedCornerShape(10.dp))
            .padding(horizontal = 6.dp, vertical = 6.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        val currentIndex = phases.indexOfFirst { it.first == currentPhase }.coerceAtLeast(0)

        phases.forEachIndexed { index, (phase, label) ->
            val isActive = phase == currentPhase
            val isPassed = index < currentIndex

            val bg = when {
                isActive && phase == BowlingPhase.RELEASE -> Color(0xFF00FF66)
                isActive -> Color(0xFF38BDF8)
                isPassed -> Color.White.copy(alpha = 0.2f)
                else -> Color.Transparent
            }
            val textCol = when {
                isActive && phase == BowlingPhase.RELEASE -> Color.Black
                isActive -> Color.Black
                isPassed -> Color.White.copy(alpha = 0.7f)
                else -> Color.White.copy(alpha = 0.35f)
            }

            Box(
                modifier = Modifier
                    .clip(RoundedCornerShape(6.dp))
                    .background(bg)
                    .padding(horizontal = 7.dp, vertical = 4.dp),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = label,
                    color = textCol,
                    fontSize = 9.sp,
                    fontWeight = if (isActive) FontWeight.ExtraBold else FontWeight.Medium,
                    fontFamily = FontFamily.Monospace
                )
            }
        }
    }
}

/**
 * Aspect-ratio correct Broadcast Canvas Overlay with Glowing Skeleton & Wrist Reticle
 */
@Composable
private fun SkeletonBroadcastOverlay(
    skeleton: BowlerSkeleton?,
    activeArm: BowlingArm,
    armAngle: Float,
    frameW: Int,
    frameH: Int,
    modifier: Modifier = Modifier,
) {
    val infiniteTransition = rememberInfiniteTransition()
    val pulseRadius by infiniteTransition.animateFloat(
        initialValue = 16f,
        targetValue = 26f,
        animationSpec = infiniteRepeatable(tween(700, easing = FastOutSlowInEasing), RepeatMode.Reverse)
    )

    Canvas(modifier = modifier) {
        if (skeleton == null || frameW <= 0 || frameH <= 0) return@Canvas
        val screenW = size.width
        val screenH = size.height

        // Compute FILL_CENTER transform mapping
        val scale = max(screenW / frameW.toFloat(), screenH / frameH.toFloat())
        val offsetX = (screenW - frameW * scale) / 2f
        val offsetY = (screenH - frameH * scale) / 2f

        fun pt(kp: PoseKeypoint): Offset {
            return Offset(offsetX + kp.x * frameW * scale, offsetY + kp.y * frameH * scale)
        }

        val ls = pt(skeleton.leftShoulder)
        val rs = pt(skeleton.rightShoulder)
        val le = pt(skeleton.leftElbow)
        val re = pt(skeleton.rightElbow)
        val lw = pt(skeleton.leftWrist)
        val rw = pt(skeleton.rightWrist)
        val lh = pt(skeleton.leftHip)
        val rh = pt(skeleton.rightHip)
        val lk = pt(skeleton.leftKnee)
        val rk = pt(skeleton.rightKnee)
        val la = pt(skeleton.leftAnkle)
        val ra = pt(skeleton.rightAnkle)

        // Palette
        val activeGlow = Color(0x6600FF66)
        val activeCore = Color(0xFF00FF66)
        val nonActiveGlow = Color(0x5500E5FF)
        val nonActiveCore = Color(0xFF00E5FF)
        val spineCore = Color.White
        val spineGlow = Color(0x44FFFFFF)
        val legCore = Color(0xFFFFB703)
        val legGlow = Color(0x55FFB703)

        // 1. Shoulder reference guideline (dashed horizon)
        val shoulderSlope = Offset(rs.x - ls.x, rs.y - ls.y)
        drawLine(
            color = Color.White.copy(alpha = 0.35f),
            start = Offset(ls.x - shoulderSlope.x * 0.4f, ls.y - shoulderSlope.y * 0.4f),
            end = Offset(rs.x + shoulderSlope.x * 0.4f, rs.y + shoulderSlope.y * 0.4f),
            strokeWidth = 2f,
            pathEffect = PathEffect.dashPathEffect(floatArrayOf(12f, 8f))
        )

        fun drawGlowBone(glowCol: Color, coreCol: Color, kp1: PoseKeypoint, kp2: PoseKeypoint, w: Float = 6f) {
            if (kp1.confidence < 0.40f || kp2.confidence < 0.40f) return
            val start = pt(kp1)
            val end = pt(kp2)
            drawLine(glowCol, start, end, w * 2.5f, cap = StrokeCap.Round)
            drawLine(coreCol, start, end, w, cap = StrokeCap.Round)
        }

        // 2. Arms (highlight active bowling arm in Neon Lime)
        val isRightActive = activeArm == BowlingArm.RIGHT
        drawGlowBone(if (isRightActive) activeGlow else nonActiveGlow, if (isRightActive) activeCore else nonActiveCore, skeleton.rightShoulder, skeleton.rightElbow, 8f)
        drawGlowBone(if (isRightActive) activeGlow else nonActiveGlow, if (isRightActive) activeCore else nonActiveCore, skeleton.rightElbow, skeleton.rightWrist, 8f)
        drawGlowBone(if (!isRightActive) activeGlow else nonActiveGlow, if (!isRightActive) activeCore else nonActiveCore, skeleton.leftShoulder, skeleton.leftElbow, 8f)
        drawGlowBone(if (!isRightActive) activeGlow else nonActiveGlow, if (!isRightActive) activeCore else nonActiveCore, skeleton.leftElbow, skeleton.leftWrist, 8f)

        // 3. Torso & Spine
        drawGlowBone(spineGlow, spineCore, skeleton.leftShoulder, skeleton.rightShoulder, 6f)
        drawGlowBone(spineGlow, spineCore, skeleton.leftShoulder, skeleton.leftHip, 6f)
        drawGlowBone(spineGlow, spineCore, skeleton.rightShoulder, skeleton.rightHip, 6f)
        drawGlowBone(spineGlow, spineCore, skeleton.leftHip, skeleton.rightHip, 6f)

        // 4. Stride Legs
        drawGlowBone(legGlow, legCore, skeleton.leftHip, skeleton.leftKnee, 6f)
        drawGlowBone(legGlow, legCore, skeleton.leftKnee, skeleton.leftAnkle, 6f)
        drawGlowBone(legGlow, legCore, skeleton.rightHip, skeleton.rightKnee, 6f)
        drawGlowBone(legGlow, legCore, skeleton.rightKnee, skeleton.rightAnkle, 6f)

        // 5. Joint Nodes (Concentric circles)
        listOf(
            skeleton.leftShoulder, skeleton.rightShoulder,
            skeleton.leftElbow, skeleton.rightElbow,
            skeleton.leftWrist, skeleton.rightWrist,
            skeleton.leftHip, skeleton.rightHip,
            skeleton.leftKnee, skeleton.rightKnee,
            skeleton.leftAnkle, skeleton.rightAnkle
        ).forEach { kp ->
            if (kp.confidence >= 0.40f) {
                val joint = pt(kp)
                drawCircle(Color.Black.copy(alpha = 0.5f), radius = 9f, center = joint)
                drawCircle(Color.White, radius = 5f, center = joint)
            }
        }

        // 6. Targeting Reticle on Active Bowling Wrist
        val activeWristKp = if (isRightActive) skeleton.rightWrist else skeleton.leftWrist
        if (activeWristKp.confidence >= 0.40f) {
            val activeWristPt = pt(activeWristKp)
            drawCircle(activeGlow, radius = pulseRadius, center = activeWristPt)
            drawCircle(activeCore, radius = 10f, center = activeWristPt, style = Stroke(width = 3f))
            drawCircle(Color.White, radius = 4f, center = activeWristPt)

            // Crosshairs on wrist
            val ch = 8f
            drawLine(activeCore, Offset(activeWristPt.x - ch, activeWristPt.y), Offset(activeWristPt.x + ch, activeWristPt.y), 2f)
            drawLine(activeCore, Offset(activeWristPt.x, activeWristPt.y - ch), Offset(activeWristPt.x, activeWristPt.y + ch), 2f)
        }
    }
}

/**
 * Exponential Moving Average (EMA) Temporal Smoothing to eliminate joint jitter
 */
private fun smoothSkeleton(
    prev: BowlerSkeleton?,
    curr: BowlerSkeleton?,
    alpha: Float
): BowlerSkeleton? {
    if (curr == null) return null // If no person detected, clear skeleton immediately
    if (prev == null) return curr

    fun sPt(p: PoseKeypoint, c: PoseKeypoint): PoseKeypoint {
        val nx = p.x * (1f - alpha) + c.x * alpha
        val ny = p.y * (1f - alpha) + c.y * alpha
        return PoseKeypoint(nx, ny, c.confidence)
    }

    return BowlerSkeleton(
        nose = sPt(prev.nose, curr.nose),
        leftShoulder = sPt(prev.leftShoulder, curr.leftShoulder),
        rightShoulder = sPt(prev.rightShoulder, curr.rightShoulder),
        leftElbow = sPt(prev.leftElbow, curr.leftElbow),
        rightElbow = sPt(prev.rightElbow, curr.rightElbow),
        leftWrist = sPt(prev.leftWrist, curr.leftWrist),
        rightWrist = sPt(prev.rightWrist, curr.rightWrist),
        leftHip = sPt(prev.leftHip, curr.leftHip),
        rightHip = sPt(prev.rightHip, curr.rightHip),
        leftKnee = sPt(prev.leftKnee, curr.leftKnee),
        rightKnee = sPt(prev.rightKnee, curr.rightKnee),
        leftAnkle = sPt(prev.leftAnkle, curr.leftAnkle),
        rightAnkle = sPt(prev.rightAnkle, curr.rightAnkle),
    )
}

/**
 * Strict Human Bowler Validation Gate
 * Rejects inanimate objects (backpacks, blankets, chairs, walls) by enforcing:
 * 1. Confident torso landmarks (shoulders + hips)
 * 2. Anthropometric geometry (shoulders above hips, realistic aspect ratio)
 * 3. Head / face presence
 */
private fun extractSkeleton(
    pose: Pose,
    frameW: Int,
    frameH: Int,
    mirrorX: Boolean,
): BowlerSkeleton? {
    fun kp(type: Int): PoseKeypoint {
        val lm = pose.getPoseLandmark(type) ?: return PoseKeypoint(0f, 0f, 0f)
        var normX = lm.position.x / frameW.toFloat()
        if (mirrorX) normX = 1f - normX
        val normY = lm.position.y / frameH.toFloat()
        return PoseKeypoint(normX.coerceIn(0f, 1f), normY.coerceIn(0f, 1f), lm.inFrameLikelihood)
    }

    val nose = kp(PoseLandmark.NOSE)
    val ls = kp(PoseLandmark.LEFT_SHOULDER)
    val rs = kp(PoseLandmark.RIGHT_SHOULDER)
    val lh = kp(PoseLandmark.LEFT_HIP)
    val rh = kp(PoseLandmark.RIGHT_HIP)

    // 1. Core Confidence: Both shoulders must be clearly detected
    if (ls.confidence < 0.55f || rs.confidence < 0.55f) return null

    // 2. At least one hip must be detected with good confidence
    if (lh.confidence < 0.45f && rh.confidence < 0.45f) return null

    // 3. Upright Human Geometry Check:
    // In image space (0 at top), shoulders MUST be vertically above hips
    val midShoulderY = (ls.y + rs.y) / 2f
    val effectiveHipY = when {
        lh.confidence >= 0.45f && rh.confidence >= 0.45f -> (lh.y + rh.y) / 2f
        lh.confidence >= 0.45f -> lh.y
        else -> rh.y
    }

    val torsoHeight = effectiveHipY - midShoulderY
    // A real person's torso spans at least 6% of the screen height
    if (torsoHeight < 0.06f) return null

    // 4. Shoulder Width Check: Must be between 5% and 60% of frame width
    val dxShoulder = (rs.x - ls.x).toDouble()
    val dyShoulder = (rs.y - ls.y).toDouble()
    val shoulderWidth = kotlin.math.hypot(dxShoulder, dyShoulder).toFloat()
    if (shoulderWidth !in 0.05f..0.60f) return null

    // 5. Human Proportions: Torso height to shoulder width ratio
    val torsoRatio = torsoHeight / shoulderWidth
    if (torsoRatio < 0.40f || torsoRatio > 3.8f) return null

    // 6. Facial Anchor Check: Inanimate objects lack head landmarks
    val lEar = kp(PoseLandmark.LEFT_EAR)
    val rEar = kp(PoseLandmark.RIGHT_EAR)
    val hasFaceAnchor = nose.confidence >= 0.45f || lEar.confidence >= 0.40f || rEar.confidence >= 0.40f
    if (!hasFaceAnchor && (ls.confidence < 0.70f || rs.confidence < 0.70f)) {
        return null
    }

    return BowlerSkeleton(
        nose = nose,
        leftShoulder = ls,
        rightShoulder = rs,
        leftElbow = kp(PoseLandmark.LEFT_ELBOW),
        rightElbow = kp(PoseLandmark.RIGHT_ELBOW),
        leftWrist = kp(PoseLandmark.LEFT_WRIST),
        rightWrist = kp(PoseLandmark.RIGHT_WRIST),
        leftHip = lh,
        rightHip = rh,
        leftKnee = kp(PoseLandmark.LEFT_KNEE),
        rightKnee = kp(PoseLandmark.RIGHT_KNEE),
        leftAnkle = kp(PoseLandmark.LEFT_ANKLE),
        rightAnkle = kp(PoseLandmark.RIGHT_ANKLE),
    )
}

/**
 * Enterprise Pitch Crease Overlay.
 * Projects metric crease lines (Bowling crease, Popping crease, Return crease corridor)
 * onto camera viewport and monitors bowler front-foot landing legality.
 */
@Composable
private fun PitchCreaseOverlay(
    pitchHomography: PitchHomography?,
    frontFootBehindCrease: Boolean?,
    modifier: Modifier = Modifier,
) {
    if (pitchHomography == null) return

    Box(modifier = modifier) {
        Canvas(modifier = Modifier.fillMaxSize()) {
            val screenW = size.width
            val screenH = size.height

            // 1. Bowling Crease Line: X = -1.32m to +1.32m, Y = 0.0m
            val bcL = pitchHomography.toImage(GroundPoint(-1.32, 0.0))
            val bcR = pitchHomography.toImage(GroundPoint(1.32, 0.0))
            if (bcL != null && bcR != null) {
                drawLine(
                    color = Color(0xFF00FF66),
                    start = Offset((bcL.x * screenW).toFloat(), (bcL.y * screenH).toFloat()),
                    end = Offset((bcR.x * screenW).toFloat(), (bcR.y * screenH).toFloat()),
                    strokeWidth = 3f
                )
            }

            // 2. Popping Crease (Front-Foot No-Ball Line): X = -1.83m to +1.83m, Y = 1.22m
            val pcL = pitchHomography.toImage(GroundPoint(-1.83, 1.2192))
            val pcR = pitchHomography.toImage(GroundPoint(1.83, 1.2192))
            if (pcL != null && pcR != null) {
                val lineCol = when (frontFootBehindCrease) {
                    false -> Color(0xFFEF4444) // Overstepped
                    true -> Color(0xFF00E5FF)  // Fair delivery
                    null -> Color(0xFF38BDF8)  // Scanning
                }
                drawLine(
                    color = lineCol,
                    start = Offset((pcL.x * screenW).toFloat(), (pcL.y * screenH).toFloat()),
                    end = Offset((pcR.x * screenW).toFloat(), (pcR.y * screenH).toFloat()),
                    strokeWidth = 4f
                )
            }

            // 3. Return Creases (Corridor Boundaries)
            val rcTL = pitchHomography.toImage(GroundPoint(-1.32, 2.44))
            val rcBL = pitchHomography.toImage(GroundPoint(-1.32, -0.60))
            if (rcTL != null && rcBL != null) {
                drawLine(
                    color = Color.White.copy(alpha = 0.40f),
                    start = Offset((rcBL.x * screenW).toFloat(), (rcBL.y * screenH).toFloat()),
                    end = Offset((rcTL.x * screenW).toFloat(), (rcTL.y * screenH).toFloat()),
                    strokeWidth = 2f,
                    pathEffect = PathEffect.dashPathEffect(floatArrayOf(10f, 10f))
                )
            }

            val rcTR = pitchHomography.toImage(GroundPoint(1.32, 2.44))
            val rcBR = pitchHomography.toImage(GroundPoint(1.32, -0.60))
            if (rcTR != null && rcBR != null) {
                drawLine(
                    color = Color.White.copy(alpha = 0.40f),
                    start = Offset((rcBR.x * screenW).toFloat(), (rcBR.y * screenH).toFloat()),
                    end = Offset((rcTR.x * screenW).toFloat(), (rcTR.y * screenH).toFloat()),
                    strokeWidth = 2f,
                    pathEffect = PathEffect.dashPathEffect(floatArrayOf(10f, 10f))
                )
            }
        }

        // Crease Front-Foot Status Pill
        val statusText = when (frontFootBehindCrease) {
            true -> "CREASE: FAIR DELIVERY (BEHIND POPPING LINE)"
            false -> "CREASE: NO-BALL (FRONT-FOOT OVERSTEPPED)"
            null -> "CREASE: MONITORING APPROACH"
        }
        val pillBg = when (frontFootBehindCrease) {
            true -> Color(0xFF064E3B)
            false -> Color(0xFF7F1D1D)
            null -> Color(0xFF0F172A).copy(alpha = 0.85f)
        }
        val pillBorder = when (frontFootBehindCrease) {
            true -> Color(0xFF10B981)
            false -> Color(0xFFEF4444)
            null -> Color(0xFF38BDF8).copy(alpha = 0.4f)
        }

        Box(
            modifier = Modifier
                .align(Alignment.TopCenter)
                .statusBarsPadding()
                .padding(top = 180.dp)
                .background(pillBg, RoundedCornerShape(12.dp))
                .border(1.dp, pillBorder, RoundedCornerShape(12.dp))
                .padding(horizontal = 12.dp, vertical = 5.dp)
        ) {
            Text(
                text = statusText,
                color = Color.White,
                fontSize = 10.sp,
                fontWeight = FontWeight.Bold,
                fontFamily = FontFamily.Monospace
            )
        }
    }
}

/**
 * Enterprise Replay Package Floating Card.
 */
@Composable
private fun DeliveryReplayCard(
    replayPackage: DeliveryReplayPackage,
    onDismiss: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Box(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = 14.dp)
            .background(
                Brush.verticalGradient(
                    listOf(Color(0xFF0F172A).copy(alpha = 0.95f), Color(0xFF020617).copy(alpha = 0.95f))
                ),
                RoundedCornerShape(16.dp)
            )
            .border(1.5.dp, Color(0xFF38BDF8), RoundedCornerShape(16.dp))
            .padding(14.dp)
    ) {
        Column {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(10.dp)
                            .background(Color(0xFF00FF66), CircleShape)
                    )
                    Spacer(Modifier.width(6.dp))
                    Text(
                        text = "DELIVERY #${replayPackage.deliveryIndex} REPLAY READY",
                        color = Color.White,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Black,
                        fontFamily = FontFamily.Monospace
                    )
                }

                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(6.dp))
                        .background(Color(0xFF334155))
                        .clickable { onDismiss() }
                        .padding(horizontal = 8.dp, vertical = 3.dp)
                ) {
                    Text("DISMISS", color = Color(0xFFCBD5E1), fontSize = 10.sp, fontWeight = FontWeight.Bold)
                }
            }

            Spacer(Modifier.height(8.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column {
                    Text("DURATION / FRAMES", color = Color(0xFF94A3B8), fontSize = 9.sp, fontWeight = FontWeight.Bold)
                    Text(
                        "${replayPackage.durationMs}ms (${replayPackage.frameCount} frames)",
                        color = Color(0xFF38BDF8),
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        fontFamily = FontFamily.Monospace
                    )
                }

                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text("SPEED / ACTION", color = Color(0xFF94A3B8), fontSize = 9.sp, fontWeight = FontWeight.Bold)
                    val speed = replayPackage.releaseMetrics?.handSpeedKmh?.toInt() ?: 0
                    val isLegal = replayPackage.releaseMetrics?.isActionLegal ?: true
                    Text(
                        "$speed km/h • ${if (isLegal) "LEGAL" else "CHUCK"}",
                        color = if (isLegal) Color(0xFF00FF66) else Color(0xFFEF4444),
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        fontFamily = FontFamily.Monospace
                    )
                }

                Column(horizontalAlignment = Alignment.End) {
                    Text("SYNC PACKET", color = Color(0xFF94A3B8), fontSize = 9.sp, fontWeight = FontWeight.Bold)
                    Text(
                        "SEQ #${replayPackage.syncPacket?.sequenceNumber ?: 1}",
                        color = Color(0xFF00FF66),
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        fontFamily = FontFamily.Monospace
                    )
                }
            }
        }
    }
}

/**
 * OLED Eco Stealth Mode Overlay.
 * Darkens display to pure black (RGB 0,0,0) to reduce display power by ~95%
 * on OLED devices, preventing thermal throttling during long outdoor matches.
 */
@Composable
private fun OledStealthOverlay(
    deliveriesCount: Int,
    state: DeliveryLifecycleState,
    fps: Double,
    onWake: () -> Unit,
) {
    val infiniteTransition = rememberInfiniteTransition()
    val stealthPulse by infiniteTransition.animateFloat(
        initialValue = 0.35f,
        targetValue = 1.0f,
        animationSpec = infiniteRepeatable(tween(1000, easing = FastOutSlowInEasing), RepeatMode.Reverse)
    )

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Color.Black)
            .clickable { onWake() },
        contentAlignment = Alignment.Center
    ) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Box(
                modifier = Modifier
                    .size(16.dp)
                    .background(Color(0xFF00FF66).copy(alpha = stealthPulse), CircleShape)
            )
            Spacer(Modifier.height(14.dp))
            Text(
                text = "OLED ECO STEALTH ACTIVE",
                color = Color(0xFF00FF66),
                fontSize = 14.sp,
                fontWeight = FontWeight.Black,
                letterSpacing = 1.2.sp
            )
            Spacer(Modifier.height(6.dp))
            Text(
                text = "SAVING ~95% SCREEN POWER • RUNNING ${"%.0f".format(fps)} FPS",
                color = Color(0xFF64748B),
                fontSize = 11.sp,
                fontWeight = FontWeight.Bold,
                fontFamily = FontFamily.Monospace
            )
            Spacer(Modifier.height(8.dp))
            Text(
                text = "BALLS: #$deliveriesCount • LIFECYCLE: ${state.name}",
                color = Color.White,
                fontSize = 12.sp,
                fontWeight = FontWeight.Bold,
                fontFamily = FontFamily.Monospace
            )
            Spacer(Modifier.height(20.dp))
            Text(
                text = "[ TAP ANYWHERE TO RESUME HUD ]",
                color = Color(0xFF38BDF8),
                fontSize = 11.sp,
                fontWeight = FontWeight.Bold,
                fontFamily = FontFamily.Monospace
            )
        }
    }
}
