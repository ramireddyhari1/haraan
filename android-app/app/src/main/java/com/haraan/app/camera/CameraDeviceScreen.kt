package com.haraan.app.camera

import android.content.Context
import android.content.res.Configuration
import android.os.Build
import android.os.PowerManager
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.video.MediaStoreOutputOptions
import androidx.camera.video.Quality
import androidx.camera.video.QualitySelector
import androidx.camera.video.Recorder
import androidx.camera.core.ImageAnalysis
import androidx.camera.video.Recording
import androidx.camera.video.VideoCapture
import androidx.camera.video.VideoRecordEvent
import androidx.camera.core.CameraSelector
import androidx.camera.core.Preview
import androidx.camera.core.resolutionselector.AspectRatioStrategy
import androidx.camera.core.resolutionselector.ResolutionSelector
import androidx.camera.core.resolutionselector.ResolutionStrategy
import androidx.camera.view.PreviewView
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.displayCutoutPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.vision.WicketDiagnosticsPanel
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.foundation.Image
import androidx.core.content.ContextCompat
import com.haraan.app.R
import com.haraan.app.theme.ArchivoDisplay
import com.haraan.app.ui.Feel
import com.haraan.app.data.CameraDeviceRepository
import com.haraan.app.vision.OpenCvBallTracker
import com.haraan.app.vision.drawWicketLock
import com.haraan.app.vision.TrackQuality
import com.haraan.app.data.CameraSession
import com.haraan.app.data.PairingPreview
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import java.io.File
import java.util.concurrent.Executors

// ─────────────────────────────────────────────────────────────────────────────
//  THE CAMERA PHONE
//
//  Three states, in order: what you are about to join, joining it, and filming.
//
//  Everything here is built for someone standing at a boundary holding a phone
//  they are not going to look at: one enormous button, a status line readable at
//  arm's length, and no navigation to get lost in. The screen never sleeps.
//
//  What it captures is FOOTAGE, and the wording never promises more. A single
//  uncalibrated phone at 30fps cannot adjudicate an LBW, and a screen that said
//  it could would be believed.
// ─────────────────────────────────────────────────────────────────────────────

private val Ink = Color(0xFFF8FAFC)
private val Panel = Color(0xFF111827)
private val Page = Color(0xFF0B1220)
private val Accent = Color(0xFF2563EB)
private val Rec = Color(0xFFDC2626)
private val Good = Color(0xFF16A34A)

@Composable
fun CameraDeviceScreen(
    initialCode: String?,
    hasCameraPermission: () -> Boolean,
    requestCameraPermission: ((Boolean) -> Unit) -> Unit,
    onExit: () -> Unit,
) {
    val repo = remember { CameraDeviceRepository() }
    val scope = rememberCoroutineScope()

    var preview by remember { mutableStateOf<PairingPreview?>(null) }
    var session by remember { mutableStateOf<CameraSession?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var busy by remember { mutableStateOf(false) }

    LaunchedEffect(initialCode) {
        val code = initialCode
        if (code == "TEST" || code == "DEBUG" || (com.haraan.app.BuildConfig.DEBUG && code == null)) {
            session = com.haraan.app.data.CameraSession(
                sessionToken = "debug_token",
                role = com.haraan.app.data.MatchDeviceRole.LBW_REVIEW,
                roleLabel = "Review Camera (Field Test)",
                matchId = "debug_match",
                matchTitle = "Live Camera · Field Test",
                venue = "Cricket Ground",
            )
            return@LaunchedEffect
        }
        if (code == null) {
            error = "That link is missing its pairing code."
            return@LaunchedEffect
        }
        busy = true
        runCatching { repo.preview(code) }
            .onSuccess { preview = it }
            .onFailure { error = it.message ?: "That pairing code is not valid." }
        busy = false
    }

    Box(Modifier.fillMaxSize()) {
        CameraBackdrop()
        val joined = session
        when {
            joined != null -> CameraMode(
                session = joined,
                repo = repo,
                hasCameraPermission = hasCameraPermission,
                requestCameraPermission = requestCameraPermission,
                onDropped = {
                    session = null
                    error = "The scorer removed this camera from the match."
                },
                onExit = onExit,
            )

            else -> JoinPanel(
                preview = preview,
                busy = busy,
                error = error,
                onJoin = {
                    val code = initialCode ?: return@JoinPanel
                    busy = true
                    error = null
                    scope.launch {
                        runCatching { repo.claim(code, android.os.Build.MODEL ?: "Camera phone") }
                            .onSuccess { session = it }
                            .onFailure { error = it.message ?: "Couldn't join the match." }
                        busy = false
                    }
                },
                onExit = onExit,
            )
        }
    }
}

/** What you are about to join, before you join it. */
@Composable
private fun JoinPanel(
    preview: PairingPreview?,
    busy: Boolean,
    error: String?,
    onJoin: () -> Unit,
    onExit: () -> Unit,
) {
    Column(
        Modifier
            .fillMaxSize()
            .statusBarsPadding()
            .navigationBarsPadding()
            .displayCutoutPadding()
            /*
             * Scrolls, because this screen is now reachable sideways.
             *
             * Everything below — the mark, the headline, the role card, the disclaimer and
             * two buttons — needs more height than a phone has in landscape. Centred and
             * unscrollable, the bottom of it simply did not exist: the join button was off
             * the screen for anybody who scanned the QR with their phone already turned,
             * which is the natural way to hold a phone you are about to film with.
             *
             * Centre arrangement still holds while it fits, which is the portrait case.
             */
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 26.dp, vertical = 20.dp),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        // Signed. Somebody who scanned a stranger's QR code is entitled to see whose
        // software just opened on their phone, and a screen with no name on it is how
        // anonymous software looks.
        Staged(0) {
            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Image(
                    painter = painterResource(R.drawable.haraan_logo_white),
                    contentDescription = "Haraan",
                    contentScale = ContentScale.Fit,
                    modifier = Modifier.height(19.dp),
                )
                Spacer(Modifier.weight(1f))
                Box(
                    Modifier
                        .clip(RoundedCornerShape(999.dp))
                        .background(Accent.copy(alpha = 0.16f))
                        .border(1.dp, Accent.copy(alpha = 0.35f), RoundedCornerShape(999.dp))
                        .padding(horizontal = 11.dp, vertical = 5.dp),
                ) {
                    Text(
                        "MATCH CAMERA",
                        color = Accent,
                        fontSize = 9.5.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 1.1.sp,
                    )
                }
            }
        }

        Spacer(Modifier.height(34.dp))
        // The brackets close on OUR mark. That is the whole identity move on this screen:
        // the camera idea and the brand in one object, instead of a stock glowing lens.
        Staged(1) {
            Box(contentAlignment = Alignment.Center) {
                LensMark()
                Image(
                    painter = painterResource(R.drawable.ic_haraan_ribbon_mark),
                    contentDescription = null,
                    modifier = Modifier.height(40.dp),
                )
            }
        }
        Spacer(Modifier.height(28.dp))

        Staged(2) {
            Text(
                "Join as match camera",
                color = Ink,
                fontSize = 30.sp,
                // The app's display face, the same one the scorer sets a total in. A
                // headline in the system font is a headline that belongs to no product.
                fontFamily = ArchivoDisplay,
                letterSpacing = (-0.9).sp,
                textAlign = TextAlign.Center,
            )
        }

        when {
            error != null -> {
                Spacer(Modifier.height(14.dp))
                Staged(3) {
                    Text(error, color = Rec, fontSize = 15.sp, lineHeight = 21.sp, textAlign = TextAlign.Center)
                }
            }

            preview == null -> {
                Spacer(Modifier.height(16.dp))
                Staged(3) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        CircularProgressIndicator(
                            color = Accent,
                            strokeWidth = 2.dp,
                            modifier = Modifier.size(15.dp),
                        )
                        Spacer(Modifier.width(10.dp))
                        Text("Checking the code…", color = Ink.copy(alpha = 0.65f), fontSize = 14.5.sp)
                    }
                }
            }

            else -> {
                Spacer(Modifier.height(10.dp))
                Staged(3) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(
                            preview.matchTitle,
                            color = Ink.copy(alpha = 0.92f),
                            fontSize = 16.sp,
                            fontWeight = FontWeight.SemiBold,
                            textAlign = TextAlign.Center,
                        )
                        if (preview.venue.isNotBlank()) {
                            Spacer(Modifier.height(5.dp))
                            Text(
                                preview.venue,
                                color = Ink.copy(alpha = 0.45f),
                                fontSize = 13.5.sp,
                                textAlign = TextAlign.Center,
                            )
                        }
                    }
                }

                Spacer(Modifier.height(26.dp))
                Staged(4) {
                    Column(
                        Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(20.dp))
                            .background(Panel.copy(alpha = 0.75f))
                            // A lit top edge and a hairline: the difference between a
                            // raised surface and a lighter rectangle.
                            .border(
                                1.dp,
                                Brush.verticalGradient(
                                    listOf(Color.White.copy(alpha = 0.12f), Color.White.copy(alpha = 0.03f)),
                                ),
                                RoundedCornerShape(20.dp),
                            )
                            .padding(20.dp),
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            // The same short accent rule the profile's career cards use.
                            // Small consistencies like this are what make two screens
                            // look like one product.
                            Box(
                                Modifier
                                    .width(3.dp)
                                    .height(12.dp)
                                    .clip(RoundedCornerShape(2.dp))
                                    .background(Accent),
                            )
                            Spacer(Modifier.width(9.dp))
                            Text(
                                "THIS PHONE BECOMES",
                                color = Ink.copy(alpha = 0.42f),
                                fontSize = 10.sp,
                                fontWeight = FontWeight.Bold,
                                letterSpacing = 1.1.sp,
                            )
                        }
                        Spacer(Modifier.height(9.dp))
                        Text(
                            preview.roleLabel,
                            color = Accent,
                            fontSize = 20.sp,
                            fontWeight = FontWeight.ExtraBold,
                            letterSpacing = (-0.4).sp,
                        )
                        Spacer(Modifier.height(11.dp))
                        Text(
                            preview.role.blurb,
                            color = Ink.copy(alpha = 0.62f),
                            fontSize = 13.5.sp,
                            lineHeight = 20.sp,
                        )
                    }
                }

                Spacer(Modifier.height(16.dp))
                Staged(5) {
                    Text(
                        "Records a short clip around each delivery and sends it to the scorer. " +
                            "It does not decide anything.",
                        color = Ink.copy(alpha = 0.38f),
                        fontSize = 12.5.sp,
                        lineHeight = 18.sp,
                        textAlign = TextAlign.Center,
                    )
                }
            }
        }

        Spacer(Modifier.height(30.dp))
        if (preview != null && error == null) {
            Staged(6) {
                PrimaryButton(if (busy) "Joining…" else "Join this match", enabled = !busy, onClick = onJoin)
            }
            Spacer(Modifier.height(12.dp))
        }
        Staged(7) { GhostButton("Close", onExit) }
    }
}

/**
 * Filming.
 *
 * The button is the whole interface: hold-free, one tap starts an eight-second capture
 * that ends itself, because a scorer shouting "record!" across a ground cannot also
 * tell this phone when to stop.
 */
