<?php
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

class VerifactuClaveRegimen extends CommonObject
{
    public $element       = 'verifactuclaveregimen';       // Identificador interno
    public $table_element = 'c_verifactu_clave_regimen';    // Nombre de la tabla (sin prefijo)
    public $picto         = 'generic';                    // Icono genérico

    public $pk_name       = 'rowid';                         // <--- Clave primaria real

    // Campos
    public $rowid;
    public $code;
    public $label;


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
        );
    }



    public function create(User $user, $notrigger = false)
    {
        return $this->createCommon($user, $notrigger);
    }
}
