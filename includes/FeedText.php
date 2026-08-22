<?php

/**
 * Normalisation for human-readable text taken from third-party feeds.
 *
 * Every title, description and summary that arrives from a feed must go through
 * feedText(). Reading such a value straight out of SimpleXML stores publisher
 * markup and entity noise verbatim, which is how "&nbsp;" and "&mdash;" ended up
 * rendered literally in the UI.
 */

/**
 * Clean a raw feed value into display-ready plain text.
 *
 * @param mixed $raw       SimpleXMLElement, string, or null.
 * @param int   $maxLength Optional truncation, in characters (0 = no limit).
 */
function feedText(mixed $raw, int $maxLength = 0): string
{
    if ($raw === null) {
        return '';
    }

    $text = (string) $raw;
    if ($text === '') {
        return '';
    }

    // Feeds routinely double-encode: the XML holds "&amp;nbsp;", the XML parser
    // hands us the literal "&nbsp;", and storing that verbatim is the bug this
    // guards against. Decoding happens BEFORE strip_tags so that escaped markup
    // ("&lt;p&gt;") turns into real tags that strip_tags then removes - feeds
    // ship descriptions in that form constantly.
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strip_tags($text);

    // &nbsp; decodes to U+00A0. It looks like a space but breaks line wrapping
    // and equality checks, so fold it into a normal space along with any other
    // runs of whitespace the markup left behind.
    $text = str_replace("\xC2\xA0", ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    $text = trim($text);

    if ($maxLength > 0 && mb_strlen($text, 'UTF-8') > $maxLength) {
        $text = rtrim(mb_substr($text, 0, $maxLength, 'UTF-8'));
    }

    return $text;
}
