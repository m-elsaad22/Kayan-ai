<?php
defined('ABSPATH') || exit;

class RKS_Gemini {

    private string $key = '';
    private string $model = 'gemini-2.0-flash';

    public function __construct() {
        $this->key = (string) RKS_Helper::opt('gemini_key');
    }

    public function set_key(string $key): void {
        $this->key = $key;
    }

    public function is_ready(): bool {
        return $this->key !== '';
    }

    public function ask(string $prompt, int $max_tokens = 4000, string $system = '') {
        if (!$this->is_ready()) {
            return false;
        }
        $text = $system !== '' ? $system . "\n\n" . $prompt : $prompt;
        $url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent?key=' . rawurlencode($this->key);
        $body = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $text]]],
            ],
            'generationConfig' => [
                'maxOutputTokens' => $max_tokens,
                'temperature'     => 0.95,
            ],
        ];
        $res = wp_remote_post($url, [
            'timeout' => 90,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
        ]);
        if (is_wp_error($res)) {
            return false;
        }
        $data = json_decode(wp_remote_retrieve_body($res), true);
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? false;
    }

    public function test(): array {
        if (!$this->is_ready()) {
            return ['success' => false, 'msg' => 'أضف مفتاح Gemini من AI Studio'];
        }
        $r = $this->ask('قل مرحبا فقط', 40);
        return $r
            ? ['success' => true, 'msg' => 'Gemini يعمل — ' . mb_substr((string) $r, 0, 60)]
            : ['success' => false, 'msg' => 'فشل الاتصال بـ Gemini'];
    }
}
