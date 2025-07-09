<?php

use JBSupport\ContaoDeeplInstantTranslationBundle\Settings;

$GLOBALS['TL_DCA']['tl_module']['fields']['deepl_key'] = [
    'exclude'                 => true,
    'inputType'               => 'textStore',
    'eval'                    => array('maxlength' => 255, 'tl_class' => 'w50', 'doNotSaveEmpty' => true, 'unique' => true),
    'sql'                     => "varchar(255) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['original_language'] = [
    'exclude'                 => true,
    'inputType'               => 'select',
    'options_callback'        => array(Settings::class, 'getLanguageTranslatedStrings'),
    'eval'                    => array('tl_class' => 'w50', 'chosen' => true, 'mandatory' => true),
    'sql'                     => "varchar(5) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['languages'] = [
    'exclude'                 => true,
    'inputType'               => 'checkboxWizard',
    'eval'                    => array('multiple' => true, 'tl_class' => 'clr w50', 'mandatory' => true),
    'options_callback'        => array(Settings::class, 'getLanguageTranslatedStrings'),
    'sql'                     => "TEXT NULL"
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

$GLOBALS['TL_DCA']['tl_module']['palettes']['language_switcher_module'] =
    '{title_legend},name, type, deepl_key, original_language, languages, show_modal; {usage_legend},usage_info';

class translation_module
{
    public function getUsageInfo($dc)
    {
        $deepl_key = $dc->activeRecord->deepl_key;

        if (empty($deepl_key)) {
            return '<div class="tl_info">' . $GLOBALS['TL_LANG']['tl_module']['usage_info']['no_key'] . '</div>';
        }

        $response = $this->fetchUsageInfo($deepl_key);

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
        $class = 'tl_info';

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
            if ($response['character_count'] == $response['character_limit']) {
                $class = 'tl_error';
            }

            $usageInfo = sprintf(
                $GLOBALS['TL_LANG']['tl_module']['usage_info']['characters'],
                number_format($response['character_count']),
                number_format($response['character_limit'])
            );
        }

        return "<div class='$class'>
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
