<?php
declare(strict_types=1);

namespace HoV2\Llm;

use PDO;

final class Client
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array{ok:bool, text:string, error:string} */
    public function call(string $prompt, string $system, int $maxTokens = 8000, bool $search = true): array
    {
        $cfg = $this->settings();
        if (($cfg['key'] ?? '') === '') {
            return ['ok' => false, 'text' => '', 'error' => 'No AI engine configured.'];
        }
        return $cfg['provider'] === 'gemini'
            ? $this->gemini($prompt, $system, $maxTokens, $cfg, $search)
            : $this->anthropic($prompt, $system, $maxTokens, $cfg, $search);
    }

    public function prompt(string $name, array $vars = []): string
    {
        $path = dirname(__DIR__, 2) . "/prompts/{$name}.md";
        $tpl = file_get_contents($path) ?: throw new \RuntimeException("Missing prompt: {$name}");
        if (str_contains($tpl, '{research_spec}')) {
            $vars['research_spec'] = file_get_contents(dirname(__DIR__, 2) . '/generated/research_spec.json') ?: '';
        }
        return strtr($tpl, array_combine(
            array_map(fn($k) => '{' . $k . '}', array_keys($vars)),
            array_map(fn($v) => (string)$v, $vars)
        ));
    }

    /** @return array{provider:string, key:string, model:string} */
    private function settings(): array
    {
        $get = function (string $k): string {
            $s = $this->pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
            $s->execute([$k]);
            return (string)($s->fetchColumn() ?: '');
        };
        return [
            'provider' => $get('llm_provider') ?: 'anthropic',
            'key'      => $get('llm_api_key'),
            'model'    => $get('llm_model'),
        ];
    }

    private function anthropic(string $prompt, string $system, int $maxTokens, array $cfg, bool $search): array
    {
        $req = [
            'model'      => $cfg['model'] !== '' ? $cfg['model'] : 'claude-sonnet-4-6',
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ];
        if ($search) {
            $req['tools'] = [['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 4]];
        }
        [$code, $resp, $err] = $this->post(
            'https://api.anthropic.com/v1/messages',
            json_encode($req),
            ['Content-Type: application/json', 'x-api-key: ' . $cfg['key'], 'anthropic-version: 2023-06-01'],
            $search ? 240 : 60
        );
        if ($resp === null) { return ['ok' => false, 'text' => '', 'error' => 'cURL: ' . $err]; }
        $api = json_decode($resp, true);
        if ($code !== 200) {
            return ['ok' => false, 'text' => '', 'error' => "Anthropic {$code}: " . ($api['error']['message'] ?? substr($resp, 0, 200))];
        }
        $text = '';
        foreach ((array)($api['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') { $text .= $block['text'] ?? ''; }
        }
        return $text !== ''
            ? ['ok' => true, 'text' => $text, 'error' => '']
            : ['ok' => false, 'text' => '', 'error' => 'No text in Claude response.'];
    }

    private function gemini(string $prompt, string $system, int $maxTokens, array $cfg, bool $search): array
    {
        $model = $cfg['model'] !== '' ? $cfg['model'] : 'gemini-2.5-flash';
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . rawurlencode($model) . ':generateContent?key=' . urlencode($cfg['key']);
        $payload = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents'          => [['parts' => [['text' => $prompt]]]],
            'generationConfig'  => ['maxOutputTokens' => $maxTokens, 'temperature' => 0.2],
        ];
        if ($search) { $payload['tools'] = [['google_search' => new \stdClass()]]; }

        $lastError = '';
        for ($attempt = 0; $attempt < 3; $attempt++) {
            [$code, $resp, $err] = $this->post($url, json_encode($payload), ['Content-Type: application/json'], $search ? 240 : 60);
            if ($resp === null) { return ['ok' => false, 'text' => '', 'error' => 'cURL: ' . $err]; }
            if ($code === 429 && $attempt < 2) {
                $msg = (string)(json_decode($resp, true)['error']['message'] ?? $resp);
                $wait = 62;
                if (preg_match('/retry in (\d+(?:\.\d+)?)s/i', $msg, $m)) { $wait = min(62, (int)ceil((float)$m[1])); }
                $lastError = 'Gemini 429: ' . $msg;
                sleep($wait);
                continue;
            }
            if ($code !== 200) {
                $api = json_decode($resp, true);
                return ['ok' => false, 'text' => '', 'error' => "Gemini {$code}: " . ($api['error']['message'] ?? substr($resp, 0, 200))];
            }
            $text = '';
            foreach ((array)(json_decode($resp, true)['candidates'][0]['content']['parts'] ?? []) as $part) {
                $text .= $part['text'] ?? '';
            }
            return $text !== ''
                ? ['ok' => true, 'text' => $text, 'error' => '']
                : ['ok' => false, 'text' => '', 'error' => 'No text in Gemini response.'];
        }
        return ['ok' => false, 'text' => '', 'error' => $lastError ?: 'Gemini: max retries exceeded.'];
    }

    /** @return array{0:int, 1:?string, 2:string} [http code, body|null, curl error] */
    private function post(string $url, string $body, array $headers, int $timeout): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return [$code, $resp === false || $resp === '' ? null : (string)$resp, $err];
    }
}
