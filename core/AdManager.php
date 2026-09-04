<?php
declare(strict_types=1);

/**
 * AdManager logic: pricing, duration calculation, Payhub verification,
 * vertical 9:16 media processing, publisher auth link emails, and impression/click stats.
 */
class AdManager
{
    private static ?PDO $pdo = null;

    private static function db(): PDO
    {
        return self::$pdo ??= Database::getInstance()->getConnection();
    }

    /** Retrieves or initializes the single ad_settings row. */
    public static function getSettings(): array
    {
        $defaults = [
            'id' => 1,
            'price_7_days' => 5000.00,
            'price_14_days' => 9500.00,
            'price_30_days' => 18000.00,
            'price_90_days' => 50000.00,
            'price_per_custom_day' => 800.00,
            'price_per_custom_hour' => 50.00,
            'skip_timer_seconds' => 7,
            'payhub_public_key' => '',
            'payhub_secret_key' => '',
            'bank_name' => '',
            'bank_account_number' => '',
            'bank_account_name' => '',
        ];
        try {
            self::db()->exec("CREATE TABLE IF NOT EXISTS ad_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                price_7_days REAL DEFAULT 5000.00,
                price_14_days REAL DEFAULT 9500.00,
                price_30_days REAL DEFAULT 18000.00,
                price_90_days REAL DEFAULT 50000.00,
                price_per_custom_day REAL DEFAULT 800.00,
                price_per_custom_hour REAL DEFAULT 50.00,
                skip_timer_seconds INTEGER DEFAULT 7,
                payhub_public_key TEXT,
                payhub_secret_key TEXT,
                bank_name TEXT,
                bank_account_number TEXT,
                bank_account_name TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $stmt = self::db()->query('SELECT * FROM ad_settings ORDER BY id ASC LIMIT 1');
            $row = $stmt ? $stmt->fetch() : false;
            if ($row) {
                return array_merge($defaults, $row);
            }
            self::db()->exec("INSERT INTO ad_settings (price_7_days, price_14_days, price_30_days, price_90_days, price_per_custom_day, price_per_custom_hour, skip_timer_seconds) VALUES (5000.00, 9500.00, 18000.00, 50000.00, 800.00, 50.00, 7)");
            $stmt = self::db()->query('SELECT * FROM ad_settings ORDER BY id ASC LIMIT 1');
            return array_merge($defaults, $stmt ? ($stmt->fetch() ?: []) : []);
        } catch (Throwable $e) {
            return $defaults;
        }
    }

    /** Updates ad_settings parameters. */
    public static function updateSettings(array $data): void
    {
        $settings = self::getSettings();
        $stmt = self::db()->prepare("UPDATE ad_settings SET
            price_7_days = ?,
            price_14_days = ?,
            price_30_days = ?,
            price_90_days = ?,
            price_per_custom_day = ?,
            price_per_custom_hour = ?,
            skip_timer_seconds = ?,
            payhub_public_key = ?,
            payhub_secret_key = ?,
            bank_name = ?,
            bank_account_number = ?,
            bank_account_name = ?
            WHERE id = ?");
        $stmt->execute([
            (float) ($data['price_7_days'] ?? $settings['price_7_days']),
            (float) ($data['price_14_days'] ?? $settings['price_14_days']),
            (float) ($data['price_30_days'] ?? $settings['price_30_days']),
            (float) ($data['price_90_days'] ?? $settings['price_90_days']),
            (float) ($data['price_per_custom_day'] ?? $settings['price_per_custom_day']),
            (float) ($data['price_per_custom_hour'] ?? $settings['price_per_custom_hour']),
            max(1, (int) ($data['skip_timer_seconds'] ?? $settings['skip_timer_seconds'])),
            trim((string) ($data['payhub_public_key'] ?? $settings['payhub_public_key'])),
            trim((string) ($data['payhub_secret_key'] ?? $settings['payhub_secret_key'])),
            trim((string) ($data['bank_name'] ?? $settings['bank_name'])),
            trim((string) ($data['bank_account_number'] ?? $settings['bank_account_number'])),
            trim((string) ($data['bank_account_name'] ?? $settings['bank_account_name'])),
            $settings['id']
        ]);
    }

    /** Calculates total price for a given duration type and parameters. */
    public static function calculatePrice(string $durationType, int $customDays = 0, int $customHours = 0): float
    {
        $s = self::getSettings();
        return match ($durationType) {
            '7_days' => (float) $s['price_7_days'],
            '14_days' => (float) $s['price_14_days'],
            '30_days' => (float) $s['price_30_days'],
            '90_days' => (float) $s['price_90_days'],
            'custom' => max(0, $customDays) * (float) $s['price_per_custom_day'] + max(0, $customHours) * (float) $s['price_per_custom_hour'],
            default => (float) $s['price_7_days'],
        };
    }

    /** Calculates duration in total hours. */
    public static function getDurationHours(string $durationType, int $customDays = 0, int $customHours = 0): int
    {
        return match ($durationType) {
            '7_days' => 7 * 24,
            '14_days' => 14 * 24,
            '30_days' => 30 * 24,
            '90_days' => 90 * 24,
            'custom' => (max(0, $customDays) * 24) + max(0, $customHours),
            default => 7 * 24,
        };
    }

    /** Process image file into 9:16 vertical ratio (e.g. 1080x1920) WebP image. */
    public static function processAdImage(string $sourcePath, string $destDir): ?string
    {
        if (!is_file($sourcePath)) {
            return null;
        }
        $info = @getimagesize($sourcePath);
        if (!$info) {
            return null;
        }
        $mime = $info['mime'];
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/gif' => @imagecreatefromgif($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            default => false,
        };
        if ($src === false) {
            return null;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);

        // Target 9:16 ratio (1080x1920)
        $targetW = 1080;
        $targetH = 1920;
        $targetRatio = $targetW / $targetH;
        $srcRatio = $srcW / $srcH;

        if ($srcRatio > $targetRatio) {
            // Source is wider than 9:16 - crop width
            $cropW = (int) round($srcH * $targetRatio);
            $cropH = $srcH;
            $cropX = (int) round(($srcW - $cropW) / 2);
            $cropY = 0;
        } else {
            // Source is taller than 9:16 - crop height
            $cropW = $srcW;
            $cropH = (int) round($srcW / $targetRatio);
            $cropX = 0;
            $cropY = (int) round(($srcH - $cropH) / 2);
        }

        $dst = imagecreatetruecolor($targetW, $targetH);
        imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $targetW, $targetH, $cropW, $cropH);
        imagedestroy($src);

        if (!is_dir($destDir)) {
            mkdir($destDir, 0775, true);
        }
        $filename = 'ad_' . uniqid('', true) . '.webp';
        $outPath = $destDir . '/' . $filename;
        $ok = imagewebp($dst, $outPath, 85);
        imagedestroy($dst);

        return $ok ? $filename : null;
    }

