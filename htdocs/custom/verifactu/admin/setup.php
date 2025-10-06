<?php
/* Copyright (C) 2004-2017  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2025		SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    verifactu/admin/setup.php
 * \ingroup verifactu
 * \brief   Verifactu setup page.
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
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/verifactu.lib.php';
//require_once "../class/myclass.class.php";

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Translations
$langs->loadLangs(array("admin", "verifactu@verifactu"));

// Initialize a technical object to manage hooks of page. Note that conf->hooks_modules contains an array of hook context
/** @var HookManager $hookmanager */
$hookmanager->initHooks(array('verifactusetup', 'globalsetup'));

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');	// Used by actions_setmoduleoptions.inc.php

$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');
$type = 'myobject';

$error = 0;
$setupnotempty = 0;

// Access control
if (!$user->admin) {
	accessforbidden();
}


// Set this to 1 to use the factory to manage constants. Warning, the generated module will be compatible with version v15+ only
$useFormSetup = 1;

if (!class_exists('FormSetup')) {
	require_once DOL_DOCUMENT_ROOT . '/core/class/html.formsetup.class.php';
}
$formSetup = new FormSetup($db);

// Access control
if (!$user->admin) {
	accessforbidden();
}


// Enter here all parameters in your setup page

// // Setup conf for selection of an URL
// $item = $formSetup->newItem('VERIFACTU_URL_ENDPOINT');
// $item->fieldParams['isMandatory'] = 1;
// $item->fieldAttr['placeholder'] = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'];
// $item->cssClass = 'minwidth500';

// // Setup conf for selection of a simple string input
// $item = $formSetup->newItem('VERIFACTU_MYPARAM2');
// $item->defaultFieldValue = 'default value';
// $item->fieldAttr['placeholder'] = 'A placeholder here';
// $item->helpText = 'Tooltip text';

// // Setup conf for selection of a simple textarea input but we replace the text of field title
// $item = $formSetup->newItem('VERIFACTU_MYPARAM3');
// $item->nameText = $item->getNameText().' more html text ';

// // Setup conf for a selection of a Thirdparty
// $item = $formSetup->newItem('VERIFACTU_MYPARAM4');
// $item->setAsThirdpartyType();

// Setup conf for a selection of a boolean
$item = $formSetup->newItem('VERIFACTU_PODER_AEAT')->setAsYesNo();
$item->fieldParams['isMandatory'] = 1;

$item = $formSetup->newItem('VERIFACTU_URL_COMPROBAR_FACTURA');
$item->fieldParams['isMandatory'] = 1;
$item->fieldAttr['placeholder'] = 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR';
$item->cssClass = 'minwidth500';

$item->fieldAttr['default'] = 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?'; // valor por defecto
$item->helpText = 'URL para comprobar las facturas en la AEAT. Para pruebas: https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR';

// $item = $formSetup->newItem('VERIFACTU_CERTIFICADO_AEAT');
// $item->fieldParams['isMandatory'] = 1;
// $item->fieldAttr['placeholder'] = '/path/to/certificado.pem';
// $item->cssClass = 'minwidth500';
// $item->helpText = 'Ruta completa al certificado digital en formato PEM.';



$arrayofcustomers = array();
$sql = "SELECT rowid, nom FROM " . MAIN_DB_PREFIX . "societe WHERE client = 1 AND entity = " . $conf->entity . " ORDER BY nom";
$result = $db->query($sql);
if ($result) {
	while ($obj = $db->fetch_object($result)) {
		$arrayofcustomers[$obj->rowid] = $obj->nom;
	}
}
$item = $formSetup->newItem('INVOICE_CLIENTE_GENERICO');
$item->setAsSelect($arrayofcustomers);
$item->helpText = 'Cliente genérico para facturas simplificadas. Crear previamente el tercero con NIF 00000000T.';
$item->fieldParams['isMandatory'] = 1;
$item->cssClass = 'minwidth500';


$item = $formSetup->newItem('INVOICE_MAX_AMOUNT_SIMPLIFICADAS');
$item->fieldAttr['type'] = 'number';
$item->fieldAttr['step'] = '0.01';
$item->fieldParams['isMandatory'] = 1;



$item = $formSetup->newItem('VERIFACTU_SISTEMA_ID');
$item->fieldParams['isMandatory'] = 1;
$item->fieldAttr['placeholder'] = '00';
$item->helpText = 'Identificador del sistema informático que emite la factura electrónica. Por defecto, 00';

