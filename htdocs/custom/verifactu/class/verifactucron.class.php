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
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactunotifyerror.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactuerror.class.php';
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
		$this->db->begin();
		try {
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

				while ($obj = $this->db->fetch_object($result)) {
					$registro = new VerifactuFacturaRegistro($this->db);
					if ($registro->fetch($obj->rowid) > 0) {
						$registro->fk_batch = $batch->id;
						$registro->estado = VERIFACTU_ESTADO_REGISTRO_ENVIANDO;
						$registro->updateCommon($user);
						$count++;
					} else {
						$this->db->rollback();
						$message .= "Error al cargar el registro ID " . $obj->rowid . "\n";
						return -1;
					}
				}

				$message = "Se han marcado " . $count . " registros para envío en batch ID " . $batch->rowid . ".";
				

			} else {
				$message = "No hay registros pendientes de envío.";
			}
			$this->db->commit();
			$message .= "Proceso completado correctamente.";

		} catch (Exception $e) {

			
			$this->db->rollback();
			$message = "Error general en cron: " . $e->getMessage();
		
			// Enviar notificación de error
			$notifier = new VerifactuNotifyError($this->db);
			$notifier->sendErrorNotification(
				$e->getMessage(),
				'cron_general',
				array(
					'Tipo de error' => 'Excepción general',
					'Registros procesados' => $count,
					'SQL ejecutado' => $sql ?? 'N/A'
				)
			);

			return 0;
		}

		try {
			if (isset($batch) && $batch){
				$xml = new VerifactuXML($this->db, $batch);
				$result = $xml->sendBatch();
			}
		} catch (\Throwable $e) {
			$message = "Error al enviar el batch ID " . $batch->rowid . ": " . $e->getMessage();
			
			// Enviar notificación de error
			$notifier = new VerifactuNotifyError($this->db);
			$notifier->sendErrorNotification(
				$e->getMessage(),
				'cron_batch_send',
				array(
					'Batch ID' => $batch->rowid,
					'Número de registros' => $count,
					'Estado del batch' => isset($batch) ? $batch->estado : 'N/A'
				)
			);
			
			return 0;
		}
		return 0;
	}

	/**
	 * Execute scheduled job for sending error notifications by email
	 *
	 * @param string $parameters Parameters (not used)
	 * @param int &$count Counter for processed items
	 * @param string &$message Message to return
	 * @return int 0 on success, <0 on error
	 */
	public function doScheduledJobErrors($parameters = '', &$count = 0, &$message = '')
	{
		global $langs, $user, $conf;

		$count = 0;
		$message = '';
		$this->db->begin();

		try {
			// Obtener errores no notificados
			$errorHandler = new VerifactuError($this->db);
			$errores = $errorHandler->getErrorsNoNotificados();

			if (empty($errores)) {
				$message = "No hay errores pendientes de notificación.";
				$this->db->commit();
				return 0;
			}

			$count = count($errores);

			// Preparar contenido del email
			$subject = '[' . getDolGlobalString('MAIN_INFO_SOCIETE_NOM') . '] Errores del módulo Verifactu - ' . dol_print_date(dol_now(), 'dayhour');

			$body = "Se han detectado los siguientes errores en el módulo Verifactu:\n\n";

			foreach ($errores as $error) {
				$body .= "Fecha: " . dol_print_date($error->fecha, 'dayhour') . "\n";
				$body .= "Tipo: " . $error->tipo_error . "\n";
				$body .= "Mensaje: " . $error->mensaje . "\n";

				if ($error->fk_batch) {
					$body .= "Batch ID: " . $error->fk_batch . "\n";
				}
				if ($error->fk_registro) {
					$body .= "Registro ID: " . $error->fk_registro . "\n";
				}
				if ($error->datos_adicionales) {
					$body .= "Datos adicionales: " . $error->datos_adicionales . "\n";
				}

				$body .= "---\n\n";
			}

			$body .= "Para más detalles, acceda al panel de administración del módulo Verifactu.\n\n";
			$body .= "Este es un mensaje automático generado por el sistema.\n";

			// Enviar email
			require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';

			$from = getDolGlobalString('MAIN_MAIL_EMAIL_FROM');
			$to = getDolGlobalString('VERIFACTU_ERROR_EMAIL_TO', getDolGlobalString('MAIN_MAIL_EMAIL_FROM'));

			if (empty($to)) {
				$message = "No se ha configurado email de destino para notificaciones de error.";
				$this->db->rollback();
				return -1;
			}

			$mailfile = new CMailFile(
				$subject,
				$to,
				$from,
				$body,
				array(),
				array(),
				array(),
				'',
				'',
				0,
				1
			);

			$result = $mailfile->sendfile();

			if ($result) {
				// Marcar errores como notificados
				foreach ($errores as $error) {
					$error->notificado = 1;
					$error->update($user);
				}

				$message = "Se han enviado " . $count . " notificaciones de error por email y marcados como notificados.";
				$this->db->commit();
			} else {
				$message = "Error al enviar el email de notificación: " . $mailfile->error;
				$this->db->rollback();
				return -1;
			}

		} catch (Exception $e) {
			$this->db->rollback();
			$message = "Error en cron de errores: " . $e->getMessage();

			// Registrar el error del cron
			VerifactuError::registrarError($this->db, 'cron_error_notification', $e->getMessage());

			return -1;
		}

		return 0;
	}
}
