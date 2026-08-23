<?php
declare(strict_types=1);

/**
 * Explicit public-site routes (pretty slugs). /admin/* and /api/* need no
 * entries here — Router falls back to a flat-file dispatch for those.
 */

/** @var Router $router */

$router->get('/', function () {
    render('home');
});

$router->get('/feed', function () {
    render('feed');
});

$router->get('/media', function () {
    render('media');
});

$router->get('/unit/{slug}', function (array $params) {
    render('unit', ['slug' => $params['slug']]);
});

$router->get('/units', function () {
    render('units');
});

$router->get('/events', function () {
    render('events');
});

$router->get('/events/{slug}', function (array $params) {
    render('event-detail', ['slug' => $params['slug']]);
});

$router->get('/sermons', function () {
    render('sermons');
});

$router->get('/sermons/{slug}', function (array $params) {
    render('sermon-detail', ['slug' => $params['slug']]);
});

$router->get('/about', function () {
    render('page', ['slug' => 'about']);
});

$router->get('/privacy-policy', function () {
    render('page', ['slug' => 'privacy-policy']);
});

$router->get('/page/{slug}', function (array $params) {
    render('page', ['slug' => $params['slug']]);
});

$router->get('/contact', function () {
    render('contact');
});

$router->get('/give', function () {
    render('give');
});

$router->get('/live', function () {
    render('live');
});

$router->get('/prayer', function () {
    render('prayer');
});

$router->get('/bible', function () {
    render('bible', [
        'metaTitle' => 'Holy Bible',
        'metaDescription' => 'Read the Holy Bible in your preferred version and language — KJV, NIV, NLT, NKJV with multi-language support.',
    ]);
});

$router->get('/forms/{slug}', function (array $params) {
    render('form', ['slug' => $params['slug']]);
});

$router->post('/forms/{slug}', function (array $params) {
    $pdo = Database::getInstance()->getConnection();
    $slug = $params['slug'];

    $stmt = $pdo->prepare('SELECT * FROM forms WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $form = $stmt->fetch();

    if (!$form) {
        http_response_code(404);
        render('404', [], true);
        return;
    }
    if (!formsAccepting($form)) {
        redirect('/forms/' . urlencode($slug));
    }

    // Honeypot: bots fill every field, humans never see this one.
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        flash('form_sent', '1');
        redirect('/forms/' . $slug . '?sent=1');
    }

    if (!RateLimiter::attempt('form_submit', $slug, 10, 300)) {
        keepFormOld($_POST);
        flash('form_error', 'Too many attempts from your browser — please wait a few minutes and try again.');
        redirect('/forms/' . $slug);
    }

    $stmt = $pdo->prepare('SELECT * FROM form_fields WHERE form_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$form['id']]);
    $fields = $stmt->fetchAll();

    $uploadedFiles = normalizeUploadedFiles($_FILES);
    $storedImages = [];
    $data = [];
    $errors = [];
    foreach ($fields as $field) {
        $key = 'field_' . $field['id'];
        $raw = $_POST[$key] ?? null;

        if (is_array($raw)) {
            $value = array_values(array_filter(array_map('trim', $raw), fn ($v) => $v !== ''));
        } else {
            $value = trim((string) $raw);
        }

        // Image uploads are handled from $_FILES, not text values.
        if ($field['field_type'] === 'image') {
            $value = [];
            foreach ($uploadedFiles[$key] ?? [] as $up) {
                if (empty($up['tmp_name']) || ($up['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if (($up['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                    $errors[] = 'The image "' . $up['name'] . '" failed to upload — please try again.';
                    continue;
                }
                if ((int) ($up['size'] ?? 0) > 8 * 1024 * 1024) {
                    $errors[] = 'Image "' . $up['name'] . '" is too large — max 8MB per file.';
                    continue;
                }
                $stored = null;
                try {
                    $stored = storeFormImageUpload($up);
                } catch (RuntimeException $e) {
                    $errors[] = $e->getMessage();
                }
                if (!$stored) {
                    $errors[] = '"' . $field['label'] . '" has an unsupported file ("' . $up['name'] . '"). Accepted: JPG, PNG, GIF, WebP, BMP, AVIF.';
                    continue;
                }
                $storedImages[] = $stored;
                $value[] = $stored;
            }
            if ($field['required'] && $value === []) {
                $errors[] = 'Please upload at least one image for "' . $field['label'] . '".';
            }
            $data[(string) $field['id']] = $value;
            continue;
        }

        if ($field['required'] && ($value === '' || (is_array($value) && $value === []))) {
            $errors[] = 'Please answer "' . $field['label'] . '".';
            continue;
        }

        switch ($field['field_type']) {
            case 'email':
                if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = '"' . $field['label'] . '" needs a valid email address.';
                }
                break;
            case 'url':
                if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[] = '"' . $field['label'] . '" needs a valid URL.';
                }
                break;
            case 'number':
                if ($value !== '' && !is_numeric($value)) {
                    $errors[] = '"' . $field['label'] . '" needs a number.';
                }
                break;
            case 'phone':
                if ($value !== '' && !preg_match('/^[0-9+\-(). ]{6,30}$/', (string) $value)) {
                    $errors[] = '"' . $field['label'] . '" needs a valid phone number.';
                }
                break;
            case 'select':
            case 'radio':
            case 'checkbox':
                $selected = is_array($value) ? $value : ($value === '' ? [] : [$value]);
                $allowed = formFieldOptions($field);
                foreach ($selected as $v) {
                    if (!in_array($v, $allowed, true)) {
                        $errors[] = '"' . $field['label'] . '" contains an invalid option.';
                        break;
                    }
                }
                $value = $selected;
                break;
        }

        $data[(string) $field['id']] = $value;
    }

    if ($errors) {
        foreach ($storedImages as $path) {
            @unlink(UPLOADS_PATH . '/' . $path);
        }
        keepFormOld($_POST);
        flash('form_error', implode(' ', $errors));
        redirect('/forms/' . $slug);
    }

    $stmt = $pdo->prepare('INSERT INTO form_submissions (form_id, data, ip_address) VALUES (?, ?, ?)');
    $stmt->execute([$form['id'], json_encode($data, JSON_UNESCAPED_SLASHES), clientIp()]);
    clearFormOld();
    flash('form_sent', '1');
    redirect('/forms/' . $slug . '?sent=1');
});

$router->get('/search', function () {
    render('search');
});

$router->get('/sitemap.xml', function () {
    require VIEWS_PATH . '/sitemap.php';
});

$router->get('/favicon.ico', function () {
    $path = setting('favicon_path');
    if ($path && is_file(UPLOADS_PATH . '/' . $path)) {
        header('Content-Type: image/webp');
        header('Cache-Control: public, max-age=86400');
        readfile(UPLOADS_PATH . '/' . $path);
        exit;
    }
    MediaProcessor::renderDynamicFavicon(setting('site_title', 'C'));
});