@Composable
private fun CameraMode(
    session: CameraSession,
    repo: CameraDeviceRepository,
    hasCameraPermission: () -> Boolean,
    requestCameraPermission: ((Boolean) -> Unit) -> Unit,
    onDropped: () -> Unit,
    onExit: () -> Unit,
) {
    val ctx = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val scope = rememberCoroutineScope()
    /*
     * THE ONLY CHANNEL THIS SCREEN HAS.
     *
     * Every other screen in the app can rely on being looked at. This one cannot: the
     * person holding it is watching a bowler run in, and a status line that changes
     * silently changes for nobody. So each thing that happens on its own - a clip ending,
     * an upload landing, a clip refused, the match going quiet - is also said in the hand.
     */
    val view = LocalView.current
    /*
     * WHICH WAY THE PHONE IS BEING HELD.
     *
     * From the configuration rather than BoxWithConstraints: this screen's state changes
     * about thirty times a second while a ball is in the air, and every one of those
     * writes would drag a subcomposition behind it. Orientation changes roughly never.
     */
    val configuration = LocalConfiguration.current
    val landscape = configuration.orientation == Configuration.ORIENTATION_LANDSCAPE

    var granted by remember { mutableStateOf(hasCameraPermission()) }
    var recording by remember { mutableStateOf(false) }

    /*
     * THE PERSISTENT REVIEW CLIP QUEUE.
     *
     * Application-scoped singleton:
     * - Exactly one instance per application process, surviving screen recreation.
     * - Clips are stored durably on internal disk (context.filesDir/review_clips/).
     * - The shutter re-arms immediately so the operator can film consecutive deliveries.
     * - Drains in the background with exponential backoff on patchy cellular signal.
     * - Deletes ONLY upon confirmed 2xx landing with the scorer.
     */
    val uploadQueue = remember { ClipUploadQueue.getInstance(ctx) }
    val queueStatus by uploadQueue.status.collectAsState()

    DisposableEffect(uploadQueue) {
        val listener = object : ClipUploadQueue.UploadListener {
            override fun onUploadSuccess(overBall: String?) {
                view.performHapticFeedback(Feel.COMMIT)
            }

            override fun onUploadPermanentFailure(message: String) {
                view.performHapticFeedback(Feel.REMOVE)
            }
        }
        uploadQueue.addListener(listener)
        onDispose {
            uploadQueue.removeListener(listener)
        }
    }

    // Cleared on the next tap: a refusal is about the clip just filmed, not a mode the
    // camera is stuck in.
    var uploadError by remember { mutableStateOf<String?>(null) }
    var score by remember { mutableStateOf("") }
    var overs by remember { mutableStateOf("") }
    var live by remember { mutableStateOf(true) }

    val executor = remember { Executors.newSingleThreadExecutor() }
    var videoCapture by remember { mutableStateOf<VideoCapture<Recorder>?>(null) }
    // Held only so its target rotation can be corrected on a turn — see below.
    var analysisUseCase by remember { mutableStateOf<ImageAnalysis?>(null) }


    /*
     * THE VISION ENGINE.
     *
     * Its own single thread, so analysis can never sit on the recorder's. Held for the
     * lifetime of the screen and reset per delivery rather than rebuilt, because the
     * native allocation is the expensive part.
     */
    val analysisExecutor = remember { Executors.newSingleThreadExecutor() }
    val vision = remember { OpenCvBallTracker() }

    /*
     * HOW HOT THE PHONE IS.
     *
     * A phone doing all of this at once — screen pinned on in sunlight, 1080p encoder,
     * continuous OpenCV — gets hot, and Android's answer is to quietly slow it down. What
     * that looks like at a ground is a camera that filmed the first six overs and then
     * started dropping frames, and nobody can tell that from a bad clip or a bad angle.
     *
     * So the app makes the choice itself, out loud, and makes it in the order the feature
     * is worth: vision stops, filming continues. The clip is what a review is built on;
     * the trail is a nice thing to watch while it is being filmed.
     *
     * MODERATE is the first level at which Android is actually throttling rather than
     * merely warm, so it is the first level worth reacting to. Below API 29 there is
     * nothing to listen to and this stays false — the same behaviour as before.
     */
    var thermalThrottled by remember { mutableStateOf(false) }
    DisposableEffect(ctx) {
        // minSdk here is 24, so most of the fleet this ships to has no thermal API at all.
        // Those phones behave exactly as they did before: vision runs until the operator
        // stops it. Nothing about this feature depends on the listener existing.
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.Q) return@DisposableEffect onDispose { }
        val power = ctx.getSystemService(Context.POWER_SERVICE) as? PowerManager
            ?: return@DisposableEffect onDispose { }

        val hot = { status: Int -> status >= PowerManager.THERMAL_STATUS_MODERATE }
        // Whatever it is right now: a phone handed over already warm should not have to
        // get hotter still before the app notices.
        thermalThrottled = hot(power.currentThermalStatus)
        val listener = PowerManager.OnThermalStatusChangedListener { status ->
            thermalThrottled = hot(status)
        }
        power.addThermalStatusListener(analysisExecutor, listener)
        onDispose { runCatching { power.removeThermalStatusListener(listener) } }
    }
    // Only counted while a delivery is actually being recorded — the camera runs the whole
    // time somebody is holding the phone, and tracking the warm-up is noise.
    var trackedPoints by remember { mutableStateOf(0) }
    var trackingLive by remember { mutableStateOf(false) }
    // What the detector is seeing, for the overlay. Short on purpose: a long trail smears
    // into a blur and hides the jitter that tells you it is tracking the wrong thing.
    var ballTrail by remember { mutableStateOf<List<com.haraan.app.vision.BallSighting>>(emptyList()) }
    var latestBall by remember { mutableStateOf<com.haraan.app.vision.BallSighting?>(null) }

    /*
     * THE SAME JUDGEMENT AS THE TRAIL'S COLOUR, IN A WORD.
     *
     * Colour on its own is a signal some people cannot read and nobody can read well in
     * direct sun on a cheap screen at arm's length — which is every condition this phone
     * is held in. The tracker's own [TrackQuality] says the same thing about the track as
     * a whole, so it is worth the four characters it costs on a line that is already there.
     */
    var trackQuality by remember { mutableStateOf(TrackQuality.UNCERTAIN) }

    /*
     * WHERE THE LAST DELIVERY PITCHED.
     *
     * The only thing this phone can say in metres and be right about, so it is the only
     * number that goes on the screen. Worked out once when the clip ends rather than per
     * frame: it needs the whole track, and during the delivery there is nothing to say.
     *
     * Kept until the next tap, because it is also the plainest evidence the operator has
     * that the calibration is good — a marker sitting where the ball actually landed means
     * the corners are right, and one out in the covers means they are not.
     */
    var lastBounce by remember { mutableStateOf<com.haraan.app.vision.Bounce?>(null) }
    // Off by default: it is an aiming aid, not decoration, and it is in the way once the
    // phone is set. Persisted for the session so it does not reappear every delivery.
    var showGuide by remember { mutableStateOf(true) }

    /*
     * THE SHAPE OF THE PICTURE THE ANALYSER IS SEEING, upright.
     *
     * Every overlay here is drawn from coordinates normalised inside the ANALYSIS frame,
     * and until now they were painted across the whole viewfinder as though the two were
     * the same rectangle. They are not: the preview is letterboxed into a view of whatever
     * shape the phone happens to be, so a point at 0.9 across the analysis frame was landing
     * somewhere else entirely on screen — worst at the edges, which is precisely where a
     * ball is at release and where a crease corner is.
     *
     * Written from the analyser thread like the counters beside it, and read when drawing.
     */
    var uprightAspect by remember { mutableStateOf(0f) }

    /*
     * THE PITCH ITSELF, found rather than drawn.
     *
     * A static outline can only tell somebody roughly where to point. Once the creases are
     * located the guide stops being a template and becomes the thing a measurement stands
     * on: the same four corners are what a homography is solved from.
     *
     * So a corridor drawn on a DETECTED pitch is not decoration. It is the only visible
     * evidence that the phone understands what it is looking at - if it sits crooked on the
     * surface, the calibration behind it is wrong, and that is worth seeing before a review
     * is ever asked for.
     */
    val pitchDetector = remember { com.haraan.app.vision.OpenCvPitchDetector() }
    var pitchQuad by remember { mutableStateOf<com.haraan.app.vision.PitchQuad?>(null) }

    /*
     * And when it cannot find it: four taps.
     *
     * Faded paint, a wet outfield, an indoor net with no creases at all - detection will
     * fail on real grounds, and a feature that only works on a good pitch is not a feature.
     * Somebody who can see the ground can always point at its corners.
     *
     * A tapped quad OUTRANKS a detected one. A person looking at the pitch has better
     * information than a Hough transform, and the [QuadSource] recorded on it means every
     * measurement downstream can still say which of the two it stood on.
     */
    var tappedCorners by remember { mutableStateOf<List<com.haraan.app.vision.Point2>>(emptyList()) }
    var tapping by remember { mutableStateOf(false) }

    /*
     * THE WICKET, HELD ACROSS FRAMES.
     *
     * A pitch quad needs four painted crease corners, and on the grounds this app is used
     * on that paint is faded, mown off, or was never there. A wicket is never absent and is
     * exactly 0.2286 m across, which makes it the one landmark that can put a speed in
     * km/h and a projection in centimetres on a ground with no visible creases at all.
     *
     * Three pieces, and the order they run in matters:
     *
     *   [OpenCvCameraMotion] first, so the tripod's own settling is subtracted before
     *   anything is compared between frames.
     *
     *   [OpenCvStumpDetector] second, for one frame's opinion.
     *
     *   [WicketTracker] last, which is the only one of the three that ever says CONFIRMED.
     *   Nothing downstream reads the detector directly any more: a single frame's three
     *   aligned bars could be a bat, a pad and a boot, and the tracker is what makes them
     *   prove otherwise before a scale is taken from them.
     */
    val stumpDetector = remember { com.haraan.app.vision.OpenCvStumpDetector() }
    val cameraMotion = remember { com.haraan.app.vision.OpenCvCameraMotion() }
    val wicketTracker = remember { com.haraan.app.vision.WicketTracker() }
    var wicketLock by remember { mutableStateOf<com.haraan.app.vision.WicketLock?>(null) }
    var wicketDiagnostics by remember { mutableStateOf(wicketTracker.diagnostics()) }
    /** Throttle: panel recomposes at ~5 Hz rather than analysis rate. */
    var lastDiagnosticsMs = remember { 0L }
    var showAdminPanel by remember { mutableStateOf(false) }

    /** The wicket placed by hand, two taps at the base of the outer stumps. */
    var wicketTapping by remember { mutableStateOf(false) }
    var wicketTaps by remember { mutableStateOf<List<com.haraan.app.vision.Point2>>(emptyList()) }

    /*
     * The last delivery's numbers, worked out once when the clip ends.
     *
     * The same moment and the same reason as [lastBounce] beside it: the whole track is
     * needed, and during the delivery there is nothing to say. What is new is that the
     * wicket lock goes in with the quad, which is what lets a speed and a wicket projection
     * exist on a ground where no quad was ever found.
     */
    var lastMetrics by remember { mutableStateOf<com.haraan.app.vision.FlightMetrics?>(null) }

    /*
     * ROTATION, now that the Activity survives one.
     *
     * A use case's target rotation is fixed at bind time. A turn of the phone used to tear
     * this whole screen down and rebuild it, so the new orientation arrived with the new
     * binding and nobody had to think about this. Keeping the session alive across a turn
     * means nobody rebinds — and a recorder still aimed at the old rotation writes the
     * delivery sideways into the clip the scorer opens.
     *
     * The analyser's rotation matters as much and shows less: ImageInfo.rotationDegrees is
     * derived from it, and both engines now turn their frames by it. Left stale, it would
     * put the pitch outline and the ball trail back a quarter turn out of the picture —
     * precisely the failure the coordinate work removed.
     *
     * The located pitch goes with it. Those corners were found in the old orientation and
     * describe nothing in this one; dropping them costs a second of re-detection and
     * avoids drawing a confident outline somewhere there is no pitch. Corners set by hand
     * go too, for the same reason and with more certainty — a person tapped them at a
     * picture that is no longer on screen.
     */
    LaunchedEffect(configuration, videoCapture, analysisUseCase) {
        val rotation = view.display?.rotation ?: return@LaunchedEffect
        videoCapture?.targetRotation = rotation
        analysisUseCase?.targetRotation = rotation
        pitchDetector.reset()
        pitchQuad = null
        tappedCorners = emptyList()
        tapping = false
        /*
         * Auto-detected wicket is reset on rotation so it immediately re-detects upright
         * in the new orientation/aspect ratio without carrying stale rotated coordinates.
         * Manual locks placed by hand are preserved.
         */
        if (wicketLock?.source != com.haraan.app.vision.WicketLockSource.MANUAL) {
            wicketTracker.reset()
            wicketLock = null
            wicketDiagnostics = wicketTracker.diagnostics()
        }
        cameraMotion.reset()
        wicketTaps = emptyList()
        wicketTapping = false
    }

    DisposableEffect(Unit) {
        onDispose {
            vision.release()
            pitchDetector.release()
            stumpDetector.release()
            cameraMotion.release()
            analysisExecutor.shutdown()
        }
    }

    val analyzer = remember {
        /*
         * ONE BUFFER, REUSED.
         *
         * The luma plane was copied into a brand new ByteArray on every single frame —
         * a quarter of a megabyte, thirty times a second, most of it for frames neither
         * engine was going to look at. That is a garbage collector running flat out for
         * the length of a match inside a phone already holding a camera, an encoder and a
         * Hough transform, and heat is the only thing it produces.
         *
         * Declared in here rather than beside the other state on purpose: it belongs to
         * the analyzer, it is only ever touched on the single-threaded analysis executor,
         * and a `remember` in the composable body would hand each recomposition a fresh
         * one while the analyzer quietly kept the first.
         */
        var lumaScratch: ByteArray? = null

        /** The turn the previous frame arrived at, so a rotation can be told from a pan. */
        var lastTurn: Int? = null
        var idleAnalysisFrame = 0

        ImageAnalysis.Analyzer { image ->
            try {
                val plane = image.planes.getOrNull(0)
                if (plane != null) {
                    // A quarter turn swaps the picture's width and height. Both engines
                    // report inside the upright frame, so that is the shape to draw into.
                    // Free: read off the frame's metadata, not its pixels.
                    val turn = ((image.imageInfo.rotationDegrees % 360) + 360) % 360
                    val sideways = turn == 90 || turn == 270
                    val frameW = if (sideways) image.height else image.width
                    val frameH = if (sideways) image.width else image.height
                    if (frameH > 0) uprightAspect = frameW.toFloat() / frameH.toFloat()

                    /*
                     * IS THERE ANYTHING TO LOOK FOR?
                     *
                     * The camera runs from the moment somebody joins until they walk off
                     * the ground — through the innings break, through a bowler's long
                     * walk back, through an hour of nothing. The ball is in flight for
                     * maybe a second of each delivery, and the pitch is found once.
                     *
                     * Every frame outside those windows used to be copied out of the
                     * hardware buffer in full before the code got round to deciding it had
                     * no use for it. Deciding first is most of the thermal saving on this
                     * screen, and it costs nothing that was ever worth having.
                     */
                    /*
                     * THE TURN IS ANNOUNCED BEFORE THE FRAME IS LOOKED AT.
                     *
                     * A quarter turn rotates the whole scene, so every correspondence in it
                     * agrees with every other one and the motion estimator would fit a
                     * confident nonsense translation to the lot. The tracker maps its
                     * anchor through the turn exactly instead, and the estimator restarts
                     * its difference from this frame.
                     */
                    val previousTurn = lastTurn
                    if (previousTurn != null && previousTurn != turn) {
                        wicketTracker.onRotation((turn - previousTurn) / 90, uprightAspect)
                    }
                    lastTurn = turn

                    val trackingNow = trackingLive
                    val lookingForPitch = !trackingNow &&
                        pitchQuad == null &&
                        // Corners set by hand outrank a detected quad, so once there are
                        // four of them the Hough transform is looking for an answer that
                        // has already been given.
                        tappedCorners.size < 4
                    // Vision yields to heat. Filming is the product; this is bolted to the
                    // side of it, and a phone that throttles its encoder mid-delivery has
                    // lost the thing the operator is actually standing there to do.
                    /*
                     * Unlike the pitch, the wicket is worth looking for on every idle
                     * frame, not only until it is found once.
                     *
                     * A quad is four corners of a painted rectangle and does not change; a
                     * wicket lock is a live claim with a state, and the whole value of the
                     * state machine is that it keeps re-deciding — re-acquiring after the
                     * batter walks across it, dropping when the phone is repointed. A lock
                     * that stopped being checked the moment it was found would be the
                     * twelve-frame hold this replaced, wearing better clothes.
                     */
                    val lookingForWicket = !trackingNow && stumpDetector.available
                    if (thermalThrottled || (!trackingNow && !lookingForPitch && !lookingForWicket)) {
                        return@Analyzer
                    }

                    val buffer = plane.buffer
                    buffer.rewind()
                    val needed = buffer.remaining()
                    val bytes = lumaScratch?.takeIf { it.size == needed }
                        ?: ByteArray(needed).also { lumaScratch = it }
                    buffer.get(bytes)

                    /*
                     * One frame, one job.
                     *
                     * The two detectors never run on the same frame. Finding the pitch is a
                     * Hough transform over the whole image and is much the heavier of the
                     * two - but a pitch does not move while a ball is in the air, and the
                     * ball is the measurement that cannot be redone. Spending analysis time
                     * re-finding a stationary rectangle mid-delivery would cost frames of
                     * the one thing there is only one chance to see.
                     */
                    /*
                     * CAMERA MOTION RUNS ON EVERY ANALYSED FRAME, INCLUDING DURING A
                     * DELIVERY, and it is the only thing here that does.
                     *
                     * It is cheap — a hundred corners followed at 320 wide, a couple of
                     * milliseconds — and it is what keeps the wicket lock attached to the
                     * ground while the heavy detector is switched off for the delivery. A
                     * lock that stopped being carried the moment the ball was bowled would
                     * be stale at exactly the moment its scale is used.
                     */
                    val cameraMove = cameraMotion.onFrame(
                        luma = bytes,
                        width = image.width,
                        height = image.height,
                        rowStride = plane.rowStride,
                        rotationDegrees = image.imageInfo.rotationDegrees,
                    )

                    if (trackingNow) {
                        /*
                         * The wicket is CARRIED, not missed.
                         *
                         * The detector is deliberately not run during a delivery — see the
                         * one-frame-one-job note below — and handing the tracker a null
                         * sighting would be telling it the wicket was looked for and not
                         * found, which would drop a perfectly good lock eight frames into
                         * every single ball.
                         */
                        wicketLock = wicketTracker.carry(cameraMove, uprightAspect)

                        // The camera's own monotonic clock, never the UI clock: ball
                        // motion timing has to come from when the sensor saw it.
                        val sighting = vision.onFrame(
                            luma = bytes,
                            width = image.width,
                            height = image.height,
                            rowStride = plane.rowStride,
                            // The same turn the pitch detector is given. Without it the
                            // trail was drawn in the sensor's frame and the corridor in the
                            // viewer's, a quarter turn apart on every portrait phone.
                            rotationDegrees = image.imageInfo.rotationDegrees,
                            timestampMs = image.imageInfo.timestamp / 1_000_000,
                        )
                        if (sighting != null) {
                            trackedPoints = vision.track().size
                            latestBall = sighting
                            ballTrail = vision.track().takeLast(CAMERA_TRAIL_POINTS)
                            trackQuality = vision.quality()
                        }
                    } else {
                        // Pitch detector is throttled to once every 4 frames so the Hough
                        // transform does not starve CPU from stump detection. Stump detector
                        // runs on every frame at full 25+ FPS for instant lock-on.
                        idleAnalysisFrame++
                        if (lookingForPitch && (idleAnalysisFrame % 4 == 0)) {
                            pitchDetector.detect(
                                luma = bytes,
                                width = image.width,
                                height = image.height,
                                rowStride = plane.rowStride,
                                rotationDegrees = image.imageInfo.rotationDegrees,
                            )?.let { pitchQuad = it }
                        }

                        if (lookingForWicket) {
                            val wicket = stumpDetector.detectWicket(
                                luma = bytes,
                                width = image.width,
                                height = image.height,
                                rowStride = plane.rowStride,
                                rotationDegrees = image.imageInfo.rotationDegrees,
                                creases = pitchDetector.creases(),
                            )
                            wicketLock = wicketTracker.onFrame(
                                sighting = wicket,
                                motion = cameraMove,
                                frameAspect = uprightAspect,
                                timestampMs = image.imageInfo.timestamp / 1_000_000,
                            )
                            val now = System.currentTimeMillis()
                            if (now - lastDiagnosticsMs >= 200L) {
                                wicketDiagnostics = wicketTracker.diagnostics()
                                lastDiagnosticsMs = now
                            }
                        }
                    }
                }
            } catch (_: Throwable) {
                // Never let analysis break the camera.
            } finally {
                image.close()
            }
        }
    }
    var activeRecording by remember { mutableStateOf<Recording?>(null) }

    DisposableEffect(Unit) { onDispose { executor.shutdown() } }

    LaunchedEffect(Unit) { if (!granted) requestCameraPermission { granted = it } }

    // Check in on a cadence matched to cricket, not to a chat app. Losing the pairing is
    // reported here rather than discovered when an upload fails.
    LaunchedEffect(session.sessionToken) {
        /*
         * A missed beat is not a lost pairing.
         *
         * This used to drop the camera out of the match on the FIRST null — so one blip
         * on ground Wi-Fi, or one slow response from a backend still warming up, ended a
         * session that was perfectly healthy and sent somebody back to the scorer for a
         * new pairing code.
         *
         * Three consecutive misses at a twenty-second cadence is about a minute of real
         * silence, which is long enough to be a genuine disconnection and short enough
         * that the scorer sees it before wondering why no clips are arriving. The screen
         * shows "reconnecting" in between rather than pretending nothing is wrong.
         */
        if (session.sessionToken == "debug_token") {
            live = true
            while (true) {
                delay(30_000)
            }
        }
        var missed = 0
        while (true) {
            val beat = repo.heartbeat(session.sessionToken)
            if (beat == null) {
                missed++
                // Said in the hand on the way down, once. Losing the scorer is the one
                // thing that happens on this screen with no gesture behind it, and the
                // operator has no reason to be looking when it does.
                if (live) view.performHapticFeedback(Feel.REMOVE)
                live = false
                if (missed >= MAX_MISSED_HEARTBEATS) {
                    onDropped()
                    return@LaunchedEffect
                }
                // Back off a little before retrying, but well inside the drop window.
                delay(5_000)
                continue
            }
            // And once on the way back, distinct from the drop: a tick, not a rejection.
            if (!live) view.performHapticFeedback(Feel.TICK)
            missed = 0
            live = true
            score = beat.score
            overs = beat.overs
            delay(20_000)
        }
    }

    Box(Modifier.fillMaxSize().background(Color.Black)) {
        if (granted) {
            AndroidView(
                modifier = Modifier.fillMaxSize(),
                factory = { context ->
                    PreviewView(context).also { view ->
                        /*
                         * FIT, not the default FILL.
                         *
                         * FILL_CENTER crops whatever does not fit the view, so the picture
                         * on screen was a centre crop of the picture the analyser measured
                         * and the recorder saved. Three different rectangles, one set of
                         * coordinates drawn across all of them. Letterboxing costs a band
                         * of black and buys an overlay that lands where the ball is - and
                         * it also stops the viewfinder from promising a wider shot than
                         * the clip the scorer will actually receive.
                         */
                        view.scaleType = PreviewView.ScaleType.FIT_CENTER
                        bindCamera(
                            context,
                            view,
                            lifecycleOwner,
                            analysisExecutor,
                            analyzer,
                        ) { capture, analysis ->
                            videoCapture = capture
                            analysisUseCase = analysis
                        }
                    }
                },
            )
        } else {
            Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                Text(
                    "Camera access is needed to film the match.",
                    color = Ink,
                    fontSize = 15.sp,
                    modifier = Modifier.padding(32.dp),
                )
            }
        }

        // Status, top: which role this phone is playing and whether the match still
        // knows about it.
        // THE AIMING GUIDE.
        //
        // The person holding this phone is the only one who can fix a bad angle, and by
        // the time a scorer opens the clip they are three overs away. So the frame says
        // what a useful shot looks like: stumps upright in the lower middle, the pitch
        // running away up the frame.
        //
        // These are the same four reference points a homography will need later, so the
        // habit this builds is the habit calibration will depend on.
        val tappedQuad = remember(tappedCorners) {
            if (tappedCorners.size < 4) {
                null
            } else {
                com.haraan.app.vision.PitchQuad(
                    corners = tappedCorners,
                    source = com.haraan.app.vision.QuadSource.TAPPED,
                    confidence = 1f,
                ).takeIf { it.isPlausible() }
            }
        }
        val found = tappedQuad ?: pitchQuad
        if (showGuide && granted && found != null) {
            /*
             * FOUND. The guide is now the pitch, not a picture of one: drawn from the
             * corners the detector located, in that pitch's own perspective.
             *
             * The corridor inside it is placed in METRES and pushed back through the
             * homography. Drawn in screen space it would be a trapezium that merely looks
             * about right; drawn this way it is visibly wrong the moment the calibration is,
             * which is the only reason to draw it at all.
             */
            Canvas(Modifier.fillMaxSize()) {
                val frame = frameRect(size.width, size.height, uprightAspect)
                fun px(point: com.haraan.app.vision.Point2) =
                    frame.at(point.x.toFloat(), point.y.toFloat())

                fun quadPath(points: List<Offset>) = Path().apply {
                    moveTo(points[0].x, points[0].y)
                    points.drop(1).forEach { lineTo(it.x, it.y) }
                    close()
                }

                drawPath(
                    quadPath(found.corners.map { px(it) }),
                    Color(0xFF4ADE80).copy(alpha = 0.8f),
                    style = Stroke(width = 2.5.dp.toPx(), cap = StrokeCap.Round),
                )

                found.toImage()?.let { toImage ->
                    val half = com.haraan.app.vision.PitchGeometry.RETURN_CREASE_HALF_WIDTH_M * 0.35
                    // From the quad's own calibration, so this cannot disagree with the
                    // corners it is being drawn inside — or with the end it was tapped at.
                    val near = found.calibration[0].y
                    val far = found.calibration[2].y

                    val band = listOf(
                        com.haraan.app.vision.Point2(-half, near),
                        com.haraan.app.vision.Point2(half, near),
                        com.haraan.app.vision.Point2(half, far),
                        com.haraan.app.vision.Point2(-half, far),
                    ).map { px(toImage.map(it)) }

                    // A corner on the horizon maps to NaN by design. Skip the band rather
                    // than let a broken calibration paint something confident.
                    if (band.none { it.x.isNaN() || it.y.isNaN() }) {
                        drawPath(quadPath(band), Color(0xFF6E9BF5).copy(alpha = 0.20f))
                    }
                }
            }
        } else if (showGuide && granted) {
            Canvas(Modifier.fillMaxSize()) {
                val guide = Color.White.copy(alpha = 0.34f)
                val stroke = Stroke(width = 2.dp.toPx(), cap = StrokeCap.Round)

                // Proportioned against the PICTURE, like everything else drawn over it:
                // an aiming guide that overhangs the letterbox is telling somebody to put
                // the stumps where the camera cannot see.
                val frame = frameRect(size.width, size.height, uprightAspect)

                // The pitch, narrowing with distance.
                // Proportioned for a phone on a tripod behind the bowler's arm. The first
                // pass ran the pitch from 40% to 86% of the height, which read as a tall
                // funnel and put the near crease directly behind the record button - the
                // one part of the screen a thumb is always covering.
                val nearY = frame.top + frame.height * 0.74f
                val farY = frame.top + frame.height * 0.36f
                val nearHalf = frame.width * 0.32f
                val farHalf = frame.width * 0.075f
                val cx = frame.left + frame.width / 2f

                drawPath(
                    Path().apply {
                        moveTo(cx - nearHalf, nearY)
                        lineTo(cx - farHalf, farY)
                        moveTo(cx + nearHalf, nearY)
                        lineTo(cx + farHalf, farY)
                    },
                    guide,
                    style = stroke,
                )

                // Popping crease, near end.
                drawLine(
                    guide,
                    Offset(cx - nearHalf, nearY),
                    Offset(cx + nearHalf, nearY),
                    strokeWidth = 2.dp.toPx(),
                    cap = StrokeCap.Round,
                )
                // And far end, where the stumps should sit.
                drawLine(
                    guide,
                    Offset(cx - farHalf, farY),
                    Offset(cx + farHalf, farY),
                    strokeWidth = 2.dp.toPx(),
                    cap = StrokeCap.Round,
                )

                // Three stumps at the far crease — the thing to line the phone up on.
                // Tall enough to aim at. The stumps are the thing being lined up, so they
                // have to be the most legible part of the guide, not a detail.
                val stumpH = frame.height * 0.075f
                listOf(-1, 0, 1).forEach { i ->
                    val x = cx + i * (farHalf * 0.62f)
                    drawLine(
                        guide,
                        Offset(x, farY),
                        Offset(x, farY - stumpH),
                        strokeWidth = 2.dp.toPx(),
                        cap = StrokeCap.Round,
                    )
                }
            }
        }

        // WHAT THE DETECTOR IS SEEING, live.
        //
        // This existed only in the debug field-test mode, which is the wrong place for it:
        // the operator is the one who can correct the aim, and without this they cannot
        // tell the tracker is following a fielder's shirt until somebody reviews the clip
        // long afterwards. Observed points only — nothing here is interpolated.
        if (granted && (ballTrail.isNotEmpty() || latestBall != null)) {
            Canvas(Modifier.fillMaxSize()) {
                val frame = frameRect(size.width, size.height, uprightAspect)

                /*
                 * THE TRAIL, AGED.
                 *
                 * Thirty points in one flat colour and one flat width is a scatter plot;
                 * the eye has to work out which end is the ball. Fading and thinning with
                 * age makes the direction of travel the first thing read instead of
                 * something to be deduced, which matters because the operator is glancing
                 * at this between watching a bowler run in.
                 *
                 * Drawn segment by segment, since one Path cannot vary alpha along itself.
                 *
                 * STRAIGHT segments, deliberately - no smoothing, no spline. The jitter in
                 * this line is the signal: a trail that shivers is a tracker following a
                 * fielder's shirt, and a curve fitted through the points would tidy away
                 * exactly the evidence this overlay exists to show. The same reason the
                 * tracker itself interpolates nothing.
                 *
                 * AND COLOURED BY HOW WELL EACH POINT BEHAVED.
                 *
                 * Age says which end is the ball. Colour says whether to believe the line
                 * at all — see [trackColour]. A tracker locked onto the ball draws in blue;
                 * one picking round-ish bits of a moving fielder draws the same shape in
                 * amber, and the difference is visible from the boundary without reading a
                 * word. That is the one question this overlay exists to answer, and until
                 * now it was answered by counting dots.
                 */
                fun age(i: Int) = i.toFloat() / (ballTrail.size - 1).coerceAtLeast(1)
                fun faded(point: com.haraan.app.vision.BallSighting, age: Float) =
                    trackColour(point.trackingConfidence)
                        .copy(alpha = TRAIL_FADE_FROM + (TRAIL_FADE_TO - TRAIL_FADE_FROM) * age)

                for (i in 1 until ballTrail.size) {
                    // Each segment takes the colour of the point it arrives at: it draws
                    // the step that reached there, so it is that step being judged.
                    drawLine(
                        faded(ballTrail[i], age(i)),
                        frame.at(ballTrail[i - 1].x, ballTrail[i - 1].y),
                        frame.at(ballTrail[i].x, ballTrail[i].y),
                        strokeWidth = (1.2f + 1.6f * age(i)).dp.toPx(),
                        cap = StrokeCap.Round,
                    )
                }
                ballTrail.forEachIndexed { i, point ->
                    drawCircle(
                        faded(point, age(i)),
                        radius = (1.6f + 1.9f * age(i)).dp.toPx(),
                        center = frame.at(point.x, point.y),
                    )
                }
                latestBall?.let { point ->
                    val o = frame.at(point.x, point.y)
                    // On the same ramp as the rest, not a fixed green. A green head on an
                    // amber trail reads as "found it" at the exact moment the tracker is
                    // least sure, which is the wrong thing to say the loudest. The white
                    // ring is what marks this as the newest point.
                    drawCircle(trackColour(point.trackingConfidence), radius = 8.dp.toPx(), center = o)
                    drawCircle(
                        Color.White,
                        radius = 13.dp.toPx(),
                        center = o,
                        style = Stroke(width = 1.5.dp.toPx()),
                    )
                }
            }
        }

        /*
         * THE WICKET, DRAWN FROM THE LOCK.
         *
         * Not from the detector's newest sighting, which is the change worth being explicit
         * about. A sighting is one frame's opinion, and three bars that happen to line up
         * for one frame — a bat, a pad and a boot; three palings past a gap in the
         * sightscreen — used to be painted with exactly the confidence of a wicket seen for
         * a minute. The lock has survived multi-frame confirmation with the phone's own
         * movement subtracted, and when it is coasting rather than seen it is drawn dashed
         * and fading, which is a picture nobody can mistake for a detection.
         *
         * Above the trail and below the bounce marker: it is the landmark the trail is
         * measured against, and the bounce is the one thing on this screen with a position
         * in metres.
         */
        if (granted) {
            wicketLock?.let { lock ->
                Canvas(Modifier.fillMaxSize()) {
                    drawWicketLock(
                        lock = lock,
                        box = com.haraan.app.vision.FrameBox.letterbox(
                            size.width,
                            size.height,
                            uprightAspect,
                        ),
                        density = this,
                    )
                }
            }
        }

        /*
         * PLACING THE WICKET BY HAND.
         *
         * Two taps, at the base of each outer stump. The detector will fail on a wicket in
         * shadow, behind a batter, or made of a stone with a bail-coloured chip in it, and a
         * calibration that only works when the detector agrees is not a calibration. A
         * person who can see the wicket can always point at it.
         *
         * Its own overlay and its own taps rather than a mode on the corner flow, because
         * the two are answering different questions and a shared "tapping" flag would let an
         * interrupted corner sequence finish as a wicket.
         */
        if (wicketTapping && granted) {
            Box(
                Modifier
                    .fillMaxSize()
                    .pointerInput(uprightAspect) {
                        detectTapGestures { offset ->
                            val box = com.haraan.app.vision.FrameBox.letterbox(
                                size.width.toFloat(),
                                size.height.toFloat(),
                                uprightAspect,
                            )
                            // A tap on a letterbox bar is outside the analysed frame, where
                            // no detector can ever agree with it.
                            if (!box.contains(offset)) return@detectTapGestures
                            val next = wicketTaps + box.toFrame(offset)
                            wicketTaps = next
                            if (next.size >= 2) {
                                wicketTracker.lockManually(
                                    baseLeft = next[0],
                                    baseRight = next[1],
                                    // The optional third tap is the top of a stump, and it
                                    // buys the vertical scale. The two base points cannot
                                    // give it at any price: they are collinear, and a line
                                    // fixes distances only along itself.
                                    top = next.getOrNull(2),
                                    kind = com.haraan.app.vision.WicketKind.STUMPS,
                                    frameAspect = uprightAspect,
                                )
                                wicketLock = wicketTracker.lock()
                                wicketDiagnostics = wicketTracker.diagnostics()
                            }
                            // Told by feel, because the person doing this is looking at a
                            // wicket rather than at the phone.
                            view.performHapticFeedback(if (next.size >= 2) Feel.COMMIT else Feel.TICK)
                            if (next.size >= 3) {
                                wicketTapping = false
                                wicketTaps = emptyList()
                            }
                        }
                    },
            ) {
                Canvas(Modifier.fillMaxSize()) {
                    val box = com.haraan.app.vision.FrameBox.letterbox(
                        size.width,
                        size.height,
                        uprightAspect,
                    )
                    wicketTaps.forEachIndexed { i, point ->
                        val o = box.toView(point)
                        drawCircle(Color(0xFFBDD3FF), radius = 7.dp.toPx(), center = o)
                        drawCircle(
                            Color.Black.copy(alpha = 0.6f),
                            radius = 7.dp.toPx(),
                            center = o,
                            style = Stroke(width = 1.5.dp.toPx()),
                        )
                        if (i > 0) {
                            drawLine(
                                Color(0xFFBDD3FF).copy(alpha = 0.75f),
                                box.toView(wicketTaps[i - 1]),
                                o,
                                strokeWidth = 2.dp.toPx(),
                            )
                        }
                    }
                }
            }
        }

        /*
         * WHERE IT PITCHED.
         *
         * Drawn flat on the grass rather than as a floating dot: a ring and a cross, at the
         * one point in the whole delivery the app is entitled to say it knows the position
         * of. If it sits where the ball landed, the calibration is good; if it sits in the
         * covers, it is not — which is worth more to the operator than the number is.
         */
        lastBounce?.let { bounce ->
            if (granted) {
                Canvas(Modifier.fillMaxSize()) {
                    val frame = frameRect(size.width, size.height, uprightAspect)
                    val at = frame.at(bounce.image.x.toFloat(), bounce.image.y.toFloat())
                    val radius = 11.dp.toPx()

                    drawCircle(
                        Color.Black.copy(alpha = 0.45f),
                        radius = radius + 1.5.dp.toPx(),
                        center = at,
                        style = Stroke(width = 3.5.dp.toPx()),
                    )
                    drawCircle(
                        BounceMark,
                        radius = radius,
                        center = at,
                        style = Stroke(width = 2.dp.toPx()),
                    )
                    val arm = radius * 0.55f
                    drawLine(
                        BounceMark,
                        Offset(at.x - arm, at.y),
                        Offset(at.x + arm, at.y),
                        strokeWidth = 2.dp.toPx(),
                        cap = StrokeCap.Round,
                    )
                    drawLine(
                        BounceMark,
                        Offset(at.x, at.y - arm),
                        Offset(at.x, at.y + arm),
                        strokeWidth = 2.dp.toPx(),
                        cap = StrokeCap.Round,
                    )
                }
            }
        }

        if (tapping && granted) {
            Box(
                Modifier
                    .fillMaxSize()
                    .pointerInput(uprightAspect) {
                        detectTapGestures { offset ->
                            /*
                             * Normalised against the PICTURE, not the view.
                             *
                             * The homography is solved in the analysis frame's 0..1 space,
                             * so a corner has to be recorded there too. Dividing by the
                             * view's size instead - which is what this did - handed the
                             * solver four points from a different rectangle, and produced
                             * a calibration that solves perfectly and reads every length
                             * wrong. That is the worst kind of wrong: it looks fine.
                             */
                            val frame = frameRect(
                                size.width.toFloat(),
                                size.height.toFloat(),
                                uprightAspect,
                            )
                            // A tap on a letterbox bar is not a tap on the pitch.
                            if (!frame.contains(offset)) return@detectTapGestures
                            val local = frame.normalise(offset)
                            val point = com.haraan.app.vision.Point2(
                                local.x.toDouble(),
                                local.y.toDouble(),
                            )
                            val next = tappedCorners + point
                            tappedCorners = next
                            // Each corner lands, and the fourth one closes the shape. Told
                            // by feel because the person doing this is looking at a pitch.
                            view.performHapticFeedback(if (next.size >= 4) Feel.COMMIT else Feel.TICK)
                            if (next.size >= 4) tapping = false
                        }
                    },
            ) {
                Canvas(Modifier.fillMaxSize()) {
                    val frame = frameRect(size.width, size.height, uprightAspect)
                    tappedCorners.forEachIndexed { i, point ->
                        val o = frame.at(point.x.toFloat(), point.y.toFloat())
                        drawCircle(Color(0xFFFACC15), radius = 7.dp.toPx(), center = o)
                        drawCircle(
                            Color.Black.copy(alpha = 0.6f),
                            radius = 7.dp.toPx(),
                            center = o,
                            style = Stroke(width = 1.5.dp.toPx()),
                        )
                        if (i > 0) {
                            val previous = tappedCorners[i - 1]
                            drawLine(
                                Color(0xFFFACC15).copy(alpha = 0.7f),
                                frame.at(previous.x.toFloat(), previous.y.toFloat()),
                                o,
                                strokeWidth = 2.dp.toPx(),
                            )
                        }
                    }
                }
            }
        }

        /*
         * THE PANEL STANDS DOWN WHILE A WICKET IS BEING PLACED.
         *
         * Found on a phone with three stumps in frame: this panel sits over the middle of
         * the picture, and a Compose surface that handles its own touches eats every tap
         * inside it. Every tap aimed at a stump did nothing at all, with nothing on screen
         * to say why.
         *
         * Hidden rather than layered under the tap catcher, because while somebody is
         * pointing at a wicket the whole picture IS the control, and a panel merely sitting
         * underneath would still be covering the thing they are aiming at. The prompt and
         * the way out are drawn separately below.
         */
        /*
         * SPORTS TECH STATUS PILL & MINIMAL CONTROLS
         *
         * Shows only simple, high-tech states: "Detecting stumps…", "Ready", or "Tracking delivery".
         * All complex calculations continue running internally without cluttering the screen.
         */
        if (!wicketTapping) {
            val currentLock = wicketLock
            val isReady = currentLock != null && (
                currentLock.state == com.haraan.app.vision.WicketTrackState.CONFIRMED ||
                currentLock.source == com.haraan.app.vision.WicketLockSource.MANUAL
            )

            // Tactile physical feedback the instant stumps lock into place
            LaunchedEffect(isReady) {
                if (isReady) {
                    view.performHapticFeedback(Feel.COMMIT)
                }
            }

            val isTracking = recording || trackingLive
            val statusColor = when {
                isTracking -> Color(0xFFEF4444)
                isReady -> Color(0xFF10B981)
                else -> Color(0xFFF59E0B)
            }
            val statusText = when {
                isTracking -> "Tracking delivery"
                isReady -> "Ready"
                else -> "Detecting stumps…"
            }

            val infiniteTransition = rememberInfiniteTransition(label = "statusPulse")
            val pulseAlpha by infiniteTransition.animateFloat(
                initialValue = 0.35f,
                targetValue = 1.0f,
                animationSpec = infiniteRepeatable(
                    animation = tween(850, easing = FastOutSlowInEasing),
                    repeatMode = RepeatMode.Reverse,
                ),
                label = "ledPulse",
            )

            // ─────────────────────────────────────────────────────────────────
            // 1. UNIFIED BROADCAST TOP MASTER BAR (Zero Collision)
            // ─────────────────────────────────────────────────────────────────
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .align(Alignment.TopCenter)
                    .statusBarsPadding()
                    .displayCutoutPadding()
                    .padding(horizontal = 14.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                // Left: Camera identity & framerate badge
                Row(
                    modifier = Modifier
                        .clip(RoundedCornerShape(8.dp))
                        .background(Color(0xD90F172A))
                        .border(1.dp, Color.White.copy(alpha = 0.12f), RoundedCornerShape(8.dp))
                        .padding(horizontal = 9.dp, vertical = 5.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Box(
                        modifier = Modifier
                            .size(6.5.dp)
                            .clip(CircleShape)
                            .background(if (recording) Color(0xFFEF4444) else Color(0xFF10B981))
                    )
                    Spacer(Modifier.width(6.dp))
                    Text(
                        text = "STUMP CAM",
                        color = Color.White,
                        fontSize = 10.5.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 0.6.sp,
                    )
                    Spacer(Modifier.width(5.dp))
                    Text(
                        text = "60fps",
                        color = Color(0xFF94A3B8),
                        fontSize = 8.5.sp,
                        fontWeight = FontWeight.Medium,
                    )
                }

                // Center: Optical Status Badge
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(20.dp))
                        .background(Color(0xE60A0E17))
                        .border(
                            1.dp,
                            Brush.horizontalGradient(
                                listOf(
                                    statusColor.copy(alpha = 0.35f),
                                    Color.White.copy(alpha = 0.15f),
                                    statusColor.copy(alpha = 0.35f),
                                )
                            ),
                            RoundedCornerShape(20.dp),
                        )
                        .padding(horizontal = 12.dp, vertical = 5.dp),
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            modifier = Modifier
                                .size(6.5.dp)
                                .clip(CircleShape)
                                .background(statusColor.copy(alpha = if (isReady) 1.0f else pulseAlpha))
                        )
                        Spacer(Modifier.width(6.dp))
                        Text(
                            text = if (isReady) "HAWK-EYE CALIBRATED" else statusText.uppercase(),
                            color = Color.White,
                            fontSize = 10.sp,
                            fontWeight = FontWeight.Bold,
                            letterSpacing = 0.6.sp,
                        )
                    }
                }

                // Right: Tuning gear & Exit cross
                Row(
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    // Settings / Tuning toggle
                    Box(
                        modifier = Modifier
                            .size(34.dp)
                            .clip(CircleShape)
                            .background(if (showAdminPanel) Color(0xFF2563EB) else Color(0xD90F172A))
                            .border(1.dp, Color.White.copy(alpha = 0.16f), CircleShape)
                            .clickableCapture(enabled = true) { showAdminPanel = !showAdminPanel },
                        contentAlignment = Alignment.Center,
                    ) {
                        TuningGearGlyph(Modifier.size(13.dp), if (showAdminPanel) Color.White else Color(0xFF94A3B8))
                    }

                    // Dismiss / Exit button
                    Box(
                        modifier = Modifier
                            .size(34.dp)
                            .clip(CircleShape)
                            .background(Color(0xD90F172A))
                            .border(1.dp, Color.White.copy(alpha = 0.16f), CircleShape)
                            .clickableCapture(enabled = true, onClick = onExit),
                        contentAlignment = Alignment.Center,
                    ) {
                        ExitCrossGlyph(Modifier.size(11.dp), Color.White.copy(alpha = 0.85f))
                    }
                }
            }

            // ─────────────────────────────────────────────────────────────────
            // 2. FOX SPORTS BROADCAST TELEMETRY CHYRON (Below Top Bar)
            // ─────────────────────────────────────────────────────────────────
            val (speedVal, speedU) = when (val s = lastMetrics?.groundSpeed) {
                is com.haraan.app.vision.MetricValue.Measured -> "%.0f".format(s.value * 0.621371) to "MPH"
                else -> "--" to "MPH"
            }
            val (spinVal, spinU) = when (val t = lastMetrics?.turn) {
                is com.haraan.app.vision.MetricValue.Measured -> "%.1f".format(kotlin.math.abs(t.value)) to "°"
                else -> "--" to "°"
            }
            val (swingVal, swingU) = when (val sw = lastMetrics?.swing) {
                is com.haraan.app.vision.MetricValue.Measured -> "%.1f".format(kotlin.math.abs(sw.value)) to "SF"
                else -> "--" to "SF"
            }

            Column(
                modifier = Modifier
                    .align(Alignment.TopStart)
                    .statusBarsPadding()
                    .displayCutoutPadding()
                    .padding(top = 52.dp, start = 14.dp),
                verticalArrangement = Arrangement.spacedBy(6.dp),
            ) {
                FoxSportsTelemetryChyron(
                    speedValue = speedVal,
                    speedUnit = speedU,
                    spinValue = spinVal,
                    spinUnit = spinU,
                    swingValue = swingVal,
                    swingUnit = swingU,
                )

                if (showAdminPanel) {
                    Spacer(Modifier.height(4.dp))
                    WicketDiagnosticsPanel(
                        diagnostics = wicketDiagnostics,
                        lock = currentLock,
                        modifier = Modifier
                            .widthIn(max = 280.dp)
                            .clip(RoundedCornerShape(12.dp))
                            .background(Color.Black.copy(alpha = 0.85f))
                            .border(1.dp, Color.White.copy(alpha = 0.15f), RoundedCornerShape(12.dp))
                            .padding(10.dp),
                    )
                }
            }

            // ─────────────────────────────────────────────────────────────────
            // 3. TACTILE MACHINED CAMERA TOOL RAIL (Middle-Left in Portrait, Bottom-Left in Landscape)
            // ─────────────────────────────────────────────────────────────────
            val toolRailModifier = if (landscape) {
                Modifier
                    .align(Alignment.BottomStart)
                    .navigationBarsPadding()
                    .displayCutoutPadding()
                    .padding(start = 16.dp, bottom = 18.dp)
            } else {
                Modifier
                    .align(Alignment.CenterStart)
                    .padding(start = 14.dp)
            }

            Box(
                modifier = toolRailModifier
                    .clip(RoundedCornerShape(24.dp))
                    .background(Color(0xD90F172A))
                    .border(
                        1.2.dp,
                        Brush.verticalGradient(
                            listOf(Color.White.copy(alpha = 0.25f), Color.White.copy(alpha = 0.08f))
                        ),
                        RoundedCornerShape(24.dp),
                    )
                    .padding(vertical = 8.dp, horizontal = 5.dp),
            ) {
                val railButtons: @Composable () -> Unit = {
                    MachinedDialButton(
                        active = showGuide,
                        activeColor = Color(0xFF00E5FF),
                        onClick = { showGuide = !showGuide },
                    ) { tint ->
                        ReticleGlyph(Modifier.size(17.dp), tint)
                    }

                    // Knurled tactile separator grooves
                    val grooveColor = Color.White.copy(alpha = 0.15f)
                    if (landscape) {
                        Row(
                            horizontalArrangement = Arrangement.spacedBy(2.5.dp),
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            repeat(3) {
                                Box(
                                    modifier = Modifier
                                        .width(1.2.dp)
                                        .height(14.dp)
                                        .background(grooveColor)
                                )
                            }
                        }
                    } else {
                        Column(
                            verticalArrangement = Arrangement.spacedBy(2.5.dp),
                            horizontalAlignment = Alignment.CenterHorizontally,
                        ) {
                            repeat(3) {
                                Box(
                                    modifier = Modifier
                                        .width(14.dp)
                                        .height(1.2.dp)
                                        .background(grooveColor)
                                )
                            }
                        }
                    }

                    MachinedDialButton(
                        active = wicketDiagnostics.lockSource == com.haraan.app.vision.WicketLockSource.MANUAL,
                        activeColor = Color(0xFFE2C48D),
                        onClick = {
                            if (wicketDiagnostics.lockSource == com.haraan.app.vision.WicketLockSource.MANUAL) {
                                wicketTracker.clearManualLock()
                                wicketLock = wicketTracker.lock()
                                wicketDiagnostics = wicketTracker.diagnostics()
                                wicketTaps = emptyList()
                            } else {
                                wicketTaps = emptyList()
                                wicketTapping = true
                            }
                        },
                    ) { tint ->
                        CalibrationGlyph(Modifier.size(17.dp), tint)
                    }
                }

                if (landscape) {
                    Row(
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        railButtons()
                    }
                } else {
                    Column(
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                    ) {
                        railButtons()
                    }
                }
            }
        }

        // And what replaces it: the one instruction that matters, and the way out. Bottom
        // centre, clear of the picture, where a thumb already is.
        if (wicketTapping && granted) {
            Column(
                Modifier
                    .align(Alignment.TopCenter)
                    .statusBarsPadding()
                    .displayCutoutPadding()
                    .padding(16.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Text(
                    when (wicketTaps.size) {
                        0 -> "Tap the outside of one stump, at the ground"
                        1 -> "Now the far stump, at the ground"
                        else -> "Optional: tap the top of a stump"
                    },
                    color = Ink,
                    fontSize = 12.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier
                        .clip(RoundedCornerShape(10.dp))
                        .background(Color.Black.copy(alpha = 0.66f))
                        .padding(horizontal = 13.dp, vertical = 9.dp),
                )
                Spacer(Modifier.height(8.dp))
                Text(
                    "Cancel",
                    color = Color(0xFFBDD3FF),
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier
                        .clip(RoundedCornerShape(10.dp))
                        .background(Color.Black.copy(alpha = 0.66f))
                        .clickableCapture(enabled = true) {
                            wicketTapping = false
                            wicketTaps = emptyList()
                        }
                        .padding(horizontal = 16.dp, vertical = 9.dp),
                )
            }
        }

        /*
         * ARMING A DELIVERY.
         *
         * Lifted out of the button's modifier chain, where it used to live as eighty lines
         * of lambda nested inside a `.then()`. Separating the control from what it does is
         * what let the tap target grow to the whole disc without a line of this moving.
         *
         * An anonymous function rather than a lambda so the early return below reads as an
         * early return.
         */
        val armDelivery: () -> Unit = fun() {
            val capture = videoCapture ?: return
            uploadError = null
            // A fresh delivery: the previous track must not bleed into this one.
            vision.reset()
            trackedPoints = 0
            ballTrail = emptyList()
            latestBall = null
            trackQuality = TrackQuality.UNCERTAIN
            lastBounce = null
            lastMetrics = null
            trackingLive = true
            recording = true
            val clipFile = uploadQueue.createClipFile()
            activeRecording = startClip(
                context = ctx,
                capture = capture,
                executor = executor,
                targetFile = clipFile,
                onFinished = { file, durationMs ->
                    recording = false
                    trackingLive = false
                    /*
                     * The measurement, taken once the delivery is over.
                     *
                     * Against the pitch as it was calibrated when the clip started, not as
                     * it might be now: the ball was filmed on that pitch. Null is the
                     * common answer — no calibration, a full toss, too few points — and it
                     * simply means nothing is claimed.
                     */
                    lastBounce = found?.let { pitch ->
                        com.haraan.app.vision.BouncePoint.find(vision.track(), pitch)
                    }
                    /*
                     * And the rest of the delivery's numbers, from the same track.
                     *
                     * Both landmarks go in, and they answer different questions: the quad
                     * is the only thing that can say how far UP the pitch the ball landed,
                     * and the wicket lock is the only thing that can put a speed in km/h or
                     * a projection in centimetres. On most grounds there is no quad and the
                     * wicket is all there is, which is the entire reason it was built.
                     *
                     * The lock is read through [WicketLock.isMeasurable] inside, not
                     * checked for null here: a lock that is coasting is still a lock and
                     * must not be measured from.
                     */
                    lastMetrics = com.haraan.app.vision.FlightMetrics.of(
                        track = vision.track(),
                        frameAspect = uprightAspect,
                        quad = found,
                        wicket = wicketLock,
                    )
                    // The window closed. Nobody pressed stop, so this is the only way to
                    // know it is shut and the next delivery can be armed.
                    view.performHapticFeedback(Feel.TICK)
                    // Checked here so a clip that cannot be accepted never crosses ground
                    // Wi-Fi at all.
                    if (file.length() > MAX_REVIEW_BYTES) {
                        runCatching { file.delete() }
                        view.performHapticFeedback(Feel.REMOVE)
                        uploadError = "That clip is too large to send. Record a shorter delivery."
                        return@startClip
                    }
                    /*
                     * Persisted in the review queue:
                     * The operator is free to film the next delivery immediately without
                     * waiting on cellular latency. Backlog drains in background with
                     * exponential backoff, deleting only upon confirmed 2xx landing.
                     */
                    uploadQueue.enqueue(
                        file = file,
                        sessionToken = session.sessionToken,
                        durationMs = durationMs,
                        overBall = overs.takeIf { it.isNotBlank() },
                    )
                },
            )
            /*
             * Ends itself at the review ceiling.
             *
             * Ten seconds is what the server accepts, and it buys the run-up, the release,
             * the bounce, the shot and enough afterwards to see where the ball went -
             * which is the context a review actually needs. Nobody at a ground is watching
             * this screen to press stop, so the cap has to be the recorder's own.
             *
             * Slightly under the limit on purpose: the container rounds, and a clip that
             * measures 10.02s server-side would be refused after the upload had already
             * been paid for over ground Wi-Fi.
             */
            scope.launch {
                delay(REVIEW_CLIP_MS)
                activeRecording?.stop()
                activeRecording = null
            }
        }

        /*
         * THE CONTROL, on the short edge of whichever way the phone is being held.
         *
         * Portrait: along the bottom, under the thumb of a hand holding the phone up.
         * Landscape: against the right edge, vertically centred - where the stock camera
         * app puts a shutter, and where the right hand already is on a phone clamped to a
         * tripod side-on. A bottom-centre button in landscape sits under the middle of a
         * wide frame, which is both the hardest place on the screen to reach and directly
         * over the pitch.
         *
         * Landscape is the orientation this feature is actually used in - a phone filming
         * a cricket pitch from behind the bowler's arm is a phone on its side - and until
         * now turning it produced a portrait layout stretched across a wide screen.
         */
        /*
         * How the track is behaving, in one word, or in none.
         *
         * UNCERTAIN says nothing, because for most of a delivery it means "fewer than
         * three points so far" rather than anything being wrong, and a screen that opens
         * every capture by calling itself unreliable teaches the operator to ignore it.
         * The two words that do appear describe the TRACK — its gaps and its wobble — and
         * never whether the thing being tracked is the ball, which nothing here knows.
         */
        val trackWord = when (trackQuality) {
            TrackQuality.RELIABLE -> " · steady"
            TrackQuality.PARTIAL -> " · patchy"
            TrackQuality.UNCERTAIN -> ""
        }
        val pendingCount = queueStatus.pendingCount
        val clipsSent = queueStatus.clipsSent
        val isUploading = queueStatus.isUploading
        val queueError = queueStatus.lastError

        val status = when {
            // The point count is the only honest signal of whether vision is doing
            // anything, and it belongs where the person filming can see it.
            recording && trackedPoints > 0 -> "Recording · $trackedPoints ball points$trackWord"
            recording -> "Recording this delivery…"
            uploadError != null -> uploadError!!
            // Said out loud rather than left to a small coloured dot. Somebody holding
            // this phone at the boundary needs to know the difference between "idle" and
            // "the scorer has stopped hearing from me".
            !live -> "Reconnecting to the match…"
            /*
             * One line, and only after a delivery.
             *
             * The rest of a pitch map belongs to the scorer and the player, not to somebody
             * standing at a boundary holding a phone. What earns its place here is the
             * confirmation that the calibration under it is working — which is why the
             * spoken length comes first and the number second, and why there is no tile,
             * no card and no second row.
             */
            /*
             * THE DELIVERY'S ONE LINE, and what gets to be in it.
             *
             * A speed goes first when there is one, because it is the number everybody at
             * a ground asks for and it only exists when the wicket lock was measurable AND
             * the camera was square enough to the flight to see the motion at all — so its
             * presence is itself the confirmation that the setup is right. The length comes
             * next, because it is the only thing on this screen measured in metres up the
             * pitch. Nothing else fits, and a second row here would be a scoreboard on a
             * screen whose job is to film.
             */
            lastMetrics?.groundSpeed is com.haraan.app.vision.MetricValue.Measured ->
                with(lastMetrics!!.groundSpeed as com.haraan.app.vision.MetricValue.Measured) {
                    buildString {
                        append("%.0f km/h".format(value))
                        val turn = lastMetrics?.turn
                        if (turn is com.haraan.app.vision.MetricValue.Measured &&
                            kotlin.math.abs(turn.value) >= 2.0
                        ) {
                            append(" · turned %.0f°".format(kotlin.math.abs(turn.value)))
                        }
                        lastBounce?.let { append(" · ${it.length.spoken}") }
                    }
                }

            lastBounce != null -> with(lastBounce!!) {
                "Pitched ${length.spoken} · ${"%.1f".format(lengthM)} m"
            }
            // Said plainly, and said with what still works. A phone that has gone quiet
            // about the ball while the operator can see it is filming invites the guess
            // that the whole thing has broken.
            thermalThrottled -> "Phone's hot · still filming, tracking paused"
            queueError != null && pendingCount > 0 -> "Upload paused · $pendingCount pending"
            isUploading && pendingCount > 1 -> "Sending clip to scorer · $pendingCount queued"
            isUploading -> "Sending to the scorer…"
            pendingCount > 0 && clipsSent > 0 -> "$clipsSent sent · $pendingCount queued"
            pendingCount > 0 -> "$pendingCount queued · waiting to send"
            trackedPoints > 0 -> "$clipsSent sent · $trackedPoints ball points$trackWord"
            clipsSent > 0 -> "$clipsSent sent"
            else -> "Tap when the bowler runs in"
        }
        ShutterControl(
            modifier = Modifier
                .align(if (landscape) Alignment.CenterEnd else Alignment.BottomCenter)
                // The bars and the cutout move with the phone; asking for both lets the
                // insets decide which edge they are on this time.
                .navigationBarsPadding()
                .displayCutoutPadding()
                .padding(
                    end = if (landscape) 26.dp else 0.dp,
                    bottom = if (landscape) 0.dp else 30.dp,
                ),
            status = status,
            isError = uploadError != null || (queueError != null && pendingCount > 0),
            recording = recording,
            canFilm = granted && videoCapture != null && !recording,
            landscape = landscape,
            onArm = armDelivery,
        )
    }
}

