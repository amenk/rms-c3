<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2004-2014 Sven Rhinow and Leo Feyer
 *
 * @package rms
 * @link    https://www.sr-tag.de
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

/**
 * Run in a custom namespace, so the class can be replaced
 */
 namespace SvenRhinow\rms;

/**
 * Class rmsAjax
 *
 * Provide methods to handle Ajax requests.
 * @copyright  Leo Feyer 2005-2014
 * @author     Leo Feyer <https://contao.org>
 * @package    Core
 */
class rmsAjax extends \Backend
{

	/**
	 * Ajax id
	 * @var string
	 */
	protected $strAjaxId;



	/**
	 * Ajax name
	 * @var string
	 */
	protected $strAjaxName;




	/**
	 * Ajax actions that do require a data container object
	 * @param \DataContainer
	 */
	public function executePostActions($strAction, \DataContainer $dc)
	{
		header('Content-Type: text/html; charset=' . $GLOBALS['TL_CONFIG']['characterSet']);

		// Bypass any core logic for non-core drivers (see #5957)
		if (!$dc instanceof \DC_rmsTable)
		{
			exit;
		}

		switch ($strAction)
		{
			// Load nodes of the page structure tree
			case 'loadStructure':
				echo $dc->ajaxTreeView($this->strAjaxId, intval(\Input::post('level')));
				exit; break;

			// Load nodes of the file manager tree
			case 'loadFileManager':
				echo $dc->ajaxTreeView(\Input::post('folder', true), intval(\Input::post('level')));
				exit; break;

			// Load nodes of the page tree
			case 'loadPagetree':
				$arrData['strTable'] = $dc->table;
				$arrData['id'] = $this->strAjaxName ?: $dc->id;
				$arrData['name'] = \Input::post('name');

				$objWidget = new $GLOBALS['BE_FFL']['pageSelector']($arrData, $dc);
				echo $objWidget->generateAjax($this->strAjaxId, \Input::post('field'), intval(\Input::post('level')));
				exit; break;

			// Load nodes of the file tree
			case 'loadFiletree':
				$arrData['strTable'] = $dc->table;
				$arrData['id'] = $this->strAjaxName ?: $dc->id;
				$arrData['name'] = \Input::post('name');

				$objWidget = new $GLOBALS['BE_FFL']['fileSelector']($arrData, $dc);

				// Load a particular node
				if (\Input::post('folder', true) != '')
				{
					echo $objWidget->generateAjax(\Input::post('folder', true), \Input::post('field'), intval(\Input::post('level')));
				}
				else
				{
					echo $objWidget->generate();
				}
				exit; break;

			// Reload the page/file picker
			case 'reloadPagetree':
			case 'reloadFiletree':
				$intId = \Input::get('id');
				$strField = $dc->field = \Input::post('name');

				// Handle the keys in "edit multiple" mode
				if (\Input::get('act') == 'editAll')
				{
					$intId = preg_replace('/.*_([0-9a-zA-Z]+)$/', '$1', $strField);
					$strField = preg_replace('/(.*)_[0-9a-zA-Z]+$/', '$1', $strField);
				}

				// The field does not exist
				if (!isset($GLOBALS['TL_DCA'][$dc->table]['fields'][$strField]))
				{
					$this->log('Field "' . $strField . '" does not exist in DCA "' . $dc->table . '"', __METHOD__, TL_ERROR);
					header('HTTP/1.1 400 Bad Request');
					die('Bad Request');
				}

				$objRow = null;
				$varValue = null;

				// Load the value
				if ($GLOBALS['TL_DCA'][$dc->table]['config']['dataContainer'] == 'File')
				{
					$varValue = $GLOBALS['TL_CONFIG'][$strField];
				}
				elseif ($intId > 0 && $this->Database->tableExists($dc->table))
				{
					$objRow = $this->Database->prepare("SELECT * FROM " . $dc->table . " WHERE id=?")
											 ->execute($intId);

					// The record does not exist
					if ($objRow->numRows < 1)
					{
						$this->log('A record with the ID "' . $intId . '" does not exist in table "' . $dc->table . '"', __METHOD__, TL_ERROR);
						header('HTTP/1.1 400 Bad Request');
						die('Bad Request');
					}

					$varValue = $objRow->$strField;
					$dc->activeRecord = $objRow;
				}

				// Call the load_callback
				if (is_array($GLOBALS['TL_DCA'][$dc->table]['fields'][$strField]['load_callback']))
				{
					foreach ($GLOBALS['TL_DCA'][$dc->table]['fields'][$strField]['load_callback'] as $callback)
					{
						if (is_array($callback))
						{
							$this->import($callback[0]);
							$varValue = $this->$callback[0]->$callback[1]($varValue, $dc);
						}
						elseif (is_callable($callback))
						{
							$varValue = $callback($varValue, $dc);
						}
					}
				}

				// Set the new value
				$varValue = \Input::post('value', true);
				$strKey = ($this->strAction == 'reloadPagetree') ? 'pageTree' : 'fileTree';

				// Convert the selected values
				if ($varValue != '')
				{
					$varValue = trimsplit("\t", $varValue);

					// Automatically add resources to the DBAFS
					if ($strKey == 'fileTree')
					{
						foreach ($varValue as $k=>$v)
						{
							$varValue[$k] = \Dbafs::addResource($v)->uuid;
						}
					}

					$varValue = serialize($varValue);
				}

				// Build the attributes based on the "eval" array
				$arrAttribs = $GLOBALS['TL_DCA'][$dc->table]['fields'][$strField]['eval'];

				$arrAttribs['id'] = $dc->field;
				$arrAttribs['name'] = $dc->field;
				$arrAttribs['value'] = $varValue;
				$arrAttribs['strTable'] = $dc->table;
				$arrAttribs['strField'] = $strField;
				$arrAttribs['activeRecord'] = $dc->activeRecord;

				$objWidget = new $GLOBALS['BE_FFL'][$strKey]($arrAttribs);
				echo $objWidget->generate();
				exit; break;

			// Feature/unfeature an element
			case 'toggleFeatured':
				if (class_exists($dc->table, false))
				{
					$dca = new $dc->table();

					if (method_exists($dca, 'toggleFeatured'))
					{
						$dca->toggleFeatured(\Input::post('id'), ((\Input::post('state') == 1) ? true : false));
					}
				}
				exit; break;

			// Toggle subpalettes
			case 'toggleSubpalette':
				$this->import('BackendUser', 'User');

				// Check whether the field is a selector field and allowed for regular users (thanks to Fabian Mihailowitsch) (see #4427)
				if (!is_array($GLOBALS['TL_DCA'][$dc->table]['palettes']['__selector__']) || !in_array($this->Input->post('field'), $GLOBALS['TL_DCA'][$dc->table]['palettes']['__selector__']) || ($GLOBALS['TL_DCA'][$dc->table]['fields'][$this->Input->post('field')]['exclude'] && !$this->User->hasAccess($dc->table . '::' . $this->Input->post('field'), 'alexf')))
				{
					$this->log('Field "' . $this->Input->post('field') . '" is not an allowed selector field (possible SQL injection attempt)', __METHOD__, TL_ERROR);
					header('HTTP/1.1 400 Bad Request');
					die('Bad Request');
				}

				if ($dc instanceof DC_rmsTable)
				{
					if (\Input::get('act') == 'editAll')
					{
						$this->strAjaxId = preg_replace('/.*_([0-9a-zA-Z]+)$/', '$1', \Input::post('id'));
						$this->Database->prepare("UPDATE " . $dc->table . " SET " . \Input::post('field') . "='" . (intval(\Input::post('state') == 1) ? 1 : '') . "' WHERE id=?")->execute($this->strAjaxId);

						if (\Input::post('load'))
						{
							echo $dc->editAll($this->strAjaxId, \Input::post('id'));
						}
					}
					else
					{
						$this->Database->prepare("UPDATE " . $dc->table . " SET " . \Input::post('field') . "='" . (intval(\Input::post('state') == 1) ? 1 : '') . "' WHERE id=?")->execute($dc->id);

						if (\Input::post('load'))
						{
							echo $dc->edit(false, \Input::post('id'));
						}
					}
				}

			default:
				exit; break;
		}
	}

}
