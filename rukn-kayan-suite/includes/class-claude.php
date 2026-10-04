<?php
defined('ABSPATH') || exit;

class RKS_Claude {

    private string $key = '';
    private string $model;

    public function __construct() {
        $this->key   = (string) RKS_Helper::opt('claude_key');
        $this->model = (string) RKS_Helper::opt('claude_model', 'claude-sonnet-4-20250514');
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
        $body = [
            'model'      => $this->model,
            'max_tokens' => $max_tokens,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ];
        if ($system !== '') {
            $body['system'] = $system;
        }
        $res = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => 120,
            'headers' => [
                'content-type'      => 'application/json',
                'x-api-key'         => $this->key,
                'anthropic-version' => '2023-06-01',
            ],
            'body' => wp_json_encode($body),
        ]);
        if (is_wp_error($res)) {
            return false;
        }
        $data = json_decode(wp_remote_retrieve_body($res), true);
        return $data['content'][0]['text'] ?? false;
    }

    public function test(): array {
        if (!$this->is_ready()) {
            return ['success' => false, 'msg' => 'أضف مفتاح Claude'];
        }
        $r = $this->ask('قل مرحبا فقط', 40);
        return $r
            ? ['success' => true, 'msg' => 'Claude يعمل — ' . mb_substr((string) $r, 0, 60)]
            : ['success' => false, 'msg' => 'فشل الاتصال بـ Claude'];
    }
}