/**
 * The status line and the shutter — everything on the filming screen that is not the
 * picture, kept together because they are read together.
 *
 * Two arrangements of the same two things. Portrait stacks them along the bottom edge;
 * landscape lays them along the right edge, status first, shutter outermost. In both the
 * disc ends up on the short edge nearest the hand, and in neither does it sit over the
 * middle of the pitch.
 */
@Composable
private fun ShutterControl(
    modifier: Modifier,
    status: String,
    isError: Boolean,
    recording: Boolean,
    canFilm: Boolean,
    landscape: Boolean,
    onArm: () -> Unit,
) {
    val view = LocalView.current

    /*
     * THE SHUTTER.
     *
     * The whole disc is the button. It used to be a 30dp square sitting inside an 84dp
     * ring, so the thing that LOOKED like the target was some seven times the area of the
     * thing that actually took a tap — on the one screen in the app built to be worked
     * without looking at it. A thumb landing on the ring did nothing, silently, while a
     * bowler ran in, and the operator had no way to tell that from a camera that had
     * stopped responding.
     *
     * It also refuses a press while a clip is still being filmed, instead of
     * letting one land on a control that cannot act on it.
     */
    val shutter = remember { MutableInteractionSource() }
    val shutterPressed by shutter.collectIsPressedAsState()
    val shutterScale by animateFloatAsState(
        targetValue = if (shutterPressed && canFilm) 0.93f else 1f,
        animationSpec = spring(dampingRatio = 0.5f, stiffness = 900f),
        label = "shutterScale",
    )

    val statusLine: @Composable () -> Unit = {
        Box(
            modifier = Modifier
                .clip(RoundedCornerShape(12.dp))
                .background(Color(0xD90A0E17))
                .border(1.dp, Color.White.copy(alpha = 0.12f), RoundedCornerShape(12.dp))
                .padding(horizontal = 14.dp, vertical = 6.dp),
        ) {
            Text(
                status,
                color = if (isError) Rec else Color.White.copy(alpha = 0.90f),
                fontSize = 11.5.sp,
                fontWeight = FontWeight.Medium,
                letterSpacing = 0.3.sp,
                modifier = if (landscape) Modifier.widthIn(max = 230.dp) else Modifier,
                textAlign = if (landscape) TextAlign.End else TextAlign.Center,
            )
        }
    }

    val disc: @Composable () -> Unit = {
        Box(
            Modifier
                // First in the chain, so the disc itself dips rather than its contents
                // shrinking inside a ring that stays where it was.
                .graphicsLayer { scaleX = shutterScale; scaleY = shutterScale }
                .size(76.dp)
                .clip(CircleShape)
                .background(
                    Brush.radialGradient(
                        listOf(Color(0xFF222B3D), Color(0xFF0F1523)),
                    )
                )
                .border(
                    2.5.dp,
                    Brush.sweepGradient(
                        listOf(
                            Color.White.copy(alpha = 0.85f),
                            Color(0xFF64748B),
                            Color.White.copy(alpha = 0.85f),
                            Color(0xFF475569),
                            Color.White.copy(alpha = 0.85f),
                        ),
                    ),
                    CircleShape,
                )
                .clickable(
                    interactionSource = shutter,
                    indication = null,
                    enabled = canFilm,
                ) {
                    // Filming started. The heaviest note this screen has, because it is
                    // the only action on it that commits.
                    view.performHapticFeedback(Feel.COMMIT)
                    onArm()
                },
            contentAlignment = Alignment.Center,
        ) {
            if (recording) {
                CircularProgressIndicator(
                    color = Rec,
                    strokeWidth = 3.dp,
                    modifier = Modifier.size(34.dp),
                )
                Box(
                    Modifier
                        .size(14.dp)
                        .clip(RoundedCornerShape(3.dp))
                        .background(Rec),
                )
            } else {
                Box(
                    Modifier
                        .size(44.dp)
                        .clip(CircleShape)
                        .background(
                            if (canFilm) {
                                Brush.radialGradient(
                                    listOf(Color(0xFFFF4D4D), Rec),
                                )
                            } else {
                                Brush.radialGradient(
                                    listOf(Rec.copy(alpha = 0.35f), Rec.copy(alpha = 0.20f)),
                                )
                            }
                        ),
                )
            }
        }
    }

    if (landscape) {
        Row(modifier, verticalAlignment = Alignment.CenterVertically) {
            statusLine()
            Spacer(Modifier.width(16.dp))
            disc()
        }
    } else {
        Column(modifier, horizontalAlignment = Alignment.CenterHorizontally) {
            statusLine()
            Spacer(Modifier.height(14.dp))
            disc()
        }
    }
}

