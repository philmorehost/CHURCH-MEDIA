<?php
declare(strict_types=1);

$metaTitle = 'Place an Advertisement';
$metaDescription = 'Advertise your product or event on our website and Mobile App feed with Reels-style vertical display.';

$settings = AdManager::getSettings();
$errors = [];
$successMessage = '';
$payhubConfig = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!RateLimiter::attempt('ad_submit', clientIp(), 5, 900)) {
        $errors[] = 'Too many attempts — please wait a few minutes before submitting another advertisement.';
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        $targetUrl = trim((string) ($_POST['target_url'] ?? ''));
        $ctaLabel = trim((string) ($_POST['cta_label'] ?? 'Learn More'));
        $publisherName = trim((string) ($_POST['publisher_name'] ?? ''));
        $publisherEmail = trim((string) ($_POST['publisher_email'] ?? ''));
        $durationType = (string) ($_POST['duration_type'] ?? '7_days');
        $customDays = (int) ($_POST['custom_days'] ?? 0);
        $customHours = (int) ($_POST['custom_hours'] ?? 0);
        $paymentMethod = (string) ($_POST['payment_method'] ?? 'payhub');

        if ($title === '') {
            $errors[] = 'Please enter an advertisement title.';
        }
        if ($targetUrl === '' || !filter_var($targetUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'Please enter a valid target website URL (e.g. https://example.com).';
        }
        if ($publisherName === '' || !filter_var($publisherEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter your name and a valid email address.';
        }

        $amount = AdManager::calculatePrice($durationType, $customDays, $customHours);
        if ($amount <= 0) {
            $errors[] = 'Invalid duration selected.';
        }

        // Check uploaded file
        if (empty($_FILES['ad_media']['tmp_name']) || ($_FILES['ad_media']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'Please upload an ad image or video file.';
        }

        $mediaPath = null;
        $originalMediaPath = null;
        $thumbnailPath = null;
        $mediaType = 'image';

        if (!$errors) {
            $tmpFile = $_FILES['ad_media']['tmp_name'];
            $mime = mime_content_type($tmpFile) ?: '';

            if (str_starts_with($mime, 'video/')) {
                $mediaType = 'video';
                // Process video into vertical 9:16 reel
                $res = MediaProcessor::processVideoToReel($tmpFile, UPLOADS_REELS_PATH, UPLOADS_THUMBS_PATH);
                $mediaPath = 'reels/' . $res['file'];
                if (!empty($res['thumbnail'])) {
                    $thumbnailPath = 'thumbs/' . $res['thumbnail'];
                }
            } elseif (str_starts_with($mime, 'image/')) {
                $mediaType = 'image';
                // Crop image to 9:16 vertical ratio WebP
                $croppedFile = AdManager::processAdImage($tmpFile, UPLOADS_PATH . '/ads');
                if ($croppedFile) {
                    $mediaPath = 'ads/' . $croppedFile;
                } else {
                    $errors[] = 'Failed to process ad image into 9:16 vertical format.';
                }
            } else {
                $errors[] = 'Unsupported file type. Please upload a valid image (JPG, PNG, WebP) or video (MP4, MOV).';
            }
        }

        // Handle payment method specific checks
        $receiptPath = null;
        if (!$errors && $paymentMethod === 'bank_transfer') {
            if (!empty($_FILES['receipt']['tmp_name']) && ($_FILES['receipt']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                try {
                    $receiptFile = storeFormImageUpload($_FILES['receipt']);
                    if ($receiptFile) {
                        $receiptPath = $receiptFile;
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Bank receipt upload failed: ' . $e->getMessage();
                }
            }
            if (!$receiptPath) {
                $errors[] = 'Please upload your bank transfer payment receipt.';
            }
        }

        if (!$errors) {
            $publisher = AdManager::findOrCreatePublisher($publisherName, $publisherEmail);
            $durationDays = match($durationType) {
                '7_days' => 7,
                '14_days' => 14,
                '30_days' => 30,
                '90_days' => 90,
                'custom' => $customDays,
                default => 7,
            };

            $payRef = 'AD_' . bin2hex(random_bytes(8));
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare("INSERT INTO advertisements
                (publisher_id, title, media_type, media_path, original_media_path, thumbnail_path, target_url, cta_label, duration_type, duration_days, duration_hours, amount, payment_method, payment_status, payment_ref, receipt_path, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, 'pending')");
            $stmt->execute([
                $publisher['id'],
                $title,
                $mediaType,
                $mediaPath,
                $originalMediaPath,
                $thumbnailPath,
                $targetUrl,
                $ctaLabel,
                $durationType,
                $durationDays,
                $customHours,
                $amount,
                $paymentMethod,
                $payRef,
                $receiptPath,
            ]);
            $adId = (int) $pdo->lastInsertId();

            $ad = [
                'id' => $adId,
                'title' => $title,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'payment_status' => 'pending',
            ];
            AdManager::notifySuperAdminNewAd($ad, $publisher);

            if ($paymentMethod === 'payhub') {
                $payhubConfig = [
                    'ad_id' => $adId,
                    'reference' => $payRef,
                    'amount' => $amount,
                    'email' => $publisherEmail,
                    'public_key' => $settings['payhub_public_key'] ?? '',
                ];
            } else {
                $successMessage = 'Your advertisement placement order has been submitted! Our team will review your bank transfer receipt and activate your ad shortly.';
            }
        }
    }
}
?>

<div class="container section" style="max-width:840px; margin-top:20px;">
  <div style="text-align:center; margin-bottom:30px;">
    <span class="eyebrow" style="color:var(--gold-soft); text-transform:uppercase; font-weight:700;">Promote Your Brand</span>
    <h1 style="font-size:32px; margin:8px 0 12px;">Place an Advertisement</h1>
    <p style="color:var(--ink-dim); max-width:600px; margin:0 auto; font-size:15px;">
      Display your image or video ads automatically in <strong>9:16 exact vertical Reels format</strong> on both our Website feed and Mobile App. Select your duration and start reaching viewers instantly!
    </p>
  </div>

  <?php if ($successMessage): ?>
    <div class="card" style="text-align:center; padding:40px;">
      <div style="font-size:48px; margin-bottom:12px;">🎉</div>
      <h2 style="color:var(--gold-soft);">Order Submitted Successfully!</h2>
      <p style="color:var(--ink-dim); margin-bottom:20px; font-size:16px;"><?= e($successMessage) ?></p>
      <p style="color:var(--ink-faint); font-size:14px;">Once approved, an access link will be sent to your email to log into your personal <strong>Ad Manager Account</strong> where you can monitor impressions, clicks, CTR, and time remaining.</p>
      <div class="btn-row" style="justify-content:center; margin-top:24px;">
        <a class="btn btn-gold" href="/feed">↗ View Live Feed</a>
      </div>
    </div>
  <?php else: ?>

    <?php if ($errors): ?>
      <?php foreach ($errors as $err): ?>
        <div class="alert error" style="margin-bottom:12px;"><?= e($err) ?></div>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="card glass-card" style="padding:32px;">
      <form method="post" action="/advertise" enctype="multipart/form-data" id="adForm">
        <?= Csrf::field() ?>

        <h3 style="margin-bottom:16px; color:var(--gold-soft); font-size:18px;">1. Advert Details</h3>

        <div style="margin-bottom:16px;">
          <label for="title" style="display:block; margin-bottom:6px; font-weight:600;">Advert Title / Headline</label>
          <input type="text" id="title" name="title" placeholder="e.g. Special Youth Conference 2026" value="<?= old('title') ?>" required style="width:100%; box-sizing:border-box;">
        </div>

        <div style="margin-bottom:16px;">
          <label for="target_url" style="display:block; margin-bottom:6px; font-weight:600;">Target Website URL (Where clicks lead)</label>
          <input type="url" id="target_url" name="target_url" placeholder="https://yourwebsite.com/landing-page" value="<?= old('target_url') ?>" required style="width:100%; box-sizing:border-box;">
        </div>

        <div style="margin-bottom:16px;">
          <label for="cta_label" style="display:block; margin-bottom:6px; font-weight:600;">Call To Action Button Label</label>
          <select id="cta_label" name="cta_label" style="width:100%; box-sizing:border-box;">
            <option value="Learn More">Learn More</option>
            <option value="Register Now">Register Now</option>
            <option value="Shop Now">Shop Now</option>
            <option value="Get Started">Get Started</option>
            <option value="Watch Video">Watch Video</option>
            <option value="Contact Us">Contact Us</option>
          </select>
        </div>

        <div style="margin-bottom:16px;">
          <label for="ad_media" style="display:block; margin-bottom:6px; font-weight:600;">Upload Ad Image or Video (Auto-converted to 9:16 Vertical Ratio)</label>
          <input type="file" id="ad_media" name="ad_media" accept="image/*,video/*" required style="width:100%; box-sizing:border-box;">
          <small style="color:var(--ink-faint); display:block; margin-top:4px;">
            Note: Images & videos are auto-cropped to 9:16 vertical ratio (like IG/FB Reels). Maximum file size: 50MB.
          </small>
        </div>

        <h3 style="margin-top:28px; margin-bottom:16px; color:var(--gold-soft); font-size:18px;">2. Select Duration</h3>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:16px;">
          <label style="border:1px solid var(--border); padding:14px; border-radius:8px; cursor:pointer; text-align:center; background:rgba(255,255,255,0.03);">
            <input type="radio" name="duration_type" value="7_days" checked onclick="toggleCustomDuration(false)">
            <div style="font-weight:700; margin-top:4px;">7 Days</div>
            <div style="color:var(--gold-soft); font-weight:800; font-size:16px;">₦<?= number_format((float) $settings['price_7_days'], 2) ?></div>
          </label>

          <label style="border:1px solid var(--border); padding:14px; border-radius:8px; cursor:pointer; text-align:center; background:rgba(255,255,255,0.03);">
            <input type="radio" name="duration_type" value="14_days" onclick="toggleCustomDuration(false)">
            <div style="font-weight:700; margin-top:4px;">14 Days</div>
            <div style="color:var(--gold-soft); font-weight:800; font-size:16px;">₦<?= number_format((float) $settings['price_14_days'], 2) ?></div>
          </label>

          <label style="border:1px solid var(--border); padding:14px; border-radius:8px; cursor:pointer; text-align:center; background:rgba(255,255,255,0.03);">
            <input type="radio" name="duration_type" value="30_days" onclick="toggleCustomDuration(false)">
            <div style="font-weight:700; margin-top:4px;">30 Days</div>
            <div style="color:var(--gold-soft); font-weight:800; font-size:16px;">₦<?= number_format((float) $settings['price_30_days'], 2) ?></div>
          </label>

          <label style="border:1px solid var(--border); padding:14px; border-radius:8px; cursor:pointer; text-align:center; background:rgba(255,255,255,0.03);">
            <input type="radio" name="duration_type" value="90_days" onclick="toggleCustomDuration(false)">
            <div style="font-weight:700; margin-top:4px;">90 Days</div>
            <div style="color:var(--gold-soft); font-weight:800; font-size:16px;">₦<?= number_format((float) $settings['price_90_days'], 2) ?></div>
          </label>

          <label style="border:1px solid var(--border); padding:14px; border-radius:8px; cursor:pointer; text-align:center; background:rgba(255,255,255,0.03);">
            <input type="radio" name="duration_type" value="custom" onclick="toggleCustomDuration(true)">
            <div style="font-weight:700; margin-top:4px;">Custom</div>
            <div style="color:var(--gold-soft); font-size:12px;">₦<?= number_format((float) $settings['price_per_custom_day']) ?>/day</div>
          </label>
        </div>

        <div id="customDurationBox" style="display:none; background:rgba(0,0,0,0.2); padding:16px; border-radius:8px; margin-bottom:16px;">
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div>
              <label for="custom_days">Days (₦<?= number_format((float) $settings['price_per_custom_day']) ?>/day)</label>
              <input type="number" min="0" id="custom_days" name="custom_days" value="0">
            </div>
            <div>
              <label for="custom_hours">Hours (₦<?= number_format((float) $settings['price_per_custom_hour']) ?>/hr)</label>
              <input type="number" min="0" id="custom_hours" name="custom_hours" value="0">
            </div>
          </div>
        </div>

        <h3 style="margin-top:28px; margin-bottom:16px; color:var(--gold-soft); font-size:18px;">3. Advertiser Contact Info</h3>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div>
            <label for="publisher_name">Your Name / Organization</label>
            <input type="text" id="publisher_name" name="publisher_name" placeholder="John Doe" value="<?= old('publisher_name') ?>" required>
          </div>
          <div>
            <label for="publisher_email">Your Email Address (Access link sent here)</label>
            <input type="email" id="publisher_email" name="publisher_email" placeholder="john@example.com" value="<?= old('publisher_email') ?>" required>
          </div>
        </div>

        <h3 style="margin-top:28px; margin-bottom:16px; color:var(--gold-soft); font-size:18px;">4. Select Payment Method</h3>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px;">
          <label style="border:1px solid var(--border); padding:16px; border-radius:8px; cursor:pointer; background:rgba(255,255,255,0.03);">
            <input type="radio" name="payment_method" value="payhub" checked onclick="togglePaymentBox('payhub')">
            <strong style="margin-left:6px;">Online Card / Transfer (Payhub)</strong>
            <p style="color:var(--ink-faint); font-size:12px; margin:4px 0 0 20px;">Instant checkout via Payhub gateway.</p>
          </label>

          <label style="border:1px solid var(--border); padding:16px; border-radius:8px; cursor:pointer; background:rgba(255,255,255,0.03);">
            <input type="radio" name="payment_method" value="bank_transfer" onclick="togglePaymentBox('bank_transfer')">
            <strong style="margin-left:6px;">Manual Bank Transfer</strong>
            <p style="color:var(--ink-faint); font-size:12px; margin:4px 0 0 20px;">Transfer to church account & upload receipt.</p>
          </label>
        </div>

        <div id="bankDetailsBox" style="display:none; background:rgba(232,185,95,0.08); border:1px solid var(--gold-soft); padding:20px; border-radius:8px; margin-bottom:20px;">
          <h4 style="margin:0 0 10px; color:var(--gold-soft);">Church Bank Transfer Details</h4>
          <p style="margin:4px 0;"><strong>Bank Name:</strong> <?= e($settings['bank_name'] ?: 'Not Specified') ?></p>
          <p style="margin:4px 0;"><strong>Account Number:</strong> <span style="font-size:18px; font-weight:800; letter-spacing:1px; color:var(--gold-soft);"><?= e($settings['bank_account_number'] ?: '0000000000') ?></span></p>
          <p style="margin:4px 0;"><strong>Account Name:</strong> <?= e($settings['bank_account_name'] ?: 'Church Media') ?></p>

          <div style="margin-top:16px;">
            <label for="receipt">Upload Payment Receipt Image/PDF</label>
            <input type="file" id="receipt" name="receipt" accept="image/*,application/pdf">
          </div>
        </div>

        <div class="btn-row" style="margin-top:28px;">
          <button type="submit" class="btn btn-gold" style="width:100%; padding:14px; font-size:16px; font-weight:700;">Place Advert Order</button>
        </div>
      </form>
    </div>

  <?php endif; ?>
</div>

<script>
function toggleCustomDuration(show) {
  document.getElementById('customDurationBox').style.display = show ? 'block' : 'none';
}
function togglePaymentBox(method) {
  document.getElementById('bankDetailsBox').style.display = method === 'bank_transfer' ? 'block' : 'none';
}
</script>

<?php if ($payhubConfig && !empty($payhubConfig['public_key'])): ?>
<script src="https://merchant.payhub.com.ng/inline.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
  var handler = PayhubPop.setup({
    key: <?= json_encode($payhubConfig['public_key']) ?>,
    email: <?= json_encode($payhubConfig['email']) ?>,
    amount: <?= (float) $payhubConfig['amount'] * 100 ?>,
    ref: <?= json_encode($payhubConfig['reference']) ?>,
    onClose: function() {
      alert('Payment window closed. Your order is pending admin verification.');
      window.location.href = '/feed';
    },
    callback: function(response) {
      alert('Payment complete! Reference: ' + response.reference + '. Your advertisement is submitted for admin review.');
      window.location.href = '/feed';
    }
  });
  handler.openIframe();
});
</script>
<?php endif; ?>
