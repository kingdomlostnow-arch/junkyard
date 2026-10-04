<?php
declare(strict_types=1);

/** Web search + scraping through the Firecrawl API. */
final class Firecrawl
{
    public function __construct(
        private Http $http,
        private string $apiKey,
        private string $baseUrl = 'https://api.firecrawl.dev',
    ) {
        if ($apiKey === '') {
            throw new RuntimeException('Firecrawl API key is not set (config.php or FIRECRAWL_API_KEY).');
        }
    }

    /**
     * Searches the web and returns the scraped content of the top results.
     *
     * @return list<array{url:string,title:string,markdown:string,images:list<string>}>
     */
    public function search(string $query, int $limit = 4): array
    {
        $data = $this->post('/v2/search', [
            'query'         => $query,
            'limit'         => $limit,
            'sources'       => ['web'],
            'scrapeOptions' => ['formats' => ['markdown'], 'onlyMainContent' => true],
        ]);

        $items = $data['data']['web'] ?? (array_is_list($data['data'] ?? null) ? $data['data'] : []);
        $results = [];
        foreach ($items as $item) {
            $markdown = trim((string) ($item['markdown'] ?? ''));
            if ($markdown === '') {
                continue;
            }
            $meta = $item['metadata'] ?? [];
            $results[] = [
                'url'      => (string) ($item['url'] ?? $meta['sourceURL'] ?? ''),
                'title'    => (string) ($item['title'] ?? $meta['title'] ?? ''),
                'markdown' => $markdown,
                'images'   => $this->imagesFrom($meta, $markdown),
            ];
        }
        return $results;
    }

    /** @return list<array{image:string,page:string}> */
    public function searchImages(string $query, int $limit = 6): array
    {
        $data = $this->post('/v2/search', [
            'query'   => $query,
            'limit'   => $limit,
            'sources' => ['images'],
        ]);
        $images = [];
        foreach ($data['data']['images'] ?? [] as $img) {
            if (!empty($img['imageUrl'])) {
                $images[] = ['image' => (string) $img['imageUrl'], 'page' => (string) ($img['url'] ?? '')];
            }
        }
        return $images;
    }

    private function post(string $path, array $payload): array
    {
        $data = $this->http->postJson(
            rtrim($this->baseUrl, '/') . $path,
            $payload,
            ['Authorization: Bearer ' . $this->apiKey],
            150
        );
        if (($data['success'] ?? true) === false) {
            throw new RuntimeException('Firecrawl error: ' . ($data['error'] ?? 'unknown'));
        }
        return $data;
    }

    /** og:image first (usually the page's lead photo), then images inside the article. */
    private function imagesFrom(array $meta, string $markdown): array
    {
        $images = [];
        foreach (['ogImage', 'og:image', 'twitter:image'] as $key) {
            foreach ((array) ($meta[$key] ?? []) as $url) {
                $images[] = (string) $url;
            }
        }
        if (preg_match_all('/!\[[^\]]*\]\((https?:\/\/[^)\s]+)/', $markdown, $m)) {
            array_push($images, ...$m[1]);
        }
        $images = array_filter($images, fn ($u) => str_starts_with($u, 'http') && !preg_match('/\.svg($|\?)|logo|icon|sprite|avatar/i', $u));
        return array_values(array_unique($images));
    }
}