    /** Finds existing publisher by email or creates a new one with a setup token. */
    public static function findOrCreatePublisher(string $name, string $email): array
    {
        $stmt = self::db()->prepare('SELECT * FROM ad_publishers WHERE email = ?');
        $stmt->execute([$email]);
        $publisher = $stmt->fetch();
        if ($publisher) {
            return $publisher;
        }

        $token = bin2hex(random_bytes(24));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        $stmt = self::db()->prepare('INSERT INTO ad_publishers (name, email, setup_token, token_expires_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $email, $token, $expires]);
        $id = (int) self::db()->lastInsertId();

        $stmt = self::db()->prepare('SELECT * FROM ad_publishers WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /** Generates a fresh password setup token for a publisher. */
    public static function generateSetupToken(int $publisherId): string
    {
        $token = bin2hex(random_bytes(24));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        self::db()->prepare('UPDATE ad_publishers SET setup_token = ?, token_expires_at = ? WHERE id = ?')
            ->execute([$token, $expires, $publisherId]);
        return $token;
    }

    /** Sends approval email to publisher containing the Ad Manager link. */
    public static function sendPublisherApprovalEmail(array $publisher, array $ad): void
    {
        $token = !empty($publisher['password_hash']) ? null : self::generateSetupToken((int) $publisher['id']);
        $siteTitle = setting('site_title', 'Church Media');

        $setupUrl = $token ? baseUrl('ads/setup-password?token=' . urlencode($token)) : baseUrl('ads/login');

        $subject = 'Your Advertisement Has Been Approved! — ' . $siteTitle;
        $body = "Hello " . e($publisher['name']) . ",\n\n"
            . "Great news! Your advertisement \"" . e($ad['title']) . "\" has been approved and is now active on " . $siteTitle . "!\n\n"
            . "You can monitor live stats (impressions, clicks, CTR, remaining time), pause or edit your ad, and submit new ads in your Ad Manager account.\n\n"
            . ($token ? "Click the link below to set your account password and log in:\n" . $setupUrl . "\n\n" : "Log into your Ad Manager here:\n" . $setupUrl . "\n\n")
            . "Thank you,\n" . $siteTitle . " Media Team";

        Mailer::send($publisher['email'], $subject, $body);
    }

    /** Notifies super admin when a new ad order is placed or updated. */
    public static function notifySuperAdminNewAd(array $ad, array $publisher): void
    {
        $s = settings();
        $adminEmail = $s['contact_email'] ?? $s['smtp_from'] ?? null;
        if (!$adminEmail) {
            return;
        }
        $siteTitle = $s['site_title'] ?? 'Church Media';
        $subject = 'New Ad Order Submitted: "' . e($ad['title']) . '" — ' . $siteTitle;
        $body = "Hello Admin,\n\n"
            . "A new advertisement placement has been submitted:\n\n"
            . "Title: " . $ad['title'] . "\n"
            . "Publisher: " . $publisher['name'] . " (" . $publisher['email'] . ")\n"
            . "Amount: ₦" . number_format((float) $ad['amount'], 2) . "\n"
            . "Payment Method: " . strtoupper($ad['payment_method']) . "\n"
            . "Payment Status: " . strtoupper($ad['payment_status']) . "\n\n"
            . "Please review and approve/reject this ad in the Admin Panel:\n"
            . baseUrl('admin/ads') . "\n\n"
            . "Thank you!";

        Mailer::send($adminEmail, $subject, $body);
    }

    /** Notifies super admin when a publisher submits an ad edit request. */
    public static function notifySuperAdminEditRequest(array $editReq, array $ad, array $publisher): void
    {
        $s = settings();
        $adminEmail = $s['contact_email'] ?? $s['smtp_from'] ?? null;
        if (!$adminEmail) {
            return;
        }
        $siteTitle = $s['site_title'] ?? 'Church Media';
        $subject = 'Ad Edit Request Submitted: "' . e($ad['title']) . '" — ' . $siteTitle;
        $body = "Hello Admin,\n\n"
            . "Publisher " . $publisher['name'] . " (" . $publisher['email'] . ") has submitted an edit request for ad #" . $ad['id'] . " (\"" . $ad['title'] . "\").\n\n"
            . "New Title: " . $editReq['title'] . "\n"
            . "New Target URL: " . $editReq['target_url'] . "\n\n"
            . "Please review this request in the Admin Panel:\n"
            . baseUrl('admin/ads?action=edits') . "\n\n"
            . "Thank you!";

        Mailer::send($adminEmail, $subject, $body);
    }

    /** Verifies Payhub transaction reference using server-to-server API. */
    public static function verifyPayhubTransaction(string $reference): array
    {
        $settings = self::getSettings();
        $secretKey = $settings['payhub_secret_key'] ?? '';
        if (!$secretKey) {
            return ['status' => false, 'message' => 'Payhub Secret Key is not configured in Admin settings.'];
        }

        $url = 'https://merchant.payhub.com.ng/api/transaction/verify/' . rawurlencode($reference);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $secretKey,
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || !$res) {
            return ['status' => false, 'message' => 'CURL error connecting to Payhub: ' . ($err ?: 'No response')];
        }

        $data = json_decode($res, true);
        if (!$data) {
            return ['status' => false, 'message' => 'Invalid JSON response from Payhub API.'];
        }

        $isPaid = !empty($data['paid']) || (isset($data['data']['status']) && $data['data']['status'] === 'success');
        return [
            'status' => true,
            'paid' => $isPaid,
            'data' => $data['data'] ?? [],
        ];
    }

    /** Approves an ad, sets its activation window, and sends notification to publisher. */
    public static function approveAd(int $adId): bool
    {
        $stmt = self::db()->prepare('SELECT a.*, p.name as publisher_name, p.email as publisher_email, p.password_hash FROM advertisements a JOIN ad_publishers p ON a.publisher_id = p.id WHERE a.id = ?');
        $stmt->execute([$adId]);
        $ad = $stmt->fetch();
        if (!$ad) {
            return false;
        }

        $durationHours = self::getDurationHours($ad['duration_type'], (int) $ad['duration_days'], (int) $ad['duration_hours']);
        $startsAt = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $durationHours . ' hours'));

        $stmt = self::db()->prepare('UPDATE advertisements SET status = "approved", payment_status = "verified", approved_at = NOW(), starts_at = ?, expires_at = ? WHERE id = ?');
        $stmt->execute([$startsAt, $expiresAt, $adId]);

        $publisher = [
            'id' => $ad['publisher_id'],
            'name' => $ad['publisher_name'],
            'email' => $ad['publisher_email'],
            'password_hash' => $ad['password_hash'],
        ];

        self::sendPublisherApprovalEmail($publisher, $ad);
        return true;
    }

