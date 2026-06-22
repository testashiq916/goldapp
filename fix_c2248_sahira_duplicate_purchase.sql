-- Fix duplicate C2248 / sahira purchase entry.
-- Keeps the order exchange entry: purchasem.slno = 1160, docno = OR/00056, pr = E
-- Removes the duplicate purchase bill: purchasem.slno = 2213, docno = P/02203, pr = P
--
-- Review before running:
--   mysql -uroot -D demo < fix_c2248_sahira_duplicate_purchase.sql

START TRANSACTION;

CREATE TABLE IF NOT EXISTS zbak_c2248_purchasem_2213 AS
SELECT * FROM purchasem WHERE 1 = 0;

CREATE TABLE IF NOT EXISTS zbak_c2248_purchased_2213 AS
SELECT * FROM purchased WHERE 1 = 0;

CREATE TABLE IF NOT EXISTS zbak_c2248_daybook_2213 AS
SELECT * FROM daybook WHERE 1 = 0;

CREATE TABLE IF NOT EXISTS zbak_c2248_daybookpart_2213 AS
SELECT * FROM daybookpart WHERE 1 = 0;

CREATE TABLE IF NOT EXISTS zbak_c2248_delpart_2213 AS
SELECT * FROM delpart WHERE 1 = 0;

INSERT INTO zbak_c2248_purchasem_2213
SELECT * FROM purchasem
WHERE slno = 2213
  AND suppcode = 'C2248'
  AND docno = 'P/02203'
  AND billno = 'OR/00056'
  AND pr = 'P';

INSERT INTO zbak_c2248_purchased_2213
SELECT d.* FROM purchased d
JOIN purchasem m ON m.slno = d.slno
WHERE m.slno = 2213
  AND m.suppcode = 'C2248'
  AND m.docno = 'P/02203'
  AND m.billno = 'OR/00056'
  AND m.pr = 'P';

INSERT INTO zbak_c2248_daybook_2213
SELECT db.* FROM daybook db
JOIN purchasem m ON m.slno = db.slno
WHERE m.slno = 2213
  AND m.suppcode = 'C2248'
  AND m.docno = 'P/02203'
  AND m.billno = 'OR/00056'
  AND m.pr = 'P';

INSERT INTO zbak_c2248_daybookpart_2213
SELECT dp.* FROM daybookpart dp
JOIN purchasem m ON m.slno = dp.slno
WHERE m.slno = 2213
  AND m.suppcode = 'C2248'
  AND m.docno = 'P/02203'
  AND m.billno = 'OR/00056'
  AND m.pr = 'P';

INSERT INTO zbak_c2248_delpart_2213
SELECT dl.* FROM delpart dl
JOIN purchasem m ON m.slno = dl.slno
WHERE m.slno = 2213
  AND m.suppcode = 'C2248'
  AND m.docno = 'P/02203'
  AND m.billno = 'OR/00056'
  AND m.pr = 'P';

-- Reverse stock added by duplicate Purchase Bill P/02203.
-- In this entry qty is 0 and weight is 2.130, item OG.
UPDATE items i
JOIN purchased d ON d.code = i.code
JOIN purchasem m ON m.slno = d.slno
SET i.qty = i.qty - d.qty,
    i.weight = i.weight - d.weight
WHERE m.slno = 2213
  AND m.suppcode = 'C2248'
  AND m.docno = 'P/02203'
  AND m.billno = 'OR/00056'
  AND m.pr = 'P';

DELETE db FROM daybook db
JOIN purchasem m ON m.slno = db.slno
WHERE m.slno = 2213
  AND m.suppcode = 'C2248'
  AND m.docno = 'P/02203'
  AND m.billno = 'OR/00056'
  AND m.pr = 'P';

DELETE dp FROM daybookpart dp
JOIN purchasem m ON m.slno = dp.slno
WHERE m.slno = 2213
  AND m.suppcode = 'C2248'
  AND m.docno = 'P/02203'
  AND m.billno = 'OR/00056'
  AND m.pr = 'P';

DELETE dl FROM delpart dl
JOIN purchasem m ON m.slno = dl.slno
WHERE m.slno = 2213
  AND m.suppcode = 'C2248'
  AND m.docno = 'P/02203'
  AND m.billno = 'OR/00056'
  AND m.pr = 'P';

DELETE d FROM purchased d
JOIN purchasem m ON m.slno = d.slno
WHERE m.slno = 2213
  AND m.suppcode = 'C2248'
  AND m.docno = 'P/02203'
  AND m.billno = 'OR/00056'
  AND m.pr = 'P';

DELETE FROM purchasem
WHERE slno = 2213
  AND suppcode = 'C2248'
  AND docno = 'P/02203'
  AND billno = 'OR/00056'
  AND pr = 'P';

COMMIT;

-- Verification after running:
-- SELECT slno,tdate,ttime,billno,docno,suppcode,name,billamt,pamt,status,pr
-- FROM purchasem
-- WHERE suppcode = 'C2248' OR name LIKE '%sahira%'
-- ORDER BY tdate,ttime,slno;
--
-- SELECT code,name,qty,weight,qtyb,weightb FROM items WHERE code = 'OG';
