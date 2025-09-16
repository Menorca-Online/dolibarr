ALTER TABLE llx_facture
ADD COLUMN fk_facture_type INTEGER NULL;

ALTER TABLE llx_facture
ADD CONSTRAINT fk_facture_facture_type FOREIGN KEY (fk_facture_type)
REFERENCES llx_verifactu_facture_type (rowid);