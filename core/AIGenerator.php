<?php
/**
 * SEAIT E-Learning — AI Quiz Generator (v3)
 * Groq LLaMA 3 / OpenAI compatible. Windows XAMPP friendly.
 */
class AIGenerator
{
    // ── Public entry point (single section) ──────────────────

    public static function generate(
        string $text,
        int    $count      = 10,
        string $type       = 'multiple_choice',
        string $difficulty = 'medium',
        string $bloomLevel = 'understand'
    ): array {
        $text = trim($text);
        if (empty($text)) return [];

        if (AI_ENABLED) {
            $result = self::callAPI($text, $count, $type, $difficulty, $bloomLevel);
            if (!empty($result)) return $result;
            error_log('[AIGenerator] API failed, using fallback generator.');
        }

        return self::fallback($text, $count, $type, $difficulty, $bloomLevel);
    }

    // ── NEW: Generate ALL sections in ONE API call ────────────
    // Much faster — 1 call instead of N calls

    public static function generateAll(string $text, array $sections, string $difficulty, string $bloomLevel): array
    {
        $text = trim($text);
        if (empty($text)) return [];

        if (AI_ENABLED) {
            $result = self::callAPIAll($text, $sections, $difficulty, $bloomLevel);
            if (!empty($result)) return $result;
            error_log('[AIGenerator] Multi-section API failed, using per-section fallback.');
        }

        // Fallback: generate each section locally (no API)
        $out = [];
        foreach ($sections as $sec) {
            $out[] = self::fallback($text, $sec['count'], $sec['type'], $difficulty, $bloomLevel);
        }
        return $out;
    }

    private static function callAPIAll(string $text, array $sections, string $difficulty, string $bloomLevel): array
    {
        $lessonText = self::trimText($text, 6000);

        // Build section instructions
        $sectionInstructions = '';
        foreach ($sections as $i => $sec) {
            $typeLabel = match ($sec['type']) {
                'multiple_choice' => 'Multiple Choice (4 options A,B,C,D — correct_answer is A/B/C/D)',
                'true_false'      => 'True/False (correct_answer is "True" or "False")',
                'identification'  => 'Fill-in-the-blank (no choices, correct_answer is the missing term)',
                'short_answer'    => 'Short Answer (no choices, correct_answer is a model answer)',
                default           => $sec['type'],
            };
            $num = $i + 1;
            $sectionInstructions .= "Section {$num} — \"{$sec['name']}\": {$sec['count']} {$typeLabel} questions.\n";
        }

        $totalSections = count($sections);

        $systemMsg = "You are an expert Philippine IT instructor creating exam questions.\n"
            . "Generate questions ONLY from the provided lesson text.\n"
            . "Return ONLY a valid JSON object with no markdown, no code fences, no extra text.";

        $userMsg = "LESSON TEXT:\n---\n{$lessonText}\n---\n\n"
            . "Generate {$totalSections} section(s) of quiz questions based ONLY on the lesson above.\n"
            . "Difficulty: {$difficulty} | Bloom's level: {$bloomLevel}\n\n"
            . "Sections to generate:\n{$sectionInstructions}\n"
            . "Return a JSON object with this exact structure:\n"
            . "{\n"
            . "  \"sections\": [\n"
            . "    {\n"
            . "      \"questions\": [\n"
            . "        {\"question_text\":\"...\",\"question_type\":\"...\",\"choices\":[...] or null,\"correct_answer\":\"...\",\"explanation\":\"...\",\"difficulty\":\"...\",\"bloom_level\":\"...\",\"topic\":\"...\"}\n"
            . "      ]\n"
            . "    }\n"
            . "  ]\n"
            . "}\n\n"
            . "The sections array must have exactly {$totalSections} element(s) in the same order as listed above.\n"
            . "Return ONLY the JSON object, nothing else.";

        $payload = [
            'model'       => OPENAI_MODEL,
            'temperature' => 0.2,
            'max_tokens'  => 8192,
            'messages'    => [
                ['role' => 'system', 'content' => $systemMsg],
                ['role' => 'user',   'content' => $userMsg],
            ],
        ];

        $apiUrl = defined('OPENAI_API_URL') ? OPENAI_API_URL : 'https://api.openai.com/v1/chat/completions';

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . OPENAI_API_KEY, 'Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $httpCode !== 200) {
            error_log("[AIGenerator] Multi-section HTTP $httpCode curlErr=$curlErr");
            return [];
        }

