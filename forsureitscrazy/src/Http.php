<?php
declare(strict_types=1);

class Http
{
    public function __construct(private string $userAgent) {}

    public function getJson(string $url): array
    {
        $body = $this->request('GET', $url);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException("Invalid JSON from $url");
        }
        return $data;
    }

    public function postJson(string $url, array $payload, array $headers = [], int $timeout = 180): array
    {
        $headers[] = 'Content-Type: application/json';
        $body = $this->request('POST', $url, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, $timeout);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException("Invalid JSON from $url");
        }
        return $data;
    }

    /** Returns [body, contentType]. */
    public function download(string $url, int $maxBytes): array
    {
        $contentType = '';
        $body = $this->request('GET', $url, null, [], 60, $contentType);
        if (strlen($body) > $maxBytes) {
            throw new RuntimeException("File too large: $url");
        }
        return [$body, $contentType];
    }

    private function request(string $method, string $url, ?string $body = null, array $headers = [], int $timeout = 30, ?string &$contentType = null): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_USERAGENT      => $this->userAgent,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            throw new RuntimeException("HTTP $method $url failed: $error");
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("HTTP $method $url returned $status: " . mb_substr($result, 0, 300));
        }
        return $result;
    }
}
