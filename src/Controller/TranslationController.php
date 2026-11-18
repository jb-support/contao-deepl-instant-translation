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
            $cleanText = preg_replace('/(?<= )\s+|\s+(?= )/', '', $text);
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
                $apiResults = $missingItems;
                break;
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

    // --- NEW DEEPL BATCH LOGIC ---

    /**
     * Sends multiple strings to DeepL in one request.
     * DeepL supports up to 50 text parameters in one call.
     */
    public static function fetchDeepLTranslationBatch(array $textMap, string $targetLang): array
    {
        $config = new Config();
        $deeplKey = $config->getDeeplKey();

        if (empty($deeplKey) || empty($textMap)) {
            return $textMap;
        }

        // Prepare chunks (DeepL recommends ~50 items per request to avoid timeouts)
        $chunks = array_chunk($textMap, 50, true);
        $finalTranslations = [];

        foreach ($chunks as $chunk) {
            // DeepL requires a simple indexed array of strings for the 'text' field
            // We must keep track of keys to map them back later
            $originalKeys = array_keys($chunk);
            $textValues = array_values($chunk);

            $url = $config->getBaseUrl() . "/v2/translate";

            $body = [
                'text' => $textValues, // Send Array here!
                'target_lang' => Settings::getVariant($targetLang),
                'source_lang' => $config->getOriginalLanguage(),
                'formality' => $config->getFormality(),
                'tag_handling' => 'html',
            ];

            if ($gid = $config->getGlossaryId()) {
                $body['glossary_id'] = $gid;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: DeepL-Auth-Key " . $deeplKey
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Handle Errors
            if ($httpCode !== 200) {
                // If failed, return originals for this chunk
                foreach ($chunk as $k => $v) $finalTranslations[$k] = $v;
                continue;
            }

            $response = json_decode($result, true);

            // Map results back to original IDs
            if (isset($response['translations'])) {
                foreach ($response['translations'] as $index => $item) {
                    $originalID = $originalKeys[$index];
                    $finalTranslations[$originalID] = $item['text'];
                }
            } else {
                foreach ($chunk as $k => $v) $finalTranslations[$k] = $v;
            }
        }

        return $finalTranslations;
    }

    public static function translateText(string $text, string $lang, int $page_id, string $translation_provider): string
    {
        $text = preg_replace('/(?<= )\s+|\s+(?= )/', '', $text); // Remove extra spaces except one on each side if present
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
                $translatedText = $text; //self::fetchGPTTranslation($text, $lang);
                break;
            default:
                return $text;
                break;
        }

        return $translatedText;

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
        $text = preg_replace('/(?<= )\s+|\s+(?= )/', '', $translation->original_string);

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
            if ($translation->translated_string === null) {
                return "[nbsp]";
            }

            return $translation->translated_string;
        }

        // Check for translation based on hash and language
        $translation = TranslationModel::findOneBy(['hash = ? AND language = ?'], [$hash, $lang]);
        if ($translation) {
            if ($translation->translated_string === null) {
                return "[nbsp]";
            }

            return $translation->translated_string;
        }

        return null;
    }

    public static function fetchDeepLTranslation(string $text, string $targetLang): string
    {
        $config = new Config();

        $deeplKey = $config->getDeeplKey();
        $sourceLang = $config->getOriginalLanguage();
        $base = $config->getBaseUrl();
        $formality = $config->getFormality();
        $glossaryId = $config->getGlossaryId();

        if (empty($deeplKey)) {
            return $text;
        }

        $url = $base . "/v2/translate";

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: DeepL-Auth-Key " . $deeplKey
        ]);

        $body = [
            'text' => [$text],
            'target_lang' => Settings::getVariant($targetLang),
            'source_lang' => $sourceLang,
            'formality' => $formality,
            'tag_handling' => 'html',
        ];

        if ($glossaryId) {
            $body['glossary_id'] = $glossaryId;
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

        $result = curl_exec($ch);
        curl_close($ch);

        $info = curl_getinfo($ch);
        $httpCode = $info['http_code'];

        if ($httpCode === 456) {
            return $text;
        }

        if ($httpCode === 429) {
            sleep(1);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: DeepL-Auth-Key " . $deeplKey
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            $result = curl_exec($ch);
            curl_close($ch);
        }

        $response = json_decode($result, true);

        if (isset($response['translations'][0]['text'])) {
            $translatedText = $response['translations'][0]['text'];
        } else {
            $translatedText = $text; // Fallback to original text if translation fails
        }

        return $translatedText;
    }

    private static function formatTranslationText(string $text): string
    {
        // Remove extra spaces except one on each side if present
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

        $requestBody = [
            "model" => "gpt-4o-mini-2024-07-18",
            "input" => [
                ["role" => "system", "content" => "Translate the user text in " . $lang . $preferLatin . ". Preserve Uppercase characters. Preserve HTML tags, do not add new ones. In " . $lang . $preferLatin . ". Preserve punctuation, preserve space characters near HTML tags. In " . $lang . $preferLatin . ". Every text is to be translated."],
                ["role" => "user", "content" => $text]
            ]
        ];

        $config = new Config();
        $apiKey = $config->getOpenaiKey();
        if (empty($apiKey)) {
            return $text;
        }

        $ch = curl_init('https://api.openai.com/v1/responses');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);

        $body = json_encode($requestBody);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($result, true);

        $contentText = $text;

        // Responses API: try to extract output text
        if (isset($response['output'][0]['content'][0]['text'])) {
            $contentText = $response['output'][0]['content'][0]['text'];
        } elseif (isset($response['output'][0]['content'][0]['type']) && isset($response['output'][0]['content'][0]['text'])) {
            $contentText = $response['output'][0]['content'][0]['text'];
        }

        return $contentText;
    }

    public static function fetchGPTAliasTranslation(string $text): string
    {

        $requestBody = [
            "model" => "gpt-4o-mini-2024-07-18",
            "input" => [
                ["role" => "system", "content" => "Translate the following URL alias into a URL-friendly slug in English. The translation should be concise and must preserve the meaning of the original alias. Remove any special characters, replace spaces with hyphens, and ensure it is suitable for use in a URL. Do not include the domain, only translate the path part of the URL. If the alias is already in English or is a proper noun, return it as is without translation. If the alias is a slash (/), keep it as is. Return no explanations, only the translated alias. Do not remove or add slashes."],
                ["role" => "user", "content" => $text]
            ]
        ];

        $config = new Config();
        $apiKey = $config->getOpenaiKey();
        if (empty($apiKey)) {
            return $text;
        }

        $ch = curl_init('https://api.openai.com/v1/responses');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);

        $body = json_encode($requestBody);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($result, true);

        $contentText = $text;

        // Responses API: try to extract output text
        if (isset($response['output'][0]['content'][0]['text'])) {
            $contentText = $response['output'][0]['content'][0]['text'];
        } elseif (isset($response['output'][0]['content'][0]['type']) && isset($response['output'][0]['content'][0]['text'])) {
            $contentText = $response['output'][0]['content'][0]['text'];
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

        foreach ($chunks as $chunk) {
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
                . "2. **Placeholders:** Preserve variables EXACTLY: `{name}`, `{{var}}`, `%s`, `$t(key)`, `[link]`, etc. Do not translate or re-space inside them.\n"
                . "3. **HTML/Code:** Leave HTML tags (`<br>`, `<span>`), attributes, URLs, and file paths untouched. Only translate the text between tags.\n"
                . "4. **Entities:** Preserve `&nbsp;`, `\n`, and other escape sequences.\n"
                . "5. **No Hallucinations:** Never invent words or output nonsense. If a brand name exists, keep it in its original or official localized form.\n\n"

                . "### OUTPUT FORMAT\n"
                . "Return ONLY the translated JSON object. No Markdown code blocks, no preamble, no explanations.";

            $jsonPayload = json_encode($chunk, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $requestBody = [
                "model" => "gpt-4o-mini-2024-07-18",
                "response_format" => ["type" => "json_object"],
                "messages" => [
                    ["role" => "system", "content" => $systemPrompt],
                    ["role" => "user", "content" => $jsonPayload]
                ],
                "temperature" => 0.3
            ];

            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $response = json_decode($result, true);
                $content = $response['choices'][0]['message']['content'] ?? null;

                if ($content) {
                    $decodedTranslations = json_decode($content, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedTranslations)) {
                        foreach ($chunk as $originalKey => $originalText) {
                            if (isset($decodedTranslations[$originalKey])) {
                                $finalTranslations[$originalKey] = $decodedTranslations[$originalKey];
                            } else {
                                $finalTranslations[$originalKey] = $originalText;
                            }
                        }
                    } else {
                        foreach ($chunk as $k => $v) $finalTranslations[$k] = $v;
                    }
                }
            } else {
                foreach ($chunk as $k => $v) $finalTranslations[$k] = $v;
            }
        }

        return $finalTranslations;
    }
}
