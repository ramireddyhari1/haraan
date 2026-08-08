package com.haraan.app.ui.profile

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.KeyboardArrowDown
import androidx.compose.material.icons.filled.PhotoCamera
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.haraan.app.data.UsernameCheck
import coil.compose.AsyncImage
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import com.haraan.app.ui.theme.HaraanColors

// The canvas is WHITE and the inputs are filled — not the other way round. Previously
// white fields sat on an #EBEBF0 canvas, so every control read as a separate floating
// box and the screen looked like a bare form rather than a page. Filled-on-white gives
// the fields a quiet grouped rhythm and lets the blue accents actually carry.
private val Bg = HaraanColors.Surface
private val Surface = HaraanColors.Surface
private val FieldFill = HaraanColors.Background
private val Blue = HaraanColors.EventsBlue
private val Green = HaraanColors.Success
private val Text1 = HaraanColors.TextPrimary
private val Text2 = HaraanColors.TextSecondary
private val Text3 = HaraanColors.TextMuted
private val Stroke = HaraanColors.BorderLight
private val Track = HaraanColors.BorderLight
private val BlueTint = HaraanColors.AccentTint
private val Disabled = HaraanColors.BorderLight
private val DashRing = Color(0xFFC7D0DE)

private val INDIAN_STATES = listOf(
    "Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chhattisgarh", "Goa", "Gujarat",
    "Haryana", "Himachal Pradesh", "Jharkhand", "Karnataka", "Kerala", "Madhya Pradesh",
    "Maharashtra", "Manipur", "Meghalaya", "Mizoram", "Nagaland", "Odisha", "Punjab", "Rajasthan",
    "Sikkim", "Tamil Nadu", "Telangana", "Tripura", "Uttar Pradesh", "Uttarakhand", "West Bengal",
    "Delhi", "Jammu & Kashmir", "Ladakh", "Puducherry", "Chandigarh",
)

