-- Documented legacy view structure. Run only after creating the corresponding tables.
-- Omit historical DEFINER account from portable examples.
CREATE OR REPLACE VIEW extensions_vw AS
SELECT e.id, e.company_id, d.context, e.exten, e.priority, e.app, e.appdata
FROM extensions AS e
JOIN dialplan AS d ON d.id = e.context;
