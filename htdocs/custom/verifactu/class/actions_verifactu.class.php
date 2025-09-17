<?php
/* Copyright (C) 2025 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    verifactu/class/actions_verifactu.class.php
 * \ingroup verifactu
 * \brief   Hook para gestión de formularios según normativa Verifactu.
 */

/**
 * Class ActionsVerifactu
 */
class ActionsVerifactu
{
    /**
     * @var DoliDB Database handler.
     */
    public $db;

    /**
     * @var string Error code (or message)
     */
    public $error = '';

    /**
     * @var array Errors
     */
    public $errors = array();

    /**
     * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
     */
    public $results = array();

    /**
     * @var string String displayed by executeHook() immediately after return
     */
    public $resprints;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Hook: formObjectOptions
     * Permite añadir campos al formulario de facturas y aplicar normativa Verifactu
     */
    public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $db, $conf;

        if ($parameters['currentcontext'] === 'invoicecard') {
            $langs->load("verifactu@verifactu");

            // Solo aplicar en facturas
            if ($object->element == 'facture' || get_class($object) == 'Facture') {
                
                // Agregar JavaScript para bloquear fecha según normativa Verifactu
                $this->resprints .= '
                <script type="text/javascript">
                $(document).ready(function() {
                    $(\'#reButtonNow\').remove();
                    $(\'.ui-datepicker-trigger\').remove();
                    // Establecer fecha actual en el campo de fecha \'re\'
                    var today = new Date();
                    var day = today.getDate();
                    var month = today.getMonth() + 1;
                    var year = today.getFullYear();

                    // Función para aplicar configuración Verifactu
                    function applyVerifactuDateRules() {
                        // Formatear fecha como YYYY-MM-DD para input type="date"
                        var todayStr = year + "-" + (month < 10 ? "0" : "") + month + "-" + (day < 10 ? "0" : "") + day;
                        $("input[name=\'re\']").val(todayStr).prop("readonly", true);
                        $("input[name=\'reday\']").val(day);
                        $("input[name=\'remonth\']").val(month);
                        $("input[name=\'reyear\']").val(year);


                        // Estilo visual para campo bloqueado
                        $("input[name=\'re\']").css({
                            "background-color": "#f5f5f5",
                            "color": "#666",
                            "cursor": "not-allowed"
                        });

                        // Agregar mensaje informativo si no existe
                        if (!$(".verifactu-date-warning").length) {
                            var warningHtml = \'<tr class="verifactu-date-warning"><td colspan="4">\' +
                                \'<div style="background:#fff3cd; border:1px solid #ffeaa7; padding:8px; margin:5px 0; border-radius:4px; font-size:12px;">\' +
                                \'<i class="fa fa-exclamation-triangle" style="color:#856404;"></i> \' +
                                \'<strong>Normativa Verifactu:</strong> La fecha de factura debe ser la fecha actual y no puede modificarse.\' +
                                \'</div></td></tr>\';

                            var dateContainer = $("input[name=\'re\']").closest("tr");
                            if (dateContainer.length) {
                                dateContainer.after(warningHtml);
                            }
                        }
                    }
                    
                    // Aplicar reglas inmediatamente
                    applyVerifactuDateRules();
                    
                    // Replicar cada segundo para evitar modificaciones externas
                    setInterval(applyVerifactuDateRules, 1000);
                    
                    // Interceptar envío del formulario para validar fecha
                    $(\'form[name="add"], form[name="update"]\').on("submit", function(e) {
                        var currentDate = new Date();
                        var formDate = $("input[name=\'re\']").val();
                        var formDay = null, formMonth = null, formYear = null;
                        if (formDate) {
                            var parts = formDate.split("/");
                            if(parts.length !== 3) {
                                parts = formDate.split("-");
                            }
                            if (parts.length === 3) {
                                if(parts[0].length === 4) {
                                    // Formato YYYY-MM-DD
                                    formYear = parseInt(parts[0]);
                                    formMonth = parseInt(parts[1]);
                                    formDay = parseInt(parts[2]);
                                } else {
                                    // Formato DD-MM-YYYY o DD/MM/YYYY
                                    formYear = parseInt(parts[2]);
                                    formMonth = parseInt(parts[1]);
                                    formDay = parseInt(parts[0]);
                                }
                            }
                        }
                        if (formDay !== currentDate.getDate() || 
                            formMonth !== (currentDate.getMonth() + 1) || 
                            formYear !== currentDate.getFullYear()) {
                            
                            alert("' . $langs->trans('VerifactuErrorFechaDebeSerHoy') . '");
                            e.preventDefault();
                            return false;
                        }
                    });
                });
                </script>';
            }
        }

        return 0;
    }
}
