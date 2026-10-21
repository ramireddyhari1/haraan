plugins {
  alias(libs.plugins.android.application)
  alias(libs.plugins.compose.compiler)
}

android {
    namespace = "com.haraan.partner"
    compileSdk = 36
    defaultConfig {
        applicationId = "com.haraan.partner"
        minSdk = 24
        targetSdk = 36
        versionCode = 7
        versionName = "1.6"
        // Live prod backend (same host the consumer app uses). The old EC2 IP
        // (13.204.63.181) is dead; partner endpoints live under /api/partner/*.
        buildConfigField("String", "API_BASE_URL", "\"https://haraan.app\"")
        // Shared OAuth Web client ID (android-app/gradle.properties). Blank → the
        // "Continue with Google" button hides itself, so this is never half-wired.
        buildConfigField("String", "GOOGLE_WEB_CLIENT_ID", "\"${project.findProperty("GOOGLE_WEB_CLIENT_ID") ?: ""}\"")
    }

    // Every partner APK shipped so far is signed with this machine's Android debug key
    // (SHA-256 D1:8E:7C:16…). The release build MUST keep the same key, or installed
    // apps can't update in place: Android refuses an update signed by anyone else.
    // Back this keystore up; losing it means every partner reinstalls from scratch.
    // To move to a dedicated key later, set PARTNER_KEYSTORE / _PASSWORD / _ALIAS /
    // _KEY_PASSWORD in ~/.gradle/gradle.properties (and plan the one-time reinstall).
    signingConfigs {
        create("partnerRelease") {
            storeFile = file(
                (project.findProperty("PARTNER_KEYSTORE") as String?)
                    ?: "${System.getProperty("user.home")}/.android/debug.keystore"
            )
            storePassword = (project.findProperty("PARTNER_KEYSTORE_PASSWORD") as String?) ?: "android"
            keyAlias = (project.findProperty("PARTNER_KEY_ALIAS") as String?) ?: "androiddebugkey"
            keyPassword = (project.findProperty("PARTNER_KEY_PASSWORD") as String?) ?: "android"
        }
    }

    buildTypes {
        // Debug talks to a local `php artisan serve`, reached over
        // `adb reverse tcp:8000 tcp:8000` so 127.0.0.1 means the dev machine on
        // both emulator and a USB device. Point it somewhere else without
        // editing this file: -PPARTNER_API_BASE_URL=https://haraan.app
        debug {
            buildConfigField(
                "String",
                "API_BASE_URL",
                "\"${project.findProperty("PARTNER_API_BASE_URL") ?: "https://haraan.app"}\""
            )
        }
        // What partners install: not debuggable (no run-as reading the session,
        // no WebView inspection of the payment pages), BuildConfig.DEBUG = false.
        release {
            signingConfig = signingConfigs.getByName("partnerRelease")
            isDebuggable = false
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

  implementation(libs.androidx.core.ktx)
  implementation(libs.androidx.lifecycle.runtime.ktx)
  implementation(libs.androidx.lifecycle.runtime.compose)
  implementation(libs.androidx.lifecycle.viewmodel.compose)
  implementation(libs.androidx.activity.compose)

  implementation(libs.androidx.compose.ui)
  implementation(libs.androidx.compose.ui.tooling.preview)
  implementation(libs.androidx.compose.material3)
  implementation(libs.androidx.compose.material.icons.core)
  implementation(libs.androidx.compose.material.icons.extended)
  debugImplementation(libs.androidx.compose.ui.tooling)

  // QR ticket scanning — ships its own capture UI and handles the camera
  // runtime permission itself.
  implementation("com.journeyapps:zxing-android-embedded:4.3.0")

  // Venue photos on the venue cards (same loader as the member app).
  implementation(libs.coil.compose)
  // Live partner updates over Reverb (Pusher protocol WebSocket) — same version as the member app.
  implementation("com.squareup.okhttp3:okhttp:4.12.0")

  // "Continue with Google" via Credential Manager (same stack as the member app).
  implementation("androidx.credentials:credentials:1.3.0")
  implementation("androidx.credentials:credentials-play-services-auth:1.3.0")
  implementation("com.google.android.libraries.identity.googleid:googleid:1.1.1")

  testImplementation(libs.junit)
}
