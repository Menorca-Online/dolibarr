<?php
/* Copyright (C) 2026 Menorca Online S.L
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \defgroup   menorcaonsentry Module MenorcaOnSentry
 * \brief      Technical Sentry integration for Dolibarr.
 *
 * \file       htdocs/custom/menorcaonsentry/core/modules/modMenorcaOnSentry.class.php
 * \ingroup    menorcaonsentry
 * \brief      Descriptor for MenorcaOnSentry module.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module MenorcaOnSentry.
 */
class modMenorcaOnSentry extends DolibarrModules
{
	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;

		// Custom module id. Reserve/change this number if it collides in your installation.
		$this->numero = 504220;
		$this->rights_class = 'menorcaonsentry';
		$this->family = 'technic';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'ModuleMenorcaOnSentryDesc';
		$this->descriptionlong = 'ModuleMenorcaOnSentryDesc';
		$this->editor_name = 'Menorca Online S.L';
		$this->editor_url = '';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-bug';

		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'printing' => 0,
			'theme' => 0,
			'css' => array(),
			'js' => array(),
			'hooks' => array('all'),
			'moduleforexternal' => 0,
			'websitetemplates' => 0,
			'captcha' => 0,
		);

		$this->dirs = array();
		$this->config_page_url = array('setup.php@menorcaonsentry');
		$this->hidden = getDolGlobalInt('MODULE_MENORCAONSENTRY_DISABLED');
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('menorcaonsentry@menorcaonsentry');
		$this->phpmin = array(8, 3);
		$this->need_dolibarr_version = array(22, 0);
		$this->need_javascript_ajax = 0;
		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		$this->const = array(
			array('MENORCAON_SENTRY_ENVIRONMENT', 'chaine', 'production', 'Sentry environment', 0, 'current', 0),
			array('MENORCAON_SENTRY_PROJECT', 'chaine', 'dolibarr', 'Sentry project tag', 0, 'current', 0),
			array('MENORCAON_SENTRY_APPLICATION', 'chaine', 'erp', 'Sentry application tag', 0, 'current', 0),
		);

		if (!isModEnabled('menorcaonsentry')) {
			$conf->menorcaonsentry = new stdClass();
			$conf->menorcaonsentry->enabled = 0;
		}

		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();
		$this->rights = array();
		$this->menu = array();
		$this->export_code = array();
		$this->export_label = array();
		$this->export_icon = array();
		$this->export_permission = array();
		$this->export_fields_array = array();
		$this->export_TypeFields_array = array();
		$this->export_entities_array = array();
		$this->import_code = array();
		$this->import_label = array();
		$this->import_icon = array();
		$this->import_tables_array = array();
		$this->import_tables_creator_array = array();
		$this->import_fields_array = array();
		$this->import_fieldshidden_array = array();
		$this->import_regex_array = array();
		$this->import_examplevalues_array = array();
		$this->import_updatekeys_array = array();
		$this->import_convertvalue_array = array();
		$this->import_run_sql_after_array = array();
	}

	/**
	 * Function called when module is enabled.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function init($options = '')
	{
		$sql = array();
		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
