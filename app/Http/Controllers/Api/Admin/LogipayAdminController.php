<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
use App\Models\Wallet;
use App\Models\User;
use App\Models\KycVerification;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Source-of-truth design:
 *   - Wallet state  → `wallets` collection
 *   - KYC state     → `kyc_verifications` collection
 *
 * We never read or write `users.wallet_status`, `users.kyc_status`, `users.wallet_id` etc.
 * Deleting a wallet/kyc row removes the user from the corresponding list, and the user record
 * itself stays clean (no orphan flags lingering).
 */
class LogipayAdminController extends Controller
{
    /**
     * Stats cards for the top of LogipayManageUsersPage.
     *
     *  - "New Requests"   = wallets in pending/under_review  ∪  KYC submissions in pending/under_review
     *                       (deduplicated by user_id so the same user isn't double-counted).
     *  - "Active Wallets" = wallets with status in (active / activated / approved)
     *  - "Closed Wallets" = wallets with status in (closed / suspended / deactivated / rejected)
     */
    public function stats()
    {
        $pendingUserIds = $this->pendingUserIds();
        $newRequests = count($pendingUserIds);

        $activeWallets = Wallet::whereIn('status', ['active', 'activated', 'approved'])->count();
        $closedWallets = Wallet::whereIn('status', ['closed', 'suspended', 'deactivated', 'rejected'])->count();

        $totalWallets = $newRequests + $activeWallets + $closedWallets;

        return ResponseHelper::sendResponse([
            'newRequests'   => $newRequests,
            'activeWallets' => $activeWallets,
            'closedWallets' => $closedWallets,
            'totalWallets'  => $totalWallets,
        ], 'Logipay stats fetched');
    }

