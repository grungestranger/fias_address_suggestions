-- Table addrobj_tmp

DROP TABLE IF EXISTS addrobj_tmp;

CREATE TABLE addrobj_tmp (
	plaincode			CHAR(15),
    aoguid				UUID,
    parentguid			UUID,
    level				SMALLINT,
    aolevel				SMALLINT,
    formalname			VARCHAR,
    address				VARCHAR,
    postalcode			CHAR(6),
    regioncode			CHAR(2),
    shortname			VARCHAR,
	final				BOOLEAN
);

CREATE UNIQUE INDEX addrobj_tmp_aoguid_uq_idx
    ON addrobj_tmp
    USING BTREE (aoguid)
;

CREATE INDEX addrobj_tmp_parentguid_idx
    ON addrobj_tmp
    USING BTREE (parentguid)
;

CREATE INDEX addrobj_tmp_plaincode_idx
    ON addrobj_tmp
    USING BTREE (plaincode)
;

CREATE INDEX addrobj_tmp_address_lower_idx
    ON addrobj_tmp
    USING BTREE (lower(address))
;

-- Table house_tmp

DROP TABLE IF EXISTS house_tmp;

CREATE TABLE house_tmp (
	houseguid	UUID,
	aoguid		UUID,
	housenum	VARCHAR,
	eststatus	SMALLINT,
	buildnum	VARCHAR,
	strucnum	VARCHAR,
	strstatus	SMALLINT,
	number		VARCHAR,
	postalcode	CHAR(6)
);

CREATE UNIQUE INDEX house_tmp_houseguid_uq_idx
    ON house_tmp
    USING BTREE (houseguid)
;

CREATE INDEX house_tmp_aoguid_idx
    ON house_tmp
    USING BTREE (aoguid)
;

CREATE INDEX house_tmp_number_lower_idx
    ON house_tmp
    USING BTREE (lower(number))
;