/** The one commitment on the screen: lit, gradient-filled, and it dips under a thumb. */
@Composable
private fun PrimaryButton(label: String, enabled: Boolean, onClick: () -> Unit) {
    val interaction = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val scale = remember { Animatable(1f) }
    val view = LocalView.current
    LaunchedEffect(pressed) {
        scale.animateTo(if (pressed) 0.97f else 1f, tween(140, easing = FastOutSlowInEasing))
    }
    Box(
        Modifier
            .fillMaxWidth()
            .graphicsLayer { scaleX = scale.value; scaleY = scale.value }
            .clip(RoundedCornerShape(16.dp))
            .background(
                Brush.horizontalGradient(
                    if (enabled) listOf(Color(0xFF3B82F6), Color(0xFF2563EB))
                    else listOf(Color(0xFF1E293B), Color(0xFF1E293B)),
                ),
            )
            .clickable(
                interactionSource = interaction,
                indication = null,
                enabled = enabled,
            ) {
                // Joining a match is a commitment, and the only one made on this screen
                // while the phone is still being looked at.
                view.performHapticFeedback(Feel.COMMIT)
                onClick()
            }
            .padding(vertical = 18.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            label,
            color = if (enabled) Color.White else Ink.copy(alpha = 0.4f),
            fontSize = 16.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = (-0.2).sp,
        )
    }
}