    /**
     * GET /admin/logipay/new-requests
     *
     * Lists users with an outstanding wallet or KYC request. Pulls user_ids exclusively from the
     * Wallet + KycVerification collections so deletion of a row removes them automatically.
     */
    public function newRequests(Request $request)
    {
        $search  = $request->get('search', '');
        $page    = (int) $request->get('page', 1);
        $perPage = min((int) $request->get('per_page', 10), 100);

        $pendingUserIds = $this->pendingUserIds();
        // Load pending KYC + wallet rows so we can attach metadata to each user row.
        $pendingKyc = KycVerification::whereIn('status', ['pending', 'under_review'])->get()->keyBy(fn ($k) => (string) $k->user_id);
        $pendingWallets = Wallet::whereIn('status', ['pending', 'under_review'])->get()->keyBy(fn ($w) => (string) $w->user_id);

        $userQuery = User::whereIn('_id', $pendingUserIds);
        if ($search) {
            $userQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        $users = $userQuery->get();

        $total = $users->count();
        $rows = $users->values()->slice(($page - 1) * $perPage, $perPage);

        $result = $rows->map(function ($user) use ($pendingKyc, $pendingWallets) {
            $userIdStr = (string) $user->_id;
            $kyc = $pendingKyc->get($userIdStr);
            $wallet = $pendingWallets->get($userIdStr);
            $createdSource = $wallet?->created_at ?? $kyc?->submitted_at ?? $kyc?->created_at;

            return [
                'id'                => $userIdStr,
                'kycId'             => $kyc ? (string) $kyc->_id : null,
                'walletId'          => $wallet ? 'W-' . substr((string) $wallet->_id, -6) : 'W-' . substr($userIdStr, -6),
                'name'              => trim(($user->name ?? '') . ' ' . ($user->last_name ?? '')),
                'username'          => $user->username ?? '',
                'requestDate'       => $createdSource ? Carbon::parse($createdSource)->format('M d, Y') : '',
                'kycStatus'         => $this->mapKycStatus($kyc),
                'verificationLevel' => $this->mapVerificationLevel($kyc),
                'country'           => $user->country ?? '',
                'avatar'            => Helpers::mediaUrl($user->image) ?? '',
            ];
        })->values();

        return ResponseHelper::sendResponse([
            'rows'      => $result,
            'total'     => $total,
            'page'      => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ], 'New requests fetched');
    }

    /**
     * GET /admin/logipay/active-wallets — users whose `wallets.status` is active/activated/approved.
     */
    public function activeWallets(Request $request)
    {
        $search  = $request->get('search', '');
        $page    = (int) $request->get('page', 1);
        $perPage = min((int) $request->get('per_page', 10), 100);

        $walletsQuery = Wallet::whereIn('status', ['active', 'activated', 'approved'])
            ->orderBy('updated_at', 'desc');
        $allActiveWallets = $walletsQuery->get();
        $userIds = $allActiveWallets->pluck('user_id')->map(fn ($id) => (string) $id)->unique()->toArray();

        $userQuery = User::whereIn('_id', $userIds);
        if ($search) {
            $userQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        $matchingUsers = $userQuery->get()->keyBy(fn ($u) => (string) $u->_id);

        // Filter the wallet list down to those whose user matched the search.
        $filteredWallets = $allActiveWallets
            ->filter(fn ($w) => $matchingUsers->has((string) $w->user_id))
            ->values();
        $total = $filteredWallets->count();
        $page_wallets = $filteredWallets->slice(($page - 1) * $perPage, $perPage)->values();

        // KYC info for verification level.
        $kycByUser = KycVerification::whereIn('user_id', $userIds)
            ->get()
            ->sortByDesc('created_at')
            ->keyBy(fn ($k) => (string) $k->user_id);

        $rows = $page_wallets->map(function ($wallet) use ($matchingUsers, $kycByUser) {
            $userIdStr = (string) $wallet->user_id;
            $user = $matchingUsers->get($userIdStr);
            $kyc = $kycByUser->get($userIdStr);

            return [
                'id'                => $userIdStr,
                'name'              => $user ? trim(($user->name ?? '') . ' ' . ($user->last_name ?? '')) : 'Unknown',
                'username'          => $user->username ?? '',
                'walletId'          => 'W-' . substr((string) $wallet->_id, -6),
                'walletStatus'      => ucfirst($wallet->status ?? 'Active'),
                'verificationLevel' => $this->mapVerificationLevel($kyc),
                'balance'           => number_format((float) ($wallet->balance ?? 0), 2) . ' ZER',
                'lastActivity'      => $wallet->updated_at ? Carbon::parse($wallet->updated_at)->diffForHumans() : '',
                'avatar'            => $user ? (Helpers::mediaUrl($user->image) ?? '') : '',
            ];
        })->values();

        return ResponseHelper::sendResponse([
            'rows'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ], 'Active wallets fetched');
    }

    /**
     * GET /admin/logipay/closed-wallets — wallets with status in closed/suspended/deactivated/rejected.
     */
    public function closedWallets(Request $request)
    {
        $search  = $request->get('search', '');
        $page    = (int) $request->get('page', 1);
        $perPage = min((int) $request->get('per_page', 10), 100);

        $walletsQuery = Wallet::whereIn('status', ['closed', 'suspended', 'deactivated', 'rejected'])
            ->orderBy('updated_at', 'desc');
        $allClosedWallets = $walletsQuery->get();
        $userIds = $allClosedWallets->pluck('user_id')->map(fn ($id) => (string) $id)->unique()->toArray();

        $userQuery = User::whereIn('_id', $userIds);
        if ($search) {
            $userQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        $matchingUsers = $userQuery->get()->keyBy(fn ($u) => (string) $u->_id);

        $filteredWallets = $allClosedWallets
            ->filter(fn ($w) => $matchingUsers->has((string) $w->user_id))
            ->values();
        $total = $filteredWallets->count();
        $page_wallets = $filteredWallets->slice(($page - 1) * $perPage, $perPage)->values();

        $rows = $page_wallets->map(function ($wallet) use ($matchingUsers) {
            $userIdStr = (string) $wallet->user_id;
            $user = $matchingUsers->get($userIdStr);

            return [
                'id'         => $userIdStr,
                'name'       => $user ? trim(($user->name ?? '') . ' ' . ($user->last_name ?? '')) : 'Unknown',
                'username'   => $user->username ?? '',
                'walletId'   => 'W-' . substr((string) $wallet->_id, -6),
                'closedDate' => $wallet->updated_at ? Carbon::parse($wallet->updated_at)->format('M d, Y') : '',
                'reason'     => $wallet->status_reason ?? 'N/A',
                'lastStatus' => ucfirst($wallet->status ?? 'Closed'),
                'avatar'     => $user ? (Helpers::mediaUrl($user->image) ?? '') : '',
            ];
        })->values();

        return ResponseHelper::sendResponse([
            'rows'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ], 'Closed wallets fetched');
    }

    /**
     * GET /admin/logipay/request/{userId} — full KYC + wallet detail for the side panel.
     */
    public function showRequest(string $userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found', false, 404);
        }

        $userIdStr = (string) $user->_id;
        $kyc = KycVerification::where(function ($q) use ($userIdStr, $user) {
            $q->where('user_id', $userIdStr)->orWhere('user_id', $user->_id);
        })->orderBy('created_at', 'desc')->first();
        $wallet = Wallet::where('user_id', $userIdStr)->first();

        return ResponseHelper::sendResponse([
            'user' => [
                'id'        => $userIdStr,
                'name'      => trim(($user->name ?? '') . ' ' . ($user->last_name ?? '')),
                'username'  => $user->username ?? '',
                'email'     => $user->email ?? '',
                'phone'     => $user->phone ?? '',
                'country'   => $user->country ?? '',
                'city'      => $user->city ?? '',
                'province'  => $user->province ?? '',
                'avatar'    => Helpers::mediaUrl($user->image) ?? '',
                'walletId'      => $wallet ? (string) $wallet->_id : null,
                'walletStatus'  => $wallet?->status ?? 'not_found',
                'walletBalance' => (float) ($wallet?->balance ?? 0),
            ],
            'kyc' => $kyc ? [
                'id'             => (string) $kyc->_id,
                'status'         => $kyc->status ?? 'pending',
                'full_name'      => $kyc->full_name ?? null,
                'document_type'  => $kyc->document_type ?? null,
                'document_number'=> $kyc->document_number ?? null,
                'date_of_birth'  => $kyc->date_of_birth ?? null,
                'nationality'    => $kyc->nationality ?? null,
                'expiry_date'    => $kyc->expiry_date ?? null,
                'front_image'    => Helpers::mediaUrl($kyc->front_image ?? $kyc->document_front ?? null),
                'back_image'     => Helpers::mediaUrl($kyc->back_image ?? $kyc->document_back ?? null),
                'selfie_image'   => Helpers::mediaUrl($kyc->selfie_image ?? $kyc->selfie_with_id ?? null),
                'submitted_at'   => $kyc->submitted_at ? Carbon::parse($kyc->submitted_at)->format('d M Y H:i') : null,
                'reviewed_at'    => $kyc->reviewed_at ? Carbon::parse($kyc->reviewed_at)->format('d M Y H:i') : null,
                'rejection_reason' => $kyc->rejection_reason ?? null,
            ] : null,
            'wallet' => $wallet ? [
                'id'      => (string) $wallet->_id,
                'status'  => $wallet->status ?? null,
                'balance' => (float) ($wallet->balance ?? 0),
                'created_at' => $wallet->created_at ? Carbon::parse($wallet->created_at)->format('d M Y') : null,
            ] : null,
        ], 'Request detail fetched');
    }

    /**
     * POST /admin/logipay/request/{userId}/approve — flip wallet active + KYC approved.
     * Writes ONLY to Wallet + KycVerification (no user-table mirroring).
     * Also strips legacy wallet/kyc duplicate fields from the user document.
     */
    public function approveRequest(string $userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found', false, 404);
        }
        $userIdStr = (string) $user->_id;

        $wallet = Wallet::firstOrNew(['user_id' => $userIdStr]);
        $wallet->user_id = $userIdStr;
        if (!isset($wallet->balance)) $wallet->balance = 0;
        // Canonical mobile status string (matches mobile `$walletStatusMessages` lookup).
        $wallet->status = 'activated';
        $wallet->status_reason = null;
        $wallet->status_message = 'All wallet features are now available.';
        // Don't grant welcome bonus here — the mobile claims it via the popup that appears after
        // the user sets a wallet PIN (see WalletApiController::verifyPin). Auto-granting on the
        // admin side hides that popup because `welcome_bonus_claimed` would already be true.
        $wallet->save();

        $kycs = KycVerification::where(function ($q) use ($userIdStr, $user) {
            $q->where('user_id', $userIdStr)->orWhere('user_id', $user->_id);
        })->get();

        if ($kycs->isEmpty()) {
            // Synthesise a KYC record if none exists (e.g. user only OTP-verified, never uploaded
            // documents) so mobile's `/kyc/status` returns approved state consistent with wallet.
            $kyc = new KycVerification();
            $kyc->user_id = $userIdStr;
            $kyc->status = 'approved';
            $kyc->submitted_at = Carbon::now();
            $kyc->reviewed_at = Carbon::now();
            $kyc->save();
            $kycs = collect([$kyc]);
        } else {
            foreach ($kycs as $kyc) {
                $kyc->status = 'approved';
                $kyc->reviewed_at = Carbon::now();
                $kyc->rejection_reason = null;
                $kyc->save();
            }
        }

        $this->stripLegacyWalletFields($user);

        return ResponseHelper::sendResponse([
            'id'                  => $userIdStr,
            'wallet_status'       => 'activated',
            'kyc_records_updated' => $kycs->count(),
            'balance'             => (float) $wallet->balance,
        ], 'Request approved');
    }

    /**
     * POST /admin/logipay/request/{userId}/reject — wallet rejected + KYC rejected with reason.
     */
    public function rejectRequest(Request $request, string $userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found', false, 404);
        }
        $userIdStr = (string) $user->_id;
        $reason = $request->input('reason', 'Rejected by admin');

        $wallet = Wallet::firstOrNew(['user_id' => $userIdStr]);
        $wallet->user_id = $userIdStr;
        if (!isset($wallet->balance)) $wallet->balance = 0;
        $wallet->status = 'rejected';
        $wallet->status_reason = $reason;
        $wallet->save();

        $kycs = KycVerification::where(function ($q) use ($userIdStr, $user) {
            $q->where('user_id', $userIdStr)->orWhere('user_id', $user->_id);
        })->get();

        if ($kycs->isEmpty()) {
            $kyc = new KycVerification();
            $kyc->user_id = $userIdStr;
            $kyc->status = 'rejected';
            $kyc->rejection_reason = $reason;
            $kyc->submitted_at = Carbon::now();
            $kyc->reviewed_at = Carbon::now();
            $kyc->save();
            $kycs = collect([$kyc]);
        } else {
            foreach ($kycs as $kyc) {
                $kyc->status = 'rejected';
                $kyc->rejection_reason = $reason;
                $kyc->reviewed_at = Carbon::now();
                $kyc->save();
            }
        }

        $this->stripLegacyWalletFields($user);

        return ResponseHelper::sendResponse([
            'id' => $userIdStr,
            'wallet_status' => 'rejected',
            'kyc_records_updated' => $kycs->count(),
        ], 'Request rejected');
    }

