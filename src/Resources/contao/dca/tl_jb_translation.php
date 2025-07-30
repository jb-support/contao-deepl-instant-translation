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
use JBSupport\ContaoDeeplInstantTranslationBundle\Controller\TranslationController;

$GLOBALS['TL_DCA']['tl_jb_translation'] = array(
	// Config
	'config' => array(
		'dataContainer'               => DC_Table::class,
		'switchToEdit'                => true,
		'enableVersioning'            => true,
		'sql' => array(
			'keys' => array(
				'id' => 'primary',
				'hash,language,pid' => 'index'
			)
		),
		'onload_callback' => array(array('tl_jb_translation', 'handleTranslate')),
	),

	// List
	'list' => array(
		'sorting' => array(
			'mode'                    => 2,
			'panelLayout'             => 'filter;search',
			'fields'                  => array('pid', 'original_string', 'translated_string', 'language'),
			'defaultSearchField'	  => 'textarea'
		),
		'label' => array(
			'fields'                  => array('pid', 'original_string', 'translated_string', 'language'),
			'showColumns' 			  => true,
			'label_callback' 		  => array('tl_jb_translation', 'renderPlainLabel'),
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
				'label' => &$GLOBALS['TL_LANG']['tl_jb_translation']['translateString'],
				'href' => 'key=translateString',
				'icon' => 'bundles/jbsupportcontaodeeplinstanttranslation/deepl.svg',
				'button_callback' => array('tl_jb_translation', 'deeplIcon'),
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
		'default'                     => '{general_legend},original_string,translated_string;',
	),
	// Fields
	'fields' => array(
		'id' => array(
			'sql'                     => "int(10) unsigned NOT NULL auto_increment"
		),
		'tstamp' => array(
			'sql'                     => "int(10) unsigned NOT NULL default 0"
		),
		'hash' => array(
			'sql'                     => "VARCHAR(32) NULL default NULL"
		),
		'language' => array(
			'filter' 				  => true,
			'sql'                     => "VARCHAR(2) NULL default NULL"
		),
		'pid' => array(
			'filter' 				  => true,
			'inputType'               => 'select',
			'foreignKey'              => 'tl_page.title',
			'eval' 				      => array('includeBlankOption' => true, 'tl_class' => 'w50', 'disabled' => true),
			'relation'                => array('type' => 'hasOne', 'load' => 'lazy'),
			'sql'                     => "int(10) unsigned NULL default NULL"
		),
		'original_string' => array(
			'search'                  => true,
			'inputType'               => 'textarea',
			'eval' 				      => array('disabled' => true, 'allowHtml' => true, 'preserveTags' => true, 'tl_class' => 'clr'),
			'sql'                     => "text NULL default NULL",
		),
		'translated_string' => array(
			'search' 				  => true,
			'inputType'               => 'textarea',
			'eval' 				      => array('mandatory' => true, 'decodeEntities' => false, 'allowHtml' => true, 'tl_class' => 'clr'),
			'sql'                     => "text NULL default NULL"
		)
	)
);

class tl_jb_translation extends Backend
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

	public function getHtmlString($dc)
	{
		$original_html = htmlspecialchars($dc->value);

		return "<div class=\"clr widget\">
		<h3>" . $GLOBALS['TL_LANG']['tl_jb_translation']['original_html'][0] . "</h3>
		<p>" . $original_html . "</p>
		</div>";
	}

	public function getRenderedString($dc)
	{
		return "<div class=\"clr widget\">
		<h3>" . $GLOBALS['TL_LANG']['tl_jb_translation']['original_html'][1] . "</h3>
		" . $dc->activeRecord->original_string . "
		</div>";
	}

	public function deeplIcon($row, $href, $label, $title, $icon, $dc)
	{
		$length = strlen($row['original_string']);
		$confirmJs = "onclick=\"return confirm('This action will use {$length} characters from your DeepL API quota. Are you sure you want to continue?');\"";
		$imagehtml = Image::getHtml($icon, $label, 'class="header_deepl_icon" style="width: 16px; height: 16px;margin-left: -2px; margin-right: 2px;"');
		return '<a href="' . $this->addToUrl($href . '&id=' . $row['id']) . '" title="' . htmlspecialchars($label) . '" class="header_deepl_icon" ' . $confirmJs . '>' . $imagehtml . '</a>';
	}
}
