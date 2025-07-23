<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle;

class Settings
{
	private static $languages = [
		'ar' => 'العربية',
		'bg' => 'Български',
		'zh' => '中文',
		'cs' => 'Čeština',
		'da' => 'Dansk',
		'nl' => 'Nederlands',
		'en' => 'English',
		'et' => 'Eesti',
		'fi' => 'Suomi',
		'fr' => 'Français',
		'de' => 'Deutsch',
		'el' => 'Ελληνικά',
		'hu' => 'Magyar',
		'id' => 'Bahasa Indonesia',
		'it' => 'Italiano',
		'ja' => '日本語',
		'ko' => '한국어',
		'lv' => 'Latviešu',
		'lt' => 'Lietuvių',
		'no' => 'Norsk',
		'pl' => 'Polski',
		'pt' => 'Português',
		'ro' => 'Română',
		'ru' => 'Русский',
		'sk' => 'Slovenčina',
		'sl' => 'Slovenščina',
		'es' => 'Español',
		'sv' => 'Svenska',
		'tr' => 'Türkçe',
		'uk' => 'Українська',
	];

	private static $translatingStrings = [
		'ar' => 'ترجمة إلى %s',
		'bg' => 'Превеждане на %s',
		'zh' => '翻译为 %s',
		'cs' => 'Překlad do %s',
		'da' => 'Oversætter til %s',
		'nl' => 'Vertalen naar %s',
		'en' => 'Translating to %s',
		'et' => 'Tõlkimine keelde %s',
		'fi' => 'Kääntää kielelle %s',
		'fr' => 'Traduction en %s',
		'de' => 'Übersetzung in %s',
		'el' => 'Μετάφραση στα %s',
		'hu' => 'Fordítás erre: %s',
		'id' => 'Menerjemahkan ke %s',
		'it' => 'Traduzione in %s',
		'ja' => '翻訳 %s',
		'ko' => '번역 %s',
		'lv' => 'Tulkošana uz %s',
		'lt' => 'Versti į %s',
		'no' => 'Oversetter til %s',
		'pl' => 'Tłumaczenie na %s',
		'pt' => 'Tradução para %s',
		'ro' => 'Traducere în %s',
		'ru' => 'Перевод на %s',
		'sk' => 'Preklad do %s',
		'sl' => 'Prevod v %s',
		'es' => 'Traducción a %s',
		'sv' => 'Översätter till %s',
		'tr' => 'Çeviri %s',
		'uk' => 'Переклад на %s',
	];

	public static function getLanguages()
	{
		return self::$languages;
	}

	public static function getLanguageTranslatedStrings()
	{
		$languagesTranslated = [];

		foreach (self::$languages as $lang => $name) {
			$translated = $GLOBALS['TL_LANG']['lang'][$lang] ? $GLOBALS['TL_LANG']['lang'][$lang] : null;
			$languagesTranslated[$lang] = $name . ($translated ? " (" . $translated . ")" : "");
		}

		return $languagesTranslated;
	}

	public static function getLanguageString($lang)
	{
		return self::$languages[$lang];
	}

	public static function getTranslatingInProgressStrings()
	{
		$result = [];
		foreach (self::$translatingStrings as $lang => $template) {
			$result[$lang] = sprintf($template, self::getLanguageString($lang));
		}
		return $result;
	}

	public static function getElementLabelTypes()
	{
		return [
			'short' => $GLOBALS['TL_LANG']['tl_module']['element_label_type']['short'],
			'long' => $GLOBALS['TL_LANG']['tl_module']['element_label_type']['long']
		];
	}
}