// District options keyed by the exact state string above. The District dropdown is filtered
// by the chosen state so players only ever see real districts for their home state.
private val DISTRICTS_BY_STATE: Map<String, List<String>> = mapOf(
    "Andhra Pradesh" to listOf(
        "Alluri Sitharama Raju", "Anakapalli", "Ananthapuramu", "Annamayya", "Bapatla", "Chittoor",
        "Dr. B.R. Ambedkar Konaseema", "East Godavari", "Eluru", "Guntur", "Kakinada", "Krishna",
        "Kurnool", "Nandyal", "NTR", "Palnadu", "Parvathipuram Manyam", "Prakasam", "SPSR Nellore",
        "Sri Sathya Sai", "Srikakulam", "Tirupati", "Visakhapatnam", "Vizianagaram", "West Godavari",
        "YSR Kadapa",
    ),
    "Arunachal Pradesh" to listOf(
        "Anjaw", "Changlang", "Dibang Valley", "East Kameng", "East Siang", "Kamle", "Kra Daadi",
        "Kurung Kumey", "Lepa Rada", "Lohit", "Longding", "Lower Dibang Valley", "Lower Siang",
        "Lower Subansiri", "Namsai", "Pakke-Kessang", "Papum Pare", "Shi Yomi", "Siang", "Tawang",
        "Tirap", "Upper Siang", "Upper Subansiri", "West Kameng", "West Siang",
    ),
    "Assam" to listOf(
        "Bajali", "Baksa", "Barpeta", "Biswanath", "Bongaigaon", "Cachar", "Charaideo", "Chirang",
        "Darrang", "Dhemaji", "Dhubri", "Dibrugarh", "Dima Hasao", "Goalpara", "Golaghat", "Hailakandi",
        "Hojai", "Jorhat", "Kamrup", "Kamrup Metropolitan", "Karbi Anglong", "Karimganj", "Kokrajhar",
        "Lakhimpur", "Majuli", "Morigaon", "Nagaon", "Nalbari", "Sivasagar", "Sonitpur",
        "South Salmara-Mankachar", "Tinsukia", "Udalguri", "West Karbi Anglong",
    ),
    "Bihar" to listOf(
        "Araria", "Arwal", "Aurangabad", "Banka", "Begusarai", "Bhagalpur", "Bhojpur", "Buxar",
        "Darbhanga", "East Champaran", "Gaya", "Gopalganj", "Jamui", "Jehanabad", "Kaimur", "Katihar",
        "Khagaria", "Kishanganj", "Lakhisarai", "Madhepura", "Madhubani", "Munger", "Muzaffarpur",
        "Nalanda", "Nawada", "Patna", "Purnia", "Rohtas", "Saharsa", "Samastipur", "Saran", "Sheikhpura",
        "Sheohar", "Sitamarhi", "Siwan", "Supaul", "Vaishali", "West Champaran",
    ),
    "Chhattisgarh" to listOf(
        "Balod", "Baloda Bazar", "Balrampur", "Bastar", "Bemetara", "Bijapur", "Bilaspur",
        "Dantewada", "Dhamtari", "Durg", "Gariaband", "Gaurela-Pendra-Marwahi", "Janjgir-Champa",
        "Jashpur", "Kabirdham", "Kanker", "Kondagaon", "Korba", "Koriya", "Mahasamund", "Mungeli",
        "Narayanpur", "Raigarh", "Raipur", "Rajnandgaon", "Sukma", "Surajpur", "Surguja",
    ),
    "Goa" to listOf("North Goa", "South Goa"),
    "Gujarat" to listOf(
        "Ahmedabad", "Amreli", "Anand", "Aravalli", "Banaskantha", "Bharuch", "Bhavnagar", "Botad",
        "Chhota Udaipur", "Dahod", "Dang", "Devbhoomi Dwarka", "Gandhinagar", "Gir Somnath", "Jamnagar",
        "Junagadh", "Kheda", "Kutch", "Mahisagar", "Mehsana", "Morbi", "Narmada", "Navsari", "Panchmahal",
        "Patan", "Porbandar", "Rajkot", "Sabarkantha", "Surat", "Surendranagar", "Tapi", "Vadodara", "Valsad",
    ),
    "Haryana" to listOf(
        "Ambala", "Bhiwani", "Charkhi Dadri", "Faridabad", "Fatehabad", "Gurugram", "Hisar", "Jhajjar",
        "Jind", "Kaithal", "Karnal", "Kurukshetra", "Mahendragarh", "Nuh", "Palwal", "Panchkula",
        "Panipat", "Rewari", "Rohtak", "Sirsa", "Sonipat", "Yamunanagar",
    ),
    "Himachal Pradesh" to listOf(
        "Bilaspur", "Chamba", "Hamirpur", "Kangra", "Kinnaur", "Kullu", "Lahaul and Spiti", "Mandi",
        "Shimla", "Sirmaur", "Solan", "Una",
    ),
    "Jharkhand" to listOf(
        "Bokaro", "Chatra", "Deoghar", "Dhanbad", "Dumka", "East Singhbhum", "Garhwa", "Giridih",
        "Godda", "Gumla", "Hazaribagh", "Jamtara", "Khunti", "Koderma", "Latehar", "Lohardaga",
        "Pakur", "Palamu", "Ramgarh", "Ranchi", "Sahebganj", "Seraikela Kharsawan", "Simdega",
        "West Singhbhum",
    ),
    "Karnataka" to listOf(
        "Bagalkot", "Ballari", "Belagavi", "Bengaluru Rural", "Bengaluru Urban", "Bidar", "Chamarajanagar",
        "Chikkaballapur", "Chikkamagaluru", "Chitradurga", "Dakshina Kannada", "Davanagere", "Dharwad",
        "Gadag", "Hassan", "Haveri", "Kalaburagi", "Kodagu", "Kolar", "Koppal", "Mandya", "Mysuru",
        "Raichur", "Ramanagara", "Shivamogga", "Tumakuru", "Udupi", "Uttara Kannada", "Vijayanagara",
        "Vijayapura", "Yadgir",
    ),
    "Kerala" to listOf(
        "Alappuzha", "Ernakulam", "Idukki", "Kannur", "Kasaragod", "Kollam", "Kottayam", "Kozhikode",
        "Malappuram", "Palakkad", "Pathanamthitta", "Thiruvananthapuram", "Thrissur", "Wayanad",
    ),
    "Madhya Pradesh" to listOf(
        "Agar Malwa", "Alirajpur", "Anuppur", "Ashoknagar", "Balaghat", "Barwani", "Betul", "Bhind",
        "Bhopal", "Burhanpur", "Chhatarpur", "Chhindwara", "Damoh", "Datia", "Dewas", "Dhar", "Dindori",
        "Guna", "Gwalior", "Harda", "Indore", "Jabalpur", "Jhabua", "Katni", "Khandwa", "Khargone",
        "Mandla", "Mandsaur", "Morena", "Narmadapuram", "Narsinghpur", "Neemuch", "Niwari", "Panna",
        "Raisen", "Rajgarh", "Ratlam", "Rewa", "Sagar", "Satna", "Sehore", "Seoni", "Shahdol", "Shajapur",
        "Sheopur", "Shivpuri", "Sidhi", "Singrauli", "Tikamgarh", "Ujjain", "Umaria", "Vidisha",
    ),
    "Maharashtra" to listOf(
        "Ahmednagar", "Akola", "Amravati", "Beed", "Bhandara", "Buldhana", "Chandrapur",
        "Chhatrapati Sambhajinagar", "Dharashiv", "Dhule", "Gadchiroli", "Gondia", "Hingoli", "Jalgaon",
        "Jalna", "Kolhapur", "Latur", "Mumbai City", "Mumbai Suburban", "Nagpur", "Nanded", "Nandurbar",
        "Nashik", "Palghar", "Parbhani", "Pune", "Raigad", "Ratnagiri", "Sangli", "Satara", "Sindhudurg",
        "Solapur", "Thane", "Wardha", "Washim", "Yavatmal",
    ),
    "Manipur" to listOf(
        "Bishnupur", "Chandel", "Churachandpur", "Imphal East", "Imphal West", "Jiribam", "Kakching",
        "Kamjong", "Kangpokpi", "Noney", "Pherzawl", "Senapati", "Tamenglong", "Tengnoupal", "Thoubal",
        "Ukhrul",
    ),
    "Meghalaya" to listOf(
        "East Garo Hills", "East Jaintia Hills", "East Khasi Hills", "Eastern West Khasi Hills",
        "North Garo Hills", "Ri Bhoi", "South Garo Hills", "South West Garo Hills", "South West Khasi Hills",
        "West Garo Hills", "West Jaintia Hills", "West Khasi Hills",
    ),
    "Mizoram" to listOf(
        "Aizawl", "Champhai", "Hnahthial", "Khawzawl", "Kolasib", "Lawngtlai", "Lunglei", "Mamit",
        "Saiha", "Saitual", "Serchhip",
    ),
    "Nagaland" to listOf(
        "Chumoukedima", "Dimapur", "Kiphire", "Kohima", "Longleng", "Mokokchung", "Mon", "Niuland",
        "Noklak", "Peren", "Phek", "Shamator", "Tseminyu", "Tuensang", "Wokha", "Zunheboto",
    ),
    "Odisha" to listOf(
        "Angul", "Balangir", "Balasore", "Bargarh", "Bhadrak", "Boudh", "Cuttack", "Deogarh", "Dhenkanal",
        "Gajapati", "Ganjam", "Jagatsinghpur", "Jajpur", "Jharsuguda", "Kalahandi", "Kandhamal",
        "Kendrapara", "Kendujhar", "Khordha", "Koraput", "Malkangiri", "Mayurbhanj", "Nabarangpur",
        "Nayagarh", "Nuapada", "Puri", "Rayagada", "Sambalpur", "Subarnapur", "Sundargarh",
    ),
    "Punjab" to listOf(
        "Amritsar", "Barnala", "Bathinda", "Faridkot", "Fatehgarh Sahib", "Fazilka", "Ferozepur",
        "Gurdaspur", "Hoshiarpur", "Jalandhar", "Kapurthala", "Ludhiana", "Malerkotla", "Mansa", "Moga",
        "Pathankot", "Patiala", "Rupnagar", "Sahibzada Ajit Singh Nagar", "Sangrur",
        "Shaheed Bhagat Singh Nagar", "Sri Muktsar Sahib", "Tarn Taran",
    ),
    "Rajasthan" to listOf(
        "Ajmer", "Alwar", "Banswara", "Baran", "Barmer", "Bharatpur", "Bhilwara", "Bikaner", "Bundi",
        "Chittorgarh", "Churu", "Dausa", "Dholpur", "Dungarpur", "Hanumangarh", "Jaipur", "Jaisalmer",
        "Jalore", "Jhalawar", "Jhunjhunu", "Jodhpur", "Karauli", "Kota", "Nagaur", "Pali", "Pratapgarh",
        "Rajsamand", "Sawai Madhopur", "Sikar", "Sirohi", "Sri Ganganagar", "Tonk", "Udaipur",
    ),
    "Sikkim" to listOf(
        "Gangtok", "Gyalshing", "Mangan", "Namchi", "Pakyong", "Soreng",
    ),
    "Tamil Nadu" to listOf(
        "Ariyalur", "Chengalpattu", "Chennai", "Coimbatore", "Cuddalore", "Dharmapuri", "Dindigul",
        "Erode", "Kallakurichi", "Kanchipuram", "Kanyakumari", "Karur", "Krishnagiri", "Madurai",
        "Mayiladuthurai", "Nagapattinam", "Namakkal", "Nilgiris", "Perambalur", "Pudukkottai",
        "Ramanathapuram", "Ranipet", "Salem", "Sivaganga", "Tenkasi", "Thanjavur", "Theni", "Thoothukudi",
        "Tiruchirappalli", "Tirunelveli", "Tirupathur", "Tiruppur", "Tiruvallur", "Tiruvannamalai",
        "Tiruvarur", "Vellore", "Viluppuram", "Virudhunagar",
    ),
    "Telangana" to listOf(
        "Adilabad", "Bhadradri Kothagudem", "Hanumakonda", "Hyderabad", "Jagtial", "Jangaon",
        "Jayashankar Bhupalpally", "Jogulamba Gadwal", "Kamareddy", "Karimnagar", "Khammam",
        "Komaram Bheem", "Mahabubabad", "Mahabubnagar", "Mancherial", "Medak", "Medchal-Malkajgiri",
        "Mulugu", "Nagarkurnool", "Nalgonda", "Narayanpet", "Nirmal", "Nizamabad", "Peddapalli",
        "Rajanna Sircilla", "Rangareddy", "Sangareddy", "Siddipet", "Suryapet", "Vikarabad", "Wanaparthy",
        "Warangal", "Yadadri Bhuvanagiri",
    ),
    "Tripura" to listOf(
        "Dhalai", "Gomati", "Khowai", "North Tripura", "Sepahijala", "South Tripura", "Unakoti",
        "West Tripura",
    ),
    "Uttar Pradesh" to listOf(
        "Agra", "Aligarh", "Ambedkar Nagar", "Amethi", "Amroha", "Auraiya", "Ayodhya", "Azamgarh",
        "Baghpat", "Bahraich", "Ballia", "Balrampur", "Banda", "Barabanki", "Bareilly", "Basti",
        "Bhadohi", "Bijnor", "Budaun", "Bulandshahr", "Chandauli", "Chitrakoot", "Deoria", "Etah",
        "Etawah", "Farrukhabad", "Fatehpur", "Firozabad", "Gautam Buddha Nagar", "Ghaziabad", "Ghazipur",
        "Gonda", "Gorakhpur", "Hamirpur", "Hapur", "Hardoi", "Hathras", "Jalaun", "Jaunpur", "Jhansi",
        "Kannauj", "Kanpur Dehat", "Kanpur Nagar", "Kasganj", "Kaushambi", "Kushinagar", "Lakhimpur Kheri",
        "Lalitpur", "Lucknow", "Maharajganj", "Mahoba", "Mainpuri", "Mathura", "Mau", "Meerut", "Mirzapur",
        "Moradabad", "Muzaffarnagar", "Pilibhit", "Pratapgarh", "Prayagraj", "Raebareli", "Rampur",
        "Saharanpur", "Sambhal", "Sant Kabir Nagar", "Shahjahanpur", "Shamli", "Shravasti", "Siddharthnagar",
        "Sitapur", "Sonbhadra", "Sultanpur", "Unnao", "Varanasi",
    ),
    "Uttarakhand" to listOf(
        "Almora", "Bageshwar", "Chamoli", "Champawat", "Dehradun", "Haridwar", "Nainital",
        "Pauri Garhwal", "Pithoragarh", "Rudraprayag", "Tehri Garhwal", "Udham Singh Nagar", "Uttarkashi",
    ),
    "West Bengal" to listOf(
        "Alipurduar", "Bankura", "Birbhum", "Cooch Behar", "Dakshin Dinajpur", "Darjeeling", "Hooghly",
        "Howrah", "Jalpaiguri", "Jhargram", "Kalimpong", "Kolkata", "Malda", "Murshidabad", "Nadia",
        "North 24 Parganas", "Paschim Bardhaman", "Paschim Medinipur", "Purba Bardhaman", "Purba Medinipur",
        "Purulia", "South 24 Parganas", "Uttar Dinajpur",
    ),
    "Delhi" to listOf(
        "Central Delhi", "East Delhi", "New Delhi", "North Delhi", "North East Delhi", "North West Delhi",
        "Shahdara", "South Delhi", "South East Delhi", "South West Delhi", "West Delhi",
    ),
    "Jammu & Kashmir" to listOf(
        "Anantnag", "Bandipora", "Baramulla", "Budgam", "Doda", "Ganderbal", "Jammu", "Kathua", "Kishtwar",
        "Kulgam", "Kupwara", "Poonch", "Pulwama", "Rajouri", "Ramban", "Reasi", "Samba", "Shopian",
        "Srinagar", "Udhampur",
    ),
    "Ladakh" to listOf("Kargil", "Leh"),
    "Puducherry" to listOf("Karaikal", "Mahe", "Puducherry", "Yanam"),
    "Chandigarh" to listOf("Chandigarh"),
)

