package com.example.thanna.ui

import androidx.compose.animation.core.*
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

@Composable
fun LoginScreen() {
    val offsetY by animateFloatAsState(
        targetValue = 0f,
        animationSpec = tween(durationMillis = 1000, easing = FastOutSlowInEasing),
        label = "offsetY"
    )
    
    val fadeInAlpha by animateFloatAsState(
        targetValue = 1f,
        animationSpec = tween(durationMillis = 1200),
        label = "fadeInAlpha"
    )

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(Color(0xFF0055A5), Color(0xFF00A34A)) // Royal Blue to Vibrant Green
                )
            )
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            // Logo goes here
            Text(
                text = "Haraan",
                color = Color.White,
                fontSize = 40.sp,
                modifier = Modifier.padding(bottom = 32.dp)
            )
            
            // Your illustrations/icons grid could be placed here with fade-in effect
            
            Spacer(modifier = Modifier.height(16.dp))
            
            // Input Fields
            OutlinedTextField(
                value = "",
                onValueChange = {},
                label = { Text("10-digit mobile number") },
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 8.dp)
            )
            
            Button(
                onClick = {},
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 16.dp)
            ) {
                Text("Continue")
            }
        }
    }
}
