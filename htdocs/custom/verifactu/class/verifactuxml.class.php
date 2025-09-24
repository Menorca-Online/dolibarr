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

    private $facture;

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
        $this->facture = $facture;

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

        //si el pais es <> 'ES' hay que añadir IDOtro
        $countryCode = $facture->thirdparty->country_code ?? '';
        if ($countryCode !== 'ES') {
            //creamos un nodo IDOtro ad Destinatario
            $idOtro = $dom->createElement('sum1:IDOtro');
            $idDestinatario->appendChild($idOtro);
            //añadimos el CodigoPais
            $this->addElement($dom, $idOtro, 'sum1:CodigoPais', $countryCode);
            //añadimos el IDType
            $this->addElement($dom, $idOtro, 'sum1:IDType', '04'); 
            //añadimos el ID
            $this->addElement($dom, $idOtro, 'sum1:ID', $facture->thirdparty->idprof1 ?: $facture->thirdparty->tva_intra ?: 'NIF_NO_PROPORCIONADO');
        }else{
            $nifDestinatario = $facture->thirdparty->tva_intra ?: $facture->thirdparty->idprof1;
            if (!empty($nifDestinatario)) {
                $this->addElement($dom, $idDestinatario, 'sum1:NIF', $nifDestinatario);
            }
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
            $this->addElement($dom, $detalleDesglose, 'sum1:ClaveRegimen', $data['claveRegimen']);
            $this->addElement($dom, $detalleDesglose, 'sum1:CalificacionOperacion', $data['calificacion']);
            $this->addElement($dom, $detalleDesglose, 'sum1:OperacionExenta', $data['operacionExenta']);
            $this->addElement($dom, $detalleDesglose, 'sum1:TipoImpositivo', number_format($tipoIva, 2, '.', ''));
            $this->addElement($dom, $detalleDesglose, 'sum1:BaseImponibleOimporteNoSujeto', number_format($data['base'], 2, '.', ''));
            $this->addElement($dom, $detalleDesglose, 'sum1:CuotaRepercutida', number_format($data['cuota'], 2, '.', ''));




            $desglose->appendChild($detalleDesglose);
        }

        $parent->appendChild($desglose);
    }

    /**
     * Agrupa las líneas de factura por tipo de IVA
     * L9
    Valores	Descripción
    S1	Operación Sujeta y No exenta - Sin inversión del sujeto pasivo.
    S2	Operación Sujeta y No exenta - Con Inversión del sujeto pasivo.
    N1	Operación No Sujeta artículo 7, 14, otros.
    N2	Operación No Sujeta por Reglas de localización.
     */