// ActionBoard is multi-sport. The player picks one primary sport, then fills only that sport's
// fields. Attribute keys here mirror the backend's User::SPORT_REQUIRED_ATTRS.
private data class Sport(val name: String, val emoji: String)

private val SPORTS = listOf(
    Sport("Cricket", "🏏"), Sport("Football", "⚽"), Sport("Badminton", "🏸"), Sport("Basketball", "🏀"),
)
private val SPORT_REQUIRED: Map<String, List<String>> = mapOf(
    "Cricket" to listOf("role", "batting", "bowling"),
    "Football" to listOf("position", "foot"),
    "Badminton" to listOf("format", "hand"),
    "Basketball" to listOf("position", "hand"),
)

private val PLAYER_ROLES = listOf("Batsman", "Bowler", "All-rounder", "Wicket-keeper")
private val BATTING_STYLES = listOf("Right-hand", "Left-hand")
private val BOWLING_STYLES = listOf(
    "Right-arm fast", "Right-arm medium", "Right-arm off-spin", "Right-arm leg-spin",
    "Left-arm fast", "Left-arm medium", "Left-arm orthodox", "Left-arm chinaman", "Doesn't bowl",
)
private val FOOTBALL_POSITIONS = listOf("Goalkeeper", "Defender", "Midfielder", "Forward")
private val FOOT_PREFERENCE = listOf("Right", "Left", "Both")
private val BADMINTON_FORMATS = listOf("Singles", "Doubles", "Both")
private val HANDEDNESS = listOf("Right", "Left")
private val BASKETBALL_POSITIONS = listOf("Guard", "Forward", "Center")
private val GENDERS = listOf("Male", "Female", "Other")
// 4ft 10in … 6ft 6in
private val HEIGHTS = (58..78).map { "${it / 12}ft ${it % 12}in" }
private val NATIONALITIES = listOf(
    "Indian", "Pakistani", "Bangladeshi", "Sri Lankan", "Afghan", "Nepali", "Other",
)

