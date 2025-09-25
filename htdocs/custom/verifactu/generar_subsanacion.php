<?php
/* Copyright (C) 2025 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       htdocs/custom/verifactu/xml_preview.php
 * \ingroup    verifactu
 * \brief      Vista previa del XML de Verifactu para una factura
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactuxml.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactufacturaregistro.class.php';

// Load translation files required by the page
$langs->loadLangs(array("verifactu@verifactu", "bills"));

// Get parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');

// Security check
if (empty($id)) {
	accessforbidden('ID de factura requerido');
}

// Check if user can read invoices
if (!$user->hasRight('facture', 'lire')) {
	accessforbidden();
}

// Load the invoice
$object = new Facture($db);
$result = $object->fetch($id);
if ($result <= 0) {
	dol_print_error($db, 'Factura no encontrada');
	exit;
}

// Check if invoice belongs to current entity
if ($object->entity != $conf->entity) {
	accessforbidden();
}


llxHeader("", "XML Verifactu - " . $object->ref, '');

include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/lib/verifactu.lib.php';
$result = verifactu_generar_registro_alta($object,true);
print $result;

llxFooter();
$db->close();
