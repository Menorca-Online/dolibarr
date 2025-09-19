<?php
/* Copyright (C) 2025 SuperAdmin
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

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Class for trigger VERIFACTU
 */
class InterfaceVerifactu extends DolibarrTriggers
{
    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "verifactu";
        $this->description = "Trigger Verifactu para gestión de facturas según normativa";

        // Version of this trigger
        $this->version = '1.0';
        $this->picto = 'verifactu@verifactu';
    }

    /**
     * Trigger name
     *
     * @return string Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * @return string Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }

    /**
     * Obtiene el hash de una factura específica por su ID
     *
     * @param int $invoiceId ID de la factura
     * @return string Hash de la factura o cadena vacía si no tiene hash
     */
    private function getInvoiceHash($invoiceId)
    {
        $sql = "SELECT hash FROM " . MAIN_DB_PREFIX . "facture_extrafields";
        $sql .= " WHERE fk_object = " . ((int) $invoiceId);

        $result = $this->db->query($sql);
        if ($result && $this->db->num_rows($result) > 0) {
            $obj = $this->db->fetch_object($result);
			//SI EL HASH ES 1 ES QUE NO TIENE HASH
			if($obj->hash == '1') return "";
            return $obj->hash;
        }

        return "";
    }

    /**
     * Obtiene el último hash generado en el sistema
     *
     * @return string Último hash o una cadena con ceros si no hay facturas previas
     */
    private function getLastInvoiceHash()
    {
        // Buscar la última factura con hash en orden descendente
        $sql = "SELECT f.hash FROM " . MAIN_DB_PREFIX . "facture_extrafields f";
        $sql .= " WHERE f.hash IS NOT NULL AND f.hash != '1'";
        $sql .= " ORDER BY f.fk_object DESC"; // Ordenar por ID de factura descendente (más reciente)
        $sql .= " LIMIT 1";

        $result = $this->db->query($sql);
        if ($result && $this->db->num_rows($result) > 0) {
            $obj = $this->db->fetch_object($result);
            return $obj->hash;
        }

        // Si no hay facturas previas, devolver un valor inicial (64 ceros = hash SHA-256 inicial)
        return "";
    }

    /**
     * Prepara los datos de la factura para la generación del hash
     *
     * @param CommonObject $object Objeto factura
     * @return array Datos preparados para el hash
     */
    private function prepareInvoiceDataForHash($object)
    {
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
		// el tipo factura es el fk_facture_type  llx_verifactu_facture_types
		$TipoFactura = $object->array_options['fk_facture_type'] ?? null;
		require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactufacturetype.class.php';
		$verifactuType = new VerifactuFactureType($this->db);

		if($TipoFactura) $verifactuType->fetch($TipoFactura);
		$tipo = $verifactuType->code ?? 'F1';
		$timestamp = dol_now();
		$dt = new DateTime('@'.$timestamp);         // crea desde timestamp UTC
		$dt->setTimezone(new DateTimeZone('Europe/Madrid')); // o la tz que necesites
		$fechaHora = $dt->format('Y-m-d\TH:i:sP'); // 2025-09-19T10:29:58+02:00
		$huellaAnterior = $this->getLastInvoiceHash();

		// Obtener información del emisor (empresa)
		$nif = '';
		if ($object->socid > 0) {
			require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
			$societe = new Societe($this->db);
			$societe->fetch($object->socid);
			$nif = $societe->idprof1;
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
				require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
				$facture = new Facture($this->db);
				if ($facture->fetch($object->id) > 0) {
					// Si la factura se ha cargado correctamente, tomamos su número
					$numFactura = $facture->ref;
					dol_syslog("Verifactu: Obtenido número desde objeto Facture: " . $numFactura);
				}
			}
		}

		// Si todavía tenemos un número provisional, generar un mensaje de advertencia
		if (preg_match('/^\(PROV/i', $numFactura)) {
			dol_syslog("Verifactu ADVERTENCIA: No se pudo obtener el número definitivo de factura. Usando: " . $numFactura, LOG_WARNING);
			setEventMessages("ADVERTENCIA: El hash se generará con el número provisional de factura", null, 'warnings');
		}

		// Preparar datos según especificaciones Verifactu
		$data = array(
			'IDEmisorFactura' => $nif,
			'NumSerieFactura' => $numFactura, // Usamos el número definitivo
			'FechaExpedicionFactura' => date('Y-m-d', $object->date),
			'TipoFactura' => $tipo,
			'CuotaTotal' => $object->total_tva,
			'ImporteTotal' => $object->total_ttc,
			'Huella' =>  $huellaAnterior,
			'FechaHoraHusoGenRegistro' => $fechaHora,
		);

        // Devolver los datos preparados para la generación del hash
        return $data;
    }

    /**
     * Genera un nuevo hash basado en los datos de la factura y el hash anterior
     *
     * @param array $data Datos de la factura
     * @param string $previousHash Hash de la factura anterior
     * @return string Nuevo hash generado
     */
    private function generateHash($data, $previousHash)
    {
        // Convertir los datos a JSON y normalizar
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);

        // Concatenar con el hash anterior
        $dataToHash = $previousHash . $jsonData;

        // Generar hash SHA-256
        $newHash = hash('sha256', $dataToHash);

        return $newHash;
    }

    /**
     * Guarda los hashes en los campos extras de la factura
     *
     * @param int $invoiceId ID de la factura
     * @param string $newHash Nuevo hash generado
     * @param string $previousHash Hash anterior
     * @param array $data Datos utilizados para generar el hash
     * @return bool True si se guardó correctamente, False en caso contrario
     */
    private function saveInvoiceHashes($invoiceId, $newHash, $previousHash, $data = null)
    {
        // Convertir los datos a JSON para almacenarlos
        $jsonData = '';
        if ($data !== null) {
            $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        // Verificar primero si ya existen registros para esta factura
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "facture_extrafields WHERE fk_object = " . ((int) $invoiceId);
        $result = $this->db->query($sql);

        if ($result && $this->db->num_rows($result) > 0) {
            // Actualizar registros existentes
            $sql = "UPDATE " . MAIN_DB_PREFIX . "facture_extrafields";
            $sql .= " SET hash = '" . $this->db->escape($newHash) . "',";
            $sql .= " hash_anterior = '" . $this->db->escape($previousHash) . "'";

            // Añadir los datos del hash si están disponibles
            if (!empty($jsonData)) {
                $sql .= ", hash_data = '" . $this->db->escape($jsonData) . "'";
            }

            $sql .= " WHERE fk_object = " . ((int) $invoiceId);
        } else {
            // Insertar nuevos registros
            if (!empty($jsonData)) {
                // Con datos
                $sql = "INSERT INTO " . MAIN_DB_PREFIX . "facture_extrafields";
                $sql .= " (fk_object, hash, hash_anterior, hash_data)";
                $sql .= " VALUES (" . ((int) $invoiceId) . ",";
                $sql .= " '" . $this->db->escape($newHash) . "',";
                $sql .= " '" . $this->db->escape($previousHash) . "',";
                $sql .= " '" . $this->db->escape($jsonData) . "')";
            } else {
                // Sin datos
                $sql = "INSERT INTO " . MAIN_DB_PREFIX . "facture_extrafields";
                $sql .= " (fk_object, hash, hash_anterior)";
                $sql .= " VALUES (" . ((int) $invoiceId) . ",";
                $sql .= " '" . $this->db->escape($newHash) . "',";
                $sql .= " '" . $this->db->escape($previousHash) . "')";
            }
        }

        $resql = $this->db->query($sql);
        return ($resql ? true : false);
    }

    /**
     * Function called when a Dolibarr business event occurs.
     * All functions "runTrigger" are triggered if file
     * is inside directory core/triggers
     *
     * @param string 		$action 	Event action code
     * @param CommonObject 	$object 	Object
     * @param User 			$user 		Object user
     * @param Translate 	$langs 		Object langs
     * @param Conf 			$conf 		Object conf
     * @return int              		<0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function runTrigger($action, $object, $user, $langs, $conf)
    {
        if (!isModEnabled('verifactu')) {
            return 0; // If module not enabled, we do nothing
        }

        // Put here code you want to execute when a Dolibarr business events occurs.
        dol_syslog(get_class($this)."::runTrigger action=".$action);

        switch ($action) {
            case 'BILL_CREATE':
                // Forzar fecha de factura a hoy según normativa Verifactu
                $today = dol_now();
                $todayMidnight = dol_mktime(0, 0, 0, date('m'), date('d'), date('Y'));

                if ($object->date != $todayMidnight) {
                    $object->date = $todayMidnight;
                    dol_syslog("Verifactu: Fecha de factura establecida a fecha actual: " . dol_print_date($todayMidnight));
                }
                break;

            case 'BILL_MODIFY':
				dol_syslog("Verifactu: Entrando en BILL_MODIFY para factura id={$object->id}, ref={$object->ref}");
                // PROTECCIÓN CRÍTICA: Evitar que se cambie la fecha de factura una vez creada
                if (isset($object->oldcopy) && isset($object->oldcopy->date)) {
                    $originalDate = $object->oldcopy->date;

                    // Si alguien intenta cambiar la fecha, la restauramos
                    if (isset($object->date) && $object->date != $originalDate) {
                        dol_syslog("Verifactu: INTENTO BLOQUEADO de modificar fecha de factura. Original: " .
                                  dol_print_date($originalDate) . ", Nuevo intento: " . dol_print_date($object->date));

                        // Restaurar fecha original
                        $object->date = $originalDate;

                        // Mostrar error al usuario
                        setEventMessages($langs->trans('VerifactuErrorFechaNoModificable'), null, 'errors');

                        return -1; // Bloquear la operación
                    }
                }
                break;

            case 'BILL_VALIDATE':
                // Validar que la fecha de factura sea la actual antes de validar
                $today = dol_mktime(0, 0, 0, date('m'), date('d'), date('Y'));
                $invoicedate = dol_mktime(0, 0, 0, date('m', $object->date), date('d', $object->date), date('Y', $object->date));

                if ($invoicedate != $today) {
                    setEventMessages($langs->trans('VerifactuErrorFechaDebeSerHoy'), null, 'errors');
                    dol_syslog("Verifactu: Validación bloqueada - fecha incorrecta. Esperada: " .
                              dol_print_date($today) . ", Actual: " . dol_print_date($invoicedate));
                    return -1; // Bloquear validación
                }

                // Generar nuevo hash para la factura validada
                try {
                    dol_syslog("Verifactu: Iniciando proceso de generación de hash para factura ID: " . $object->id);

                    // Verificar si ya tiene un hash (por si es una revalidación)
                    $existingHash = $this->getInvoiceHash($object->id);
                    if (!empty($existingHash)) {
                        dol_syslog("Verifactu: La factura ID: " . $object->id . " ya tiene un hash: " . $existingHash);
                        setEventMessages("Esta factura ya tiene un hash de verificación", null, 'warnings');
                        return 1; // Ya tiene un hash, no necesitamos continuar
                    }

                    // 1. Obtener el último hash conocido (hash_anterior)
                    $lastHash = $this->getLastInvoiceHash();
                    dol_syslog("Verifactu: Último hash encontrado: " . $lastHash);

                    // 2. Obtener datos de esta factura para generar el nuevo hash
                    $invoiceData = $this->prepareInvoiceDataForHash($object);

                    // 3. Generar el nuevo hash
                    $newHash = $this->generateHash($invoiceData, $lastHash);
                    dol_syslog("Verifactu: Nuevo hash generado: " . $newHash);

                    // 4. Guardar el nuevo hash, el hash anterior y los datos utilizados para generar el hash en los campos extras
                    $result = $this->saveInvoiceHashes($object->id, $newHash, $lastHash, $invoiceData);

                    if ($result) {
                        dol_syslog("Verifactu: Hash guardado correctamente para factura ID: " . $object->id);
                        setEventMessages("Verificación de seguridad aplicada correctamente a la factura", null, 'mesgs');

                        // Log detallado para auditoria
                        $logDetails = "Factura: " . $object->ref . ", ID: " . $object->id;
                        $logDetails .= ", Hash: " . $newHash;
                        $logDetails .= ", Hash Anterior: " . $lastHash;
                        $logDetails .= ", Usuario: " . $user->login;
                        $logDetails .= ", Fecha: " . dol_print_date(dol_now(), 'dayhourtext');

                        dol_syslog("Verifactu AUDIT: " . $logDetails);
                    } else {
                        dol_syslog("Verifactu: ERROR al guardar hash en factura ID: " . $object->id, LOG_ERR);
                        setEventMessages("Error al aplicar verificación de seguridad a la factura", null, 'errors');
                        return -1; // Indicar error
                    }
                } catch (Exception $e) {
                    dol_syslog("Verifactu: Excepción al generar hash - " . $e->getMessage(), LOG_ERR);
                    setEventMessages("Error en el proceso de verificación: " . $e->getMessage(), null, 'errors');
                    return -1; // Indicar error
                }

                dol_syslog("Verifactu: Factura validada correctamente con fecha actual");
                break;

            // Protección adicional para otros eventos que puedan modificar facturas
            case 'BILL_BUILDDOC':
            case 'BILL_SENTBYMAIL':
                // Verificar que la fecha no haya sido alterada
                if (isset($object->date)) {
                    $today = dol_mktime(0, 0, 0, date('m'), date('d'), date('Y'));
                    $invoicedate = dol_mktime(0, 0, 0, date('m', $object->date), date('d', $object->date), date('Y', $object->date));

                    if ($invoicedate != $today) {
                        dol_syslog("Verifactu: ADVERTENCIA - Factura con fecha incorrecta detectada en evento " . $action);
                    }
                }
                break;

            default:
                break;
        }

        return 0;
    }
}
