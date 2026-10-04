<?php
declare(strict_types=1);

final class DeepSeek
{
    public function __construct(
        private Http $http,
        private string $apiKey,
        private string $baseUrl,
        private string $model,
        private string $language,
    ) {
        if ($apiKey === '') {
            throw new RuntimeException('DeepSeek API key is not set (config.php or DEEPSEEK_API_KEY).');
        }
    }

    /**
     * Writes an article about a real event based only on the supplied source text.
     *
     * @return array{title:string,summary:string,content:string,fun_fact:string,category:string}
     */
    public function writeArticle(?int $year, string $eventText, string $articleTitle, string $sourceText): array
    {
        $system = "You are the editor of \"forsureitscrazy\", a website that tells surprising true stories from history. "
            . "Write in {$this->language}. Be vivid and engaging but strictly factual: use ONLY facts found in the provided source text. "
            . "Do not invent dates, numbers, names or quotes. Reply with a single JSON object.";

        $user = "Event (year " . ($year ?? 'unknown') . "): {$eventText}\n"
            . "Main Wikipedia article: {$articleTitle}\n\n"
            . "SOURCE TEXT:\n{$sourceText}\n\n"
            . "Return JSON with exactly these keys:\n"
            . "- \"title\": catchy headline (max 12 words)\n"
            . "- \"summary\": 2 sentences teaser\n"
            . "- \"content\": detailed article of 5-7 paragraphs separated by blank lines (background, what happened, people involved, consequences, why it still matters)\n"
            . "- \"fun_fact\": one surprising fact from the source\n"
            . "- \"category\": one word category (e.g. war, science, politics, disaster, culture, sports, space)";

        $response = $this->http->postJson(
            rtrim($this->baseUrl, '/') . '/chat/completions',
            [
                'model'           => $this->model,
                'messages'        => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature'     => 0.7,
                'max_tokens'      => 3000,
            ],
            ['Authorization: Bearer ' . $this->apiKey]
        );

        $json = $response['choices'][0]['message']['content'] ?? '';
        $article = json_decode($json, true);
        if (!is_array($article)) {
            throw new RuntimeException('DeepSeek returned invalid JSON.');
        }
        foreach (['title', 'summary', 'content'] as $key) {
            if (!is_string($article[$key] ?? null) || trim($article[$key]) === '') {
                throw new RuntimeException("DeepSeek response missing \"$key\".");
            }
        }
        return [
            'title'    => trim($article['title']),
            'summary'  => trim($article['summary']),
            'content'  => trim($article['content']),
            'fun_fact' => trim((string) ($article['fun_fact'] ?? '')),
            'category' => trim((string) ($article['category'] ?? '')),
        ];
    }
}
