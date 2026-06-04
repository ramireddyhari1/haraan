package com.example.thanna.ui

import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.launch

@Composable
fun LoginScreen() {
    val alpha = remember { Animatable(0f) }
    val offsetY = remember { Animatable(300f) }

    LaunchedEffect(Unit) {
        // Run animations in parallel
        launch {
            alpha.animateTo(1f, animationSpec = tween(1000))
        }
        launch {
            offsetY.animateTo(0f, animationSpec = tween(1000))
        }
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(Color(0xFF0055A5), Color(0xFF00A34A))
                )
            )
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp)
                .offset(y = offsetY.value.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            Text(
                text = "Haraan",
                color = Color.White.copy(alpha = alpha.value),
                fontSize = 40.sp,
                modifier = Modifier.padding(bottom = 32.dp)
            )

            Spacer(modifier = Modifier.height(16.dp))

            OutlinedTextField(
                value = "",
                onValueChange = {},
                label = { Text("10-digit mobile number") },
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 8.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    unfocusedTextColor = Color.White.copy(alpha = alpha.value),
                    focusedTextColor = Color.White.copy(alpha = alpha.value),
                    unfocusedBorderColor = Color.White.copy(alpha = 0.5f * alpha.value),
                    focusedBorderColor = Color.White.copy(alpha = alpha.value),
                    unfocusedLabelColor = Color.White.copy(alpha = 0.7f * alpha.value),
                    focusedLabelColor = Color.White.copy(alpha = alpha.value)
                )
            )

            Button(
                onClick = {},
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 16.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = Color.White.copy(alpha = alpha.value),
                    contentColor = Color(0xFF0055A5).copy(alpha = alpha.value)
                )
            ) {
                Text("Continue")
            }
        }
    }
}
