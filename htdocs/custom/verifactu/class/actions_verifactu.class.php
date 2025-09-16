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

            // Cargar lista de tipos
            $sql = "SELECT rowid, code, label FROM ".MAIN_DB_PREFIX."verifactu_facturae_type WHERE active = 1";
            $resql = $db->query($sql);

            $options = '';
            if ($resql) {
                while ($obj = $db->fetch_object($resql)) {
                    $selected = ($object->fk_facturae_type == $obj->rowid ? 'selected' : '');
                    $options .= '<option value="'.$obj->rowid.'" '.$selected.'>['.$obj->code.'] '.$obj->label.'</option>';
                }
            }

            print '<tr><td><span class="fieldrequired">'.$langs->trans("FacturaeType").'</span></td>';
            print '<td><select name="fk_facturae_type" required>';
            print '<option value="">-- '.$langs->trans("SelectFacturaeType").' --</option>';
            print $options;
            print '</select></td></tr>';
        }

        return 0;
    }
}
