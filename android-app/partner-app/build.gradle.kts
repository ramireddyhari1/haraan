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
        versionCode = 1
        versionName = "1.0"
        // Live prod backend (same host the consumer app uses). The old EC2 IP
        // (13.204.63.181) is dead; partner endpoints live under /api/partner/*.
        buildConfigField("String", "API_BASE_URL", "\"https://haraan.app\"")
        // Shared OAuth Web client ID (android-app/gradle.properties). Blank → the
        // "Continue with Google" button hides itself, so this is never half-wired.
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

  // "Continue with Google" via Credential Manager (same stack as the member app).
  implementation("androidx.credentials:credentials:1.3.0")
  implementation("androidx.credentials:credentials-play-services-auth:1.3.0")
  implementation("com.google.android.libraries.identity.googleid:googleid:1.1.1")

  testImplementation(libs.junit)
}