/** The way out. An outline, because leaving is not the thing being encouraged. */
@Composable
private fun GhostButton(label: String, onClick: () -> Unit) {
    Box(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .border(1.dp, Color.White.copy(alpha = 0.13f), RoundedCornerShape(16.dp))
            .clickableCapture(enabled = true, onClick = onClick)
            .padding(vertical = 17.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(label, color = Ink.copy(alpha = 0.75f), fontSize = 15.5.sp, fontWeight = FontWeight.SemiBold)
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// HANDCRAFTED BROADCAST SPORTS-TECH GLYPHS (Precision Canvas Paths)
// ─────────────────────────────────────────────────────────────────────────────

@Composable
private fun VelocityGlyph(modifier: Modifier = Modifier, tint: Color = Color(0xFF00E5FF)) {
    Canvas(modifier = modifier) {
        val w = size.width
        val h = size.height
        val stroke = with(density) { 1.5.dp.toPx() }
        val path1 = Path().apply {
            moveTo(w * 0.15f, h * 0.20f)
            lineTo(w * 0.52f, h * 0.50f)
            lineTo(w * 0.15f, h * 0.80f)
        }
        val path2 = Path().apply {
            moveTo(w * 0.45f, h * 0.20f)
            lineTo(w * 0.82f, h * 0.50f)
            lineTo(w * 0.45f, h * 0.80f)
        }
        drawPath(path1, tint.copy(alpha = 0.45f), style = Stroke(width = stroke, cap = StrokeCap.Round))
        drawPath(path2, tint, style = Stroke(width = stroke, cap = StrokeCap.Round))
    }
}

@Composable
private fun SpinGlyph(modifier: Modifier = Modifier, tint: Color = Color(0xFFA855F7)) {
    Canvas(modifier = modifier) {
        val w = size.width
        val h = size.height
        val r = kotlin.math.min(w, h) * 0.36f
        val c = Offset(w / 2f, h / 2f)
        val stroke = with(density) { 1.5.dp.toPx() }
        drawArc(
            color = tint,
            startAngle = 35f,
            sweepAngle = 275f,
            useCenter = false,
            topLeft = Offset(c.x - r, c.y - r),
            size = androidx.compose.ui.geometry.Size(r * 2, r * 2),
            style = Stroke(width = stroke, cap = StrokeCap.Round),
        )
        val arrow = Path().apply {
            moveTo(c.x + r * 0.70f, c.y - r * 0.72f)
            lineTo(c.x + r * 1.05f, c.y - r * 0.22f)
            lineTo(c.x + r * 0.42f, c.y - r * 0.38f)
            close()
        }
        drawPath(arrow, tint)
    }
}

@Composable
private fun TrajectoryGlyph(modifier: Modifier = Modifier, tint: Color = Color(0xFF10B981)) {
    Canvas(modifier = modifier) {
        val w = size.width
        val h = size.height
        val stroke = with(density) { 1.6.dp.toPx() }
        val arc = Path().apply {
            moveTo(w * 0.18f, h * 0.82f)
            cubicTo(w * 0.28f, h * 0.35f, w * 0.65f, h * 0.22f, w * 0.82f, h * 0.38f)
        }
        drawPath(arc, tint, style = Stroke(width = stroke, cap = StrokeCap.Round))
        drawCircle(tint, radius = with(density) { 2.dp.toPx() }, center = Offset(w * 0.82f, h * 0.38f))
    }
}

@Composable
private fun ReticleGlyph(modifier: Modifier = Modifier, tint: Color = Color.White) {
    Canvas(modifier = modifier) {
        val w = size.width
        val h = size.height
        val c = Offset(w / 2f, h / 2f)
        val r = kotlin.math.min(w, h) * 0.32f
        drawCircle(tint.copy(alpha = 0.55f), radius = r, center = c, style = Stroke(width = with(density) { 1.2.dp.toPx() }))
        val tickLen = kotlin.math.min(w, h) * 0.18f
        val strokeW = with(density) { 1.3.dp.toPx() }
        drawLine(tint, Offset(c.x, c.y - r - tickLen), Offset(c.x, c.y - r + with(density) { 2.dp.toPx() }), strokeWidth = strokeW, cap = StrokeCap.Round)
        drawLine(tint, Offset(c.x, c.y + r - with(density) { 2.dp.toPx() }), Offset(c.x, c.y + r + tickLen), strokeWidth = strokeW, cap = StrokeCap.Round)
        drawLine(tint, Offset(c.x - r - tickLen, c.y), Offset(c.x - r + with(density) { 2.dp.toPx() }, c.y), strokeWidth = strokeW, cap = StrokeCap.Round)
        drawLine(tint, Offset(c.x + r - with(density) { 2.dp.toPx() }, c.y), Offset(c.x + r + tickLen, c.y), strokeWidth = strokeW, cap = StrokeCap.Round)
        drawCircle(tint, radius = with(density) { 1.6.dp.toPx() }, center = c)
    }
}

@Composable
private fun CalibrationGlyph(modifier: Modifier = Modifier, tint: Color = Color(0xFFFACC15)) {
    Canvas(modifier = modifier) {
        val w = size.width
        val h = size.height
        val bolt = Path().apply {
            moveTo(w * 0.58f, h * 0.12f)
            lineTo(w * 0.28f, h * 0.52f)
            lineTo(w * 0.52f, h * 0.52f)
            lineTo(w * 0.42f, h * 0.88f)
            lineTo(w * 0.72f, h * 0.46f)
            lineTo(w * 0.48f, h * 0.46f)
            close()
        }
        drawPath(bolt, tint)
    }
}

@Composable
private fun TuningGearGlyph(modifier: Modifier = Modifier, tint: Color = Color.White) {
    Canvas(modifier = modifier) {
        val w = size.width
        val h = size.height
        val strokeW = with(density) { 1.2.dp.toPx() }
        val r = with(density) { 2.dp.toPx() }
        // 3 horizontal slider bars with tuning knobs
        drawLine(tint.copy(alpha = 0.40f), Offset(w * 0.12f, h * 0.28f), Offset(w * 0.88f, h * 0.28f), strokeWidth = strokeW, cap = StrokeCap.Round)
        drawCircle(tint, radius = r, center = Offset(w * 0.38f, h * 0.28f))

        drawLine(tint.copy(alpha = 0.40f), Offset(w * 0.12f, h * 0.52f), Offset(w * 0.88f, h * 0.52f), strokeWidth = strokeW, cap = StrokeCap.Round)
        drawCircle(tint, radius = r, center = Offset(w * 0.68f, h * 0.52f))

        drawLine(tint.copy(alpha = 0.40f), Offset(w * 0.12f, h * 0.76f), Offset(w * 0.88f, h * 0.76f), strokeWidth = strokeW, cap = StrokeCap.Round)
        drawCircle(tint, radius = r, center = Offset(w * 0.32f, h * 0.76f))
    }
}

@Composable
private fun ExitCrossGlyph(modifier: Modifier = Modifier, tint: Color = Color.White) {
    Canvas(modifier = modifier) {
        val w = size.width
        val h = size.height
        val strokeW = with(density) { 1.8.dp.toPx() }
        drawLine(tint, Offset(w * 0.22f, h * 0.22f), Offset(w * 0.78f, h * 0.78f), strokeWidth = strokeW, cap = StrokeCap.Round)
        drawLine(tint, Offset(w * 0.78f, h * 0.22f), Offset(w * 0.22f, h * 0.78f), strokeWidth = strokeW, cap = StrokeCap.Round)
    }
}

@Composable
private fun TelemetryItem(
    label: String,
    value: String,
    unit: String,
    accentColor: Color,
    glyph: @Composable () -> Unit,
) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            glyph()
            Spacer(Modifier.width(3.dp))
            Text(
                text = label,
                color = Color(0xFF94A3B8),
                fontSize = 7.5.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = 0.8.sp,
            )
        }
        Spacer(Modifier.height(2.dp))
        Row(verticalAlignment = Alignment.Bottom) {
            Text(
                text = value,
                color = Color.White,
                fontSize = 15.sp,
                fontWeight = FontWeight.Bold,
                fontFamily = FontFamily.Monospace,
            )
            Spacer(Modifier.width(2.dp))
            Text(
                text = unit,
                color = accentColor,
                fontSize = 8.sp,
                fontWeight = FontWeight.Bold,
                modifier = Modifier.padding(bottom = 1.dp),
            )
        }
    }
}