private const val TOTAL_STEPS = 3

/**
 * One-time ActionBoard player-profile setup, presented as a three-step "build your player card"
 * flow rather than one long form. Required before any ranked action.
 * [onSave] persists the profile (throws on error); [onDone] fires on success.
 */
@Composable
fun PlayerProfileSetupScreen(
    onClose: () -> Unit,
    onSave: suspend (
        name: String, state: String, district: String,
        primarySport: String, sportAttributes: Map<String, String>,
        gender: String, dateOfBirth: String, birthPlace: String, height: String, nationality: String,
        photoUri: Uri?, username: String,
    ) -> Unit,
    onDone: () -> Unit,
    // Live handle availability. Defaults to "can't tell", so any caller that doesn't wire
    // it up still lets the player through — the server re-checks on save regardless.
    checkUsername: suspend (String) -> UsernameCheck = { UsernameCheck.Unknown },
    modifier: Modifier = Modifier,
) {
    var name by remember { mutableStateOf("") }
    var username by remember { mutableStateOf("") }
    // Once they edit the handle themselves we stop auto-filling it from their name.
    var usernameEdited by remember { mutableStateOf(false) }
    var usernameState by remember { mutableStateOf<UsernameCheck?>(null) }
    var checkingUsername by remember { mutableStateOf(false) }
    var state by remember { mutableStateOf("") }
    var district by remember { mutableStateOf("") }
    // Cricket-first platform: preselect Cricket so its fields show immediately. Players can
    // still switch to another sport, which clears the cricket attributes.
    var primarySport by remember { mutableStateOf("Cricket") }
    val sportAttrs = remember { mutableStateMapOf<String, String>() }
    var gender by remember { mutableStateOf("") }
    var dobDisplay by remember { mutableStateOf("") }
    var dobIso by remember { mutableStateOf("") }
    var birthPlace by remember { mutableStateOf("") }
    var height by remember { mutableStateOf("") }
    var nationality by remember { mutableStateOf("Indian") }
    var photoUri by remember { mutableStateOf<Uri?>(null) }
    var saving by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var step by remember { mutableIntStateOf(0) }
    val scope = rememberCoroutineScope()

    // Per-step completeness — drives the Continue button and small, frequent wins.
    // Gate ONLY on what the server actually requires (PlayersController: name, state,
    // district, primary_sport, sport_attributes). Everything else is nullable there, so
    // demanding it here was inventing friction — and height/nationality sat below the
    // fold, which turned that invented rule into a dead end.
    // Date of birth and gender stay required: they're two taps and they drive the
    // player card and age-group features, unlike height and nationality.
    // Offer a handle built from their name until they take over. Saves most players from
    // inventing one — the whole field is friction they didn't ask for.
    LaunchedEffect(name) {
        if (!usernameEdited) username = suggestUsername(name)
    }

    // Debounced availability check. Deliberately NOT on every keystroke: the server call
    // is a courtesy, and the save path re-checks.
    LaunchedEffect(username) {
        val candidate = username.trim()
        if (candidate.isEmpty()) {
            usernameState = null
            checkingUsername = false
            return@LaunchedEffect
        }
        usernameState = null
        checkingUsername = true
        delay(450)
        usernameState = checkUsername(candidate)
        checkingUsername = false
    }

    // A handle the server actively refused blocks Continue. "Unknown" (offline, timeout)
    // does NOT: the player shouldn't be stranded by a flaky network, and the save call
    // validates for real and reports back.
    val usernameOk = username.isNotBlank() &&
        !checkingUsername &&
        usernameState !is UsernameCheck.Rejected

    val step1Valid = name.isNotBlank() && gender.isNotBlank() && dobIso.isNotBlank() && usernameOk
    val step2Valid = state.isNotBlank() && district.isNotBlank()
    val step3Valid = primarySport.isNotBlank() &&
        SPORT_REQUIRED[primarySport].orEmpty().all { !sportAttrs[it].isNullOrBlank() }
    val currentStepValid = when (step) {
        0 -> step1Valid
        1 -> step2Valid
        else -> step3Valid
    }

    // Name the FIRST thing still missing, so the footer can say why Continue is inert.
    // Without this the button just sat grey: step 1 requires height and nationality,
    // which are below the fold, so a user who filled everything visible hit a dead
    // button with no explanation and no way to discover the cause.
    val sportAttrLabels = mapOf(
        "role" to "playing role", "batting" to "batting style", "bowling" to "bowling style",
        "position" to "position", "foot" to "stronger foot", "format" to "format", "hand" to "playing hand",
    )
    val missingField: String? = when (step) {
        0 -> when {
            name.isBlank() -> "your name"
            username.isBlank() -> "a username"
            usernameState is UsernameCheck.Rejected -> "an available username"
            dobIso.isBlank() -> "your date of birth"
            gender.isBlank() -> "your gender"
            else -> null
        }
        1 -> when {
            state.isBlank() -> "your state"
            district.isBlank() -> "your district"
            else -> null
        }
        else -> when {
            primarySport.isBlank() -> "your primary sport"
            else -> SPORT_REQUIRED[primarySport].orEmpty()
                .firstOrNull { sportAttrs[it].isNullOrBlank() }
                ?.let { sportAttrLabels[it] ?: it }
        }
    }
    val firstName = name.trim().substringBefore(' ').takeIf { it.isNotBlank() }

    val (title, subtitle) = when (step) {
        0 -> "Let's start with you" to (firstName?.let { "Nice to meet you, $it." }
            ?: "Tell us who's stepping onto the pitch.")
        1 -> "Where you're from" to (firstName?.let { "Every player has a home ground, $it." }
            ?: "Every player has a home ground.")
        else -> "Your game" to "The part everyone wants to know."
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(Bg)
    ) {
        // Top bar — back arrow on later steps, close on the first.
        Column(Modifier.fillMaxWidth().background(Surface)) {
            Row(
                Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 14.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(
                    Modifier.size(36.dp).clip(CircleShape).background(Color(0xFFF1F5F9))
                        .clickable { if (step == 0) onClose() else { step--; error = null } },
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(
                        if (step == 0) Icons.Default.Close else Icons.AutoMirrored.Filled.ArrowBack,
                        contentDescription = if (step == 0) "Close" else "Back",
                        tint = Text1,
                        modifier = Modifier.size(18.dp),
                    )
                }
                Spacer(Modifier.width(12.dp))
                // One line, not a stacked title+subtitle: the step counter moves to a pill on
                // the right, so the bar reads left-to-right in a single glance instead of
                // stacking four different type sizes in the top 100dp.
                Text(
                    "Player profile",
                    color = Text1,
                    fontSize = 17.sp,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.weight(1f),
                )
                Box(
                    Modifier
                        .clip(RoundedCornerShape(999.dp))
                        .background(BlueTint)
                        .padding(horizontal = 10.dp, vertical = 5.dp),
                ) {
                    Text(
                        "${step + 1} of $TOTAL_STEPS",
                        color = Blue,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                    )
                }
            }
            StepProgress(current = step, total = TOTAL_STEPS)
            Spacer(Modifier.height(16.dp))
        }

        // Animated step body — slides in the direction of travel for a sense of momentum.
        AnimatedContent(
            targetState = step,
            transitionSpec = {
                val dir = if (targetState > initialState) 1 else -1
                (slideInHorizontally(tween(280)) { it * dir } + fadeIn(tween(220))) togetherWith
                    (slideOutHorizontally(tween(280)) { -it * dir } + fadeOut(tween(160)))
            },
            modifier = Modifier.weight(1f),
            label = "profileStep",
        ) { s ->
            Column(
                Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(horizontal = 16.dp, vertical = 18.dp),
            ) {
                // 26sp gives the page a real lead. At 22sp against 14sp/SemiBold field
                // labels there was no dominant element and the screen read flat.
                Text(title, color = Text1, fontSize = 26.sp, fontWeight = FontWeight.Bold)
                Spacer(Modifier.height(5.dp))
                Text(subtitle, color = Text2, fontSize = 14.5.sp)
                Spacer(Modifier.height(26.dp))

                when (s) {
                    0 -> {
                        AvatarPicker(
                            photoUri = photoUri,
                            initial = firstName?.take(1).orEmpty(),
                            onPick = { photoUri = it },
                        )
                        Spacer(Modifier.height(22.dp))

                        FieldLabel("Full name")
                        Field(name, { name = it }, "Your name")
                        Spacer(Modifier.height(16.dp))

                        FieldLabel("Username")
                        UsernameField(
                            value = username,
                            onChange = { usernameEdited = true; username = it },
                            checking = checkingUsername,
                            status = usernameState,
                        )
                        Spacer(Modifier.height(16.dp))

                        FieldLabel("Date of birth")
                        DateField(dobDisplay, "Select date of birth") { iso, display ->
                            dobIso = iso; dobDisplay = display
                        }
                        Spacer(Modifier.height(16.dp))

                        FieldLabel("Gender")
                        // One row instead of a 2+1 grid, which left an orphan tile.
                        SegmentedChoice(GENDERS, gender) { gender = it }
                    }

                    1 -> {
                        FieldLabel("State")
                        // Changing state invalidates the chosen district, so clear it.
                        Dropdown(state, INDIAN_STATES, "Select state") { state = it; district = "" }
                        Spacer(Modifier.height(16.dp))

                        FieldLabel("District")
                        val districtOptions = DISTRICTS_BY_STATE[state].orEmpty()
                        Dropdown(
                            value = district,
                            options = districtOptions,
                            placeholder = if (state.isBlank()) "Select state first" else "Select district",
                            enabled = districtOptions.isNotEmpty(),
                        ) { district = it }

                        // Everything below is genuinely optional — the backend stores these
                        // as nullable. They used to be mandatory gates on step 1, sitting
                        // below the fold, which is what made Continue look broken. Grouped
                        // and labelled here so nobody is blocked by them again.
                        Spacer(Modifier.height(24.dp))
                        Text(
                            "A few extras",
                            color = Text1,
                            fontSize = 15.sp,
                            fontWeight = FontWeight.Bold,
                        )
                        Text(
                            "Optional — these show on your player card. You can add them later.",
                            color = Text3,
                            fontSize = 12.5.sp,
                            modifier = Modifier.padding(top = 2.dp, bottom = 14.dp),
                        )

                        FieldLabel("Birth place")
                        Field(birthPlace, { birthPlace = it }, "City, State")
                        Spacer(Modifier.height(16.dp))

                        FieldLabel("Height")
                        Dropdown(height, HEIGHTS, "Select height") { height = it }
                        Spacer(Modifier.height(16.dp))

                        FieldLabel("Nationality")
                        Dropdown(nationality, NATIONALITIES, "Select nationality") { nationality = it }
                    }

                    else -> {
                        FieldLabel("Choose your sport")
                        SportPicker(primarySport) { picked ->
                            if (picked != primarySport) { primarySport = picked; sportAttrs.clear() }
                        }
                        Spacer(Modifier.height(20.dp))

                        // Only the chosen sport's fields appear — a footballer is never asked
                        // their bowling style. Keys mirror the backend's required attributes.
                        when (primarySport) {
                            "Cricket" -> {
                                FieldLabel("Player role")
                                ChipWrap(PLAYER_ROLES, sportAttrs["role"].orEmpty()) { sportAttrs["role"] = it }
                                Spacer(Modifier.height(16.dp))

                                FieldLabel("Batting style")
                                ChipWrap(BATTING_STYLES, sportAttrs["batting"].orEmpty()) { sportAttrs["batting"] = it }
                                Spacer(Modifier.height(16.dp))

                                FieldLabel("Bowling style")
                                Dropdown(sportAttrs["bowling"].orEmpty(), BOWLING_STYLES, "Select bowling style") {
                                    sportAttrs["bowling"] = it
                                }
                            }
                            "Football" -> {
                                FieldLabel("Position")
                                ChipWrap(FOOTBALL_POSITIONS, sportAttrs["position"].orEmpty()) { sportAttrs["position"] = it }
                                Spacer(Modifier.height(16.dp))

                                FieldLabel("Preferred foot")
                                ChipWrap(FOOT_PREFERENCE, sportAttrs["foot"].orEmpty()) { sportAttrs["foot"] = it }
                            }
                            "Badminton" -> {
                                FieldLabel("Format")
                                ChipWrap(BADMINTON_FORMATS, sportAttrs["format"].orEmpty()) { sportAttrs["format"] = it }
                                Spacer(Modifier.height(16.dp))

                                FieldLabel("Playing hand")
                                ChipWrap(HANDEDNESS, sportAttrs["hand"].orEmpty()) { sportAttrs["hand"] = it }
                            }
                            "Basketball" -> {
                                FieldLabel("Position")
                                ChipWrap(BASKETBALL_POSITIONS, sportAttrs["position"].orEmpty()) { sportAttrs["position"] = it }
                                Spacer(Modifier.height(16.dp))

                                FieldLabel("Dominant hand")
                                ChipWrap(HANDEDNESS, sportAttrs["hand"].orEmpty()) { sportAttrs["hand"] = it }
                            }
                        }
                    }
                }

                if (error != null) {
                    Spacer(Modifier.height(14.dp))
                    Text(error!!, color = Color(0xFFDC2626), fontSize = 13.sp)
                }
            }
        }

        // Footer CTA — blue while there's still progress to make, green to commit on the last step.
        val isLast = step == TOTAL_STEPS - 1
        val ctaColor = if (isLast) Green else Blue
        // Same footer rule as the create-match wizard: the white surface stays flush to
        // the screen edge, but the button is lifted above the system navigation (and the
        // keyboard on text-entry steps). Without this the CTA sat underneath the gesture
        // bar, which overlapped the tap target.
        Column(
            Modifier
                .fillMaxWidth()
                .background(Surface)
                // The body is white now, so the footer needs its own hairline to read as a
                // fixed bar rather than more page.
                .drawBehind {
                    drawLine(Stroke, Offset(0f, 0f), Offset(size.width, 0f), strokeWidth = 1f)
                }
                .navigationBarsPadding()
                .imePadding()
                .padding(16.dp)
        ) {
            // Says what is still needed BEFORE the tap, rather than leaving a grey
            // button to be poked at. Named field, not a generic "complete all fields".
            if (missingField != null && !saving) {
                Text(
                    text = "Add $missingField to continue",
                    color = Text2,
                    fontSize = 13.sp,
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(bottom = 10.dp),
                    textAlign = TextAlign.Center,
                )
            }
            Button(
                onClick = {
                    if (saving) return@Button
                    if (!isLast) {
                        if (currentStepValid) { step++; error = null }
                        return@Button
                    }
                    if (step1Valid && step2Valid && step3Valid) {
                        saving = true; error = null
                        scope.launch {
                            try {
                                onSave(
                                    name.trim(), state, district.trim(),
                                    primarySport, sportAttrs.toMap(),
                                    gender, dobIso, birthPlace.trim(), height, nationality, photoUri,
                                    username.trim(),
                                )
                                onDone()
                            } catch (e: Exception) {
                                error = e.message ?: "Could not save profile."
                            } finally {
                                saving = false
                            }
                        }
                    }
                },
                enabled = currentStepValid && !saving,
                modifier = Modifier.fillMaxWidth().height(52.dp),
                shape = RoundedCornerShape(14.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = ctaColor, contentColor = Color.White,
                    // Neutral grey when it isn't ready yet, NOT a 35%-alpha blue: a washed-out
                    // version of the live colour reads as "this button is broken", whereas a
                    // plainly inert control reads as "not yet" — which is the truth.
                    disabledContainerColor = Disabled,
                    disabledContentColor = Text3,
                ),
            ) {
                if (saving) {
                    CircularProgressIndicator(modifier = Modifier.size(20.dp), strokeWidth = 2.dp, color = Color.White)
                } else {
                    Text(
                        if (isLast) "Create profile" else "Continue",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                    )
                }
            }
        }
    }
}

