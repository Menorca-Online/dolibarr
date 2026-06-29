<?php
/* Copyright (C) 2026 Menorca Online S.L
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Prepare admin pages header.
 *
 * @return array<array{string,string,string}>
 */
function menorcaonsentryAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load('menorcaonsentry@menorcaonsentry');

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/menorcaonsentry/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('Settings');
	$head[$h][2] = 'settings';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'menorcaonsentry@menorcaonsentry');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'menorcaonsentry@menorcaonsentry', 'remove');

	return $head;
}
