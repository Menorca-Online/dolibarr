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
 * \file    verifactu/class/verifactucron.class.php
 * \ingroup verifactu
 * \brief   Class for Verifactu cron jobs
 */

include_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/lib/verifactu.lib.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactufacturaregistro.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactuxml.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactubatch.class.php';
/**
 * Class VerifactuCron
 */
class VerifactuCron extends CommonObject
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Execute scheduled job for Verifactu
	 *
	 * @param string $parameters Parameters (not used)
	 * @param int &$count Counter for processed items
	 * @param string &$message Message to return
	 * @return int 0 on success, <0 on error
	 */
	public function doScheduledJob($parameters = '', &$count = 0, &$message = '')
	{
		global $langs, $user;

		$count = 0;
		$message = '';

		// Obtener hasta 1000 registros pendientes de envío
		$sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "verifactu_factura_registros WHERE estado = 1 ORDER BY rowid ASC LIMIT 1000";
		$result = $this->db->query($sql);


		if ($result && $this->db->num_rows($result) > 0) {
            
            $batch = new VerifactuBatch($this->db);
            $batch->fecha = dol_now();
            $batch->estado = VERIFACTU_ESTADO_BATCH_PENDIENTE;
            $batch->msg_error = '';
            $batch->num_records = $this->db->num_rows($result);
            $batch->csv = '';
            $batch->create($user);
            die();
			$registrosProcesados = 0;
			$errores = 0;

			while ($obj = $this->db->fetch_object($result)) {
				$registro = new VerifactuFacturaRegistro($this->db);
				if ($registro->fetch($obj->rowid) > 0) {
                    print "Procesando registro ID " . $obj->rowid . "\n";
				// 	// Generar y enviar XML
				// 	$verifactuXML = new VerifactuXML($this->db);
				// 	$resultadoEnvio = $verifactuXML->enviarYVincular($registro, $user);

				// 	if ($resultadoEnvio) {
				// 		$registrosProcesados++;
				// 		dol_syslog("Verifactu Cron: Registro ID " . $obj->rowid . " enviado correctamente", LOG_INFO);
				// 	} else {
				// 		$errores++;
				// 		dol_syslog("Verifactu Cron: Error al enviar registro ID " . $obj->rowid, LOG_ERR);
				// 	}
				// } else {
				// 	$errores++;
				// 	dol_syslog("Verifactu Cron: Error al cargar registro ID " . $obj->rowid, LOG_ERR);
				// }
                }
			}

			// $this->db->free($result);

			// $count = $registrosProcesados;
			// $message = "Procesados: " . $registrosProcesados . ", Errores: " . $errores;

			// if ($errores > 0) {
			// 	return -1; // Indicar que hubo errores
			// }
		} else {
			$message = "No hay registros pendientes de envío.";
			return -1;
		}

		return 0;
	}
}