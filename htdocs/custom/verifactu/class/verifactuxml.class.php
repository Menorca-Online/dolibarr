<?php
/* Copyright (C) 2025 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/verifactu/class/verifactufacturetype.class.php';

/**
 * Class VerifactuXML
 * Generador de XML para la normativa Verifactu
 */
class VerifactuXML
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

    public $xml;

    /**
     * @var array Configuración del módulo
     */
    private $config;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
        $this->loadConfig();
    }

    /**
     * Carga la configuración del módulo Verifactu
     */
    private function loadConfig()
    {
        global $conf;

        $this->config = array(
            // Información del emisor (empresa)
            'emisor_nif' => $this->getConfigValue($conf, 'MAIN_INFO_TVAINTRA') ?:
                           $this->getConfigValue($conf, 'MAIN_INFO_SIREN') ?:
                           $this->getConfigValue($conf, 'MAIN_INFO_NIF') ?: '',
            'emisor_nombre' => $this->getConfigValue($conf, 'MAIN_INFO_SOCIETE_NOM', ''),

            // Información del sistema informático
            'sistema_nombre' => $this->getConfigValue($conf, 'VERIFACTU_SISTEMA_NOMBRE', 'MENORCAONLINE S.L.'),
            'sistema_nif' => $this->getConfigValue($conf, 'VERIFACTU_SISTEMA_NIF') ?:
                            $this->getConfigValue($conf, 'MAIN_INFO_TVAINTRA') ?:
                            $this->getConfigValue($conf, 'MAIN_INFO_SIREN') ?:
                            $this->getConfigValue($conf, 'MAIN_INFO_NIF') ?: '',
            'sistema_nombre_software' => $this->getConfigValue($conf, 'VERIFACTU_SOFTWARE_NOMBRE', 'Dolibarr Verifactu'),
            'sistema_id' => $this->getConfigValue($conf, 'VERIFACTU_SISTEMA_ID', '01'),
            'sistema_version' => $this->getConfigValue($conf, 'VERIFACTU_SOFTWARE_VERSION', '1.0.0'),
            'sistema_instalacion' => $this->getConfigValue($conf, 'VERIFACTU_NUM_INSTALACION', 'DOLI' . strtoupper(substr(md5(DOL_DOCUMENT_ROOT), 0, 8))),
            'sistema_solo_verifactu' => $this->getConfigValue($conf, 'VERIFACTU_SOLO_VERIFACTU', 'S'),
            'sistema_multi_ot' => $this->getConfigValue($conf, 'VERIFACTU_MULTI_OT', 'S'),
            'sistema_indicador_multi' => $this->getConfigValue($conf, 'VERIFACTU_INDICADOR_MULTI', 'N')
        );
    }

    /**
     * Obtiene un valor de configuración de forma segura
     *
     * @param object $conf Objeto de configuración de Dolibarr
     * @param string $key Clave de configuración
     * @param string $default Valor por defecto
     * @return string Valor de configuración o valor por defecto
     */
    private function getConfigValue($conf, $key, $default = '')
    {
        return isset($conf->global->$key) ? $conf->global->$key : $default;
    }

    /**
     * Genera el XML de un RegistroAlta para una factura
     *
     * @param Facture $facture Objeto factura de Dolibarr
     * @return string XML generado
     * @throws Exception Si hay errores en la generación
     */
    public function generateRegistroAlta($facture)
    {
        // Validar factura
        if (!$facture || !$facture->id) {
            throw new Exception('Factura no válida para generar XML Verifactu');
        }

        // Cargar datos completos de la factura
        $facture->fetch_lines();
        $facture->fetch_thirdparty();

        // Obtener datos del hash de la factura
        $hashData = $this->getInvoiceHashData($facture->id);

        // Crear el documento XML con estructura SOAP
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Crear elemento raíz soapenv:Envelope con todos los namespaces
        $envelope = $dom->createElement('soapenv:Envelope');
        $envelope->setAttribute('xmlns:soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');
        $envelope->setAttribute('xmlns:sum', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd');
        $envelope->setAttribute('xmlns:sum1', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd');
        $envelope->setAttribute('xmlns:con', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/ConsultaLR.xsd');
        $dom->appendChild($envelope);

        // soapenv:Header (vacío)
        $header = $dom->createElement('soapenv:Header');
        $envelope->appendChild($header);

        // soapenv:Body
        $body = $dom->createElement('soapenv:Body');
        $envelope->appendChild($body);

        // sum:RegFactuSistemaFacturacion
        $regFactu = $dom->createElement('sum:RegFactuSistemaFacturacion');
        $body->appendChild($regFactu);

        // sum:Cabecera
        $cabecera = $dom->createElement('sum:Cabecera');
        $regFactu->appendChild($cabecera);

        // sum1:ObligadoEmision
        $obligadoEmision = $dom->createElement('sum1:ObligadoEmision');
        $cabecera->appendChild($obligadoEmision);

        $this->addElement($dom, $obligadoEmision, 'sum1:NombreRazon', $this->config['emisor_nombre']);
        $this->addElement($dom, $obligadoEmision, 'sum1:NIF', $this->config['emisor_nif']);

        // sum:RegistroFactura
        $registroFactura = $dom->createElement('sum:RegistroFactura');
        $regFactu->appendChild($registroFactura);

        // sum1:RegistroAlta
        $registroAlta = $dom->createElement('sum1:RegistroAlta');
        $registroFactura->appendChild($registroAlta);

        // 1. IDVersion
        $this->addElement($dom, $registroAlta, 'sum1:IDVersion', '1.0');

        // 2. IDFactura
        $this->addIDFactura($dom, $registroAlta, $facture);

        // 3. RefExterna (hash de la factura como referencia externa)
        $refExterna = str_pad($facture->id, 20, '0', STR_PAD_LEFT);
        $this->addElement($dom, $registroAlta, 'sum1:RefExterna', $refExterna);

        // 4. NombreRazonEmisor
        $this->addElement($dom, $registroAlta, 'sum1:NombreRazonEmisor', $this->config['emisor_nombre']);

        // 5. TipoFactura
        $tipoFactura = $this->getTipoFactura($facture);
        $this->addElement($dom, $registroAlta, 'sum1:TipoFactura', $tipoFactura);

        // 6. DescripcionOperacion
        $descripcion = $this->getDescripcionOperacion($facture);
        $this->addElement($dom, $registroAlta, 'sum1:DescripcionOperacion', $descripcion);


        if ($this->getTipoFactura($facture) != 'F2') {
            // 7. Destinatarios
            $this->addDestinatarios($dom, $registroAlta, $facture);            
        }


        // 8. Desglose
        $this->addDesglose($dom, $registroAlta, $facture);

        // 9. CuotaTotal
        $this->addElement($dom, $registroAlta, 'sum1:CuotaTotal', number_format($facture->total_tva, 2, '.', ''));

        // 10. ImporteTotal
        $this->addElement($dom, $registroAlta, 'sum1:ImporteTotal', number_format($facture->total_ttc, 2, '.', ''));


        // 11. Encadenamiento
        $this->addEncadenamiento($dom, $registroAlta, $hashData);


        // 12. SistemaInformatico
        $this->addSistemaInformatico($dom, $registroAlta);

        // 13. FechaHoraHusoGenRegistro
        $fechaHora = !empty($hashData['fechaHoraHusoGenRegistro']) ?
                     $hashData['fechaHoraHusoGenRegistro'] :
                     date('c'); // ISO 8601 format
        $this->addElement($dom, $registroAlta, 'sum1:FechaHoraHusoGenRegistro', $fechaHora);

        // 14. TipoHuella
        $this->addElement($dom, $registroAlta, 'sum1:TipoHuella', '01');

        // 15. Huella
        $huella = !empty($hashData['hash']) ? $hashData['hash'] : 'HASH_NO_GENERADO';
        $this->addElement($dom, $registroAlta, 'sum1:Huella', $huella);

        $this->xml = $dom->saveXML();
        return $this->xml;
    }

    /**
     * Obtiene los datos del hash de una factura
     */
    private function getInvoiceHashData($invoiceId)
    {
        $sql = "SELECT hash, hash_anterior, fechaHoraHusoGenRegistro, hash_data
                FROM " . MAIN_DB_PREFIX . "facture_extrafields
                WHERE fk_object = " . ((int) $invoiceId);

        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql) > 0) {
            $obj = $this->db->fetch_object($resql);
            return array(
                'hash' => $obj->hash,
                'hash_anterior' => $obj->hash_anterior,
                'fechaHoraHusoGenRegistro' => $obj->fechaHoraHusoGenRegistro,
                'hash_data' => $obj->hash_data
            );
        }

        return array();
    }

    /**
     * Agrega elemento IDFactura al XML
     */
    private function addIDFactura($dom, $parent, $facture)
    {
        $idFactura = $dom->createElement('sum1:IDFactura');

        // IDEmisorFactura
        $this->addElement($dom, $idFactura, 'sum1:IDEmisorFactura', $this->config['emisor_nif']);

        // NumSerieFactura
        $this->addElement($dom, $idFactura, 'sum1:NumSerieFactura', $facture->ref);

        // FechaExpedicionFactura
        $fechaExpedicion = date('d-m-Y', $facture->date);
        $this->addElement($dom, $idFactura, 'sum1:FechaExpedicionFactura', $fechaExpedicion);

        $parent->appendChild($idFactura);
    }

    /**
     * Obtiene el tipo de factura según Verifactu
     */
    private function getTipoFactura($facture)
    {
        // Obtener tipo desde extrafield
        $tipoId = $facture->array_options['options_fk_facture_type'] ?? null;

        if ($tipoId) {
            $verifactuType = new VerifactuFactureType($this->db);
            // Usar fetchCommon en lugar de fetch
            if ($verifactuType->fetchCommon($tipoId) > 0) {
                return $verifactuType->code;
            }
        }

        return '';

        // // Determinar tipo por defecto según el tipo de factura de Dolibarr
        // switch ($facture->type) {
        //     case 0: // Factura estándar
        //         return 'F1';
        //     case 1: // Factura de sustitución
        //         return 'F2';
        //     case 2: // Nota de crédito
        //         return 'R1';
        //     case 3: // Anticipo
        //         return 'F4';
        //     default:
        //         return 'F1';
        // }
    }

    /**
     * Genera descripción de la operación
     */
    private function getDescripcionOperacion($facture)
    {
        // Intentar obtener descripción de las líneas de factura
        $descripcion = '';

        if (!empty($facture->lines)) {
            $descripciones = array();
            foreach ($facture->lines as $line) {
                if (!empty($line->desc)) {
                    $descripciones[] = $line->desc;
                }
            }
            $descripcion = implode('; ', array_slice($descripciones, 0, 3)); // Máximo 3 descripciones
        }

        // Si no hay descripción, usar una genérica
        if (empty($descripcion)) {
            $descripcion = 'Prestación de servicios';
        }

        // Limitar longitud y limpiar caracteres especiales
        $descripcion = substr($descripcion, 0, 500);
        $descripcion = htmlspecialchars($descripcion, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        return $descripcion;
    }

    /**
     * Agrega elemento Destinatarios al XML
     */
    private function addDestinatarios($dom, $parent, $facture)
    {
        $destinatarios = $dom->createElement('sum1:Destinatarios');

        $idDestinatario = $dom->createElement('sum1:IDDestinatario');

        // NombreRazon del destinatario
        $nombreDestinatario = $facture->thirdparty->name ?: $facture->thirdparty->nom;
        $this->addElement($dom, $idDestinatario, 'sum1:NombreRazon', $nombreDestinatario);

        // NIF del destinatario
        $nifDestinatario = $facture->thirdparty->tva_intra ?: $facture->thirdparty->idprof1;
        if (!empty($nifDestinatario)) {
            $this->addElement($dom, $idDestinatario, 'sum1:NIF', $nifDestinatario);
        }

        $destinatarios->appendChild($idDestinatario);
        $parent->appendChild($destinatarios);
    }

    /**
     * Agrega elemento Desglose al XML
     */
    private function addDesglose($dom, $parent, $facture)
    {
        $desglose = $dom->createElement('sum1:Desglose');

        // Agrupar líneas por tipo de IVA
        $desgloseData = $this->agruparPorTipoIVA($facture);

        foreach ($desgloseData as $tipoIva => $data) {
            $detalleDesglose = $dom->createElement('sum1:DetalleDesglose');

            $this->addElement($dom, $detalleDesglose, 'sum1:Impuesto', '01'); // IVA
            $this->addElement($dom, $detalleDesglose, 'sum1:ClaveRegimen', '01'); // Régimen general
            $this->addElement($dom, $detalleDesglose, 'sum1:CalificacionOperacion', $this->getCalificacionOperacion($tipoIva));
            $this->addElement($dom, $detalleDesglose, 'sum1:TipoImpositivo', number_format($tipoIva, 0));
            $this->addElement($dom, $detalleDesglose, 'sum1:BaseImponibleOimporteNoSujeto', number_format($data['base'], 2, '.', ''));
            $this->addElement($dom, $detalleDesglose, 'sum1:CuotaRepercutida', number_format($data['cuota'], 2, '.', ''));

            $desglose->appendChild($detalleDesglose);
        }

        $parent->appendChild($desglose);
    }

    /**
     * Agrupa las líneas de factura por tipo de IVA
     */
    private function agruparPorTipoIVA($facture)
    {
        $desglose = array();

        foreach ($facture->lines as $line) {
            $tipoIva = $line->tva_tx;

            if (!isset($desglose[$tipoIva])) {
                $desglose[$tipoIva] = array('base' => 0, 'cuota' => 0);
            }

            $desglose[$tipoIva]['base'] += $line->total_ht;
            $desglose[$tipoIva]['cuota'] += $line->total_tva;
        }

        return $desglose;
    }

    /**
     * Obtiene la calificación de la operación según el tipo de IVA
     */
    private function getCalificacionOperacion($tipoIva)
    {
        if ($tipoIva == 0) {
            return 'E1'; // Exenta
        }
        return 'S1'; // Sujeta
    }

    /**
     * Agrega elemento Encadenamiento al XML
     */
    private function addEncadenamiento($dom, $parent, $hashData)
    {
        $encadenamiento = $dom->createElement('sum1:Encadenamiento');
        $facturaAnterior = $this->getFacturaAnterior($hashData['hash_anterior']);


        if ($facturaAnterior) {
            $registroAnterior = $dom->createElement('sum1:RegistroAnterior');
            $this->addElement($dom, $registroAnterior, 'sum1:IDEmisorFactura', $this->config['emisor_nif']);
            $this->addElement($dom, $registroAnterior, 'sum1:NumSerieFactura', $facturaAnterior['ref']);
            $this->addElement($dom, $registroAnterior, 'sum1:FechaExpedicionFactura', $facturaAnterior['fecha']);
            $this->addElement($dom, $registroAnterior, 'sum1:Huella', $hashData['hash_anterior']);
            $encadenamiento->appendChild($registroAnterior);
        }else {
            // Si no se encuentra la factura anterior, crear un nodo vacío
            $primerRegistro = $dom->createElement('sum1:PrimerRegistro', 'S');
            $encadenamiento->appendChild($primerRegistro);
        }
        $parent->appendChild($encadenamiento);
    }

    /**
     * Obtiene datos de la factura anterior basándose en el hash anterior
     */
    private function getFacturaAnterior($hashAnterior)
    {
        $sql = "SELECT f.ref, DATE_FORMAT(f.datef, '%d-%m-%Y') as fecha
                FROM " . MAIN_DB_PREFIX . "facture f
                INNER JOIN " . MAIN_DB_PREFIX . "facture_extrafields fe ON fe.fk_object = f.rowid
                WHERE fe.hash = '" . $this->db->escape($hashAnterior) . "'";

        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql) > 0) {
            $obj = $this->db->fetch_object($resql);
            return array(
                'ref' => $obj->ref,
                'fecha' => $obj->fecha
            );
        }

        return null;
    }

    /**
     * Agrega elemento SistemaInformatico al XML
     */
    private function addSistemaInformatico($dom, $parent)
    {
        $sistema = $dom->createElement('sum1:SistemaInformatico');

        $this->addElement($dom, $sistema, 'sum1:NombreRazon', $this->config['sistema_nombre']);
        $this->addElement($dom, $sistema, 'sum1:NIF', $this->config['sistema_nif']);
        $this->addElement($dom, $sistema, 'sum1:NombreSistemaInformatico', $this->config['sistema_nombre_software']);
        $this->addElement($dom, $sistema, 'sum1:IdSistemaInformatico', $this->config['sistema_id']);
        $this->addElement($dom, $sistema, 'sum1:Version', $this->config['sistema_version']);
        $this->addElement($dom, $sistema, 'sum1:NumeroInstalacion', $this->config['sistema_instalacion']);
        $this->addElement($dom, $sistema, 'sum1:TipoUsoPosibleSoloVerifactu', $this->config['sistema_solo_verifactu']);
        $this->addElement($dom, $sistema, 'sum1:TipoUsoPosibleMultiOT', $this->config['sistema_multi_ot']);
        $this->addElement($dom, $sistema, 'sum1:IndicadorMultiplesOT', $this->config['sistema_indicador_multi']);

        $parent->appendChild($sistema);
    }

    /**
     * Genera timestamp en formato ISO 8601
     */
    private function generateTimestamp()
    {
        return date('c'); // ISO 8601 format: 2025-09-18T17:15:41+02:00
    }

    /**
     * Agrega un elemento al DOM
     */
    private function addElement($dom, $parent, $name, $value)
    {
        $element = $dom->createElement($name);
        $element->appendChild($dom->createTextNode($value));
        $parent->appendChild($element);
    }

    public function send()
    {
        global $conf;

        $return = "";
        $url = "https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP?op=RegFactuSistemaFacturacion";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: text/xml; charset=utf-8",
            "SOAPAction: RegFactuSistemaFacturacion"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 
        $certPath = DOL_DATA_ROOT . '/verifactu/certs/cert.pem';
        $keyPath = DOL_DATA_ROOT . '/verifactu/certs/key.pem';

        curl_setopt($ch, CURLOPT_SSLCERT, $certPath);
        curl_setopt($ch, CURLOPT_SSLKEY, $keyPath);

        // Debug si quieres ver errores SSL
        curl_setopt($ch, CURLOPT_VERBOSE, true);
 
        $response = curl_exec($ch);
        if ($response === false) {
            $return = 'Error en cURL: ' . curl_error($ch);
        } else {
            $return = $response;
        }

        curl_close($ch);
        return $return;
    }

}




