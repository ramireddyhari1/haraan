package com.example.thanna.ui.components

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.layout.ContentScale
import coil.compose.AsyncImage
import com.example.thanna.ui.animations.haraanShimmer

@Composable
fun HaraanImage(
    model: Any?,
    contentDescription: String?,
    modifier: Modifier = Modifier,
    contentScale: ContentScale = ContentScale.Crop
) {
    var isLoading by remember { mutableStateOf(true) }
    
    Box(modifier = modifier) {
        AsyncImage(
            model = model,
            contentDescription = contentDescription,
            contentScale = contentScale,
            onState = { state ->
                isLoading = state is coil.compose.AsyncImagePainter.State.Loading
            },
            modifier = Modifier.fillMaxSize()
        )
        
        if (isLoading) {
            Box(modifier = Modifier.fillMaxSize().haraanShimmer())
        }
    }
}