    /** Rejects an ad with a reason. */
    public static function rejectAd(int $adId, string $reason): bool
    {
        $stmt = self::db()->prepare('UPDATE advertisements SET status = "rejected", reject_reason = ? WHERE id = ?');
        $stmt->execute([$reason, $adId]);

        $stmt = self::db()->prepare('SELECT a.*, p.name as publisher_name, p.email as publisher_email FROM advertisements a JOIN ad_publishers p ON a.publisher_id = p.id WHERE a.id = ?');
        $stmt->execute([$adId]);
        $ad = $stmt->fetch();
        if ($ad) {
            $siteTitle = setting('site_title', 'Church Media');
            $subject = 'Update regarding your Advertisement — ' . $siteTitle;
            $body = "Hello " . e($ad['publisher_name']) . ",\n\n"
                . "Your advertisement placement \"" . e($ad['title']) . "\" was reviewed but could not be approved at this time.\n\n"
                . "Reason: " . e($reason) . "\n\n"
                . "Please feel free to submit a revised ad placement at:\n"
                . baseUrl('advertise') . "\n\n"
                . "Thank you,\n" . $siteTitle . " Media Team";
            Mailer::send($ad['publisher_email'], $subject, $body);
        }
        return true;
    }

    /** Records an ad impression deduplicated per visitor fingerprint/IP. */
    public static function recordImpression(int $adId, ?string $fingerprint = null, ?string $ip = null): void
    {
        $fingerprint ??= Fingerprint::get();
        $ip ??= clientIp();

        // Expire check
        self::db()->exec("UPDATE advertisements SET status = 'expired' WHERE status = 'approved' AND expires_at <= NOW()");

        // Check deduplication (1 impression per fingerprint per ad every 1 hour)
        $stmt = self::db()->prepare('SELECT id FROM ad_impressions WHERE ad_id = ? AND fingerprint_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) LIMIT 1');
        $stmt->execute([$adId, $fingerprint]);
        if ($stmt->fetch()) {
            return;
        }

        self::db()->prepare('INSERT INTO ad_impressions (ad_id, fingerprint_hash, ip_address) VALUES (?, ?, ?)')
            ->execute([$adId, $fingerprint, $ip]);
        self::db()->prepare('UPDATE advertisements SET impressions_count = impressions_count + 1 WHERE id = ?')
            ->execute([$adId]);
    }

