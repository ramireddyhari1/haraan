package com.haraan.app

import android.content.Context
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.lifecycle.lifecycleScope
import com.haraan.app.data.LanguageManager
import com.haraan.app.data.RealtimeClient
import com.haraan.app.data.RemoteBootstrap
import com.haraan.app.data.RemoteConfigStore
import com.haraan.app.theme.ThannaTheme
import kotlinx.coroutines.launch

class MainActivity : ComponentActivity() {
  // Apply the user's chosen language to every resource lookup in this Activity.
  override fun attachBaseContext(newBase: Context) {
    super.attachBaseContext(LanguageManager.wrap(newBase))
  }

  override fun onCreate(savedInstanceState: Bundle?) {
    super.onCreate(savedInstanceState)

    // Load remote config (feature flags + theme) and the translation overlay at
    // launch, then open the realtime socket so later admin changes arrive live.
    // Best-effort: the in-memory stores keep their defaults if it fails.
    lifecycleScope.launch {
      RemoteBootstrap.load(applicationContext)
      RealtimeClient.start(applicationContext, RemoteConfigStore.config.realtime)
    }

    enableEdgeToEdge()
    setContent {
      ThannaTheme {
        MainNavigation()
      }
    }
  }
}
