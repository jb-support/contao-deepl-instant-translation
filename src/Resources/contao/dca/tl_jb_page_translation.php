<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

use Contao\Image;
use Contao\Input;
use Contao\Backend;
use Contao\DC_Table;
use Contao\PageModel;
use Contao\Controller;
use Contao\DataContainer;
use JBSupport\ContaoDeeplInstantTranslationBundle\Settings;
use JBSupport\ContaoDeeplInstantTranslationBundle\Controller\TranslationController;

$GLOBALS['TL_DCA']['tl_jb_page_translation'] = array(
	// Config
	'config' => array(
		'dataContainer'               => DC_Table::class,
		'switchToEdit'                => true,
		'enableVersioning'            => true,
		'sql' => array(
			'keys' => array(
				'id' => 'primary',
				'hash,language' => 'index'
			)
		),
		'notCreatable' => true,
		'onload_callback' => array(array('tl_jb_page_translation', 'handleTranslate')),
	),

	// List
	'list' => array(
		'sorting' => array(
			'mode'                    => 2,
			'panelLayout'             => 'filter;sort,search,limit',
			'fields'                  => array('pid', 'original_string', 'translated_string', 'language'),
			'defaultSearchField'	  => 'textarea'
		),
		'label' => array(
			'fields'                  => array('pid', 'original_string', 'translated_string', 'language'),
			'showColumns' 			  => true,
			'label_callback' 		  => array('tl_jb_page_translation', 'renderPlainLabel'),
		),
		'global_operations' => array(
			'all' => [
				'label' => &$GLOBALS['TL_LANG']['MSC']['all'],
				'href'  => 'act=select',
				'class' => 'header_edit_all',
				'attributes' => 'onclick="Backend.getScrollOffset()"'
			]
		),
		'operations' => array(
			'edit' => array(
				'href'                => 'act=edit',
				'icon'                => 'edit.svg',
			),
			'translateString' => array(
				'label' => &$GLOBALS['TL_LANG']['tl_jb_page_translation']['translateString'],
				'href' => 'key=translateString',
				'icon' => 'bundles/jbsupportcontaodeeplinstanttranslation/deepl.svg',
				'button_callback' => array('tl_jb_page_translation', 'deeplIcon'),
			),
			'delete' => array(
				'href'                => 'act=delete',
				'icon'                => 'delete.svg',
				'attributes'          => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null) . '\'))return false;Backend.getScrollOffset()"',
			),
		),
	),

	// Select
	'select' => array(),

	// Palettes
	'palettes' => array(
		'default' 					  => '{general_legend},alias, hash, translated, tstamp;',
	),
	// Fields
	'fields' => array(
		'id' => array(
			'sql'                     => "int(10) unsigned NOT NULL auto_increment"
		),
		'tstamp' => array(
			'sql'                     => "int(10) unsigned NOT NULL default 0"
		),
		'alias' => array(
			'filter' 				  => true,
			'inputType'               => 'select',
			'eval'                    => array('tl_class' => 'w50'),
			'sql'                     => "varchar(255) NULL default NULL",
		),
		'alias_translation' => array(
			'sql'                     => "varchar(255) NULL default NULL",
		),
		'hash' => array(
			'inputType'               => 'text',
			'eval'                    => array('unique' => true, 'disabled' => true, 'tl_class' => 'w50'),
			'sql'                     => "VARCHAR(32) NULL default NULL"
		),
		'language' => array(
			'filter' 				  => true,
			'inputType'               => 'select',
			'eval'                    => array('tl_class' => 'w50'),
			'options_callback'        => function () {
				return Settings::getTranslatedLanguagesArray();
			},
			'sql'                     => "VARCHAR(2) NULL default NULL"
		),
		'translated' => array(
			'sql'                     => "char(1) NOT NULL default ''"
		),
	)
);

class tl_jb_page_translation extends Backend
{
	public function handleTranslate()
	{
		$id = Input::get('id');

		if (Input::get('key') !== 'translateString' || !$id) {
			return;
		}

		TranslationController::forceTranslate($id);

		Controller::redirect('/contao?do=translation');
	}

	public function renderPlainLabel($row, $label, DataContainer $dc = null, $args = null)
	{
		$pageModel = PageModel::findById($row['pid']);
		$url = '';

		if ($pageModel) {
			try {
				$url = $pageModel->getFrontendUrl();
			} catch (\Exception $e) {
				$url = '';
			}
		}

		$row['pid'] = $pageModel ? "<a href='" . $url . "' target='_blank'>" . $pageModel->title . "</a>" : '-';
		$row['original_string'] = $this->truncateString($row['original_string'], 10);
		$row['translated_string'] = $this->truncateString($row['translated_string'], 10);
		$row['language'] = Settings::getTranslatedLanguageOnly($row['language']);

		return [$row["pid"], $row['original_string'], $row['translated_string'], $row['language']];
	}

	private function truncateString($string, $length = 10)
	{
		$words = preg_split('/\s+/', strip_tags($string));
		if (count($words) > $length) {
			return implode(' ', array_slice($words, 0, $length)) . '...';
		} else {
			return implode(' ', $words);
		}
	}


	public function deeplIcon($row, $href, $label, $title, $icon, $dc)
	{
		$length = strlen(strip_tags($row['original_string']));
		$lengthString = sprintf(
			$GLOBALS['TL_LANG']['tl_jb_page_translation']['usage_alert'],
			$length
		);

		$confirmJs = sprintf(
			'onclick="return confirm(\'%s\');"',
			$lengthString
		);

		$imagehtml = Image::getHtml($icon, $label, 'class="header_deepl_icon" style="width: 16px; height: 16px;margin-left: -2px; margin-right: 2px;"');
		return '<a href="' . $this->addToUrl($href . '&id=' . $row['id']) . '" title="' . htmlspecialchars($label) . '" class="header_deepl_icon" ' . $confirmJs . '>' . $imagehtml . '</a>';
	}
}
