package com.example.thanna.ui.main

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.MyLocation
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.thanna.data.CityOption
import com.example.thanna.data.LocationState

private val popularCities = listOf(
    "Chennai", "Coimbatore", "Madurai", "Trichy", "Salem",
    "Tirunelveli", "Vellore", "Erode", "Thoothukudi", "Tiruppur"
).map { CityOption(it) }

// 0 == "Any distance".
private val radiusOptions = listOf(2, 5, 10, 25, 0)
private val accent = Color(0xFF2563EB)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun LocationPickerSheet(
    state: LocationState,
    recents: List<CityOption>,
    selectedRadiusKm: Int,
    onRadiusChange: (Int) -> Unit,
    onUseCurrentLocation: () -> Unit,
    onSelectCity: (CityOption) -> Unit,
    onDismiss: () -> Unit,
) {
    var citySearch by remember { mutableStateOf("") }
    val q = citySearch.trim()
    val filteredRecents = recents.filter { q.isBlank() || it.name.contains(q, ignoreCase = true) }
    val filteredPopular = popularCities.filter { q.isBlank() || it.name.contains(q, ignoreCase = true) }

    ModalBottomSheet(onDismissRequest = onDismiss) {
        Column(modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp)) {
            Text(
                "Choose Location",
                fontWeight = FontWeight.Bold,
                fontSize = 18.sp,
                modifier = Modifier.padding(bottom = 16.dp),
            )

            // Use current location row
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable(onClick = onUseCurrentLocation)
                    .padding(vertical = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                Icon(Icons.Default.MyLocation, contentDescription = null, tint = accent)
                Column {
                    Text("Use current location", fontWeight = FontWeight.Medium)
                    if (state is LocationState.Locating) {
                        Text("Detecting…", fontSize = 12.sp, color = Color(0xFF64748B))
                    }
                }
            }

            HorizontalDivider(modifier = Modifier.padding(vertical = 8.dp))

            // Search radius selector (Phase 3)
            Text("Search radius", fontWeight = FontWeight.SemiBold, fontSize = 13.sp, color = Color(0xFF64748B))
            Spacer(Modifier.height(8.dp))
            Row(
                modifier = Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                radiusOptions.forEach { km ->
                    RadiusChip(
                        label = if (km == 0) "Any" else "$km km",
                        selected = km == selectedRadiusKm,
                        onClick = { onRadiusChange(km) },
                    )
                }
            }

            HorizontalDivider(modifier = Modifier.padding(vertical = 12.dp))

            // City search
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .background(Color(0xFFF1F5F9))
                    .padding(horizontal = 12.dp, vertical = 10.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                Icon(Icons.Default.Search, contentDescription = null, tint = Color(0xFF94A3B8), modifier = Modifier.size(18.dp))
                BasicTextField(
                    value = citySearch,
                    onValueChange = { citySearch = it },
                    modifier = Modifier.weight(1f),
                    singleLine = true,
                    cursorBrush = SolidColor(accent),
                    decorationBox = { inner ->
                        if (citySearch.isEmpty()) {
                            Text("Search a city", color = Color(0xFF94A3B8), fontSize = 14.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
                        }
                        inner()
                    },
                )
            }

            Spacer(Modifier.height(12.dp))

            if (filteredRecents.isNotEmpty()) {
                Text("Recent", fontWeight = FontWeight.SemiBold, fontSize = 13.sp, color = Color(0xFF64748B))
                filteredRecents.forEach { city ->
                    CityRow(city, onSelectCity)
                }
                HorizontalDivider(modifier = Modifier.padding(vertical = 8.dp))
            }

            Text("Popular Cities", fontWeight = FontWeight.SemiBold, fontSize = 13.sp, color = Color(0xFF64748B))
            LazyColumn(modifier = Modifier.heightIn(max = 260.dp)) {
                items(filteredPopular) { city ->
                    CityRow(city, onSelectCity)
                }
                if (filteredPopular.isEmpty()) {
                    item {
                        Text(
                            "No cities match \"$q\"",
                            fontSize = 13.sp,
                            color = Color(0xFF94A3B8),
                            modifier = Modifier.padding(vertical = 14.dp),
                        )
                    }
                }
            }
            Spacer(Modifier.height(32.dp))
        }
    }
}

@Composable
private fun RadiusChip(label: String, selected: Boolean, onClick: () -> Unit) {
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(50))
            .background(if (selected) accent else Color(0xFFF1F5F9))
            .border(1.dp, if (selected) accent else Color(0xFFE2E8F0), RoundedCornerShape(50))
            .clickable(onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 8.dp),
    ) {
        Text(
            label,
            color = if (selected) Color.White else Color(0xFF475569),
            fontSize = 13.sp,
            fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
        )
    }
}

@Composable
private fun CityRow(city: CityOption, onSelect: (CityOption) -> Unit) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onSelect(city) }
            .padding(vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Icon(Icons.Default.LocationOn, contentDescription = null, tint = Color(0xFF94A3B8), modifier = Modifier.size(18.dp))
        Text(city.name, fontSize = 15.sp)
    }
}