/**
 * Segmented progress. The "Step X of N" caption that used to hang under this now lives as
 * a pill in the top bar, so the bar is purely a visual measure — it earns its space
 * instead of restating a number twice. Segments animate so moving on feels like travel.
 */
@Composable
private fun StepProgress(current: Int, total: Int) {
    Row(
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp),
    ) {
        repeat(total) { i ->
            val fill by animateColorAsState(
                if (i <= current) Blue else Track,
                animationSpec = tween(320),
                label = "stepFill",
            )
            Box(
                Modifier
                    .weight(1f)
                    .height(4.dp)
                    .clip(RoundedCornerShape(2.dp))
                    .background(fill),
            )
        }
    }
}

/**
 * Big, centred profile-photo picker — the emotional anchor of step 1. Optional, so it never
 * gates progress, but framed to make adding a face feel worth it.
 */
@Composable
private fun AvatarPicker(photoUri: Uri?, initial: String, onPick: (Uri?) -> Unit) {
    val launcher = rememberLauncherForActivityResult(
        ActivityResultContracts.PickVisualMedia(),
    ) { uri -> if (uri != null) onPick(uri) }
    val openPicker: () -> Unit = {
        launcher.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly))
    }

    // The circle carries exactly ONE camera glyph. It used to show a camera inside the
    // circle AND a camera badge on the corner — two identical icons 40dp apart, which is
    // what made this block read as cluttered. Empty state = one centred glyph, no badge;
    // once there's something to change, the badge appears and the inner glyph goes away.
    val hasContent = photoUri != null || initial.isNotBlank()

    Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally) {
        Box(contentAlignment = Alignment.BottomEnd) {
            // Empty state gets a DASHED ring. A solid hairline the same value as the fill
            // made a near-white disc on a white page — it read as a hole in the layout
            // rather than something to tap. Dashes say "drop something here".
            val isEmpty = photoUri == null && initial.isBlank()
            Box(
                Modifier
                    .size(104.dp)
                    .clip(CircleShape)
                    .background(if (photoUri != null) BlueTint else FieldFill)
                    .then(
                        if (isEmpty) {
                            Modifier.drawBehind {
                                drawCircle(
                                    color = DashRing,
                                    radius = size.minDimension / 2f - 1.dp.toPx(),
                                    style = androidx.compose.ui.graphics.drawscope.Stroke(
                                        width = 1.5.dp.toPx(),
                                        pathEffect = PathEffect.dashPathEffect(
                                            floatArrayOf(9.dp.toPx(), 7.dp.toPx()), 0f,
                                        ),
                                    ),
                                )
                            }
                        } else {
                            Modifier.border(
                                if (photoUri != null) 2.dp else 1.dp,
                                if (photoUri != null) Blue else Stroke,
                                CircleShape,
                            )
                        }
                    )
                    .clickable(onClick = openPicker),
                contentAlignment = Alignment.Center,
            ) {
                when {
                    photoUri != null -> AsyncImage(
                        model = photoUri,
                        contentDescription = "Profile photo",
                        contentScale = ContentScale.Crop,
                        modifier = Modifier.fillMaxSize().clip(CircleShape),
                    )
                    initial.isNotBlank() -> Text(
                        initial.uppercase(), color = Blue, fontSize = 40.sp, fontWeight = FontWeight.Bold,
                    )
                    else -> Icon(
                        Icons.Default.PhotoCamera, null, tint = Text3, modifier = Modifier.size(30.dp),
                    )
                }
            }
            if (hasContent) {
                Box(
                    Modifier
                        .size(34.dp)
                        .clip(CircleShape)
                        .background(Blue)
                        .border(3.dp, Surface, CircleShape)
                        .clickable(onClick = openPicker),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(
                        Icons.Default.PhotoCamera, "Change photo",
                        tint = Color.White, modifier = Modifier.size(16.dp),
                    )
                }
            }
        }
        Spacer(Modifier.height(12.dp))
        Text(
            if (photoUri != null) "Tap to change photo" else "Add a profile photo",
            color = Text1, fontSize = 14.sp, fontWeight = FontWeight.SemiBold,
        )
        Spacer(Modifier.height(2.dp))
        // Was "Players with a photo get noticed first" — a claim nothing in the product
        // measures. Say the one thing that's true and actually reduces friction here:
        // this field will not block them.
        Text("Optional — you can add one later", color = Text3, fontSize = 12.sp)
    }
}

