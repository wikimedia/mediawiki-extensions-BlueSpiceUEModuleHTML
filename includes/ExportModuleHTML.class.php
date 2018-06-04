<?php
/**
 * BsExportModuleHTML.
 *
 * Part of BlueSpice MediaWiki
 *
 * @author     Robert Vogel <vogel@hallowelt.com>
 * @package    BlueSpice_Extensions
 * @subpackage UEModuleHTML
 * @copyright  Copyright (C) 2016 Hallo Welt! GmbH, All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License v3
 * @filesource
 */

/**
 * UniversalExport BsExportModuleHTML class.
 * @package BlueSpice_Extensions
 * @subpackage UEModuleHTML
 */
class BsExportModuleHTML implements BsUniversalExportModule {

	/**
	 * Implementation of BsUniversalExportModule interface. Uses the
	 * Java library xhtmlrenderer to create a HTML file.
	 * @param SpecialUniversalExport $oCaller
	 * @return array array( 'mime-type' => 'application/zip', 'filename' => 'Filename.zip', 'content' => '8F3BC3025A7...' );
	 */
	public function createExportFile( &$oCaller ) {
		global $wgUser, $wgRequest;
		$aPageParams = $oCaller->aParams;

		$config = \BlueSpice\Services::getInstance()->getConfigFactory()
			->makeConfig( 'bsg' );

		$aPageParams['title']      = $oCaller->oRequestedTitle->getPrefixedText();
		$aPageParams['article-id'] = $oCaller->oRequestedTitle->getArticleID();
		$aPageParams['oldid']      = $wgRequest->getInt( 'oldid', 0 );
		if( $config->get( 'UEModuleHTMLSuppressNS' ) ) {
			$aPageParams['display-title'] = $oCaller->oRequestedTitle->getText();
		}
		//If we are in history mode and we are relative to an oldid
		$aPageParams['direction'] = $wgRequest->getVal('direction', '');
		if( !empty( $aPageParams['direction'] ) ) {
			$oCurrentRevision = Revision::newFromId( $aPageParams['oldid'] );
			switch( $aPageParams['direction'] ) {
				case 'next': $oCurrentRevision = $oCurrentRevision->getNext();
					break;
				case 'prev': $oCurrentRevision = $oCurrentRevision->getPrevious();
					break;
				default: break;
			}
			if( $oCurrentRevision !== null ) {
				$aPageParams['oldid'] = $oCurrentRevision->getId();
			}
		}

		//Get Page DOM
		$aPage = BsPDFPageProvider::getPage( $aPageParams );

		//Prepare Template
		$aTemplateParams = array(
			'path'     => $config->get( 'UEModulePDFTemplatePath' ),
			'template' => $config->get( 'UEModulePDFDefaultTemplate' ),
			'language' => $wgUser->getOption( 'language', 'en' ),
			'meta'     => $aPage['meta']
		);

		//Override template param if needed. The override may come from GET (&ue[template]=...) or from a tag (<bs:ueparams template="..." />)
		//TODO: Make more generic
		if(!empty( $oCaller->aParams['template'] ) ) {
			$aTemplateParams['template'] = $oCaller->aParams['template'];
		}

		//TODO: decouple from UEModulePDF
		$aTemplate = BsPDFTemplateProvider::getTemplate( $aTemplateParams );

		//Combine Page Contents and Template
		$oDOM = $aTemplate['dom'];

		//Add the bookmarks
		$aTemplate['bookmarks-element']->appendChild(
			$aTemplate['dom']->importNode( $aPage['bookmark-element'], true )
		);
		$aTemplate['title-element']->nodeValue = $oCaller->oRequestedTitle->getPrefixedText();

		$aContents = array(
			'content' => array( $aPage['dom']->documentElement )
		);
		wfRunHooks( 'BSUEModuleHTMLBeforeAddingContent', array( &$aTemplate, &$aContents ) );

		$oContentTags = $oDOM->getElementsByTagName( 'content' );
		$i = $oContentTags->length - 1;
		while( $i > -1 ){
			$oContentTag = $oContentTags->item($i);
			$sKey = $oContentTag->getAttribute('key');
			if( isset($aContents[$sKey] ) ) {
				foreach($aContents[$sKey] as $oNode ) {
					$oNode = $oDOM->importNode( $oNode, true );
					$oContentTag->parentNode->insertBefore( $oNode, $oContentTag );
				}
			}
			$oContentTag->parentNode->removeChild($oContentTag);
			$i--;
		}

		$oCaller->aParams['document-token'] = md5( $oCaller->oRequestedTitle->getPrefixedText() ).'-'.$oCaller->aParams['oldid'];
		$oCaller->aParams['title'] = $oCaller->oRequestedTitle->getText();
		$oCaller->aParams['resources']      = $aTemplate['resources'];

		wfRunHooks( 'BSUEModuleHTMLBeforeCreateHTML', array( $this, $oDOM, $oCaller ) );

		//Prepare response
		$aResponse = array(
			'mime-type' => 'application/zip',
			'filename'  => '%s.zip',
			'content'   => ''
		);

		if ( RequestContext::getMain()->getRequest()->getVal( 'debugformat', '' ) == 'html' ) {
			$aResponse['content'] = $oDOM->saveXML( $oDOM->documentElement );
			$aResponse['mime-type'] = 'text/html';
			$aResponse['filename'] = sprintf(
				'%s.html',
				$oCaller->oRequestedTitle->getPrefixedText()
			);
			$aResponse['disposition'] = 'inline';
			return $aResponse;
		}

		$this->modifyPDFSpecificStuff( $oCaller->aParams, $oDOM );

		$oHTMLArchiver = new BsHTMLArchiver( $oCaller->aParams );
		$aResponse['content'] = $oHTMLArchiver->createHTML( $oDOM );

		$aResponse['filename'] = sprintf(
			$aResponse['filename'],
			$oCaller->oRequestedTitle->getPrefixedText()
		);

		return $aResponse;
	}

