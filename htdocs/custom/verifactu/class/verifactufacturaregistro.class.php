<?php
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

class VerifactuFacturaRegistro extends CommonObject
{
    public $element       = 'verifactufacturaregistro';       // Identificador interno
    public $table_element = 'verifactu_factura_registros';    // Nombre de la tabla (sin prefijo)
    public $picto         = 'generic';                    // Icono genérico

    public $pk_name       = 'rowid';                         // <--- Clave primaria real

    // Campos
    public $rowid;
    public $factureid;
    public $hash;
    public $hash_data;
    public $fecha;
    public $estado;
    public $msg_error;
    public $csv_line;
    public $operation;
    /**
     * Constructor
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->fields = array(
            'rowid'    => array('type' => 'integer', 'label' => 'ID', 'enabled' => 1, 'visible' => -2, 'notnull' => 1, 'index' => true),
            'factureid'  => array('type' => 'integer',  'label' => 'Invoice',  'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'index' => true),
            'hash' => array('type' => 'string',  'label' => 'Hash', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'length' => '64'),
            'hash_data'   => array('type' => 'text', 'label' => 'Hash data',   'enabled' => 1, 'visible' => 1, 'notnull' => 1),
            'fecha'   => array('type' => 'date', 'label' => 'Date',   'enabled' => 1, 'visible' => 1, 'notnull' => 1),
            'estado'   => array('type' => 'integer', 'label' => 'State',   'enabled' => 1, 'visible' => 1, 'notnull' => 1),    
            'msg_error'   => array('type' => 'text', 'label' => 'Error message',   'enabled' => 1, 'visible' => 1, 'notnull' => 0),
            'csv_line'   => array('type' => 'text', 'label' => 'CSV line',   'enabled' => 1, 'visible' => 1, 'notnull' => 0),
            'operation'   => array('type' => 'string', 'label' => 'Operation',   'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'length' => '20')

        );
    }



    public function create(User $user, $notrigger = false)
    {
        return $this->createCommon($user, $notrigger);
    }
}
