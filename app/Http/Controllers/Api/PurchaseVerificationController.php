<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyPurchaseRequest;
use App\Models\UserPurchase;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Imdhemy\AppStore\Exceptions\InvalidReceiptException as AppStoreInvalidReceiptException;
use Imdhemy\AppStore\ValueObjects\Status as AppStoreStatus;
use Imdhemy\Purchases\Facades\Product;
use Imdhemy\Purchases\Facades\Subscription;
use Throwable;
use Tymon\JWTAuth\Facades\JWTAuth;

class PurchaseVerificationController extends Controller
{
    public function verify(VerifyPurchaseRequest $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $payload = $request->validated();

        $platform = $payload['platform'];
        $type = $payload['type'];
        $productId = $payload['product_id'];
        $transactionId = $payload['transaction_id'] ?? null;
        $purchaseToken = $payload['purchase_token'] ?? null;
        $transactionReceipt = $payload['transaction_receipt'] ?? null;

        if ($transactionId) {
            $existing = UserPurchase::query()
                ->where('platform', $platform)
                ->where('transaction_id', $transactionId)
                ->first();

            if ($existing && $existing->is_valid) {
                return ResponseHelper::sendResponse(
                    [
                        'product_id' => $existing->product_id,
                        'type' => $existing->type,
                        'expires_at' => optional($existing->expires_at)->toISOString(),
                        'entitlement' => $this->entitlementFromProductId($existing->product_id),
                    ],
                    'Purchase verified',
                    true,
                    200
                );
            }
        }

        try {
            if ($platform === 'ios') {
                if (! $transactionReceipt) {
                    return $this->fail('Purchase verification failed', 'INVALID_RECEIPT', 422);
                }

                return $this->verifyIos(
                    userId: (int) $user->id,
                    type: $type,
                    productId: $productId,
                    transactionId: $transactionId,
                    transactionReceipt: $transactionReceipt
                );
            }

            if (! $purchaseToken) {
                return $this->fail('Purchase verification failed', 'INVALID_RECEIPT', 422);
            }

            return $this->verifyAndroid(
                userId: (int) $user->id,
                type: $type,
                productId: $productId,
                transactionId: $transactionId,
                purchaseToken: $purchaseToken
            );
        } catch (AppStoreInvalidReceiptException $e) {
            return $this->fail('Purchase verification failed', 'INVALID_RECEIPT', 422);
        } catch (Throwable $e) {
            return $this->fail('Purchase verification failed', 'SERVER_ERROR', 500);
        }
    }

    private function verifyIos(
        int $userId,
        string $type,
        string $productId,
        ?string $transactionId,
        string $transactionReceipt
    ) {
        $password = (string) config('liap.appstore_password');

        if ($type === 'subs') {
            $receiptResponse = Subscription::appStore()
                ->receiptData($transactionReceipt)
                ->password($password)
                ->verifyRenewable();

            $status = $receiptResponse->getStatus();
            $latest = $receiptResponse->getLatestReceiptInfo() ?? [];

            $match = collect($latest)->first(function ($info) use ($productId, $transactionId) {
                if ($info->getProductId() !== $productId) {
                    return false;
                }
                if ($transactionId && $info->getTransactionId() !== $transactionId) {
                    return false;
                }
                return true;
            });

            if (! $match) {
                $this->storePurchase(
                    userId: $userId,
                    platform: 'ios',
                    type: $type,
                    productId: $productId,
                    transactionId: $transactionId,
                    purchaseToken: null,
                    isValid: false,
                    expiresAt: null,
                    rawResponse: $receiptResponse->toArray()
                );

                return $this->fail('Purchase verification failed', 'INVALID_RECEIPT', 422);
            }

            $expiresAt = $match->getExpiresDate();
            $expiresAtCarbon = $expiresAt ? Carbon::parse($expiresAt) : null;

            $isExpired =
                $status->getValue() === AppStoreStatus::STATUS_SUBSCRIPTION_EXPIRED ||
                ($expiresAtCarbon && $expiresAtCarbon->isPast());

            $this->storePurchase(
                userId: $userId,
                platform: 'ios',
                type: $type,
                productId: $productId,
                transactionId: $match->getTransactionId(),
                purchaseToken: null,
                isValid: ! $isExpired,
                expiresAt: $expiresAtCarbon,
                rawResponse: $receiptResponse->toArray()
            );

            if ($isExpired) {
                return $this->fail('Purchase verification failed', 'EXPIRED', 422);
            }

            return ResponseHelper::sendResponse(
                [
                    'product_id' => $productId,
                    'type' => $type,
                    'expires_at' => optional($expiresAtCarbon)->toISOString(),
                    'entitlement' => $this->entitlementFromProductId($productId),
                ],
                'Purchase verified',
                true,
                200
            );
        }

        $receiptResponse = Product::appStore()
            ->receiptData($transactionReceipt)
            ->password($password)
            ->verifyReceipt();

        $receipt = $receiptResponse->getReceipt();
        $inApps = $receipt ? ($receipt->getInApp() ?? []) : [];

        $match = collect($inApps)->first(function ($inApp) use ($productId, $transactionId) {
            if ($inApp->getProductId() !== $productId) {
                return false;
            }
            if ($transactionId && $inApp->getTransactionId() !== $transactionId) {
                return false;
            }
            return true;
        });

        $this->storePurchase(
            userId: $userId,
            platform: 'ios',
            type: $type,
            productId: $productId,
            transactionId: $match ? $match->getTransactionId() : $transactionId,
            purchaseToken: null,
            isValid: (bool) $match,
            expiresAt: null,
            rawResponse: $receiptResponse->toArray()
        );

        if (! $match) {
            return $this->fail('Purchase verification failed', 'INVALID_RECEIPT', 422);
        }

        return ResponseHelper::sendResponse(
            [
                'product_id' => $productId,
                'type' => $type,
                'expires_at' => null,
                'entitlement' => $this->entitlementFromProductId($productId),
            ],
            'Purchase verified',
            true,
            200
        );
    }

