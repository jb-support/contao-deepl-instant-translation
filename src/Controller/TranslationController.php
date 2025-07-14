<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\Controller;

use Contao\CoreBundle\Controller\AbstractController;
use JBSupport\ContaoDeeplInstantTranslationBundle\Model\TranslationModel;

class TranslationController extends AbstractController
{
    public static function translateText(string $text, string $lang, string $source_lang, int $page_id, $deepl_key = null): string
    {
        $text = preg_replace('/(?<= )\s+|\s+(?= )/', '', $text); // Remove extra spaces except one on each side if present
        $hash = md5($text);

        //Check for translation based on page_id, hash, and language
        $translation = TranslationModel::findOneBy(['pid = ? AND hash = ? AND language = ?'], [$page_id, $hash, $lang]);
        if ($translation) {
            // If translation exists, return the translated string
            return $translation->translated_string;
        } else {
            if (empty($deepl_key)) {
                return $text; // Return original text if no API key is provided
            }
            // Try finding translation based on only hash and language (for global translations - footer or header etc)
            $translation = TranslationModel::findOneBy(['hash = ? AND language = ?'], [$hash, $lang]);
            if ($translation) {
                // If translation exists, return the translated string
                return $translation->translated_string;
            }


            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, "https://api-free.deepl.com/v2/translate");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: DeepL-Auth-Key " . $deepl_key
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                "text" => [$text],
                "target_lang" => $lang,
                "source_lang" => $source_lang,
                'tag_handling' => 'html'
            ]));

            $result = curl_exec($ch);
            curl_close($ch);

            $response = json_decode($result, true);

            if (isset($response['translations'][0]['text'])) {
                $translatedText = $response['translations'][0]['text'];
            } else {
                $translatedText = $text; // Fallback to original text if translation fails
            }

            $translation = new TranslationModel();
            $translation->hash = $hash;
            $translation->tstamp = time();
            $translation->original_string = $text;
            $translation->translated_string = $translatedText;
            $translation->language = $lang;
            $translation->pid = $page_id;
            $translation->save();

            return $translatedText;
        }
    }

    public static function translateBatch(array | string $texts, string $lang, string $source_lang, int $page_id, $deepl_key = null): array
    {
        if (!is_array($texts) && !empty($texts)) {
            $texts = [$texts];
        }

        $texts = array_map(function ($text) {
            return preg_replace('/(?<= )\s+|\s+(?= )/', '', $text);
        }, $texts);

        $hashes = array_map('md5', $texts);

        $placeholders = implode(',', array_fill(0, count($hashes), '?'));
        $translations = TranslationModel::findBy(['pid = ? AND hash IN (' . $placeholders . ') AND language = ?'], array_merge([$page_id], $hashes, [$lang]));

        if (empty($translations)) {
            $translations = TranslationModel::findBy(['hash IN (' . $placeholders . ') AND language = ?'], array_merge($hashes, [$lang]));

            if (empty($translations)) {
                $ch = curl_init();

                curl_setopt($ch, CURLOPT_URL, "https://api-free.deepl.com/v2/translate");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Content-Type: application/json",
                    "Authorization: DeepL-Auth-Key " . $deepl_key
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    "text" => [$texts],
                    "target_lang" => $lang,
                    "source_lang" => $source_lang,
                    'tag_handling' => 'html'
                ]));

                $result = curl_exec($ch);
                curl_close($ch);

                $response = json_decode($result, true);

                $translatedText = array_map(function ($translation) use ($texts) {
                    return $translation['text'] ?? $texts[array_search($translation['text'], $texts)];
                }, $response['translations']);

                foreach ($translatedText as $index => $text) {
                    $hash = $hashes[$index];
                    $translation = new TranslationModel();
                    $translation->hash = $hash;
                    $translation->tstamp = time();
                    $translation->original_string = $texts[$index];
                    $translation->translated_string = $text;
                    $translation->language = $lang;
                    $translation->pid = $page_id;
                    $translation->save();
                }

                return $translatedText;
            }
        }
        $translatedTexts = [];
        foreach ($translations as $translation) {
            $translatedTexts[] = $translation->translated_string;
        }
        return $translatedTexts;
    }
}