$item = $formSetup->newItem('VERIFACTU_SOFTWARE_VERSION');
$item->fieldParams['isMandatory'] = 1;
$item->fieldAttr['placeholder'] = '1.0.0';
$item->helpText = 'Versión del software que genera la factura electrónica. Por defecto, 1.0.0';

$item = $formSetup->newItem('VERIFACTU_NUM_INSTALACION');
$item->fieldParams['isMandatory'] = 1;
$item->fieldAttr['placeholder'] = '123456789';
$item->helpText = 'Número de instalación del sistema informático que emite la factura electrónica. Por defecto, 123456789';

// $item = $formSetup->newItem('VERIFACTU_SOLO_VERIFACTU');
// $item->fieldParams['isMandatory'] = 1;
// $item->helpText = 'Si está activado, solo se permitirá el uso de tipos de factura Verifactu en las facturas electrónicas.';

// $item = $formSetup->newItem('VERIFACTU_MULTI_OT');
// $item->fieldParams['isMandatory'] = 1;
// $item->helpText = 'Si está activado, se permitirá el uso de múltiples operaciones de tipo Verifactu en las facturas electrónicas.';

// $item = $formSetup->newItem('VERIFACTU_INDICADOR_MULTI');
// $item->fieldParams['isMandatory'] = 1;
// $item->helpText = 'Si está activado, se permitirá el uso de múltiples indicadores de tipo Verifactu en las facturas electrónicas.';


// // Setup conf for a selection of a secured key
// //$formSetup->newItem('VERIFACTU_MYPARAM7')->setAsSecureKey();

// // Setup conf for a selection of a Product
// $formSetup->newItem('VERIFACTU_MYPARAM8')->setAsProduct();

// // Add a title for a new section
// $formSetup->newItem('NewSection')->setAsTitle();

// $TField = array(
// 	'test01' => $langs->trans('test01'),
// 	'test02' => $langs->trans('test02'),
// 	'test03' => $langs->trans('test03'),
// 	'test04' => $langs->trans('test04'),
// 	'test05' => $langs->trans('test05'),
// 	'test06' => $langs->trans('test06'),
// );

// // Setup conf for a simple combo list
// $formSetup->newItem('VERIFACTU_MYPARAM9')->setAsSelect($TField);

// // Setup conf for a multiselect combo list
// $item = $formSetup->newItem('VERIFACTU_MYPARAM10');
// $item->setAsMultiSelect($TField);
// $item->helpText = $langs->transnoentities('VERIFACTU_MYPARAM10');

// // Setup conf for a category selection
// $formSetup->newItem('VERIFACTU_CATEGORY_ID_XXX')->setAsCategory('product');

// // Setup conf VERIFACTU_MYPARAM10
// $item = $formSetup->newItem('VERIFACTU_MYPARAM10');
// $item->setAsColor();
// $item->defaultFieldValue = '#FF0000';
// //$item->fieldValue = '';
// //$item->fieldAttr = array() ; // fields attribute only for compatible fields like input text
// //$item->fieldOverride = false; // set this var to override field output will override $fieldInputOverride and $fieldOutputOverride too
// //$item->fieldInputOverride = false; // set this var to override field input
// //$item->fieldOutputOverride = false; // set this var to override field output

// $item = $formSetup->newItem('VERIFACTU_MYPARAM11')->setAsHtml();
// $item->nameText = $item->getNameText().' more html text ';
// $item->fieldInputOverride = '';
// $item->helpText = $langs->transnoentities('HelpMessage');
// $item->cssClass = 'minwidth500';

// $item = $formSetup->newItem('VERIFACTU_MYPARAM12');
// $item->fieldOverride = "Value forced, can't be modified";
// $item->cssClass = 'minwidth500';

//$item = $formSetup->newItem('VERIFACTU_MYPARAM13')->setAsDate();	// Not yet implemented

// End of definition of parameters


$setupnotempty += count($formSetup->items);


$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);

$moduledir = 'verifactu';
$myTmpObjects = array();
// TODO Scan list of objects to fill this array
$myTmpObjects['myobject'] = array('label' => 'MyObject', 'includerefgeneration' => 0, 'includedocgeneration' => 0, 'class' => 'MyObject');

$tmpobjectkey = GETPOST('object', 'aZ09');
if ($tmpobjectkey && !array_key_exists($tmpobjectkey, $myTmpObjects)) {
	accessforbidden('Bad value for object. Hack attempt ?');
}


