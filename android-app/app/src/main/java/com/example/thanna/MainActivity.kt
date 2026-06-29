package com.example.thanna

import android.content.Context
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.lifecycle.lifecycleScope
import com.example.thanna.data.LanguageManager
import com.example.thanna.data.RemoteBootstrap
import com.example.thanna.theme.ThannaTheme
import kotlinx.coroutines.launch

class MainActivity : ComponentActivity() {
  // Apply the user's chosen language to every resource lookup in this Activity.
  override fun attachBaseContext(newBase: Context) {
    super.attachBaseContext(LanguageManager.wrap(newBase))
  }

  override fun onCreate(savedInstanceState: Bundle?) {
    super.onCreate(savedInstanceState)

    // Load remote config (feature flags + theme) and the translation overlay at
    // launch. Best-effort: the in-memory stores keep their defaults if it fails.
    lifecycleScope.launch { RemoteBootstrap.load(applicationContext) }

    enableEdgeToEdge()
    setContent {
      ThannaTheme {
        MainNavigation()
      }
    }
  }
}
