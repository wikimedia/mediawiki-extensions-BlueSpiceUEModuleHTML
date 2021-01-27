<?php

namespace BlueSpice\UEModuleHTML\Hook\ChameleonSkinTemplateOutputPageBeforeExec;

use BlueSpice\Hook\ChameleonSkinTemplateOutputPageBeforeExec;
use BlueSpice\UniversalExport\ModuleFactory;

class AddWidget extends ChameleonSkinTemplateOutputPageBeforeExec {
	/**
	 *
	 * @return bool
	 */
	protected function skipProcessing() {
		if ( !$this->skin->getTitle()->isContentPage() ) {
			return true;
		}
		return !$this->getServices()->getSpecialPageFactory()->exists( 'UniversalExport' );
	}

	protected function doProcess() {
		/** @var ModuleFactory $moduleFactory */
		$moduleFactory = $this->getServices()->getService(
			'BSUniversalExportModuleFactory'
		);
		$module = $moduleFactory->newFromName( 'html' );

		$contentActions = [
			'id' => 'bs-ta-uemodulehtml',
			'href' => $module->getExportLink( $this->getContext()->getRequest() ),
			'title' => $this->msg( 'bs-uemodulehtml-widgetlink-single-title' )->plain(),
			'text' => $this->msg( 'bs-uemodulehtml-widgetlink-single-text' )->plain(),
			'class' => 'bs-ue-export-link',
			'iconClass' => 'icon-file-zip bs-ue-export-link'
		];

		$this->template->data['bs_export_menu'][] = $contentActions;
	}

}
