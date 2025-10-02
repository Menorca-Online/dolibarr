<?php
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

class VerifactuError extends CommonObject
{
    public $element       = 'verifactuerror';       // Identificador interno
    public $table_element = 'verifactu_errores';    // Nombre de la tabla (sin prefijo)
    public $picto         = 'fa-exclamation-triangle';                    // Icono de advertencia

    public $pk_name       = 'rowid';                         // <--- Clave primaria real

    // Campos
    public $rowid;
    public $fecha;
    public $tipo_error;
    public $mensaje;
    public $notificado;
    public $fk_batch;
    public $fk_registro;
    public $datos_adicionales;

    /**
     * Constructor
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->fields = array(
            'rowid'    => array('type' => 'integer', 'label' => 'ID', 'enabled' => 1, 'visible' => -2, 'notnull' => 1, 'index' => true),
            'fecha'    => array('type' => 'datetime', 'label' => 'Fecha', 'enabled' => 1, 'visible' => 1, 'notnull' => 1),
            'tipo_error' => array('type' => 'string', 'label' => 'Tipo Error', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'length' => 50),
            'mensaje' => array('type' => 'text', 'label' => 'Mensaje', 'enabled' => 1, 'visible' => 1, 'notnull' => 1),
            'notificado' => array('type' => 'boolean', 'label' => 'Notificado', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'default' => 0),
            'fk_batch' => array('type' => 'integer', 'label' => 'Batch', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'index' => true, 'foreignkey' => 'verifactu_batches.rowid'),
            'fk_registro' => array('type' => 'integer', 'label' => 'Registro', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'index' => true, 'foreignkey' => 'verifactu_factura_registros.rowid'),
            'datos_adicionales' => array('type' => 'text', 'label' => 'Datos Adicionales', 'enabled' => 1, 'visible' => 1, 'notnull' => 0)
        );
    }

    /**
     * Crear un nuevo error
     */
    public function create(User $user, $notrigger = false)
    {
        return $this->createCommon($user, $notrigger);
    }

    /**
     * Obtener error por ID
     */
    public function fetch($id, $ref = null, $ref_ext = null)
    {
        return $this->fetchCommon($id, $ref, $ref_ext);
    }

    /**
     * Obtener errores no notificados
     */
    public function getErrorsNoNotificados()
    {
        $errors = array();
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "verifactu_errores WHERE notificado = 0 ORDER BY fecha ASC";
        $result = $this->db->query($sql);
        if ($result) {
            while ($obj = $this->db->fetch_object($result)) {
                $error = new VerifactuError($this->db);
                $error->fetch($obj->rowid);
                $errors[] = $error;
            }
            $this->db->free($result);
        }
        return $errors;
    }

    /**
     * Marcar error como notificado
     */
    public function marcarNotificado()
    {
        $this->notificado = 1;
        return $this->update($user); // Asumiendo que hay un usuario global
    }

    /**
     * Método estático para registrar un error fácilmente
     */
    public static function registrarError($db, $tipo_error, $mensaje, $fk_batch = null, $fk_registro = null, $datos_adicionales = null)
    {
        $error = new VerifactuError($db);
        $error->tipo_error = $tipo_error;
        $error->mensaje = $mensaje;
        $error->fk_batch = $fk_batch;
        $error->fk_registro = $fk_registro;
        $error->datos_adicionales = $datos_adicionales;
        $error->notificado = 0;

        // Asumir usuario admin o el usuario actual
        global $user;
        if (!$user) {
            $user = new User($db);
            $user->fetch(1); // Usuario admin por defecto
        }

        return $error->create($user);
    }
}