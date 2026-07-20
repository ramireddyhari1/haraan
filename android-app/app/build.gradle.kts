plugins {
  alias(libs.plugins.android.application)
  alias(libs.plugins.compose.compiler)
  alias(libs.plugins.kotlin.serialization)
}

android {
    namespace = "com.haraan.app"
    compileSdk = 36
    defaultConfig {
        applicationId = "com.haraan.app"
        minSdk = 24
        targetSdk = 36
        versionCode = 3
        versionName = "1.0.2"
        // Points at the deployed server over HTTPS (nginx TLS -> Laravel) so the app works
        // on any device on any network without `adb reverse`. For local dev, switch back to
        // "http://127.0.0.1:8000" + `adb reverse tcp:8000 tcp:8000` (and temporarily allow
        // cleartext in the manifest).
        buildConfigField("String", "API_BASE_URL", "\"https://haraan.app\"")
        // Google Sign-In: the OAuth **Web application** client ID (serverClientId) from the
        // Google Cloud Console. The backend uses the same value as the token audience. Empty
        // until configured — the "Continue with Google" button hides itself when blank.
        buildConfigField("String", "GOOGLE_WEB_CLIENT_ID", "\"${project.findProperty("GOOGLE_WEB_CLIENT_ID") ?: ""}\"")
    }

    buildTypes {
        release {
            isMinifyEnabled = false
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    buildFeatures {
      compose = true
      aidl = false
      buildConfig = true
      shaders = false
    }

    packaging {
      resources {
        excludes += "/META-INF/{AL2.0,LGPL2.1}"
      }
    }
}

kotlin {
    jvmToolchain(17)
}

dependencies {
  val composeBom = platform(libs.androidx.compose.bom)
  implementation(composeBom)
  androidTestImplementation(composeBom)

  // Core Android dependencies
  implementation(libs.androidx.core.ktx)
  implementation(libs.androidx.lifecycle.runtime.ktx)
  implementation(libs.androidx.activity.compose)

  // Fused location (fresh GPS fix instead of the flaky LocationManager last-known)
  implementation("com.google.android.gms:play-services-location:21.3.0")

  // "Continue with Google" — Credential Manager + Sign in with Google
  implementation("androidx.credentials:credentials:1.3.0")
  implementation("androidx.credentials:credentials-play-services-auth:1.3.0")
  implementation("com.google.android.libraries.identity.googleid:googleid:1.1.1")

  // Razorpay Standard Checkout (native) — opens the payment sheet; the host Activity
  // receives the result via PaymentResultWithDataListener.
  implementation("com.razorpay:checkout:1.6.40")

  // Arch Components
  implementation(libs.androidx.lifecycle.runtime.compose)
  implementation(libs.androidx.lifecycle.viewmodel.compose)

  // Compose
  implementation(libs.androidx.compose.ui)
  implementation(libs.androidx.compose.ui.tooling.preview)
  implementation(libs.androidx.compose.material3)
  implementation(libs.androidx.compose.material.icons.core)
  implementation(libs.androidx.compose.material.icons.extended)
  implementation(libs.coil.compose)
  // QR code generation (attendee ticket QRs)
  implementation("com.google.zxing:core:3.5.3")
  // Tooling
  debugImplementation(libs.androidx.compose.ui.tooling)
  // Instrumented tests
  androidTestImplementation(libs.androidx.compose.ui.test.junit4)
  debugImplementation(libs.androidx.compose.ui.test.manifest)

  // Local tests: jUnit, coroutines, Android runner
  testImplementation(libs.junit)
  testImplementation(libs.kotlinx.coroutines.test)

  // Instrumented tests: jUnit rules and runners
  androidTestImplementation(libs.androidx.test.core)
  androidTestImplementation(libs.androidx.test.ext.junit)
  androidTestImplementation(libs.androidx.test.runner)
  androidTestImplementation(libs.androidx.test.espresso.core)

  // Navigation
  implementation(libs.androidx.navigation3.ui)
  implementation(libs.androidx.navigation3.runtime)
  implementation(libs.androidx.lifecycle.viewmodel.navigation3)
  // Security crypto for EncryptedSharedPreferences
  implementation("androidx.security:security-crypto:1.1.0")
  // WebSocket client for realtime content updates (Reverb / Pusher protocol)
  implementation("com.squareup.okhttp3:okhttp:4.12.0")
}
