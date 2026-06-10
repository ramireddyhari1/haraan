package com.example.thanna.ui

import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.example.thanna.R
import kotlinx.coroutines.delay
import kotlinx.coroutines.yield

@Composable
fun LoginRoute(
    onSkipClick: () -> Unit = {},
    onLoginSuccess: (String) -> Unit = {},
    modifier: Modifier = Modifier,
    viewModel: LoginViewModel = viewModel()
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()

    LoginScreen(
        uiState = uiState,
        onPhoneNumberChange = viewModel::onPhoneNumberChange,
        onOtpChange = viewModel::onOtpChange,
        onContinueClick = viewModel::requestOtp,
        onVerifyOtpClick = { viewModel.verifyOtp(onLoginSuccess) },
        onBackToPhoneClick = viewModel::resetToPhoneEntry,
        onSkipClick = onSkipClick,
        modifier = modifier
    )
}

@OptIn(ExperimentalFoundationApi::class)
@Composable
fun LoginScreen(
    uiState: LoginUiState,
    onPhoneNumberChange: (String) -> Unit,
    onOtpChange: (String) -> Unit,
    onContinueClick: () -> Unit,
    onVerifyOtpClick: () -> Unit,
    onBackToPhoneClick: () -> Unit,
    onSkipClick: () -> Unit = {},
    modifier: Modifier = Modifier
) {
    var isPhoneInputVisible by remember { mutableStateOf(false) }

    // Smooth transition weights for the overall screen balance
    // Maintain the 50/50 split when either the phone input is visible or in OTP stage
    val isBottomPanelExpanded = isPhoneInputVisible || uiState.stage == LoginStage.VerifyOtp
    val topWeight by animateFloatAsState(
        targetValue = if (isBottomPanelExpanded) 0.65f else 0.68f,
        animationSpec = tween(durationMillis = 350), label = "TopSpaceAnimation"
    )
    val bottomWeight by animateFloatAsState(
        targetValue = if (isBottomPanelExpanded) 0.35f else 0.32f,
        animationSpec = tween(durationMillis = 350), label = "BottomSpaceAnimation"
    )

    val posters = listOf(
        R.drawable.poster1,
        R.drawable.poster2,
        R.drawable.poster3
    )

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(Color(0xFFF8FAFC))
    ) {

        // --- TOP SECTION: Poster Scrolling ---
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .weight(topWeight)
        ) {
            // Using a large number for loop scrolling
            val pageCount = Int.MAX_VALUE
            val pagerState = rememberPagerState(
                initialPage = (pageCount / 2) - ((pageCount / 2) % posters.size),
                pageCount = { pageCount }
            )

            // Auto-scroll loop
            LaunchedEffect(Unit) {
                while (true) {
                    yield()
                    delay(3000) // 3 seconds interval
                    pagerState.animateScrollToPage(pagerState.currentPage + 1)
                }
            }

            HorizontalPager(
                state = pagerState,
                modifier = Modifier.fillMaxSize()
            ) { page ->
                val actualPage = page % posters.size
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    Image(
                        painter = painterResource(id = posters[actualPage]),
                        contentDescription = "Poster ${actualPage + 1}",
                        modifier = Modifier.fillMaxSize(),
                        contentScale = ContentScale.Crop
                    )
                }
            }

            // Skip Button at Top Right
            Button(
                onClick = onSkipClick,
                modifier = Modifier
                    .statusBarsPadding()
                    .padding(16.dp)
                    .align(Alignment.TopEnd)
                    .height(36.dp),
                shape = RoundedCornerShape(18.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = Color.White.copy(alpha = 0.5f),
                    contentColor = Color(0xFF2563EB)
                ),
                contentPadding = PaddingValues(horizontal = 16.dp, vertical = 0.dp)
            ) {
                Text(
                    text = "Skip",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold
                )
            }

            // Indicators
            Row(
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .padding(bottom = 24.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                repeat(posters.size) { iteration ->
                    val isSelected = (pagerState.currentPage % posters.size) == iteration
                    Box(
                        modifier = Modifier
                            .size(if (isSelected) 10.dp else 8.dp)
                            .clip(CircleShape)
                            .background(if (isSelected) Color(0xFFFFFFFF) else Color(0xFFCBD5E1).copy(alpha = 0.5f))
                    )
                }
            }
        }

        // --- BOTTOM SECTION: Premium Login Card ---
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .weight(bottomWeight),
            shape = RoundedCornerShape(32.dp),
            colors = CardDefaults.cardColors(containerColor = Color.White),
            elevation = CardDefaults.cardElevation(defaultElevation = 16.dp)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .navigationBarsPadding()
                    .padding(start = 24.dp, end = 24.dp, top = 24.dp, bottom = 16.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                
                Text(
                    text = "Haraan", 
                    fontSize = 26.sp,
                    fontWeight = FontWeight.Black,
                    color = Color(0xFF0F172A),
                    modifier = Modifier.padding(bottom = 4.dp)
                )
                
                Text(
                    text = "Crafting Premium Experiences",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = Color(0xFF334155),
                    textAlign = TextAlign.Center
                )
                
                Spacer(modifier = Modifier.height(6.dp))
                
                Text(
                    text = if (uiState.stage == LoginStage.EnterPhone) {
                        if (!isPhoneInputVisible) "Login or sign up to continue" else "Enter your mobile number to verify"
                    } else {
                        "Enter the 6-digit OTP code sent via WhatsApp"
                    },
                    fontSize = 14.sp,
                    color = Color(0xFF64748B),
                    textAlign = TextAlign.Center
                )

                if (uiState.stage == LoginStage.EnterPhone) {
                    if (!isPhoneInputVisible) {
                        Spacer(modifier = Modifier.weight(1f))
                        
                        Button(
                            onClick = { isPhoneInputVisible = true },
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(54.dp),
                            shape = RoundedCornerShape(16.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF2563EB))
                        ) {
                            Text(
                                text = "Continue with mobile number",
                                fontSize = 16.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = Color.White
                            )
                        }
                        
                        Spacer(modifier = Modifier.weight(1f))
                        
                    } else {
                        Spacer(modifier = Modifier.height(32.dp)) 
                        
                        Column(
                            modifier = Modifier.fillMaxWidth(),
                            verticalArrangement = Arrangement.spacedBy(16.dp)
                        ) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(12.dp)
                            ) {
                                OutlinedTextField(
                                    value = "🇮🇳 +91",
                                    onValueChange = {},
                                    readOnly = true,
                                    modifier = Modifier.width(100.dp),
                                    shape = RoundedCornerShape(14.dp),
                                    colors = OutlinedTextFieldDefaults.colors(
                                        focusedBorderColor = Color(0xFFCBD5E1),
                                        unfocusedBorderColor = Color(0xFFE2E8F0),
                                        focusedContainerColor = Color(0xFFF8FAFC),
                                        unfocusedContainerColor = Color(0xFFF8FAFC)
                                    ),
                                    textStyle = LocalTextStyle.current.copy(
                                        textAlign = TextAlign.Center, 
                                        fontWeight = FontWeight.Medium
                                    )
                                )

                                OutlinedTextField(
                                    value = uiState.phoneNumber,
                                    onValueChange = onPhoneNumberChange,
                                    placeholder = { Text("Enter mobile number", color = Color(0xFF94A3B8)) },
                                    modifier = Modifier.fillMaxWidth(),
                                    shape = RoundedCornerShape(14.dp),
                                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                                    singleLine = true,
                                    colors = OutlinedTextFieldDefaults.colors(
                                        focusedBorderColor = Color(0xFF2563EB),
                                        unfocusedBorderColor = Color(0xFFCBD5E1)
                                    )
                                )
                            }

                            // Error feedback
                            if (!uiState.errorMessage.isNullOrEmpty()) {
                                Text(
                                    text = uiState.errorMessage,
                                    color = Color(0xFFDC2626),
                                    fontSize = 13.sp,
                                    fontWeight = FontWeight.Medium,
                                    modifier = Modifier.fillMaxWidth(),
                                    textAlign = TextAlign.Start
                                )
                            }

                            Button(
                                onClick = onContinueClick,
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .height(54.dp),
                                shape = RoundedCornerShape(14.dp),
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = Color(0xFF2563EB),
                                    disabledContainerColor = Color(0xFFE2E8F0)
                                ),
                                enabled = uiState.canContinue
                            ) {
                                Text(
                                    text = if (uiState.isLoading) "Please wait..." else "Proceed",
                                    fontSize = 16.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = if (uiState.canContinue) Color.White else Color(0xFF94A3B8)
                                )
                            }
                        }
                        
                        Spacer(modifier = Modifier.weight(1f)) 
                    }
                } else {
                    // OTP Verification Stage
                    Spacer(modifier = Modifier.height(32.dp))
                    
                    Column(
                        modifier = Modifier.fillMaxWidth(),
                        verticalArrangement = Arrangement.spacedBy(16.dp),
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        OtpEntryRow(
                            otp = uiState.otp,
                            onOtpChange = onOtpChange,
                            modifier = Modifier.fillMaxWidth()
                        )

                        // Error feedback
                        if (!uiState.errorMessage.isNullOrEmpty()) {
                            Text(
                                text = uiState.errorMessage,
                                color = Color(0xFFDC2626),
                                fontSize = 13.sp,
                                fontWeight = FontWeight.Medium,
                                modifier = Modifier.fillMaxWidth(),
                                textAlign = TextAlign.Start
                            )
                        }

                        // Success feedback
                        if (!uiState.successMessage.isNullOrEmpty()) {
                            Text(
                                text = uiState.successMessage,
                                color = Color(0xFF16A34A),
                                fontSize = 13.sp,
                                fontWeight = FontWeight.Medium,
                                modifier = Modifier.fillMaxWidth(),
                                textAlign = TextAlign.Start
                            )
                        }

                        Button(
                            onClick = onVerifyOtpClick,
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(54.dp),
                            shape = RoundedCornerShape(14.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = Color(0xFF2563EB),
                                disabledContainerColor = Color(0xFFE2E8F0)
                            ),
                            enabled = uiState.isOtpValid && !uiState.isLoading
                        ) {
                            Text(
                                text = if (uiState.isLoading) "Verifying..." else "Verify & Proceed",
                                fontSize = 16.sp,
                                fontWeight = FontWeight.Bold,
                                color = if (uiState.isOtpValid && !uiState.isLoading) Color.White else Color(0xFF94A3B8)
                            )
                        }

                        TextButton(
                            onClick = onBackToPhoneClick,
                            modifier = Modifier.height(36.dp)
                        ) {
                            Text(
                                text = "Change mobile number",
                                color = Color(0xFF2563EB),
                                fontSize = 14.sp,
                                fontWeight = FontWeight.SemiBold
                            )
                        }
                    }

                    Spacer(modifier = Modifier.weight(1f))
                }

                Text(
                    text = "By continuing, you agree to our Terms and conditions",
                    fontSize = 11.sp,
                    color = Color(0xFF94A3B8),
                    textAlign = TextAlign.Center,
                    modifier = Modifier.padding(bottom = 4.dp)
                )
            }
        }
    }
}

@Composable
fun OtpEntryRow(
    otp: String,
    onOtpChange: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    BasicTextField(
        value = otp,
        onValueChange = {
            if (it.length <= 6) {
                onOtpChange(it)
            }
        },
        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
        decorationBox = {
            Row(
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                modifier = modifier
            ) {
                repeat(6) { index ->
                    val char = when {
                        index >= otp.length -> ""
                        else -> otp[index].toString()
                    }
                    val isFocused = otp.length == index
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .height(56.dp)
                            .background(Color(0xFFF8FAFC), RoundedCornerShape(12.dp))
                            .border(
                                width = if (isFocused) 2.dp else 1.dp,
                                color = if (isFocused) Color(0xFF2563EB) else Color(0xFFCBD5E1),
                                shape = RoundedCornerShape(12.dp)
                            ),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = char,
                            fontSize = 20.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color(0xFF0F172A)
                        )
                    }
                }
            }
        }
    )
}
