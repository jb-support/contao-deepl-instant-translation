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

		'ku' => 'Kurdî',
		'fa' => 'فارسی',
		'ps' => 'پښتو',
		'sr' => 'Srpski',
		'hr' => 'Hrvatski',
		'bs' => 'Bosanski',
		'ti' => 'ትግርኛ',
		'hi' => 'हिन्दी',
		'ta' => 'தமிழ்',
		'vi' => 'Tiếng Việt',
		'th' => 'ไทย',
		'ur' => 'اردو',
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

		'ku' => 'Wergerînî bi %s',
		'fa' => 'ترجمه به %s',
		'ps' => 'ژباړه په %s',
		'sr' => 'Prevod na %s',
		'hr' => 'Prijevod na %s',
		'bs' => 'Prijevod na %s',
		'ti' => 'ትርጉም ወደ %s',
		'hi' => '%s पर अनुवाद',
		'ta' => '%s க்கு மொழிபெயர்ப்பு',
		'vi' => 'Dịch sang %s',
		'th' => 'กำลังแปลเป็น %s',
		'ur' => '%s میں ترجمہ کرنا',
	];

	private static $translatingSubstrings = [
		'ar' => 'يبدو أنك الأول الذي يترجم هذه الصفحة. قد يستغرق الترجمة بعض الوقت.',
		'bg' => 'Изглежда сте първият, който превежда тази страница. Преводът може да отнеме известно време.',
		'zh' => '似乎您是第一个翻译此页面的人。 翻译可能需要一些时间。',
		'cs' => 'Zdá se, že jste první, kdo překládá tuto stránku. Překlad může chvíli trvat.',
		'da' => 'Det ser ud til, at du er den første, der oversætter denne side. Oversættelse kan tage et øjeblik.',
		'nl' => 'Het lijkt erop dat u de eerste bent die deze pagina vertaalt. Vertalen kan even duren.',
		'en' => 'Seems you are the first one translating this page. Translating may take a moment.',
		'et' => 'Tundub, et olete esimene, kes seda lehte tõlgib. Tõlkimine võib võtta aega.',
		'fi' => 'Näyttää siltä, että olet ensimmäinen, joka kääntää tämän sivun. Kääntäminen saattaa kestää hetken.',
		'fr' => 'Il semble que vous soyez le premier à traduire cette page. La traduction peut prendre un moment.',
		'de' => 'Es scheint, dass Sie der Erste sind, der diese Seite übersetzt. Die Übersetzung kann einen Moment dauern.',
		'el' => 'Φαίνεται ότι είστε ο πρώτος που μεταφράζει αυτήν τη σελίδα. Η μετάφραση μπορεί να διαρκέσει λίγο.',
		'hu' => 'Úgy tűnik, Ön az első, aki lefordítja ezt az oldalt. A fordítás eltarthat egy ideig.',
		'id' => 'Sepertinya Anda adalah yang pertama menerjemahkan halaman ini. Menerjemahkan mungkin memerlukan waktu sejenak.',
		'it' => 'Sembra che tu sia il primo a tradurre questa pagina. La traduzione potrebbe richiedere un po\' di tempo.',
		'ja' => 'このページを翻訳するのはあなたが初めてのようです。 翻訳には少し時間がかかる場合があります。',
		'ko' => '이 페이지를 번역하는 것은 처음인 것 같습니다. 번역하는 데 잠시 시간이 걸릴 수 있습니다.',
		'lv' => 'Izskatās, ka jūs esat pirmais, kas tulko šo lapu. Tulkošana var aizņemt kādu brīdi.',
		'lt' => 'Panašu, kad esate pirmasis, kuris verčia šį puslapį. Vertimas gali užtrukti akimirką.',
		'no' => 'Det ser ut til at du er den første som oversetter denne siden. Oversettelse kan ta et øyeblikk.',
		'pl' => 'Wygląda na to, że jesteś pierwszą osobą tłumaczącą tę stronę. Tłumaczenie może chwilę potrwać.',
		'pt' => 'Parece que você é o primeiro a traduzir esta página. A tradução pode levar um momento.',
		'ro' => 'Se pare că sunteți primul care traduce această pagină. Traducerea poate dura puțin.',
		'ru' => 'Похоже, вы первый, кто переводит эту страницу. Перевод может занять некоторое время.',
		'sk' => 'Zdá sa, že ste prvý, kto prekladá túto stránku. Preklad môže chvíľu trvať.',
		'sl' => 'Zdi se, da ste prvi, ki prevaja to stran. Prevajanje lahko traja nekaj časa.',
		'es' => 'Parece que eres el primero en traducir esta página. La traducción puede llevar un momento.',
		'sv' => 'Det verkar som att du är den första som översätter den här sidan. Översättningen kan ta en stund.',
		'tr' => 'Görünüşe göre bu sayfayı çeviren ilk kişisiniz. Çeviri biraz zaman alabilir.',
		'uk' => 'Схоже, ви перший, хто перекладає цю сторінку. Переклад може зайняти деякий час.',

		'ku' => 'Xuya dike ku tu yekem kes î ku vê rûpelê wergerînî. Werger dikare demekî bigire.',
        'fa' => 'به نظر می‌رسد شما اولین نفری هستید که این صفحه را ترجمه می‌کنید. ترجمه ممکن است کمی زمان ببرد.',
        'ps' => 'دا داسې ښکاري چې تاسې لومړی څوک یاست چې دا پاڼه ژباړئ. ژباړه کېدای شي لږ وخت ونیسي.',
        'sr' => 'Izgleda da ste vi prvi koji prevodi ovu stranicu. Prevođenje može potrajati neko vreme.',
        'hr' => 'Izgleda da ste vi prvi koji prevodi ovu stranicu. Prijevod može potrajati neko vrijeme.',
        'bs' => 'Izgleda da ste vi prvi koji prevodi ovu stranicu. Prijevod može potrajati neko vrijeme.',
        'ti' => 'ይመስል እዚ ገፅ ዝትተርጎም ቀዳማይ ሰብ እቲ ኢኻ። ትርጉም ግዜ ሊወስድ ይኽእል።',
        'hi' => 'लगता है कि आप इस पेज का अनुवाद करने वाले पहले व्यक्ति हैं। अनुवाद में थोड़ा समय लग सकता है।',
        'ta' => 'இந்தப் பக்கத்தை மொழிபெயர்க்கும் முதல் நபர் நீங்கள் போல தெரிகிறது. மொழிபெயர்ப்பிற்கு சிறிது நேரம் பிடிக்கலாம்.',
        'vi' => 'Có vẻ như bạn là người đầu tiên dịch trang này. Việc dịch có thể sẽ mất một lúc.',
        'th' => 'ดูเหมือนว่าคุณจะเป็นคนแรกที่แปลหน้านี้ การแปลอาจใช้เวลาสักครู่',
        'ur' => 'ایسا لگتا ہے کہ آپ اس صفحے کا ترجمہ کرنے والے پہلے شخص ہیں۔ ترجمہ ہونے میں کچھ وقت لگ سکتا ہے۔',
	];

	private static $languagesEnglish = [
		'ar' => 'Arabic',
		'bg' => 'Bulgarian',
		'zh' => 'Chinese',
		'cs' => 'Czech',
		'da' => 'Danish',
		'nl' => 'Dutch',
		'en' => 'English',
		'et' => 'Estonian',
		'fi' => 'Finnish',
		'fr' => 'French',
		'de' => 'German',
		'el' => 'Greek',
		'hu' => 'Hungarian',
		'id' => 'Indonesian',
		'it' => 'Italian',
		'ja' => 'Japanese',
		'ko' => 'Korean',
		'lv' => 'Latvian',
		'lt' => 'Lithuanian',
		'no' => 'Norwegian',
		'pl' => 'Polish',
		'pt' => 'Portuguese',
		'ro' => 'Romanian',
		'ru' => 'Russian',
		'sk' => 'Slovak',
		'sl' => 'Slovenian',
		'es' => 'Spanish',
		'sv' => 'Swedish',
		'tr' => 'Turkish',
		'uk' => 'Ukrainian',

 		'ku' => 'Kurdish (Kurmanji)',
        'fa' => 'Persian (Farsi/Dari)',
        'ps' => 'Pashto',
        'sr' => 'Serbian',
        'hr' => 'Croatian',
        'bs' => 'Bosnian',
        'ti' => 'Tigrinya',
        'hi' => 'Hindi',
        'ta' => 'Tamil',
        'vi' => 'Vietnamese',
        'th' => 'Thai',
        'ur' => 'Urdu',
	];

	private static $defaultVariants = [
		"en" => "EN-US",
		"pt" => "PT-PT",
		"zh" => "ZH-HANT",
	];

	private static $flagOverrides = [
		"en" => "gb",
		"ar" => "sa",
		"zh" => "cn",
		"el" => "gr",
		"uk" => "ua",
		"bs" => "ba",
		"sr" => "rs",
		// "ku" => "iq",
		"fa" => "ir",
		"ps" => "af",
		"ti" => "er",
		"hi" => "in",
		"ta" => "in",
		"ur" => "pk",
		"vi" => "vn",
		"th" => "th",
	];

	public static function getLanguages()
	{
		return self::$languages;
	}

	public static function getTranslatingSubstrings()
	{
		return self::$translatingSubstrings;
	}

	public static function getLanguageEnglishLabel($lang)
	{
		return self::$languagesEnglish[$lang];
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

	public static function getTranslatedLanguageOnly($lang)
	{
		return $GLOBALS['TL_LANG']['lang'][$lang] ?? '';
	}

	public static function getTranslatedLanguagesArray()
	{
		return $GLOBALS['TL_LANG']['lang'] ?? [];
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

	public static function getFormalityTypes()
	{
		return [
			'prefer_more' => $GLOBALS['TL_LANG']['tl_module']['formality']['prefer_more'],
			'default' => $GLOBALS['TL_LANG']['tl_module']['formality']['default'],
			'prefer_less' => $GLOBALS['TL_LANG']['tl_module']['formality']['prefer_less']
		];
	}

	public static function getVariant($lang)
	{
		return self::$defaultVariants[$lang] ?? $lang;
	}

	public static function getFlagSvg($lang)
	{
		$flag = self::$flagOverrides[$lang] ?? $lang;
		$flagPath = 'bundles/jbsupportcontaodeeplinstanttranslation/flags/' . $flag . '.svg';
		if (file_exists($flagPath)) {
			return file_get_contents($flagPath);
		}
		return '';
	}
}