	/**
	 * Implementation of BsUniversalExportModule interface. Creates an overview
	 * over the PdfExportModule
	 * @return ViewExportModuleOverview
	 */
	public function getOverview() {
		$oModuleOverviewView = new ViewExportModuleOverview();

		$oModuleOverviewView->setOption( 'module-title', wfMessage( 'bs-uemodulehtml-overview-title' )->plain() );
		$oModuleOverviewView->setOption( 'module-description', wfMessage( 'bs-uemodulehtml-overview-description' )->plain() );
		$oModuleOverviewView->setOption( 'module-bodycontent', '' );

		return $oModuleOverviewView;
	}

	/**
	 * This is really ugly! As we use the same templates as UEModulePDF we have
	 * to change some stuff, like embedded fonts and running elements...
	 * @param $oDOM DOMDocument
	 */
	public function modifyPDFSpecificStuff( &$aParams, $oDOM ) {

		//Remove fonts
		foreach($aParams['resources']['STYLESHEET'] as $sKey => $sValue ) {
			if( strtoupper(substr($aParams['resources']['STYLESHEET'][$sKey], -3) ) == 'TTF' ) {
				unset( $aParams['resources']['STYLESHEET'][$sKey] );
			}
		}

		//Remove <bookmarks>
		$oBookmarksElement = $oDOM->getElementsByTagName('bookmarks')->item(0);
		$oBookmarksElement->parentNode->removeChild( $oBookmarksElement );

		$oDOMXPath = new DOMXPath( $oDOM );
		$oRunningElements = $oDOMXPath->query(
			"//*[starts-with(@id, 'bs-running')] | //*[contains(@class, 'bs-running')]"
		);
		$aRunningElements = array();
		foreach( $oRunningElements as $oRElem ) { $aRunningElements[] = $oRElem; }
		foreach( $aRunningElements as $oRElem ) {
			$oRElem->parentNode->removeChild( $oRElem );
		}
	}
}