@Composable
private fun FoxSportsTelemetryChyron(
    speedValue: String,
    speedUnit: String,
    spinValue: String,
    spinUnit: String,
    swingValue: String,
    swingUnit: String,
    modifier: Modifier = Modifier,
) {
    Box(
        modifier = modifier
            .clip(RoundedCornerShape(10.dp))
            .background(Color(0xE60A0E17))
            .border(
                1.dp,
                Brush.horizontalGradient(
                    listOf(
                        Color.White.copy(alpha = 0.20f),
                        Color.White.copy(alpha = 0.06f),
                    )
                ),
                RoundedCornerShape(10.dp),
            )
            .padding(horizontal = 14.dp, vertical = 8.dp),
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            // Speed column
            Column {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    VelocityGlyph(Modifier.size(10.dp), Color(0xFFE2C48D))
                    Spacer(Modifier.width(4.dp))
                    Text(
                        text = "SPEED",
                        color = Color(0xFF94A3B8),
                        fontSize = 8.5.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 0.8.sp,
                    )
                }
                Spacer(Modifier.height(2.dp))
                Row(verticalAlignment = Alignment.Bottom) {
                    Text(
                        text = speedValue,
                        color = Color.White,
                        fontSize = 17.sp,
                        fontWeight = FontWeight.ExtraBold,
                        fontFamily = FontFamily.Monospace,
                    )
                    Spacer(Modifier.width(3.dp))
                    Text(
                        text = speedUnit,
                        color = Color(0xFF94A3B8),
                        fontSize = 8.5.sp,
                        fontWeight = FontWeight.SemiBold,
                    )
                }
            }

            Box(Modifier.width(1.dp).height(26.dp).background(Color.White.copy(alpha = 0.12f)))

            // Spin column
            Column {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    SpinGlyph(Modifier.size(10.dp), Color(0xFFE2C48D))
                    Spacer(Modifier.width(4.dp))
                    Text(
                        text = "SPIN",
                        color = Color(0xFF94A3B8),
                        fontSize = 8.5.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 0.8.sp,
                    )
                }
                Spacer(Modifier.height(2.dp))
                Row(verticalAlignment = Alignment.Bottom) {
                    Text(
                        text = spinValue,
                        color = Color.White,
                        fontSize = 17.sp,
                        fontWeight = FontWeight.ExtraBold,
                        fontFamily = FontFamily.Monospace,
                    )
                    Spacer(Modifier.width(2.dp))
                    Text(
                        text = spinUnit,
                        color = Color(0xFF94A3B8),
                        fontSize = 9.sp,
                        fontWeight = FontWeight.SemiBold,
                    )
                }
            }

            Box(Modifier.width(1.dp).height(26.dp).background(Color.White.copy(alpha = 0.12f)))

            // Swing column
            Column {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    TrajectoryGlyph(Modifier.size(10.dp), Color(0xFFE2C48D))
                    Spacer(Modifier.width(4.dp))
                    Text(
                        text = "SWING",
                        color = Color(0xFF94A3B8),
                        fontSize = 8.5.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 0.8.sp,
                    )
                }
                Spacer(Modifier.height(2.dp))
                Row(verticalAlignment = Alignment.Bottom) {
                    Text(
                        text = swingValue,
                        color = Color.White,
                        fontSize = 17.sp,
                        fontWeight = FontWeight.ExtraBold,
                        fontFamily = FontFamily.Monospace,
                    )
                    Spacer(Modifier.width(3.dp))
                    Text(
                        text = swingUnit,
                        color = Color(0xFF94A3B8),
                        fontSize = 8.5.sp,
                        fontWeight = FontWeight.SemiBold,
                    )
                }
            }
        }
    }
}