    /**
     * Same cleanup helper as UsersController::stripLegacyWalletFields — removes the historical
     * wallet/kyc duplicate fields from the user document so they don't linger after an admin
     * action. Authoritative state lives in wallets / kyc_verifications now.
     */
    private function stripLegacyWalletFields(User $user): void
    {
        User::raw(function ($collection) use ($user) {
            $collection->updateOne(
                ['_id' => $user->_id],
                ['$unset' => [
                    'wallet_status'         => '',
                    'wallet_status_reason'  => '',
                    'wallet_id'             => '',
                    'wallet_balance'        => '',
                    'wallet_pin'            => '',
                    'wallet_created_at'     => '',
                    'wallet_expire_at'      => '',
                    'kyc_status'            => '',
                    'kyc_otp'               => '',
                    'kyc_otp_expires_at'    => '',
                    'kyc_otp_verified'      => '',
                    'zer_balance'           => '',
                ]]
            );
        });
    }

    // ── Helpers ──

    /**
     * Distinct user_ids who have a pending wallet OR pending KYC submission. The two sources are
     * merged so we don't show the same user twice in "New Requests".
     */
    private function pendingUserIds(): array
    {
        $kycUserIds = KycVerification::whereIn('status', ['pending', 'under_review'])
            ->pluck('user_id')->map(fn ($id) => (string) $id)->toArray();
        $walletUserIds = Wallet::whereIn('status', ['pending', 'under_review'])
            ->pluck('user_id')->map(fn ($id) => (string) $id)->toArray();
        return array_values(array_unique(array_merge($kycUserIds, $walletUserIds)));
    }

    private function mapKycStatus(?KycVerification $kyc): string
    {
        $status = $kyc?->status ?? null;
        return match ($status) {
            'approved'     => 'Approved',
            'rejected'     => 'Rejected',
            'under_review' => 'Under Review',
            'pending'      => 'Pending',
            default        => 'Pending',
        };
    }

    private function mapVerificationLevel(?KycVerification $kyc): string
    {
        if (!$kyc) return 'Basic';
        $selfie = $kyc->selfie_image ?? $kyc->selfie_with_id ?? null;
        if ($kyc->status === 'approved' && $selfie) return 'Full';
        if ($kyc->status === 'approved') return 'Plus';
        return 'Basic';
    }
}
