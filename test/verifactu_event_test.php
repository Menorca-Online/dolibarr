<?php

require_once '/var/www/html/htdocs/conf/conf.php';
require_once '/var/www/html/htdocs/master.inc.php';
global $db, $user;
if (!$db) {
    echo 'DB error';
    exit;
}
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
$user = new User($db);
$user->fetch(1);
require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactueventregistro.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactueventbatch.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactuxml.class.php';

$result = verifactu_generar_registro_event(3, array(
    'LanzamientoProcesoDeteccionAnomaliasRegFacturacion' => array(
        array(
            'RealizadoProcesoSobreIntegridadHuellasRegFacturacion' => 'S',
            'RealizadoProcesoSobreIntegridadFirmasRegFacturacion' => 'S',
            'RealizadoProcesoSobreTrazabilidadCadenaRegFacturacion' => 'S',
            'RealizadoProcesoSobreTrazabilidadFechasRegFacturacion' => 'S',
        )
    )
));

if ($result == 0) {
    $this->db->begin();
    try {
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "verifactu_event_registros WHERE estado = 1 ORDER BY rowid ASC LIMIT 1";
        $result = $this->db->query($sql);
        if ($result && $this->db->num_rows($result) > 0) {
            $batch = new VerifactuEventBatch($this->db);
            $batch->fecha = dol_now();
            $batch->estado = VERIFACTU_ESTADO_BATCH_PENDIENTE;
            $batch->msg_error = '';
            $batch->num_records = $this->db->num_rows($result);
            $batch->csv = '';
            $batch->create($user);

            while ($obj = $this->db->fetch_object($result)) {
                $registro = new VerifactuEventRegistro($this->db);
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
        $message = "Error al cargar el usuario admin.";
        return 0;
    }

    try {
        $xml = new VerifactuXML($this->db, $batch);
        $result = $xml->sendBatchEvent();
    } catch (Exception $e) {
        $message = "Error al enviar el batch ID " . $batch->rowid . ": " . $e->getMessage();
        return 0;
    }
}
