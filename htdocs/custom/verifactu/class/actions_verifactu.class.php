<?php

class ActionsVerifactu
{
    /**
     * Hook: formObjectOptions
     * Permite añadir campos al formulario de facturas
     */
    public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $db;



        if ($parameters['currentcontext'] === 'invoicecard') {
            $langs->load("verifactu@verifactu");

            $sql = "SELECT rowid, code, label 
                    FROM ".MAIN_DB_PREFIX."verifactu_facture_type 
                    WHERE active = 1
                    ORDER BY code";
            $resql = $db->query($sql);

            $options = '';
            if ($resql) {
                while ($obj = $db->fetch_object($resql)) {
                    $selected = ($object->fk_facture_type == $obj->rowid ? 'selected' : '');
                    $options .= '<option value="'.$obj->rowid.'" '.$selected.'>['.$obj->code.'] '.$obj->label.'</option>';
                }
            }

            print '<tr class="oddeven">';
            print '<td><span class="fieldrequired">'.$langs->trans("FactureType").'</span></td>';
            print '<td><select name="fk_facture_type" required>';
            print '<option value="">-- '.$langs->trans("SelectFactureType").' --</option>';
            print $options;
            print '</select></td></tr>';
        }

        return 0;
    }

    public function getNomUrl($parameters, &$object, &$action, $hookmanager)
    {
        if ($object->element == 'facture') {
            var_dump($object);
            die("🚨 Hook getNomUrl ejecutado");
            // Le decimos al objeto que incluya nuestro campo en los loads
            if (empty($object->fields['fk_facture_type'])) {
                $object->fields['fk_facture_type'] = array(
                    'type' => 'integer',
                    'label' => 'Tipo Factura',
                    'enabled' => 1,
                    'visible' => 1,
                    'notnull' => 0
                );
            }
        }
    }
}
