<?php
/* Copyright (C) 2025		SuperAdmin
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
 * \file    verifactu/lib/verifactu.lib.php
 * \ingroup verifactu
 * \brief   Library files with common functions for Verifactu
 */

include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactufacturaregistro.class.php';

global $db, $langs, $conf, $user;
/**
 * Prepare admin pages header
 *
 * @return array<array{string,string,string}>
 */
function verifactuAdminPrepareHead()
{
	global $langs, $conf;

	// global $db;
	// $extrafields = new ExtraFields($db);
	// $extrafields->fetch_name_optionals_label('myobject');

	$langs->load("verifactu@verifactu");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/verifactu/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	/*
	$head[$h][0] = dol_buildpath("/verifactu/admin/myobject_extrafields.php", 1);
	$head[$h][1] = $langs->trans("ExtraFields");
	$nbExtrafields = (isset($extrafields->attributes['myobject']['label']) && is_countable($extrafields->attributes['myobject']['label'])) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafields';
	$h++;

	$head[$h][0] = dol_buildpath("/verifactu/admin/myobjectline_extrafields.php", 1);
	$head[$h][1] = $langs->trans("ExtraFieldsLines");
	$nbExtrafields = (isset($extrafields->attributes['myobjectline']['label']) && is_countable($extrafields->attributes['myobjectline']['label'])) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafieldsline';
	$h++;
	*/

	$head[$h][0] = dol_buildpath("/verifactu/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@verifactu:/verifactu/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@verifactu:/verifactu/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, null, $head, $h, 'verifactu@verifactu');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'verifactu@verifactu', 'remove');

	return $head;
}



/**
 * Return array of tabs to used on pages for third parties
 *
 * @param $object Object company shown
 * @return int
 * 0 if ok
 */
function verifactu_generar_registro_alta($object)
{
	global $db, $user;
	
	try {

		$existingAvalidHash = getInvoiceHash($object->id);
		if (!empty($existingAvalidHash)) {
			setEventMessages("Esta factura ya tiene un hash de verificación valido en la AEAT", null, 'warnings');

			return 1;
		}

		// 2. Obtener datos de esta factura para generar el nuevo hash
		$invoiceData = prepareInvoiceDataForHash($object);
		if (!isset($invoiceData['FechaHoraHusoGenRegistro']) || $invoiceData['FechaHoraHusoGenRegistro'] == null) {
			setEventMessages("ADVERTENCIA: El hash se generará con el número provisional de factura", null, 'warnings');
			return 1;
		}


		$newHash = generateHash($invoiceData);

		$db->begin();
		$registro = new VerifactuFacturaRegistro($db);
		$registro->factureid = $object->id;
		$registro->hash = $newHash;
		$registro->hash_data = json_encode($invoiceData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		$registro->fechaHoraHusoGenRegistro = $invoiceData['FechaHoraHusoGenRegistro'];
		$registro->fecha = (new DateTime('now', new DateTimeZone('Europe/Madrid')))->format('Y-m-d H:i:s');
		$registro->estado = 1; // Pendiente de envío
		$registro->operation = 'REGISTRO_ALTA';
		$result = $registro->create($user);

		if ($result) {
			$db->commit();
			setEventMessages("Registro de Alta Verifactu creado correctamente para la factura", null, 'mesgs');
		} else {
			$db->rollback();
			setEventMessages("Error al aplicar verificación de seguridad a la factura", null, 'errors');
			$object->error = "Error al aplicar verificación de seguridad a la factura";
			return -1; // Indicar error
		}
	} catch (Exception $e) {
		// En caso de excepción, hacer rollback
		$db->rollback();
		dol_syslog("Verifactu: Excepción al generar hash - " . $e->getMessage(), LOG_ERR);
		setEventMessages("Error en el proceso de verificación: " . $e->getMessage(), null, 'errors');
		$object->error = "Error en el proceso de verificación: " . $e->getMessage();
		return -1; // Indicar error
	}
	return 0;
}




// function saveInvoiceHashes($invoiceId, $newHash, $previousHash, $data = null)
// {
// 	// Convertir los datos a JSON para almacenarlos
// 	$fechaHora = $data['FechaHoraHusoGenRegistro'] ?? '';
// 	$jsonData = '';
// 	if ($data !== null) {
// 		$jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
// 	}

// 	// Verificar si es una factura rectificativa
// 	$sql = "SELECT type FROM " . MAIN_DB_PREFIX . "facture WHERE rowid = " . ((int) $invoiceId);
// 	$typeResult = $this->db->query($sql);
// 	$isRectificativa = false;

// 	if ($typeResult && $this->db->num_rows($typeResult) > 0) {
// 		$typeObj = $this->db->fetch_object($typeResult);
// 		if ($typeObj->type == 2) {
// 			$isRectificativa = true;
// 			dol_syslog("Verifactu: Guardando hash para factura rectificativa ID: " . $invoiceId);
// 		}
// 	}

// 	// Verificar primero si ya existen registros para esta factura
// 	$sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "facture_extrafields WHERE fk_object = " . ((int) $invoiceId);
// 	$result = $this->db->query($sql);

// 	if ($result && $this->db->num_rows($result) > 0) {
// 		// Actualizar registros existentes
// 		$sql = "UPDATE " . MAIN_DB_PREFIX . "facture_extrafields";
// 		$sql .= " SET hash = '" . $this->db->escape($newHash) . "',";
// 		$sql .= " hash_anterior = '" . $this->db->escape($previousHash) . "',";
// 		$sql .= " fechaHoraHusoGenRegistro = '" . $this->db->escape($fechaHora) . "'";

// 		// Añadir los datos del hash si están disponibles
// 		if (!empty($jsonData)) {
// 			$sql .= ", hash_data = '" . $this->db->escape($jsonData) . "'";
// 		}

// 		$sql .= " WHERE fk_object = " . ((int) $invoiceId);
// 	} else {
// 		// Insertar nuevos registros
// 		if (!empty($jsonData)) {
// 			// Con datos
// 			$sql = "INSERT INTO " . MAIN_DB_PREFIX . "facture_extrafields";
// 			$sql .= " (fk_object, hash, hash_anterior, hash_data, fechaHoraHusoGenRegistro)";
// 			$sql .= " VALUES (" . ((int) $invoiceId) . ",";
// 			$sql .= " '" . $this->db->escape($newHash) . "',";
// 			$sql .= " '" . $this->db->escape($previousHash) . "',";
// 			$sql .= " '" . $this->db->escape($jsonData) . "',";
// 			$sql .= " '" . $this->db->escape($fechaHora) . "')";
// 		} else {
// 			// Sin datos
// 			$sql = "INSERT INTO " . MAIN_DB_PREFIX . "facture_extrafields";
// 			$sql .= " (fk_object, hash, hash_anterior, fechaHoraHusoGenRegistro)";
// 			$sql .= " VALUES (" . ((int) $invoiceId) . ",";
// 			$sql .= " '" . $this->db->escape($newHash) . "',";
// 			$sql .= " '" . $this->db->escape($previousHash) . "',";
// 			$sql .= " '" . $this->db->escape($fechaHora) . "' )";
// 		}
// 	}

// 	$resql = $this->db->query($sql);
// 	return ($resql ? true : false);
// }
/**
 * Genera un nuevo hash basado en los datos de la factura y el hash anterior
 * REVISAR CON VERFICATU C# SI ESTA BIEN GENERADO O NO, ALLI LOS CONCATENABAN CON & COMO URL
 *
 * @param array $data Datos de la factura
 * @param string $previousHash Hash de la factura anterior
 * @return string Nuevo hash generado
 */
function generateHash(array $data)
{
	if (!isset($data['IDEmisorFactura'])) { // es una factura anulada
		$stringToHash =
			"IDEmisorFacturaAnulada=" . $data['IDEmisorFacturaAnulada'] .
			"&NumSerieFacturaAnulada=" . $data['NumSerieFacturaAnulada'] .
			"&FechaExpedicionFacturaAnulada=" . $data['FechaExpedicionFacturaAnulada'] .
			"&Huella=" . ($data['Huella'] ?? '') .
			"&FechaHoraHusoGenRegistro=" . $data['FechaHoraHusoGenRegistro'];
	} else {
		$stringToHash =
			"IDEmisorFactura=" . $data['IDEmisorFactura'] .
			"&NumSerieFactura=" . $data['NumSerieFactura'] .
			"&FechaExpedicionFactura=" . $data['FechaExpedicionFactura'] .
			"&TipoFactura=" . $data['TipoFactura'] .
			"&CuotaTotal=" . $data['CuotaTotal'] .
			"&ImporteTotal=" . $data['ImporteTotal'] .
			"&Huella=" . ($data['Huella'] ?? '') .
			"&FechaHoraHusoGenRegistro=" . $data['FechaHoraHusoGenRegistro'];
	}

	return strtoupper(hash('sha256', $stringToHash));
}

/**
 * Prepara los datos de la factura para la generación del hash
 *
 * @param CommonObject $object Objeto factura
 * @return array Datos preparados para el hash
 */
function prepareInvoiceDataForHash($object)
{
	global $db, $conf;
	
	// Obtener líneas de factura
	$object->fetch_lines();


	//PARA OBTENER EL HASH ACTUAL NECESITAMOS
	// 1.º NIF del emisor.
	// 2.º Numero de factura y serie.
	// 3.º Fecha de expedición de la factura.
	// 4.º Tipo de factura.
	// 5.º Cuota total.
	// 6.º Importe total.
	// 7.º Huella del registro de facturación anterior.
	// 8.º Fecha, hora y huso horario de generación del registro.

	require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';
	require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactufacturetype.class.php';



	$tipoId = $object->array_options['options_fk_facture_type'] ?? null;
	$tipo = "F2";
	if ($tipoId) {
		$verifactuType = new VerifactuFactureType($db);
		if ($verifactuType->fetchCommon($tipoId) > 0) {
			$tipo = $verifactuType->code;
		}
	}


	$timestamp = dol_now();
	$dt = new DateTime('@' . $timestamp);         // crea desde timestamp UTC
	$dt->setTimezone(new DateTimeZone('Europe/Madrid')); // o la tz que necesites
	$fechaHora = $dt->format('Y-m-d\TH:i:sP'); // 2025-09-19T10:29:58+02:00
	$huellaAnterior = getLastInvoice()->hash ?? '';

	global $conf;

	$nif = '';
	if (isset($conf->global->MAIN_INFO_TVAINTRA) && !empty($conf->global->MAIN_INFO_TVAINTRA)) {
		$nif = $conf->global->MAIN_INFO_TVAINTRA;
	} elseif (isset($conf->global->MAIN_INFO_SIREN) && !empty($conf->global->MAIN_INFO_SIREN)) {
		$nif = $conf->global->MAIN_INFO_SIREN;
	} elseif (isset($conf->global->MAIN_INFO_NIF) && !empty($conf->global->MAIN_INFO_NIF)) {
		$nif = $conf->global->MAIN_INFO_NIF;
	}


	// Verificar si la factura todavía tiene un número provisional
	$numFactura = $object->ref;

	// Si estamos en BILL_VALIDATE, la factura podría tener aún un número provisional
	if (preg_match('/^\(PROV/i', $numFactura)) {
		dol_syslog("Verifactu: La factura tiene un número provisional: " . $numFactura);

		// Esperar un momento para que Dolibarr asigne el número definitivo
		sleep(1);

		// 1. Intentar recargar la factura para obtener el número definitivo
		$object->fetch($object->id);
		$numFactura = $object->ref;

		// 2. Si todavía es provisional, intentar obtener el número generado
		if (preg_match('/^\(PROV/i', $numFactura)) {
			// Intentar acceder a la factura a través del modelo de facturación
			require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
			$facture = new Facture($db);
			if ($facture->fetch($object->id) > 0) {
				// Si la factura se ha cargado correctamente, tomamos su número
				$numFactura = $facture->ref;
				dol_syslog("Verifactu: Obtenido número desde objeto Facture: " . $numFactura);
			}
		}
	}

	if (preg_match('/^\(PROV/i', $numFactura)) {
		dol_syslog("Verifactu ADVERTENCIA: No se pudo obtener el número definitivo de factura. Usando: " . $numFactura, LOG_WARNING);
		setEventMessages("ADVERTENCIA: El hash se generará con el número provisional de factura", null, 'warnings');
		return array();
	}

	// Preparar datos según especificaciones Verifactu
	$data = array(
		'IDEmisorFactura' => $nif,
		'NumSerieFactura' => $numFactura, // Usamos el número definitivo
		'FechaExpedicionFactura' => date('d-m-Y', $object->date),
		'TipoFactura' => $tipo,
		'CuotaTotal' => number_format($object->total_tva, 2, '.', ''),
		'ImporteTotal' => number_format($object->total_ttc, 2, '.', ''),
		'Huella' =>  $huellaAnterior,
		'FechaHoraHusoGenRegistro' => $fechaHora,
	);

	return $data;
}

function getPreviousRegister($hash)
{
	global $db;
	
	// Leer el último hash de la tabla dedicada
	$sql = "SELECT hash, hash_data, operation  FROM " . MAIN_DB_PREFIX . "verifactu_factura_registros WHERE hash = '" . $db->escape($hash) . "'";
	$result = $db->query($sql);
	if ($result && $db->num_rows($result) > 0) {
		$obj = $db->fetch_object($result);
		$db->free($result);
		return $obj;
	}

	// Si no hay registros, devolver cadena vacía (se inicializará con el primer hash)
	return "";
}

function getLastRegistro()
{
	global $db;
	
	// Leer el último hash de la tabla dedicada
	$sql = "SELECT hash, hash_data, operation  FROM " . MAIN_DB_PREFIX . "verifactu_factura_registros ORDER BY rowid DESC LIMIT 1";
	$result = $db->query($sql);
	if ($result && $db->num_rows($result) > 0) {
		$obj = $db->fetch_object($result);
		$db->free($result);
		return $obj;
	}

	// Si no hay registros, devolver cadena vacía (se inicializará con el primer hash)
	return "";
}


function getFirstFacturaRegistro($factureId)
{
	global $db;
	
	// Leer primer registro pendiente de envio para esta factura
	$sql = "SELECT hash, hash_data, operation  FROM " . MAIN_DB_PREFIX . "verifactu_factura_registros";
	$sql .= " WHERE factureid = " . ((int) $factureId) ;
	$sql .= " AND estado = 1"; // Pendiente de envío
	$sql .= " ORDER BY rowid ASC LIMIT 1";

	$result = $db->query($sql);
	if ($result && $db->num_rows($result) > 0) {
		$obj = $db->fetch_object($result);
		$db->free($result);
		return $obj;
	}

	return null;
}


function getLastInvoice()
{
	global $db;
	
	// Leer el último hash de la tabla dedicada
	$sql = "SELECT hash, hash_data, operation  FROM " . MAIN_DB_PREFIX . "verifactu_factura_registros ORDER BY rowid DESC LIMIT 1";
	$result = $db->query($sql);
	if ($result && $db->num_rows($result) > 0) {
		$obj = $db->fetch_object($result);
		$db->free($result);
		return $obj;
	}

	// Si no hay registros, devolver cadena vacía (se inicializará con el primer hash)
	return "";
}

/**
 * Obtiene el hash de una factura específica por su ID
 *
 * @param int $invoiceId ID de la factura
 * @return string Hash de la factura o cadena vacía si no tiene hash
 */
function getInvoiceHash($invoiceId)
{
	global $db;
	
	$sql = "SELECT hash FROM " . MAIN_DB_PREFIX . "verifactu_factura_registros";
	$sql .= " WHERE factureid = " . ((int) $invoiceId);
	$sql .= " AND estado  = 2";

	$result = $db->query($sql);
	if ($result && $db->num_rows($result) > 0) {
		$obj = $db->fetch_object($result);
		return $obj->hash;
	}

	return "";
}