/*
 * Actions
 */

// Crear carpetas necesarias para Verifactu
$verifactu_dir = DOL_DATA_ROOT . '/verifactu';
$outbox_dir = $verifactu_dir . '/OUTBOX';
$inbox_dir = $verifactu_dir . '/INBOX';
$outbox_event_dir = $verifactu_dir . '/EVENT_OUTBOX';
$inbox_event_dir = $verifactu_dir . '/EVENT_INBOX';

// Crear directorio principal de verifactu si no existe
if (!is_dir($verifactu_dir)) {
	if (dol_mkdir($verifactu_dir) < 0) {
		setEventMessages('Error creando directorio ' . $verifactu_dir, null, 'errors');
	} else {
		setEventMessages('Directorio ' . $verifactu_dir . ' creado correctamente', null, 'mesgs');
	}
}

// Crear directorio OUTBOX si no existe
if (!is_dir($outbox_dir)) {
	if (dol_mkdir($outbox_dir) < 0) {
		setEventMessages('Error creando directorio OUTBOX: ' . $outbox_dir, null, 'errors');
	} else {
		setEventMessages('Directorio OUTBOX creado correctamente: ' . $outbox_dir, null, 'mesgs');
	}
}

// Crear directorio INBOX si no existe
if (!is_dir($inbox_dir)) {
	if (dol_mkdir($inbox_dir) < 0) {
		setEventMessages('Error creando directorio INBOX: ' . $inbox_dir, null, 'errors');
	} else {
		setEventMessages('Directorio INBOX creado correctamente: ' . $inbox_dir, null, 'mesgs');
	}
}

// Crear directorio EVENT_OUTBOX si no existe
if (!is_dir($outbox_event_dir)) {
	if (dol_mkdir($outbox_event_dir) < 0) {
		setEventMessages('Error creando directorio EVENT_OUTBOX: ' . $outbox_event_dir, null, 'errors');
	} else {
		setEventMessages('Directorio EVENT_OUTBOX creado correctamente: ' . $outbox_event_dir, null, 'mesgs');
	}
}

// Crear directorio EVENT_INBOX si no existe
if (!is_dir($inbox_event_dir)) {
	if (dol_mkdir($inbox_event_dir) < 0) {
		setEventMessages('Error creando directorio EVENT_INBOX: ' . $inbox_event_dir, null, 'errors');
	} else {
		setEventMessages('Directorio EVENT_INBOX creado correctamente: ' . $inbox_event_dir, null, 'mesgs');
	}
}

// For retrocompatibility Dolibarr < 15.0
if (versioncompare(explode('.', DOL_VERSION), array(15)) < 0 && $action == 'update' && !empty($user->admin)) {
	$formSetup->saveConfFromPost();
}

include DOL_DOCUMENT_ROOT . '/core/actions_setmoduleoptions.inc.php';

// Acción para crear carpetas manualmente
if ($action == 'create_folders') {
	$verifactu_dir = DOL_DATA_ROOT . '/verifactu';
	$outbox_dir = $verifactu_dir . '/OUTBOX';
	$inbox_dir = $verifactu_dir . '/INBOX';

	$errors = 0;
	$messages = array();

	// Crear directorio principal
	if (!is_dir($verifactu_dir)) {
		if (dol_mkdir($verifactu_dir) < 0) {
			$errors++;
			$messages[] = 'Error creando directorio principal: ' . $verifactu_dir;
		} else {
			$messages[] = 'Directorio principal creado: ' . $verifactu_dir;
		}
	} else {
		$messages[] = 'Directorio principal ya existe: ' . $verifactu_dir;
	}

	// Crear OUTBOX
	if (!is_dir($outbox_dir)) {
		if (dol_mkdir($outbox_dir) < 0) {
			$errors++;
			$messages[] = 'Error creando OUTBOX: ' . $outbox_dir;
		} else {
			$messages[] = 'Directorio OUTBOX creado: ' . $outbox_dir;
		}
	} else {
		$messages[] = 'Directorio OUTBOX ya existe: ' . $outbox_dir;
	}

	// Crear INBOX
	if (!is_dir($inbox_dir)) {
		if (dol_mkdir($inbox_dir) < 0) {
			$errors++;
			$messages[] = 'Error creando INBOX: ' . $inbox_dir;
		} else {
			$messages[] = 'Directorio INBOX creado: ' . $inbox_dir;
		}
	} else {
		$messages[] = 'Directorio INBOX ya existe: ' . $inbox_dir;
	}

	if ($errors > 0) {
		setEventMessages($messages, null, 'errors');
	} else {
		setEventMessages($messages, null, 'mesgs');
	}
}

