<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\Controller;

use Contao\CoreBundle\Controller\AbstractController;
use JBSupport\ContaoDeeplInstantTranslationBundle\Settings;
use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\Config;
use JBSupport\ContaoDeeplInstantTranslationBundle\Model\TranslationModel;

class TranslationController extends AbstractController
{
    public static function translateText(string $text, string $lang, int $page_id): string
    {
        $text = preg_replace('/(?<= )\s+|\s+(?= )/', '', $text); // Remove extra spaces except one on each side if present
        $hash = md5($text);

        $translation = self::fetchTranslationFromDB($hash, $lang);
        if ($translation) {
            return $translation;
        }

        $translatedText = self::fetchDeepLTranslation($text, $lang);

        $translation = new TranslationModel();
        $translation->hash = $hash;
        $translation->tstamp = time();
        $translation->original_string = $text;
        $translation->translated_string = $translatedText;
        $translation->language = $lang;
        $translation->pid = $page_id;
        $translation->save();

        return html_entity_decode($translatedText);
    }

    public static function forceTranslate(int $translation_id): string
    {
        $translation = TranslationModel::findByPk($translation_id);
        $text = preg_replace('/(?<= )\s+|\s+(?= )/', '', $translation->original_string);

        $translatedText = self::fetchDeepLTranslation($text, $translation->language);

        $translation->translated_string = $translatedText;
        $translation->tstamp = time();
        $translation->save();

        return $translatedText;
    }

    public static function fetchTranslationFromDB($hash, $lang): ?string
    {
        $translation = TranslationModel::findOneBy(['hash = ? AND language = ?'], [$hash, $lang]);
        if ($translation) {
            return html_entity_decode($translation->translated_string);
        }

        return null;
    }

    public static function fetchDeepLTranslation(string | array $text, string $targetLang): string | array
    {
        $config = new Config();

        $deeplKey = $config->getDeeplKey();
        $sourceLang = $config->getOriginalLanguage();
        $url = $config->getApiUrl();
        $formality = $config->getFormality();
        $glossaryId = $config->getGlossaryId();

        if (empty($deeplKey)) {
            return $text;
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: DeepL-Auth-Key " . $deeplKey
        ]);

        $body = [
            'text' => is_array($text) ? $text : [$text],
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

        $response = json_decode($result, true);

        if (!is_array($text) && isset($response['translations'][0]['text'])) {
            $translatedText = $response['translations'][0]['text'];
        } else if (is_array($text) && isset($response['translations'])) {
            $translatedText = $response['translations'];
        } else {
            $translatedText = is_array($text) ? $text : [$text];
        }

        return $translatedText;
    }
}
