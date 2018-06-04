<?php

namespace BlueSpice\UEModuleHTML\ConfigDefinition;

use BlueSpice\ConfigDefinition\BooleanSetting;

class SuppressNS extends BooleanSetting {

	public function getPaths() {
		return [
			static::MAIN_PATH_FEATURE . '/' . static::FEATURE_EXPORT . '/UEModuleHTML',
			static::MAIN_PATH_EXTENSION . '/UEModuleHTML/' . static::FEATURE_EXPORT,
			static::MAIN_PATH_PACKAGE . '/' . static::PACKAGE_PRO . '/UEModuleHTML',
		];
	}

	public function getLabelMessageKey() {
		return 'bs-uemodulehtml-pref-SuppressNS';
	}
}
