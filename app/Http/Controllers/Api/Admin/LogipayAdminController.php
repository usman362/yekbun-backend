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
     * Stats cards for the top of LogipayManageUsersPage
     */
    public function stats()
    {
        $newRequests   = Wallet::where('status', 'pending')->count();
        $activeWallets = Wallet::where('status', 'active')->count();
        $closedWallets = Wallet::whereIn('status', ['closed', 'suspended', 'deactivated'])->count();
        $totalWallets  = Wallet::count();

        return ResponseHelper::sendResponse([
            'newRequests'   => $newRequests,
            'activeWallets' => $activeWallets,
            'closedWallets' => $closedWallets,
            'totalWallets'  => $totalWallets,
        ], 'Logipay stats fetched');
    }

    /**
     * New wallet requests (KYC pending)
     */
    public function newRequests(Request $request)
    {
        $search  = $request->get('search', '');
        $page    = (int) $request->get('page', 1);
        $perPage = min((int) $request->get('per_page', 10), 100);

        $query = Wallet::where('status', 'pending')->orderBy('created_at', 'desc');

        if ($search) {
            $userIds = User::where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->pluck('_id')->toArray();
            $query->whereIn('user_id', $userIds);
        }

        $total   = $query->count();
        $wallets = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $rows = $wallets->map(function ($wallet) {
            $user = User::find($wallet->user_id);
            $kyc  = KycVerification::where('user_id', $wallet->user_id)
                        ->orderBy('created_at', 'desc')->first();

            return [
                'id'                => (int) ($wallet->_id ?? 0),
                'name'              => $user->name ?? 'Unknown',
                'username'          => $user->username ?? '',
                'walletId'          => 'W-' . substr((string) $wallet->_id, -6),
                'requestDate'       => $wallet->created_at ? Carbon::parse($wallet->created_at)->format('M d, Y') : '',
                'kycStatus'         => $this->mapKycStatus($kyc->status ?? 'pending'),
                'verificationLevel' => $this->mapVerificationLevel($kyc),
                'country'           => $user->country ?? '',
                'avatar'            => Helpers::mediaUrl($user->image) ?? '',
            ];
        })->values();

        return ResponseHelper::sendResponse([
            'rows'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'last_page' => max(1, ceil($total / $perPage)),
        ], 'New requests fetched');
    }

    /**
     * Active wallets
     */
    public function activeWallets(Request $request)
    {
        $search  = $request->get('search', '');
        $page    = (int) $request->get('page', 1);
        $perPage = min((int) $request->get('per_page', 10), 100);

        $query = Wallet::where('status', 'active')->orderBy('updated_at', 'desc');

        if ($search) {
            $userIds = User::where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->pluck('_id')->toArray();
            $query->whereIn('user_id', $userIds);
        }

        $total   = $query->count();
        $wallets = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $rows = $wallets->map(function ($wallet) {
            $user = User::find($wallet->user_id);
            $kyc  = KycVerification::where('user_id', $wallet->user_id)
                        ->orderBy('created_at', 'desc')->first();

            return [
                'id'                => (int) ($wallet->_id ?? 0),
                'name'              => $user->name ?? 'Unknown',
                'username'          => $user->username ?? '',
                'walletId'          => 'W-' . substr((string) $wallet->_id, -6),
                'walletStatus'      => ucfirst($wallet->status ?? 'Active'),
                'verificationLevel' => $this->mapVerificationLevel($kyc),
                'balance'           => number_format($wallet->balance ?? 0, 2) . ' ZER',
                'lastActivity'      => $wallet->updated_at ? Carbon::parse($wallet->updated_at)->diffForHumans() : '',
                'avatar'            => Helpers::mediaUrl($user->image) ?? '',
            ];
        })->values();

        return ResponseHelper::sendResponse([
            'rows'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'last_page' => max(1, ceil($total / $perPage)),
        ], 'Active wallets fetched');
    }

    /**
     * Closed / suspended / deactivated wallets
     */
    public function closedWallets(Request $request)
    {
        $search  = $request->get('search', '');
        $page    = (int) $request->get('page', 1);
        $perPage = min((int) $request->get('per_page', 10), 100);

        $query = Wallet::whereIn('status', ['closed', 'suspended', 'deactivated'])
            ->orderBy('updated_at', 'desc');

        if ($search) {
            $userIds = User::where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->pluck('_id')->toArray();
            $query->whereIn('user_id', $userIds);
        }

        $total   = $query->count();
        $wallets = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $rows = $wallets->map(function ($wallet) {
            $user = User::find($wallet->user_id);

            return [
                'id'         => (int) ($wallet->_id ?? 0),
                'name'       => $user->name ?? 'Unknown',
                'username'   => $user->username ?? '',
                'walletId'   => 'W-' . substr((string) $wallet->_id, -6),
                'closedDate' => $wallet->updated_at ? Carbon::parse($wallet->updated_at)->format('M d, Y') : '',
                'reason'     => $wallet->status_reason ?? 'N/A',
                'lastStatus' => ucfirst($wallet->status ?? 'Closed'),
                'avatar'     => Helpers::mediaUrl($user->image) ?? '',
            ];
        })->values();

        return ResponseHelper::sendResponse([
            'rows'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'last_page' => max(1, ceil($total / $perPage)),
        ], 'Closed wallets fetched');
    }

    // ── Helpers ──

    private function mapKycStatus(?string $status): string
    {
        return match ($status) {
            'approved'     => 'Approved',
            'rejected'     => 'Rejected',
            'under_review' => 'Under Review',
            default        => 'Pending',
        };
    }

    private function mapVerificationLevel($kyc): string
    {
        if (!$kyc) return 'Basic';

        if ($kyc->status === 'approved' && $kyc->selfie_image) return 'Full';
        if ($kyc->status === 'approved') return 'Plus';

        return 'Basic';
    }
}
