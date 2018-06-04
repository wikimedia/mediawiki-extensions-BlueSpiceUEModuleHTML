<?php
//TODO: decouple from UEModulePDF
class BsHTMLArchiver extends BsPDFServlet {

	public function __construct( $aParams ) {
		$this->aParams = $aParams;
		$this->aFiles =  isset($aParams['resources']) ? $aParams['resources'] : array();
	}

	public function createPDF(&$oHtmlDOM) {
		return $this->createHTML($oHtmlDOM);
	}

	/**
	 * Gets a DOMDocument, searches it for files and creates a ZIP with files and markup.
	 * @param DOMDocument $oHtmlDOM The source markup
	 * @return string The resulting Zip archive as bytes
	 */
	public function createHTML(&$oHtmlDOM) {

		$this->findFiles( $oHtmlDOM );

		//Save temporary
		$oStatus = BsFileSystemHelper::ensureCacheDirectory('UEModuleHTML');
		$sTmpZipFile = $oStatus->getValue().DS.$this->aParams['document-token'].'.zip';

		$oZip = new ZipArchive();
		$oZip->open( $sTmpZipFile, ZipArchive::CREATE );
		//TODO: Find solution for encoding issue:
		//https://github.com/owncloud/core/pull/1117
		//http://stackoverflow.com/questions/20600843/php-ziparchive-non-english-filenames-return-funky-filenames-within-archive

		foreach( $this->aFiles as $sType => $aFiles ) {

			//Backwards compatibility to old inconsitent PDFTemplates (having "STYLESHEET" as type but linnking to "stylesheets")
			//TODO: Make conditional?
			if( $sType == 'IMAGE' )      $sType = 'images';
			if( $sType == 'STYLESHEET' ) $sType = 'stylesheets';
			$sAssetDir = $this->aParams['title'].'/'.$sType;

			$oZip->addEmptyDir( $sAssetDir );

			foreach( $aFiles as $sFileName => $sFilePath ) {
				if( file_exists( $sFilePath) == false ) {
					$aErrors[] = $sFilePath;
					continue;
				}
				$oZip->addFile( $sFilePath, $sAssetDir.'/'.$sFileName );
			}

			if( !empty( $aErrors ) ) {
				wfDebugLog(
					'BS::UEModuleHTML',
					'BsHTMLArchiver::createHTML: Error trying to fetch files:'."\n". var_export( $aErrors, true )
				);
			}
		}
		wfRunHooks( 'BSUEModuleHTMLCreateHTMLBeforeSend', array( $this, &$aOptions, $oHtmlDOM ) );
		//HINT: http://www.php.net/manual/en/class.domdocument.php#96055
		//But: Formated Output is evil because is will destroy formatting in <pre> Tags!
		$oZip->addFromString($this->aParams['title'].'/index.html', $oHtmlDOM->saveHTML() );
		$oZip->close();

		$vPdfByteArray = file_get_contents( $sTmpZipFile );

		if( $vPdfByteArray == false ) {
			wfDebugLog(
				'BS::UEModulePDF',
				'BsPDFServlet::createPDF: Failed creating "'.$this->aParams['document-token'].'"'
			);
		}

		$config = \BlueSpice\Services::getInstance()->getConfigFactory()
			->makeConfig( 'bsg' );

		//Remove temporary file
		if( !$config->get( 'TestMode' ) ) {
			unlink( $sTmpZipFile );
		}

		return $vPdfByteArray;
	}
}
