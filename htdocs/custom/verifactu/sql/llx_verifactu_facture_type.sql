-- Creación de la tabla verifactu_facturae_type
CREATE TABLE IF NOT EXISTS llx_verifactu_facture_type (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(255) NOT NULL,
    active TINYINT DEFAULT 1
) ENGINE=innodb;

INSERT INTO llx_verifactu_facture_type (code, label, active) VALUES
('F1', 'Factura completa', 1),
('F2', 'Factura simplificada', 1),
('F3', 'F3 - Factura emitida en sustitución de facturas simplificadas', 1),
('F4', 'Factura rectificativa', 1),
('R1', 'Factura Rectificativa (Error fundado en derecho y en la forma)',1),
('R2', 'Factura Rectificativa (Error en la determinación de la base imponible)',1),
('R3', 'Factura Rectificativa (Error en la aplicación del tipo impositivo o de la cuota tributaria)',1),
('R4', 'Factura Rectificativa (Error en la identificación del destinatario o del emisor)',1),
('R5', 'Factura Rectificativa (Error en la descripción de las operaciones)',1),
('R6', 'Factura Rectificativa (Error en la fecha de expedición o en el número de factura)',1),
('R7', 'Factura Rectificativa (Deterioro o pérdida total del bien objeto de la operación)',1);

