<?php
declare(strict_types=1);

/** Fetches real events, article text and images from Wikipedia. */
final class Wikipedia
{
    public function __construct(private Http $http, private string $lang) {}

    /**
     * Events that happened on this month/day in history, each with a main
     * article that has an image.
     *
     * @return list<array{year:?int,text:string,page:array}>
     */
    public function onThisDay(DateTimeInterface $date): array
    {
        $url = sprintf(
            'https://%s.wikipedia.org/api/rest_v1/feed/onthisday/all/%s/%s',
            $this->lang, $date->format('m'), $date->format('d')
        );
        $data = $this->http->getJson($url);

        $events = [];
        foreach (['selected', 'events'] as $group) {
            foreach ($data[$group] ?? [] as $item) {
                $page = $this->pickPage($item['pages'] ?? []);
                if ($page === null || empty($item['text'])) {
                    continue;
                }
                $events[] = [
                    'year' => isset($item['year']) ? (int) $item['year'] : null,
                    'text' => (string) $item['text'],
                    'page' => $page,
                ];
            }
        }
        return $events;
    }

    /** Plain-text body of an article, trimmed to $maxChars. */
    public function articleText(string $title, int $maxChars = 8000): string
    {
        $url = sprintf('https://%s.wikipedia.org/w/api.php?', $this->lang) . http_build_query([
            'action'        => 'query',
            'prop'          => 'extracts',
            'explaintext'   => 1,
            'titles'        => $title,
            'format'        => 'json',
            'formatversion' => 2,
            'redirects'     => 1,
        ]);
        $data = $this->http->getJson($url);
        $text = (string) ($data['query']['pages'][0]['extract'] ?? '');
        return mb_substr($text, 0, $maxChars);
    }

    /** First linked page that has an image; that page is the event's main topic. */
    private function pickPage(array $pages): ?array
    {
        foreach ($pages as $p) {
            $image = $p['originalimage']['source'] ?? $p['thumbnail']['source'] ?? null;
            if ($image === null) {
                continue;
            }
            return [
                'title'   => (string) ($p['titles']['normalized'] ?? $p['title'] ?? ''),
                'key'     => (string) ($p['title'] ?? ''),
                'extract' => (string) ($p['extract'] ?? ''),
                'url'     => (string) ($p['content_urls']['desktop']['page'] ?? ''),
                // Prefer a ~1280px thumbnail over a potentially huge original.
                'image'   => $this->resize($p['thumbnail']['source'] ?? $image, 1280),
            ];
        }
        return null;
    }

    private function resize(string $thumbUrl, int $width): string
    {
        return preg_replace('#/(\d+)px-([^/]+)$#', "/{$width}px-$2", $thumbUrl) ?? $thumbUrl;
    }
}
