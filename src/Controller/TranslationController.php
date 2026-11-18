<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\Controller;

use Contao\System;
use Contao\CoreBundle\Controller\AbstractController;
use JBSupport\ContaoDeeplInstantTranslationBundle\Settings;
use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\Config;
use JBSupport\ContaoDeeplInstantTranslationBundle\Model\TranslationModel;
use JBSupport\ContaoDeeplInstantTranslationBundle\Model\PageTranslationModel;

class TranslationController extends AbstractController
{
    private static $gpt_model = "gpt-6-luna";

    /**
     * Translates an array of strings efficiently.
     * @param array $batchInput Associative array ['id_1' => 'Text A', 'id_2' => 'Text B']
     * @return array Associative array ['id_1' => 'Translated A', 'id_2' => 'Translated B']
     */
    public static function translateBatch(array $batchInput, string $lang, int $page_id, string $translation_provider): array
    {
        $db = System::getContainer()->get('database_connection');
        $finalResults = [];
        $missingItems = [];
        $hashMap = [];

        foreach ($batchInput as $id => $text) {
            $cleanText = self::normalizeSpaces($text);
            $hash = md5($cleanText);

            $hashMap[$id] = $hash;

            $cached = self::fetchTranslationFromDB($page_id, $hash, $lang);

            if ($cached) {
                $finalResults[$id] = $cached;
            } else {
                $missingItems[$id] = $cleanText;
            }
        }

        if (empty($missingItems)) {
            return $finalResults;
        }

        $apiResults = [];

        switch ($translation_provider) {
            case 'deepl':
                $apiResults = self::fetchDeepLTranslationBatch($missingItems, $lang);
                break;
            case 'openai':
                $apiResults = self::fetchGPTTranslationBatch($missingItems, $lang);
                break;
            default:
                return array_merge($finalResults, $missingItems);
        }

        foreach ($apiResults as $id => $translatedText) {
            $originalText = $missingItems[$id];
            $hash = $hashMap[$id];

            $query = "INSERT INTO tl_jb_translation (tstamp, hash, language, original_string, translated_string, pid)
            VALUES (:tstamp, :hash, :lang, :orig, :translated, :pid)
            ON DUPLICATE KEY UPDATE tstamp = :tstamp";

            $db->executeQuery($query, [
                'tstamp' => time(),
                'hash'   => $hash,
                'lang'   => $lang,
                'orig'   => $originalText,
                'translated' => $translatedText,
                'pid'    => $page_id
            ]);

            $finalResults[$id] = $translatedText;
        }

        return $finalResults;
    }

    /**
     * Sends multiple strings to DeepL in one request.
     * DeepL supports up to 50 text parameters in one call.
     */
    public static function fetchDeepLTranslationBatch(array $textMap, string $targetLang, bool $retryOn429 = false): array
    {
        $config = new Config();
        $deeplKey = $config->getDeeplKey();

        if (empty($deeplKey) || empty($textMap)) {
            return $textMap;
        }

        $chunks = array_chunk($textMap, 50, true);
        $finalTranslations = [];
        $url = $config->getBaseUrl() . "/v2/translate";

        $headers = [
            "Content-Type: application/json",
            "Authorization: DeepL-Auth-Key " . $deeplKey
        ];

        foreach ($chunks as $chunk) {
            $originalKeys = array_keys($chunk);
            $textValues = array_values($chunk);

            $body = [
                'text' => $textValues,
                'target_lang' => Settings::getVariant($targetLang),
                'source_lang' => $config->getOriginalLanguage(),
                'formality' => $config->getFormality(),
                'tag_handling' => 'html',
            ];

            if ($gid = $config->getGlossaryId()) {
                $body['glossary_id'] = $gid;
            }

            $response = self::sendApiRequest($url, $headers, $body, $retryOn429);

            if ($response['code'] !== 200 || !isset($response['data']['translations'])) {
                foreach ($chunk as $k => $v) $finalTranslations[$k] = $v;
                continue;
            }

            foreach ($response['data']['translations'] as $index => $item) {
                $originalID = $originalKeys[$index];
                $finalTranslations[$originalID] = $item['text'];
            }
        }

        return $finalTranslations;
    }

