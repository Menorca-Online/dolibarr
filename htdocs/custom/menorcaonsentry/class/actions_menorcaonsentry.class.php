<?php
/* Copyright (C) 2026 Menorca Online S.L
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    menorcaonsentry/class/actions_menorcaonsentry.class.php
 * \ingroup menorcaonsentry
 * \brief   Hooks for MenorcaOnSentry.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/menorcaonsentry/lib/sentry.lib.php';

/**
 * Class ActionsMenorcaonsentry
 */
class ActionsMenorcaonsentry
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error
	 */
	public $error = '';

	/**
	 * @var array<int,string> Errors
	 */
	public $errors = array();

	/**
	 * @var array<string,mixed> Hook results
	 */
	public $results = array();

	/**
	 * @var string Output printed by hooks
	 */
	public $resprints = '';

	/**
	 * @var int Hook priority. Run early so handlers are registered soon.
	 */
	public $priority = 1;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Generic hook called by many Dolibarr pages.
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param CommonObject|null   $object     Object
	 * @param string              $action     Action
	 * @param HookManager         $hookmanager Hook manager
	 * @return int
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		try {
			menorcaonSentryInit();
		} catch (Throwable $e) {
			menorcaonSentryCaptureException($e, array('module' => 'menorcaonsentry', 'operation' => 'hook_init'));
			dol_syslog('MenorcaOnSentry: hook initialization failed: '.$e->getMessage(), LOG_ERR);
		}

		return 0;
	}
}
