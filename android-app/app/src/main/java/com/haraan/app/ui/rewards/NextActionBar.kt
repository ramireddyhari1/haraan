package com.haraan.app.ui.rewards

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.outlined.Stadium
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.ui.theme.PlusJakartaSans

/**
 * "I want to play again." Always on screen: a headline that answers the result, the streak
 * nudge with its real deadline, and the two things the app can actually do next — start a match
 * and book a turf.
 */
@Composable
internal fun NextActionBar(action: NextActionUi, modifier: Modifier = Modifier, onAction: (NextActionKind) -> Unit) {
    Column(
        modifier
            .fillMaxWidth()
            .background(
                Brush.verticalGradient(
                    0f to Color.Transparent,
                    0.14f to Board.Base,
                    1f to Board.Base,
                ),
            )
            .navigationBarsPadding()
            .padding(start = 16.dp, end = 16.dp, top = 32.dp, bottom = 14.dp),
    ) {
        Text(action.headline, color = Board.Ink, fontFamily = PlusJakartaSans, fontWeight = FontWeight.ExtraBold, fontSize = 17.sp, letterSpacing = (-0.3).sp)
        Spacer(Modifier.height(2.dp))
        Text(action.nudge, color = Board.InkMuted, fontFamily = PlusJakartaSans, fontSize = 12.5.sp, lineHeight = 17.sp)
        Spacer(Modifier.height(12.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            ActionButton(action.primaryLabel, primary = true, icon = Icons.Filled.Add, modifier = Modifier.weight(1.25f)) { onAction(action.primary) }
            ActionButton(action.secondaryLabel, primary = false, icon = Icons.Outlined.Stadium, modifier = Modifier.weight(1f)) { onAction(action.secondary) }
        }
    }
}
