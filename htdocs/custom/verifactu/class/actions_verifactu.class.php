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
        var_dump($parameters);
        die();

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
}