        $data    = json_decode($response, true);
        $content = trim($data['choices'][0]['message']['content'] ?? '');
        if (empty($content)) return [];

        // Strip fences
        $content = preg_replace('/^```(?:json)?\s*/im', '', $content);
        $content = preg_replace('/```\s*$/im', '', $content);
        $content = trim($content);

        $parsed = json_decode($content, true);

        // Accept both {sections:[...]} and [[...],[...]] formats
        if (isset($parsed['sections']) && is_array($parsed['sections'])) {
            $rawSections = $parsed['sections'];
        } elseif (is_array($parsed) && isset($parsed[0]['questions'])) {
            $rawSections = $parsed;
        } else {
            // Fallback: maybe the AI returned a flat array for a single section
            if (is_array($parsed) && !empty($parsed) && isset($parsed[0]['question_text'])) {
                $rawSections = [['questions' => $parsed]];
            } else {
                error_log('[AIGenerator] Unexpected multi-section response: ' . substr($content, 0, 300));
                return [];
            }
        }

        $result = [];
        foreach ($rawSections as $rawSec) {
            $qs = $rawSec['questions'] ?? $rawSec ?? [];
            $result[] = is_array($qs) ? self::normalize($qs) : [];
        }

        return $result;
    }

    // ── Groq / OpenAI API call ────────────────────────────────

    private static function callAPI(
        string $text, int $count, string $type, string $difficulty, string $bloomLevel
    ): array {

        $typeInstruction = match ($type) {
            'multiple_choice' => "Multiple Choice: exactly 4 options labeled A, B, C, D. correct_answer must be exactly one of: A, B, C, or D.",
            'true_false'      => "True/False: 2 choices [True, False]. correct_answer must be exactly \"True\" or \"False\".",
            'identification'  => "Fill-in-the-blank identification: NO choices array (null). correct_answer is the missing word/phrase.",
            'short_answer'    => "Short Answer: NO choices array (null). correct_answer is a 1-2 sentence model answer.",
            default           => $type,
        };

        $lessonText = self::trimText($text, 6000);

        $systemMsg = <<<SYSTEM
You are an expert Philippine IT instructor creating exam questions.
Your ONLY job is to create questions STRICTLY based on the lesson text provided.

CRITICAL RULES:
1. READ the entire lesson text carefully before writing any question.
2. Every question MUST be answerable ONLY from the lesson text — no outside knowledge.
3. Use specific facts, terms, definitions, processes, and examples FROM the lesson.
4. Questions must test understanding of the actual content, not generic IT knowledge.
5. Return ONLY a valid JSON array — NO markdown, NO code fences, NO explanation text.
6. Every question object must have: question_text, question_type, choices (array or null), correct_answer, explanation, difficulty, bloom_level, topic.
SYSTEM;

        $userMsg = <<<USER
Here is the lesson content. Read it carefully:

---LESSON START---
{$lessonText}
---LESSON END---

Based ONLY on the lesson above, generate exactly {$count} quiz questions.
Question type: {$typeInstruction}
Difficulty: {$difficulty}
Bloom's taxonomy level: {$bloomLevel}

Each question's explanation must cite the relevant part of the lesson text.
Return ONLY the raw JSON array, nothing else.
USER;

        $payload = [
            'model'       => OPENAI_MODEL,
            'temperature' => 0.2,
            'max_tokens'  => 4096,
            'messages'    => [
                ['role' => 'system', 'content' => $systemMsg],
                ['role' => 'user',   'content' => $userMsg],
            ],
        ];

        $apiUrl = defined('OPENAI_API_URL')
            ? OPENAI_API_URL
            : 'https://api.openai.com/v1/chat/completions';

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . OPENAI_API_KEY,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log("[AIGenerator] cURL: $curlErr");
            return [];
        }
        if ($httpCode !== 200) {
            error_log("[AIGenerator] HTTP $httpCode: " . substr($response, 0, 400));
            return [];
        }

        $data    = json_decode($response, true);
        $content = trim($data['choices'][0]['message']['content'] ?? '');

        if (empty($content)) {
            error_log('[AIGenerator] Empty API response content');
            return [];
        }

        // Strip markdown fences
        $content = preg_replace('/^```(?:json)?\s*/im', '', $content);
        $content = preg_replace('/```\s*$/im', '', $content);
        $content = trim($content);

        // Find the JSON array boundaries
        $start = strpos($content, '[');
        $end   = strrpos($content, ']');

        if ($start === false || $end === false || $end <= $start) {
            error_log('[AIGenerator] No JSON array found in: ' . substr($content, 0, 200));
            return [];
        }

        $json      = substr($content, $start, $end - $start + 1);
        $questions = json_decode($json, true);

        if (!is_array($questions) || empty($questions)) {
            error_log('[AIGenerator] JSON parse failed: ' . json_last_error_msg());
            return [];
        }

        $normalized = self::normalize($questions);

        // Pad with fallback if API returned fewer than requested
        if (count($normalized) < $count) {
            $needed = $count - count($normalized);
            $extra  = self::fallback($text, $needed, $type, $difficulty, $bloomLevel);
            $normalized = array_merge($normalized, $extra);
        }

        return array_slice($normalized, 0, $count);
    }

    // ── Smart fallback (content-aware) ────────────────────────

    private static function fallback(
        string $text, int $count, string $type, string $difficulty, string $bloomLevel
    ): array {
        // Clean and split into sentences
        $clean     = preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($text)));
        $sentences = preg_split('/(?<=[.?!])\s+/', $clean);
        $sentences = array_values(array_filter($sentences, fn($s) => strlen(trim($s)) >= 25));

        if (empty($sentences)) {
            $sentences = ["This lesson covers important concepts and principles of the subject matter."];
        }

        // Extract meaningful terms (nouns/keywords)
        $stopWords = array_flip(['the','a','an','is','are','was','were','be','been','being',
            'have','has','had','do','does','did','will','would','could','should','may',
            'might','can','this','that','these','those','and','but','or','nor','for',
            'yet','so','of','to','in','on','at','by','with','from','into','through',
            'also','which','when','where','who','what','how','its','their','our','your']);

        $terms = [];
        foreach ($sentences as $s) {
            foreach (preg_split('/\s+/', $s) as $w) {
                $w = trim($w, '.,;:()[]"\'!?');
                if (strlen($w) >= 4 && !isset($stopWords[strtolower($w)]) && ctype_alpha($w[0])) {
                    $terms[] = $w;
                }
            }
        }
        $terms = array_values(array_unique($terms));
        if (empty($terms)) $terms = ['concept','principle','method','system','process'];

        $total  = count($sentences);
        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $sentence = $sentences[$i % $total];
            $keyword  = $terms[$i % count($terms)];
            $short    = self::short($sentence);

            switch ($type) {

                case 'multiple_choice':
                    $correct   = ['A','B','C','D'][$i % 4];
                    $distract  = array_diff($terms, [$keyword]);
                    shuffle($distract);
                    $opts = [];
                    $d    = 0;
                    foreach (['A','B','C','D'] as $lbl) {
                        $opts[$lbl] = ($lbl === $correct)
                            ? $keyword
                            : ($distract[$d++] ?? ['None','Other','Unknown','N/A'][$d - 1]);
                    }
                    $stems = [
                        "According to the lesson, which of the following is correct about \"{$short}\"?",
                        "What term is associated with: \"{$short}\"?",
                        "Which of the following best describes a key concept from: \"{$short}\"?",
                        "Based on the lesson, what is the correct answer regarding: \"{$short}\"?",
                    ];
                    $result[] = [
                        'question_text'  => $stems[$i % 4],
                        'question_type'  => 'multiple_choice',
                        'choices'        => array_map(fn($l) => ['label' => $l, 'text' => $opts[$l]], ['A','B','C','D']),
                        'correct_answer' => $correct,
                        'explanation'    => "Answer {$correct}: \"{$keyword}\" — {$short}.",
                        'difficulty'     => $difficulty,
                        'bloom_level'    => $bloomLevel,
                        'topic'          => self::topic($sentence),
                    ];
                    break;

                case 'true_false':
                    $isTrue = ($i % 2 === 0);
                    if ($isTrue) {
                        $q   = "True or False: " . rtrim($sentence, '.') . ".";
                        $ans = 'True';
                        $exp = "TRUE — The lesson states: \"" . rtrim($sentence, '.') . ".\"";
                    } else {
                        $neg = self::negate($sentence);
                        $q   = "True or False: " . $neg;
                        $ans = 'False';
                        $exp = "FALSE — The correct statement is: \"" . rtrim($sentence, '.') . ".\"";
                    }
                    $result[] = [
                        'question_text'  => $q,
                        'question_type'  => 'true_false',
                        'choices'        => [['label'=>'A','text'=>'True'],['label'=>'B','text'=>'False']],
                        'correct_answer' => $ans,
                        'explanation'    => $exp,
                        'difficulty'     => $difficulty,
                        'bloom_level'    => $bloomLevel,
                        'topic'          => self::topic($sentence),
                    ];
                    break;

                case 'identification':
                    $blank = preg_replace('/\b' . preg_quote($keyword, '/') . '\b/i', '_________', $sentence, 1);
                    if ($blank === $sentence) $blank = rtrim($sentence, '.') . ': _________';
                    $result[] = [
                        'question_text'  => "Fill in the blank: " . $blank,
                        'question_type'  => 'identification',
                        'choices'        => null,
                        'correct_answer' => $keyword,
                        'explanation'    => "\"{$keyword}\" — " . rtrim($sentence, '.') . ".",
                        'difficulty'     => $difficulty,
                        'bloom_level'    => $bloomLevel,
                        'topic'          => self::topic($sentence),
                    ];
                    break;

                default: // short_answer
                    $stems = ["Explain: ","Describe: ","What is meant by: ","Define: ","Discuss: "];
                    $result[] = [
                        'question_text'  => $stems[$i % 5] . $short . ".",
                        'question_type'  => 'short_answer',
                        'choices'        => null,
                        'correct_answer' => rtrim($sentence, '.') . ".",
                        'explanation'    => 'See lesson content for reference.',
                        'difficulty'     => $difficulty,
                        'bloom_level'    => $bloomLevel,
                        'topic'          => self::topic($sentence),
                    ];
            }
        }

        return $result;
    }

    // ── Normalize API output ──────────────────────────────────

    private static function normalize(array $raw): array
    {
        $out = [];
        foreach ($raw as $q) {
            if (empty(trim($q['question_text'] ?? ''))) continue;

            $choices = null;
            if (!empty($q['choices']) && is_array($q['choices'])) {
                $choices = [];
                $labels  = ['A','B','C','D'];
                foreach ($q['choices'] as $idx => $c) {
                    if (is_array($c) && !empty($c['text'])) {
                        $choices[] = ['label' => $c['label'] ?? $labels[$idx] ?? 'A', 'text' => (string)$c['text']];
                    } elseif (is_string($c) && !empty($c)) {
                        $choices[] = ['label' => $labels[$idx] ?? 'A', 'text' => $c];
                    }
                }
                if (empty($choices)) $choices = null;
            }

            $out[] = [
                'question_text'  => trim($q['question_text']  ?? ''),
                'question_type'  => $q['question_type']  ?? 'multiple_choice',
                'choices'        => $choices,
                'correct_answer' => trim($q['correct_answer'] ?? $q['answer'] ?? ''),
                'explanation'    => trim($q['explanation']    ?? ''),
                'difficulty'     => $q['difficulty']   ?? 'medium',
                'bloom_level'    => $q['bloom_level']   ?? 'understand',
                'topic'          => trim($q['topic']    ?? 'General'),
            ];
        }
        return $out;
    }

    // ── Text Extraction — Windows / XAMPP compatible ──────────

    /**
     * Extract readable text from a file.
     * Supports: TXT, PDF, PPTX/PPT, DOCX
     */
    public static function extractText(string $filePath): string
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return '';
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // ── TXT ──
        if ($ext === 'txt') {
            $t = file_get_contents($filePath);
            return $t !== false ? $t : '';
        }

        // ── PDF ──
        if ($ext === 'pdf') {
            return self::extractPDF($filePath);
        }

        // ── PPTX / PPT ──
        if (in_array($ext, ['pptx', 'ppt'])) {
            return self::extractPPTX($filePath);
        }

        // ── DOCX / DOC ──
        if (in_array($ext, ['docx', 'doc'])) {
            return self::extractDOCX($filePath);
        }

        return '';
    }

    private static function extractPDF(string $path): string
    {
        $raw = file_get_contents($path);
        if ($raw === false || strlen($raw) < 5) return '';

        $text = '';

        // Strategy 1: decompress FlateDecode streams and extract BT...ET text blocks
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $streams);
        foreach ($streams[1] as $stream) {
            $src = false;
            // Try gzuncompress (zlib without header)
            if (strlen($stream) > 0) {
                $src = @gzuncompress($stream);
            }
            // Try gzinflate (raw deflate)
            if ($src === false && strlen($stream) > 2) {
                $src = @gzinflate(substr($stream, 2));
            }
            if ($src === false) $src = $stream;

            // Extract text from BT...ET blocks
            preg_match_all('/BT(.*?)ET/s', $src, $blocks);
            foreach ($blocks[1] as $block) {
                // Match (text)Tj, (text)TJ, [(text)]TJ
                preg_match_all('/\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)\s*T[Jj]/s', $block, $tj);
                foreach ($tj[1] as $t) {
                    $decoded = preg_replace_callback('/\\\\([0-7]{3})/', fn($m) => chr(octdec($m[1])), $t);
                    $decoded = str_replace(['\\n','\\r','\\t','\\(','\\)','\\\\'], [' ',' ',' ','(',')','\\'], $decoded);
                    $text   .= $decoded . ' ';
                }
            }
        }

        // Strategy 2: if strategy 1 gave little text, extract printable ASCII strings
        if (strlen(trim($text)) < 80) {
            preg_match_all('/[A-Za-z0-9][A-Za-z0-9 .,;:\-\'\"()\[\]\/\r\n]{20,}/', $raw, $m);
            $lines = array_filter($m[0], fn($l) => strlen(trim($l)) > 15);
            $text  = implode(' ', $lines);
        }

        $text = preg_replace('/\s+/', ' ', trim($text));
        return strlen($text) > 30 ? $text : '';
    }

    private static function extractPPTX(string $path): string
    {
        if (!class_exists('ZipArchive')) return '';
        try {
            $zip = new ZipArchive();
            if ($zip->open($path) !== true) return '';
            $text = '';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (preg_match('#^ppt/slides/slide\d+\.xml$#', $name)) {
                    $xml = $zip->getFromIndex($i);
                    // Extract all <a:t> text runs
                    preg_match_all('/<a:t(?:\s[^>]*)?>([^<]+)<\/a:t>/', $xml, $m);
                    $text .= implode(' ', $m[1]) . "\n";
                }
            }
            $zip->close();
            return trim(preg_replace('/\s+/', ' ', $text));
        } catch (Throwable) {
            return '';
        }
    }

    private static function extractDOCX(string $path): string
    {
        if (!class_exists('ZipArchive')) return '';
        try {
            $zip = new ZipArchive();
            if ($zip->open($path) !== true) return '';
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            if (!$xml) return '';
            preg_match_all('/<w:t(?:\s[^>]*)?>([^<]+)<\/w:t>/', $xml, $m);
            return trim(preg_replace('/\s+/', ' ', implode(' ', $m[1])));
        } catch (Throwable) {
            return '';
        }
    }

    // ── Helpers ───────────────────────────────────────────────

    private static function trimText(string $t, int $max): string
    {
        // Clean whitespace but preserve sentence structure
        $t = preg_replace('/[ \t]+/', ' ', trim($t));   // collapse spaces/tabs
        $t = preg_replace('/\n{3,}/', "\n\n", $t);       // max 2 newlines
        if (strlen($t) <= $max) return $t;
        // Trim at the last sentence boundary within the limit
        $cut = substr($t, 0, $max);
        $dot = max(strrpos($cut, '.'), strrpos($cut, '?'), strrpos($cut, '!'));
        return ($dot > $max * 0.6) ? substr($cut, 0, $dot + 1) : $cut;
    }

    private static function short(string $s): string
    {
        $s = rtrim(trim($s), '.');
        return strlen($s) > 90 ? substr($s, 0, 90) . '…' : $s;
    }

    private static function topic(string $s): string
    {
        $words = preg_split('/\s+/', $s);
        $stop  = array_flip(['the','a','an','is','are','was','were','and','or','of','to','in','on','at','by','with']);
        $out   = [];
        foreach ($words as $w) {
            $c = trim($w, '.,;:()');
            if (strlen($c) >= 3 && !isset($stop[strtolower($c)])) {
                $out[] = $c;
                if (count($out) === 3) break;
            }
        }
        return implode(' ', $out) ?: 'General';
    }

    private static function negate(string $s): string
    {
        $s = rtrim(trim($s), '.');
        $maps = [' is ' => ' is NOT ', ' are ' => ' are NOT ', ' can ' => ' cannot ',
                 ' has ' => ' does not have ', ' have ' => ' do not have ',
                 ' uses ' => ' does not use ', ' means ' => ' does not mean '];
        foreach ($maps as $f => $r) {
            if (str_contains($s, $f)) return str_replace($f, $r, $s) . '.';
        }
        return 'It is NOT correct to say: "' . $s . '".';
    }
}
