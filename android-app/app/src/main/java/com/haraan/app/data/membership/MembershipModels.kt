package com.haraan.app.data.membership

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

// Wire shapes for /api/membership. Every decision (what a plan includes, whether a member
// has it, what they've used) is made by the server; these only carry the answers.

@Serializable
data class DataEnvelope<T>(val data: T)

@Serializable
data class MembershipCatalogue(
    @SerialName("current_plan") val currentPlan: String = "free",
    val plans: List<CataloguePlan> = emptyList(),
)

@Serializable
data class CataloguePlan(
    val code: String,
    val name: String,
    val tagline: String? = null,
    val description: String? = null,
    val rank: Int = 0,
    @SerialName("is_default") val isDefault: Boolean = false,
    @SerialName("is_current") val isCurrent: Boolean = false,
    val prices: List<PlanPrice> = emptyList(),
    val features: List<PlanFeature> = emptyList(),
)

@Serializable
data class PlanPrice(
    val id: Long,
    val interval: String,
    @SerialName("amount_paise") val amountPaise: Long,
    val currency: String = "INR",
    val label: String = "",
)

@Serializable
data class PlanFeature(
    val key: String,
    val name: String,
    val description: String? = null,
    val type: String = "boolean",
    val unit: String? = null,
    val enabled: Boolean = false,
    val limit: Int? = null,
    val unlimited: Boolean = false,
    /** Present on the member's own entitlements for monthly quotas. */
    val used: Int? = null,
    @SerialName("resets_at") val resetsAt: String? = null,
)

@Serializable
data class Membership(
    val plan: MembershipPlanRef,
    val source: String = "default",
    val subscription: MembershipSubscription? = null,
    @SerialName("scheduled_change") val scheduledChange: ScheduledChange? = null,
    /** `payment_retrying` | `payment_failed` | null */
    val attention: String? = null,
    val badge: String? = null,
    val entitlements: List<PlanFeature> = emptyList(),
    @SerialName("insight_sports") val insightSports: InsightSportsStatus? = null,
)

@Serializable
data class MembershipPlanRef(val code: String, val name: String, val rank: Int = 0)

@Serializable
data class MembershipSubscription(
    val id: Long,
    val status: String,
    val provider: String,
    val plan: NamedPlan,
    val interval: String? = null,
    @SerialName("amount_paise") val amountPaise: Long? = null,
    @SerialName("current_period_end") val currentPeriodEnd: String? = null,
    @SerialName("renews_at") val renewsAt: String? = null,
    @SerialName("ends_at") val endsAt: String? = null,
    @SerialName("cancel_at_period_end") val cancelAtPeriodEnd: Boolean = false,
    @SerialName("in_grace") val inGrace: Boolean = false,
    @SerialName("can_cancel") val canCancel: Boolean = false,
)

@Serializable
data class NamedPlan(val code: String? = null, val name: String? = null)

@Serializable
data class ScheduledChange(
    val plan: NamedPlan,
    val interval: String? = null,
    @SerialName("amount_paise") val amountPaise: Long? = null,
    @SerialName("starts_at") val startsAt: String? = null,
)

@Serializable
data class CheckoutSession(
    @SerialName("subscription_id") val subscriptionId: String,
    val key: String? = null,
    @SerialName("amount_paise") val amountPaise: Long,
    val currency: String = "INR",
    val interval: String,
    val plan: NamedPlan,
    @SerialName("change_type") val changeType: String = "new",
    @SerialName("starts_at") val startsAt: String? = null,
)

@Serializable
data class VerifyResult(
    val confirmed: Boolean,
    val membership: Membership,
)

@Serializable
data class MemberPaymentItem(
    val id: String,
    val plan: String? = null,
    @SerialName("amount_paise") val amountPaise: Long,
    val currency: String = "INR",
    val status: String,
    val method: String? = null,
    @SerialName("paid_at") val paidAt: String? = null,
)

@Serializable
data class SubscribeBody(@SerialName("price_id") val priceId: Long)

@Serializable
data class VerifyBody(
    @SerialName("razorpay_payment_id") val paymentId: String,
    @SerialName("razorpay_subscription_id") val subscriptionId: String,
    @SerialName("razorpay_signature") val signature: String,
)

@Serializable
data class AbandonBody(@SerialName("subscription_id") val subscriptionId: String)

@Serializable
data class CancelBody(@SerialName("at_period_end") val atPeriodEnd: Boolean)

/** The server's error body: `{error, code}` for billing, plus feature/limit for plan gates. */
@Serializable
data class MembershipErrorBody(
    val error: String? = null,
    val code: String? = null,
    val feature: String? = null,
    @SerialName("upgrade_plan") val upgradePlan: String? = null,
    /** On an insights refusal: the sport the match is in. */
    val sport: String? = null,
)

/**
 * Advanced insights, per sport. [mode]: `all` (every sport), `choose` (pick up to [limit]),
 * `none` (not on this plan). The server enforces all of it; this only draws the picker.
 */
@Serializable
data class InsightSportsStatus(
    val mode: String = "none",
    val limit: Int? = null,
    val plan: NamedPlan = NamedPlan(),
    val selected: List<String> = emptyList(),
    @SerialName("slots_left") val slotsLeft: Int? = null,
    @SerialName("over_limit") val overLimit: Boolean = false,
    @SerialName("cooldown_days") val cooldownDays: Int = 0,
    val sports: List<InsightSport> = emptyList(),
)

@Serializable
data class InsightSport(
    val key: String,
    val label: String,
    val selected: Boolean = false,
    val unlocked: Boolean = false,
    /** Until when this chosen sport can't be swapped out; null once it can. */
    @SerialName("locked_until") val lockedUntil: String? = null,
)

@Serializable
data class InsightSportsBody(val sports: List<String>)
