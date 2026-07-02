<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\KycVerification;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\ZercashSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class KycApiController extends Controller
{


    public function sendOtp()
    {
        $user = User::find(Auth::id());
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found.', false, 404);
        }
        // Generate 6-digit OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        // Store OTP on user (expires in 10 minutes)
        $user->kyc_otp = $otp;
        $user->kyc_otp_expires_at = Carbon::now()->addMinutes(10)->toDateTimeString();
        $user->save();
        // Send OTP via email (using existing mail setup)
        $email = $user->email;
        if ($email) {
            try {
                Mail::raw("Your KYC verification code is: {$otp}\n\nThis code expires in 10 minutes.", function ($message) use ($email, $user) {
                    $message->to($email)->subject('YekBûn KYC Verification Code');
                });
            } catch (\Exception $e) {
                // Log error but don't fail - OTP is still stored
            }
        }
        // Mask email for response
        $maskedEmail = $this->maskEmail($email ?? '');
        return ResponseHelper::sendResponse([
            'sent_to' => $maskedEmail,
            'expires_in' => 600, // seconds
        ], 'OTP sent to your registered email.');
    }

    public function verifyOtp(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return ResponseHelper::sendResponse(
                $validator->errors(),
                'Validation error.',
                false,
                422
            );
        }

        $user = User::find(Auth::id());

        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found.', false, 404);
        }

        if (empty($user->kyc_otp)) {
            return ResponseHelper::sendResponse(null, 'No OTP found.', false, 400);
        }

        if ($user->kyc_otp !== $request->otp) {
            return ResponseHelper::sendResponse(null, 'Invalid OTP.', false, 401);
        }

        $user->kyc_otp = null;
        $user->kyc_otp_verified = true;
        $user->save();

        return ResponseHelper::sendResponse([
            'verified'    => true,
            'userDetails' => $this->getUserDetails($user),
        ], 'OTP verified successfully.');
    }


    public function submit(Request $request)
    {

        $rules = [
            'document_type' => 'required|in:national_id,passport,driver_license,work_company_license',
            'document_front' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'selfie' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'full_name' => 'nullable|string|max:255',
            'document_number' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|string',
            'nationality' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|string',
        ];

        $docType = $request->input('document_type');

        if (in_array($docType, ['national_id', 'driver_license'])) {
            $rules['document_back'] = 'required|file|mimes:jpg,jpeg,png,pdf|max:5120';
        } else {
            $rules['document_back'] = 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120';
        }

        $validator = Validator::make($request->all(), $rules, [
            'document_back.required' => 'Back of document is required for ' . str_replace('_', ' ', $docType ?? '') . '.',
        ]);

        if ($validator->fails()) {
            return ResponseHelper::sendResponse(
                $validator->errors(),
                'Validation error.',
                false,
                422
            );
        }

        $user = User::find(Auth::id());

        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found.', false, 404);
        }

        if (empty($user->kyc_otp_verified)) {
            return ResponseHelper::sendResponse(null, 'Please verify OTP first.', false, 403);
        }

        $kyc = new KycVerification();

        $kyc->user_id = $user->_id;
        $kyc->document_type = $docType;

        $frontFile = $request->file('document_front');

        $frontName = 'kyc_front_' . $user->_id . '_' . time() . '.' . $frontFile->getClientOriginalExtension();

        $kyc->document_front = $frontFile->storeAs('kyc/' . $user->_id, $frontName, 'public');

        if ($request->hasFile('document_back')) {

            $backFile = $request->file('document_back');

            $backName = 'kyc_back_' . $user->_id . '_' . time() . '.' . $backFile->getClientOriginalExtension();

            $kyc->document_back = $backFile->storeAs('kyc/' . $user->_id, $backName, 'public');
        }

        if ($request->hasFile('selfie')) {

            $selfieFile = $request->file('selfie');

            $selfieName = 'kyc_selfie_' . $user->_id . '_' . time() . '.' . $selfieFile->getClientOriginalExtension();

            $kyc->selfie_with_id = $selfieFile->storeAs('kyc/' . $user->_id, $selfieName, 'public');
        }

        $kyc->full_name = $request->full_name;
        $kyc->document_number = $request->document_number;
        $kyc->date_of_birth = $request->date_of_birth;
        $kyc->nationality = $request->nationality;
        $kyc->expiry_date = $request->expiry_date;

        $kyc->status = 'pending';
        $kyc->submitted_at = Carbon::now();

        $kyc->save();

        $user->kyc_status = 'pending';
        $user->save();

        return ResponseHelper::sendResponse([
            'kyc_id'      => $kyc->_id,
            'status'      => 'pending',
            'userDetails' => $this->getUserDetails($user),
        ], 'KYC submitted.');
    }


    public function review(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'kyc_id' => 'required|string',
            'action' => 'required|in:approve,reject',
            'reason' => 'required_if:action,reject|nullable|string',
        ]);

        if ($validator->fails()) {
            return ResponseHelper::sendResponse(
                $validator->errors(),
                'Validation error.',
                false,
                422
            );
        }

        $kyc = KycVerification::find($request->kyc_id);

        if (!$kyc) {
            return ResponseHelper::sendResponse(null, 'KYC record not found.', false, 404);
        }

        $user = User::find($kyc->user_id);

        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found.', false, 404);
        }

        if ($request->action === 'approve') {

            $kyc->status = 'approved';
            $kyc->reviewed_at = Carbon::now();
            $kyc->save();

            $user->kyc_status = 'approved';

            // Create or activate wallet on KYC approval
            $wallet = Wallet::where('user_id', $user->getKey())->first();
            if (!$wallet) {
                $wallet = new Wallet();
                $wallet->user_id      = $user->getKey();
                $wallet->status       = 'activated';
                $wallet->balance      = 0;
                $wallet->activated_at = Carbon::now();
                $wallet->created_at   = Carbon::now();
                $wallet->save();
            } else {
                $wallet->status       = 'activated';
                $wallet->activated_at = Carbon::now();
                $wallet->save();
            }

            $user->wallet_id     = $wallet->getKey();
            $user->wallet_status = 'activated';
            $user->save();

            return ResponseHelper::sendResponse([
                'kyc_status'  => 'approved',
                'userDetails' => $this->getUserDetails($user),
            ], 'KYC approved and wallet activated');
        } else {

            $kyc->status = 'rejected';
            $kyc->rejection_reason = $request->reason;
            $kyc->reviewed_at = Carbon::now();
            $kyc->save();

            $user->kyc_status = 'rejected';
            $user->save();

            return ResponseHelper::sendResponse([
                'kyc_status'  => 'rejected',
                'userDetails' => $this->getUserDetails($user),
            ], 'KYC rejected');
        }
    }

    public function status()
    {
        $user = User::find(Auth::id());
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found.', false, 404);
        }
        $userDetails = $this->getUserDetails($user);
        $kyc = KycVerification::where('user_id', $user->_id)->orderBy('created_at', 'desc')->first();
        if (!$kyc) {
            return ResponseHelper::sendResponse(['has_kyc' => false, 'kyc_status' => null, 'userDetails' => $userDetails,], 'No KYC submission found.');
        }
        $statusMessages = ['draft' => 'KYC documents are being uploaded.', 'pending' => 'Your documents are submitted and waiting for review.', 'under_review' => 'Our team is currently reviewing your documents.', 'approved' => 'All wallet features are now available.', 'rejected' => 'Your KYC was rejected. Please resubmit.',];
        return ResponseHelper::sendResponse(['has_kyc' => true, 'kyc_id' => $kyc->_id, 'kyc_status' => $kyc->status, 'status_message' => $statusMessages[$kyc->status] ?? 'Unknown status.', 'document_type' => $kyc->document_type, 'full_name' => $kyc->full_name, 'rejection_reason' => $kyc->rejection_reason, 'submitted_at' => $kyc->submitted_at ? Carbon::parse($kyc->submitted_at)->format('d M Y H:i') : null, 'reviewed_at' => $kyc->reviewed_at ? Carbon::parse($kyc->reviewed_at)->format('d M Y H:i') : null, 'userDetails' => $userDetails,], 'KYC status fetched.');
    }

    public function pendingList(Request $request)
    {
        $perPage = $request->query('per_page', 20);
        $status = $request->query('status', 'pending');
        $query = KycVerification::whereIn('status', $status === 'all' ? ['pending', 'under_review', 'approved', 'rejected'] : [$status])->orderBy('submitted_at', 'desc');
        $kycs = $query->paginate($perPage);
        $items = $kycs->map(function ($kyc) {
            return ['kyc_id' => $kyc->_id, 'user_id' => $kyc->user_id, 'full_name' => $kyc->full_name, 'document_type' => $kyc->document_type, 'document_number' => $kyc->document_number, 'status' => $kyc->status, 'submitted_at' => $kyc->submitted_at ? Carbon::parse($kyc->submitted_at)->format('d M Y H:i') : null, 'document_front' => $kyc->document_front ? asset('storage/' . $kyc->document_front) : null, 'document_back' => $kyc->document_back ? asset('storage/' . $kyc->document_back) : null, 'selfie_with_id' => $kyc->selfie_with_id ? asset('storage/' . $kyc->selfie_with_id) : null,];
        });
        return ResponseHelper::sendResponse(['items' => $items, 'current_page' => $kycs->currentPage(), 'last_page' => $kycs->lastPage(), 'total' => $kycs->total(),], 'KYC list fetched.');
    }

    private function getUserDetails($user)
    {
        $user = $user->fresh();

        // Wallet info
        $wallet = Wallet::where('user_id', $user->_id)->first();
        $walletStatusMessages = [
            'not_found'    => 'No wallet found. Please create one.',
            'pending'      => 'Your request is pending review.',
            'under_review' => 'We will review your request. We will get back soon.',
            'active'       => 'All wallet features are now available.',
            'activated'    => 'All wallet features are now available.',
            'approved'     => 'All wallet features are now available.',
            'on_hold'      => 'Wallet is on Hold. See the reason here.',
            'rejected'     => 'Your wallet request was rejected.',
            'closed'       => 'Wallet is Closed. The account will be removed after 90 Days.',
        ];

        if ($wallet) {
            $wStatus = $wallet->status ?? 'under_review';
            $walletNumber = $wallet->formattedWalletNumber();

            $walletData = [
                'has_wallet'            => true,
                // Accept both `active` and `activated` as a valid live wallet.
                'has_valid'             => in_array($wStatus, ['activated', 'active', 'approved'], true),
                'has_pin'               => !empty($wallet->pin),
                'welcome_bonus_claimed' => !empty($wallet->welcome_bonus_claimed),
                'wallet_id'             => $walletNumber,
                'wallet_number'         => $walletNumber,
                'wallet_status'         => $wStatus,
                // Admin-stored message wins; falls back to the static map.
                'status_message'        => $wallet->status_message ?? ($walletStatusMessages[$wStatus] ?? 'Unknown status.'),
                'hold_reason'           => $wallet->status_reason ?? null,
                'balance'               => round($wallet->balance ?? 0, 2),
                'expire_at'             => $wallet->expire_at ?? null,
                'created_at'            => $wallet->created_at ?? null,
            ];
        } else {
            $walletData = [
                'has_wallet'            => false,
                'has_valid'             => false,
                'has_pin'               => false,
                'welcome_bonus_claimed' => false,
                'wallet_id'             => null,
                'wallet_status'         => 'not_found',
                'status_message'        => $walletStatusMessages['not_found'],
                'hold_reason'           => null,
                'balance'               => 0,
                'expire_at'             => null,
                'created_at'            => null,
            ];
        }

        // KYC info
        $kyc = KycVerification::where('user_id', $user->_id)->orderBy('created_at', 'desc')->first();
        $kycStatusMessages = [
            'not_submitted' => 'KYC not submitted yet.',
            'pending'       => 'Your documents are submitted and waiting for review.',
            'under_review'  => 'Our team is currently reviewing your documents.',
            'approved'      => 'All wallet features are now available.',
            'rejected'      => 'Your KYC was rejected. Please resubmit.',
        ];

        if ($kyc) {
            $kycData = [
                'has_kyc'          => true,
                'kyc_id'           => $kyc->_id,
                'kyc_status'       => $kyc->status,
                'status_message'   => $kycStatusMessages[$kyc->status] ?? 'Unknown status.',
                'document_type'    => $kyc->document_type,
                'rejection_reason' => $kyc->rejection_reason ?? null,
                'submitted_at'     => $kyc->submitted_at ? Carbon::parse($kyc->submitted_at)->format('d M Y H:i') : null,
                'reviewed_at'      => $kyc->reviewed_at ? Carbon::parse($kyc->reviewed_at)->format('d M Y H:i') : null,
            ];
        } else {
            $kycData = [
                'has_kyc'          => false,
                'kyc_id'           => null,
                'kyc_status'       => 'not_submitted',
                'status_message'   => $kycStatusMessages['not_submitted'],
                'document_type'    => null,
                'rejection_reason' => null,
                'submitted_at'     => null,
                'reviewed_at'      => null,
            ];
        }

        // Embed wallet & kyc inside user object
        $userData = $user->toArray();
        $userData['wallet'] = $walletData;
        $userData['kyc'] = $kycData;

        return $userData;
    }

    private function maskEmail($email)
    {
        if (empty($email) || !str_contains($email, '@')) {
            return '***@***.***';
        }

        [$name, $domain] = explode('@', $email);
        $domainParts = explode('.', $domain);

        $maskedName = strlen($name) <= 2
            ? str_repeat('*', strlen($name))
            : substr($name, 0, 2) . str_repeat('*', strlen($name) - 2);

        $maskedDomain = strlen($domainParts[0]) <= 2
            ? str_repeat('*', strlen($domainParts[0]))
            : substr($domainParts[0], 0, 2) . str_repeat('*', strlen($domainParts[0]) - 2);

        $domainParts[0] = $maskedDomain;

        return $maskedName . '@' . implode('.', $domainParts);
    }

    public function documentTypes()
    {
        $types = [['key' => 'national_id', 'label' => 'National ID Card', 'description' => 'Government-issued national identity card.', 'requires_back' => true,], ['key' => 'passport', 'label' => 'Passport', 'description' => 'Valid international passport.', 'requires_back' => false,], ['key' => 'driver_license', 'label' => 'Driver License', 'description' => 'Valid driving license with photo.', 'requires_back' => true,], ['key' => 'work_company_license', 'label' => 'Work & Company License', 'description' => 'Official work permit or company license.', 'requires_back' => false,],];
        return ResponseHelper::sendResponse($types, 'Document types fetched.');
    }
}