    /** Records an ad click deduplicated per visitor. */
    public static function recordClick(int $adId, ?string $fingerprint = null, ?string $ip = null): void
    {
        $fingerprint ??= Fingerprint::get();
        $ip ??= clientIp();

        self::db()->prepare('INSERT INTO ad_clicks (ad_id, fingerprint_hash, ip_address) VALUES (?, ?, ?)')
            ->execute([$adId, $fingerprint, $ip]);
        self::db()->prepare('UPDATE advertisements SET clicks_count = clicks_count + 1 WHERE id = ?')
            ->execute([$adId]);
    }

    /** Returns active approved non-expired non-paused ads. */
    public static function getActiveAds(): array
    {
        // Auto-expire
        self::db()->exec("UPDATE advertisements SET status = 'expired' WHERE status = 'approved' AND expires_at <= NOW()");

        $stmt = self::db()->query("SELECT * FROM advertisements WHERE status = 'approved' AND starts_at <= NOW() AND expires_at > NOW() ORDER BY RAND()");
        return $stmt->fetchAll();
    }

    /** Aggregates overall ad statistics for admin dashboard. */
    public static function getAdminStats(): array
    {
        // Auto-expire
        self::db()->exec("UPDATE advertisements SET status = 'expired' WHERE status = 'approved' AND expires_at <= NOW()");

        $totalRevenue = (float) self::db()->query("SELECT SUM(amount) FROM advertisements WHERE payment_status = 'verified'")->fetchColumn();
        $totalAds = (int) self::db()->query("SELECT COUNT(*) FROM advertisements")->fetchColumn();
        $activeAds = (int) self::db()->query("SELECT COUNT(*) FROM advertisements WHERE status = 'approved' AND expires_at > NOW()")->fetchColumn();
        $pendingAds = (int) self::db()->query("SELECT COUNT(*) FROM advertisements WHERE status = 'pending'")->fetchColumn();
        $totalImpressions = (int) self::db()->query("SELECT SUM(impressions_count) FROM advertisements")->fetchColumn();
        $totalClicks = (int) self::db()->query("SELECT SUM(clicks_count) FROM advertisements")->fetchColumn();
        $overallCtr = $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0.0;

        return [
            'total_revenue' => $totalRevenue,
            'total_ads' => $totalAds,
            'active_ads' => $activeAds,
            'pending_ads' => $pendingAds,
            'total_impressions' => $totalImpressions,
            'total_clicks' => $totalClicks,
            'ctr' => $overallCtr,
        ];
    }

    /** Returns top performing video and image ads for performance analysis. */
    public static function getTopPerformingAds(int $limit = 10): array
    {
        $stmt = self::db()->prepare("SELECT a.*, p.name as publisher_name, p.email as publisher_email,
            (CASE WHEN a.impressions_count > 0 THEN ROUND((a.clicks_count / a.impressions_count) * 100, 2) ELSE 0 END) as ctr
            FROM advertisements a
            JOIN ad_publishers p ON a.publisher_id = p.id
            ORDER BY a.amount DESC, a.impressions_count DESC, a.clicks_count DESC
            LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
