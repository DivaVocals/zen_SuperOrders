-- SUPER ORDERS PAYMENTS SUPPORT
-- Use this file to add payment types for additional languages. See readme for details on how to use this file
ALTER TABLE so_payment_types (
  DROP UNIQUE KEY type_code (payment_type_code),
  ADD UNIQUE KEY type_code (payment_type_code, language_id);
)

ALTER TABLE so_payment_types DROP INDEX type_code;
alter table so_payment_types add UNIQUE type_code (payment_type_code, language_id);


INSERT INTO so_payment_types VALUES (NULL, 2, 'CA', 'De trésorerie');
INSERT INTO so_payment_types VALUES (NULL, 2, 'CK', 'Chèque');
INSERT INTO so_payment_types VALUES (NULL, 2, 'MO', 'Mandat Postal');
INSERT INTO so_payment_types VALUES (NULL, 2, 'WU', 'Western Union');
INSERT INTO so_payment_types VALUES (NULL, 2, 'ADJ', 'Ajustement');
INSERT INTO so_payment_types VALUES (NULL, 2, 'REF', 'Remboursement');
INSERT INTO so_payment_types VALUES (NULL, 2, 'CC', 'Carte de crédit');
INSERT INTO so_payment_types VALUES (NULL, 2, 'MC', 'MasterCard');
INSERT INTO so_payment_types VALUES (NULL, 2, 'VISA', 'Visa');
INSERT INTO so_payment_types VALUES (NULL, 2, 'AMEX', 'American Express');
INSERT INTO so_payment_types VALUES (NULL, 2, 'DISC', 'Discover');
INSERT INTO so_payment_types VALUES (NULL, 2, 'DINE', 'Diners Club');
INSERT INTO so_payment_types VALUES (NULL, 2, 'SOLO', 'Solo');
INSERT INTO so_payment_types VALUES (NULL, 2, 'MAES', 'Maestro');
INSERT INTO so_payment_types VALUES (NULL, 2, 'JCB', 'JCB');