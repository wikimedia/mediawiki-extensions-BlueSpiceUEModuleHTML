<?php
/**
 * UniversalExport HTML Module extension for BlueSpice
 *
 * Enables MediaWiki to export pages into HTML format.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * This file is part of BlueSpice MediaWiki
 * For further information visit https://bluespice.com
 *
 * @author     Robert Vogel <vogel@hallowelt.com>
 * @package    BlueSpice_Extensions
 * @subpackage UEModuleHTML
 * @copyright  Copyright (C) 2016 Hallo Welt! GmbH, All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GPL-3.0-only
 * @filesource
 */

/**
 * Base class for UniversalExport HTML Module extension
 * @package BlueSpice_Extensions
 * @subpackage UEModuleHTML
 */
class UEModuleHTML extends BsExtensionMW {

	/**
	 * Initialization of UEModuleHTML extension
	 */
	protected function initExt() {
		$this->setHook( 'BSUniversalExportSpecialPageExecute' );
		$this->setHook(
			'ChameleonSkinTemplateOutputPageBeforeExec',
			'onSkinTemplateOutputPageBeforeExec'
		);
	}

	/**
	 * Hook to insert the PDF-Export link if BlueSpiceSkin is active
	 * @param SkinTemplate &$skin
	 * @param QuickTemplate &$template
	 * @return bool
	 */
	public function onSkinTemplateOutputPageBeforeExec( &$skin, &$template ) {
		if ( $skin->getTitle()->isContentPage() === false ) {
			return true;
		}

		$template->data['bs_export_menu'][] = $this->buildContentAction();

		return true;
	}

	/**
	 * Builds the ContentAction Array fort the current page
	 * @return array The ContentAction Array
	 */
	private function buildContentAction() {
		$currentQueryParams = $this->getRequest()->getValues();
		if ( isset( $currentQueryParams['title'] ) ) {
			$title = $currentQueryParams['title'];
		} else {
			$title = '';
		}
		$specialPageParameter = BsCore::sanitize( $title, '', BsPARAMTYPE::STRING );
		$specialPage = SpecialPage::getTitleFor( 'UniversalExport', $specialPageParameter );
		if ( isset( $currentQueryParams['title'] ) ) {
			unset( $currentQueryParams['title'] );
		}
		$currentQueryParams['ue[module]'] = 'html';

		return [
			'id' => 'bs-ta-uemodulehtml',
			'href' => $specialPage->getLinkUrl( $currentQueryParams ),
			'title' => wfMessage( 'bs-uemodulehtml-widgetlink-single-title' )->text(),
			'text' => wfMessage( 'bs-uemodulehtml-widgetlink-single-text' )->text(),
			'class' => 'bs-ue-export-link',
			'iconClass' => 'icon-file-zip bs-ue-export-link'
		];
	}

	/**
	 *
	 * @param SpecialUniversalExport $specialPage
	 * @param string $param
	 * @param array &$modules
	 * @return true
	 */
	public function onBSUniversalExportSpecialPageExecute( $specialPage, $param, &$modules ) {
		$modules['html'] = new ExportModuleHTML();
		return true;
	}
}
