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
 * \file    verifactu/class/verifactunotifyerror.class.php
 * \ingroup verifactu
 * \brief   Class for sending error notifications via email for Verifactu
 */

include_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';
include_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';

/**
 * Class VerifactuNotifyError
 */
class VerifactuNotifyError extends CommonObject
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
     * Send error notification email
     *
     * @param string $errorMessage The error message
     * @param string $context Additional context (e.g., 'cron', 'batch', 'xml')
     * @param array $additionalData Additional data to include in the email
     * @return bool True on success, false on failure
     */
    public function sendErrorNotification($errorMessage, $context = 'general', $additionalData = array())
    {
        global $conf, $langs;

        try {
            // Configuración del email desde Dolibarr
            $fromEmail = !empty($conf->global->MAIN_MAIL_EMAIL_FROM) ? $conf->global->MAIN_MAIL_EMAIL_FROM : 'noreply@dolibarr.com';
            $fromName = !empty($conf->global->MAIN_MAIL_EMAIL_FROM_NAME) ? $conf->global->MAIN_MAIL_EMAIL_FROM_NAME : 'Dolibarr Verifactu';
            $toEmail = 'verifactu@menorcaon.com';

            // Asunto del email
            $subject = '[Verifactu] Error en ' . ucfirst($context) . ' - ' . date('d/m/Y H:i:s');

            // Cuerpo del email
            $body = $this->buildEmailBody($errorMessage, $context, $additionalData);

            // Crear instancia de CMailFile con parámetros correctos
            $mail = new CMailFile(
                $subject,           // subject
                $toEmail,           // to
                $fromEmail,         // from
                $body,              // msg
                array(),            // filename_list
                array(),            // mimetype_list
                array(),            // mimefilename_list
                "",                 // addr_cc
                "",                 // addr_bcc
                0,                  // deliveryreceipt
                0,                  // msgishtml (0 = texto plano)
                "",                 // errors_to
                "",                 // css
                "verifactu_error",  // trackid
                "",                 // moreinheader
                "standard",         // sendcontext
                "",                 // replyto
                "",                 // upload_dir_tmp
                "",                 // in_reply_to
                ""                  // references
            );

            // Establecer el nombre del remitente
            $mail->addr_from = $fromName . ' <' . $fromEmail . '>';

            // Configurar SMTP si está disponible (usando el método estándar de Dolibarr)
            if (!empty($conf->global->MAIN_MAIL_SENDMODE) && $conf->global->MAIN_MAIL_SENDMODE == 'smtp') {
                if (!empty($conf->global->MAIN_MAIL_SMTP_SERVER)) {
                    $mail->host = $conf->global->MAIN_MAIL_SMTP_SERVER;
                }
                if (!empty($conf->global->MAIN_MAIL_SMTP_PORT)) {
                    $mail->port = $conf->global->MAIN_MAIL_SMTP_PORT;
                }
                if (!empty($conf->global->MAIN_MAIL_SMTP_USER)) {
                    $mail->username = $conf->global->MAIN_MAIL_SMTP_USER;
                    $mail->password = $conf->global->MAIN_MAIL_SMTP_PASS;
                }
                $mail->smtp_auth = (!empty($conf->global->MAIN_MAIL_SMTP_USER)) ? 1 : 0;
                if (!empty($conf->global->MAIN_MAIL_SMTP_SSL)) {
                    $mail->smtp_secure = $conf->global->MAIN_MAIL_SMTP_SSL;
                }
            }

            // Enviar el email
            $result = $mail->sendfile();

            if ($result) {
                dol_syslog("VerifactuNotifyError: Email enviado correctamente a " . $toEmail, LOG_INFO);
                return true;
            } else {
                dol_syslog("VerifactuNotifyError: Error al enviar email: " . $mail->error, LOG_ERR);
                return false;
            }

        } catch (Exception $e) {
            dol_syslog("VerifactuNotifyError: Excepción al enviar email: " . $e->getMessage(), LOG_ERR);
            return false;
        }
    }

    /**
     * Build the email body with error details
     *
     * @param string $errorMessage The error message
     * @param string $context The context
     * @param array $additionalData Additional data
     * @return string The formatted email body
     */
    private function buildEmailBody($errorMessage, $context, $additionalData = array())
    {
        global $conf;

        $body = "Se ha producido un error en el módulo Verifactu.\n\n";
        $body .= "Detalles del error:\n";
        $body .= "- Fecha/Hora: " . date('d/m/Y H:i:s T') . "\n";
        $body .= "- Contexto: " . $context . "\n";
        $body .= "- Mensaje de error: " . $errorMessage . "\n";
        $body .= "- URL: " . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'N/A') . "\n";
        $body .= "- IP: " . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'N/A') . "\n";
        $body .= "- Usuario: " . (isset($GLOBALS['user']) ? $GLOBALS['user']->login : 'N/A') . "\n";

        if (!empty($additionalData)) {
            $body .= "\nDatos adicionales:\n";
            foreach ($additionalData as $key => $value) {
                $body .= "- " . $key . ": " . $value . "\n";
            }
        }

        $body .= "\nConfiguración del sistema:\n";
        $body .= "- Dolibarr versión: " . DOL_VERSION . "\n";
        $body .= "- PHP versión: " . PHP_VERSION . "\n";
        $body .= "- Zona horaria: " . date_default_timezone_get() . "\n";

        $body .= "\nPor favor, revise los logs de Dolibarr para más detalles.\n";
        $body .= "Archivo de logs: " . $conf->global->SYSLOG_FILE . "\n";

        return $body;
    }
}