/** Sport selector for step 3 — emoji + name cards in a 2-column grid; the pick drives the
 *  fields shown beneath it. */
@Composable
private fun SportPicker(selected: String, onSelect: (String) -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        SPORTS.chunked(2).forEach { rowItems ->
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                rowItems.forEach { sport ->
                    val isSel = sport.name == selected
                    Row(
                        Modifier
                            .weight(1f)
                            .clip(RoundedCornerShape(14.dp))
                            .background(if (isSel) Blue else FieldFill)
                            .border(1.dp, if (isSel) Blue else Color.Transparent, RoundedCornerShape(14.dp))
                            .clickable { onSelect(sport.name) }
                            .padding(horizontal = 14.dp, vertical = 16.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Text(sport.emoji, fontSize = 20.sp)
                        Spacer(Modifier.width(10.dp))
                        Text(
                            sport.name,
                            color = if (isSel) Color.White else Text1,
                            fontSize = 15.sp,
                            fontWeight = FontWeight.SemiBold,
                            modifier = Modifier.weight(1f),
                        )
                        if (isSel) Icon(Icons.Default.Check, null, tint = Color.White, modifier = Modifier.size(18.dp))
                    }
                }
                if (rowItems.size == 1) Spacer(Modifier.weight(1f))
            }
        }
    }
}

