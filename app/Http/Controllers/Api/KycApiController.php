<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\KycVerification;
use App\Models\User;
use App\Models\Wallet;
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
        if (!$user) return ResponseHelper::sendResponse(null, 'User not found.', false, 404);

        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->kyc_otp = $otp;
        $user->kyc_otp_expires_at = Carbon::now()->addMinutes(10)->toDateTimeString();
        $user->save();

        if ($user->email) {
            try {
                Mail::raw("Your KYC verification code is: {$otp}\n\nThis code expires in 10 minutes.", function ($message) use ($user) {
                    $message->to($user->email)->subject('YekBûn KYC Verification Code');
                });
            } catch (\Exception $e) {}
        }

        return ResponseHelper::sendResponse(['sent_to' => $this->maskEmail($user->email ?? ''), 'expires_in' => 600], 'OTP sent to your registered email.');
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), ['otp' => 'required|string|size:6']);
        if ($validator->fails()) return ResponseHelper::sendResponse($validator->errors(), 'Validation error.', false, 422);

        $user = User::find(Auth::id());
        if (!$user) return ResponseHelper::sendResponse(null, 'User not found.', false, 404);
        if (empty($user->kyc_otp)) return ResponseHelper::sendResponse(null, 'No OTP found.', false, 400);
        if ($user->kyc_otp !== $request->otp) return ResponseHelper::sendResponse(null, 'Invalid OTP.', false, 401);

        $user->kyc_otp = null;
        $user->kyc_otp_verified = true;
        $user->save();

        return ResponseHelper::sendResponse(['verified' => true, 'userDetails' => $this->getUserDetails($user)], 'OTP verified successfully.');
    }

    public function submit(Request $request)
    {
        $rules = [
            'document_type' => 'required|in:national_id,passport,driver_license,work_company_license',
            'document_front' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'selfie' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'full_name' => 'nullable|string|max:255',
            'document_number' => 'nullable|string|max:100',
        ];

        $docType = $request->input('document_type');
        if (in_array($docType, ['national_id', 'driver_license'])) {
            $rules['document_back'] = 'required|file|mimes:jpg,jpeg,png,pdf|max:5120';
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) return ResponseHelper::sendResponse($validator->errors(), 'Validation error.', false, 422);

        $user = User::find(Auth::id());
        if (!$user) return ResponseHelper::sendResponse(null, 'User not found.', false, 404);
        if (empty($user->kyc_otp_verified)) return ResponseHelper::sendResponse(null, 'Please verify OTP first.', false, 403);

        $kyc = new KycVerification();
        $kyc->user_id = $user->_id;
        $kyc->document_type = $docType;
        $kyc->document_front = $request->file('document_front')->storeAs('kyc/' . $user->_id, 'kyc_front_' . $user->_id . '_' . time() . '.' . $request->file('document_front')->getClientOriginalExtension(), 'public');

        if ($request->hasFile('document_back')) {
            $kyc->document_back = $request->file('document_back')->storeAs('kyc/' . $user->_id, 'kyc_back_' . $user->_id . '_' . time() . '.' . $request->file('document_back')->getClientOriginalExtension(), 'public');
        }
        if ($request->hasFile('selfie')) {
            $kyc->selfie_with_id = $request->file('selfie')->storeAs('kyc/' . $user->_id, 'kyc_selfie_' . $user->_id . '_' . time() . '.' . $request->file('selfie')->getClientOriginalExtension(), 'public');
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

        return ResponseHelper::sendResponse(['kyc_id' => $kyc->_id, 'status' => 'pending', 'userDetails' => $this->getUserDetails($user)], 'KYC submitted.');
    }

    public function review(Request $request)
    {
        $validator = Validator::make($request->all(), ['kyc_id' => 'required|string', 'action' => 'required|in:approve,reject', 'reason' => 'required_if:action,reject|nullable|string']);
        if ($validator->fails()) return ResponseHelper::sendResponse($validator->errors(), 'Validation error.', false, 422);

        $kyc = KycVerification::find($request->kyc_id);
        if (!$kyc) return ResponseHelper::sendResponse(null, 'KYC record not found.', false, 404);

        $user = User::find($kyc->user_id);
        if (!$user) return ResponseHelper::sendResponse(null, 'User not found.', false, 404);

        if ($request->action === 'approve') {
            $kyc->status = 'approved';
            $kyc->reviewed_at = Carbon::now();
            $kyc->save();
            $user->kyc_status = 'approved';
            $user->save();
            return ResponseHelper::sendResponse(['kyc_status' => 'approved', 'userDetails' => $this->getUserDetails($user)], 'KYC approved');
        } else {
            $kyc->status = 'rejected';
            $kyc->rejection_reason = $request->reason;
            $kyc->reviewed_at = Carbon::now();
            $kyc->save();
            $user->kyc_status = 'rejected';
            $user->save();
            return ResponseHelper::sendResponse(['kyc_status' => 'rejected', 'userDetails' => $this->getUserDetails($user)], 'KYC rejected');
        }
    }

    public function status()
    {
        $user = User::find(Auth::id());
        if (!$user) return ResponseHelper::sendResponse(null, 'User not found.', false, 404);

        $kyc = KycVerification::where('user_id', $user->_id)->orderBy('created_at', 'desc')->first();
        if (!$kyc) return ResponseHelper::sendResponse(['has_kyc' => false, 'kyc_status' => null, 'userDetails' => $this->getUserDetails($user)], 'No KYC submission found.');

        $statusMessages = ['pending' => 'Your documents are submitted and waiting for review.', 'under_review' => 'Our team is currently reviewing your documents.', 'approved' => 'Your KYC is approved. Your wallet is now active!', 'rejected' => 'Your KYC was rejected. Please resubmit.'];

        return ResponseHelper::sendResponse([
            'has_kyc' => true, 'kyc_id' => $kyc->_id, 'kyc_status' => $kyc->status,
            'status_message' => $statusMessages[$kyc->status] ?? 'Unknown status.',
            'document_type' => $kyc->document_type, 'rejection_reason' => $kyc->rejection_reason,
            'userDetails' => $this->getUserDetails($user),
        ], 'KYC status fetched.');
    }

    public function pendingList(Request $request)
    {
        $status = $request->query('status', 'pending');
        $query = KycVerification::whereIn('status', $status === 'all' ? ['pending', 'under_review', 'approved', 'rejected'] : [$status])->orderBy('submitted_at', 'desc');
        $kycs = $query->paginate($request->query('per_page', 20));
        return ResponseHelper::sendResponse($kycs, 'KYC list fetched.');
    }

    public function documentTypes()
    {
        $types = [
            ['key' => 'national_id', 'label' => 'National ID Card', 'description' => 'Government-issued national identity card.', 'requires_back' => true],
            ['key' => 'passport', 'label' => 'Passport', 'description' => 'Valid international passport.', 'requires_back' => false],
            ['key' => 'driver_license', 'label' => 'Driver License', 'description' => 'Valid driving license with photo.', 'requires_back' => true],
            ['key' => 'work_company_license', 'label' => 'Work & Company License', 'description' => 'Official work permit or company license.', 'requires_back' => false],
        ];
        return ResponseHelper::sendResponse($types, 'Document types fetched.');
    }

    private function getUserDetails($user)
    {
        $user = $user->fresh();
        $wallet = Wallet::where('user_id', $user->_id)->first();
        $walletData = $wallet ? ['has_wallet' => true, 'wallet_status' => $wallet->status ?? 'under_review', 'balance' => round($wallet->balance ?? 0, 2)] : ['has_wallet' => false, 'wallet_status' => 'not_found', 'balance' => 0];
        $kyc = KycVerification::where('user_id', $user->_id)->orderBy('created_at', 'desc')->first();
        $kycData = $kyc ? ['has_kyc' => true, 'kyc_status' => $kyc->status] : ['has_kyc' => false, 'kyc_status' => 'not_submitted'];
        $userData = $user->toArray();
        $userData['wallet'] = $walletData;
        $userData['kyc'] = $kycData;
        return $userData;
    }

    private function maskEmail($email)
    {
        if (empty($email) || !str_contains($email, '@')) return '***@***.***';
        [$name, $domain] = explode('@', $email);
        $maskedName = strlen($name) <= 2 ? str_repeat('*', strlen($name)) : substr($name, 0, 2) . str_repeat('*', strlen($name) - 2);
        return $maskedName . '@' . $domain;
    }
}
