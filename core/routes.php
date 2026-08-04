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
    render('about');
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
