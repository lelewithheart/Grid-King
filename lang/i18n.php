<?php
/**
 * GridKing i18n – Internationalisation helper
 *
 * Usage:
 *   require_once __DIR__ . '/i18n.php';
 *   $t = new I18n('de');           // or I18n::fromSession()
 *   echo $t->t('nav.home');        // "Startseite"
 *   echo $t->t('standings.title'); // "Meisterschaftswertung"
 *
 * Translation files live in /lang/{code}.php and return an array.
 */

class I18n
{
    private array $strings  = [];
    private array $fallback = [];
    private string $lang;

    public function __construct(string $lang = 'en')
    {
        $this->lang = $lang;

        // Load fallback (English) first
        $enFile = __DIR__ . '/en.php';
        if (file_exists($enFile)) {
            $this->fallback = require $enFile;
        }

        // Load requested language (may be same as fallback)
        if ($lang !== 'en') {
            $langFile = __DIR__ . '/' . preg_replace('/[^a-z\-]/', '', $lang) . '.php';
            if (file_exists($langFile)) {
                $this->strings = require $langFile;
            }
        } else {
            $this->strings = $this->fallback;
        }
    }

    /**
     * Resolve a dot-notation key, e.g. "nav.home" → $strings['nav']['home']
     */
    public function t(string $key, array $replace = []): string
    {
        $value = $this->resolve($this->strings, $key)
               ?? $this->resolve($this->fallback, $key)
               ?? $key;

        foreach ($replace as $placeholder => $replacement) {
            $value = str_replace(':' . $placeholder, htmlspecialchars((string)$replacement), $value);
        }
        return $value;
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    /** Create from session / cookie / browser preference */
    public static function fromRequest(): self
    {
        $allowed = ['en', 'de', 'fr', 'es', 'it', 'pt', 'nl', 'pl'];

        // 1. Explicit URL parameter
        if (!empty($_GET['lang']) && in_array($_GET['lang'], $allowed, true)) {
            $_SESSION['lang'] = $_GET['lang'];
        }

        // 2. Session
        if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], $allowed, true)) {
            return new self($_SESSION['lang']);
        }

        // 3. Browser Accept-Language header
        $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
        foreach ($allowed as $code) {
            if (str_starts_with(strtolower($accept), $code)) {
                return new self($code);
            }
        }

        return new self('en');
    }

    // ----------------------------------------------------------------
    private function resolve(array $data, string $key): ?string
    {
        $parts = explode('.', $key);
        $node  = $data;
        foreach ($parts as $part) {
            if (!is_array($node) || !array_key_exists($part, $node)) {
                return null;
            }
            $node = $node[$part];
        }
        return is_string($node) ? $node : null;
    }
}
