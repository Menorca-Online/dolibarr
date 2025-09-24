<?php
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

class VerifactuClaveOperacion extends CommonObject
{
    public $element       = 'verifactuclaveoperacion';       // Identificador interno
    public $table_element = 'c_verifactu_clave_operaciones';    // Nombre de la tabla (sin prefijo)
    public $picto         = 'generic';                    // Icono genérico

    public $pk_name       = 'rowid';                         // <--- Clave primaria real

    // Campos
    public $rowid;
    public $code;
    public $label;
    public $active;

    /**
     * Constructor
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->fields = array(
            'rowid'    => array('type' => 'integer', 'label' => 'ID', 'enabled' => 1, 'visible' => -2, 'notnull' => 1, 'index' => true),
            'code'  => array('type' => 'string',  'label' => 'Code',  'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'length' => '50'),
            'label' => array('type' => 'string',  'label' => 'Label', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'length' => '255'),
            'active'   => array('type' => 'integer', 'label' => 'Active',   'enabled' => 1, 'visible' => 1, 'notnull' => 1)
        );
    }



    public function create(User $user, $notrigger = false)
    {
        return $this->createCommon($user, $notrigger);
    }
}
