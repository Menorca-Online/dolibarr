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

                // Determinar si la factura ya existe (modo edición) o se está creando
                $isExistingInvoice = !empty($object->id) && $object->id > 0;
                $isCreating = ($action === 'create' || empty($object->id));

                $this->resprints .= '
                <script type="text/javascript">
                $(document).ready(function() {
                    var isExistingInvoice = ' . ($isExistingInvoice ? 'true' : 'false') . ';
                    var isCreating = ' . ($isCreating ? 'true' : 'false') . ';

                    // Verificar si la factura no está en estado borrador y mostrar alerta
                    if (isExistingInvoice && ' . ($object->status > 0 ? 'true' : 'false') . ') {
                        alert("Verifactu: La factura no está en estado borrador, se deshabilitan botones de modificar y eliminar");
                    }

                    // Remover botones que permiten modificar fecha
                    $(\'#reButtonNow\').remove();
                    $(\'.ui-datepicker-trigger\').remove();

                    // Establecer fecha actual
                    var today = new Date();
                    var day = today.getDate();
                    var month = today.getMonth() + 1;
                    var year = today.getFullYear();
                    var todayStr = year + "-" + (month < 10 ? "0" : "") + month + "-" + (day < 10 ? "0" : "") + day;

                    function applyVerifactuDateRules() {
                        var dateInput = $("input[name=\'re\']");

                        if (isCreating) {
                            // MODO CREACIÓN: Forzar fecha actual
                            dateInput.val(todayStr).prop("readonly", true);

                            // También establecer campos ocultos si existen
                            $("input[name=\'reday\']").val(day);
                            $("input[name=\'remonth\']").val(month);
                            $("input[name=\'reyear\']").val(year);

                            showVerifactuWarning("create");

                        } else if (isExistingInvoice) {
                            // MODO EDICIÓN: Bloquear completamente el campo
                            dateInput.prop("readonly", true).prop("disabled", false);

                            // Bloquear también cualquier selector de fecha
                            $("select[name=\'remonth\'], select[name=\'reday\'], select[name=\'reyear\']")
                                .prop("disabled", true);

                            showVerifactuWarning("edit");
                        }

                        // Estilo visual para campos bloqueados
                        $("input[name=\'re\'], select[name=\'remonth\'], select[name=\'reday\'], select[name=\'reyear\']").css({
                            "background-color": "#f5f5f5",
                            "color": "#666",
                            "cursor": "not-allowed"
                        });

                        // Interceptar cualquier intento de cambio
                        dateInput.on("focus click keydown keyup", function(e) {
                            if (isExistingInvoice) {
                                e.preventDefault();
                                alert("' . $langs->trans('VerifactuErrorFechaNoModificable') . '");
                                return false;
                            }
                        });
                    }

                    function showVerifactuWarning(mode) {
                        if ($(".verifactu-date-warning").length > 0) return;

                        var message = "";
                        if (mode === "create") {
                            message = "La fecha de factura se establece automáticamente a la fecha actual según la normativa Verifactu.";
                        } else {
                            message = "La fecha de factura no puede modificarse una vez creada según la normativa Verifactu.";
                        }

                        var warningHtml = \'<tr class="verifactu-date-warning"><td colspan="4">\' +
                            \'<div style="background:#fff3cd; border:1px solid #ffeaa7; padding:8px; margin:5px 0; border-radius:4px; font-size:12px;">\' +
                            \'<i class="fa fa-exclamation-triangle" style="color:#856404;"></i> \' +
                            \'<strong>Normativa Verifactu:</strong> \' + message +
                            \'</div></td></tr>\';

                        var dateContainer = $("input[name=\'re\']").closest("tr");
                        if (dateContainer.length) {
                            dateContainer.after(warningHtml);
                        }
                    }

                    // Gestionar campos hash y hash_anterior - mostrar solo cuando la factura ya existe
                    function blockHashFields() {
                        // PASO 1: Buscar tanto los campos como sus contenedores (filas, divs, etc.)

                        // Dolibarr puede usar diferentes nombres de selector según la versión y configuración
                        var hashSelectors = [
                            "input[name=\'options_hash\']",
                            "#options_hash",
                            "input[name=\'hash\']",
                            "#hash",
                            "[name^=\'hash\']:not([name*=\'anterior\'])",
                            "input[data-fieldname=\'hash\']",
                            "input[id$=\'_hash\']"
                        ];

                        var hashAnteriorSelectors = [
                            "input[name=\'options_hash_anterior\']",
                            "#options_hash_anterior",
                            "input[name=\'hash_anterior\']",
                            "#hash_anterior",
                            "[name^=\'hash_anterior\']",
                            "input[data-fieldname=\'hash_anterior\']",
                            "input[id$=\'_hash_anterior\']"
                        ];

                        // Buscar contenedores (filas, divs, etc)
                        var hashRowSelectors = [
                            "tr:has(" + hashSelectors.join("), tr:has(") + ")",
                            "div.form-group:has(" + hashSelectors.join("), div.form-group:has(") + ")",
                            ".hash",
                            "tr.hash",
                            "div.hash"
                        ];

                        var hashAnteriorRowSelectors = [
                            "tr:has(" + hashAnteriorSelectors.join("), tr:has(") + ")",
                            "div.form-group:has(" + hashAnteriorSelectors.join("), div.form-group:has(") + ")",
                            ".hash_anterior",
                            "tr.hash_anterior",
                            "div.hash_anterior"
                        ];

                        // PASO 2: Ejecutar búsquedas
                        var hashField = $(hashSelectors.join(", "));
                        var hashAnteriorField = $(hashAnteriorSelectors.join(", "));
                        var hashRow = $(hashRowSelectors.join(", "));
                        var hashAnteriorRow = $(hashAnteriorRowSelectors.join(", "));

                        // Eliminar cualquier aviso previo para evitar duplicados
                        $(".verifactu-hash-warning").remove();

                        // PASO 3: Decidir qué hacer según el modo (creación o edición)

                        // MODO CREACIÓN: Ocultar completamente los campos
                        if (isCreating) {
                            if (hashRow.length > 0) hashRow.hide();
                            if (hashAnteriorRow.length > 0) hashAnteriorRow.hide();
                            if (hashField.length > 0) hashField.closest("tr, div.form-group").hide();
                            if (hashAnteriorField.length > 0) hashAnteriorField.closest("tr, div.form-group").hide();
                            return; // No hay más que hacer en modo creación
                        }

                        // MODO EDICIÓN: Solo mostrar como lectura

                        // PASO 4: Mostrar y configurar campos para modo edición
                        if (hashRow.length > 0 || hashField.length > 0) {
                            if (hashRow.length > 0) hashRow.show();
                            if (hashField.length > 0) {
                                hashField.closest("tr, div.form-group").show();
                                // Guardar valor original solo una vez
                                if (!hashField.data("original-value")) {
                                    hashField.data("original-value", hashField.val());
                                }

                                // Configurar como solo lectura sin disparar eventos
                                hashField
                                    .prop("readonly", true)
                                    .removeAttr("disabled")
                                    .css({
                                        "background-color": "#f5f5f5",
                                        "color": "#666",
                                        "cursor": "not-allowed"
                                    });

                                // Desactivar eventos existentes y agregar nuevos sin alertas
                                hashField.off().on("focus click keydown keyup change", function(e) {
                                    e.preventDefault();
                                    $(this).val($(this).data("original-value") || "");
                                    return false;
                                });
                            }
                        }

                        if (hashAnteriorRow.length > 0 || hashAnteriorField.length > 0) {
                            if (hashAnteriorRow.length > 0) hashAnteriorRow.show();
                            if (hashAnteriorField.length > 0) {
                                hashAnteriorField.closest("tr, div.form-group").show();
                                // Guardar valor original solo una vez
                                if (!hashAnteriorField.data("original-value")) {
                                    hashAnteriorField.data("original-value", hashAnteriorField.val());
                                }

                                // Configurar como solo lectura sin disparar eventos
                                hashAnteriorField
                                    .prop("readonly", true)
                                    .removeAttr("disabled")
                                    .css({
                                        "background-color": "#f5f5f5",
                                        "color": "#666",
                                        "cursor": "not-allowed"
                                    });

                                // Desactivar eventos existentes y agregar nuevos sin alertas
                                hashAnteriorField.off().on("focus click keydown keyup change", function(e) {
                                    e.preventDefault();
                                    $(this).val($(this).data("original-value") || "");
                                    return false;
                                });
                            }
                        }

                        // PASO 5: Añadir mensaje explicativo solo en modo edición
                        if ((hashField.length > 0 || hashAnteriorField.length > 0) && $(".verifactu-hash-warning").length === 0) {
                            var warningHtml = \'<tr class="verifactu-hash-warning"><td colspan="4">\' +
                                \'<div style="background:#fff3cd; border:1px solid #ffeaa7; padding:8px; margin:5px 0; border-radius:4px; font-size:12px;">\' +
                                \'<i class="fa fa-lock" style="color:#856404;"></i> \' +
                                \'<strong>Campos de seguridad:</strong> Los campos Hash y Hash Anterior son gestionados automáticamente por el sistema.\' +
                                \'</div></td></tr>\';

                            // Intentar insertar el mensaje en el lugar adecuado
                            var targetRow = null;
                            if (hashField.length > 0) {
                                targetRow = hashField.closest("tr");
                            } else if (hashAnteriorField.length > 0) {
                                targetRow = hashAnteriorField.closest("tr");
                            }

                            if (targetRow && targetRow.length > 0) {
                                targetRow.after(warningHtml);
                            }
                        }
                    }

                    // Aplicar reglas inmediatamente
                    applyVerifactuDateRules();
                    blockHashFields();

                    // Monitorear cambios cada segundo SOLO para fecha (no para los campos hash)
                    setInterval(function() {
                        if (isExistingInvoice) {
                            $("input[name=\'re\']").prop("readonly", true);
                            $("select[name=\'remonth\'], select[name=\'reday\'], select[name=\'reyear\']").prop("disabled", true);
                        }
                        // Ya NO llamamos a blockHashFields() aquí para evitar alertas infinitas
                        // Los campos hash se configuran una vez al inicio
                    }, 1000);

                    // Interceptar envío del formulario
                    $(\'form[name="add"], form[name="update"]\').on("submit", function(e) {
                        // Validación para fecha actual en creación
                        if (isCreating) {
                            // Validar que la fecha sea hoy en modo creación
                            var currentDate = new Date();
                            var formDate = $("input[name=\'re\']").val();

                            if (formDate !== todayStr) {
                                alert("' . $langs->trans('VerifactuErrorFechaDebeSerHoy') . '");
                                e.preventDefault();
                                return false;
                            }
                        }

                        // Validación para campos hash en modo edición
                        if (isExistingInvoice) {
                            // Buscar los campos con todos los posibles selectores
                            var hashSelectors = [
                                "input[name=\'options_hash\']",
                                "#options_hash",
                                "input[name=\'hash\']",
                                "#hash",
                                "[name^=\'hash\']:not([name*=\'anterior\'])",
                                "input[data-fieldname=\'hash\']",
                                "input[id$=\'_hash\']"
                            ];

                            var hashAnteriorSelectors = [
                                "input[name=\'options_hash_anterior\']",
                                "#options_hash_anterior",
                                "input[name=\'hash_anterior\']",
                                "#hash_anterior",
                                "[name^=\'hash_anterior\']",
                                "input[data-fieldname=\'hash_anterior\']",
                                "input[id$=\'_hash_anterior\']"
                            ];

                            var hashField = $(hashSelectors.join(", "));
                            var hashAnteriorField = $(hashAnteriorSelectors.join(", "));

                            // Solo validar si encontramos los campos
                            if (hashField.length > 0) {
                                var originalHash = hashField.data("original-value") || hashField.val();

                                // Asegurar que el campo no está disabled al enviar
                                hashField.removeAttr("disabled");

                                // Restaurar silenciosamente si hay cambios
                                if (hashField.val() !== originalHash) {
                                    hashField.val(originalHash);
                                }
                            }

                            if (hashAnteriorField.length > 0) {
                                var originalHashAnterior = hashAnteriorField.data("original-value") || hashAnteriorField.val();

                                // Asegurar que el campo no está disabled al enviar
                                hashAnteriorField.removeAttr("disabled");

                                // Restaurar silenciosamente si hay cambios
                                if (hashAnteriorField.val() !== originalHashAnterior) {
                                    hashAnteriorField.val(originalHashAnterior);
                                }
                            }
                        }
                    });
                });
                </script>';
            }
        }

        return 0;
    }

    /**
     * Hook para interceptar y desactivar los botones de modificar y eliminar en facturas no borrador
     */
    public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $user, $conf;

        if ($parameters['currentcontext'] === 'invoicecard') {
            $langs->load("verifactu@verifactu");

            // Solo aplicar en facturas existentes que NO son borrador
            if (($object->element == 'facture' || get_class($object) == 'Facture') && !empty($object->id) && $object->status > 0) {
                // Intentar ocultar los botones directamente
                $this->resprints = '<style>
                    .butAction[href*="action=edit"],
                    .butAction[href*="action=delete"],
                    .butActionDelete[href*="action=delete"],
                    span.butAction:contains("Modificar"),
                    span.butActionDelete:contains("Eliminar") {
                        display: none !important;
                    }
                </style>';

                // Agregar también un script que se ejecutará al final del DOM
                $this->resprints .= '<script type="text/javascript">
                document.addEventListener("DOMContentLoaded", function() {
                    // Buscar y ocultar botones de modificar y eliminar
                    var buttons = document.querySelectorAll(".butAction[href*=\'action=edit\'], .butAction[href*=\'action=delete\'], .butActionDelete");
                    buttons.forEach(function(button) {
                        button.style.display = "none";
                    });

                    console.log("Verifactu: Se ocultaron " + buttons.length + " botones de modificar/eliminar");
                });
                </script>';
            }
        }

        return 0;
    }

    /**
     * Hook para interceptar antes de guardar cambios en factura
     */
    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $user;

        if ($parameters['currentcontext'] === 'invoicecard') {
            $langs->load("verifactu@verifactu");

            // Solo aplicar en facturas existentes
            if (($object->element == 'facture' || get_class($object) == 'Facture') && !empty($object->id)) {

                // Si se está intentando modificar la fecha en una factura existente
                if ($action == 'update' && isset($_POST['re'])) {

                    // Obtener la fecha original de la base de datos
                    $sql = "SELECT date_facture FROM " . MAIN_DB_PREFIX . "facture WHERE rowid = " . ((int) $object->id);
                    $resql = $this->db->query($sql);

                    if ($resql && $this->db->num_rows($resql) > 0) {
                        $obj = $this->db->fetch_object($resql);
                        $originalDate = $obj->date_facture;

                        // Verificar si se está intentando cambiar la fecha
                        $newDate = $_POST['re'];
                        if (!empty($newDate) && $newDate != $originalDate) {

                            // BLOQUEAR el cambio
                            setEventMessages($langs->trans('VerifactuErrorFechaNoModificable'), null, 'errors');

                            // Restaurar la fecha original en el POST para evitar el cambio
                            $_POST['re'] = $originalDate;
                            $_POST['reday'] = date('d', strtotime($originalDate));
                            $_POST['remonth'] = date('m', strtotime($originalDate));
                            $_POST['reyear'] = date('Y', strtotime($originalDate));

                            dol_syslog("Verifactu: Intento de modificar fecha bloqueado en doActions. Original: $originalDate, Intento: $newDate");
                        }
                    }
                }

                // Proteger campos hash y hash_anterior de modificaciones
                if ($action == 'update') {
                    // Obtener valores originales de los campos hash
                    $sql = "SELECT hash, hash_anterior FROM " . MAIN_DB_PREFIX . "facture_extrafields WHERE fk_object = " . ((int) $object->id);
                    $resql = $this->db->query($sql);

                    if ($resql && $this->db->num_rows($resql) > 0) {
                        $obj = $this->db->fetch_object($resql);
                        $originalHash = $obj->hash;
                        $originalHashAnterior = $obj->hash_anterior;

                        // Verificar si se intenta cambiar el hash
                        if (isset($_POST['options_hash']) && $_POST['options_hash'] !== $originalHash) {
                            setEventMessages("El campo Hash es de seguridad y no puede ser modificado manualmente", null, 'errors');
                            $_POST['options_hash'] = $originalHash;
                            dol_syslog("Verifactu: Intento de modificar hash bloqueado en doActions");
                        }

                        // Verificar si se intenta cambiar el hash anterior
                        if (isset($_POST['options_hash_anterior']) && $_POST['options_hash_anterior'] !== $originalHashAnterior) {
                            setEventMessages("El campo Hash Anterior es de seguridad y no puede ser modificado manualmente", null, 'errors');
                            $_POST['options_hash_anterior'] = $originalHashAnterior;
                            dol_syslog("Verifactu: Intento de modificar hash_anterior bloqueado en doActions");
                        }
                    }
                }
            }
        }

        return 0;
    }
}
