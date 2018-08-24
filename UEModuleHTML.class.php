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
 * For further information visit http://bluespice.com
 *
 * @author     Robert Vogel <vogel@hallowelt.com>
 * @package    BlueSpice_Extensions
 * @subpackage UEModuleHTML
 * @copyright  Copyright (C) 2016 Hallo Welt! GmbH, All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License v3
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
		$this->setHook('BSUniversalExportGetWidget');
		$this->setHook('BSUniversalExportSpecialPageExecute');
		$this->setHook('SkinTemplateOutputPageBeforeExec');
		$this->setHook('BaseTemplateToolbox');
	}

	/**
	 * Hook to insert the PDF-Export link if BlueSpiceSkin is active
	 * @param SkinTemplate $oSkin
	 * @param QuickTemplate $oTemplate
	 * @return boolean
	 */
	public function onSkinTemplateOutputPageBeforeExec( &$oSkin, &$oTemplate ) {
		$oTemplate->data['bs_export_menu'][] = $this->buildContentAction();

		return true;
	}

	/**
	 * Hook to be executed when the Vector Skin is activated to add the PDF-Export Link to the Toolbox
	 * @param SkinTemplate $oTemplate
	 * @param Array $aToolbox
	 * @return boolean
	 */
	public function onBaseTemplateToolbox( &$oTemplate, &$aToolbox ) {
		$oTitle = RequestContext::getMain()->getTitle();
		//if the BlueSpiceSkin is activated we don't need to add the Link to the Toolbox,
		//onSkinTemplateOutputPageBeforeExec will handle it
		if ( $oTemplate instanceof BsBaseTemplate || !$oTitle->isContentPage() ) {
			return true;
		}

		//if "print" is set insert pdf export afterwards
		if ( isset( $aToolbox['print'] ) ) {
			$aToolboxNew = array();
			foreach ( $aToolbox as $sKey => $aValue ) {
				$aToolboxNew[$sKey] = $aValue;
				if ( $sKey === "print" ) {
					$aToolboxNew['uemodulehtml'] = $this->buildContentAction();
				}
			}
			$aToolbox = $aToolboxNew;
		} else {
			$aToolbox['uemodulehtml'] = $this->buildContentAction();
		}

		return true;
	}


	/**
	 * Builds the ContentAction Array fort the current page
	 * @return Array The ContentAction Array
	 */
	private function buildContentAction() {
		$aCurrentQueryParams = $this->getRequest()->getValues();
		if ( isset( $aCurrentQueryParams['title'] ) ) {
			$sTitle = $aCurrentQueryParams['title'];
		} else {
			$sTitle = '';
		}
		$sSpecialPageParameter = BsCore::sanitize( $sTitle, '', BsPARAMTYPE::STRING );
		$oSpecialPage = SpecialPage::getTitleFor( 'UniversalExport', $sSpecialPageParameter );
		if ( isset( $aCurrentQueryParams['title'] ) ) {
			unset( $aCurrentQueryParams['title'] );
		}
		$aCurrentQueryParams['ue[module]'] = 'html';
		return array(
			'id' => 'bs-ta-uemodulehtml',
			'href' => $oSpecialPage->getLinkUrl( $aCurrentQueryParams ),
			'title' => wfMessage( 'bs-uemodulehtml-widgetlink-single-title' )->text(),
			'text' => wfMessage( 'bs-uemodulehtml-widgetlink-single-text' )->text(),
			'class' => 'bs-ue-export-link',
			'classes' => 'icon-file-zip bs-ue-export-link'
		);
	}

	/**
	 *
	 * @param SpecialUniversalExport $oSpecialPage
	 * @param string $sParam
	 * @param array $aModules
	 * @return true
	 */
	public function onBSUniversalExportSpecialPageExecute( $oSpecialPage, $sParam, &$aModules ) {
		$aModules['html'] = new BsExportModuleHTML();
		return true;
	}

	/**
	 * Hook-Handler method for the 'BSUniversalExportGetWidget' event.
	 * @param UniversalExport $oUniversalExport
	 * @param array $aModules
	 * @param Title $oSpecialPage
	 * @param Title $oCurrentTitle
	 * @param array $aCurrentQueryParams
	 * @return boolean
	 */
	public function onBSUniversalExportGetWidget( $oUniversalExport, &$aModules, $oSpecialPage, $oCurrentTitle, $aCurrentQueryParams ) {
		$aCurrentQueryParams['ue[module]'] = 'html';
		$aLinks = array();
		$aLinks['html-single'] = array(
			'URL'     => htmlspecialchars( $oSpecialPage->getLinkUrl( $aCurrentQueryParams ) ),
			'TITLE'   => wfMessage( 'bs-uemodulehtml-widgetlink-single-title' )->text(),
			'CLASSES' => 'bs-uemodulehtml-single',
			'TEXT'    => wfMessage( 'bs-uemodulehtml-widgetlink-single-text' )->text(),
		);

		\Hooks::run( 'BSUEModuleHTMLBeforeCreateWidget', array( $this, $oSpecialPage, &$aLinks, $aCurrentQueryParams ) );

		$oHTMLView = new ViewBaseElement();
		$oHTMLView->setAutoWrap( '<ul>###CONTENT###</ul>' );
		$oHTMLView->setTemplate( '<li><a href="{URL}" rel="nofollow" title="{TITLE}" class="{CLASSES}">{TEXT}</a></li>' );#

		foreach( $aLinks as $sKey => $aData ) {
			$oHTMLView->addData( $aData );
		}

		$aModules[] = $oHTMLView;
		return true;
	}
}