// Labels sit a level BELOW the page title now (13sp, Text2). At 14sp/SemiBold/Text1 they
// were the same visual weight as "Let's start with you", so the screen had no clear lead.
/**
 * Build a starting handle from the player's name: "Virat Kohli" -> "viratkohli".
 * Mirrors the server's shape rules (lowercase, starts with a letter, <=20) so the
 * suggestion is never something the server will immediately reject.
 */
private fun suggestUsername(name: String): String {
    val cleaned = name.lowercase()
        .filter { it.isLetterOrDigit() }
        .dropWhile { !it.isLetter() }   // must start with a letter
        .take(20)
    return if (cleaned.length >= 3) cleaned else ""
}

/**
 * Handle field with a fixed "@" and a live verdict underneath. The verdict is the point:
 * a username that is silently rejected on submit is far worse than one checked as you
 * type. Filters input to the server's alphabet so an invalid character can't be typed at
 * all, rather than being scolded for it afterwards.
 */
@Composable
private fun UsernameField(
    value: String,
    onChange: (String) -> Unit,
    checking: Boolean,
    status: UsernameCheck?,
) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(FieldFill)
            .padding(start = 16.dp, end = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text("@", color = Text3, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
        androidx.compose.foundation.text.BasicTextField(
            value = value,
            onValueChange = { raw ->
                onChange(raw.lowercase().filter { it.isLetterOrDigit() || it == '.' || it == '_' }.take(20))
            },
            singleLine = true,
            textStyle = androidx.compose.ui.text.TextStyle(
                color = Text1, fontSize = 15.sp, fontWeight = FontWeight.Medium,
            ),
            cursorBrush = androidx.compose.ui.graphics.SolidColor(Blue),
            modifier = Modifier.weight(1f).padding(vertical = 17.dp, horizontal = 2.dp),
            decorationBox = { inner ->
                if (value.isEmpty()) {
                    Text("yourname", color = Text3, fontSize = 15.sp)
                }
                inner()
            },
        )
        when {
            checking -> CircularProgressIndicator(
                color = Text3, strokeWidth = 2.dp, modifier = Modifier.size(16.dp),
            )
            status is UsernameCheck.Available -> Icon(
                Icons.Default.Check, "Available", tint = Green, modifier = Modifier.size(18.dp),
            )
            else -> Spacer(Modifier.size(18.dp))
        }
    }

    val note: Pair<String, Color>? = when {
        value.isBlank() -> "Players find you by this when adding you to a match" to Text3
        checking -> null
        status is UsernameCheck.Available -> "@$value is available" to Green
        status is UsernameCheck.Rejected -> status.reason to Color(0xFFDC2626)
        else -> null
    }
    if (note != null) {
        Spacer(Modifier.height(6.dp))
        Text(note.first, color = note.second, fontSize = 12.sp)
    }
}

