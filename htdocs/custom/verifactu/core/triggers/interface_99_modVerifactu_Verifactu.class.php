<?php
/* Copyright (C) 2025 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as pub		if($TipoFactura) {
            try {
                $tipoVerifactu = $verifactuType->fetchCommon($TipoFactura);
                $tipo = $tipoVerifactu->code ?? $tipo;
            } catch (Exception $e) {
                // Log the exception
                dol_syslog("Verifactu: Error fetching facture type: " . $e->getMessage(), LOG_ERR);
            }
        } by
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

require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactufacturetype.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactuclaveoperacion.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactuclaveexencion.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactuclaveregimen.class.php';

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
     * Maneja la validación de facturas según normativa Verifactu
     *
     * @param CommonObject $object Objeto factura
     * @param User $user Usuario que realiza la acción
     * @param Translate $langs Objeto de traducciones
     * @param Conf $conf Configuración de Dolibarr
     * @return int Código de retorno (<0 error, 0 ok, >0 warning)
     */
    private function handleBillValidate($object, $user, $langs, $conf)
    {
        // Verificar si es una factura rectificativa
        $isRectificativa = ($object->type == 2);
        $isAbono = ($object->type == 1);
        $totalFactura = $object->total_ttc;
        $factureType = $object->array_options['options_fk_facture_type'] ?? null;
        $factureTypeObj = new VerifactuFactureType($this->db);
        $factureType = $factureTypeObj->fetchCommon($factureType) ? $factureTypeObj->code : 'F1'; // Por defecto F1 si no se encuentra

        $maxAmountSimplificadas = $conf->global->INVOICE_MAX_AMOUNT_SIMPLIFICADAS ?? 0;
        $clienteGenerico = $conf->global->INVOICE_CLIENTE_GENERICO ?? -1000;



        //regla para facturas simplificadas
        if (abs($totalFactura) >= $maxAmountSimplificadas && $object->socid == $clienteGenerico) {
            $errorMsg = "ADVERTENCIA: La factura excede el límite de cantidad simplificada";
            setEventMessages($errorMsg, null, 'warnings');
            $object->error = $errorMsg;
            return -1;
        }
        //regla para facturas nominativas, el cliente no es generico
        if ($object->socid != $clienteGenerico) {
            $object->fetch_thirdparty();
            //si el pais es ESPAÑA (ES), el cliente tiene que tener un NIF valido
            if ($object->thirdparty->country == 'ES' && (empty($object->thirdparty->id) || empty($object->thirdparty->id) || empty($object->thirdparty->id))) {
                $errorMsg = "ERROR: El cliente de la factura debe tener un NIF válido";
                setEventMessages($errorMsg, null, 'errors');
                $object->error = $errorMsg;
                return -1;
            }
        }


        $today = dol_mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $invoicedate = dol_mktime(0, 0, 0, date('m', $object->date), date('d', $object->date), date('Y', $object->date));

        if ($invoicedate != $today) {
            $errorMsg = "La fecha de la factura debe ser la fecha actual según normativa Verifactu";
            setEventMessages($errorMsg, null, 'errors');
            $object->error = $errorMsg;
            return -1; // Bloquear validación
        }


        //VALIDACION DESGLOSE

        $impuesto = "01"; //01 Por el momento ya que en ningun sitio se puede configurar
        foreach ($object->lines as $line) {
            $tipoImpositivo = (float) $line->tva_tx;
            $tipoRecargoEquivalencia = (float)$line->localtax2_tx ?? 0;
            $cuotaRepercutida = (float)$line->total_tva ?? 0;

            //obtener fk
            $claveRegimen = $line->array_options['options_fk_clave_regimen'] ?? 0;
            $calificacionOperacion = $line->array_options['options_fk_clave_operacion'] ?? 0;
            $claveExencion = $line->array_options['options_fk_clave_exencion'] ?? 0;
            //obtener valores
            $claveExencionObj = new VerifactuClaveExencion($this->db);
            $claveRegimenObj = new VerifactuClaveRegimen($this->db);
            $calificacionOperacionObj = new VerifactuClaveOperacion($this->db);

            $claveRegimen = $claveRegimenObj->fetch($claveRegimen) ? $claveRegimenObj->code : null;
            $calificacionOperacion = $calificacionOperacionObj->fetch($calificacionOperacion) ?
                $calificacionOperacionObj->code : null;
            $claveExencion = $claveExencionObj->fetch($claveExencion) ?
                $claveExencionObj->code : null;

            if (empty($claveRegimen) || (empty($calificacionOperacion) && empty($claveExencion)) || (!empty($calificacionOperacion) && !empty($claveExencion))) {
                $errorMsg = "ERROR: Falta clave de régimen o (calificación de operación / clave exención están vacías o cumplimentadas las dos, son excluyentes) en alguna línea de la factura.";
                setEventMessages($errorMsg, null, 'errors');
                $object->error = $errorMsg;
                return -1;
            }
            //fin obtener valores fk

            //Validacion TipoImpositivo
            if ($impuesto == '01' && $calificacionOperacion == 'S1' && !in_array($tipoImpositivo, [0, 4, 10, 21])) { //2, 5 y 7.5 no se tienen en cuenta ya que solo se admiten desde fechas anteriores al desarrollo actual
                $errorMsg = "ERROR: Tipo impositivo inválido para clave de régimen 01. Valores permitidos: 0%, 4%, 10%, 21%";
                setEventMessages($errorMsg, null, 'errors');
                $object->error = $errorMsg;
                return -1;
            }

            //Validacion BaseImponibleACoste - No se puede dar el caso en este sistema
            // if ($claveRegimen == "06") { //impuesto 02 o 05 no se tienen en cuenta ya que solo utilizamos 01

            // }

            //Validacion TipoRecargoEquivalencia
            if ($impuesto == '01' && $calificacionOperacion == 'S1') {
                if ($tipoRecargoEquivalencia != 0) {
                    if (!in_array($tipoRecargoEquivalencia, [5.2, 1.75, 1.4, 1, 0.62, 0.5, 0.26,])) {
                        $errorMsg = "ERROR: Tipo recargo de equivalencia inválido para clave de régimen 01. Valores permitidos: 5.2%, 1.75%, 1.4%, 1%, 0.62%, 0.5%, 0.26%";
                        setEventMessages($errorMsg, null, 'errors');
                        $object->error = $errorMsg;
                        return -1;
                    }
                    if ($tipoImpositivo == 21 && !in_array($tipoRecargoEquivalencia, [5.2, 1.75])) {
                        $errorMsg = "ERROR: Tipo recargo de equivalencia inválido para clave de régimen 01 con tipo impositivo 21. Valores permitidos: 5.2%, 1.75%";
                        setEventMessages($errorMsg, null, 'errors');
                        $object->error = $errorMsg;
                        return -1;
                    }
                    if ($tipoImpositivo == 10 && !in_array($tipoRecargoEquivalencia, [1.4])) {
                        $errorMsg = "ERROR: Tipo recargo de equivalencia inválido para clave de régimen 01 con tipo impositivo 10. Valores permitidos: 1.4%";
                        setEventMessages($errorMsg, null, 'errors');
                        $object->error = $errorMsg;
                        return -1;
                    }
                    //no se valida tipoImpositivo 7.5 ya que no dejamos llegar a ese punto
                    if ($tipoImpositivo == 4 && !in_array($tipoRecargoEquivalencia, [0.5])) {
                        $errorMsg = "ERROR: Tipo recargo de equivalencia inválido para clave de régimen 01 con tipo impositivo 4. Valores permitidos: 0.5%";
                        setEventMessages($errorMsg, null, 'errors');
                        $object->error = $errorMsg;
                        return -1;
                    }
                }
            }
            //Validacion CalificacionOperacion | hay aplicadas validaciones en XML
            if ($calificacionOperacion == "S2") {
                if (!in_array($factureType, ['F1', 'F3', 'R1', 'R2', 'R3', 'R4'])) {
                    $errorMsg = "ERROR: Tipo de factura inválido para clave de operación S2. Valores permitidos: F1, F3, R1, R2, R3, R4";
                    setEventMessages($errorMsg, null, 'errors');
                    $object->error = $errorMsg;
                    return -1;
                }

                if ($tipoImpositivo != 0) {
                    $errorMsg = "ERROR: Tipo impositivo inválido para clave de operación S2. Solo se permite 0% ya que S2 es inversion del sujeto pasivo";
                    setEventMessages($errorMsg, null, 'errors');
                    $object->error = $errorMsg;
                    return -1;
                }
            }

            if ($calificacionOperacion == "N1" || $calificacionOperacion == "N2") {
                if ($tipoImpositivo != 0) {
                    $errorMsg = "ERROR: Tipo impositivo inválido para clave de operación N1 o N2. Solo se permite 0% ya que N1 y N2 son operaciones no sujetas";
                    setEventMessages($errorMsg, null, 'errors');
                    $object->error = $errorMsg;
                    return -1;
                }

                if ($tipoRecargoEquivalencia != 0) {
                    $errorMsg = "ERROR: Tipo recargo de equivalencia inválido para clave de operación N1 o N2. Solo se permite 0% ya que N1 y N2 son operaciones no sujetas";
                    setEventMessages($errorMsg, null, 'errors');
                    $object->error = $errorMsg;
                    return -1;
                }
            }

            //Validacion ClaveExencion | hay aplicadas validaciones en XML
            if (!empty($claveExencion)) {

                if ($claveRegimen == '01' && in_array($claveExencion, ['E2', 'E3'])) {
                    $errorMsg = "ERROR: Clave de exención inválida para clave de régimen 01. No se permite clave de exención E2 o E3.";
                    setEventMessages($errorMsg, null, 'errors');
                    $object->error = $errorMsg;
                    return -1;
                }

                if ($tipoImpositivo != 0) {
                    $errorMsg = "ERROR: Tipo impositivo inválido para clave de exención. Solo se permite 0% ya que las claves de exención son operaciones no sujetas";
                    setEventMessages($errorMsg, null, 'errors');
                    $object->error = $errorMsg;
                    return -1;
                }

                if ($tipoRecargoEquivalencia != 0) {
                    $errorMsg = "ERROR: Tipo recargo de equivalencia inválido para clave de exención. Solo se permite 0% ya que las claves de exención son operaciones no sujetas";
                    setEventMessages($errorMsg, null, 'errors');
                    $object->error = $errorMsg;
                    return -1;
                }
            }


            //Validacion ClaveRegimen
            if ($claveRegimen == '03') {
                if ($calificacionOperacion != 'S1') {
                    $errorMsg = "ERROR: CalificacionOperacion inválida para clave de régimen 03. Solo se permite clave de operación S1.";
                    setEventMessages($errorMsg, null, 'errors');
                    $object->error = $errorMsg;
                    return -1;
                }
            }

            if ($claveRegimen == '04' && (!empty($calificacionOperacion) && $calificacionOperacion != 'S2')) {
                $errorMsg = "ERROR: CalificacionOperacion inválida para clave de régimen 04. Solo se permite clave de operación S2.";
                setEventMessages($errorMsg, null, 'errors');
                $object->error = $errorMsg;
                return -1;
            }

            if ($claveRegimen == '06') {
                if (in_array(($factureType), ['F2', 'F3', 'R5'])) {
                    $errorMsg = "ERROR: Tipo de factura inválido para clave de régimen 06. Valores no permitidos: F2, F3, R5";
                    setEventMessages($errorMsg, null, 'errors');
                    $object->error = $errorMsg;
                    return -1;
                }
                //Campo BaseImponibleACoste obligatorio, nosotros aun no lo aplicamos asi que no validar por el momento
            }

            if ($claveRegimen == '07') {
                if (in_array($calificacionOperacion, ['S2', 'N1', 'N2'])) {
                    $errorMsg = "ERROR: Clave de régimen inválida para clave de operación S2, N1 o N2. No se permite clave de régimen 07.";
                    setEventMessages($errorMsg, null, 'errors');
                    $object->error = $errorMsg;
                    return -1;
                }
                if (in_array(($claveExencion), ['E2', 'E3', 'E4', 'E5'])) {
                    $errorMsg = "ERROR: Clave de exención inválida para clave de regimen 07. No se permite clave de exención E2, E3, E4 o E5.";
                    setEventMessages($errorMsg, null, 'errors');
                    $object->error = $errorMsg;
                    return -1;
                }
            }

            if ($claveRegimen == '08') {
                if ($calificacionOperacion != 'N2') {
                    $errorMsg = "ERROR: Clave de régimen 08 solo es válida para calificacion operación N2";
                    setEventMessages($errorMsg, null, 'errors');
                    $object->error = $errorMsg;
                    return -1;
                }
            }

            if ($claveRegimen == '10') {
                $destinatarioEspañol = $object->thirdparty->country_code == 'ES';
                if ($factureType != 'F1' || $calificacionOperacion != 'N1' || !empty($claveExencion) || !$destinatarioEspañol) {
                    $errorMsg = "ERROR: Clave de régimen 10 solo es válida para facturas tipo F1, con clave de operación N1 y clave de exención.";
                    setEventMessages($errorMsg, null, 'errors');
                    $object->error = $errorMsg;
                    return -1;
                }
            }

            if ($claveRegimen == '11' && $tipoImpositivo != 21) {
                $errorMsg = "ERROR: Clave de régimen 11 solo es válida para tipo impositivo 21.";
                setEventMessages($errorMsg, null, 'errors');
                $object->error = $errorMsg;
                return -1;
            }
            //Validacion ClaveRegimen 14 - Actualmente no se pueden crear facturas anteriores asi que tampoco se puede poner una operacion en el futuro, pendiente de definir
            // if($claveRegimen = '14'){

            // }
            if ($claveRegimen == '18') {
                if ($tipoRecargoEquivalencia != 0 && $calificacionOperacion != 'S1') {
                    $errorMsg = "ERROR: Clave de régimen 18 solo es válida para clave de operación S1 cuando hay recargo de equivalencia.";
                    setEventMessages($errorMsg, null, 'errors');
                    $object->error = $errorMsg;
                    return -1;
                }
            }

            //Validaciones CuotaRepercutida | hay validaciones aplicadas en XML
            if ($cuotaRepercutida != 0 && $calificacionOperacion != 'S1') {
                $errorMsg = "ERROR: Cuota repercutida solo puede ser distinta de 0 cuando la clave de operación es S1.";
                setEventMessages($errorMsg, null, 'errors');
                $object->error = $errorMsg;
                return -1;
            }
        }


        include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/lib/verifactu.lib.php';
        return verifactu_generar_registro_alta($object);
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
        dol_syslog(get_class($this) . "::runTrigger action=" . $action);

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
                return $this->handleBillValidate($object, $user, $langs, $conf);

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
