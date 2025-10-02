<?php
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';
require_once __DIR__ . '/verifactufacturaregistro.class.php';

class VerifactuBatch extends CommonObject
{
    public $element       = 'verifactu_batch';       // Identificador interno
    public $table_element = 'verifactu_batches';    // Nombre de la tabla (sin prefijo)
    public $picto         = 'generic';                    // Icono genérico

    public $pk_name       = 'rowid';                         // <--- Clave primaria real

    // Campos
    public $rowid;
    public $fecha;
    public $estado;
    public $msg_error;
    public $num_records;
    public $csv;

    /**
     * Constructor
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->fields = array(
            'rowid'    => array('type' => 'integer', 'label' => 'ID', 'enabled' => 1, 'visible' => -2, 'notnull' => 1, 'index' => true),
            'fecha'    => array('type' => 'datetime', 'label' => 'Fecha', 'enabled' => 1, 'visible' => 1, 'notnull' => 1),
            'estado'   => array('type' => 'integer', 'label' => 'Estado',   'enabled' => 1, 'visible' => 1, 'notnull' => 1),
            'msg_error' => array('type' => 'text',    'label' => 'Mensaje Error', 'enabled' => 1, 'visible' => 1, 'notnull' => 0),
            'num_records' => array('type' => 'integer', 'label' => 'Número de registros', 'enabled' => 1, 'visible' => 1, 'notnull' => 1),
            'csv'      => array('type' => 'text',    'label' => 'CSV', 'enabled' => 1, 'visible' => 0, 'notnull' => 0)

        );
    }



    public function create(User $user, $notrigger = false)
    {
        return $this->createCommon($user, $notrigger);
    }
    public function fetch($id, $ref = null, $ref_ext = null)
    {
        return $this->fetchCommon($id, $ref, $ref_ext);
    }

    public function registros()
    {
        $registros = array();
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "verifactu_factura_registros WHERE fk_batch = " . (int)$this->id;
        $result = $this->db->query($sql);
        if ($result) {
            while ($obj = $this->db->fetch_object($result)) {
                $registro = new VerifactuFacturaRegistro($this->db);
                $registro->fetch($obj->rowid);
                $registros[] = $registro;
            }
            $this->db->free($result);
        }
        return $registros;
    }

    public function getRegistrosFactures(): array
    {
        $factures = array();
        $sql = "SELECT DISTINCT factureid, entity, ref FROM " . MAIN_DB_PREFIX . "verifactu_factura_registros";
        $sql.= " INNER JOIN " . MAIN_DB_PREFIX . "facture ON " . MAIN_DB_PREFIX . "facture.rowid = " . MAIN_DB_PREFIX . "verifactu_factura_registros.factureid";
        $sql.= " WHERE fk_batch = " . (int)$this->id;

        $result = $this->db->query($sql);
        if ($result) {
            while ($obj = $this->db->fetch_object($result)) {
                $factures[] = $obj;
            }
            $this->db->free($result);
        }
        return $factures;
    }
}
