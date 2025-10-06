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

include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/lib/verifactu.lib.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactufacturetype.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
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
    /**
     * Valida un CIF, NIF, NIE o DNI español
     *
     * @param string $doc Documento a validar
     * @return bool True si es válido
     */
    private function validarCIFNIFNIEDNI($doc)
    {
        if (empty($doc)) {
            return false;
        }

        $doc = strtoupper(trim($doc));

        // --- Validar NIF/DNI (8 dígitos + letra) ---
        if (preg_match('/^[0-9]{8}[A-Z]$/', $doc)) {
            $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
            $numero = substr($doc, 0, 8);
            $letra = substr($doc, -1);
            return ($letra === $letras[$numero % 23]);
        }

        // --- Validar NIE (X/Y/Z + 7 dígitos + letra) ---
        if (preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $doc)) {
            $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
            $numero = str_replace(['X', 'Y', 'Z'], ['0', '1', '2'], substr($doc, 0, 1)) . substr($doc, 1, 7);
            $letra = substr($doc, -1);
            return ($letra === $letras[$numero % 23]);
        }

        // --- Validar CIF ---
        if (preg_match('/^[ABCDEFGHJNPQRSUVW][0-9]{7}[0-9A-J]$/', $doc)) {
            $letras = 'JABCDEFGHI';
            $suma = 0;

            for ($i = 1; $i < 8; $i++) {
                $digito = (int)$doc[$i];
                if ($i % 2 == 0) {
                    $suma += $digito;
                } else {
                    $doble = $digito * 2;
                    $suma += ($doble >= 10) ? $doble - 9 : $doble;
                }
            }

            $resto = $suma % 10;
            $digitoControl = ($resto == 0) ? 0 : 10 - $resto;
            $ultimo = $doc[8];

            if (is_numeric($ultimo)) {
                return ((int)$ultimo == $digitoControl);
            } else {
                return ($ultimo == $letras[$digitoControl]);
            }
        }

        // --- NIF especiales (K, L, M) validan como DNI ---
        if (preg_match('/^[KLM][0-9]{7}[A-Z]$/', $doc)) {
            $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
            $numero = substr($doc, 1, 7);
            $letra = substr($doc, -1);
            return ($letra === $letras[$numero % 23]);
        }

        return false; // No cumple ningún formato
    }


    /**
     * Hook: formObjectOptions
     * Permite añadir campos al formulario de facturas y aplicar normativa Verifactu
     */
    public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $db, $conf, $extrafields;



        if ($parameters['currentcontext'] === 'invoicecard') {
            $langs->load("verifactu@verifactu");

            // Solo aplicar en facturas
            if ($object->element == 'facture' || get_class($object) == 'Facture') {

                if (count($object->array_options) > 0)
                    if ($object->array_options['options_fk_verifactu_registro_estado'] == VERIFACTU_ESTADO_REGISTRO_INCORRECTO) {
                        $extrafields->attributes['facture']['alwayseditable']['fk_facture_type'] = 1;
                    }

                // Determinar si la factura ya existe (modo edición) o se está creando
                $isExistingInvoice = !empty($object->id) && $object->id > 0;
                $isCreating = ($action === 'create' || empty($object->id));

                if ($isCreating) {
                    $oldType = -1;
                    //leemos la factura original para obtener su fk_facture_type
                    $factureOriginal = new Facture($db);
                    if (isset($_GET['facid']) && is_numeric($_GET['facid']) && $_GET['facid'] > 0) {
                        $factureOriginal->fetch($_GET['facid']);
                    }
                    //si es una factura recurrente, leemos la factura origen
                    if (isset($_GET['fac_rec']) && is_numeric($_GET['fac_rec']) && $_GET['fac_rec'] > 0) {
                        $factureOriginal->fetch($_GET['fac_rec']);
                    } //sino puede ser una factura de abono
                    else if (isset($_GET['fac_avoir']) && is_numeric($_GET['fac_avoir']) && $_GET['fac_avoir'] > 0) {
                        $factureOriginal->fetch($_GET['fac_avoir']);
                    }
                    if ($factureOriginal->id > 0 && isset($factureOriginal->array_options['options_fk_facture_type']) && $factureOriginal->array_options['options_fk_facture_type'] > 0) {
                        $verifactuFactureType = new VerifactuFactureType($db);
                        $verifactuFactureType->fetch($factureOriginal->array_options['options_fk_facture_type']);
                        $oldType = $verifactuFactureType->code;
                    }
                }


                // Detectar si es factura correctiva (tiene factura origen)
                $isFacturaCorrectiva = $object->fk_facture_source > 0;

                // También detectar por URL para casos de creación
                $correctionFromUrl = (
                    isset($_GET['fac_avoir']) ||        // Factura de abono
                    isset($_GET['fac_rec']) ||          // Factura recurrente  
                    isset($_GET['facid']) ||            // ID de factura origen
                    isset($_GET['fac_replacement']) ||  // Factura de reemplazo
                    strpos($_SERVER['REQUEST_URI'] ?? '', 'fac_avoir') !== false ||
                    strpos($_SERVER['REQUEST_URI'] ?? '', 'fac_rec') !== false ||
                    strpos($_SERVER['REQUEST_URI'] ?? '', 'correction') !== false
                );

                // Combinar ambas detecciones
                $isFacturaCorrectiva = $isFacturaCorrectiva || $correctionFromUrl;

                // Resetear el estado de registro Verifactu para facturas correctivas en creación
                if ($isCreating && $isFacturaCorrectiva) {
                    // Resetear el campo fk_verifactu_registro_estado para que no mantenga el valor de la factura original
                    $object->array_options['options_fk_verifactu_registro_estado'] = '';
                    dol_syslog("Verifactu: Reseteado campo fk_verifactu_registro_estado para factura correctiva en creación");
                }

                $this->resprints .= '
                <script type="text/javascript">
                $(document).ready(function() {
                    var isExistingInvoice = ' . ($isExistingInvoice ? 'true' : 'false') . ';
                    var isCreating = ' . ($isCreating ? 'true' : 'false') . ';
                    var isFacturaCorrectiva = ' . ($isFacturaCorrectiva ? 'true' : 'false') . ';
                    var oldType = "' . ($oldType ?? '') . '";
                    
                    console.log("Verifactu: Información de detección:");
                    console.log("  - fk_facture_source:", ' . ($object->fk_facture_source ?? 0) . ');
                    console.log("  - URL actual:", window.location.href);
                    console.log("  - Parámetros GET:", new URLSearchParams(window.location.search).toString());
                    console.log("  - isFacturaCorrectiva:", isFacturaCorrectiva);
                    console.log("  - oldType (factura origen):", oldType); ' . ($isExistingInvoice ? 'true' : 'false') . ';
                    var isCreating = ' . ($isCreating ? 'true' : 'false') . ';
                    var isFacturaCorrectiva = ' . ($isFacturaCorrectiva ? 'true' : 'false') . ';
                    
                    console.log("Verifactu: Información de detección:");
                    console.log("  - fk_facture_source:", ' . ($object->fk_facture_source ?? 0) . ');
                    console.log("  - URL actual:", window.location.href);
                    console.log("  - Parámetros GET:", new URLSearchParams(window.location.search).toString());
                    console.log("  - isFacturaCorrectiva:", isFacturaCorrectiva);
                    
                    // Verificar parámetros específicos en JavaScript también
                    var urlParams = new URLSearchParams(window.location.search);
                    var hasCorrectiveParams = urlParams.has("fac_avoir") || 
                                             urlParams.has("fac_rec") || 
                                             urlParams.has("facid") || 
                                             urlParams.has("fac_replacement");
                    
                    console.log("  - Parámetros correctivos detectados:", hasCorrectiveParams);
                    
                    // Si PHP no detectó pero JS sí, actualizar la variable
                    if (!isFacturaCorrectiva && hasCorrectiveParams) {
                        isFacturaCorrectiva = true;
                        console.log("  - ✓ Factura correctiva detectada por JavaScript");
                    }

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

                    if (isFacturaCorrectiva) {
                        function filtrarTiposFacturaCorrectiva() {
                            var selectTipoFactura = $("select[name*=\'options_fk_facture_type\']");
                            
                            if (selectTipoFactura.length > 0) {
                                console.log("🔧 filtrarTiposFacturaCorrectiva - oldType:", oldType);
                                
                                var currentValue = selectTipoFactura.val();
                                var currentText = selectTipoFactura.find("option:selected").text().trim();
                                
                                console.log("🔧 Valor actual antes del filtro:", currentValue, "->", currentText);
                                
                                if (currentValue && currentValue !== "" && currentValue !== "0" && 
                                    !currentText.match(/^R\d+ - /)) {
                                    selectTipoFactura.val("").trigger("change");
                                    console.log("🧹 Limpiado valor no válido para corrección");
                                }
                                
                                var optionsVisible = 0;
                                selectTipoFactura.find("option").each(function() {
                                    var optionText = $(this).text().trim();
                                    var optionValue = $(this).val();
                                    
                                    if (optionValue === "" || optionValue === "0") {
                                        return true;
                                    }
                                    
                                    var startsWithR = false;
                                    var regexMatch = optionText.match(/^R\d+ - /);
                                    if (regexMatch) {
                                        startsWithR = true;
                                        optionsVisible++;
                                    }
                                    
                                    if (!startsWithR) {
                                        $(this).prop("disabled", true).hide();
                                    } else {
                                        $(this).prop("disabled", false).show();
                                        console.log("👁️ Opción visible:", optionText, "Value:", optionValue);
                                    }
                                });
                                
                                console.log("📊 Total opciones R visibles:", optionsVisible);
                                
                                if (selectTipoFactura.parent().find(".verifactu-correction-info").length === 0) {
                                    var mappingMessage = "";
                                    if (oldType === "F1") {
                                        mappingMessage = " Se intentará seleccionar R1 automáticamente (F1 → R1).";
                                    } else if (oldType === "F2") {
                                        mappingMessage = " Se intentará seleccionar R5 automáticamente (F2 → R5).";
                                    } else {
                                        mappingMessage = " Se seleccionará automáticamente el tipo rectificativo correspondiente.";
                                    }
                                    
                                    selectTipoFactura.after(
                                        \'<div class="verifactu-correction-info" style="background:#d1ecf1; border:1px solid #bee5eb; padding:6px; margin:5px 0; border-radius:3px; font-size:11px; color:#0c5460;">\' +
                                        \'<i class="fa fa-info-circle"></i> <strong>Factura correctiva:</strong> Solo se muestran los tipos rectificativos (códigos R).\' + mappingMessage +
                                        \'</div>\'
                                    );
                                }
                            }
                        }
                        
                        function aplicarMapeoAutomatico() {
                            var selectTipoFactura = $("select[name*=\'options_fk_facture_type\']");
                            
                            if (selectTipoFactura.length > 0) {
                                var finalValue = selectTipoFactura.val();
                                console.log("🔍 aplicarMapeoAutomatico - Valor actual:", finalValue);
                                console.log("🔍 aplicarMapeoAutomatico - oldType:", oldType);
                                
                                if (!finalValue || finalValue === "" || finalValue === "0") {
                                    var targetRectificativo = "";
                                    
                                    switch(oldType) {
                                        case "F1":
                                            targetRectificativo = "R1";
                                            break;
                                        case "F2":
                                            targetRectificativo = "R5";
                                            break;
                                        default:
                                            targetRectificativo = "R1";
                                    }
                                    
                                    console.log("🎯 Mapeo determinado:", oldType, "->", targetRectificativo);
                                    
                                    var targetOption = selectTipoFactura.find("option:not(:disabled)").filter(function() {
                                        var optionText = $(this).text().trim();
                                        var regex = new RegExp("^" + targetRectificativo + " - ");
                                        var matches = optionText.match(regex);
                                        console.log("📋 Evaluando opción:", optionText, "-> Regex:", regex, "-> Coincide con", targetRectificativo + "?", matches !== null);
                                        return matches !== null;
                                    });
                                    
                                    console.log("✅ Opciones encontradas para", targetRectificativo + ":", targetOption.length);
                                    
                                    if (targetOption.length > 0) {
                                        var targetValue = targetOption.first().val();
                                        var targetText = targetOption.first().text().trim();
                                        console.log("🎯 Intentando seleccionar:", targetText, "Value:", targetValue);
                                        
                                        selectTipoFactura.val(targetValue).trigger("change");
                                        
                                        // Verificar si se seleccionó correctamente
                                        setTimeout(function() {
                                            var selectedAfter = selectTipoFactura.val();
                                            var selectedText = selectTipoFactura.find("option:selected").text().trim();
                                            console.log("✅ Valor después de selección:", selectedAfter, "->", selectedText);
                                            
                                            if (selectedAfter === targetValue) {
                                                console.log("🎉 ¡ÉXITO! Seleccionado automáticamente", targetRectificativo, "para factura correctiva");
                                            } else {
                                                console.log("❌ ERROR: No se aplicó la selección - Expected:", targetValue, "Got:", selectedAfter);
                                            }
                                        }, 100);
                                        
                                    } else {
                                        console.log("⚠️ No se encontró opción para", targetRectificativo, "- Intentando fallback R1");
                                        
                                        var r1Option = selectTipoFactura.find("option:not(:disabled)").filter(function() {
                                            return $(this).text().trim().match(/^R1 - /);
                                        });
                                        
                                        if (r1Option.length > 0) {
                                            var r1Value = r1Option.first().val();
                                            selectTipoFactura.val(r1Value).trigger("change");
                                            console.log("✅ Fallback: Seleccionado R1");
                                        }
                                    }
                                } else {
                                    console.log("ℹ️ Ya hay una selección:", finalValue);
                                }
                            } else {
                                console.log("❌ No se encontró el select de tipo de factura");
                            }
                        }
                        
                        // Primer paso: aplicar filtro
                        console.log("⏰ Programando filtrado en:", "100ms, 500ms, 1000ms");
                        setTimeout(filtrarTiposFacturaCorrectiva, 100);
                        setTimeout(filtrarTiposFacturaCorrectiva, 500);
                        setTimeout(filtrarTiposFacturaCorrectiva, 1000);
                        
                        // Segundo paso: aplicar mapeo después del filtrado
                        console.log("⏰ Programando mapeo en:", "1500ms, 2000ms, 3000ms");
                        setTimeout(aplicarMapeoAutomatico, 1500);
                        setTimeout(aplicarMapeoAutomatico, 2000);
                        setTimeout(aplicarMapeoAutomatico, 3000);
                    } else {
                        // ====== FILTRAR TIPOS DE FACTURA PARA FACTURAS NORMALES (SOLO F1 Y F2) ======
                        console.log("Verifactu: Aplicando filtros para factura normal");
                        
                        function filtrarTiposFacturaNormal() {
                            var selectTipoFactura = $("select[name*=\'options_fk_facture_type\']");
                            
                            if (selectTipoFactura.length > 0) {
                                console.log("Verifactu: Filtrando opciones de tipo de factura para factura normal (solo F1 y F2)");
                                
                                // Debug: listar todas las opciones disponibles
                                console.log("Verifactu: === DEBUG: Todas las opciones disponibles ===");
                                selectTipoFactura.find("option").each(function(index) {
                                    var optText = $(this).text().trim();
                                    var optValue = $(this).val();
                                    console.log("Verifactu: Opción", index + ":", optText, "Value:", optValue);
                                });
                                console.log("Verifactu: === FIN DEBUG ===");
                                
                                // Limpiar selección actual si no es válida para factura normal
                                var currentValue = selectTipoFactura.val();
                                var currentText = selectTipoFactura.find("option:selected").text().trim();
                                
                                console.log("Verifactu: Valor actual seleccionado:", currentValue, "->", currentText);
                                
                                if (currentValue && currentValue !== "" && currentValue !== "0" && 
                                    !currentText.match(/^F[12]\s/)) {
                                    console.log("Verifactu: ⚠️ Limpiando selección no válida para factura normal:", currentText);
                                    selectTipoFactura.val("").trigger("change");
                                }
                                
                                selectTipoFactura.find("option").each(function() {
                                    var optionText = $(this).text().trim();
                                    var optionValue = $(this).val();
                                    
                                    // Mantener solo opciones que empiecen con "F1" o "F2" (facturas normales)
                                    // y la opción vacía (para permitir selección)
                                    if (optionValue === "" || optionValue === "0") {
                                        // Mantener opción vacía
                                        return true;
                                    }
                                    
                                    // Verificar si el código empieza con "F1" o "F2"
                                    var isValidNormal = false;
                                    var regexMatchF1 = optionText.match(/^F1 - /);
                                    var regexMatchF2 = optionText.match(/^F2 - /);
                                    if (regexMatchF1 || regexMatchF2) {
                                        isValidNormal = true;
                                    }
                                    
                                    console.log("Verifactu: Evaluando opción:", optionText, "-> F1 match:", regexMatchF1, "-> F2 match:", regexMatchF2, "-> Es válida:", isValidNormal);
                                    
                                    if (!isValidNormal) {
                                        console.log("Verifactu: ❌ Ocultando opción no válida para factura normal:", optionText);
                                        $(this).prop("disabled", true).hide();
                                    } else {
                                        console.log("Verifactu: ✅ Manteniendo opción válida para factura normal:", optionText);
                                        $(this).prop("disabled", false).show();
                                    }
                                });
                                
                                // Agregar mensaje explicativo
                                if (selectTipoFactura.parent().find(".verifactu-normal-info").length === 0) {
                                    selectTipoFactura.after(
                                        \'<div class="verifactu-normal-info" style="background:#e7f3ff; border:1px solid #bee5eb; padding:6px; margin:5px 0; border-radius:3px; font-size:11px; color:#0c5460;">\' +
                                        \'<i class="fa fa-info-circle"></i> <strong>Factura normal:</strong> Solo se muestran los tipos de factura estándar (F1, F2).\' +
                                        \'</div>\'
                                    );
                                }
                            }
                        }
                        
                        // Aplicar filtro inmediatamente y con retraso
                        setTimeout(filtrarTiposFacturaNormal, 100);
                        setTimeout(filtrarTiposFacturaNormal, 500);
                        setTimeout(filtrarTiposFacturaNormal, 1000);
                        
                        // Asegurar que el filtro se mantenga
                        setTimeout(filtrarTiposFacturaNormal, 1500);
                        setTimeout(filtrarTiposFacturaNormal, 2000);
                    }

                    // ====== APLICAR VALORES POR DEFECTO PARA LÍNEAS DE FACTURA ======
                    console.log("Verifactu: Inicializando aplicación de valores por defecto para líneas");
                    
                    function aplicarValoresPorDefectoLineas() {
                        console.log("Verifactu: Ejecutando aplicación de valores por defecto para líneas");
                        
                        // Régimen: buscar opción con código "01"
                        $("select[name*=\'options_fk_clave_regimen\']").each(function() {
                            var currentValue = $(this).val();
                            console.log("Verifactu: Régimen actual:", currentValue);
                            
                            if (!currentValue || currentValue === "" || currentValue === "0") {
                                console.log("Verifactu: Régimen vacío, buscando opción 01");
                                var found = false;
                                $(this).find("option").each(function() {
                                    var optionText = $(this).text().trim();
                                    var optionValue = $(this).val();
                                    console.log("Verifactu: Evaluando opción régimen:", optionText, "Value:", optionValue);
                                    
                                    if (optionText.startsWith("01:") || 
                                        optionText.startsWith("01 -") || 
                                        optionText.indexOf("01:") === 0) {
                                        $(this).parent().val(optionValue).trigger("change");
                                        console.log("Verifactu: ✓ Seleccionado régimen por defecto:", optionText);
                                        found = true;
                                        return false; // Break
                                    }
                                });
                                if (!found) {
                                    console.log("Verifactu: ⚠ No se encontró opción de régimen con código 01");
                                }
                            } else {
                                console.log("Verifactu: Régimen ya tiene valor, no se modifica");
                            }
                        });
                        
                        // Operación: buscar opción con código "S1"  
                        $("select[name*=\'options_fk_clave_operacion\']").each(function() {
                            var currentValue = $(this).val();
                            console.log("Verifactu: Operación actual:", currentValue);
                            
                            if (!currentValue || currentValue === "" || currentValue === "0") {
                                console.log("Verifactu: Operación vacía, buscando opción S1");
                                var found = false;
                                $(this).find("option").each(function() {
                                    var optionText = $(this).text().trim();
                                    var optionValue = $(this).val();
                                    console.log("Verifactu: Evaluando opción operación:", optionText, "Value:", optionValue);
                                    
                                    if (optionText.startsWith("S1:") || 
                                        optionText.startsWith("S1 -") || 
                                        optionText.indexOf("S1:") === 0) {
                                        $(this).parent().val(optionValue).trigger("change");
                                        console.log("Verifactu: ✓ Seleccionada operación por defecto:", optionText);
                                        found = true;
                                        return false; // Break
                                    }
                                });
                                if (!found) {
                                    console.log("Verifactu: ⚠ No se encontró opción de operación con código S1");
                                }
                            } else {
                                console.log("Verifactu: Operación ya tiene valor, no se modifica");
                            }
                        });
                    }
                    
                    // Aplicar valores por defecto en diferentes momentos
                    setTimeout(aplicarValoresPorDefectoLineas, 500);
                    setTimeout(aplicarValoresPorDefectoLineas, 1000);
                    setTimeout(aplicarValoresPorDefectoLineas, 2000);
                    
                    // Cuando se hace click en agregar línea
                    $(document).on("click", "input[name=\'addline\']", function() {
                        console.log("Verifactu: Detectado click en agregar línea");
                        setTimeout(aplicarValoresPorDefectoLineas, 500);
                        setTimeout(aplicarValoresPorDefectoLineas, 1000);
                        setTimeout(aplicarValoresPorDefectoLineas, 2000);
                    });
                    
                    // Cuando se hace click en editar línea
                    $(document).on("click", "a[href*=\'action=editline\'], .editfielda", function() {
                        console.log("Verifactu: Detectado click en editar línea");
                        setTimeout(aplicarValoresPorDefectoLineas, 500);
                        setTimeout(aplicarValoresPorDefectoLineas, 1000);
                    });
                    
                    // Observer para detectar cuando aparecen nuevos selects
                    if (window.MutationObserver) {
                        var observer = new MutationObserver(function(mutations) {
                            var hasNewSelects = false;
                            mutations.forEach(function(mutation) {
                                if (mutation.addedNodes.length) {
                                    for (var i = 0; i < mutation.addedNodes.length; i++) {
                                        var node = mutation.addedNodes[i];
                                        if (node.nodeType === 1) { // Element node
                                            if (node.tagName === "SELECT" && 
                                                node.name && 
                                                node.name.indexOf("options_fk_clave_") !== -1) {
                                                hasNewSelects = true;
                                                break;
                                            }
                                            // Buscar selects dentro del nodo
                                            var selects = node.querySelectorAll ? 
                                                         node.querySelectorAll("select[name*=\'options_fk_clave_\']") : [];
                                            if (selects.length > 0) {
                                                hasNewSelects = true;
                                                break;
                                            }
                                        }
                                    }
                                }
                            });
                            
                            if (hasNewSelects) {
                                console.log("Verifactu: Detectados nuevos selects de líneas, aplicando valores por defecto");
                                setTimeout(aplicarValoresPorDefectoLineas, 100);
                                setTimeout(aplicarValoresPorDefectoLineas, 500);
                            }
                        });
                        
                        observer.observe(document.body, {
                            childList: true,
                            subtree: true
                        });
                    }

                    //Verificar si la  no está en estado borrador y mostrar alerta
                    if (isExistingInvoice && ' . ($object->status > 0 ? 'true' : 'false') . ') {
                        //alert("Verifactu: La factura no está en estado borrador, se deshabilitan botones de modificar y eliminar");

                        // DESHABILITAR EFECTIVAMENTE LOS BOTONES DE MODIFICAR Y ELIMINAR
                        setTimeout(function() {
                            // Buscar y deshabilitar botones de modificar y eliminar con múltiples selectores
                            var buttonSelectors = [
                                // \'a[href*="action=edit"]\',
                                // \'a[href*="action=delete"]\',
                                // \'a.butAction[href*="edit"]\',
                                // \'a.butActionDelete[href*="delete"]\',
                                // \'input[name="edit"]\',
                                // \'input[name="delete"]\',
                                // \'input[value*="Modificar"]\',
                                // \'input[value*="Eliminar"]\',
                                \'.butAction\',
                                \'.butActionDelete\'
                            ];

                            var hiddenCount = 0;
                            buttonSelectors.forEach(function(selector) {
                            console.log(selector);
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
                        // Validación para facturas correctivas
                        if (isFacturaCorrectiva) {
                            var tipoFactura = $("select[name*=\'options_fk_facture_type\']").val();
                            var tipoTexto = $("select[name*=\'options_fk_facture_type\'] option:selected").text().trim();
                            
                            console.log("Verifactu: Validando envío - Tipo seleccionado:", tipoFactura, "->", tipoTexto);
                            
                            if (!tipoFactura || tipoFactura === "" || tipoFactura === "0") {
                                alert("Debe seleccionar un tipo de factura rectificativa (R1-R5) para facturas correctivas.");
                                e.preventDefault();
                                return false;
                            }
                            
                            if (tipoTexto && !tipoTexto.match(/^R\d+ - /)) {
                                alert("Para facturas correctivas solo se permiten tipos rectificativos que empiecen con R (R1, R2, R3, R4, R5).");
                                e.preventDefault();
                                return false;
                            }
                        }
                        
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
                && !empty($object->id) && $object->status > 0
            ) {

                // PINTA el botón tú mismo (no devuelvas array)
                // print '<div class="inline-block divButAction">'
                // 	. '<a id="verifactu-xml-btn" class="butAction" target="_blank" '
                // 	. 'href="' . dol_buildpath('/custom/verifactu/xml_preview.php?id=' . $object->id, 1) . '">'
                // 	. '<i class="fa fa-code"></i> ' . $langs->trans("VerXMLVerifactu") . '</a>'
                // 	. '</div>';


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

        if ($parameters['currentcontext'] === 'contactcard' || $parameters['currentcontext'] === 'contact') {
            $langs->load("verifactu@verifactu");

            $confirm = GETPOST('confirm', 'alpha');
            $isDelete = false;
            if ($action === 'confirm_delete' && $confirm === 'yes') {
                $isDelete = true;
            } elseif ($action === 'delete') {
                $isDelete = true;
            }

            if ($isDelete && !empty($object->id)) {
                if ($this->contactHasInvoiceLinks($object->id)) {
                    setEventMessages($langs->trans('VerifactuErrorContactHasInvoices'), null, 'errors');
                    dol_syslog('Verifactu: Bloqueada eliminación de contacto id=' . $object->id . ' por tener facturas asociadas');
                    $action = '';
                    $_POST['action'] = '';
                    $_GET['action'] = '';
                    return -1;
                }
            }
        }

        // Validación de terceros/clientes
        if ($parameters['currentcontext'] === 'thirdpartycard') {
            $langs->load("verifactu@verifactu");

            dol_syslog("Verifactu: doActions EJECUTADO para tercero - Contexto: " . ($parameters['currentcontext'] ?? 'N/A') . ", Acción: " . $action . ", Elemento: " . ($object->element ?? 'N/A'));

            // Validar cuando se crea un cliente (solo cuando se envía el formulario)
            if (($action == 'add' || $action == 'create' || $action == 'update') && $_SERVER['REQUEST_METHOD'] == 'POST') {
                $errors = array();

                // Validar dirección obligatoria
                $address = trim($_POST['address'] ?? '');
                if (empty($address)) {
                    $errors[] = "La dirección es obligatoria para clientes según normativa Verifactu";
                }

                // Validar CIF/NIF solo si el país es España
                $country = $_POST['country_id'] ?? $_POST['country'] ?? '';
                $cif = trim($_POST['idprof1'] ?? '');


                dol_syslog("Verifactu: País seleccionado: $country, CIF: $cif");

                if ($country == '4'  || strtoupper($country) == 'ES' || strtoupper($country) == 'ESPAÑA') {
                    if (empty($cif)) {
                        $errors[] = "El CIF/NIF es obligatorio para clientes españoles según normativa Verifactu";
                    } elseif (!$this->validarCIFNIFNIEDNI($cif)) {
                        $errors[] = "El CIF/NIF proporcionado no es válido";
                    }
                } else {
                    if (empty($_POST['typent_id']))
                        $errors[] = "Tipo de tercero es obligatorio para clientes no españoles";
                    if ($_POST['typent_id'] != 8) // Si es tipo 5 (NIF extranjero) el CIF/NIF es obligatorio
                        $errors[] = "Tipo de tercero debe ser 'Particular' para emitir facturas con IVA al prestarse el servicio en España";
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
                    // foreach ($errors as $error) {
                    //     setEventMessages($error, null, 'errors');
                    // }
                    dol_syslog("Verifactu: Validación de cliente fallida en doActions: " . implode(", ", $errors));

                    // Cambiar la acción para volver al formulario sin redirección
                    if ($action == 'create' || $action == 'add') {
                        $action = 'create';
                        $_POST['action'] = 'create';
                        $_GET['action'] = 'create';
                    } elseif ($action == 'update' || $action == 'edit') {
                        $action = 'edit';
                        $_POST['action'] = 'edit';
                        $_GET['action'] = 'edit';
                    }
                    $this->errors = array_merge($this->errors, $errors);
                    return -1; // Retornar error para bloquear el guardado
                }

                // Bloquear cambios en CIF/NIF o país si existen facturas emitidas
                if ($action == 'update' && !empty($object->id) && $this->thirdpartyHasIssuedInvoices($object->id)) {
                    $originalThirdparty = new Societe($this->db);
                    $originalThirdparty->fetch($object->id);

                    $immutableErrors = array();
                    $originalVat = dol_strtoupper(trim($originalThirdparty->idprof1));
                    $newVat = dol_strtoupper(trim($_POST['idprof1'] ?? ''));
                    if ($newVat !== $originalVat) {
                        $immutableErrors[] = $langs->trans('VerifactuErrorImmutableVat');
                        $_POST['idprof1'] = $originalThirdparty->idprof1;
                    }

                    $originalCountryId = (int) $originalThirdparty->country_id;
                    $newCountryRaw = $_POST['country_id'] ?? $_POST['country'] ?? '';
                    if ($newCountryRaw === '') {
                        $newCountryRaw = $originalCountryId;
                    }
                    $newCountryId = is_numeric($newCountryRaw) ? (int) $newCountryRaw : $originalCountryId;

                    if ($originalCountryId && $newCountryId !== $originalCountryId) {
                        $immutableErrors[] = $langs->trans('VerifactuErrorImmutableCountry');
                        $_POST['country_id'] = $originalCountryId;
                        $_POST['country'] = $originalCountryId;
                    }

                    if (!empty($immutableErrors)) {
                        foreach ($immutableErrors as $msg) {
                            setEventMessages($msg, null, 'errors');
                        }
                        $action = 'edit';
                        return -1;
                    }
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

                $hasIssuedInvoices = (!empty($object->id)) ? $this->thirdpartyHasIssuedInvoices($object->id) : false;
                $vatLockMessage = dol_escape_js($langs->trans('VerifactuErrorImmutableVat'));
                $countryLockMessage = dol_escape_js($langs->trans('VerifactuErrorImmutableCountry'));

                $this->resprints .= '
                <script type="text/javascript">
                $(document).ready(function() {
                    console.log("Verifactu: JavaScript de validación de clientes cargado");
                    console.log("Verifactu: Contexto actual:", "' . ($parameters['currentcontext'] ?? 'N/A') . '");
                    console.log("Verifactu: Elemento del objeto:", "' . ($object->element ?? 'N/A') . '");

                    var hasIssuedInvoices = ' . ($hasIssuedInvoices ? 'true' : 'false') . ';
                    var vatLockMessage = "' . $vatLockMessage . '";
                    var countryLockMessage = "' . $countryLockMessage . '";

                    if (hasIssuedInvoices) {
                        var vatField = $("input[name=\"idprof1\"]");
                        if (vatField.length && !vatField.data("verifactuLocked")) {
                            vatField.prop("readonly", true).addClass("verifactu-field-locked");
                            vatField.data("verifactuLocked", true);
                            if (vatField.next(".verifactu-lock-msg").length === 0) {
                                vatField.after("<div class=\"verifactu-lock-msg verifactu-note\">" + vatLockMessage + "</div>");
                            }
                        }

                        var countryFields = $("select[name=\"country_id\"], select[name=\"country\"]");
                        countryFields.each(function() {
                            var $select = $(this);
                            if ($select.data("verifactuLocked")) {
                                return;
                            }
                            var currentValue = $select.val();
                            if (currentValue !== undefined) {
                                $("<input type=\"hidden\" class=\"verifactu-hidden-locked\">").attr("name", this.name).val(currentValue).insertAfter($select);
                            }
                            $select.prop("disabled", true).addClass("verifactu-field-locked").data("verifactuLocked", true);
                            if ($select.nextAll(".verifactu-lock-msg-country").length === 0) {
                                $select.after("<div class=\"verifactu-lock-msg-country verifactu-note\">" + countryLockMessage + "</div>");
                            }
                        });
                    }

                    var formStorage = (function() {
                        try {
                            var storage = window.sessionStorage;
                            var testKey = "verifactu_form_test";
                            storage.setItem(testKey, "1");
                            storage.removeItem(testKey);
                            return storage;
                        } catch (err) {
                            console.warn("Verifactu: sessionStorage no disponible", err);
                            return null;
                        }
                    })();
                    var storageKey = "verifactu_thirdparty_form_snapshot";

                    function clearFormSnapshot() {
                        if (formStorage) {
                            formStorage.removeItem(storageKey);
                        }
                    }

                    function saveFormSnapshot($form) {
                        if (!formStorage || !$form || !$form.length) {
                            return;
                        }

                        var snapshot = {};
                        $form.find("input, select, textarea").each(function() {
                            var $field = $(this);
                            var name = $field.attr("name");

                            if (!name) {
                                return;
                            }

                            if ($field.attr("type") === "password" || name === "token" || name === "action" || name === "id" || name === "mode") {
                                return;
                            }

                            if ($field.is(":disabled") && !$field.hasClass("verifactu-hidden-locked")) {
                                return;
                            }

                            if ($field.attr("type") === "checkbox") {
                                snapshot[name] = $field.is(":checked");
                            } else if ($field.attr("type") === "radio") {
                                if ($field.is(":checked")) {
                                    snapshot[name] = $field.val();
                                } else if (!(name in snapshot)) {
                                    snapshot[name] = null;
                                }
                            } else {
                                snapshot[name] = $field.val();
                            }
                        });

                        try {
                            formStorage.setItem(storageKey, JSON.stringify(snapshot));
                            console.log("Verifactu: Snapshot de formulario guardado");
                        } catch (err) {
                            console.warn("Verifactu: No se pudo guardar snapshot", err);
                        }
                    }

                    function restoreFormSnapshot($form) {
                        if (!formStorage || !$form || !$form.length) {
                            return;
                        }

                        var rawData = formStorage.getItem(storageKey);
                        if (!rawData) {
                            return;
                        }

                        var snapshot;
                        try {
                            snapshot = JSON.parse(rawData);
                        } catch (err) {
                            console.warn("Verifactu: No se pudo parsear snapshot", err);
                            clearFormSnapshot();
                            return;
                        }

                        $.each(snapshot, function(name, value) {
                            if (typeof name === "undefined" || name === null) {
                                return;
                            }

                            var $fields = $form.find("[name]").filter(function() {
                                return this.name === name;
                            });

                            if (!$fields.length) {
                                return;
                            }

                            $fields.each(function() {
                                var $field = $(this);
                                var type = ($field.attr("type") || "").toLowerCase();

                                if (type === "checkbox") {
                                    $field.prop("checked", !!value);
                                } else if (type === "radio") {
                                    if (value === null) {
                                        $field.prop("checked", false);
                                    } else {
                                        $field.prop("checked", $field.val() == value);
                                    }
                                } else {
                                    $field.val(value);
                                }
                            });

                            $fields.filter("select").each(function() {
                                $(this).trigger("change");
                            });
                        });

                        console.log("Verifactu: Snapshot de formulario restaurado", snapshot);
                    }

                    var $forms = $("form[name=\"add\"], form[name=\"update\"]");
                    if (formStorage && $forms.length) {
                        var hasServerErrors = $(".error, .errorBox, .errorMsg, .ui-state-error").filter(":visible").length > 0;
                        if (hasServerErrors) {
                            restoreFormSnapshot($forms.first());
                        } else {
                            clearFormSnapshot();
                        }
                    }

                    // Interceptar envío del formulario de cliente/tercero
                    $forms.on("submit", function(e) {
                        console.log("Verifactu: Interceptando envío de formulario de cliente");

                        var errors = [];

                        // Validar dirección obligatoria
                        var address = $("input[name=\"address\"]").val() || "";
                        if (!address.trim()) {
                            errors.push("La dirección es obligatoria para clientes según normativa Verifactu");
                        }

                        // Validar CIF/NIF solo si el país es España
                        var country = $("select[name=\"country_id\"]").val() || $("select[name=\"country\"]").val() || "";
                        var cif = $("input[name=\"idprof1\"]").val() || "";

                        console.log("Verifactu: País seleccionado:", country, "CIF:", cif);

                        if (country == "1" || country == "75" || country.toUpperCase() == "ES" || country.toUpperCase() == "ESPAÑA") {
                            if (!cif.trim()) {
                                errors.push("El CIF/NIF es obligatorio para clientes españoles según normativa Verifactu");
                            } else {
                                // Validar formato CIF básico
                                var cifRegex = /^[ABCDEFGHJNPQRSUVW]{1}\d{7}[0-9A-J]$/i;
                                if (!cifRegex.test(cif.toUpperCase())) {
                                    errors.push("El CIF/NIF proporcionado no tiene un formato válido");
                                }
                            }
                        }

                        // Validar código postal obligatorio
                        var zip = $("input[name=\"zipcode\"], input[name=\"zip\"]").val() || "";
                        if (!zip.trim()) {
                            errors.push("El código postal es obligatorio para clientes según normativa Verifactu");
                        }

                        // Validar población obligatoria
                        var town = $("input[name=\"town\"]").val() || "";
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

                        saveFormSnapshot($(this));

                        console.log("Verifactu: Validación de cliente exitosa, permitiendo envío");
                        return true;
                    });
                });
                </script>';
            }
        }

        return 0;
    }

    function formEditValueBeforeUpdate($parameters, &$object, &$action, $hookmanager)
    {
        global $user;

        // Solo nos interesa si es una factura
        if ($object->element == 'facture') {

            $fieldname = $parameters['fieldname'] ?? '';
            $newvalue = $parameters['value'] ?? '';
            $oldvalue = $object->$fieldname ?? null;

            if ($oldvalue != $newvalue) {
                $cambio = [
                    'campo' => $fieldname,
                    'antes' => $oldvalue,
                    'despues' => $newvalue,
                ];

                $json = json_encode($cambio, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

                dol_syslog("Verifactu: Cambio detectado por edición inline en factura {$object->ref}: " . $json);

                // Registrar el cambio en el log/auditoría
                $object->log($user->id, 'MODIFICACION_INLINE_JSON', $json);
            }

            var_dump('detectado cambio en campo ' . $fieldname . ': ' . $oldvalue . ' -> ' . $newvalue);
            die();
        }

        return 0;
    }

    /**
     * Comprueba si el tercero tiene facturas emitidas (validadas o más)
     *
     * @param int $thirdpartyId
     * @return bool
     */
    private function thirdpartyHasIssuedInvoices($thirdpartyId)
    {
        global $conf;

        if (empty($thirdpartyId)) {
            return false;
        }

        $sql = "SELECT COUNT(f.rowid) AS nb FROM " . $this->db->prefix() . "facture AS f
            WHERE f.fk_soc = " . ((int) $thirdpartyId) . "
            AND f.fk_statut > 0
            AND f.entity = " . ((int) $conf->entity);
        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog("Verifactu: Error comprobando facturas emitidas para tercero id=" . $thirdpartyId . " - " . $this->db->lasterror(), LOG_ERR);
            return false;
        }

        $obj = $this->db->fetch_object($resql);
        $this->db->free($resql);

        return (!empty($obj) && (int) $obj->nb > 0);
    }

    /**
     * Comprueba si un contacto está asociado a alguna factura
     *
     * @param int $contactId
     * @return bool
     */
    private function contactHasInvoiceLinks($contactId)
    {
        global $conf;

        if (empty($contactId)) {
            return false;
        }

        $sql = "SELECT COUNT(ec.rowid) AS nb
                FROM " . $this->db->prefix() . "element_contact AS ec
                INNER JOIN " . $this->db->prefix() . "facture AS f ON f.rowid = ec.fk_element
                WHERE ec.fk_contact = " . ((int) $contactId) . "
                  AND ec.element IN ('facture','invoice')
                  AND ec.entity = " . ((int) $conf->entity) . "
                  AND f.entity = " . ((int) $conf->entity) . "
                  AND f.fk_statut >= 0";

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog("Verifactu: Error comprobando facturas asociadas a contacto id=" . $contactId . " - " . $this->db->lasterror(), LOG_ERR);
            return false;
        }

        $obj = $this->db->fetch_object($resql);
        $this->db->free($resql);

        return (!empty($obj) && (int) $obj->nb > 0);
    }


    function beforeApiCall($parameters, &$object, &$action, $hookmanager)
    {
        if (!empty($_SERVER['REQUEST_URI']) && str_contains($_SERVER['REQUEST_URI'], '/invoices/') && $_SERVER['REQUEST_METHOD'] === 'PUT') {

            // Aquí podrías comprobar si la factura ya está verificada o enviada a la AEAT
            // (Ejemplo: $object es la factura)
            http_response_code(403);
            echo json_encode([
                'error' => 'Factura bloqueada por VeriFactu. No se permite modificar una factura ya enviada a AEAT.'
            ]);
            exit;
        }

        return 0;
    }
}
