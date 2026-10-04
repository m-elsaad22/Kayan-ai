<?php
defined('ABSPATH') || exit;

class RKS_AI {

    private static ?self $inst = null;
    private string $provider;

    public static function instance(): self {
        if (!self::$inst) {
            self::$inst = new self();
        }
        return self::$inst;
    }

    private function __construct() {
        $this->provider = (string) RKS_Helper::opt('ai_provider', 'auto');
    }

    public function provider() {
        $g = new RKS_Gemini();
        $c = new RKS_Claude();
        if ($this->provider === 'gemini') {
            return $g->is_ready() ? $g : null;
        }
        if ($this->provider === 'claude') {
            return $c->is_ready() ? $c : null;
        }
        if ($g->is_ready()) {
            return $g;
        }
        if ($c->is_ready()) {
            return $c;
        }
        return null;
    }

    public function is_ready(): bool {
        return $this->provider() !== null;
    }

    public function name(): string {
        $p = $this->provider();
        if ($p instanceof RKS_Gemini) {
            return 'Google Gemini';
        }
        if ($p instanceof RKS_Claude) {
            return 'Anthropic Claude';
        }
        return 'غير متصل';
    }

    public function ask(string $prompt, int $max = 4000, string $system = '') {
        $p = $this->provider();
        return $p ? $p->ask($prompt, $max, $system) : false;
    }

    public function ask_json(string $prompt, int $max = 4000, string $system = ''): array {
        $sys = $system !== '' ? $system . "\n" : '';
        $sys .= 'أجب بـ JSON صحيح فقط. بدون شرح أو ماركداون أو backticks.';
        $raw = $this->ask($prompt, $max, $sys);
        return $raw ? RKS_Helper::parse_json((string) $raw) : [];
    }
}
