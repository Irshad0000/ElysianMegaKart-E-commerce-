<?php
declare(strict_types=1);

if (!function_exists('get_razorpay_config')) {
    function get_razorpay_config(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $config = [
            'key_id' => '',
            'key_secret' => '',
            'webhook_secret' => ''
        ];

        $localPath = __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'payment.local.php';
        if (is_file($localPath)) {
            $local = include $localPath;
            if (is_array($local) && isset($local['razorpay']) && is_array($local['razorpay'])) {
                $config = array_merge($config, $local['razorpay']);
            }
        }

        $envMap = [
            'key_id' => ['RAZORPAY_KEY_ID'],
            'key_secret' => ['RAZORPAY_KEY_SECRET'],
            'webhook_secret' => ['RAZORPAY_WEBHOOK_SECRET']
        ];

        foreach ($envMap as $cfgKey => $envNames) {
            foreach ($envNames as $envName) {
                $val = getenv($envName);
                if ($val !== false && trim((string)$val) !== '') {
                    $config[$cfgKey] = trim((string)$val);
                    break;
                }
                if (isset($_ENV[$envName]) && trim((string)$_ENV[$envName]) !== '') {
                    $config[$cfgKey] = trim((string)$_ENV[$envName]);
                    break;
                }
                if (isset($_SERVER[$envName]) && trim((string)$_SERVER[$envName]) !== '') {
                    $config[$cfgKey] = trim((string)$_SERVER[$envName]);
                    break;
                }
            }
        }

        $cached = $config;
        return $cached;
    }
}

if (!function_exists('is_razorpay_configured')) {
    function is_razorpay_configured(): bool
    {
        $cfg = get_razorpay_config();
        return $cfg['key_id'] !== '' && $cfg['key_secret'] !== '';
    }
}

if (!function_exists('create_razorpay_order')) {
    function create_razorpay_order(int $amountPaise, string $currency = 'INR', string $receipt = ''): array
    {
        $cfg = get_razorpay_config();

        if ($amountPaise <= 0) {
            return ['ok' => false, 'error' => 'Invalid payment amount.'];
        }

        if ($cfg['key_id'] === '' || $cfg['key_secret'] === '') {
            return ['ok' => false, 'error' => 'Razorpay API keys are not configured.'];
        }

        $payload = [
            'amount' => $amountPaise,
            'currency' => $currency,
            'receipt' => $receipt !== '' ? $receipt : ('emk_' . time()),
            'payment_capture' => 1
        ];

        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_USERPWD => $cfg['key_id'] . ':' . $cfg['key_secret'],
            CURLOPT_TIMEOUT => 30
        ]);

        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return ['ok' => false, 'error' => 'Gateway request failed: ' . $err];
        }

        $data = json_decode((string)$resp, true);
        if (!is_array($data) || $httpCode < 200 || $httpCode >= 300 || empty($data['id'])) {
            $reason = '';
            if (is_array($data) && isset($data['error']['description'])) {
                $reason = (string)$data['error']['description'];
            }
            if ($reason === '') {
                $reason = 'Unable to create Razorpay order.';
            }
            return ['ok' => false, 'error' => $reason, 'http_code' => $httpCode];
        }

        return [
            'ok' => true,
            'order_id' => (string)$data['id'],
            'raw' => $data
        ];
    }
}

if (!function_exists('verify_razorpay_signature')) {
    function verify_razorpay_signature(string $orderId, string $paymentId, string $signature): bool
    {
        if ($orderId === '' || $paymentId === '' || $signature === '') {
            return false;
        }

        $cfg = get_razorpay_config();
        if ($cfg['key_secret'] === '') {
            return false;
        }

        $payload = $orderId . '|' . $paymentId;
        $expected = hash_hmac('sha256', $payload, $cfg['key_secret']);
        return hash_equals($expected, $signature);
    }
}