@Composable
private fun MachinedDialButton(
    active: Boolean,
    activeColor: Color = Color(0xFF00E5FF),
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    content: @Composable (tint: Color) -> Unit,
) {
    val interactionSource = remember { MutableInteractionSource() }
    val isPressed by interactionSource.collectIsPressedAsState()
    val scale by animateFloatAsState(
        targetValue = if (isPressed) 0.90f else 1.0f,
        animationSpec = spring(stiffness = 600f),
        label = "machined_dial_scale",
    )

    Box(
        modifier = modifier
            .graphicsLayer {
                scaleX = scale
                scaleY = scale
            }
            .size(42.dp)
            .clip(CircleShape)
            .background(
                if (active) {
                    Brush.radialGradient(
                        listOf(activeColor.copy(alpha = 0.30f), Color(0xF20A0E17)),
                    )
                } else {
                    Brush.radialGradient(
                        listOf(Color(0xFF1E293B), Color(0xF20A0E17)),
                    )
                }
            )
            .border(
                1.dp,
                if (active) {
                    Brush.sweepGradient(listOf(activeColor, Color.White.copy(alpha = 0.5f), activeColor))
                } else {
                    Brush.verticalGradient(listOf(Color.White.copy(alpha = 0.30f), Color.White.copy(alpha = 0.08f)))
                },
                CircleShape,
            )
            .clickable(
                interactionSource = interactionSource,
                indication = null,
                onClick = onClick,
            ),
        contentAlignment = Alignment.Center,
    ) {
        content(if (active) activeColor else Color.White.copy(alpha = 0.90f))
    }
}

