<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/modules_facture.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

class mod_facture_multiseries extends ModeleNumRefFactures
{
    public $name = 'multiseries';

    public function getExample()
    {
        $y = date('Y');
        return "M$y-000001, W$y-000001, AM$y-000001, AW$y-000001";
    }

    public function getNextValue($objsoc, $invoice, $mode = 'next')
    {
        global $db;

        // Extrafield 'serie'
        $prefix = $invoice->array_options['options_serie'] ?? 'W';


        $year = date('Y');

        // --- Si solo piden el último usado
        if ($mode === 'last') {
            $sql = "SELECT ref 
                    FROM ".MAIN_DB_PREFIX."facture
                    WHERE ref LIKE '".$db->escape($prefix.$year."-%")."'
                    ORDER BY ref DESC
                    LIMIT 1";
            $res = $db->query($sql);
            if ($res && ($obj = $db->fetch_object($res))) return $obj->ref ?: '';
            return '';
        }

        // --- Número manual desde extrafield 'numero'
        $manual = isset($invoice->array_options['options_numero']) ? trim((string)$invoice->array_options['options_numero']) : '';
        if ($manual !== '') {
            $seq = sprintf('%06d', (int)preg_replace('/\D+/', '', $manual));
            $ref = $prefix.$year.'-'.$seq;

            // Comprobar que no exista
            $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture WHERE ref='".$db->escape($ref)."'";
            $res = $db->query($sql);
            if ($res && $db->num_rows($res) > 0) {
                return -1; // Error: ref ya existe
            }
            return $ref;
        }

        // --- Autoincremento: coger el mayor usado
        $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(ref, '-', -1) AS UNSIGNED)) as lastnum
                FROM ".MAIN_DB_PREFIX."facture
                WHERE ref LIKE '".$db->escape($prefix.$year."-%")."'";
        $resql = $db->query($sql);
        $last = 0;
        if ($resql && ($obj = $db->fetch_object($resql)) && !empty($obj->lastnum)) {
            $last = (int)$obj->lastnum;
        }

        $next = $last + 1;
        $seq  = sprintf('%06d', $next); // <<<<<< 6 dígitos

        return $prefix.$year.'-'.$seq;
    }
}
