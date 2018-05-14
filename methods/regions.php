<?php

require_once realpath(__DIR__ . '/../config.php');

try {
	if (!isset($_GET['str'])) {
		$str = NULL;
	} else {
		if (!is_string($str = $_GET['str'])) {
			throw new Exception('Wrong data');
		}

		/*
		 * String
		 */

		$sourseStr = $str;

		// Заменяем все подряд идущие пробельные знаки на один пробел + trim
		$str = trim(mb_ereg_replace('\s+', ' ', $str));

		if (mb_strlen($str) < MIN_LENGTH) {
			throw new Exception('Trim string must be min length: ' . MIN_LENGTH);
		}
		
		// К нижнему регистру
		$str = mb_strtolower($str);
	}

	/*
	 * DB connection.
	 */

	$db = new PDO(DBSTRING);
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if ($str) {
		$result = NULL;

		/*
		 * Full
		 */

		$sql = <<<SQL
SELECT * FROM addrobj WHERE parentguid IS NULL
	AND lower(shortname) || ', ' || lower(formalname) = :str LIMIT 1;
SQL;
		$prepare = $db->prepare($sql);
		$prepare->bindValue(':str', $str);
		$prepare->execute();
		if ($res = $prepare->fetchAll(PDO::FETCH_ASSOC)) {
			$fullName = $res[0]['shortname'] . ', ' . $res[0]['formalname'];
			if ($sourseStr == $fullName) {
				$result = [
					'final' => TRUE,
					'code' => $res[0]['code'],
					'name' => $fullName,
				];
			} else {
				$result = [
					'final' => FALSE,
					'items' => [
						[
							'code' => $res[0]['code'],
							'name' => $fullName,
						],
					],
				];
			}
		}

		if (!$result) {
			$sql = <<<SQL
SELECT * FROM addrobj WHERE parentguid IS NULL
	ORDER BY similarity(shortname || ', ' || formalname, :str) LIMIT 10;
SQL;
			$prepare = $db->prepare($sql);
			$prepare->bindValue(':str', $str);
			$prepare->execute();
			$res = $prepare->fetchAll(PDO::FETCH_ASSOC);
			
			$result = [
				'final' => FALSE,
				'items' => [],
			];
			foreach ($res as $item) {
				$result['items'][] = [
					'code' => $item['code'],
					'name' => $item['shortname'] . ', ' . $item['formalname'],
				];
			}
		}
	} else {
		$sql = <<<SQL
SELECT * FROM addrobj WHERE parentguid IS NULL ORDER BY regioncode;
SQL;
		$prepare = $db->prepare($sql);
		$prepare->execute();
		$res = $prepare->fetchAll(PDO::FETCH_ASSOC);
		
		$result = [
			'items' => [],
		];
		foreach ($res as $item) {
			$result['items'][] = [
				'code' => $item['code'],
				'name' => $item['shortname'] . ', ' . $item['formalname'],
			];
		}
	}

	$result['success'] = TRUE;

} catch (Exception $e) {
	$result = [
		'success' => FALSE,
		'error' => $e->getMessage(),
	];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);