if ($action == 'updateMask') {
	$maskconst = GETPOST('maskconst', 'aZ09');
	$maskvalue = GETPOST('maskvalue', 'alpha');

	if ($maskconst && preg_match('/_MASK$/', $maskconst)) {
		$res = dolibarr_set_const($db, $maskconst, $maskvalue, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) {
			$error++;
		}
	}

	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
} elseif ($action == 'specimen' && $tmpobjectkey) {
	$modele = GETPOST('module', 'alpha');

	$className = $myTmpObjects[$tmpobjectkey]['class'];
	$tmpobject = new $className($db);
	'@phan-var-force MyObject $tmpobject';
	$tmpobject->initAsSpecimen();

	// Search template files
	$file = '';
	$className = '';
	$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
	foreach ($dirmodels as $reldir) {
		$file = dol_buildpath($reldir . "core/modules/verifactu/doc/pdf_" . $modele . "_" . strtolower($tmpobjectkey) . ".modules.php", 0);
		if (file_exists($file)) {
			$className = "pdf_" . $modele . "_" . strtolower($tmpobjectkey);
			break;
		}
	}

	if ($className !== '') {
		require_once $file;

		$module = new $className($db);
		'@phan-var-force ModelePDFMyObject $module';

		'@phan-var-force ModelePDFMyObject $module';

		if ($module->write_file($tmpobject, $langs) > 0) {
			header("Location: " . DOL_URL_ROOT . "/document.php?modulepart=verifactu-" . strtolower($tmpobjectkey) . "&file=SPECIMEN.pdf");
			return;
		} else {
			setEventMessages($module->error, null, 'errors');
			dol_syslog($module->error, LOG_ERR);
		}
	} else {
		setEventMessages($langs->trans("ErrorModuleNotFound"), null, 'errors');
		dol_syslog($langs->trans("ErrorModuleNotFound"), LOG_ERR);
	}
} elseif ($action == 'setmod') {
	// TODO Check if numbering module chosen can be activated by calling method canBeActivated
	if (!empty($tmpobjectkey)) {
		$constforval = 'VERIFACTU_' . strtoupper($tmpobjectkey) . "_ADDON";
		dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity);
	}
} elseif ($action == 'set') {
	// Activate a model
	$ret = addDocumentModel($value, $type, $label, $scandir);
} elseif ($action == 'del') {
	$ret = delDocumentModel($value, $type);
	if ($ret > 0) {
		if (!empty($tmpobjectkey)) {
			$constforval = 'VERIFACTU_' . strtoupper($tmpobjectkey) . '_ADDON_PDF';
			if (getDolGlobalString($constforval) == "$value") {
				dolibarr_del_const($db, $constforval, $conf->entity);
			}
		}
	}
} elseif ($action == 'setdoc') {
	// Set or unset default model
	if (!empty($tmpobjectkey)) {
		$constforval = 'VERIFACTU_' . strtoupper($tmpobjectkey) . '_ADDON_PDF';
		if (dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity)) {
			// The constant that was read before the new set
			// We therefore requires a variable to have a coherent view
			$conf->global->{$constforval} = $value;
		}

		// We disable/enable the document template (into llx_document_model table)
		$ret = delDocumentModel($value, $type);
		if ($ret > 0) {
			$ret = addDocumentModel($value, $type, $label, $scandir);
		}
	}
} elseif ($action == 'unsetdoc') {
	if (!empty($tmpobjectkey)) {
		$constforval = 'VERIFACTU_' . strtoupper($tmpobjectkey) . '_ADDON_PDF';
		dolibarr_del_const($db, $constforval, $conf->entity);
	}
}

$action = 'edit';


/*
 * View
 */

$form = new Form($db);

$help_url = '';
$title = "VerifactuSetup";

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-verifactu page-admin');

// Subheader
$linkback = '<a href="' . ($backtopage ? $backtopage : DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans("BackToModuleList") . '</a>';

print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

// Configuration header
$head = verifactuAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($title), -1, "verifactu@verifactu");

// Setup page goes here
echo '<span class="opacitymedium">' . $langs->trans("VerifactuSetupPage") . '</span><br><br>';


