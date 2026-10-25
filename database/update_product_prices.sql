-- Select your existing Aliman database in phpMyAdmin, then import this file.
-- Estimated project retail prices, PHP, reviewed 2026-10-23.
-- Only matching original prices are updated; edited prices and deleted products are skipped.
-- Corrected original products are made visible. Stock and existing orders are untouched.
START TRANSACTION;
UPDATE products SET price_cents=149500, active=1, version=version+1, updated_at=NOW() WHERE sku='AM-0061' AND name='Reward' AND price_cents=100 AND deleted=0;
UPDATE products SET price_cents=499500, active=1, version=version+1, updated_at=NOW() WHERE sku='AM-0062' AND name='Riemann' AND price_cents=900 AND deleted=0;
UPDATE products SET price_cents=129500, active=1, version=version+1, updated_at=NOW() WHERE sku='AM-0063' AND name='Prolate' AND price_cents=100 AND deleted=0;
UPDATE products SET price_cents=349500, active=1, version=version+1, updated_at=NOW() WHERE sku='AM-0064' AND name='Upbear' AND price_cents=400 AND deleted=0;
UPDATE products SET price_cents=79500, active=1, version=version+1, updated_at=NOW() WHERE sku='AM-0065' AND name='Restore' AND price_cents=100 AND deleted=0;
UPDATE products SET price_cents=189500, active=1, version=version+1, updated_at=NOW() WHERE sku='AM-0066' AND name='Resistor' AND price_cents=200 AND deleted=0;
UPDATE products SET price_cents=179500, active=1, version=version+1, updated_at=NOW() WHERE sku='AM-0067' AND name='Qualify' AND price_cents=200 AND deleted=0;
UPDATE products SET price_cents=499500, active=1, version=version+1, updated_at=NOW() WHERE sku='AM-0069' AND name='Rheostat' AND price_cents=600 AND deleted=0;
UPDATE products SET price_cents=99500, active=1, version=version+1, updated_at=NOW() WHERE sku='AM-0070' AND name='Skew' AND price_cents=200 AND deleted=0;
UPDATE products SET price_cents=169500, active=1, version=version+1, updated_at=NOW() WHERE sku='AM-0071' AND name='Rollick' AND price_cents=200 AND deleted=0;
UPDATE products SET price_cents=24900, active=1, version=version+1, updated_at=NOW() WHERE sku='AM-0073' AND name='Neon' AND price_cents=100 AND deleted=0;
UPDATE products SET price_cents=149500, active=1, version=version+1, updated_at=NOW() WHERE sku='AM-0074' AND name='Utilize' AND price_cents=200 AND deleted=0;
COMMIT;
