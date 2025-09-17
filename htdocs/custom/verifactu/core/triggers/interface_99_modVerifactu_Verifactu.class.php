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
            if (empty($object->fk_facture_type)) {
                setEventMessages("El campo Tipo Facture es obligatorio", null, 'errors');
                return -1; // impedir validación
            }
            // echo "<pre>";
            // var_dump($object->ref);
            // var_dump($object->total_ht);

            // echo "</pre>";
            // die("🚨 Trigger VeriFactu ejecutado en BILL_VALIDATE");
        }
        if ($action === 'BILL_CREATE' || $action === 'BILL_MODIFY') {
            global $db;

            // esto no es correcto y creo que no hace falta hacer nada en este trigger. Actualmente ya que estamos usando la api de dolibarr para crear custom fields
            // if (!empty($_POST['fk_facture_type'])) {
            //     $facture_type = (int) $_POST['fk_facture_type'];

            //     $sql = "UPDATE ".MAIN_DB_PREFIX."facture
            //             SET fk_facture_type = ".$facture_type."
            //             WHERE rowid = ".$object->id;
            //     $db->query($sql);
            // }
        }

        return 0;
    }
}