/*if ($action == 'edit') {
 print $formSetup->generateOutput(true);
 print '<br>';
 } elseif (!empty($formSetup->items)) {
 print $formSetup->generateOutput();
 print '<div class="tabsAction">';
 print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'">'.$langs->trans("Modify").'</a>';
 print '</div>';
 }
 */
if (!empty($formSetup->items)) {
	print $formSetup->generateOutput(true);
	print '<br>';
}

// Sección de gestión de carpetas Verifactu
print load_fiche_titre($langs->trans("VerifactuFolders"), '', '');

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>' . $langs->trans("Folder") . '</th>';
print '<th>' . $langs->trans("Path") . '</th>';
print '<th class="center">' . $langs->trans("Status") . '</th>';
print '</tr>';

$verifactu_dir = DOL_DATA_ROOT . '/verifactu';
$outbox_dir = $verifactu_dir . '/OUTBOX';
$inbox_dir = $verifactu_dir . '/INBOX';

// Mostrar estado de las carpetas
$folders = array(
	'Verifactu' => $verifactu_dir,
	'OUTBOX' => $outbox_dir,
	'INBOX' => $inbox_dir
);

foreach ($folders as $name => $path) {
	print '<tr class="oddeven">';
	print '<td>' . $name . '</td>';
	print '<td><code>' . $path . '</code></td>';
	print '<td class="center">';
	if (is_dir($path)) {
		print '<span class="badge badge-status4 badge-status">' . $langs->trans("Exists") . '</span>';
	} else {
		print '<span class="badge badge-status8 badge-status">' . $langs->trans("NotExists") . '</span>';
	}
	print '</td>';
	print '</tr>';
}

print '</table>';
print '</div>';

// Botón para crear carpetas
print '<div class="tabsAction">';
print '<a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?action=create_folders&token=' . newToken() . '">' . $langs->trans("CreateFolders") . '</a>';
print '</div>';
print '<br>';


