<?php

use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\Config;
use JBSupport\ContaoDeeplInstantTranslationBundle\Settings;

$GLOBALS['TL_DCA']['tl_module']['config']['onsubmit_callback'][] = array('translation_module', 'writeConfig');

$GLOBALS['TL_DCA']['tl_module']['fields']['disabled'] = [
    'exclude'                 => true,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class' => 'clr w100'],
    'sql'                     => "TINYINT(1) NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['deepl_key'] = [
    'exclude'                 => true,
    'inputType'               => 'text',
    'eval'                    => array(
        'maxlength' => 255,
        'tl_class' => 'clr w50',
        'doNotSaveEmpty' => true,
        'mandatory' => (class_exists('translation_module') && (new translation_module())->hasDeepLKey()) ? false : true,
        'placeholder' => (class_exists('translation_module') && (new translation_module())->hasDeepLKey()) ? '**************' : ''
    ),
    'sql'                     => "varchar(255) NOT NULL default ''",
];


$GLOBALS['TL_DCA']['tl_module']['fields']['original_language'] = [
    'exclude'                 => true,
    'inputType'               => 'select',
    'options_callback'        => array(Settings::class, 'getLanguageTranslatedStrings'),
    'eval'                    => array('tl_class' => 'w50', 'chosen' => true, 'mandatory' => true),
    'sql'                     => "varchar(5) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['languages'] = [
    'exclude'                 => true,
    'inputType'               => 'checkboxWizard',
    'eval'                    => array('multiple' => true, 'tl_class' => 'w50', 'mandatory' => true),
    'options_callback'        => array(Settings::class, 'getLanguageTranslatedStrings'),
    'sql'                     => "TEXT NULL",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['show_modal'] = [
    'exclude'                 => true,
    'inputType'               => 'checkbox',
    'eval'                    => array('tl_class' => 'w50'),
    'sql'                     => "TINYINT(1) NULL default '1'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['usage_info'] = [
    'exclude'                 => false,
    'inputType'               => 'custom',
    'eval'                    => ['tl_class' => 'clr', 'doNotSaveEmpty' => true],
    'input_field_callback'    => ['translation_module', 'getUsageInfo'],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['in_url'] = [
    'exclude'                 => true,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class' => 'w50'],
    'sql'                     => "TINYINT(1) NULL default '1'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['agent_redirect'] = [
    'exclude'                 => true,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class' => 'w50'],
    'sql'                     => "TINYINT(1) NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['element_type'] = [
    'exclude'                 => false,
    'inputType'               => 'select',
    'options'                 => ['select' => 'Select', 'radio' => 'Radio', 'buttons' => 'Buttons'],
    'eval'                    => ['tl_class' => 'w50', 'chosen' => true],
    'sql'                     => "varchar(10) NOT NULL default 'select'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['deepl_pro_plan'] = [
    'exclude'                 => true,
    'inputType'               => 'checkbox',
    'eval'                    => array('tl_class' => 'w50'),
    'sql'                     => "TINYINT(1) NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['element_label_type'] = [
    'exclude'                 => true,
    'inputType'               => 'radio',
    'options'                 => ['short' => 'Short', 'long' => 'Long'],
    'options_callback'        => [Settings::class, 'getElementLabelTypes'],
    'eval'                    => ['tl_class' => 'w50'],
    'sql'                     => "varchar(10) NOT NULL default 'long'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['glossary_id'] = [
    'exclude'                 => false,
    'inputType'               => 'select',
    'options'                 => [],
    'options_callback'        => ['translation_module', 'getGlossaries'],
    'eval'                    => ['tl_class' => 'w50', 'chosen' => true, 'includeBlankOption' => true],
    'sql'                     => "varchar(36) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['formality'] = [
    'exclude'                 => false,
    'inputType'               => 'radio',
    'options'                 => ['prefer_more' => 'More formal', 'default' => 'Default', 'prefer_less' => 'Less formal'],
    'options_callback'        => [Settings::class, 'getFormalityTypes'],
    'eval'                    => ['tl_class' => 'w50'],
    'sql'                     => "varchar(12) NOT NULL default 'default'",
];

$GLOBALS['TL_DCA']['tl_module']['palettes']['language_switcher_module'] =
    '{disable_legend},disabled;{title_legend},name, type, deepl_key, in_url, agent_redirect; {languages_legend}, languages, original_language, glossary_id, formality; {look_legend}, element_type, show_modal, element_label_type; {usage_legend},usage_info';

class translation_module
{
    public function writeConfig($dc)
    {
        $configObj = new Config();
        $configPath = $configObj->getConfigPath();
        $fields = $configObj->getFields();
        $existingConfig = file_exists($configPath) ? @include($configPath) : [];
        $db = \Contao\Database::getInstance();

        $config = [];
        foreach ($fields as $field) {
            if ($field == "deepl_key") {
                if (!empty($dc->activeRecord->deepl_key)) {
                    $config[$field] = $this->handleConfigValue($dc->activeRecord->{$field});
                } else {
                    $config[$field] = $existingConfig[$field] ?? false;
                }

                if (str_contains($config[$field], ':fx')) {
                    $config['deepl_pro_plan'] = false;
                } else {
                    $config['deepl_pro_plan'] = true;
                }
                continue;
            }

            $config[$field] = $this->handleConfigValue($dc->activeRecord->{$field});
        }

        $db->query("UPDATE tl_module SET `deepl_key` = '' WHERE id=" . $dc->activeRecord->id);

        $content = "<?php\n"
            . "/*THIS FILE HAS BEEN AUTO GENERATED BY THE CONTAO DEEPL INSTANT TRANSLATIONS EXTENSION*/\n"
            . "return "  . var_export($config, true) . ";";

        file_put_contents($configPath, $content);

        return '';
    }

    public function hasDeepLKey()
    {
        $config = new Config();

        return !empty($config->getDeepLKey());
    }

    private function handleConfigValue($value)
    {
        if (is_array($value)) {
            return serialize($value);
        } else if (is_numeric($value)) {
            return $value != 0 ? true : false;
        } else if (is_null($value)) {
            return false;
        } else if (empty($value)) {
            return false;
        }

        return $value;
    }

    public function getUsageInfo($dc)
    {
        $config = new Config();
        $deepl_key = $config->getDeeplKey();
        $base_url = $config->getBaseUrl();
        $pro_plan = $config->getIsProPlan();

        if (empty($deepl_key)) {
            return '<div class="tl_info">' . $GLOBALS['TL_LANG']['tl_module']['usage_info']['no_key'] . '</div>';
        }

        $response = $this->fetchUsageInfo($deepl_key, $base_url);

        return $this->generateUsageString($response, $pro_plan);
    }

    private function generateUsageString($response, $pro_plan)
    {
        $plan = $pro_plan ? 'API Pro' : 'API Free';

        $planstring = sprintf(
            $GLOBALS['TL_LANG']['tl_module']['usage_info']['plan'],
            htmlspecialchars($plan)
        );

        $usageInfo = '';

        if ($plan == 'API Pro') {
            $characterInfo = sprintf(
                $GLOBALS['TL_LANG']['tl_module']['usage_info']['characters'],
                number_format($response['api_key_character_count']),
                $response['api_key_character_limit'] > 0 ? number_format($response['api_key_character_limit']) : '∞'
            );

            $timeInfo = sprintf(
                $GLOBALS['TL_LANG']['tl_module']['usage_info']['times'],
                date('d.m.Y H:i', strtotime($response['start_time'])),
                date('d.m.Y H:i', strtotime($response['end_time']))
            );

            $usageInfo = $characterInfo . "<br>" . $timeInfo;
        } else {
            $usageInfo = sprintf(
                $GLOBALS['TL_LANG']['tl_module']['usage_info']['characters'],
                number_format($response['character_count']),
                number_format($response['character_limit'])
            );
        }

        return "<div class='tl_info'>
            <h2>" . $GLOBALS['TL_LANG']['tl_module']['usage_info']['label'] . "</h2>
            <strong>" . $planstring . "</strong> <br>
            <strong>" . $usageInfo . "
            </strong>
        </div>";
    }

    private function fetchUsageInfo($deepl_key, $base_url)
    {
        $url = $base_url . "/v2/usage";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: DeepL-Auth-Key " . $deepl_key
        ]);

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // If forbidden, try the free endpoint
        if ($http_code == 403) {
            curl_setopt($ch, CURLOPT_URL, "https://api-free.deepl.com/v2/usage");
            $result = curl_exec($ch);
        }

        $response = json_decode($result, true);
        curl_close($ch);

        return $response;
    }

    public function getGlossaries()
    {
        $glossaries = $this->fetchGlossaries();

        $glossaries = array_reduce($glossaries, function ($carry, $item) {
            $carry[$item['glossary_id']] = $item['name'];
            return $carry;
        }, []);

        return $glossaries;
    }

    private function fetchGlossaries()
    {
        $config = new Config();
        $deepl_key = $config->getDeeplKey();
        $base_url = $config->getBaseUrl();

        if (empty($deepl_key)) {
            return [];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $base_url . "/v3/glossaries");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: DeepL-Auth-Key " . $deepl_key
        ]);

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // If forbidden, try the free endpoint
        if ($http_code == 403) {
            curl_setopt($ch, CURLOPT_URL, "https://api-free.deepl.com/v3/glossaries");
            $result = curl_exec($ch);
        }

        $response = json_decode($result, true);
        curl_close($ch);

        return $response['glossaries'] ?? [];
    }
}
