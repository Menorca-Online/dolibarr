<?php
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

class VerifactuFactureType extends CommonObject
{
    public $element       = 'verifactufacturetype';       // Identificador interno
    public $table_element = 'verifactu_facture_types';    // Nombre de la tabla (sin prefijo)
    public $picto         = 'generic';                    // Icono genérico

    public $pk_name       = 'id';                         // <--- Clave primaria real

    // Campos
    public $id;
    public $code;
    public $label;
    public $api;

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
            'api'   => array('type' => 'integer', 'label' => 'API',   'enabled' => 1, 'visible' => 1, 'notnull' => 1)
        );
    }



    public function create(User $user, $notrigger = false)
    {
        return $this->createCommon($user, $notrigger);
    }
}
