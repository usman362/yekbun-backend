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

class LogipayAdminController extends Controller
{
    /**
     * Stats cards for the top of LogipayManageUsersPage.
     *
     * Data model notes:
     *  - "New Requests"   = KYC submissions awaiting review (kyc_verifications.status = 'pending')
     *                      OR users whose wallet_status = 'under_review' / 'pending'.
     *  - "Active Wallets" = users with wallet_status = 'activated' / 'active'
     *  - "Closed Wallets" = users with wallet_status in (closed / suspended / deactivated / rejected)
     */
    public function stats()
    {
        $newRequests = KycVerification::where('status', 'pending')->count()
            + User::whereIn('wallet_status', ['under_review', 'pending'])
                ->whereNotIn('_id', KycVerification::where('status', 'pending')->pluck('user_id')->toArray())
                ->count();

        $activeWallets = User::whereIn('wallet_status', ['activated', 'active'])->count();
        $closedWallets = User::whereIn('wallet_status', ['closed', 'suspended', 'deactivated', 'rejected'])->count();

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
     * Returns the pending KYC submissions joined with their submitting user. Falls back to users
     * whose wallet_status is under_review when no KYC document was uploaded.
     */
    public function newRequests(Request $request)
    {
        $search  = $request->get('search', '');
        $page    = (int) $request->get('page', 1);
        $perPage = min((int) $request->get('per_page', 10), 100);

        // 1) Pending KYC records.
        $kycQuery = KycVerification::where('status', 'pending')->orderBy('created_at', 'desc');
        $pendingKyc = $kycQuery->get();
        $kycUserIds = $pendingKyc->pluck('user_id')->filter()->map(fn($id) => (string) $id)->toArray();

        // 2) Users in review without a KYC record yet (started OTP but didn't upload docs).
        $extraUsers = User::whereIn('wallet_status', ['under_review', 'pending'])
            ->whereNotIn('_id', $kycUserIds)
            ->orderBy('updated_at', 'desc')
            ->get();

        // Merge into a single sortable list keyed by user.
        $allUserIds = array_unique(array_merge($kycUserIds, $extraUsers->pluck('_id')->map(fn($id) => (string) $id)->toArray()));

        $userQuery = User::whereIn('_id', $allUserIds);
        if ($search) {
            $userQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        $users = $userQuery->get()->keyBy('_id');

        $total = $users->count();
        $rows = $users->values()->slice(($page - 1) * $perPage, $perPage);

        $result = $rows->map(function ($user) use ($pendingKyc) {
            $kyc = $pendingKyc->first(fn($k) => (string) $k->user_id === (string) $user->_id);

            return [
                'id'                => (string) $user->_id,
                'kycId'             => $kyc ? (string) $kyc->_id : null,
                'name'              => trim(($user->name ?? '') . ' ' . ($user->last_name ?? '')),
                'username'          => $user->username ?? '',
                'walletId'          => $user->wallet_id ?? ('W-' . substr((string) $user->_id, -6)),
                'requestDate'       => $kyc?->submitted_at
                    ? Carbon::parse($kyc->submitted_at)->format('M d, Y')
                    : ($user->updated_at ? Carbon::parse($user->updated_at)->format('M d, Y') : ''),
                'kycStatus'         => $this->mapKycStatus($user, $kyc),
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
     * GET /admin/logipay/active-wallets
     */
    public function activeWallets(Request $request)
    {
        $search  = $request->get('search', '');
        $page    = (int) $request->get('page', 1);
        $perPage = min((int) $request->get('per_page', 10), 100);

        $query = User::whereIn('wallet_status', ['activated', 'active'])->orderBy('updated_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $total = $query->count();
        $users = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $userIds = $users->pluck('_id')->map(fn($id) => (string) $id)->toArray();
        $kycByUser = KycVerification::whereIn('user_id', $userIds)->get()
            ->sortByDesc('created_at')->keyBy(fn($k) => (string) $k->user_id);
        $walletByUser = Wallet::whereIn('user_id', $userIds)->get()->keyBy(fn($w) => (string) $w->user_id);

        $rows = $users->map(function ($user) use ($kycByUser, $walletByUser) {
            $wallet = $walletByUser->get((string) $user->_id);
            $kyc = $kycByUser->get((string) $user->_id);

            return [
                'id'                => (string) $user->_id,
                'name'              => trim(($user->name ?? '') . ' ' . ($user->last_name ?? '')),
                'username'          => $user->username ?? '',
                'walletId'          => $user->wallet_id ?? ('W-' . substr((string) $user->_id, -6)),
                'walletStatus'      => ucfirst($user->wallet_status ?? 'Active'),
                'verificationLevel' => $this->mapVerificationLevel($kyc),
                'balance'           => number_format(($wallet->balance ?? $user->wallet_balance ?? 0), 2) . ' ZER',
                'lastActivity'      => $user->updated_at ? Carbon::parse($user->updated_at)->diffForHumans() : '',
                'avatar'            => Helpers::mediaUrl($user->image) ?? '',
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
     * GET /admin/logipay/closed-wallets
     */
    public function closedWallets(Request $request)
    {
        $search  = $request->get('search', '');
        $page    = (int) $request->get('page', 1);
        $perPage = min((int) $request->get('per_page', 10), 100);

        $query = User::whereIn('wallet_status', ['closed', 'suspended', 'deactivated', 'rejected'])
            ->orderBy('updated_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $total = $query->count();
        $users = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $rows = $users->map(function ($user) {
            return [
                'id'         => (string) $user->_id,
                'name'       => trim(($user->name ?? '') . ' ' . ($user->last_name ?? '')),
                'username'   => $user->username ?? '',
                'walletId'   => $user->wallet_id ?? ('W-' . substr((string) $user->_id, -6)),
                'closedDate' => $user->updated_at ? Carbon::parse($user->updated_at)->format('M d, Y') : '',
                'reason'     => $user->wallet_status_reason ?? 'N/A',
                'lastStatus' => ucfirst($user->wallet_status ?? 'Closed'),
                'avatar'     => Helpers::mediaUrl($user->image) ?? '',
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
     * GET /admin/logipay/request/{userId}
     *
     * Returns full KYC + wallet detail for the request-detail side panel.
     */
    public function showRequest(string $userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found', false, 404);
        }

        $kyc = KycVerification::where('user_id', (string) $user->_id)->orderBy('created_at', 'desc')->first();
        $wallet = Wallet::where('user_id', (string) $user->_id)->first();

        return ResponseHelper::sendResponse([
            'user' => [
                'id'        => (string) $user->_id,
                'name'      => trim(($user->name ?? '') . ' ' . ($user->last_name ?? '')),
                'username'  => $user->username ?? '',
                'email'     => $user->email ?? '',
                'phone'     => $user->phone ?? '',
                'country'   => $user->country ?? '',
                'city'      => $user->city ?? '',
                'province'  => $user->province ?? '',
                'avatar'    => Helpers::mediaUrl($user->image) ?? '',
                'walletId'  => $user->wallet_id ?? null,
                'walletStatus' => $user->wallet_status ?? 'not_found',
                'walletBalance' => (float) ($user->wallet_balance ?? 0),
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
     * POST /admin/logipay/request/{userId}/approve
     */
    public function approveRequest(string $userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found', false, 404);
        }

        $kyc = KycVerification::where('user_id', (string) $user->_id)->where('status', 'pending')->orderBy('created_at', 'desc')->first();
        if ($kyc) {
            $kyc->status = 'approved';
            $kyc->reviewed_at = Carbon::now();
            $kyc->save();
        }

        $user->kyc_status = 'approved';
        $user->wallet_status = 'activated';
        if (empty($user->wallet_id)) {
            $user->wallet_id = strtoupper(substr(bin2hex(random_bytes(8)), 0, 16));
        }
        $user->save();

        // Ensure wallet record exists.
        $wallet = Wallet::firstOrNew(['user_id' => (string) $user->_id]);
        $wallet->user_id = (string) $user->_id;
        $wallet->status = 'active';
        if (!isset($wallet->balance)) $wallet->balance = 0;
        $wallet->save();

        return ResponseHelper::sendResponse(['id' => (string) $user->_id], 'Request approved');
    }

    /**
     * POST /admin/logipay/request/{userId}/reject
     */
    public function rejectRequest(Request $request, string $userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found', false, 404);
        }

        $reason = $request->input('reason', 'Rejected by admin');

        $kyc = KycVerification::where('user_id', (string) $user->_id)->where('status', 'pending')->orderBy('created_at', 'desc')->first();
        if ($kyc) {
            $kyc->status = 'rejected';
            $kyc->rejection_reason = $reason;
            $kyc->reviewed_at = Carbon::now();
            $kyc->save();
        }

        $user->kyc_status = 'rejected';
        $user->wallet_status = 'rejected';
        $user->wallet_status_reason = $reason;
        $user->save();

        return ResponseHelper::sendResponse(['id' => (string) $user->_id], 'Request rejected');
    }

    // ── Helpers ──

    private function mapKycStatus(User $user, ?KycVerification $kyc): string
    {
        $status = $kyc?->status ?? $user->kyc_status ?? null;
        return match ($status) {
            'approved'     => 'Approved',
            'rejected'     => 'Rejected',
            'under_review' => 'Under Review',
            'pending'      => 'Pending',
            default        => $user->kyc_otp_verified ? 'Under Review' : 'Pending',
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
