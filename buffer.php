<?php

require_once __DIR__ . '/config.php';

try {

	/*
	 * DB connection.
	 */

	$db = new PDO(DBSTRING);
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	/*
	 * Level 0.
	 */

	$sql = <<<SQL
SELECT * FROM addrobj WHERE parentguid IS NULL;
SQL;
    foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
		$sql = <<<SQL
EXPLAIN ANALYZE SELECT * FROM addrobj WHERE regioncode = :region AND address % 'тест';
SQL;
		$prepare = $db->prepare($sql);
		$prepare->bindValue(':region', $row['regioncode']);
		$prepare->execute();
    }

	/*
	 * Level 1.
	 */

	$sql = <<<SQL
SELECT * FROM addrobj WHERE level = 1;
SQL;
    foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
		$sql = <<<SQL
EXPLAIN ANALYZE SELECT * FROM (
	WITH RECURSIVE r AS (
		SELECT * FROM addrobj WHERE aoguid = :aoguid
		UNION ALL
		SELECT ao.* FROM addrobj ao
			JOIN r
				ON ao.parentguid = r.aoguid
					AND r.final = 'f'
	)
	SELECT * FROM r
) as tmp;
SQL;
		$prepare = $db->prepare($sql);
		$prepare->bindValue(':aoguid', $row['aoguid']);
		$prepare->execute();
    }

} catch (Exception $e) {
	echo $e->getMessage();
}