    public static function translateText(string $text, string $lang, int $page_id, string $translation_provider): string
    {
        $text = self::normalizeSpaces($text);
        $hash = md5($text);

        $translationText = self::fetchTranslationFromDB($page_id, $hash, $lang);
        if ($translationText) {
            return $translationText;
        }

        $translatedText = '';
        switch ($translation_provider) {
            case 'deepl':
                $translatedText = self::fetchDeepLTranslation($text, $lang);
                break;
            case 'openai':
                $translatedText = self::fetchGPTTranslation($text, $lang);
                break;
            default:
                return $text;
        }

        $translation = new TranslationModel();
        $translation->hash = $hash;
        $translation->tstamp = time();
        $translation->original_string = $text;
        $translation->translated_string = $translatedText;
        $translation->language = $lang;
        $translation->pid = $page_id;
        $translation->save();

        return self::formatTranslationText($translatedText);
    }

    public static function forceTranslate(int $translation_id): string
    {
        $config = new Config();
        $translation_provider = $config->getTranslationProvider();

        $translation = TranslationModel::findByPk($translation_id);
        $text = self::normalizeSpaces($translation->original_string);

        switch ($translation_provider) {
            case 'deepl':
                $translatedText = self::fetchDeepLTranslation($text, $translation->language);
                break;
            case 'openai':
                $translatedText = self::fetchGPTTranslation($text, $translation->language);
                break;
            default:
                $translatedText = $text;
                break;
        }

        $translation->translated_string = $translatedText;
        $translation->tstamp = time();
        $translation->save();

        return $translatedText;
    }

    public static function translateAlias($translation): string
    {
        $translation = PageTranslationModel::findOneBy(['alias = ? AND language = ?'], [$translation->alias, $translation->language]);
        if ($translation->alias_translation && $translation->alias_translation !== "") {
            return $translation->alias_translation;
        }

        $translatedAlias = self::fetchGPTAliasTranslation($translation->alias);
        try {
            $translation->alias_translation = $translatedAlias;
            $translation->save();
        } catch (\Exception $e) {
            dd($translatedAlias);
        }

        return $translatedAlias;
    }

    public static function fetchTranslationFromDB($page_id, $hash, $lang): ?string
    {
        $translation = TranslationModel::findOneBy(['pid = ? AND hash = ? AND language = ?'], [$page_id, $hash, $lang]);
        if ($translation) {
            return $translation->translated_string ?? "[nbsp]";
        }

        $translation = TranslationModel::findOneBy(['hash = ? AND language = ?'], [$hash, $lang]);
        if ($translation) {
            return $translation->translated_string ?? "[nbsp]";
        }

        return null;
    }

    public static function fetchDeepLTranslation(string $text, string $targetLang): string
    {
        $result = self::fetchDeepLTranslationBatch(['single' => $text], $targetLang, true);
        return $result['single'] ?? $text;
    }

    private static function formatTranslationText(string $text): string
    {
        $text = html_entity_decode($text);
        $text = str_replace('[nbsp]', ' ', $text);

        // Fix RXSS (FORM-based) Lücke
        $text = str_replace(["'", '"', '´', '`'], ['&#39;', '&quot;', '&#96;', '&#180;'], $text);

        return $text;
    }

    public static function fetchGPTTranslation(string $text, string $targetLang): string
    {
        $lang = Settings::getLanguageEnglishLabel($targetLang);
        $preferLatin = in_array($targetLang, ['sr']) ? ' in Latin script' : '';

        $config = new Config();
        $apiKey = $config->getOpenaiKey();
        if (empty($apiKey)) {
            return $text;
        }

        $body = [
            "model" => self::$gpt_model,
            "messages" => [
                ["role" => "system", "content" => "Translate the user text in " . $lang . $preferLatin . ". Preserve Uppercase characters. Preserve HTML tags, do not add new ones. In " . $lang . $preferLatin . ". Preserve punctuation, preserve space characters near HTML tags. In " . $lang . $preferLatin . ". Every text is to be translated."],
                ["role" => "user", "content" => $text]
            ]
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];

        $response = self::sendApiRequest('https://api.openai.com/v1/chat/completions', $headers, $body);

        $contentText = $text;
        // Responses API: try to extract output text
        if (isset($response['data']['choices'][0]['message']['content'])) {
            $contentText = $response['data']['choices'][0]['message']['content'];
        }

        return $contentText;
    }