foreach ($myTmpObjects as $myTmpObjectKey => $myTmpObjectArray) {
	if (!empty($myTmpObjectArray['includerefgeneration'])) {
		// Numbering models

		$setupnotempty++;

		print load_fiche_titre($langs->trans("NumberingModules", $myTmpObjectArray['label']), '', '');

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>' . $langs->trans("Name") . '</td>';
		print '<td>' . $langs->trans("Description") . '</td>';
		print '<td class="nowrap">' . $langs->trans("Example") . '</td>';
		print '<td class="center" width="60">' . $langs->trans("Status") . '</td>';
		print '<td class="center" width="16">' . $langs->trans("ShortInfo") . '</td>';
		print '</tr>' . "\n";

		clearstatcache();

		foreach ($dirmodels as $reldir) {
			$dir = dol_buildpath($reldir . "core/modules/" . $moduledir);

			if (is_dir($dir)) {
				$handle = opendir($dir);
				if (is_resource($handle)) {
					while (($file = readdir($handle)) !== false) {
						if (strpos($file, 'mod_' . strtolower($myTmpObjectKey) . '_') === 0 && substr($file, dol_strlen($file) - 3, 3) == 'php') {
							$file = substr($file, 0, dol_strlen($file) - 4);

							require_once $dir . '/' . $file . '.php';

							$module = new $file($db);
							'@phan-var-force ModeleNumRefMyObject $module';

							// Show modules according to features level
							if ($module->version == 'development' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 2) {
								continue;
							}
							if ($module->version == 'experimental' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 1) {
								continue;
							}

							if ($module->isEnabled()) {
								dol_include_once('/' . $moduledir . '/class/' . strtolower($myTmpObjectKey) . '.class.php');

								print '<tr class="oddeven"><td>' . $module->getName($langs) . "</td><td>\n";
								print $module->info($langs);
								print '</td>';

								// Show example of numbering model
								print '<td class="nowrap">';
								$tmp = $module->getExample();
								if (preg_match('/^Error/', $tmp)) {
									$langs->load("errors");
									print '<div class="error">' . $langs->trans($tmp) . '</div>';
								} elseif ($tmp == 'NotConfigured') {
									print $langs->trans($tmp);
								} else {
									print $tmp;
								}
								print '</td>' . "\n";

								print '<td class="center">';
								$constforvar = 'VERIFACTU_' . strtoupper($myTmpObjectKey) . '_ADDON';
								$defaultifnotset = 'thevaluetousebydefault';
								$activenumberingmodel = getDolGlobalString($constforvar, $defaultifnotset);
								if ($activenumberingmodel == $file) {
									print img_picto($langs->trans("Activated"), 'switch_on');
								} else {
									print '<a href="' . $_SERVER["PHP_SELF"] . '?action=setmod&token=' . newToken() . '&object=' . strtolower($myTmpObjectKey) . '&value=' . urlencode($file) . '">';
									print img_picto($langs->trans("Disabled"), 'switch_off');
									print '</a>';
								}
								print '</td>';

								$className = $myTmpObjectArray['class'];
								$mytmpinstance = new $className($db);
								'@phan-var-force MyObject $mytmpinstance';
								$mytmpinstance->initAsSpecimen();

								// Info
								$htmltooltip = '';
								$htmltooltip .= '' . $langs->trans("Version") . ': <b>' . $module->getVersion() . '</b><br>';

								$nextval = $module->getNextValue($mytmpinstance);
								if ("$nextval" != $langs->trans("NotAvailable")) {  // Keep " on nextval
									$htmltooltip .= '' . $langs->trans("NextValue") . ': ';
									if ($nextval) {
										if (preg_match('/^Error/', $nextval) || $nextval == 'NotConfigured') {
											$nextval = $langs->trans($nextval);
										}
										$htmltooltip .= $nextval . '<br>';
									} else {
										$htmltooltip .= $langs->trans($module->error) . '<br>';
									}
								}

								print '<td class="center">';
								print $form->textwithpicto('', $htmltooltip, 1, 'info');
								print '</td>';

								print "</tr>\n";
							}
						}
					}
					closedir($handle);
				}
			}
		}
		print "</table><br>\n";
	}

	if (!empty($myTmpObjectArray['includedocgeneration'])) {
		/*
		 * Document templates generators
		 */
		$setupnotempty++;
		$type = strtolower($myTmpObjectKey);

		print load_fiche_titre($langs->trans("DocumentModules", $myTmpObjectKey), '', '');

		// Load array def with activated templates
		$def = array();
		$sql = "SELECT nom";
		$sql .= " FROM " . $db->prefix() . "document_model";
		$sql .= " WHERE type = '" . $db->escape($type) . "'";
		$sql .= " AND entity = " . $conf->entity;
		$resql = $db->query($sql);
		if ($resql) {
			$i = 0;
			$num_rows = $db->num_rows($resql);
			while ($i < $num_rows) {
				$array = $db->fetch_array($resql);
				array_push($def, $array[0]);
				$i++;
			}
		} else {
			dol_print_error($db);
		}

		print '<table class="noborder centpercent">' . "\n";
		print '<tr class="liste_titre">' . "\n";
		print '<td>' . $langs->trans("Name") . '</td>';
		print '<td>' . $langs->trans("Description") . '</td>';
		print '<td class="center" width="60">' . $langs->trans("Status") . "</td>\n";
		print '<td class="center" width="60">' . $langs->trans("Default") . "</td>\n";
		print '<td class="center" width="38">' . $langs->trans("ShortInfo") . '</td>';
		print '<td class="center" width="38">' . $langs->trans("Preview") . '</td>';
		print "</tr>\n";

		clearstatcache();

		foreach ($dirmodels as $reldir) {
			foreach (array('', '/doc') as $valdir) {
				$realpath = $reldir . "core/modules/" . $moduledir . $valdir;
				$dir = dol_buildpath($realpath);

				if (is_dir($dir)) {
					$handle = opendir($dir);
					if (is_resource($handle)) {
						$filelist = array();
						while (($file = readdir($handle)) !== false) {
							$filelist[] = $file;
						}
						closedir($handle);
						arsort($filelist);

						foreach ($filelist as $file) {
							if (preg_match('/\.modules\.php$/i', $file) && preg_match('/^(pdf_|doc_)/', $file)) {
								if (file_exists($dir . '/' . $file)) {
									$name = substr($file, 4, dol_strlen($file) - 16);
									$className = substr($file, 0, dol_strlen($file) - 12);

									require_once $dir . '/' . $file;
									$module = new $className($db);
									'@phan-var-force ModelePDFMyObject $module';

									$modulequalified = 1;
									if ($module->version == 'development' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 2) {
										$modulequalified = 0;
									}
									if ($module->version == 'experimental' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 1) {
										$modulequalified = 0;
									}

									if ($modulequalified) {
										print '<tr class="oddeven"><td width="100">';
										print(empty($module->name) ? $name : $module->name);
										print "</td><td>\n";
										if (method_exists($module, 'info')) {
											print $module->info($langs);  // @phan-suppress-current-line PhanUndeclaredMethod
										} else {
											print $module->description;
										}
										print '</td>';

										// Active
										if (in_array($name, $def)) {
											print '<td class="center">' . "\n";
											print '<a href="' . $_SERVER["PHP_SELF"] . '?action=del&token=' . newToken() . '&value=' . urlencode($name) . '">';
											print img_picto($langs->trans("Enabled"), 'switch_on');
											print '</a>';
											print '</td>';
										} else {
											print '<td class="center">' . "\n";
											print '<a href="' . $_SERVER["PHP_SELF"] . '?action=set&token=' . newToken() . '&value=' . urlencode($name) . '&scan_dir=' . urlencode($module->scandir) . '&label=' . urlencode($module->name) . '">' . img_picto($langs->trans("Disabled"), 'switch_off') . '</a>';
											print "</td>";
										}

										// Default
										print '<td class="center">';
										$constforvar = 'VERIFACTU_' . strtoupper($myTmpObjectKey) . '_ADDON_PDF';
										if (getDolGlobalString($constforvar) == $name) {
											//print img_picto($langs->trans("Default"), 'on');
											// Even if choice is the default value, we allow to disable it. Replace this with previous line if you need to disable unset
											print '<a href="' . $_SERVER["PHP_SELF"] . '?action=unsetdoc&token=' . newToken() . '&object=' . urlencode(strtolower($myTmpObjectKey)) . '&value=' . urlencode($name) . '&scan_dir=' . urlencode($module->scandir) . '&label=' . urlencode($module->name) . '&amp;type=' . urlencode($type) . '" alt="' . $langs->trans("Disable") . '">' . img_picto($langs->trans("Enabled"), 'on') . '</a>';
										} else {
											print '<a href="' . $_SERVER["PHP_SELF"] . '?action=setdoc&token=' . newToken() . '&object=' . urlencode(strtolower($myTmpObjectKey)) . '&value=' . urlencode($name) . '&scan_dir=' . urlencode($module->scandir) . '&label=' . urlencode($module->name) . '" alt="' . $langs->trans("Default") . '">' . img_picto($langs->trans("Disabled"), 'off') . '</a>';
										}
										print '</td>';

										// Info
										$htmltooltip = '' . $langs->trans("Name") . ': ' . $module->name;
										$htmltooltip .= '<br>' . $langs->trans("Type") . ': ' . ($module->type ? $module->type : $langs->trans("Unknown"));
										if ($module->type == 'pdf') {
											$htmltooltip .= '<br>' . $langs->trans("Width") . '/' . $langs->trans("Height") . ': ' . $module->page_largeur . '/' . $module->page_hauteur;
										}
										$htmltooltip .= '<br>' . $langs->trans("Path") . ': ' . preg_replace('/^\//', '', $realpath) . '/' . $file;

										$htmltooltip .= '<br><br><u>' . $langs->trans("FeaturesSupported") . ':</u>';
										$htmltooltip .= '<br>' . $langs->trans("Logo") . ': ' . yn($module->option_logo, 1, 1);
										$htmltooltip .= '<br>' . $langs->trans("MultiLanguage") . ': ' . yn($module->option_multilang, 1, 1);

										print '<td class="center">';
										print $form->textwithpicto('', $htmltooltip, 1, 'info');
										print '</td>';

										// Preview
										print '<td class="center">';
										if ($module->type == 'pdf') {
											$newname = preg_replace('/_' . preg_quote(strtolower($myTmpObjectKey), '/') . '/', '', $name);
											print '<a href="' . $_SERVER["PHP_SELF"] . '?action=specimen&module=' . urlencode($newname) . '&object=' . urlencode($myTmpObjectKey) . '">' . img_object($langs->trans("Preview"), 'pdf') . '</a>';
										} else {
											print img_object($langs->transnoentitiesnoconv("PreviewNotAvailable"), 'generic');
										}
										print '</td>';

										print "</tr>\n";
									}
								}
							}
						}
					}
				}
			}
		}

		print '</table>';
	}
}

if (empty($setupnotempty)) {
	print '<br>' . $langs->trans("NothingToSetup");
}

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
