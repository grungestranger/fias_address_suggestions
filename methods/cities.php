<?php

require_once realpath(__DIR__ . '/../config.php');

try {
	if (!isset($_GET['aoguid'])) {
		$aoguid = NULL;
	} else {
		$aoguid = $_GET['aoguid'];
		if (!is_string($aoguid)) {
			throw new Exception('Wrong data');
		}
	}

	/*
	 * DB connection.
	 */

	$db = new PDO(DBSTRING);
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	/*
	 * Find parent object.
	 */

	if ($aoguid !== NULL) {
		$sql = <<<SQL
SELECT * FROM addrobj WHERE aoguid = :aoguid AND aolevel < 6 LIMIT 1;
SQL;
		$prepare = $db->prepare($sql);
		$prepare->bindValue(':aoguid', $aoguid);
		$prepare->execute();
		if (!($res = $prepare->fetchAll(PDO::FETCH_ASSOC))) {
			throw new Exception('Wrong parent fias aoguid');
		}
		$parentObject = $res[0];
	}

	/*
	 * Select objects.
	 */

	if ($aoguid === NULL) {
		$sql = <<<SQL
SELECT * FROM addrobj WHERE parentguid IS NULL ORDER BY regioncode;
SQL;
	} else {
		$sql = <<<SQL
SELECT * FROM addrobj WHERE parentguid = :aoguid ORDER BY lower(formalname);
SQL;
	}
	$prepare = $db->prepare($sql);
	if ($aoguid) {
		$prepare->bindValue(':aoguid', $aoguid);
	}
	$prepare->execute();
	$res = $prepare->fetchAll(PDO::FETCH_ASSOC);

	$result = [
		'success' => TRUE,
		'items' => [],
	];
	foreach ($res as $item) {
		$result['items'][] = [
			'address' => $item['shortname'] . ' ' . $item['formalname'],
			'aoguid' => $item['aoguid'],
		];
	}

} catch (Exception $e) {
	$result = [
		'success' => FALSE,
		'error' => $e->getMessage(),
	];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);
