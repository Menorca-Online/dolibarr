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

    /**
     * @var array Configuración del módulo Verifactu
     */
    private $config;

    /**
     * @var string Namespace del XML
     */
    private $namespace = 'sum1';

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
            'emisor_nombre' => $this->getConfigValue($conf, 'MAIN_INFO_SOCIETE', ''),

            // Información del sistema informático
            'sistema_nombre' => $this->getConfigValue($conf, 'VERIFACTU_SISTEMA_NOMBRE', 'DOLIBARR ERP CRM'),
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

        // Crear el documento XML
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Crear elemento raíz RegistroAlta
        $registroAlta = $dom->createElement($this->namespace . ':RegistroAlta');
        $registroAlta->setAttribute('xmlns:' . $this->namespace, 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/ssii/fact/ws/SuministroInformacion.xsd');
        $dom->appendChild($registroAlta);

        // 1. IDVersion
        $this->addElement($dom, $registroAlta, 'IDVersion', '1.0');

        // 2. IDFactura
        $this->addIDFactura($dom, $registroAlta, $facture);

        // 3. RefExterna (hash de la factura como referencia externa)
        $refExterna = str_pad($facture->id, 20, '0', STR_PAD_LEFT);
        $this->addElement($dom, $registroAlta, 'RefExterna', $refExterna);

        // 4. NombreRazonEmisor
        $this->addElement($dom, $registroAlta, 'NombreRazonEmisor', $this->config['emisor_nombre']);

        // 5. TipoFactura
        $tipoFactura = $this->getTipoFactura($facture);
        $this->addElement($dom, $registroAlta, 'TipoFactura', $tipoFactura);

        // 6. DescripcionOperacion
        $descripcion = $this->getDescripcionOperacion($facture);
        $this->addElement($dom, $registroAlta, 'DescripcionOperacion', $descripcion);

        // 7. Destinatarios
        $this->addDestinatarios($dom, $registroAlta, $facture);

        // 8. Desglose
        $this->addDesglose($dom, $registroAlta, $facture);

        // 9. CuotaTotal
        $this->addElement($dom, $registroAlta, 'CuotaTotal', number_format($facture->total_tva, 2, '.', ''));

        // 10. ImporteTotal
        $this->addElement($dom, $registroAlta, 'ImporteTotal', number_format($facture->total_ttc, 2, '.', ''));

        // 11. Encadenamiento (solo si hay hash anterior)
        if (!empty($hashData['hash_anterior'])) {
            $this->addEncadenamiento($dom, $registroAlta, $hashData);
        }

        // 12. SistemaInformatico
        $this->addSistemaInformatico($dom, $registroAlta);

        // 13. FechaHoraHusoGenRegistro
        $fechaHora = $hashData['fechaHoraHusoGenRegistro'] ?? $this->generateTimestamp();
        $this->addElement($dom, $registroAlta, 'FechaHoraHusoGenRegistro', $fechaHora);

        // 14. TipoHuella
        $this->addElement($dom, $registroAlta, 'TipoHuella', '01');

        // 15. Huella (hash de la factura)
        $huella = $hashData['hash'] ?? '';
        if (empty($huella)) {
            throw new Exception('No se encontró hash para la factura ID: ' . $facture->id);
        }
        $this->addElement($dom, $registroAlta, 'Huella', $huella);

        return $dom->saveXML();
    }

    /**
     * Obtiene los datos del hash de una factura
     */
    private function getInvoiceHashData($invoiceId)
    {
        $sql = "SELECT hash, hash_anterior, fechaHoraHusoGenRegistro, hash_data
                FROM " . MAIN_DB_PREFIX . "facture_extrafields
                WHERE fk_object = " . ((int) $invoiceId);

        $result = $this->db->query($sql);
        if ($result && $this->db->num_rows($result) > 0) {
            $obj = $this->db->fetch_object($result);
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
     * Agrega el elemento IDFactura
     */
    private function addIDFactura($dom, $parent, $facture)
    {
        $idFactura = $dom->createElement($this->namespace . ':IDFactura');

        // IDEmisorFactura
        $this->addElement($dom, $idFactura, 'IDEmisorFactura', $this->config['emisor_nif']);

        // NumSerieFactura
        $this->addElement($dom, $idFactura, 'NumSerieFactura', $facture->ref);

        // FechaExpedicionFactura
        $fechaExpedicion = date('d-m-Y', $facture->date);
        $this->addElement($dom, $idFactura, 'FechaExpedicionFactura', $fechaExpedicion);

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

        // Determinar tipo por defecto según el tipo de factura de Dolibarr
        switch ($facture->type) {
            case 0: // Factura estándar
                return 'F1';
            case 1: // Factura de sustitución
                return 'F2';
            case 2: // Nota de crédito
                return 'R1';
            case 3: // Anticipo
                return 'F4';
            default:
                return 'F1';
        }
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

        // Limitar longitud y limpiar
        $descripcion = substr(strip_tags($descripcion), 0, 500);

        return $descripcion;
    }

    /**
     * Agrega destinatarios
     */
    private function addDestinatarios($dom, $parent, $facture)
    {
        $destinatarios = $dom->createElement($this->namespace . ':Destinatarios');

        $idDestinatario = $dom->createElement($this->namespace . ':IDDestinatario');

        // NombreRazon
        $nombreCliente = $facture->thirdparty->name ?: $facture->thirdparty->nom;
        $this->addElement($dom, $idDestinatario, 'NombreRazon', $nombreCliente);

        // NIF/CIF del cliente
        $nifCliente = $facture->thirdparty->tva_intra ?: $facture->thirdparty->idprof1 ?: '';
        if (!empty($nifCliente)) {
            $this->addElement($dom, $idDestinatario, 'NIF', $nifCliente);
        } else {
            // Si no tiene NIF español, podría ser extranjero
            $this->addElement($dom, $idDestinatario, 'IDOtro', $facture->thirdparty->idprof1 ?: 'SIN_NIF');
        }

        $destinatarios->appendChild($idDestinatario);
        $parent->appendChild($destinatarios);
    }

    /**
     * Agrega el desglose de impuestos
     */
    private function addDesglose($dom, $parent, $facture)
    {
        $desglose = $dom->createElement($this->namespace . ':Desglose');

        // Agrupar líneas por tipo de IVA
        $impuestos = array();

        foreach ($facture->lines as $line) {
            $tipoIva = $line->tva_tx;
            if (!isset($impuestos[$tipoIva])) {
                $impuestos[$tipoIva] = array(
                    'base' => 0,
                    'cuota' => 0
                );
            }
            $impuestos[$tipoIva]['base'] += $line->total_ht;
            $impuestos[$tipoIva]['cuota'] += $line->total_tva;
        }

        // Crear DetalleDesglose para cada tipo de IVA
        foreach ($impuestos as $tipoIva => $datos) {
            $detalleDesglose = $dom->createElement($this->namespace . ':DetalleDesglose');

            // Impuesto (01 = IVA)
            $this->addElement($dom, $detalleDesglose, 'Impuesto', '01');

            // ClaveRegimen (01 = Régimen general)
            $this->addElement($dom, $detalleDesglose, 'ClaveRegimen', '01');

            // CalificacionOperacion
            $calificacion = $this->getCalificacionOperacion($tipoIva);
            $this->addElement($dom, $detalleDesglose, 'CalificacionOperacion', $calificacion);

            // TipoImpositivo
            $this->addElement($dom, $detalleDesglose, 'TipoImpositivo', number_format($tipoIva, 2, '.', ''));

            // BaseImponibleOimporteNoSujeto
            $this->addElement($dom, $detalleDesglose, 'BaseImponibleOimporteNoSujeto', number_format($datos['base'], 2, '.', ''));

            // CuotaRepercutida
            $this->addElement($dom, $detalleDesglose, 'CuotaRepercutida', number_format($datos['cuota'], 2, '.', ''));

            $desglose->appendChild($detalleDesglose);
        }

        $parent->appendChild($desglose);
    }

    /**
     * Obtiene la calificación de operación según el tipo de IVA
     */
    private function getCalificacionOperacion($tipoIva)
    {
        if ($tipoIva == 0) {
            return 'E1'; // Exenta
        } elseif ($tipoIva >= 1 && $tipoIva <= 10) {
            return 'S2'; // Sujeta - tipo reducido/superreducido
        } else {
            return 'S1'; // Sujeta - tipo general
        }
    }

    /**
     * Agrega información de encadenamiento
     */
    private function addEncadenamiento($dom, $parent, $hashData)
    {
        // Obtener datos de la factura anterior
        $facturaAnterior = $this->getFacturaAnterior($hashData['hash_anterior']);

        if ($facturaAnterior) {
            $encadenamiento = $dom->createElement($this->namespace . ':Encadenamiento');
            $registroAnterior = $dom->createElement($this->namespace . ':RegistroAnterior');

            $this->addElement($dom, $registroAnterior, 'IDEmisorFactura', $this->config['emisor_nif']);
            $this->addElement($dom, $registroAnterior, 'NumSerieFactura', $facturaAnterior['ref']);
            $this->addElement($dom, $registroAnterior, 'FechaExpedicionFactura', $facturaAnterior['fecha']);
            $this->addElement($dom, $registroAnterior, 'Huella', $hashData['hash_anterior']);

            $encadenamiento->appendChild($registroAnterior);
            $parent->appendChild($encadenamiento);
        }
    }

    /**
     * Obtiene datos de la factura anterior por su hash
     */
    private function getFacturaAnterior($hashAnterior)
    {
        $sql = "SELECT f.ref, f.datef
                FROM " . MAIN_DB_PREFIX . "facture f
                INNER JOIN " . MAIN_DB_PREFIX . "facture_extrafields fe ON f.rowid = fe.fk_object
                WHERE fe.hash = '" . $this->db->escape($hashAnterior) . "'";

        $result = $this->db->query($sql);
        if ($result && $this->db->num_rows($result) > 0) {
            $obj = $this->db->fetch_object($result);
            return array(
                'ref' => $obj->ref,
                'fecha' => date('d-m-Y', $this->db->jdate($obj->datef))
            );
        }

        return null;
    }

    /**
     * Agrega información del sistema informático
     */
    private function addSistemaInformatico($dom, $parent)
    {
        $sistemaInformatico = $dom->createElement($this->namespace . ':SistemaInformatico');

        $this->addElement($dom, $sistemaInformatico, 'NombreRazon', $this->config['sistema_nombre']);
        $this->addElement($dom, $sistemaInformatico, 'NIF', $this->config['sistema_nif']);
        $this->addElement($dom, $sistemaInformatico, 'NombreSistemaInformatico', $this->config['sistema_nombre_software']);
        $this->addElement($dom, $sistemaInformatico, 'IdSistemaInformatico', $this->config['sistema_id']);
        $this->addElement($dom, $sistemaInformatico, 'Version', $this->config['sistema_version']);
        $this->addElement($dom, $sistemaInformatico, 'NumeroInstalacion', $this->config['sistema_instalacion']);
        $this->addElement($dom, $sistemaInformatico, 'TipoUsoPosibleSoloVerifactu', $this->config['sistema_solo_verifactu']);
        $this->addElement($dom, $sistemaInformatico, 'TipoUsoPosibleMultiOT', $this->config['sistema_multi_ot']);
        $this->addElement($dom, $sistemaInformatico, 'IndicadorMultiplesOT', $this->config['sistema_indicador_multi']);

        $parent->appendChild($sistemaInformatico);
    }

    /**
     * Genera timestamp en formato ISO 8601 con zona horaria
     */
    private function generateTimestamp()
    {
        $dt = new DateTime('now', new DateTimeZone('Europe/Madrid'));
        return $dt->format('Y-m-d\TH:i:sP');
    }

    /**
     * Método auxiliar para agregar elementos al DOM
     */
    private function addElement($dom, $parent, $name, $value)
    {
        $element = $dom->createElement($this->namespace . ':' . $name, htmlspecialchars($value, ENT_XML1, 'UTF-8'));
        $parent->appendChild($element);
    }

    /**
     * Genera XML para un lote de facturas (hasta 1000)
     *
     * @param array $factureIds Array de IDs de facturas
     * @return string XML del lote
     */
    public function generateLoteFacturas($factureIds)
    {
        if (count($factureIds) > 1000) {
            throw new Exception('El lote no puede contener más de 1000 facturas');
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Crear elemento raíz del lote
        $lote = $dom->createElement($this->namespace . ':LoteFacturas');
        $lote->setAttribute('xmlns:' . $this->namespace, 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/ssii/fact/ws/SuministroInformacion.xsd');
        $dom->appendChild($lote);

        foreach ($factureIds as $factureId) {
            $facture = new Facture($this->db);
            if ($facture->fetch($factureId) > 0) {
                // Generar XML individual y agregarlo al lote
                $registroXML = $this->generateRegistroAlta($facture);

                // Extraer solo el elemento RegistroAlta del XML individual
                $tempDom = new DOMDocument();
                $tempDom->loadXML($registroXML);
                $registroNode = $dom->importNode($tempDom->documentElement, true);
                $lote->appendChild($registroNode);
            }
        }

        return $dom->saveXML();
    }
}
