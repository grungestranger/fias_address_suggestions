
-- Table addrobj_tmp

DROP TABLE IF EXISTS addrobj_tmp;

CREATE TABLE addrobj_tmp (
    aoguid				UUID PRIMARY KEY,
    parentguid			UUID,
    plaincode           CHAR(15),
    level				SMALLINT,
    aolevel				SMALLINT,
    formalname			VARCHAR,
    address				VARCHAR,
    postalcode			CHAR(6),
    regioncode			CHAR(2),
    shortname			VARCHAR,
	final				BOOLEAN
);

CREATE INDEX addrobj_tmp_parentguid_idx
    ON addrobj_tmp
    USING BTREE (parentguid)
;

CREATE INDEX addrobj_tmp_regioncode_address_gist_idx
    ON addrobj_tmp
    USING GIST (regioncode, address gist_trgm_ops)
;

CREATE INDEX addrobj_tmp_regioncode_address_lower_idx
    ON addrobj_tmp
    USING BTREE (regioncode, lower(address))
;

--CREATE INDEX addrobj_tmp_plaincode_idx
--    ON addrobj_tmp
--    USING BTREE (plaincode)
--;

-- Table house_tmp

DROP TABLE IF EXISTS house_tmp;

CREATE TABLE house_tmp (
	houseguid	UUID PRIMARY KEY,
	aoguid		UUID,
	housenum	VARCHAR,
	eststatus	SMALLINT,
	buildnum	VARCHAR,
	strucnum	VARCHAR,
	strstatus	SMALLINT,
	number		VARCHAR,
	postalcode	CHAR(6)
);

--CREATE INDEX house_tmp_aoguid_number_gist_idx
--    ON house_tmp
--    USING GIST (aoguid, number gist_trgm_ops)
--;

CREATE INDEX house_tmp_aoguid_number_lower_idx
    ON house_tmp
    USING BTREE (aoguid, lower(number))
;