/**
 * The four taps, in the order [com.haraan.app.vision.PitchGeometry.CALIBRATION_CORNERS_M]
 * expects them: clockwise from the striker's left, near crease first.
 *
 * Order is not a detail. The homography maps whatever it is handed, so corners collected
 * in the wrong sequence produce a map that solves perfectly and reads every length wrong.
 */
private val CORNER_PROMPTS = listOf(
    "Tap the NEAR crease corner on your left",
    "Now the NEAR corner on your right",
    "Now the FAR corner on the right",
    "Last one: the FAR corner on the left",
)

/**
 * Taps with no ripple — this screen is mostly a viewfinder.
 *
 * No ripple used to mean no acknowledgement at all: a tap on a control here landed in
 * total silence, on the one screen in the app where the finger is the only sense with any
 * attention to spare. The haptic is now what the ripple would have been. Pass null for a
 * control that fires its own, so nothing buzzes twice.
 */
@Composable
private fun Modifier.clickableCapture(
    enabled: Boolean,
    haptic: Int? = Feel.SELECT,
    onClick: () -> Unit,
): Modifier {
    val interaction = remember { androidx.compose.foundation.interaction.MutableInteractionSource() }
    val view = LocalView.current
    // `this.then(clickable(…))` — which is what this was — resolves the bare `clickable`
    // against the same implicit receiver and then appends the result to it, putting the
    // whole incoming chain into the final modifier twice. Chaining directly is what it
    // was always meant to say.
    return this.clickable(
        interactionSource = interaction,
        indication = null,
        enabled = enabled,
    ) {
        haptic?.let { view.performHapticFeedback(it) }
        onClick()
    }
}

// THE REVIEW CONTRACT, mirrored from the server.
//
// DeliveryReview holds the authoritative numbers; these exist so the phone refuses a clip
// it already knows will be refused, before spending a ground's upload on it. If the server
// limits move, these move with them.

/** Server ceiling is 10s; stop just under it so container rounding cannot push us over. */
/** Points kept on the live overlay. Enough to see a path, few enough to stay readable. */
private const val CAMERA_TRAIL_POINTS = 30

/**
 * How well-behaved a sighting was, as a colour: amber for barely, blue for thoroughly.
 *
 * WHAT THIS IS NOT. [com.haraan.app.vision.BallSighting.trackingConfidence] goes out of
 * its way not to call itself a confidence or a probability, because nothing in the tracker
 * has ever been checked against a human's judgement of the same footage. It ranks
 * candidates against each other: how round the thing was, and how well it continued the
 * track. So this ramp is not "how likely is that the ball" and the screen never says it
 * is. It is "how much did that behave like one", which is a smaller claim and an honest
 * one — and, usefully, the exact claim an operator needs, because a tracker that has
 * latched onto a fielder produces a trail of low-scoring points and always has.
 *
 * The ends of the ramp are the ends of the range the tracker can actually emit, not 0 and
 * 1. A candidate below MIN_CIRCULARITY or off the trajectory is discarded rather than
 * scored, so the lowest score that ever reaches this is around 0.3; spreading the ramp
 * across the full unit interval would paint every real track the same mid-blue and the
 * distinction would be invisible — which is the only thing it is for.
 */
internal fun trackColour(score: Float): Color {
    val t = ((score - TRACK_SCORE_FLOOR) / (TRACK_SCORE_CEILING - TRACK_SCORE_FLOOR))
        .coerceIn(0f, 1f)
    return lerp(TrailWeak, TrailStrong, t)
}

/** Barely ball-like. Deliberately not the yellow the corner-tapping flow owns. */
internal val TrailWeak = Color(0xFFF59E0B)

/** Round, and continuing the track as a thrown ball would. */
internal val TrailStrong = Color(0xFF6E9BF5)

/**
 * The bounce marker. Its own colour, because it is its own kind of thing: everything else
 * drawn over the pitch is a guess about where the ball was, and this is the one place the
 * app can say it knows.
 */
private val BounceMark = Color(0xFF4ADE80)

/*
 * THE SPAN OF SCORES WORTH TELLING APART.
 *
 * A sighting scores circularity × 0.6 + continuity × 0.4. The filters upstream mean the
 * lowest score that can ever reach this is around 0.27 — MIN_CIRCULARITY is 0.45, and a
 * candidate whose continuity hits zero is thrown away rather than scored — and the
 * arithmetic top is 1.0.
 *
 * Neither end of that arithmetic range is where the ramp ends, on purpose.
 *
 * The floor sits ABOVE the worst possible score so the bottom of the range saturates:
 * everything between a just-accepted 0.27 and 0.38 is equally "barely ball-like", and
 * grading within it would draw distinctions the numbers cannot support.
 *
 * The ceiling sits below 1.0 because a real ball in flight is motion-blurred into an oval
 * and pays for it in circularity. Requiring a perfect score for full blue would mean a
 * correctly tracked delivery never quite gets there, and a signal that never reaches its
 * good end is a signal nobody learns to read.
 */
private const val TRACK_SCORE_FLOOR = 0.38f
private const val TRACK_SCORE_CEILING = 0.78f

/**
 * How far the oldest end of the trail has faded, and how solid the newest end is.
 *
 * The floor is not zero on purpose: a point that has faded to nothing still occupies the
 * count on the status line, and an overlay that claims thirty points while showing eight
 * is lying about how much the tracker is actually holding on to.
 */
private const val TRAIL_FADE_FROM = 0.14f
private const val TRAIL_FADE_TO = 0.85f

/** Consecutive failed heartbeats before a camera is treated as genuinely gone. */
private const val MAX_MISSED_HEARTBEATS = 3

private const val REVIEW_CLIP_MS = 9_500L

/** Matches DeliveryReview::MAX_REVIEW_BYTES. */
private const val MAX_REVIEW_BYTES = 50L * 1024 * 1024

// ────────────────────────────────────────────── Picture space, not view space ─────

/**
 * Where the camera's picture actually sits inside this view.
 *
 * The preview is fitted, not filled, so on a tall phone showing a 16:9 frame there are
 * black bars and the picture is SHORTER than the view it lives in. Every overlay here is
 * normalised inside the picture, so every overlay has to be drawn into this rectangle —
 * painting across the whole view instead stretches the pitch outline and the ball trail
 * by the height of the bars, which is most visible at the top and bottom of the frame:
 * the far crease and the near one.
 *
 * Falls back to the whole view until the first analysis frame has reported a shape, which
 * is the old behaviour and is only ever on screen for a frame or two.
 */
internal fun frameRect(width: Float, height: Float, aspect: Float): Rect {
    if (aspect <= 0f || width <= 0f || height <= 0f) return Rect(0f, 0f, width, height)
    return if (width / height > aspect) {
        // The view is wider than the picture: bars down the sides.
        val fitted = height * aspect
        val left = (width - fitted) / 2f
        Rect(left, 0f, left + fitted, height)
    } else {
        // Taller: bars top and bottom. The usual case on a phone held upright.
        val fitted = width / aspect
        val top = (height - fitted) / 2f
        Rect(0f, top, width, top + fitted)
    }
}

/** A normalised point in the picture, placed on the screen. */
internal fun Rect.at(x: Float, y: Float) = Offset(left + x * width, top + y * height)

/** And back again: a point on the screen, in the picture's own 0..1 terms. */
internal fun Rect.normalise(point: Offset) =
    Offset((point.x - left) / width, (point.y - top) / height)

// ─────────────────────────────────────────────────────── CameraX plumbing ─────

private fun bindCamera(
    context: Context,
    view: PreviewView,
    lifecycleOwner: androidx.lifecycle.LifecycleOwner,
    analysisExecutor: java.util.concurrent.Executor,
    onFrame: ImageAnalysis.Analyzer,
    onReady: (VideoCapture<Recorder>, ImageAnalysis) -> Unit,
) {
    val future = ProcessCameraProvider.getInstance(context)
    future.addListener({
        val provider = future.get()

        /*
         * ONE SHAPE FOR ALL THREE STREAMS.
         *
         * Left alone, CameraX picks a resolution per use case: preview commonly 16:9,
         * analysis commonly 4:3, and the recorder whatever Quality.FHD is. That is three
         * different framings of the same scene, and an overlay measured in one of them
         * cannot be drawn correctly over another - which is why the trail drifted towards
         * the edges even once the rotation was right.
         *
         * 16:9 because that is what FHD records. The analysis frame is now a scaled-down
         * copy of the footage the scorer will watch, which is the only arrangement in
         * which "the ball was here" means the same thing on both.
         */
        /*
         * AND ONE SIZE EACH, ASKED FOR RATHER THAN ACCEPTED.
         *
         * Left to itself CameraX sizes the preview to the display, so a 1080p phone gets a
         * 1080p preview stream — every frame of which is produced, converted and composited
         * purely so somebody can check the aim. 720p is indistinguishable at arm's length
         * on a viewfinder and is a third of the pixels.
         *
         * The analysis stream is sized smaller still, because the tracker's first act is to
         * scale whatever it is handed down to 480px wide. Handing it 1080p means moving a
         * megabyte out of the hardware buffer to throw away three quarters of it; handing
         * it 640x360 asks the camera to do that scaling in silicon built for it.
         *
         * Neither touches the recording. The clip stays FHD, because that is the artefact
         * a review is built on.
         */
        fun sizedFor(target: android.util.Size) = ResolutionSelector.Builder()
            .setAspectRatioStrategy(AspectRatioStrategy.RATIO_16_9_FALLBACK_AUTO_STRATEGY)
            .setResolutionStrategy(
                ResolutionStrategy(
                    target,
                    // Prefer the nearest size at or below the target, but take a larger one
                    // over failing to bind: a bigger preview is a warm phone, no preview is
                    // a camera nobody can aim.
                    ResolutionStrategy.FALLBACK_RULE_CLOSEST_LOWER_THEN_HIGHER,
                ),
            )
            .build()

        val preview = Preview.Builder()
            .setResolutionSelector(sizedFor(android.util.Size(1280, 720)))
            .build()
            .also { it.setSurfaceProvider(view.surfaceProvider) }

        /*
         * ANALYSIS, alongside recording rather than after it.
         *
         * This is the entire argument for doing vision on the phone: the frames are
         * already here, in memory, stamped with the camera's own clock, while the ball is
         * being bowled. A server would have to be sent twenty megabytes and decode it
         * again to reach the same pixels.
         *
         * KEEP_ONLY_LATEST is the important flag. Under back pressure the analyser drops
         * frames rather than queueing them, so a slow phone loses ball points but never
         * delays the recording. Filming is the product; vision is bolted to the side of
         * it, and it yields.
         */
        val analysis = ImageAnalysis.Builder()
            .setResolutionSelector(sizedFor(android.util.Size(640, 360)))
            .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
            .setOutputImageFormat(ImageAnalysis.OUTPUT_IMAGE_FORMAT_YUV_420_888)
            .build()
            .also { it.setAnalyzer(analysisExecutor, onFrame) }
        // FULL HD, and the comment that used to sit here was wrong.
        //
        // It said "SD is deliberate" while the code asked for Quality.HD, which is 720p -
        // neither the SD it claimed nor the Full HD anybody assumed. A review is worth
        // having only if the ball is visible in it, and at 720p across a maidan it often
        // is not, so this now asks for what it should always have asked for.
        //
        // The fallback still steps down rather than up: a handset that cannot do 1080p
        // should film the delivery at 720p, not refuse to film it.
        val recorder = Recorder.Builder()
            .setQualitySelector(
                QualitySelector.from(
                    Quality.FHD,
                    androidx.camera.video.FallbackStrategy.lowerQualityOrHigherThan(Quality.HD),
                ),
            )
            .build()
        val videoCapture = VideoCapture.withOutput(recorder)
        runCatching {
            provider.unbindAll()
            provider.bindToLifecycle(
                lifecycleOwner,
                CameraSelector.DEFAULT_BACK_CAMERA,
                preview,
                videoCapture,
                analysis,
            )
            onReady(videoCapture, analysis)
        }
    }, ContextCompat.getMainExecutor(context))
}

private fun startClip(
    context: Context,
    capture: VideoCapture<Recorder>,
    executor: java.util.concurrent.Executor,
    targetFile: File,
    onFinished: (File, Long) -> Unit,
): Recording? {
    val options = androidx.camera.video.FileOutputOptions.Builder(targetFile).build()
    val startedAt = System.currentTimeMillis()

    return runCatching {
        capture.output
            .prepareRecording(context, options)
            .start(executor) { event ->
                if (event is VideoRecordEvent.Finalize) {
                    onFinished(targetFile, System.currentTimeMillis() - startedAt)
                }
            }
    }.getOrNull()
}
