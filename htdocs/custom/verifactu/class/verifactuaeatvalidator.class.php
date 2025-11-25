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

/**
 * Class for AEAT NIF validation utilities
 */
class VerifactuAEATValidator
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

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
     * Valida un NIF contra el servicio web de la AEAT
     *
     * @param string $nif NIF/CIF/NIE a validar
     * @param string $name Nombre del contribuyente
     * @return bool True si es válido según AEAT, False en caso contrario
     */
    public function validateNIFAEAT($nif, $name)
    {
        $certPath = DOL_DATA_ROOT . '/verifactu/certs/cert.pem';
        $keyPath = DOL_DATA_ROOT . '/verifactu/certs/key.pem';
        
        // Verificar que existen los certificados
        if (!file_exists($certPath) || !file_exists($keyPath)) {
            dol_syslog("Verifactu: Certificados no encontrados para validación NIF AEAT", LOG_ERR);
            return false;
        }
        
        $url = 'https://www1.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP';
        
        // Preparar solicitud SOAP
        $soapRequest = '<?xml version="1.0" encoding="UTF-8"?>
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:vnif="http://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/burt/jdit/ws/VNifV2Ent.xsd">
            <soapenv:Header/>
                <soapenv:Body>
                    <vnif:VNifV2Ent>
                        <vnif:Contribuyente>
                            <vnif:Nif>' . htmlspecialchars($nif) . '</vnif:Nif>
                            <vnif:Nombre>' . htmlspecialchars($name) . '</vnif:Nombre>
                        </vnif:Contribuyente>
                    </vnif:VNifV2Ent>
                </soapenv:Body>
            </soapenv:Envelope>';
        
        // Inicializar cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: ""',
        ]);
        curl_setopt($ch, CURLOPT_SSLCERT, $certPath);
        curl_setopt($ch, CURLOPT_SSLKEY, $keyPath);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $soapRequest);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            dol_syslog("Verifactu: Error cURL validando NIF AEAT: " . $error, LOG_ERR);
            return false;
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            dol_syslog("Verifactu: Error HTTP validando NIF AEAT: " . $httpCode, LOG_ERR);
            return false;
        }
        
        // Parsear respuesta XML
        $xml = simplexml_load_string($response);
        
        if ($xml === false) {
            dol_syslog("Verifactu: Error parseando respuesta XML de AEAT", LOG_ERR);
            return false;
        }
        
        // Registrar namespaces
        $xml->registerXPathNamespace('env', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('VNifV2Sal', 'http://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/burt/jdit/ws/VNifV2Sal.xsd');
        
        // Buscar elemento Contribuyente
        $contribuyente = $xml->xpath('//VNifV2Sal:Contribuyente');
        
        if (empty($contribuyente)) {
            dol_syslog("Verifactu: No se encontró información del contribuyente en respuesta AEAT", LOG_WARNING);
            return false;
        }
        
        $contribuyente = $contribuyente[0];
        $contribuyente->registerXPathNamespace('VNifV2Sal', 'http://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/burt/jdit/ws/VNifV2Sal.xsd');
        
        // Extraer resultado
        $resultadoElement = $contribuyente->xpath('VNifV2Sal:Resultado');
        $resultado = !empty($resultadoElement) ? (string)$resultadoElement[0] : '';
        
        // Validar resultado
        $valido = strtoupper($resultado) === 'IDENTIFICADO';
        
        if ($valido) {
            dol_syslog("Verifactu: NIF $nif validado correctamente contra AEAT para $name", LOG_INFO);
        } else {
            dol_syslog("Verifactu: NIF $nif NO válido en AEAT para $name. Resultado: $resultado", LOG_WARNING);
        }
        
        return $valido;
    }

    /**
     * Valida un CIF, NIF, NIE o DNI español (validación local)
     *
     * @param string $doc Documento a validar
     * @return bool True si es válido
     */
    public function validarCIFNIFNIEDNI($doc)
    {
        if (empty($doc)) {
            return false;
        }

        $doc = strtoupper(trim($doc));

        // --- Validar NIF/DNI (8 dígitos + letra) ---
        if (preg_match('/^[0-9]{8}[A-Z]$/', $doc)) {
            $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
            $numero = substr($doc, 0, 8);
            $letra = substr($doc, -1);
            return ($letra === $letras[$numero % 23]);
        }

        // --- Validar NIE (X/Y/Z + 7 dígitos + letra) ---
        if (preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $doc)) {
            $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
            $numero = str_replace(['X', 'Y', 'Z'], ['0', '1', '2'], substr($doc, 0, 1)) . substr($doc, 1, 7);
            $letra = substr($doc, -1);
            return ($letra === $letras[$numero % 23]);
        }

        // --- Validar CIF ---
        if (preg_match('/^[ABCDEFGHJNPQRSUVW][0-9]{7}[0-9A-J]$/', $doc)) {
            $letras = 'JABCDEFGHI';
            $suma = 0;

            for ($i = 1; $i < 8; $i++) {
                $digito = (int)$doc[$i];
                if ($i % 2 == 0) {
                    $suma += $digito;
                } else {
                    $doble = $digito * 2;
                    $suma += ($doble >= 10) ? $doble - 9 : $doble;
                }
            }

            $resto = $suma % 10;
            $digitoControl = ($resto == 0) ? 0 : 10 - $resto;
            $ultimo = $doc[8];

            if (is_numeric($ultimo)) {
                return ((int)$ultimo == $digitoControl);
            } else {
                return ($ultimo == $letras[$digitoControl]);
            }
        }

        // --- NIF especiales (K, L, M) validan como DNI ---
        if (preg_match('/^[KLM][0-9]{7}[A-Z]$/', $doc)) {
            $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
            $numero = substr($doc, 1, 7);
            $letra = substr($doc, -1);
            return ($letra === $letras[$numero % 23]);
        }

        return false; // No cumple ningún formato
    }
}