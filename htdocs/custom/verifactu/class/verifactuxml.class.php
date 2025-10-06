<?php
/* Copyright (C) 2025 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactufacturetype.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactuclaveoperacion.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactuclaveexencion.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactuclaveregimen.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/lib/verifactu.lib.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifacturegistroestado.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactubatch.class.php';
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

    private $registro;

    private $batch;

    /**
     * @var array Configuración del módulo
     */
    private $config;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db, VerifactuBatch $batch)
    {
        $this->db = $db;
        $this->batch = $batch;
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
            'sistema_nombre' => 'Menorca Online S.L.',
            'sistema_nif' => 'B57479677',
            'sistema_nombre_software' => 'Eco Verifactu',
            'sistema_id' => $this->getConfigValue($conf, 'VERIFACTU_SISTEMA_ID', '01'),
            'sistema_version' => $this->getConfigValue($conf, 'VERIFACTU_SOFTWARE_VERSION', '1.0.0'),
            'sistema_instalacion' => $this->getConfigValue($conf, 'VERIFACTU_NUM_INSTALACION', 'DOLI' . strtoupper(substr(md5(DOL_DOCUMENT_ROOT), 0, 8))),
            'sistema_solo_verifactu' => 'S',
            'sistema_multi_ot' => 'N',
            'sistema_indicador_multi' => 'N'
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
     * @param VerifactuFacturaRegistro $registro Objeto registro de Dolibarr
     * @param Facture $facture Objeto factura de Dolibarr
     * @return string XML generado
     * @throws Exception Si hay errores en la generación
     */
    public function generateRegistroFactura($dom, $parent, $registro, $facture)
    {

        $registroFactura = $dom->createElement('sum:RegistroFactura');
        $parent->appendChild($registroFactura);

        $hashData = json_decode($registro->hash_data, true);


        // sum1:RegistroAlta
        $registroAlta = $dom->createElement('sum1:RegistroAlta');
        $registroFactura->appendChild($registroAlta);

        // 1. IDVersion
        $this->addElement($dom, $registroAlta, 'sum1:IDVersion', '1.0');

        // 2. IDFactura
        $this->addIDFactura($dom, $registroAlta, $facture);

        // 3. RefExterna (hash de la factura como referencia externa)

        $refExterna = str_pad($registro->id, 20, '0', STR_PAD_LEFT);
        $this->addElement($dom, $registroAlta, 'sum1:RefExterna', $refExterna);

        // 4. NombreRazonEmisor
        $this->addElement($dom, $registroAlta, 'sum1:NombreRazonEmisor', $this->config['emisor_nombre']);

        if ($registro->operation == VERIFACTU_OPERACION_REGISTRO_ALTA_SUBSANACION || $registro->operation == VERIFACTU_OPERACION_REGISTRO_ALTA_SUBSANACION_RECHAZADA) {
            $this->addElement($dom, $registroAlta, 'sum1:Subsanacion', 'S');
            if ($registro->operation == VERIFACTU_OPERACION_REGISTRO_ALTA_SUBSANACION_RECHAZADA) {
                $this->addElement($dom, $registroAlta, 'sum1:RechazoPrevio', 'S');
            }
            //$this->addElement($dom, $registroAlta, 'sum1:RechazoPrevio', 'N');
        }

        // 5. TipoFactura
        $tipoFactura = $this->getTipoFactura($facture);
        $this->addElement($dom, $registroAlta, 'sum1:TipoFactura', $tipoFactura);


        if ($facture->fk_facture_source != null && $facture->fk_facture_source > 0) {
            $this->addElement($dom, $registroAlta, 'sum1:TipoRectificativa', 'I');

            $factureSource = new Facture($this->db);
            $factureSource->fetch($facture->fk_facture_source);
            $FacturasRectificadas = $dom->createElement('sum1:FacturasRectificadas');
            $registroAlta->appendChild($FacturasRectificadas);
            $IDFacturaRectificada  = $dom->createElement('sum1:IDFacturaRectificada');
            $this->addElement($dom, $IDFacturaRectificada, 'sum1:IDEmisorFactura', $this->config['emisor_nif']);
            $this->addElement($dom, $IDFacturaRectificada, 'sum1:NumSerieFactura', $factureSource->ref);
            $this->addElement($dom, $IDFacturaRectificada, 'sum1:FechaExpedicionFactura', date('d-m-Y', $factureSource->date));
            $FacturasRectificadas->appendChild($IDFacturaRectificada);
        }

        // 6. DescripcionOperacion
        $descripcion = $this->getDescripcionOperacion($facture);
        $this->addElement($dom, $registroAlta, 'sum1:DescripcionOperacion', $descripcion);


        if ($this->getTipoFactura($facture) != 'F2' && $this->getTipoFactura($facture) != 'R5') {
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
        $this->addEncadenamiento($dom, $registroAlta, $registro);


        // 12. SistemaInformatico
        $this->addSistemaInformatico($dom, $registroAlta);

        // 13. FechaHoraHusoGenRegistro
        $fechaHora = $hashData['FechaHoraHusoGenRegistro'];

        $this->addElement($dom, $registroAlta, 'sum1:FechaHoraHusoGenRegistro', $fechaHora);

        // 14. TipoHuella
        $this->addElement($dom, $registroAlta, 'sum1:TipoHuella', '01');

        // 15. Huella
        $huella = $registro->hash;
        $this->addElement($dom, $registroAlta, 'sum1:Huella', $huella);

        //$this->addSignature($dom, $registroAlta);

    }





    /**
     * Genera el XML de un RegistroAlta para una factura
     *
     * @param VerifactuFacturaRegistro $registro Objeto factura de Dolibarr
     * @return string XML generado
     * @throws Exception Si hay errores en la generación
     */
    public function generateEnvioRegistroAlta($registro)
    {

        global $conf;

        // Validar registro
        if (!$registro || !$registro->factureid) {
            throw new Exception('Registro no válido para generar XML Verifactu');
        }
        $this->registro = $registro;
        $this->facture = new Facture($this->db);
        $this->facture->fetch($registro->factureid);

        // Cargar datos completos de la factura
        $this->facture->fetch_lines();
        $this->facture->fetch_thirdparty();



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
        if ($conf->global->VERIFACTU_PODER_AEAT == "1") {
            // sum1:Representante
            $representante = $dom->createElement('sum1:Representante');
            $cabecera->appendChild($representante);
            $this->addElement($dom, $representante, 'sum1:NombreRazon', 'MENORCA ONLINE SL');
            $this->addElement($dom, $representante, 'sum1:NIF', 'B57479677');
        }

        $this->generateRegistroFactura($dom, $regFactu, $registro, $this->facture);

        $this->xml = $dom->saveXML();
        return $this->xml;
    }

    private function generateEnvioFromBatch()
    {
        global $conf;
        if (!$this->batch || !$this->batch->id) {
            throw new Exception('Batch no válido para generar XML Verifactu');
        }

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
        if ($conf->global->VERIFACTU_PODER_AEAT == "1") {
            // sum1:Representante
            $representante = $dom->createElement('sum1:Representante');
            $cabecera->appendChild($representante);
            $this->addElement($dom, $representante, 'sum1:NombreRazon', 'MENORCA ONLINE SL');
            $this->addElement($dom, $representante, 'sum1:NIF', 'B57479677');
        }
        foreach ($this->batch->registros() as $registro) {
            $facture = new Facture($this->db);
            $facture->fetch($registro->factureid);
            $facture->fetch_lines();
            $facture->fetch_thirdparty();
            $this->generateRegistroFactura($dom, $regFactu, $registro, $facture);
        }
        $this->xml = $dom->saveXML();
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
        } else {
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
        $desgloseData = $this->obtenerDesgloseFactura($facture);


        foreach ($desgloseData as $data) {

            $detalleDesglose = $dom->createElement('sum1:DetalleDesglose');
            $this->addElement($dom, $detalleDesglose, 'sum1:Impuesto', '01'); // IVA
            $this->addElement($dom, $detalleDesglose, 'sum1:ClaveRegimen', $data['ClaveRegimen']);
            if (!empty($data['CalificacionOperacion'])) {
                $this->addElement($dom, $detalleDesglose, 'sum1:CalificacionOperacion', $data['CalificacionOperacion']);
            }
            if (isset($data['OperacionExenta']) && $data['OperacionExenta']  != null) {
                $this->addElement($dom, $detalleDesglose, 'sum1:OperacionExenta', $data['OperacionExenta']);
            }
            if (in_array($data['CalificacionOperacion'], array('S1', 'S2'))) {
                $this->addElement($dom, $detalleDesglose, 'sum1:TipoImpositivo', number_format($data['TipoImpositivo'], 2, '.', ''));
            }
            $this->addElement($dom, $detalleDesglose, 'sum1:BaseImponibleOimporteNoSujeto', number_format($data['BaseImponibleOimporteNoSujeto'], 2, '.', ''));
            if (isset($data['BaseImponibleACoste']) && $data['BaseImponibleACoste'] > 0)
                $this->addElement($dom, $detalleDesglose, 'sum1:BaseImponibleACoste', number_format($data['BaseImponibleACoste'], 2, '.', ''));

            if (in_array($data['CalificacionOperacion'], array('S1', 'S2'))) {
                $this->addElement($dom, $detalleDesglose, 'sum1:CuotaRepercutida', number_format($data['CuotaRepercutida'], 2, '.', ''));
            }

            if (isset($data['TipoRecargoEquivalencia']) && $data['TipoRecargoEquivalencia'] > 0 && in_array($data['CalificacionOperacion'], array('S1', 'S2'))) {
                $this->addElement($dom, $detalleDesglose, 'sum1:TipoRecargoEquivalencia', number_format($data['TipoRecargoEquivalencia'], 2, '.', ''));
                $this->addElement($dom, $detalleDesglose, 'sum1:CuotaRecargoEquivalencia', number_format($data['CuotaRecargoEquivalencia'], 2, '.', ''));
            }



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
    private function obtenerDesgloseFactura($facture)
    {
        $desglose = array();

        // Obtener datos del cliente
        $soc = $facture->thirdparty;
        $countryCode = $soc->country_code ?? '';
        $tvaIntra = trim($soc->tva_intra);

        foreach ($facture->lines as $line) {

            $tipoIva = (float) $line->tva_tx;
            $clave_regimen = $line->array_options['options_fk_clave_regimen'] ?? 0;
            $clave_operacion = $line->array_options['options_fk_clave_operacion'] ?? 0;
            $clave_exencion = $line->array_options['options_fk_clave_exencion'] ?? 0;


            if (!isset($desglose[$tipoIva][$clave_regimen][$clave_operacion][$clave_exencion])) {
                $desglose[$tipoIva][$clave_regimen][$clave_operacion][$clave_exencion] = array(
                    'BaseImponibleOimporteNoSujeto' => 0,
                    'CuotaRepercutida' => 0,
                    'tipoRecargoEquivalencia' => 0,
                    'cuotaRecargoEquivalencia' => 0,
                    'impuesto' => '01', //IVA
                    'fk_clave_regimen' => $clave_regimen,
                    'fk_clave_operacion' => $clave_operacion,
                    'fk_clave_exencion' => $clave_exencion,
                );
            }

            $desglose[$tipoIva][$clave_regimen][$clave_operacion][$clave_exencion]['BaseImponibleOimporteNoSujeto'] += $line->total_ht;
            $desglose[$tipoIva][$clave_regimen][$clave_operacion][$clave_exencion]['CuotaRepercutida'] += $line->total_tva;
            if ($line->localtax1_tx > 0) {
                $desglose[$tipoIva][$clave_regimen][$clave_operacion][$clave_exencion]['tipoRecargoEquivalencia'] = (float)$line->localtax1_tx;
                $desglose[$tipoIva][$clave_regimen][$clave_operacion][$clave_exencion]['cuotaRecargoEquivalencia'] += (float)$line->total_localtax1;
            }
        }

        $claveExencionObj = new VerifactuClaveExencion($this->db);
        $claveRegimenObj = new VerifactuClaveRegimen($this->db);
        $claveOperacionObj = new VerifactuClaveOperacion($this->db);


        //extraemos los ultimos desgloses
        $finalDesglose = array();
        foreach ($desglose as $tipoIva => $regimenes) {
            foreach ($regimenes as $clave_regimen => $operaciones) {
                foreach ($operaciones as $clave_operacion => $exenciones) {
                    foreach ($exenciones as $clave_exencion => $data) {
                        $claveExencionObj->fetchCommon($data['fk_clave_exencion']);
                        $claveRegimenObj->fetchCommon($data['fk_clave_regimen']);
                        $claveOperacionObj->fetchCommon($data['fk_clave_operacion']);
                        $finalDesglose[] = array(
                            'TipoImpositivo' => $tipoIva,
                            'BaseImponibleOimporteNoSujeto' => $data['BaseImponibleOimporteNoSujeto'],
                            'CuotaRepercutida' => $data['CuotaRepercutida'],
                            'CuotaRecargoEquivalencia' => $data['cuotaRecargoEquivalencia'],
                            'TipoRecargoEquivalencia' => $data['tipoRecargoEquivalencia'],
                            'ClaveRegimen' => $claveRegimenObj->code ?: null,
                            'CalificacionOperacion' => $claveOperacionObj->code ?: null,
                            'OperacionExenta' => $claveExencionObj->code ?: null,
                        );
                    }
                }
            }
        }


        return $finalDesglose;
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
    private function addEncadenamiento($dom, $parent, VerifactuFacturaRegistro $registro)
    {

        $encadenamiento = $dom->createElement('sum1:Encadenamiento');

        $registroAnteriorData = $registro->getPreviousRegister();

        if ($registroAnteriorData) {
            $data = json_decode($registroAnteriorData->hash_data, true);
            if ($data) {
                $registroAnterior = $dom->createElement('sum1:RegistroAnterior');
                $this->addElement($dom, $registroAnterior, 'sum1:IDEmisorFactura', $this->config['emisor_nif']);
                $this->addElement($dom, $registroAnterior, 'sum1:NumSerieFactura', $data['NumSerieFactura'] ? $data['NumSerieFactura'] : $data['NumSerieFacturaAnulada']);
                $this->addElement($dom, $registroAnterior, 'sum1:FechaExpedicionFactura', $data['FechaExpedicionFactura'] ? $data['FechaExpedicionFactura'] : $data['FechaExpedicionFacturaAnulada']);
                $this->addElement($dom, $registroAnterior, 'sum1:Huella', $registroAnteriorData->hash);
                $encadenamiento->appendChild($registroAnterior);
            }
        } else {
            $primerRegistro = $dom->createElement('sum1:PrimerRegistro', 'S');
            $encadenamiento->appendChild($primerRegistro);
        }
        $parent->appendChild($encadenamiento);
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
        $verifactu_dir = DOL_DATA_ROOT . '/verifactu';
        $outbox_dir = $verifactu_dir . '/OUTBOX';
        $inbox_dir = $verifactu_dir . '/INBOX';
        //guardamos el xml a enviar en OUTBOX con el nombre de la factura
        $file = $outbox_dir . '/' . $this->facture->ref . '.xml';
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
            $errorFile = $inbox_dir . '/' . $this->facture->ref . '_error.txt';
            file_put_contents($errorFile, $return);
        } else {
            $return = $response;
            // Guardar respuesta en INBOX
            $responseFile = $inbox_dir . '/' . $this->facture->ref . '_response.xml';
            file_put_contents($responseFile, $response);

            // Procesar la respuesta si se proporciona el registro
            if ($this->registro) {
                $this->processResponse($response, $this->registro);
            }
        }

        curl_close($ch);
        return $return;
    }

    private function addSignature($dom, $registroAlta)
    {
        // Canonicalizar el XML de RegistroAlta (sin la firma aún)
        $c14nData = $registroAlta->C14N(true, false);

        $certPath = DOL_DATA_ROOT . '/verifactu/certs/cert.pem';
        $keyPath = DOL_DATA_ROOT . '/verifactu/certs/key.pem';

        // Cargar clave privada y certificado

        $privateKey = openssl_pkey_get_private("file://$keyPath");
        $certData = file_get_contents($certPath);



        // Firmar el bloque canonicalizado
        openssl_sign($c14nData, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        // Base64
        $signatureValue = base64_encode($signature);
        $certBase64 = base64_encode(
            str_replace(["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\n", "\r"], "", $certData)
        );

        // Crear nodo ds:Signature
        $sigNode = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Signature');

        $signedInfo = $dom->createElement('ds:SignedInfo');
        $canonMethod = $dom->createElement('ds:CanonicalizationMethod');
        $canonMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');

        $sigMethod = $dom->createElement('ds:SignatureMethod');
        $sigMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');

        $reference = $dom->createElement('ds:Reference');
        $reference->setAttribute('URI', ''); // Enveloped signature sobre el nodo actual

        $transforms = $dom->createElement('ds:Transforms');
        $transform = $dom->createElement('ds:Transform');
        $transform->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($transform);

        $digestMethod = $dom->createElement('ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');

        $digestValue = $dom->createElement('ds:DigestValue', base64_encode(hash('sha256', $c14nData, true)));

        $reference->appendChild($transforms);
        $reference->appendChild($digestMethod);
        $reference->appendChild($digestValue);

        $signedInfo->appendChild($canonMethod);
        $signedInfo->appendChild($sigMethod);
        $signedInfo->appendChild($reference);

        // Firma final
        $sigNode->appendChild($signedInfo);
        $sigNode->appendChild($dom->createElement('ds:SignatureValue', $signatureValue));

        // KeyInfo con certificado
        $keyInfo = $dom->createElement('ds:KeyInfo');
        $x509Data = $dom->createElement('ds:X509Data');
        $x509Cert = $dom->createElement('ds:X509Certificate', $certBase64);
        $x509Data->appendChild($x509Cert);
        $keyInfo->appendChild($x509Data);

        $sigNode->appendChild($keyInfo);

        // Insertar al final de RegistroAlta
        $registroAlta->appendChild($sigNode);
    }




    public function sendBatch()
    {
        global $conf;


        if (!$this->batch || !$this->batch->id) {
            throw new Exception('Batch no válido para enviar a Verifactu');
        }
        $relatedFactures = $this->batch->getRegistrosFactures();

        $this->generateEnvioFromBatch();

        $verifactu_dir = DOL_DATA_ROOT . '/verifactu';
        $outbox_dir = $verifactu_dir . '/OUTBOX';
        $inbox_dir = $verifactu_dir . '/INBOX';
        //guardamos el xml a enviar en OUTBOX con el nombre de la factura
        $file = $outbox_dir . '/batch_' . str_pad($this->batch->id, 20, '0', STR_PAD_LEFT) . '.xml';
        file_put_contents($file, $this->xml);

        //vinculamos el archivo a las facturas
        $this->addFileToInvoice($file, 'Verifactu Batch ' . str_pad($this->batch->id, 20, '0', STR_PAD_LEFT), $relatedFactures);

        $return = "";

        $url = "https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP?op=RegFactuSistemaFacturacion";
        #$url = "https://google.com";
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
            $errorFile = $inbox_dir . '/batch_' . str_pad($this->batch->id, 20, '0', STR_PAD_LEFT) . '_error.txt';
            file_put_contents($errorFile, $return);
        } else {
            $return = $response;
            // Guardar respuesta en INBOX
            $responseFile = $inbox_dir . '/batch_' . str_pad($this->batch->id, 20, '0', STR_PAD_LEFT) . '_response.xml';
            file_put_contents($responseFile, $response);

            //vinculamos el archivo de respuesta a las facturas
            $this->addFileToInvoice($responseFile, 'Verifactu Batch Response ' . str_pad($this->batch->id, 20, '0', STR_PAD_LEFT), $relatedFactures);

            $this->processResponse($response);
        }

        curl_close($ch);
        return $return;
    }


    /**
     * Procesa la respuesta XML de la AEAT y actualiza el registro en la base de datos
     *
     * @param string $response Respuesta XML de la AEAT
     * @param VerifactuFacturaRegistro $registro Objeto registro a actualizar
     */
    private function processResponse($response)
    {
        global $user;
        if (empty($response)) {
            return;
        }
        if (empty($this->batch)) {
            return;
        }
        $this->db->begin();
        try {
            $dom = new DOMDocument();
            $dom->loadXML($response);
            // Verificar si hay un error de esquema (SOAP Fault)
            $faults = $dom->getElementsByTagNameNS('http://schemas.xmlsoap.org/soap/envelope/', 'Fault');
            if ($faults->length > 0) {
                $fault = $faults->item(0);
                $faultstring = $fault->getElementsByTagName('faultstring')->item(0);
                if ($faultstring) {
                    $errorMsg = $faultstring->textContent;
                    // Actualizar registro con error de esquema
                    $this->batch->estado = VERIFACTU_ESTADO_BATCH_INCORRECTO;
                    // $invoice->array_options['fk_verifactu_registro_estado'] = VERIFACTU_ESTADO_REGISTRO_NO_ENVIADO;
                    // $invoice->update($user);
                    $this->batch->msg_error = $errorMsg;
                    $this->batch->updateCommon($user);
                    $error = new VerifactuError($this->db);
                    $error->fecha = time();
                    $error->tipo_error = 'Error de esquema SOAP';
                    $error->mensaje = $errorMsg;
                    $error->notificado = 0;
                    $error->fk_batch = $this->batch->id;
                    $error->fk_registro = 0;
                    $error->datos_adicionales = $response;
                    $error->create($user);
                }
                $this->db->commit();
                return 0;
            }

            // Procesar respuesta normal
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('tikR', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd');
            $xpath->registerNamespace('tik', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd');

            $respuesta = $xpath->query('//tikR:RespuestaRegFactuSistemaFacturacion')->item(0);
            if (!$respuesta) {
                $errorMsg = "Respuesta inválida o no contiene RespuestaRegFactuSistemaFacturacion";

                $error = new VerifactuError($this->db);
                $error->fecha = time();
                $error->tipo_error = 'Error no contiene RespuestaRegFactuSistemaFacturacion';
                $error->mensaje = $errorMsg;
                $error->notificado = 0;
                $error->fk_batch = $this->batch->id;
                $error->fk_registro = 0;
                $error->datos_adicionales = $response;
                $error->create($user);


                $this->batch->estado = VERIFACTU_ESTADO_BATCH_INCORRECTO;

                $this->batch->msg_error = $errorMsg;
                $this->batch->updateCommon($user);



                $this->db->commit();
                return 0;
            }

            // Extraer CSV si existe
            $csvNode = $xpath->query('//tikR:CSV')->item(0);
            if ($csvNode) {
                $this->batch->csv = $csvNode->textContent;
            }

            // Extraer EstadoEnvio
            $estadoEnvioNode = $xpath->query('//tikR:EstadoEnvio')->item(0);
            if ($estadoEnvioNode) {
                $estadoEnvio = $estadoEnvioNode->textContent;
                if ($estadoEnvio == 'Correcto') {
                    $this->batch->estado = VERIFACTU_ESTADO_BATCH_CORRECTO;
                } elseif ($estadoEnvio == 'ParcialmenteCorrecto') {
                    $this->batch->estado = VERIFACTU_ESTADO_BATCH_PARCIALMENTE_CORRECTO;
                    $error = new VerifactuError($this->db);
                    $error->fecha = time();
                    $error->tipo_error = 'Error, el batch ha sido Aceptado con errores';
                    $error->mensaje = "El batch ha sido procesado parcialmente correcto. Revise los registros individuales para más detalles.";
                    $error->notificado = 0;
                    $error->fk_batch = $this->batch->id;
                    $error->fk_registro = 0;
                    $error->create($user);
                } elseif ($estadoEnvio == 'Incorrecto') {
                    $this->batch->estado = VERIFACTU_ESTADO_BATCH_INCORRECTO;
                    $error = new VerifactuError($this->db);
                    $error->fecha = time();
                    $error->tipo_error = 'Error, el batch ha sido incorrecto';
                    $error->mensaje = "El batch ha sido procesado incorrectamente. Revise los registros individuales para más detalles.";
                    $error->notificado = 0;
                    $error->fk_batch = $this->batch->id;
                    $error->fk_registro = 0;
                    $error->create($user);
                } else {

                    $error = new VerifactuError($this->db);
                    $error->fecha = time();
                    $error->tipo_error = 'Error desconocido en envio de batch';
                    $error->mensaje = "EstadoEnvio desconocido: $estadoEnvio";
                    $error->notificado = 0;
                    $error->fk_batch = $this->batch->id;
                    $error->fk_registro = 0;
                    $error->create($user);

                    $this->batch->estado = VERIFACTU_ESTADO_BATCH_INCORRECTO; // Estado desconocido
                    $this->batch->msg_error = "EstadoEnvio desconocido: $estadoEnvio";
                }
            }

            // Procesar RespuestaLinea
            $respuestaLineas = $xpath->query('//tikR:RespuestaLinea');
            foreach ($respuestaLineas as $linea) {
                // Verificar RefExterna para confirmar que es el registro correcto
                $refExternaNode = $xpath->query('tikR:RefExterna', $linea)->item(0);
                if ($refExternaNode) {
                    $registro = new VerifactuFacturaRegistro($this->db);
                    $refExterna = $refExternaNode->textContent;
                    $expectedRef = ltrim($refExterna, '0');
                    $registro->fetch($expectedRef);
                    if (!$registro || $registro->id != $expectedRef) {
                        continue; // No es el registro que buscamos
                    }
                }

                // Extraer EstadoRegistro
                $estadoRegistroNode = $xpath->query('tikR:EstadoRegistro', $linea)->item(0);
                if ($estadoRegistroNode) {
                    $estadoRegistro = $estadoRegistroNode->textContent;
                    switch ($estadoRegistro) {
                        case 'Correcto':
                            $registro->estado = VERIFACTU_ESTADO_REGISTRO_CORRECTO;
                            $registro->msg_error = '';
                            break;
                        case 'AceptadoConErrores':
                            $registro->estado = VERIFACTU_ESTADO_REGISTRO_ACEPTADO_CON_ERRORES;
                            $codigoErrorRegistroNode = $xpath->query('tikR:CodigoErrorRegistro', $linea)->item(0);
                            $descripcionErrorRegistroNode = $xpath->query('tikR:DescripcionErrorRegistro', $linea)->item(0);
                            $registro->msg_error = "CodigoErrorRegistro: " . ($codigoErrorRegistroNode ? $codigoErrorRegistroNode->textContent : '') .
                                "- DescripcionErrorRegistro: " . ($descripcionErrorRegistroNode ? $descripcionErrorRegistroNode->textContent : '');

                            break;
                        case 'Incorrecto':
                            $registro->estado = VERIFACTU_ESTADO_REGISTRO_INCORRECTO;
                            $codigoErrorRegistroNode = $xpath->query('tikR:CodigoErrorRegistro', $linea)->item(0);
                            $descripcionErrorRegistroNode = $xpath->query('tikR:DescripcionErrorRegistro', $linea)->item(0);
                            $registro->msg_error = "CodigoErrorRegistro: " . ($codigoErrorRegistroNode ? $codigoErrorRegistroNode->textContent : '') .
                                "- DescripcionErrorRegistro: " . ($descripcionErrorRegistroNode ? $descripcionErrorRegistroNode->textContent : '');
                            break;
                        // case 'Rechazado':
                        //     $this->registro->estado = 3;
                        //     break;
                        default:
                            $registro->estado = VERIFACTU_ESTADO_REGISTRO_NO_ENVIADO; // Estado desconocido
                            $registro->msg_error = "EstadoRegistro desconocido: $estadoRegistro";
                            break;
                    }
                }

                $registro->updateCommon($user);
            }
            $this->batch->updateCommon($user);
            $this->db->commit();
        } catch (Exception $e) {
            $error = new VerifactuError($this->db);
            $error->fecha = time();
            $error->tipo_error = 'Error de procesamiento';
            $error->mensaje = "Error en el procesamiento del batch: " . $e->getMessage();
            $error->notificado = 0;
            $error->fk_batch = $this->batch->id;
            $error->fk_registro = 0;
            $error->create($user);

            $this->db->rollback();
            return -1;
        }
        return 0;
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

        require_once DOL_DOCUMENT_ROOT . '/ecm/class/ecmfiles.class.php';

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
    private function addFileToInvoice($filePath, $description = '', $factures = null)
    {

        if (!file_exists($filePath)) {
            return false;
        }

        $fileName = basename($filePath);
        //$fileSize = filesize($filePath);

        // Directorio de destino para documentos de la factura
        //$upload_dir = $conf->facture->multidir_output[$this->facture->entity] . '/' . $this->facture->ref;

        // // Crear directorio si no existe
        // if (!is_dir($upload_dir)) {
        //     if (dol_mkdir($upload_dir) < 0) {
        //         return false;
        //     }
        // }

        // // Copiar archivo al directorio de documentos de la factura
        // $destFile = $upload_dir . '/' . $fileName;
        // if (!copy($filePath, $destFile)) {
        //     return false;
        // }

        // Registrar el archivo en la base de datos

        if ($factures && is_array($factures)) {
            $result = true;
            foreach ($factures as $facture) {
                $result &= $this->addFileToInvoiceSingle($filePath, $fileName, $description, $facture);
            }
            return $result;
        } else {
            return $this->addFileToInvoiceSingle($filePath, $fileName, $description);
        }
    }

    private function createSymLink($target, $link)
    {
        if (file_exists($link)) {
            return true; // El enlace ya existe
        }
        return symlink($target, $link);
    }

    private function addFileToInvoiceSingle($filePath, $fileName, $description = '', $facture = null)
    {

        global $conf, $user;

        //multidir_output = 1 porque no es multiempresa.

        $upload_dir = $conf->facture->multidir_output[$facture ? $facture->entity : $this->facture->entity] . '/' . ($facture ? $facture->ref : $this->facture->ref);

        // Crear directorio si no existe
        if (!is_dir($upload_dir)) {
            if (dol_mkdir($upload_dir) < 0) {
                return false;
            }
        }

        $this->createSymLink($filePath, $conf->facture->multidir_output[$facture ? $facture->entity : $this->facture->entity] . '/' .  ($facture ? $facture->ref : $this->facture->ref) . '/' . $fileName);
        return true;
        // require_once DOL_DOCUMENT_ROOT . '/ecm/class/ecmfiles.class.php';
        // $ecmfile = new EcmFiles($this->db);

        // $ecmfile->filepath = $filePath;
        // $ecmfile->filename = $fileName;
        // $ecmfile->label = $description;
        // $ecmfile->fullpath_orig = $filePath;
        // $ecmfile->gen_or_uploaded = 'uploaded';
        // $ecmfile->description = $description;
        // $ecmfile->keywords = 'verifactu';
        // $ecmfile->cover = 0;
        // $ecmfile->position = 0;
        // $ecmfile->acl = '';
        // $ecmfile->date_c = dol_now();
        // $ecmfile->date_m = dol_now();
        // $ecmfile->fk_user_c = $user->id;
        // $ecmfile->fk_user_m = $user->id;
        // $ecmfile->src_object_type = 'facture';
        // $ecmfile->src_object_id = $facture ? $facture->id : $this->facture->id;

        // return $ecmfile->create($user) > 0;
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
        $verifactu_dir = DOL_DATA_ROOT . '/verifactu';
        $outbox_dir = $verifactu_dir . '/OUTBOX';
        $inbox_dir = $verifactu_dir . '/INBOX';

        $xmlFile = $outbox_dir . '/' . $this->facture->ref . '.xml';
        $responseFile = $inbox_dir . '/' . $this->facture->ref . '_response.xml';
        $errorFile = $inbox_dir . '/' . $this->facture->ref . '_error.txt';

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

        require_once DOL_DOCUMENT_ROOT . '/ecm/class/ecmfiles.class.php';

        $sql = "SELECT filepath, filename, label, date_c, fk_user_c";
        $sql .= " FROM " . MAIN_DB_PREFIX . "ecm_files";
        $sql .= " WHERE src_object_type = 'facture'";
        $sql .= " AND src_object_id = " . $this->facture->id;
        $sql .= " AND (keywords LIKE '%verifactu%' OR filename LIKE '%" . $this->facture->ref . "%')";
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

        return DOL_URL_ROOT . '/document.php?modulepart=facture&file=' . urlencode($this->facture->ref . '/' . $filename);
    }
}
