<?php

namespace Modules\SignatureCutter\Services;

class SignatureStripper
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Cut known signatures, legal footers and quoted mail headers.
     *
     * @param string|null $body
     *
     * @return string
     */
    public function clean($body)
    {
        if (!is_string($body) || trim($body) === '') {
            return (string) $body;
        }

        if ($this->shouldDrop($body)) {
            return '';
        }
        $body = $this->stripAgranaDisclaimer($body);
        if ($this->looksLikeHtml($body)) {
            return $this->cleanHtml($body);
        }

        return $this->cleanPlainText($body);
    }
    protected function stripAgranaDisclaimer($body)
    {
        return preg_replace(
            '/Disclaimer:\s*This message contains confidential information.*?Festivalnaya Str\.,\s*5\s*\|\s*142203\s*Russia/isu',
            '',
            $body
        );
    }

    /**
     * Check if the whole email is service noise and should not be shown.
     *
     * @param string|null $body
     *
     * @return bool
     */
    public function shouldDrop($body)
    {
        if (!is_string($body) || trim($body) === '') {
            return false;
        }

        $text = $this->bodyToSearchText($body);
        $patterns = isset($this->config['drop_email_patterns'])
            ? $this->config['drop_email_patterns']
            : [];

        foreach ($patterns as $pattern) {
            if (@preg_match($pattern, '') === false) {
                continue;
            }

            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $body
     *
     * @return string
     */
    protected function cleanPlainText($body)
    {
        $body = $this->normalizeNewlines($body);

        $lines = explode("\n", $body);

        $block = $this->findSignatureBlock($lines);

        if ($block !== null) {
            [$start, $end] = $block;

            array_splice($lines, $start, $end - $start + 1);
        }

        return $this->trimTrailingNoise(
            implode("\n", $lines)
        );
    }

    /**
     * @param string $body
     *
     * @return string
     */
    protected function cleanHtml($body)
    {
        $body = $this->stripKnownSignatureImages($body);

        $bodyWithLineBreaks = preg_replace(
            '/(<br\s*\/?>|<\/p>|<\/div>|<\/li>|<\/tr>|<\/h[1-6]>)/i',
            "$1\n",
            $body
        );

        $lines = explode("\n", $bodyWithLineBreaks);

        $plainLines = array_map(function ($line) {
            return $this->htmlLineToText($line);
        }, $lines);

        $block = $this->findSignatureBlock($plainLines);

        if ($block !== null) {
            [$start, $end] = $block;

            array_splice($lines, $start, $end - $start + 1);
        }

        return $this->trimTrailingNoise(
            implode('', $lines)
        );
    }

    /**
     * Find bounded signature block.
     *
     * @param array $lines
     *
     * @return array|null
     */
    protected function findSignatureBlock(array $lines)
    {
        $normalized = array_map(function ($line) {
            return $this->normalizeLine($line);
        }, $lines);

        foreach ($normalized as $index => $line) {

            if ($line === '') {
                continue;
            }

            if (
                $this->isSignatureStarter($line)
                || $this->isStandaloneSignatureLine($line)
            ) {

                if (!$this->hasSignatureEvidenceAfter($normalized, $index)) {
                    continue;
                }

                $end = $this->findSignatureBlockEnd($normalized, $index);

                return [$index, $end];
            }
        }

        return null;
    }

    /**
     * Detect signature block end.
     *
     * @param array $lines
     * @param int   $start
     *
     * @return int
     */
    protected function findSignatureBlockEnd(array $lines, $start)
    {
        $maxScan = min(count($lines) - 1, $start + 15);

        $end = $start;

        for ($i = $start + 1; $i <= $maxScan; $i++) {

            $line = $lines[$i];

            // quoted reply starts -> stop
            if ($this->isQuotedReplyStart($line)) {
                break;
            }

            // allow empty lines inside signature
            if ($line === '') {
                $end = $i;
                continue;
            }

            // signature-related lines
            if (
                $this->isCorporateMarkerLine($line)
                || $this->isContactLine($line)
                || $this->looksLikePersonName($line)
                || $this->isPipeSeparatedIdentity($line)
            ) {
                $end = $i;
                continue;
            }

            // logo/banner/etc
            if (preg_match('/logo|banner|privacy|confidential/iu', $line)) {
                $end = $i;
                continue;
            }

            // ordinary text -> signature ended
            break;
        }

        return $end;
    }

    /**
     * @param string $line
     *
     * @return bool
     */
    protected function isQuotedReplyStart($line)
    {
        return $this->matchesAny($line, [
            '/^from\s*:/iu',
            '/^sent\s*:/iu',
            '/^to\s*:/iu',
            '/^subject\s*:/iu',

            '/^от\s*:/iu',
            '/^отправлено\s*:/iu',
            '/^кому\s*:/iu',
            '/^тема\s*:/iu',

            '/^-{2,}\s*original message\s*-{2,}/iu',
        ]);
    }

    /**
     * @param string $html
     *
     * @return string
     */
    protected function stripKnownSignatureImages($html)
    {
        return preg_replace_callback('/<img\b[^>]*>/iu', function ($match) {

            $img = $match[0];

            $has400x200 =
                preg_match('/\bwidth=["\']?400["\']?/iu', $img)
                && preg_match('/\bheight=["\']?200["\']?/iu', $img);

            $has125x73 =
                preg_match('/\bwidth=["\']?125["\']?/iu', $img)
                && preg_match('/\bheight=["\']?73["\']?/iu', $img);

            if ($has400x200 || $has125x73) {
                return '';
            }

            return $img;

        }, $html);
    }

    /**
     * @param string $line
     *
     * @return bool
     */
    protected function isDisclaimerStart($line)
    {
       return false;
    }

    /**
     * @param string $line
     *
     * @return bool
     */
    protected function isSignatureStarter($line)
    {
        $line = $this->trimPunctuation($line);

        $starters = isset($this->config['signature_starters'])
            ? $this->config['signature_starters']
            : [];

        foreach ($starters as $starter) {

            if (
                $line === $this->trimPunctuation(
                    $this->normalizeLine($starter)
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $lines
     * @param int   $index
     *
     * @return bool
     */
    protected function hasSignatureEvidenceAfter(array $lines, $index)
    {
        $window = array_slice($lines, $index + 1, 14);

        $evidence = 0;

        foreach ($window as $line) {

            if ($line === '') {
                continue;
            }

            if (
                $this->isCorporateMarkerLine($line)
                || $this->isContactLine($line)
                || $this->isPipeSeparatedIdentity($line)
                || $this->looksLikePersonName($line)
            ) {
                $evidence++;
            }

            if ($evidence >= 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $line
     *
     * @return bool
     */
    protected function isStandaloneSignatureLine($line)
    {
        return $this->isPipeSeparatedIdentity($line)
            || (
                $this->isContactLine($line)
                && $this->isCorporateMarkerLine($line)
            );
    }

    /**
     * @param string $line
     *
     * @return bool
     */
    protected function isPipeSeparatedIdentity($line)
    {
        if (substr_count($line, '|') < 2) {
            return false;
        }

        $parts = array_values(
            array_filter(
                array_map('trim', explode('|', $line))
            )
        );

        if (
            count($parts) >= 3
            && $this->looksLikePersonName($parts[0])
        ) {
            return true;
        }

        return $this->isContactLine($line)
            || $this->isCorporateMarkerLine($line)
            || $this->looksLikePersonName($line);
    }

    /**
     * @param string $line
     *
     * @return bool
     */
    protected function isContactLine($line)
    {
        return $this->matchesAny($line, [
            '/\b(phone|mobile|e-mail|email|tel|t|m)\s*:/iu',
            '/\+\d[\d\s().-]{6,}/u',
            '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/iu',
            '/https?:\/\/\S+/iu',
        ]);
    }

    /**
     * @param string $line
     *
     * @return bool
     */
    protected function isCorporateMarkerLine($line)
    {
        $markers = isset($this->config['corporate_markers'])
            ? $this->config['corporate_markers']
            : [];

        foreach ($markers as $marker) {

            $marker = $this->normalizeLine($marker);

            if (
                $marker !== ''
                && strpos($line, $marker) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $line
     *
     * @return bool
     */
    protected function looksLikePersonName($line)
    {
        if (preg_match('/[@\d|:\/]/u', $line)) {
            return false;
        }

        return (bool) preg_match(
            '/^[\p{L}][\p{L}.\'-]+(\s+[\p{L}][\p{L}.\'-]+){1,3}\.?$/u',
            $line
        );
    }

    /**
     * @param string $line
     * @param array  $patterns
     *
     * @return bool
     */
    protected function matchesAny($line, array $patterns)
    {
        foreach ($patterns as $pattern) {

            if (preg_match($pattern, $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $body
     *
     * @return bool
     */
    protected function looksLikeHtml($body)
    {
        return (bool) preg_match(
            '/<(br|p|div|span|table|tr|td|html|body)\b/i',
            $body
        );
    }

    /**
     * @param string $line
     *
     * @return string
     */
    protected function htmlLineToText($line)
    {
        return html_entity_decode(
            strip_tags($line),
            $this->htmlDecodeFlags(),
            'UTF-8'
        );
    }

    /**
     * @param string $body
     *
     * @return string
     */
    protected function bodyToSearchText($body)
    {
        $body = $this->normalizeNewlines($body);

        if ($this->looksLikeHtml($body)) {

            $body = preg_replace(
                '/(<br\s*\/?>|<\/p>|<\/div>|<\/li>|<\/tr>|<\/h[1-6]>)/i',
                "$1\n",
                $body
            );
        }

        $body = html_entity_decode(
            strip_tags($body),
            $this->htmlDecodeFlags(),
            'UTF-8'
        );

        $body = str_replace("\xC2\xA0", ' ', $body);

        $body = preg_replace(
            '/[ \t\x{00A0}]+/u',
            ' ',
            $body
        );

        return trim($this->lower($body));
    }

    /**
     * @param string $text
     *
     * @return string
     */
    protected function normalizeNewlines($text)
    {
        return str_replace(
            ["\r\n", "\r"],
            "\n",
            $text
        );
    }

    /**
     * @param string $line
     *
     * @return string
     */
    protected function normalizeLine($line)
    {
        $line = html_entity_decode(
            strip_tags((string) $line),
            $this->htmlDecodeFlags(),
            'UTF-8'
        );

        $line = str_replace("\xC2\xA0", ' ', $line);

        $line = preg_replace(
            '/[ \t\x{00A0}]+/u',
            ' ',
            $line
        );

        return trim($this->lower($line));
    }

    /**
     * @param string $line
     *
     * @return string
     */
    protected function trimPunctuation($line)
    {
        return trim($line, " \t\n\r\0\x0B,.;:-");
    }

    /**
     * @param string $text
     *
     * @return string
     */
    protected function lower($text)
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($text, 'UTF-8');
        }

        return strtolower($text);
    }

    /**
     * @return int
     */
    protected function htmlDecodeFlags()
    {
        $flags = ENT_QUOTES;

        if (defined('ENT_HTML5')) {
            $flags |= ENT_HTML5;
        }

        return $flags;
    }

    /**
     * @param string $body
     *
     * @return string
     */
    protected function trimTrailingNoise($body)
    {
        $body = rtrim($body, " \t\n\r\0\x0B");

        $body = preg_replace(
            '/(?:\s|<br\s*\/?>|<\/?p[^>]*>|<\/?div[^>]*>)*$/iu',
            '',
            $body
        );

        $body = preg_replace(
            '/(?:\s*(?:<img\b[^>]*>|image\d+\.(?:png|jpe?g|gif)(?:\?[^ \n\r<]*)?|Agrana Fruit in Fashion Banner|logo|логотип)\s*)+$/iu',
            '',
            $body
        );

        return rtrim($body, " \t\n\r\0\x0B");
    }
}

