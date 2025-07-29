<?php

use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\Config;
use JBSupport\ContaoDeeplInstantTranslationBundle\Settings;

$GLOBALS['TL_DCA']['tl_module']['fields']['deepl_key'] = [
    'exclude'                 => true,
    'inputType'               => 'text',
    'eval'                    => array('maxlength' => 255, 'tl_class' => 'clr w50', 'doNotSaveEmpty' => true),
    'sql'                     => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['config']['onsubmit_callback'][] = array('translation_module', 'writeConfig');

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
    'eval'                    => array('multiple' => true, 'tl_class' => 'clr w50', 'mandatory' => true),
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
    'exclude' => false,
    'inputType' => 'custom',
    'eval' => ['tl_class' => 'clr', 'doNotSaveEmpty' => true],
    'input_field_callback' => ['translation_module', 'getUsageInfo'],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['in_url'] = [
    'exclude'                 => true,
    'inputType'               => 'checkbox',
    'eval'                    => ['tl_class' => 'w50'],
    'sql'                     => "TINYINT(1) NULL default '1'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['element_type'] = [
    'exclude' => false,
    'inputType' => 'select',
    'options' => ['select' => 'Select', 'radio' => 'Radio', 'buttons' => 'Buttons'],
    'eval' => [
        'tl_class' => 'w50',
        'chosen' => true,
    ],
    'sql' => "varchar(10) NOT NULL default 'select'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['deepl_pro_plan'] = [
    'exclude'                 => true,
    'inputType'               => 'checkbox',
    'eval'                    => array('tl_class' => 'w50'),
    'sql'                     => "TINYINT(1) NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['element_label_type'] = [
    'exclude' => true,
    'inputType' => 'radio',
    'options' => [
        'short' => 'Short',
        'long' => 'Long'
    ],
    'options_callback' => [Settings::class, 'getElementLabelTypes'],
    'eval' => [
        'tl_class' => 'w50',
    ],
    'sql' => "varchar(10) NOT NULL default 'long'",
];

$GLOBALS['TL_DCA']['tl_module']['palettes']['language_switcher_module'] =
    '{title_legend},name, type, deepl_key, original_language,  in_url, languages; {look_legend}, element_type, show_modal, element_label_type; {usage_legend},usage_info';

class translation_module
{
    public function writeConfig($dc)
    {
        $fields = ["deepl_key", "deepl_pro_plan", "original_language", "languages", "in_url", "element_type", "show_modal", "element_label_type"];
        $configObj = new Config();
        $configPath = $configObj->getConfigPath();
        $existingConfig = file_exists($configPath) ? @include($configPath) : [];
        $db = \Contao\Database::getInstance();

        $config = [];
        foreach ($fields as $field) {
            if (isset($dc->activeRecord->{$field})) {
                if ($field == 'deepl_key') {
                    if (empty($dc->activeRecord->{$field}) && $existingConfig[$field]) {
                        $config[$field] = $existingConfig[$field] ?? '';
                        $db->query("UPDATE tl_module SET " . $field . "='' WHERE id=" . $dc->activeRecord->id);
                    } else if (empty($dc->activeRecord->{$field}) && !$existingConfig[$field]) {
                        $config[$field] = '';
                        $db->query("UPDATE tl_module SET " . $field . "='' WHERE id=" . $dc->activeRecord->id);
                    } else {
                        $config[$field] = $dc->activeRecord->{$field};
                        $db->query("UPDATE tl_module SET " . $field . "='' WHERE id=" . $dc->activeRecord->id);
                    }
                    continue;
                }

                $config[$field] = is_numeric($dc->activeRecord->{$field}) ? (bool) $dc->activeRecord->{$field} : $dc->activeRecord->{$field};
            } else {
                $config[$field] = '';
            }
        }


        $content = "<?php\nreturn " . var_export($config, true) . ";";

        file_put_contents($configPath, $content);

        return '';
    }

    public function getUsageInfo($dc)
    {
        $config = new Config();
        $deepl_key = $config->getDeeplKey();

        if (empty($deepl_key)) {
            return '<div class="tl_info">' . $GLOBALS['TL_LANG']['tl_module']['usage_info']['no_key'] . '</div>';
        }

        $response = $this->fetchUsageInfo($deepl_key);

        $pro_plan = $response['plan'] == "API Pro" ? true : false;

        if ($pro_plan != (bool) $dc->activeRecord->deepl_pro_plan) {
            $dc->activeRecord->deepl_pro_plan = $pro_plan;
        }

        return $this->generateUsageString($response);
    }

    private function generateUsageString($response)
    {
        $plan = $response['plan'];

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

    private function fetchUsageInfo($deepl_key)
    {
        $pro_plan = true;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.deepl.com/v2/usage");
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
            $pro_plan = false;
        }

        $response = json_decode($result, true);
        $response['plan'] = $pro_plan ? 'API Pro' : 'API Free';
        curl_close($ch);

        return $response ?? "Error fetching usage info. Please check your API key.";
    }
}