@Composable
private fun FieldLabel(text: String) {
    Text(text, color = Text2, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
    Spacer(Modifier.height(7.dp))
}

@Composable
private fun Field(value: String, onChange: (String) -> Unit, placeholder: String) {
    OutlinedTextField(
        value = value,
        onValueChange = onChange,
        placeholder = { Text(placeholder, color = Text3, fontSize = 14.sp) },
        singleLine = true,
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(12.dp),
        colors = OutlinedTextFieldDefaults.colors(
            // Filled, not outlined-on-grey: the border only asserts itself on focus.
            focusedBorderColor = Blue, unfocusedBorderColor = Color.Transparent,
            focusedContainerColor = Surface, unfocusedContainerColor = FieldFill,
            focusedTextColor = Text1, unfocusedTextColor = Text1, cursorColor = Blue,
        ),
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DateField(display: String, placeholder: String, onPicked: (iso: String, display: String) -> Unit) {
    var open by remember { mutableStateOf(false) }
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(FieldFill)
            .clickable { open = true }
            .padding(horizontal = 16.dp, vertical = 17.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(
            display.ifBlank { placeholder },
            color = if (display.isBlank()) Text3 else Text1,
            fontSize = 14.sp,
            modifier = Modifier.weight(1f),
        )
        Icon(Icons.Default.KeyboardArrowDown, null, tint = Text3, modifier = Modifier.size(20.dp))
    }

    if (open) {
        // A date of BIRTH: the future is never valid, and opening on today forced every
        // user to scroll back 20-40 years to reach their birth year. Land on ~25 years
        // ago and refuse anything later than today — previously "21 Jul 2026" was
        // accepted without complaint.
        val today = remember { java.util.Calendar.getInstance(java.util.TimeZone.getTimeZone("UTC")) }
        val todayMillis = remember { today.timeInMillis }
        val thisYear = remember { today.get(java.util.Calendar.YEAR) }
        val openAtMillis = remember {
            java.util.Calendar.getInstance(java.util.TimeZone.getTimeZone("UTC")).apply {
                add(java.util.Calendar.YEAR, -25)
            }.timeInMillis
        }
        // MUST be remembered: rememberDatePickerState treats this as an input, so a
        // freshly-allocated object each composition made it rebuild its state, which
        // recomposed, which allocated again — an infinite loop that ANR'd the app and
        // showed up as multi-MB GC churn every few seconds while idle.
        val selectable = remember(todayMillis, thisYear) {
            object : androidx.compose.material3.SelectableDates {
                override fun isSelectableDate(utcTimeMillis: Long) = utcTimeMillis <= todayMillis
                override fun isSelectableYear(year: Int) = year <= thisYear
            }
        }
        val pickerState = rememberDatePickerState(
            initialSelectedDateMillis = null,
            initialDisplayedMonthMillis = openAtMillis,
            // 120 years is the widest plausible span for a living player.
            yearRange = (thisYear - 120)..thisYear,
            selectableDates = selectable,
        )
        DatePickerDialog(
            onDismissRequest = { open = false },
            shape = RoundedCornerShape(24.dp),
            colors = androidx.compose.material3.DatePickerDefaults.colors(containerColor = Color.White),
            confirmButton = {
                TextButton(onClick = {
                    pickerState.selectedDateMillis?.let { millis ->
                        val utc = java.util.TimeZone.getTimeZone("UTC")
                        val iso = java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US)
                            .apply { timeZone = utc }.format(java.util.Date(millis))
                        val pretty = java.text.SimpleDateFormat("d MMM yyyy", java.util.Locale.US)
                            .apply { timeZone = utc }.format(java.util.Date(millis))
                        onPicked(iso, pretty)
                    }
                    open = false
                }) { Text("OK", color = Blue, fontWeight = FontWeight.Bold) }
            },
            dismissButton = {
                TextButton(onClick = { open = false }) { Text("Cancel", color = Text2) }
            },
        ) {
            // Stock Material3 renders this in its own purple-grey tonal palette, which
            // read as a different app dropped into the flow. Restate it in the screen's
            // own colours instead.
            // Material's own header is the rest of the problem: it prints the literal
            // placeholder "Selected date" in 24sp before you have picked anything, and
            // hangs a pencil (keyboard-entry toggle) in the corner. Both are replaced —
            // a real label, a headline that shows the actual date, and no mode toggle.
            val headlineText = pickerState.selectedDateMillis?.let { millis ->
                java.text.SimpleDateFormat("d MMM yyyy", java.util.Locale.US)
                    .apply { timeZone = java.util.TimeZone.getTimeZone("UTC") }
                    .format(java.util.Date(millis))
            } ?: "Pick your birth date"

            DatePicker(
                state = pickerState,
                showModeToggle = false,
                title = {
                    Text(
                        "Date of birth",
                        color = Text2,
                        fontSize = 13.sp,
                        fontWeight = FontWeight.SemiBold,
                        modifier = Modifier.padding(start = 24.dp, end = 24.dp, top = 20.dp),
                    )
                },
                headline = {
                    Text(
                        headlineText,
                        color = if (pickerState.selectedDateMillis == null) Text3 else Text1,
                        fontSize = 24.sp,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(start = 24.dp, end = 24.dp, bottom = 8.dp),
                    )
                },
                colors = androidx.compose.material3.DatePickerDefaults.colors(
                    containerColor = Color.White,
                    titleContentColor = Text2,
                    headlineContentColor = Text1,
                    weekdayContentColor = Text3,
                    subheadContentColor = Text2,
                    navigationContentColor = Text2,
                    yearContentColor = Text1,
                    currentYearContentColor = Blue,
                    selectedYearContainerColor = Blue,
                    dayContentColor = Text1,
                    selectedDayContainerColor = Blue,
                    todayContentColor = Blue,
                    todayDateBorderColor = Blue,
                ),
            )
        }
    }
}

@Composable
private fun Dropdown(
    value: String,
    options: List<String>,
    placeholder: String,
    enabled: Boolean = true,
    onSelect: (String) -> Unit,
) {
    var open by remember { mutableStateOf(false) }
    Box {
        Row(
            Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(12.dp))
                .background(if (enabled) FieldFill else Disabled)
                .clickable(enabled = enabled) { open = true }
                .padding(horizontal = 16.dp, vertical = 17.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                value.ifBlank { placeholder },
                color = if (value.isBlank()) Text3 else Text1,
                fontSize = 14.sp,
                modifier = Modifier.weight(1f),
            )
            Icon(Icons.Default.KeyboardArrowDown, null, tint = Text3, modifier = Modifier.size(20.dp))
        }
        DropdownMenu(
            expanded = open,
            onDismissRequest = { open = false },
            modifier = Modifier.heightIn(max = 320.dp).background(Surface),
        ) {
            options.forEach { opt ->
                DropdownMenuItem(
                    text = { Text(opt, color = Text1, fontSize = 14.sp) },
                    onClick = { onSelect(opt); open = false },
                )
            }
        }
    }
}

/**
 * A single-row segmented control. [ChipWrap] chunks into pairs, which for a
 * three-option set (Male / Female / Other) left a lone tile on a second row —
 * visually ragged, and it made the gender question look bigger than it is.
 * Use this for short, mutually-exclusive sets that fit one line.
 */
@Composable
private fun SegmentedChoice(options: List<String>, selected: String, onSelect: (String) -> Unit) {
    // A real segmented control: one recessed track, the selection riding on top as a
    // raised pill. The old version was a white box with a hard blue block in one cell,
    // which read as three separate buttons rather than one either/or choice.
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(FieldFill)
            .padding(4.dp),
        horizontalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        options.forEach { opt ->
            val isSel = opt == selected
            Row(
                Modifier
                    .weight(1f)
                    .clip(RoundedCornerShape(9.dp))
                    .background(if (isSel) Blue else Color.Transparent)
                    .clickable { onSelect(opt) }
                    .padding(vertical = 12.dp),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    opt,
                    color = if (isSel) Color.White else Text2,
                    fontSize = 13.5.sp,
                    fontWeight = if (isSel) FontWeight.Bold else FontWeight.Medium,
                    maxLines = 1,
                )
            }
        }
    }
}

@Composable
private fun ChipWrap(options: List<String>, selected: String, onSelect: (String) -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        options.chunked(2).forEach { rowItems ->
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                rowItems.forEach { opt ->
                    val isSel = opt == selected
                    Row(
                        Modifier
                            .weight(1f)
                            .clip(RoundedCornerShape(12.dp))
                            .background(if (isSel) Blue else FieldFill)
                            .border(1.dp, if (isSel) Blue else Color.Transparent, RoundedCornerShape(12.dp))
                            .clickable { onSelect(opt) }
                            .padding(vertical = 13.dp),
                        horizontalArrangement = Arrangement.Center,
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        if (isSel) {
                            Icon(
                                Icons.Default.Check, null, tint = Color.White,
                                modifier = Modifier.size(16.dp),
                            )
                            Spacer(Modifier.width(6.dp))
                        }
                        Text(
                            opt,
                            color = if (isSel) Color.White else Text1,
                            fontSize = 13.5.sp,
                            fontWeight = FontWeight.SemiBold,
                        )
                    }
                }
                if (rowItems.size == 1) Spacer(Modifier.weight(1f))
            }
        }
    }
}
