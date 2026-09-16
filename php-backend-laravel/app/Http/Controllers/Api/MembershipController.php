<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MemberPlanPrice;
use App\Models\MemberSubscription;
use App\Models\User;
use App\Services\Membership\MembershipException;
use App\Services\Membership\MembershipPresenter;
use App\Services\Membership\MemberSubscriptions;
use App\Services\Membership\SportInsightsAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Member plans (Free / Pro / Hero): catalogue, current membership, checkout, cancellation
 * and billing history. Partner plans live under /api/partner and never pass through here.
 */
final class MembershipController extends Controller
{
    public function __construct(
        private readonly MemberSubscriptions $subscriptions,
        private readonly MembershipPresenter $presenter,
    ) {}

    /** GET /api/membership/plans — public catalogue, tailored to the caller when signed in. */
    public function plans(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->presenter->catalogue($this->member($request))]);
    }

    /** GET /api/membership */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->presenter->membership($this->requireMember($request))]);
    }

    /** POST /api/membership/subscribe {price_id} */
    public function subscribe(Request $request): JsonResponse
    {
        $user = $this->requireMember($request);
        $data = $request->validate(['price_id' => ['required', 'integer']]);

        $price = MemberPlanPrice::query()->with('plan')->find($data['price_id']);
        if ($price === null) {
            return $this->error(new MembershipException("This plan isn't available to buy right now.", 422, 'price_unavailable'));
        }

        try {
            return response()->json(['data' => $this->subscriptions->subscribe($user, $price)], 201);
        } catch (MembershipException $e) {
            return $this->error($e);
        }
    }

    /** POST /api/membership/verify {razorpay_payment_id, razorpay_subscription_id, razorpay_signature} */
    public function verify(Request $request): JsonResponse
    {
        $user = $this->requireMember($request);
        $data = $request->validate([
            'razorpay_payment_id' => ['required', 'string', 'max:64'],
            'razorpay_subscription_id' => ['required', 'string', 'max:64'],
            'razorpay_signature' => ['required', 'string', 'max:128'],
        ]);

        try {
            $subscription = $this->subscriptions->verifyCheckout(
                $user,
                $data['razorpay_payment_id'],
                $data['razorpay_subscription_id'],
                $data['razorpay_signature'],
            );
        } catch (MembershipException $e) {
            return $this->error($e);
        }

        return response()->json(['data' => [
            // The app keeps polling GET /api/membership while this is true.
            'confirmed' => in_array($subscription->status, [MemberSubscription::STATUS_ACTIVE], true)
                || ($subscription->status === MemberSubscription::STATUS_AUTHENTICATED && $subscription->starts_at?->isFuture()),
            'subscription' => $this->presenter->subscription($subscription),
            'membership' => $this->presenter->membership($user),
        ]]);
    }

    /** POST /api/membership/abandon {subscription_id} */
    public function abandon(Request $request): JsonResponse
    {
        $user = $this->requireMember($request);
        $data = $request->validate(['subscription_id' => ['required', 'string', 'max:64']]);

        $this->subscriptions->abandon($user, $data['subscription_id']);

        return response()->json(['data' => $this->presenter->membership($user)]);
    }

    /** POST /api/membership/cancel {at_period_end?: bool} */
    public function cancel(Request $request): JsonResponse
    {
        $user = $this->requireMember($request);
        $data = $request->validate(['at_period_end' => ['sometimes', 'boolean']]);

        try {
            $this->subscriptions->cancel($user, (bool) ($data['at_period_end'] ?? true), $user);
        } catch (MembershipException $e) {
            return $this->error($e);
        }

        return response()->json(['data' => $this->presenter->membership($user)]);
    }

    /** GET /api/membership/payments */
    public function payments(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->presenter->payments($this->requireMember($request))]);
    }

    /** GET /api/membership/insight-sports */
    public function insightSports(Request $request, SportInsightsAccess $access): JsonResponse
    {
        return response()->json(['data' => $access->status($this->requireMember($request))]);
    }

    /** PUT /api/membership/insight-sports {sports: string[]} */
    public function saveInsightSports(Request $request, SportInsightsAccess $access): JsonResponse
    {
        $user = $this->requireMember($request);
        $data = $request->validate([
            'sports' => ['present', 'array', 'max:20'],
            'sports.*' => ['string', 'max:30'],
        ]);

        try {
            return response()->json(['data' => $access->save($user, $data['sports'])]);
        } catch (MembershipException $e) {
            return $this->error($e);
        }
    }

    private function member(Request $request): ?User
    {
        $user = $request->attributes->get('auth_user');

        return $user instanceof User ? $user : null;
    }

    private function requireMember(Request $request): User
    {
        // auth.jwt guarantees this; the check keeps a mis-wired route from serving a guest.
        return $this->member($request) ?? abort(401, 'Unauthorized');
    }

    private function error(MembershipException $e): JsonResponse
    {
        $status = $e->status >= 400 && $e->status < 600 ? $e->status : 422;

        return response()->json(['error' => $e->getMessage(), 'code' => $e->errorCode], $status);
    }
}
