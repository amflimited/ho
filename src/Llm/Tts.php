<?php
declare(strict_types=1);

namespace HoV2\Llm;

use PDO;

/**
 * Text-to-speech for call demos. Gemini multi-speaker TTS: one request renders
 * the whole two-voice conversation as a single clip. Returns WAV bytes (the API
 * returns raw 24 kHz 16-bit mono PCM; we add the 44-byte header — no ffmpeg on
 * shared hosting). Key lives in app_settings `tts_api_key` (a Gemini key,
 * independent of which provider Client uses for text).
 */
final class Tts
{
    private const MODEL = 'gemini-2.5-flash-preview-tts';
    private const VOICE_RECEPTIONIST = 'Kore';
    private const VOICE_CALLER      = 'Puck';

    public function __construct(private readonly PDO $pdo) {}

    public function configured(): bool
    {
        return $this->key() !== '';
    }

    /**
     * @param array<int,array{speaker:string,line:string}> $lines
     * @return array{ok:bool, wav:string, error:string}
     */
    public function render(array $lines): array
    {
        $key = $this->key();
        if ($key === '') {
            return ['ok' => false, 'wav' => '', 'error' => 'No tts_api_key set (needs a Gemini key).'];
        }

        $script = "TTS the following phone conversation. The Receptionist is warm and professional; the Caller is a regular customer:\n\n";
        foreach ($lines as $l) {
            $who = strcasecmp(trim((string)($l['speaker'] ?? '')), 'caller') === 0 ? 'Caller' : 'Receptionist';
            $script .= $who . ': ' . trim((string)($l['line'] ?? '')) . "\n";
        }

        $payload = [
            'contents' => [['parts' => [['text' => $script]]]],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'multiSpeakerVoiceConfig' => [
                        'speakerVoiceConfigs' => [
                            ['speaker' => 'Receptionist', 'voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => self::VOICE_RECEPTIONIST]]],
                            ['speaker' => 'Caller',       'voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => self::VOICE_CALLER]]],
                        ],
                    ],
                ],
            ],
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . self::MODEL . ':generateContent?key=' . urlencode($key);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if (!is_string($resp) || $resp === '') {
            return ['ok' => false, 'wav' => '', 'error' => 'cURL: ' . $err];
        }
        $api = json_decode($resp, true);
        if ($code !== 200) {
            return ['ok' => false, 'wav' => '', 'error' => "TTS {$code}: " . (string)($api['error']['message'] ?? substr($resp, 0, 200))];
        }

        $part = $api['candidates'][0]['content']['parts'][0] ?? [];
        $b64  = (string)($part['inlineData']['data'] ?? '');
        if ($b64 === '') {
            return ['ok' => false, 'wav' => '', 'error' => 'No audio in TTS response.'];
        }
        $pcm = base64_decode($b64, true);
        if ($pcm === false || $pcm === '') {
            return ['ok' => false, 'wav' => '', 'error' => 'Bad base64 audio in TTS response.'];
        }

        $rate = 24000;
        $mime = (string)($part['inlineData']['mimeType'] ?? '');
        if (preg_match('/rate=(\d+)/', $mime, $m)) { $rate = (int)$m[1]; }

        return ['ok' => true, 'wav' => self::wav($pcm, $rate), 'error' => ''];
    }

    /** Wrap raw 16-bit mono PCM in a WAV header. */
    private static function wav(string $pcm, int $rate): string
    {
        $channels = 1; $bits = 16;
        $blockAlign = (int)($channels * $bits / 8);
        $byteRate   = $rate * $blockAlign;
        return 'RIFF' . pack('V', 36 + strlen($pcm)) . 'WAVE'
             . 'fmt ' . pack('VvvVVvv', 16, 1, $channels, $rate, $byteRate, $blockAlign, $bits)
             . 'data' . pack('V', strlen($pcm)) . $pcm;
    }

    private function key(): string
    {
        $s = $this->pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
        $s->execute(['tts_api_key']);
        return trim((string)($s->fetchColumn() ?: ''));
    }
}