private function agruparPorTipoIVA($facture)
{
    $desglose = array();
    $soc = $facture->thirdparty;
    $countryCode = $soc->country_code ?? '';
    $tvaIntra = trim($soc->tva_intra);

    foreach ($facture->lines as $line) {
        $tipoIva = (float) $line->tva_tx;

        if (!isset($desglose[$tipoIva])) {
            $desglose[$tipoIva] = array(
                'base' => 0,
                'cuota' => 0,
                'claveRegimen' => null,
                'calificacion' => null,
                'operacionExenta' => null
            );
        }

        $desglose[$tipoIva]['base'] += $line->total_ht;
        $desglose[$tipoIva]['cuota'] += $line->total_tva;
    }

    // Añadir régimen y calificación según reglas AEAT
    foreach ($desglose as $tipoIva => &$data) {

        if ($tipoIva > 0) {
            // Con IVA -> sujeta y no exenta
            $data['claveRegimen'] = '01'; // Régimen general
            $data['calificacion'] = 'S1';
            $data['operacionExenta'] = null; // no aplica
        } else {

            if ($countryCode === 'ES') {
                // Exenta nacional (ej: art. 20 LIVA)
                $data['claveRegimen'] = '01';
                $data['calificacion'] = 'S1'; // sigue siendo sujeta
                $data['operacionExenta'] = 'E1'; // por art. 20
            } elseif (!empty($tvaIntra) && in_array($countryCode, array(
                'DE','FR','IT','PT','NL','BE','LU','AT','IE','DK','SE','FI','PL','CZ','SK',
                'HU','RO','BG','HR','SI','EE','LV','LT','CY','MT','GR'
            ))) {
                // Cliente UE con VAT válido -> no sujeta por localización
                $data['claveRegimen'] = '01';
                $data['calificacion'] = 'N2';
                $data['operacionExenta'] = null;
            } elseif (in_array($countryCode, array(
                'DE','FR','IT','PT','NL','BE','LU','AT','IE','DK','SE','FI','PL','CZ','SK',
                'HU','RO','BG','HR','SI','EE','LV','LT','CY','MT','GR'
            ))) {
                // Cliente UE sin VAT -> debería llevar IVA español
                $data['claveRegimen'] = '01';
                $data['calificacion'] = 'S1';
                $data['operacionExenta'] = null;
            } else {
                // Exportaciones -> exentas por art. 21 LIVA
                $data['claveRegimen'] = '02';
                $data['calificacion'] = 'S1'; // sujeta, pero exenta
                $data['operacionExenta'] = 'E2';
            }
        }
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
	    $verifactu_dir = DOL_DATA_ROOT.'/verifactu';
        $outbox_dir = $verifactu_dir.'/OUTBOX';
	    $inbox_dir = $verifactu_dir.'/INBOX';
        //guardamos el xml a enviar en OUTBOX con el nombre de la factura
        $file = $outbox_dir.'/'.$this->facture->ref.'.xml';
        file_put_contents($file, $this->xml);
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
            // Guardar error en INBOX
            $errorFile = $inbox_dir.'/'.$this->facture->ref.'_error.txt';
            file_put_contents($errorFile, $return);
        } else {
            $return = $response;
            // Guardar respuesta en INBOX
            $responseFile = $inbox_dir.'/'.$this->facture->ref.'_response.xml';
            file_put_contents($responseFile, $response);
        }

        curl_close($ch);
        return $return;
    }

    /**
     * Vincula los archivos generados como documentos relacionados de la factura
     * 
     * @param string $xmlFile Ruta del archivo XML generado
     * @param string $responseFile Ruta del archivo de respuesta (opcional)
     * @param string $errorFile Ruta del archivo de error (opcional)
     * @return bool True si se vinculó correctamente
     */
    public function linkFilesToInvoice($xmlFile = null, $responseFile = null, $errorFile = null)
    {
        if (!$this->facture) {
            return false;
        }

        require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
        
        $result = true;
        
        // Vincular archivo XML de envío
        if ($xmlFile && file_exists($xmlFile)) {
            $result &= $this->addFileToInvoice($xmlFile, 'Verifactu XML');
        }
        
        // Vincular archivo de respuesta
        if ($responseFile && file_exists($responseFile)) {
            $result &= $this->addFileToInvoice($responseFile, 'Verifactu Response');
        }
        
        // Vincular archivo de error
        if ($errorFile && file_exists($errorFile)) {
            $result &= $this->addFileToInvoice($errorFile, 'Verifactu Error');
        }
        
        return $result;
    }

    /**
     * Añade un archivo específico como documento relacionado de la factura
     * 
     * @param string $filePath Ruta completa del archivo
     * @param string $description Descripción del archivo
     * @return bool True si se añadió correctamente
     */
    private function addFileToInvoice($filePath, $description = '')
    {
        global $conf, $user;
        
        if (!file_exists($filePath)) {
            return false;
        }
        
        $fileName = basename($filePath);
        $fileSize = filesize($filePath);
        
        // Directorio de destino para documentos de la factura
        $upload_dir = $conf->facture->multidir_output[$this->facture->entity].'/'.$this->facture->ref;
        
        // Crear directorio si no existe
        if (!is_dir($upload_dir)) {
            if (dol_mkdir($upload_dir) < 0) {
                return false;
            }
        }
        
        // Copiar archivo al directorio de documentos de la factura
        $destFile = $upload_dir.'/'.$fileName;
        if (!copy($filePath, $destFile)) {
            return false;
        }
        
        // Registrar el archivo en la base de datos
        require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
        $ecmfile = new EcmFiles($this->db);
        
        $ecmfile->filepath = $this->facture->ref;
        $ecmfile->filename = $fileName;
        $ecmfile->label = $description;
        $ecmfile->fullpath_orig = $filePath;
        $ecmfile->gen_or_uploaded = 'uploaded';
        $ecmfile->description = $description;
        $ecmfile->keywords = 'verifactu';
        $ecmfile->cover = 0;
        $ecmfile->position = 0;
        $ecmfile->acl = '';
        $ecmfile->date_c = dol_now();
        $ecmfile->date_m = dol_now();
        $ecmfile->fk_user_c = $user->id;
        $ecmfile->fk_user_m = $user->id;
        $ecmfile->src_object_type = 'facture';
        $ecmfile->src_object_id = $this->facture->id;
        
        return $ecmfile->create($user) > 0;
    }

    /**
     * Envía el XML a la AEAT y vincula todos los archivos generados a la factura
     * 
     * @return string Respuesta del envío
     */
    public function enviarYVincular()
    {
        global $conf;
        
        // Generar rutas de archivos
        $verifactu_dir = DOL_DATA_ROOT.'/verifactu';
        $outbox_dir = $verifactu_dir.'/OUTBOX';
        $inbox_dir = $verifactu_dir.'/INBOX';
        
        $xmlFile = $outbox_dir.'/'.$this->facture->ref.'.xml';
        $responseFile = $inbox_dir.'/'.$this->facture->ref.'_response.xml';
        $errorFile = $inbox_dir.'/'.$this->facture->ref.'_error.txt';
        
        // Enviar XML
        $response = $this->send();
        
        // Determinar qué archivos se generaron
        $files = array();
        if (file_exists($xmlFile)) {
            $files['xml'] = $xmlFile;
        }
        if (file_exists($responseFile)) {
            $files['response'] = $responseFile;
        }
        if (file_exists($errorFile)) {
            $files['error'] = $errorFile;
        }
        
        // Vincular archivos a la factura
        $this->linkFilesToInvoice(
            isset($files['xml']) ? $files['xml'] : null,
            isset($files['response']) ? $files['response'] : null,
            isset($files['error']) ? $files['error'] : null
        );
        
        return $response;
    }

    /**
     * Obtiene la lista de archivos Verifactu vinculados a la factura
     * 
     * @return array Lista de archivos vinculados
     */
    public function getLinkedFiles()
    {
        if (!$this->facture) {
            return array();
        }

        require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
        
        $sql = "SELECT filepath, filename, label, date_c, fk_user_c";
        $sql .= " FROM ".MAIN_DB_PREFIX."ecm_files";
        $sql .= " WHERE src_object_type = 'facture'";
        $sql .= " AND src_object_id = ".$this->facture->id;
        $sql .= " AND (keywords LIKE '%verifactu%' OR filename LIKE '%".$this->facture->ref."%')";
        $sql .= " ORDER BY date_c DESC";
        
        $result = $this->db->query($sql);
        $files = array();
        
        if ($result) {
            while ($obj = $this->db->fetch_object($result)) {
                $files[] = array(
                    'filepath' => $obj->filepath,
                    'filename' => $obj->filename,
                    'label' => $obj->label,
                    'date_c' => $obj->date_c,
                    'fk_user_c' => $obj->fk_user_c
                );
            }
        }
        
        return $files;
    }

    /**
     * Obtiene la URL de descarga de un archivo vinculado a la factura
     * 
     * @param string $filename Nombre del archivo
     * @return string URL de descarga
     */
    public function getDownloadUrl($filename)
    {
        if (!$this->facture) {
            return '';
        }
        
        global $conf;
        
        return DOL_URL_ROOT.'/document.php?modulepart=facture&file='.urlencode($this->facture->ref.'/'.$filename);
    }

}




