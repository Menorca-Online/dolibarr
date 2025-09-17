<?php
/* Trigger VeriFactu - Captura BILL_VALIDATE */

class InterfaceVerifactu
{
    public $family = 'verifactu';
    public $description = "Trigger VeriFactu para capturar validación de facturas";
    public $version = '1.0';

    /**
     * Constructor vacío (no necesitamos $db aquí)
     */
    public function __construct($db)
    {
        // No hace falta guardar $db si no lo usas
    }

    /**
     * Trigger function
     *
     * @param string    $action     Event action code
     * @param Object    $object     Factura, pedido, etc.
     * @param User      $user       Usuario que hace la acción
     * @param Translate $langs      Traducciones
     * @param Conf      $conf       Config
     * @return int                  <0 si error, 0 si nada, >0 si OK
     */
    public function runTrigger($action, $object, $user, $langs, $conf)
    {
        if ($action === 'BILL_VALIDATE') {
            // enviar a verifactu
        }

        return 0;
    }
}
