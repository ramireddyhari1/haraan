package com.haraan.app.ui.profile

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.KeyboardArrowDown
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.ApiConfig
import com.haraan.app.data.StoredAccount
import com.haraan.app.ui.Feel
import com.haraan.app.ui.components.HaraanImage
import com.haraan.app.ui.pressable
import com.haraan.app.ui.theme.HaraanColors

private val Surface = HaraanColors.Surface
private val Text1 = HaraanColors.TextPrimary
private val Text2 = HaraanColors.TextSecondary
private val Text3 = HaraanColors.TextMuted
private val Stroke = HaraanColors.BorderLight
private val BlueBright = HaraanColors.EventsBlue

/**
 * The tappable "@handle ⌄" at the top of your own profile — Instagram's account chip.
 *
 * Renders the chevron only when there is more than one account on the device: a chevron
 * that opens a sheet listing one entry is a promise of a choice that isn't there. With a
 * single account it stays a plain heading that still opens the sheet, so "Add account"
 * remains reachable.
 */
@Composable
fun AccountChip(
  label: String,
  showChevron: Boolean,
  onClick: () -> Unit,
  modifier: Modifier = Modifier,
) {
  Row(
    modifier
      .clip(RoundedCornerShape(10.dp))
      .pressable(haptic = Feel.SELECT) { onClick() }
      .padding(horizontal = 10.dp, vertical = 6.dp),
    verticalAlignment = Alignment.CenterVertically,
  ) {
    Text(label, color = Text1, fontSize = 19.sp, fontWeight = FontWeight.Bold)
    if (showChevron) {
      Spacer(Modifier.width(4.dp))
      Icon(Icons.Filled.KeyboardArrowDown, "Switch account", tint = Text1, modifier = Modifier.size(22.dp))
    }
  }
}

/**
 * Account switcher.
 *
 * [switching] disables every row while a switch is in flight — tapping a second account
 * mid-switch would race two session writes against one token slot.
 */
@Composable
@OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)
fun AccountSwitcherSheet(
  accounts: List<StoredAccount>,
  activePlayerId: String?,
  canAdd: Boolean,
  switching: Boolean,
  onSwitch: (StoredAccount) -> Unit,
  onAdd: () -> Unit,
  onSignOut: (StoredAccount) -> Unit,
  onDismiss: () -> Unit,
) {
  val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
  var confirmSignOut by remember { mutableStateOf<StoredAccount?>(null) }

  ModalBottomSheet(
    onDismissRequest = onDismiss,
    sheetState = sheetState,
    containerColor = Surface,
    dragHandle = null,
  ) {
    Column(Modifier.fillMaxWidth().navigationBarsPadding().padding(bottom = 8.dp)) {
      Spacer(Modifier.height(10.dp))
      Box(Modifier.fillMaxWidth(), Alignment.Center) {
        Box(Modifier.width(38.dp).height(4.dp).clip(RoundedCornerShape(2.dp)).background(Stroke))
      }
      Spacer(Modifier.height(18.dp))
      Text(
        "Accounts",
        color = Text1,
        fontSize = 16.sp,
        fontWeight = FontWeight.Bold,
        modifier = Modifier.padding(horizontal = 20.dp),
      )
      Spacer(Modifier.height(6.dp))

      accounts.forEach { account ->
        val isActive = account.playerId == activePlayerId
        Row(
          Modifier
            .fillMaxWidth()
            .then(
              if (switching) Modifier
              else Modifier.pressable(haptic = Feel.SELECT) {
                if (isActive) onDismiss() else onSwitch(account)
              },
            )
            .padding(horizontal = 20.dp, vertical = 12.dp),
          verticalAlignment = Alignment.CenterVertically,
        ) {
          Box(Modifier.size(44.dp).clip(CircleShape).background(Stroke)) {
            val url = ApiConfig.mediaUrl(account.avatar)
            if (url != null) {
              HaraanImage(url, account.name, modifier = Modifier.size(44.dp))
            } else {
              Box(Modifier.size(44.dp), Alignment.Center) {
                Text(
                  account.name.take(1).uppercase(),
                  color = Text2,
                  fontSize = 17.sp,
                  fontWeight = FontWeight.Bold,
                )
              }
            }
          }
          Spacer(Modifier.width(12.dp))
          Column(Modifier.weight(1f)) {
            Text(account.handleOrId, color = Text1, fontSize = 15.sp, fontWeight = FontWeight.Bold)
            Text(account.name, color = Text3, fontSize = 12.5.sp)
          }
          if (isActive) {
            Icon(Icons.Filled.Check, "Active", tint = BlueBright, modifier = Modifier.size(21.dp))
            Spacer(Modifier.width(10.dp))
          }
          Text(
            "Sign out",
            color = if (switching) Text3 else HaraanColors.Danger,
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            modifier = Modifier
              .clip(RoundedCornerShape(8.dp))
              .then(if (switching) Modifier else Modifier.pressable { confirmSignOut = account })
              .padding(horizontal = 8.dp, vertical = 4.dp),
          )
        }
      }

      if (switching) {
        Row(
          Modifier.fillMaxWidth().padding(horizontal = 20.dp, vertical = 10.dp),
          verticalAlignment = Alignment.CenterVertically,
          horizontalArrangement = Arrangement.Center,
        ) {
          CircularProgressIndicator(color = BlueBright, strokeWidth = 2.dp, modifier = Modifier.size(16.dp))
          Spacer(Modifier.width(10.dp))
          Text("Switching…", color = Text2, fontSize = 13.sp)
        }
      }

      Box(Modifier.fillMaxWidth().padding(horizontal = 20.dp).height(1.dp).background(Stroke))

      Row(
        Modifier
          .fillMaxWidth()
          .then(if (canAdd && !switching) Modifier.pressable(haptic = Feel.SELECT) { onAdd() } else Modifier)
          .padding(horizontal = 20.dp, vertical = 16.dp),
        verticalAlignment = Alignment.CenterVertically,
      ) {
        Icon(
          Icons.Filled.Add,
          null,
          tint = if (canAdd) BlueBright else Text3,
          modifier = Modifier.size(20.dp),
        )
        Spacer(Modifier.width(12.dp))
        Column {
          Text(
            "Add account",
            color = if (canAdd) BlueBright else Text3,
            fontSize = 15.sp,
            fontWeight = FontWeight.Bold,
          )
          if (!canAdd) {
            Text("This device already holds the maximum of 5.", color = Text3, fontSize = 12.sp)
          }
        }
      }
    }
  }

  // Sign-out is account-wide by necessity: these tokens carry no per-device id, so the
  // only revocation available invalidates the account's sessions everywhere. Saying that
  // plainly beats a surprise sign-out on someone's other phone.
  confirmSignOut?.let { target ->
    AlertDialog(
      onDismissRequest = { confirmSignOut = null },
      title = { Text("Sign out of ${target.handleOrId}?", color = Text1, fontWeight = FontWeight.Bold) },
      text = {
        Text(
          "This removes the account from this device and ends its session on your other devices too. " +
            "Your other accounts here stay signed in.",
          color = Text2,
          fontSize = 14.sp,
        )
      },
      confirmButton = {
        TextButton(onClick = {
          val t = target
          confirmSignOut = null
          onSignOut(t)
        }) { Text("Sign out", color = HaraanColors.Danger, fontWeight = FontWeight.Bold) }
      },
      dismissButton = {
        TextButton(onClick = { confirmSignOut = null }) { Text("Cancel", color = Text2) }
      },
      containerColor = Surface,
    )
  }
}
