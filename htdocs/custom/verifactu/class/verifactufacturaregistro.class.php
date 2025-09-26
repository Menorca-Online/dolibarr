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
            'operation'   => array(
                'type' => 'integer',
                'label' => 'Operation',
                'enabled' => 1,
                'visible' => 1,
                'notnull' => 1,
                'foreignkey' => 'c_verifactu_registro_operaciones.rowid'
            )

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

    public function getPreviousRegister()
    {
        $data = json_decode($this->hash_data, true);
        if (!$data) {
            return null; // No hay datos de hash_data
        }
        $previousHash = $data['Huella'] ?? "";

        $sql = "SELECT * FROM " . MAIN_DB_PREFIX . $this->table_element;
        $sql .= " WHERE hash = '" . $previousHash . "'";
        $sql .= " ORDER BY rowid DESC";
        $sql .= " LIMIT 1";

        $resql = $this->db->query($sql);
        if ($resql) {
            if ($this->db->num_rows($resql) > 0) {
                $obj = $this->db->fetch_object($resql);
                $registro = new VerifactuFacturaRegistro($this->db);
                $registro->fetch($obj->rowid);
                return $registro;
            } else {
                return null; // No hay registro previo  
            }
        } else {
            dol_print_error($this->db);
            return null;
        }
    }

    /**
     * Busca el primer registro que cumple con las condiciones WHERE
     *
     * @param array $where_conditions Array asociativo de condiciones WHERE ['campo' => 'valor']
     * @param string $order_by Campo para ordenar (opcional)
     * @param string $order_direction Dirección del orden (ASC/DESC, por defecto DESC)
     * @return VerifactuFacturaRegistro|null El registro encontrado o null si no existe
     */
    public static function findFirst($db, $where_conditions = array(), $order_by = 'rowid', $order_direction = 'DESC')
    {
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "verifactu_factura_registros";

        // Agregar condiciones WHERE
        if (!empty($where_conditions)) {
            $sql .= " WHERE ";
            $conditions = array();
            foreach ($where_conditions as $field => $value) {
                if (is_string($value)) {
                    $conditions[] = $field . " = '" . $db->escape($value) . "'";
                } else {
                    $conditions[] = $field . " = " . ((int) $value);
                }
            }
            $sql .= implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY " . $order_by . " " . $order_direction;
        $sql .= " LIMIT 1";

        $result = $db->query($sql);
        if ($result && $db->num_rows($result) > 0) {
            $obj = $db->fetch_object($result);
            $registro = new VerifactuFacturaRegistro($db);
            $registro->fetch($obj->rowid); // Ahora fetch() está definido
            $db->free($result);
            return $registro;
        }

        return null;
    }
}
