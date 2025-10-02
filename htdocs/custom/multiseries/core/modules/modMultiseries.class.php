<?php
include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modMultiseries extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs;
        $this->db = $db;

        $this->numero = 104000; // ID único >100000
        $this->rights_class = 'multiseries';
        $this->family = "billing";
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "Módulo Multi-series de facturación";
        $this->version = '1.0';
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);

        $this->dirs = array("/multiseries");

        $this->config_page_url = array();

        // Este módulo añade un numerador
        $this->module_parts = array(
            'models' => 1
        );
    }

    public function init($options = '')
    {
        $result = $this->_load_tables('/multiseries/sql/'); // si tuvieras SQL
        $sql = array();
        $this->_add_extra_fields();
        return $this->_init($sql,$options);
    }

    public function remove($options = '')
    {

        $this->_remove_extra_fields();
        $sql = array();
        return $this->_remove($sql, $options);
    }

    public function _add_extra_fields()
    {
        include_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
        $extrafields = new ExtraFields($this->db);

        $result1 = $extrafields->addExtraField(
            'serie',                     // $attrname
            'Serie de factura',                     // $label
            'varchar',                             // $type
            -10,                                   // $pos (posición negativa para aparecer al inicio)
            '5',                                    // $size
            'facture',                             // $elementtype
            0,                                     // $unique
            1,                                     // $required (obligatorio)
            '',                                    // $default_value
            '',
            0,                                     // $alwayseditable
            '',                                    // $perms
            1,                                     // $list
            'Seleccione la serie', // $help
            '',                                    // $computed
            '',                                    // $entity
            '',                                    // $langfile
            '1',                                   // $enabled
            0,                                     // $totalizable
            1                                      // $printable
        );


        return 1;
    }

    public function _remove_extra_fields()
    {
        include_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
        $extrafields = new ExtraFields($this->db);

        // Eliminar campo serie
        $result1 = $extrafields->delete('serie', 'facture');
        if ($result1 < 0) {
            return -1;
        }
    }
}
