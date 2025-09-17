/**
 * JavaScript para Verifactu - Bloquear fecha de factura
 * Según normativa Verifactu, la fecha de factura debe ser la fecha actual
 */

$(document).ready(function() {
    // Solo aplicar en páginas de facturas
    if (window.location.pathname.includes('facture') || window.location.pathname.includes('invoice')) {
        
        // Función para configurar fecha actual y bloquear campo
        function setupVerifactuDateField() {
            var dateField = $('input[name="remonth"], input[name="reday"], input[name="reyear"]');
            var dateFieldAlt = $('input[name="datef"]'); // Campo alternativo de fecha
            
            // Si encontramos campos de fecha
            if (dateField.length > 0 || dateFieldAlt.length > 0) {
                
                // Establecer fecha actual
                var today = new Date();
                var day = today.getDate();
                var month = today.getMonth() + 1; // JavaScript cuenta meses desde 0
                var year = today.getFullYear();
                
                // Configurar campos individuales (día, mes, año)
                $('select[name="remonth"]').val(month).prop('disabled', true);
                $('select[name="reday"]').val(day).prop('disabled', true);
                $('select[name="reyear"]').val(year).prop('disabled', true);
                
                // Configurar campo de fecha alternativo
                if (dateFieldAlt.length > 0) {
                    var todayStr = year + '-' + (month < 10 ? '0' : '') + month + '-' + (day < 10 ? '0' : '') + day;
                    dateFieldAlt.val(todayStr).prop('readonly', true);
                }
                
                // Agregar estilo visual para indicar que está bloqueado
                dateField.css({
                    'background-color': '#f5f5f5',
                    'color': '#666'
                });
                
                dateFieldAlt.css({
                    'background-color': '#f5f5f5',
                    'color': '#666'
                });
                
                // Agregar mensaje informativo
                var helpText = '<div class="verifactu-date-help" style="font-size: 12px; color: #666; margin-top: 5px;">' +
                    '<i class="fa fa-info-circle"></i> ' +
                    'Según normativa Verifactu, la fecha de factura debe ser la fecha actual y no puede modificarse.' +
                    '</div>';
                
                // Insertar mensaje después del campo de fecha
                if (!$('.verifactu-date-help').length) {
                    if (dateField.length > 0) {
                        dateField.last().parent().after(helpText);
                    } else if (dateFieldAlt.length > 0) {
                        dateFieldAlt.parent().after(helpText);
                    }
                }
            }
        }
        
        // Ejecutar al cargar la página
        setupVerifactuDateField();
        
        // También ejecutar cuando se recargue contenido dinámico
        $(document).ajaxComplete(function() {
            setupVerifactuDateField();
        });
        
        // Prevenir cambios por JavaScript externo
        setInterval(function() {
            $('select[name="remonth"], select[name="reday"], select[name="reyear"]').prop('disabled', true);
            $('input[name="datef"]').prop('readonly', true);
        }, 1000);
    }
});

// Función para mostrar alerta si intentan cambiar la fecha
function verifactuDateAlert() {
    alert('La fecha de factura no puede modificarse según la normativa Verifactu. Debe ser la fecha actual.');
    return false;
}