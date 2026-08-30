<?php
declare(strict_types=1);

/**
 * Minimal cPanel UAPI client used to auto-create church admin email accounts
 * (and optional forwarders) when a registration is approved.
 *
 * Credentials come from Settings → Corporate Email (cPanel): the cPanel host
 * (port 2083), the cPanel username, and an API token (cPanel → Security →
 * Manage API Tokens). The token is the only credential needed for the API and
 * is far safer than storing the cPanel password.
 */
class CpanelApi
{
    private string $host;
    private string $user;
    private string $token;
    private int $port;

    /** @param array{host?:string,user?:string,token?:string,port?:int} $cfg */
    public function __construct(array $cfg)
    {
        // Tolerate common input mistakes: "https://host", "host:2083", "host/".
        $raw = trim((string) ($cfg['host'] ?? ''));
        $raw = preg_replace('#^https?://#i', '', $raw) ?? '';
        $raw = rtrim($raw, '/');
        if (preg_match('#^(.*):(\d+)$#', $raw, $m)) {
            $this->host = $m[1];
            $this->port = (int) $m[2];
        } else {
            $this->host = $raw;
            $this->port = max(1, (int) ($cfg['port'] ?? 2083));
        }
        $this->user = (string) ($cfg['user'] ?? '');
        $this->token = (string) ($cfg['token'] ?? '');
    }

    public function configured(): bool
    {
        return $this->host !== '' && $this->user !== '' && $this->token !== '';
    }

    /** Lightweight call used by the Settings "Test cPanel connection" button. */
    public function testConnection(): array
    {
        return $this->request('Email/list_pops', []);
    }

    /** Create a POP/IMAP mailbox. Returns ['ok'=>bool,'error'=>?string,'exists'=>bool]. */
    public function createEmail(string $domain, string $localPart, string $password, int $quotaMB): array
    {
        $result = $this->request('Email/add_pop', [
            'email' => $localPart . '@' . $domain,
            'password' => $password,
            'quota' => max(0, $quotaMB),
            'domain' => $domain,
        ]);
        if (!$result['ok'] && stripos((string) $result['error'], 'exist') !== false) {
            // Already created earlier — treat as success (idempotent).
            return ['ok' => true, 'error' => null, 'exists' => true];
        }
        $result['exists'] = false;
        return $result;
    }

    /** Create a forwarder from a corporate mailbox to an external backup inbox. */
    public function createForwarder(string $domain, string $localPart, string $forwardTo): array
    {
        return $this->request('Email/add_forwarder', [
            'domain' => $domain,
            'forwarder' => $localPart,
            'fwdemail' => $forwardTo,
            'fwdopt' => 'fwd', // forward only, no local copy
        ]);
    }

    /** cPanel UAPI request; returns ['ok'=>bool,'error'=>?string]. */
    private function request(string $module, array $params): array
    {
        if (!$this->configured()) {
            return ['ok' => false, 'error' => 'cPanel API is not configured.'];
        }
        $url = 'https://' . $this->host . ':' . $this->port . '/execute/' . $module . '?' . http_build_query($params);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: Basic ' . base64_encode($this->user . ':' . $this->token) . "\r\n" .
                            "Accept: application/json\r\n",
                'timeout' => 30,
                'ignore_errors' => true,
            ],
            'ssl' => [
                // Shared hosts often serve the 2083 port with the same valid cert,
                // but disabling verification here avoids failures on self-signed
                // setups. The credential used is a scoped API token, not a password.
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);

        // Capture the HTTP status from the wrapper's response headers so we can
        // give a real diagnosis (401 = bad credentials, 404 = wrong host, etc.).
        $status = 0;
        $statusText = '';
        if (!empty($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('#^HTTP/\S+\s+(\d+)\s*(.*)$#', (string) $line, $m)) {
                    $status = (int) $m[1];
                    $statusText = trim((string) $m[2]);
                }
            }
        }

        if ($body === false) {
            return ['ok' => false, 'error' => 'Could not reach the cPanel API (' . $this->host . ':' . $this->port . ').' . ($statusText ? ' HTTP ' . $status . ' ' . $statusText . '.' : ' No response.')];
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            $excerpt = trim((string) preg_replace('/\s+/', ' ', (string) $body));
            $excerpt = mb_substr($excerpt, 0, 220);
            $hint = '';
            if ($status === 401 || $status === 403) {
                $hint = ' Authentication was rejected — double-check the cPanel username and API token.';
            } elseif ($status === 404) {
                $hint = ' Not found — this is probably not the cPanel server. Use the cPanel hostname (e.g. cpanel.yourhost.com), not the website domain.';
            } elseif (stripos($excerpt, '<html') !== false || stripos($excerpt, '<!doctype') !== false || stripos($excerpt, 'login') !== false) {
                $hint = ' The server returned a web page instead of the API — confirm the cPanel host/port (2083 for cPanel) and that API access is allowed.';
            }
            return ['ok' => false, 'error' => 'Unexpected cPanel API response (HTTP ' . $status . ($statusText ? ' ' . $statusText : '') . ').' . $hint . ' Body: ' . ($excerpt !== '' ? $excerpt : '(empty)')];
        }
        $errors = $data['errors'] ?? [];
        if (!empty($errors)) {
            return ['ok' => false, 'error' => implode(' ', array_map('strval', $errors))];
        }
        $status = (int) ($data['status'] ?? 0);
        return ['ok' => $status === 1, 'error' => $status === 1 ? null : 'cPanel API returned status 0 for ' . $module . '.'];
    }
}