    public static function fetchGPTAliasTranslation(string $text): string
    {
        $config = new Config();
        $apiKey = $config->getOpenaiKey();
        if (empty($apiKey)) {
            return $text;
        }

        $body = [
            "model" => self::$gpt_model,
            "messages" => [
                ["role" => "system", "content" => "Translate the following URL alias into a URL-friendly slug in English. The translation should be concise and must preserve the meaning of the original alias. Remove any special characters, replace spaces with hyphens, and ensure it is suitable for use in a URL. Do not include the domain, only translate the path part of the URL. If the alias is already in English or is a proper noun, return it as is without translation. If the alias is a slash (/), keep it as is. Return no explanations, only the translated alias. Do not remove or add slashes."],
                ["role" => "user", "content" => $text]
            ]
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];

        $response = self::sendApiRequest('https://api.openai.com/v1/chat/completions', $headers, $body);

        $contentText = $text;
        // Responses API: try to extract output text
        if (isset($response['data']['choices'][0]['message']['content'])) {
            $contentText = $response['data']['choices'][0]['message']['content'];
        }

        return $contentText;
    }

    /**
     * Clusters OpenAI calls using JSON Mode.
     * Takes an array of strings and returns an array of translations.
     */
    public static function fetchGPTTranslationBatch(array $textMap, string $targetLang): array
    {
        $config = new Config();
        $apiKey = $config->getOpenaiKey();

        if (empty($apiKey) || empty($textMap)) {
            return $textMap;
        }

        $finalTranslations = [];
        $chunks = array_chunk($textMap, 30, true);

        $languageName = Settings::getLanguageEnglishLabel($targetLang);
        $systemPrompt =
            "### ROLE\n"
            . "You are an expert Website Localization Engineer & UX Writer. Your goal is to translate UI and marketing microcopy into native, high-quality " . $languageName . ".\n\n"
            . "### GUIDELINES\n"
            . "* **Native Resonance:** Do not translate word-for-word. Rewrite for natural flow, idiomatic correctness, and standard " . $languageName . " typography.\n"
            . "* **UI Constraints:** Keep CTA/Button text concise and action-oriented. Match the character length of the source as closely as possible to prevent UI breakage.\n"
            . "* **Tone:** Maintain the source's level of formality. Default to 'Professional/Neutral' if ambiguous.\n"
            . "* **Language-Specifics:** Use locale-appropriate punctuation (e.g., quotes, spacing). Avoid literal calques or awkward compounds.\n\n"
            . "### STRICT TECHNICAL RULES\n"
            . "1. **Structure:** Return ONLY the valid JSON. Do not modify keys, nesting, or order. Translate values ONLY.\n"
            . "2. **Placeholders:** Preserve variables EXACTLY: `{name}`, `{{var}}`, `%s`, `\$t(key)`, `[link]`, etc. Do not translate or re-space inside them.\n"
            . "3. **HTML/Code:** Leave HTML tags (`<br>`, `<span>`), attributes, URLs, and file paths untouched. Only translate the text between tags.\n"
            . "4. **Entities:** Preserve `&nbsp;`, `\n`, and other escape sequences.\n"
            . "5. **No Hallucinations:** Never invent words or output nonsense. If a brand name exists, keep it in its original or official localized form.\n\n"
            . "### OUTPUT FORMAT\n"
            . "Return ONLY the translated JSON object. No Markdown code blocks, no preamble, no explanations.";

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];

        foreach ($chunks as $chunk) {
            $jsonPayload = json_encode($chunk, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $body = [
                "model" => self::$gpt_model,
                "response_format" => ["type" => "json_object"],
                "messages" => [
                    ["role" => "system", "content" => $systemPrompt],
                    ["role" => "user", "content" => $jsonPayload]
                ],
                "temperature" => 0.3
            ];

            $response = self::sendApiRequest('https://api.openai.com/v1/chat/completions', $headers, $body);

            if ($response['code'] === 200 && isset($response['data']['choices'][0]['message']['content'])) {
                $content = $response['data']['choices'][0]['message']['content'];
                $decodedTranslations = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedTranslations)) {
                    foreach ($chunk as $originalKey => $originalText) {
                        $finalTranslations[$originalKey] = $decodedTranslations[$originalKey] ?? $originalText;
                    }
                    continue;
                }
            }

            foreach ($chunk as $k => $v) $finalTranslations[$k] = $v;
        }

        return $finalTranslations;
    }

    private static function sendApiRequest(string $url, array $headers, array $body, bool $retryOn429 = false): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($retryOn429 && $httpCode === 429) {
            sleep(1);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        }

        if ($httpCode === 456) {
            return ['code' => $httpCode, 'data' => null];
        }

        return [
            'code' => $httpCode,
            'data' => $result ? json_decode($result, true) : null
        ];
    }

    private static function normalizeSpaces(string $text): string
    {
        // Remove extra spaces except one on each side if present
        return preg_replace('/(?<= )\s+|\s+(?= )/', '', $text);
    }
}
