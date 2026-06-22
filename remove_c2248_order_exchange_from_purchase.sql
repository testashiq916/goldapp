-- Remove C2248 / sahira order exchange from Purchase entry/register only.
--
-- This keeps:
--   orderm/orderd for OR/00056
--   daybook/daybookpart ledger posting for OR/00056
--   cashbook unchanged
--
-- This removes only:
--   purchasem.slno = 1160, docno = OR/00056, pr = E
--   purchased rows for slno = 1160
--
-- Run:
--   mysql -uroot -D demo < remove_c2248_order_exchange_from_purchase.sql

START TRANSACTION;

CREATE TABLE IF NOT EXISTS zbak_c2248_purchasem_1160 AS
SELECT * FROM purchasem WHERE 1 = 0;

CREATE TABLE IF NOT EXISTS zbak_c2248_purchased_1160 AS
SELECT * FROM purchased WHERE 1 = 0;

CREATE TABLE IF NOT EXISTS zbak_c2248_purchased_dmddet_1160 AS
SELECT * FROM purchased_dmddet WHERE 1 = 0;

INSERT INTO zbak_c2248_purchasem_1160
SELECT *
FROM purchasem
WHERE slno = 1160
  AND suppcode = 'C2248'
  AND docno = 'OR/00056'
  AND billno = 'OR/00056'
  AND pr = 'E';

INSERT INTO zbak_c2248_purchased_1160
SELECT d.*
FROM purchased d
JOIN purchasem m ON m.slno = d.slno
WHERE m.slno = 1160
  AND m.suppcode = 'C2248'
  AND m.docno = 'OR/00056'
  AND m.billno = 'OR/00056'
  AND m.pr = 'E';

INSERT INTO zbak_c2248_purchased_dmddet_1160
SELECT dd.*
FROM purchased_dmddet dd
JOIN purchasem m ON m.slno = dd.slno
WHERE m.slno = 1160
  AND m.suppcode = 'C2248'
  AND m.docno = 'OR/00056'
  AND m.billno = 'OR/00056'
  AND m.pr = 'E';

DELETE dd
FROM purchased_dmddet dd
JOIN purchasem m ON m.slno = dd.slno
WHERE m.slno = 1160
  AND m.suppcode = 'C2248'
  AND m.docno = 'OR/00056'
  AND m.billno = 'OR/00056'
  AND m.pr = 'E';

DELETE d
FROM purchased d
JOIN purchasem m ON m.slno = d.slno
WHERE m.slno = 1160
  AND m.suppcode = 'C2248'
  AND m.docno = 'OR/00056'
  AND m.billno = 'OR/00056'
  AND m.pr = 'E';

DELETE FROM purchasem
WHERE slno = 1160
  AND suppcode = 'C2248'
  AND docno = 'OR/00056'
  AND billno = 'OR/00056'
  AND pr = 'E';

COMMIT;

-- Verify purchase entry is removed:
-- SELECT slno,tdate,billno,docno,suppcode,name,billamt,pamt,status,pr
-- FROM purchasem
-- WHERE slno = 1160 OR suppcode = 'C2248'
-- ORDER BY tdate,slno;
--
-- Verify order/ledger still exists:
-- SELECT slno,ordno,custcode,custname,billamt,eamt,status FROM orderm WHERE slno = 1160;
-- SELECT * FROM daybook WHERE slno = 1160;
-- SELECT * FROM daybookpart WHERE slno = 1160;
