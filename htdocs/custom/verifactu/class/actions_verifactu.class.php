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
     * Valida un CIF/NIF español
     *
     * @param string $cif CIF/NIF a validar
     * @return bool True si es válido
     */
    private function validarCIF($cif)
    {
        if (empty($cif)) {
            return false;
        }

        $cif = strtoupper(trim($cif));

        // Verificar longitud
        if (strlen($cif) != 9) {
            return false;
        }

        // Verificar primer carácter (tipo de documento)
        $primerCaracter = $cif[0];
        if (!preg_match('/^[ABCDEFGHJNPQRSUVW]{1}$/', $primerCaracter)) {
            return false;
        }

        // Verificar que los siguientes 7 caracteres sean dígitos
        if (!preg_match('/^\d{7}$/', substr($cif, 1, 7))) {
            return false;
        }

        // Calcular dígito de control
        $letras = 'JABCDEFGHI';
        $numeros = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9);
        $suma = 0;

        for ($i = 1; $i < 8; $i++) {
            $digito = (int)$cif[$i];
            if ($i % 2 == 0) {
                $suma += $digito;
            } else {
                $doble = $digito * 2;
                $suma += ($doble >= 10) ? $doble - 9 : $doble;
            }
        }

        $resto = $suma % 10;
        $digitoControl = ($resto == 0) ? 0 : 10 - $resto;

        // Para CIF, el dígito de control puede ser número o letra
        $ultimoCaracter = $cif[8];
        if (is_numeric($ultimoCaracter)) {
            return ((int)$ultimoCaracter == $digitoControl);
        } else {
            return ($ultimoCaracter == $letras[$digitoControl]);
        }
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

                    // Lógica de selección automática de tipo de factura basado en cliente
                    if (isCreating) {
                        var clienteGenerico = "' . $conf->global->INVOICE_CLIENTE_GENERICO . '";
                        if (clienteGenerico) {
                            // Obtener socid de la URL
                            var urlParams = new URLSearchParams(window.location.search);
                            var socid = urlParams.get("socid");
                            
                            // Si no está en URL, buscar en el formulario
                            if (!socid) {
                                socid = $("input[name=\'socid\']").val() || $("select[name=\'socid\']").val();
                            }
                            
                            if (socid) {
                                if (socid == clienteGenerico) {
                                    $("select[name*=\'options_fk_facture_type\']").val("2").change();
                                } else {
                                    if (socid == -1) {  
                                        $("select[name*=\'options_fk_facture_type\']").val("").change();
                                    }else{
                                        $("select[name*=\'options_fk_facture_type\']").val("1").change();
                                    }
                                }
                            } else {
                                    $("select[name*=\'options_fk_facture_type\']").val("").change();
                            }
                        } else {

                                $("select[name*=\'options_fk_facture_type\']").val("").change();

                        }
                    }

                    // Verificar si la factura no está en estado borrador y mostrar alerta
                    if (isExistingInvoice && ' . ($object->status > 0 ? 'true' : 'false') . ') {
                        //alert("Verifactu: La factura no está en estado borrador, se deshabilitan botones de modificar y eliminar");

                        // DESHABILITAR EFECTIVAMENTE LOS BOTONES DE MODIFICAR Y ELIMINAR
                        setTimeout(function() {
                            // Buscar y deshabilitar botones de modificar y eliminar con múltiples selectores
                            var buttonSelectors = [
                                \'a[href*="action=edit"]\',
                                \'a[href*="action=delete"]\',
                                \'a.butAction[href*="edit"]\',
                                \'a.butActionDelete[href*="delete"]\',
                                \'input[name="edit"]\',
                                \'input[name="delete"]\',
                                \'input[value*="Modificar"]\',
                                \'input[value*="Eliminar"]\',
                                \'.butAction\',
                                \'.butActionDelete\'
                            ];

                            var hiddenCount = 0;
                            buttonSelectors.forEach(function(selector) {
                                $(selector).each(function() {
                                    var $this = $(this);
                                    var href = $this.attr("href") || "";
                                    var value = $this.attr("value") || "";
                                    var text = $this.text() || "";

                                    // Verificar si el botón es de editar o eliminar
                                    if (href.includes("edit") || href.includes("delete") ||
                                        value.includes("Modificar") || value.includes("Eliminar") ||
                                        text.includes("Modificar") || text.includes("Eliminar") ||
                                        text.includes("Edit") || text.includes("Delete")) {

                                        $this.hide();
                                        $this.prop("disabled", true);
                                        hiddenCount++;
                                        console.log("Verifactu: Ocultado botón:", text || value || href);
                                    }
                                });
                            });

                            console.log("Verifactu: Total de botones ocultados/deshabilitados:", hiddenCount);
                        }, 100);
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
                    disableAdvanceInvoiceType();

                    // FUNCIÓN PARA DESHABILITAR TIPO DE FACTURA "ANTICIPO"
                    function disableAdvanceInvoiceType() {
                        // Buscar radiobuttons de tipo de factura
                        // En Dolibarr, el tipo "Anticipo" normalmente tiene value="3"
                        var advanceRadios = $(\'input[type="radio"][name="type"][value="3"]\');

                        if (advanceRadios.length > 0) {
                            // Deshabilitar el radiobutton de anticipo
                            advanceRadios.prop("disabled", true);
                            advanceRadios.prop("checked", false);

                            // Ocultar visualmente la opción de anticipo
                            advanceRadios.closest("label, .radio, tr, div").hide();

                            // Agregar mensaje explicativo si no existe
                            var advanceContainer = advanceRadios.closest("td, div").first();
                            if (advanceContainer.length && !advanceContainer.find(".verifactu-anticipo-warning").length) {
                                advanceContainer.append(
                                    \'<div class="verifactu-anticipo-warning" style="background:#f8d7da; border:1px solid #f5c6cb; padding:6px; margin:5px 0; border-radius:3px; font-size:11px; color:#721c24;">\' +
                                    \'<i class="fa fa-ban"></i> <strong>Verifactu:</strong> Los anticipos no están permitidos en este sistema.\' +
                                    \'</div>\'
                                );
                            }

                            console.log("Verifactu: Radiobutton de anticipo deshabilitado");
                        }

                        // También buscar con otros posibles selectores
                        var otherAdvanceSelectors = [
                            \'select[name="type"] option[value="3"]\',
                            \'input[value*="anticip"]\',
                            \'input[value*="deposit"]\',
                            \'input[value*="advance"]\'
                        ];

                        otherAdvanceSelectors.forEach(function(selector) {
                            $(selector).each(function() {
                                var $this = $(this);
                                var value = $this.val();
                                var text = $this.text() || $this.next("label").text() || "";

                                // Verificar si es el tipo anticipo
                                if (value == "3" ||
                                    text.toLowerCase().includes("anticip") ||
                                    text.toLowerCase().includes("deposit") ||
                                    text.toLowerCase().includes("advance")) {

                                    if ($this.is("option")) {
                                        $this.prop("disabled", true).hide();
                                    } else {
                                        $this.prop("disabled", true);
                                        $this.closest("label, .radio, tr, div").hide();
                                    }

                                    console.log("Verifactu: Opción de anticipo encontrada y deshabilitada:", text);
                                }
                            });
                        });
                    }

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
            $this->resprints .= '
                <script type="text/javascript">
                $(document).ready(function() {
                    // === Mover campo "Tipo de factura" al inicio del formulario ===
                    var fkTypeRow = $("tr:has(select[name*=\'options_fk_facture_type\'])");
                    if (fkTypeRow.length) {
                        var firstRow = $("form[name=\'add\'] tr, form[name=\'update\'] tr").first();
                        if (firstRow.length) {
                            fkTypeRow.detach().insertAfter(firstRow);
                        }
                    }
                });
                </script>';
        }
		
        return 0;
    }

    // /**
    //  * Hook para interceptar y desactivar los botones de modificar y eliminar en facturas no borrador
    //  */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		if (strpos($parameters['currentcontext'], 'invoicecard') !== false) {
			$langs->load("verifactu@verifactu");

			if (($object->element == 'facture' || get_class($object) == 'Facture')
				&& !empty($object->id) && $object->status > 0) {

				// PINTA el botón tú mismo (no devuelvas array)
				print '<div class="inline-block divButAction">'
					. '<a id="verifactu-xml-btn" class="butAction" target="_blank" '
					. 'href="' . dol_buildpath('/custom/verifactu/xml_preview.php?id=' . $object->id, 1) . '">'
					. '<i class="fa fa-code"></i> ' . $langs->trans("VerXMLVerifactu") . '</a>'
					. '</div>';

				return 0; // no reemplazo los botones estándar
			}
		}

        return 0;
    }

    /**
     * Hook para interceptar antes de guardar cambios en factura, contacto o tercero/cliente
     */
    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $user;

        // Validación de terceros/clientes
        if ($parameters['currentcontext'] === 'thirdpartycard') {
            $langs->load("verifactu@verifactu");
            
            dol_syslog("Verifactu: doActions EJECUTADO para tercero - Contexto: " . ($parameters['currentcontext'] ?? 'N/A') . ", Acción: " . $action . ", Elemento: " . ($object->element ?? 'N/A'));

            // Validar cuando se crea un cliente (solo cuando se envía el formulario)
            if (($action == 'add' || $action == 'create') && $_SERVER['REQUEST_METHOD'] == 'POST') {
                $errors = array();

                // Validar dirección obligatoria
                $address = trim($_POST['address'] ?? '');
                if (empty($address)) {
                    $errors[] = "La dirección es obligatoria para clientes según normativa Verifactu";
                }

                // Validar CIF/NIF obligatorio
                $cif = trim($_POST['idprof1'] ?? '');
                if (empty($cif)) {
                    $errors[] = "El CIF/NIF es obligatorio para clientes según normativa Verifactu";
                } elseif (!$this->validarCIF($cif)) {
                    $errors[] = "El CIF/NIF proporcionado no es válido";
                }

                // Validar código postal obligatorio
                $zip = trim($_POST['zipcode'] ?? $_POST['zip'] ?? '');
                if (empty($zip)) {
                    $errors[] = "El código postal es obligatorio para clientes según normativa Verifactu";
                }

                // Validar población obligatoria
                $town = trim($_POST['town'] ?? '');
                if (empty($town)) {
                    $errors[] = "La población es obligatoria para clientes según normativa Verifactu";
                }

                // Si hay errores, mostrarlos y bloquear la creación
                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        setEventMessages($error, null, 'errors');
                    }
                    dol_syslog("Verifactu: Validación de cliente fallida en doActions: " . implode(", ", $errors));
                    
                    // Cambiar la acción para volver al formulario sin redirección
                    $action = 'create';
                    return -1; // Retornar error para bloquear el guardado
                }

                dol_syslog("Verifactu: Validación de cliente exitosa en doActions");
            }
        }

        if ($parameters['currentcontext'] === 'invoicecard') {
            $langs->load("verifactu@verifactu");

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

        if ($parameters['currentcontext'] === 'thirdpartycard' || $parameters['currentcontext'] === 'thirdparty') {
            $langs->load("verifactu@verifactu");

            // Solo aplicar en terceros/clientes
            if ($object->element == 'societe' || get_class($object) == 'Societe') {

                dol_syslog("Verifactu: formObjectOptions EJECUTADO para tercero - Contexto: " . ($parameters['currentcontext'] ?? 'N/A') . ", Elemento: " . ($object->element ?? 'N/A'));

                $this->resprints .= '
                <script type="text/javascript">
                $(document).ready(function() {
                    console.log("Verifactu: JavaScript de validación de clientes cargado");
                    console.log("Verifactu: Contexto actual:", "' . ($parameters['currentcontext'] ?? 'N/A') . '");
                    console.log("Verifactu: Elemento del objeto:", "' . ($object->element ?? 'N/A') . '");

                    // Interceptar envío del formulario de cliente/tercero
                    $(\'form[name="add"], form[name="update"]\').on("submit", function(e) {
                        console.log("Verifactu: Interceptando envío de formulario de cliente");

                        var errors = [];

                        // Validar dirección obligatoria
                        var address = $(\'input[name="address"]\').val() || "";
                        if (!address.trim()) {
                            errors.push("La dirección es obligatoria para clientes según normativa Verifactu");
                        }

                        // Validar CIF/NIF obligatorio
                        var cif = $(\'input[name="idprof1"]\').val() || "";
                        if (!cif.trim()) {
                            errors.push("El CIF/NIF es obligatorio para clientes según normativa Verifactu");
                        } else {
                            // Validar formato CIF básico
                            var cifRegex = /^[ABCDEFGHJNPQRSUVW]{1}\d{7}[0-9A-J]$/i;
                            if (!cifRegex.test(cif.toUpperCase())) {
                                errors.push("El CIF/NIF proporcionado no tiene un formato válido");
                            }
                        }

                        // Validar código postal obligatorio
                        var zip = $(\'input[name="zipcode"], input[name="zip"]\').val() || "";
                        if (!zip.trim()) {
                            errors.push("El código postal es obligatorio para clientes según normativa Verifactu");
                        }

                        // Validar población obligatoria
                        var town = $(\'input[name="town"]\').val() || "";
                        if (!town.trim()) {
                            errors.push("La población es obligatoria para clientes según normativa Verifactu");
                        }

                        // Si hay errores, mostrarlos y prevenir envío
                        if (errors.length > 0) {
                            console.log("Verifactu: Errores de validación encontrados:", errors);
                            alert("Errores de validación:\n\n" + errors.join("\n"));
                            e.preventDefault();
                            return false;
                        }

                        console.log("Verifactu: Validación de cliente exitosa, permitiendo envío");
                        return true;
                    });
                });
                </script>';
            }
        }

        return 0;
    }


}
