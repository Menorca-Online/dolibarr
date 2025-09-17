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

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

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
        dol_syslog(get_class($this)."::runTrigger action=".$action);

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
                // Validar que la fecha de factura sea la actual antes de validar
                $today = dol_mktime(0, 0, 0, date('m'), date('d'), date('Y'));
                $invoicedate = dol_mktime(0, 0, 0, date('m', $object->date), date('d', $object->date), date('Y', $object->date));
                
                if ($invoicedate != $today) {
                    setEventMessages($langs->trans('VerifactuErrorFechaDebeSerHoy'), null, 'errors');
                    dol_syslog("Verifactu: Validación bloqueada - fecha incorrecta. Esperada: " . 
                              dol_print_date($today) . ", Actual: " . dol_print_date($invoicedate));
                    return -1; // Bloquear validación
                }
                
                dol_syslog("Verifactu: Factura validada correctamente con fecha actual");
                break;

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
