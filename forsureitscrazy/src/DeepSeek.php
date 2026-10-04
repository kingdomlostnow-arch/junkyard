<?php
declare(strict_types=1);

final class DeepSeek
{
    /** Visual themes and layouts the AI may choose per story (see public/assets/style.css). */
    public const THEMES = ['neon', 'blood', 'ocean', 'toxic', 'royal', 'sand'];
    public const LAYOUTS = ['classic', 'magazine', 'timeline'];

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
     * Picks story ideas the site will research on the web.
     *
     * @param list<string> $avoid titles already published recently
     * @return list<array{query:string,brief:string}>
     */
    public function proposeTopics(int $count, string $mode, array $avoid, string $today): array
    {
        $modeText = match ($mode) {
            'history' => 'historical events (any era)',
            'modern'  => 'events from the last 30 years, including recent news',
            default   => 'a mix: some historical events from different eras and some from recent years',
        };
        $avoidText = $avoid ? "\nDo NOT repeat any of these already-published stories:\n- " . implode("\n- ", $avoid) : '';

        $result = $this->chatJson(
            'You are the research editor of "forsureitscrazy", a site of bizarre-but-true stories. Reply with a JSON object.',
            "Today is {$today}. Suggest {$count} different, surprising, almost unbelievable but TRUE and well-documented stories. "
            . "Cover {$modeText}. Vary the countries and themes (science, crime, nature, war, sports, space, medicine, inventions, animals, culture...).{$avoidText}\n\n"
            . 'Return JSON: {"topics":[{"query":"precise English web search query that finds sources about this exact story","brief":"one-line description"}]}',
            1.2,
            1500
        );

        $topics = [];
        foreach ($result['topics'] ?? [] as $t) {
            if (is_string($t['query'] ?? null) && trim($t['query']) !== '') {
                $topics[] = ['query' => trim($t['query']), 'brief' => trim((string) ($t['brief'] ?? ''))];
            }
        }
        if (!$topics) {
            throw new RuntimeException('DeepSeek returned no topics.');
        }
        return $topics;
    }

    /**
     * Writes a story using only the given sources, and picks how it is displayed.
     *
     * @param list<array{title:string,url:string,text:string}> $sources
     * @return array{supported:bool,title:string,summary:string,sections:list<array{heading:string,body:string}>,
     *               fun_fact:string,category:string,emoji:string,year:?int,theme:string,layout:string}
     */
    public function writeArticle(string $brief, array $sources): array
    {
        $sourceText = '';
        foreach ($sources as $i => $s) {
            $sourceText .= "\n\n### SOURCE " . ($i + 1) . ": {$s['title']} ({$s['url']})\n{$s['text']}";
        }

        $a = $this->chatJson(
            "You are the editor of \"forsureitscrazy\", a website that tells surprising true stories. Write in {$this->language}. "
            . 'Be vivid and gripping but strictly factual: use ONLY facts found in the sources. Never invent dates, numbers, names or quotes. '
            . 'Reply with a single JSON object.',
            "Story: {$brief}\nSOURCES:{$sourceText}\n\n"
            . "Return JSON with exactly these keys:\n"
            . "- \"supported\": true if the sources clearly describe this story, false if they are off-topic or too thin\n"
            . "- \"title\": catchy headline (max 12 words)\n"
            . "- \"summary\": 2-sentence teaser\n"
            . "- \"sections\": 4-6 objects {\"heading\", \"body\"} telling the full story in order (background, what happened, the people, aftermath, why it matters); body may have several paragraphs separated by blank lines\n"
            . "- \"fun_fact\": one surprising fact from the sources\n"
            . "- \"category\": one-word category in {$this->language}\n"
            . "- \"emoji\": one emoji that fits the story\n"
            . "- \"year\": year the main event happened (integer) or null\n"
            . '- "theme": visual mood, one of ' . implode(', ', self::THEMES) . " (neon=weird/fun, blood=crime/war/disaster, ocean=sea/space/science, toxic=nature/medicine/animals, royal=kings/history/art, sand=ancient/exploration)\n"
            . '- "layout": one of ' . implode(', ', self::LAYOUTS) . ' (timeline when the story unfolds step by step over time, magazine for dramatic visual stories, classic otherwise)',
            0.7,
            4000
        );

        $sections = [];
        foreach ($a['sections'] ?? [] as $s) {
            if (is_string($s['heading'] ?? null) && is_string($s['body'] ?? null) && trim($s['body']) !== '') {
                $sections[] = ['heading' => trim($s['heading']), 'body' => trim($s['body'])];
            }
        }
        foreach (['title', 'summary'] as $key) {
            if (!is_string($a[$key] ?? null) || trim($a[$key]) === '') {
                throw new RuntimeException("DeepSeek response missing \"$key\".");
            }
        }
        if (count($sections) < 2) {
            throw new RuntimeException('DeepSeek response has too few sections.');
        }

        return [
            'supported' => ($a['supported'] ?? true) !== false,
            'title'     => trim($a['title']),
            'summary'   => trim($a['summary']),
            'sections'  => $sections,
            'fun_fact'  => trim((string) ($a['fun_fact'] ?? '')),
            'category'  => mb_substr(trim((string) ($a['category'] ?? '')), 0, 50),
            'emoji'     => mb_substr(trim((string) ($a['emoji'] ?? '')), 0, 8),
            'year'      => is_numeric($a['year'] ?? null) ? (int) $a['year'] : null,
            'theme'     => in_array($a['theme'] ?? '', self::THEMES, true) ? $a['theme'] : 'neon',
            'layout'    => in_array($a['layout'] ?? '', self::LAYOUTS, true) ? $a['layout'] : 'classic',
        ];
    }

    private function chatJson(string $system, string $user, float $temperature, int $maxTokens): array
    {
        $response = $this->http->postJson(
            rtrim($this->baseUrl, '/') . '/chat/completions',
            [
                'model'           => $this->model,
                'messages'        => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature'     => $temperature,
                'max_tokens'      => $maxTokens,
            ],
            ['Authorization: Bearer ' . $this->apiKey]
        );

        $data = json_decode($response['choices'][0]['message']['content'] ?? '', true);
        if (!is_array($data)) {
            throw new RuntimeException('DeepSeek returned invalid JSON.');
        }
        return $data;
    }
}
