<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\UserWallet;
use App\Module\Base;
use App\Services\Wallet\WalletSignatureService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Request;

class WalletAuthController extends AbstractController
{
    private const CHALLENGE_TTL = 300;

    public function challenge()
    {
        $address = $this->normalizeAddress(Request::input('address'));
        $chainId = trim((string)Request::input('chain_id', config('dootask.wallet_chain_id', '1')));
        $this->validateChain($chainId);
        $nonce = Str::random(32);
        $issuedAt = Carbon::now()->toIso8601String();
        $message = Request::getHost() . " wants you to sign in to YeYing.\n\nAddress: {$address}\nChain ID: {$chainId}\nNonce: {$nonce}\nIssued At: {$issuedAt}";
        Cache::put($this->challengeKey($address, $chainId), [
            'message' => $message,
            'address' => $address,
            'chain_id' => $chainId,
        ], self::CHALLENGE_TTL);
        return Base::retSuccess('success', [
            'challenge' => $message,
            'nonce' => $nonce,
            'expires_at' => Carbon::now()->addSeconds(self::CHALLENGE_TTL)->toIso8601String(),
        ]);
    }

    public function verify()
    {
        $address = $this->normalizeAddress(Request::input('address'));
        $chainId = trim((string)Request::input('chain_id', config('dootask.wallet_chain_id', '1')));
        $this->validateChain($chainId);
        $signature = trim((string)Request::input('signature'));
        $challenge = Cache::pull($this->challengeKey($address, $chainId));
        if (!is_array($challenge)) {
            return Base::retError('钱包登录挑战已过期或无效', ['code' => 'wallet_challenge_invalid']);
        }
        $recovered = app(WalletSignatureService::class)->recoverPersonalSignAddress($challenge['message'], $signature);
        if ($recovered !== $address) {
            return Base::retError('钱包签名地址不匹配', ['code' => 'wallet_signature_mismatch']);
        }
        $wallet = UserWallet::where('chain', 'eip155')->where('chain_id', $chainId)->where('address_normalized', $address)->first();
        if (!$wallet) {
            return Base::retError('钱包尚未绑定 YeYing 账号', ['code' => 'wallet_not_bound', 'address' => $address]);
        }
        $user = User::where('userid', $wallet->userid)->first();
        if (!$user || $user->disable_at) {
            return Base::retError('钱包绑定的账号不可用');
        }
        $wallet->update(['last_login_at' => Carbon::now()]);
        return Base::retSuccess('success', [
            'token' => User::generateTokenNoDevice($user, max(1, intval(Base::settingFind('system', 'token_valid_days', 30))) * 86400),
            'userid' => $user->userid,
            'address' => $address,
            'is_new' => false,
        ]);
    }

    private function normalizeAddress($address): string
    {
        $address = trim((string)$address);
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            abort(422, '钱包地址格式无效');
        }
        return strtolower($address);
    }

    private function validateChain(string $chainId): void
    {
        if ($chainId !== (string)config('dootask.wallet_chain_id', '1')) {
            abort(422, '暂不支持该链网络');
        }
    }

    private function challengeKey(string $address, string $chainId): string
    {
        return 'wallet_login_challenge:' . $chainId . ':' . $address;
    }
}