    private function verifyAndroid(
        int $userId,
        string $type,
        string $productId,
        ?string $transactionId,
        string $purchaseToken
    ) {
        if ($type === 'subs') {
            $subscriptionReceipt = Subscription::googlePlay()
                ->id($productId)
                ->token($purchaseToken)
                ->get();

            $expiresAtMillis = $subscriptionReceipt->getExpiryTimeMillis();
            $expiresAt = $expiresAtMillis ? Carbon::createFromTimestampMs((int) $expiresAtMillis) : null;
            $paymentState = (int) $subscriptionReceipt->getPaymentState();

            $isExpired = $expiresAt ? $expiresAt->isPast() : false;
            $isValid = ($paymentState === 1) && ! $isExpired;

            $this->storePurchase(
                userId: $userId,
                platform: 'android',
                type: $type,
                productId: $productId,
                transactionId: $transactionId,
                purchaseToken: $purchaseToken,
                isValid: $isValid,
                expiresAt: $expiresAt,
                rawResponse: $this->safeToArray($subscriptionReceipt)
            );

            if (! $isValid) {
                return $this->fail('Purchase verification failed', $isExpired ? 'EXPIRED' : 'INVALID_RECEIPT', 422);
            }

            return ResponseHelper::sendResponse(
                [
                    'product_id' => $productId,
                    'type' => $type,
                    'expires_at' => optional($expiresAt)->toISOString(),
                    'entitlement' => $this->entitlementFromProductId($productId),
                ],
                'Purchase verified',
                true,
                200
            );
        }

        $productReceipt = Product::googlePlay()
            ->id($productId)
            ->token($purchaseToken)
            ->get();

        $purchaseState = method_exists($productReceipt, 'getPurchaseState')
            ? (int) $productReceipt->getPurchaseState()
            : null;

        $isValid = ($purchaseState === null) ? true : ($purchaseState === 0);

        $this->storePurchase(
            userId: $userId,
            platform: 'android',
            type: $type,
            productId: $productId,
            transactionId: $transactionId,
            purchaseToken: $purchaseToken,
            isValid: $isValid,
            expiresAt: null,
            rawResponse: $this->safeToArray($productReceipt)
        );

        if (! $isValid) {
            return $this->fail('Purchase verification failed', 'INVALID_RECEIPT', 422);
        }

        return ResponseHelper::sendResponse(
            [
                'product_id' => $productId,
                'type' => $type,
                'expires_at' => null,
                'entitlement' => $this->entitlementFromProductId($productId),
            ],
            'Purchase verified',
            true,
            200
        );
    }

    private function storePurchase(
        int $userId,
        string $platform,
        string $type,
        string $productId,
        ?string $transactionId,
        ?string $purchaseToken,
        bool $isValid,
        ?Carbon $expiresAt,
        array $rawResponse
    ): void {
        $unique = ['platform' => $platform];
        if ($transactionId) {
            $unique['transaction_id'] = $transactionId;
        } elseif ($purchaseToken) {
            $unique['purchase_token'] = $purchaseToken;
        }

        UserPurchase::query()->updateOrCreate(
            $unique,
            [
                'user_id' => $userId,
                'platform' => $platform,
                'type' => $type,
                'product_id' => $productId,
                'transaction_id' => $transactionId,
                'purchase_token' => $purchaseToken,
                'is_valid' => $isValid,
                'expires_at' => $expiresAt,
                'raw_response' => $rawResponse,
            ]
        );
    }

    private function safeToArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return (array) $value->toArray();
        }

        return json_decode(json_encode($value), true) ?: [];
    }

    private function entitlementFromProductId(string $productId): string
    {
        $base = Str::of($productId)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');
        return (string) $base;
    }

    private function fail(string $message, string $code, int $status)
    {
        return response()->json(
            [
                'success' => false,
                'message' => $message,
                'error_code' => $code,
            ],
            $status
        );
    }
}

