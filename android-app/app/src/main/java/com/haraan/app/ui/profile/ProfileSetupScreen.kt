package com.haraan.app.ui.profile

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.AnimatedContent
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import kotlinx.coroutines.launch

private val Bg = Color(0xFFEBEBF0)
private val Surface = Color(0xFFFFFFFF)
private val Blue = Color(0xFF2563EB)
private val Green = Color(0xFF16A34A)
private val Text1 = Color(0xFF111827)
private val Text2 = Color(0xFF5A5A6A)
private val Text3 = Color(0xFF9A9AA8)
private val Stroke = Color(0xFFE2E8F0)
private val Track = Color(0xFFD9DCE3)
private val BlueTint = Color(0xFFEFF4FF)

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
        photoUri: Uri?,
    ) -> Unit,
    onDone: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var name by remember { mutableStateOf("") }
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
    val step1Valid = name.isNotBlank() && gender.isNotBlank() && dobIso.isNotBlank() &&
        height.isNotBlank() && nationality.isNotBlank()
    val step2Valid = state.isNotBlank() && district.isNotBlank() && birthPlace.isNotBlank()
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
            dobIso.isBlank() -> "your date of birth"
            gender.isBlank() -> "your gender"
            height.isBlank() -> "your height"
            nationality.isBlank() -> "your nationality"
            else -> null
        }
        1 -> when {
            state.isBlank() -> "your state"
            district.isBlank() -> "your district"
            birthPlace.isBlank() -> "your birth place"
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
                Column {
                    Text("Player profile", color = Text1, fontSize = 18.sp, fontWeight = FontWeight.Bold)
                    Text("Required to create or play ranked matches", color = Text3, fontSize = 12.sp)
                }
            }
            StepProgress(current = step, total = TOTAL_STEPS)
            Spacer(Modifier.height(14.dp))
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
                Text(title, color = Text1, fontSize = 22.sp, fontWeight = FontWeight.Bold)
                Spacer(Modifier.height(4.dp))
                Text(subtitle, color = Text2, fontSize = 14.sp)
                Spacer(Modifier.height(22.dp))

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

                        FieldLabel("Date of birth")
                        DateField(dobDisplay, "Select date of birth") { iso, display ->
                            dobIso = iso; dobDisplay = display
                        }
                        Spacer(Modifier.height(16.dp))

                        FieldLabel("Gender")
                        ChipWrap(GENDERS, gender) { gender = it }
                        Spacer(Modifier.height(16.dp))

                        FieldLabel("Height")
                        Dropdown(height, HEIGHTS, "Select height") { height = it }
                        Spacer(Modifier.height(16.dp))

                        FieldLabel("Nationality")
                        Dropdown(nationality, NATIONALITIES, "Select nationality") { nationality = it }
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
                        Spacer(Modifier.height(16.dp))

                        FieldLabel("Birth place")
                        Field(birthPlace, { birthPlace = it }, "City, State")
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
                    disabledContainerColor = ctaColor.copy(alpha = 0.35f),
                    disabledContentColor = Color.White.copy(alpha = 0.7f),
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

/** Segmented progress: filled segments behind the current step, plus a "Step X of N" label. */
@Composable
private fun StepProgress(current: Int, total: Int) {
    Column(Modifier.fillMaxWidth().padding(horizontal = 16.dp)) {
        Row(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth()) {
            repeat(total) { i ->
                Box(
                    Modifier
                        .weight(1f)
                        .height(5.dp)
                        .clip(RoundedCornerShape(3.dp))
                        .background(if (i <= current) Blue else Track),
                )
            }
        }
        Spacer(Modifier.height(8.dp))
        Text(
            "Step ${current + 1} of $total",
            color = Text2,
            fontSize = 12.sp,
            fontWeight = FontWeight.SemiBold,
        )
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

    Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally) {
        Box(contentAlignment = Alignment.BottomEnd) {
            Box(
                Modifier
                    .size(108.dp)
                    .clip(CircleShape)
                    .background(BlueTint)
                    .border(2.dp, if (photoUri != null) Blue else Stroke, CircleShape)
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
                        initial.uppercase(), color = Blue, fontSize = 42.sp, fontWeight = FontWeight.Bold,
                    )
                    else -> Icon(
                        Icons.Default.PhotoCamera, null, tint = Blue, modifier = Modifier.size(36.dp),
                    )
                }
            }
            // Camera badge — a clear, tappable affordance that this circle is editable.
            Box(
                Modifier
                    .size(36.dp)
                    .clip(CircleShape)
                    .background(Blue)
                    .border(3.dp, Surface, CircleShape)
                    .clickable(onClick = openPicker),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Default.PhotoCamera, "Add photo", tint = Color.White, modifier = Modifier.size(18.dp))
            }
        }
        Spacer(Modifier.height(10.dp))
        Text(
            if (photoUri != null) "Tap to change photo" else "Add a profile photo",
            color = Text1, fontSize = 14.sp, fontWeight = FontWeight.SemiBold,
        )
        Text(
            "Players with a photo get noticed first",
            color = Text3, fontSize = 12.sp,
        )
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
                            .background(if (isSel) Blue else Surface)
                            .border(1.dp, if (isSel) Blue else Stroke, RoundedCornerShape(14.dp))
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

@Composable
private fun FieldLabel(text: String) {
    Text(text, color = Text1, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
    Spacer(Modifier.height(8.dp))
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
            focusedBorderColor = Blue, unfocusedBorderColor = Stroke,
            focusedContainerColor = Surface, unfocusedContainerColor = Surface,
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
            .background(Surface)
            .border(1.dp, Stroke, RoundedCornerShape(12.dp))
            .clickable { open = true }
            .padding(horizontal = 16.dp, vertical = 16.dp),
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
        val pickerState = rememberDatePickerState(
            initialSelectedDateMillis = null,
            initialDisplayedMonthMillis = openAtMillis,
            // 120 years is the widest plausible span for a living player.
            yearRange = (thisYear - 120)..thisYear,
            selectableDates = object : androidx.compose.material3.SelectableDates {
                override fun isSelectableDate(utcTimeMillis: Long) = utcTimeMillis <= todayMillis
                override fun isSelectableYear(year: Int) = year <= thisYear
            },
        )
        DatePickerDialog(
            onDismissRequest = { open = false },
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
            DatePicker(state = pickerState)
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
                .background(if (enabled) Surface else Color(0xFFF1F2F6))
                .border(1.dp, Stroke, RoundedCornerShape(12.dp))
                .clickable(enabled = enabled) { open = true }
                .padding(horizontal = 16.dp, vertical = 16.dp),
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
                            .background(if (isSel) Blue else Surface)
                            .border(1.dp, if (isSel) Blue else Stroke, RoundedCornerShape(12.dp))
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
