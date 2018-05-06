
-- Table addrobj_tmp --

-- Set level and address

UPDATE addrobj_tmp ao
SET level = tmp.level,
	address = tmp.address
FROM (
	WITH RECURSIVE r (level, aoguid, address) AS (
		SELECT 0, aoguid, shortname || ' ' || formalname
		FROM addrobj_tmp
		WHERE parentguid IS NULL
		UNION ALL
		SELECT r.level + 1, ao1.aoguid, r.address || ', ' || shortname || ' ' || ao1.formalname
		FROM addrobj_tmp ao1
		INNER JOIN r
			ON r.aoguid = ao1.parentguid
	)
	SELECT * FROM r
) tmp
WHERE tmp.aoguid = ao.aoguid;

-- Set final

UPDATE addrobj_tmp ao1
SET final = NOT EXISTS(SELECT * FROM addrobj_tmp ao2 WHERE ao2.parentguid = ao1.aoguid LIMIT 1);

-- Table house_tmp --

-- Set number

UPDATE house_tmp
SET number = COALESCE(housenum, '') || COALESCE('к' || buildnum, '') || COALESCE('с' || strucnum, '');

-- Swop tables --

-- Table addrobj

DROP TABLE IF EXISTS addrobj;

ALTER TABLE addrobj_tmp RENAME TO addrobj;

ALTER INDEX addrobj_tmp_parentguid_formalname_lower_idx RENAME TO addrobj_parentguid_formalname_lower_idx;

ALTER INDEX addrobj_tmp_plaincode_idx RENAME TO addrobj_plaincode_idx;

-- Table house

DROP TABLE IF EXISTS house;

ALTER TABLE house_tmp RENAME TO house;

ALTER INDEX house_tmp_aoguid_number_lower_idx RENAME TO house_aoguid_number_lower_idx;
