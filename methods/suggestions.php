<?php
/*

Искать внутри последнего точного совпадения - если есть - не обязательно в родительском или финальном.

*/

require_once realpath(__DIR__ . '/../config.php');

try {
	if (!isset($_GET['aoguid']) || !isset($_GET['str'])) {
		throw new Exception('No data');
	}

	$aoguid = $_GET['aoguid'];
	$str = $_GET['str'];
	if (!isset($_GET['count'])) {
		$limit = COUNT_HINTS;
	} elseif (($limit = intval($_GET['count'])) < 1) {
		$limit = 1;
	} elseif ($limit > MAX_COUNT_HINTS) {
		$limit = MAX_COUNT_HINTS;
	}
	
	if (!is_string($aoguid) || !is_string($str)) {
		throw new Exception('Wrong data');
	}

	$sourseStr = $str;

	// Заменяем все подряд идущие пробельные знаки на один пробел + trim
	$str = trim(mb_ereg_replace('\s+', ' ', $str));

	if (mb_strlen($str) < MIN_LENGTH) {
		throw new Exception('Trim string must be min length: ' . MIN_LENGTH);
	}
	
	// К нижнему регистру
	$str = mb_strtolower($str);

	/*
	 * DB connection.
	 */

	$db = new PDO(DBSTRING);
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, TRUE);

	/*
	 * Find parent object.
	 */

	$sql = <<<SQL
SELECT * FROM addrobj WHERE aoguid = :aoguid LIMIT 1;
SQL;
	$prepare = $db->prepare($sql);
	$prepare->bindValue(':aoguid', $aoguid);
	$prepare->execute();
	if (!($res = $prepare->fetchAll(PDO::FETCH_ASSOC))) {
		throw new Exception('Wrong parent fias aoguid');
	}
	$parentObject = $res[0];
	
	$parentAddress = mb_strtolower($parentObject['address']) . ', ';
	
	$fullStr = $parentObject['address'] . ', ' . $sourseStr;
	
	// Проверяем, содержит ли фраза полный адрес финального адресного объекта
	if ($parentObject['final'] == 't') {
		$finalObject = $parentObject;
		$houseStr = $str;
	} else {
		// Отсекаем части с конца по пробелам. Убираем зяпятые в конце.
		$sql = <<<SQL
SELECT * FROM (
	WITH RECURSIVE r AS (
		SELECT * FROM addrobj WHERE aoguid = :aoguid
		UNION ALL
		SELECT ao.* FROM addrobj ao
			JOIN r
				ON ao.parentguid = r.aoguid
	)
	SELECT * FROM r
) as tmp
WHERE lower(address) = :str
LIMIT 1;
SQL;
		$prepare = $db->prepare($sql);
		$prepare->bindValue(':aoguid', $aoguid);

		$finalObject = NULL;
		$houseStr = '';
		$part = $str;
		while ($part) {
			$prepare->bindValue(
				':str',
				$parentAddress . (
					mb_substr($part, -1) == ','
						? mb_substr($part, 0, mb_strlen($part) - 1) : $part
				)
			);
			$prepare->execute();
			if ($res = $prepare->fetchAll(PDO::FETCH_ASSOC)) {
				if ($res[0]['final'] == 't') {
					$finalObject = $res[0];
				} else {
					$aoguid = $res[0]['aoguid'];
					$parentAddress = mb_strtolower($res[0]['address']) . ', ';
					//$str = mb_substr($str, 0, mb_strlen($part) - 1);
				}
				break;
			}
			$houseStr = (mb_strrchr($part, ' ') ?: $part) . $houseStr;
			$part = mb_strrchr($part, ' ', TRUE);
		}
		if (mb_substr($houseStr, 0, 1) == ',') {
			$houseStr = mb_substr($houseStr, 1);
		}
		$houseStr = trim($houseStr);
	}

	// Если финальный объект - ищем дома
	if ($finalObject) {		
		$result = NULL;
		// Проверяем на полное соответствие
		if ($houseStr) {
			$sql = <<<SQL
SELECT * FROM house WHERE aoguid = :aoguid AND lower(number) = :str LIMIT 1;
SQL;
			$prepare = $db->prepare($sql);
			$prepare->bindValue(':aoguid', $finalObject['aoguid']);
			$prepare->bindValue(':str', $houseStr);
			$prepare->execute();
			if ($res = $prepare->fetchAll(PDO::FETCH_ASSOC)) {
				$finalStr = $finalObject['address'] . ', ' . $res[0]['number'];
				if ($fullStr == $finalStr) {
					$result = [
						'final' => TRUE,
					];
				} else {
					$result = [
						'final' => FALSE,
						'items' => [
							[
								'address' => $finalStr,
							],
						],
					];
				}
			}
		}

		if (!$result) {
			if (!$houseStr) {
				$sql = <<<SQL
SELECT * FROM house WHERE aoguid = :aoguid
	ORDER BY lower(number) LIMIT :limit;
SQL;
			} else {
				$sql = <<<SQL
SELECT * FROM house WHERE aoguid = :aoguid
	ORDER BY similarity(number, :str) DESC LIMIT :limit;
SQL;
			}
			$prepare = $db->prepare($sql);
			$prepare->bindValue(':aoguid', $finalObject['aoguid']);
			$prepare->bindValue(':limit', $limit, PDO::PARAM_INT);
			if ($houseStr) {
				$prepare->bindValue(':str', $houseStr);
			}
			$prepare->execute();
			if ($res = $prepare->fetchAll(PDO::FETCH_ASSOC)) {
				$result = [
					'final' => FALSE,
					'items' => [],
				];
				foreach ($res as $item) {
					$result['items'][] = [
						'address' => $finalObject['address'] . ', ' . $item['number'],
					];
				}
			// Если у финального объекта нет домов
			} else {
				// Если поисковая строка равна полному адресу финального объекта
				if ($fullStr == $finalObject['address']) {
					$result = [
						'final' => TRUE,
					];
				} else {
					$result = [
						'final' => FALSE,
						'items' => [
							[
								'address' => $finalObject['address'],
							],
						],
					];
				}
			}
		}
	// Иначе выводим подсказки по адресам
	} else {
		if ($str) {
			$sql = <<<SQL
SELECT * FROM (
	WITH RECURSIVE r AS (
		SELECT * FROM addrobj WHERE aoguid = :aoguid
		UNION ALL
		SELECT ao.* FROM addrobj ao
			JOIN r
				ON ao.parentguid = r.aoguid
	)
	SELECT * FROM r
) as tmp
WHERE aoguid != :aoguid
ORDER BY similarity(substring(address, :parAddrLen), :str) DESC LIMIT :limit;
SQL;
		} else {

		}
		$prepare = $db->prepare($sql);
		$prepare->bindValue(':aoguid', $aoguid);
		$prepare->bindValue(':parAddrLen', mb_strlen($parentAddress) + 1, PDO::PARAM_INT);
		$prepare->bindValue(':str', $str);
		$prepare->bindValue(':limit', $limit, PDO::PARAM_INT);
		$prepare->execute();
		$res = $prepare->fetchAll(PDO::FETCH_ASSOC);
		
		$result = [
			'final' => FALSE,
			'items' => [],
		];
		foreach ($res as $item) {
			$result['items'][] = [
				'address' => $item['address'],
			];
		}
	}
	
	// Обрезаем адрес родительского объекта у подсказок
	if (isset($result['items'])) {
		$parAddrLen = mb_strlen($parentObject['address']) + 2;
		foreach ($result['items'] as &$item) {
			$item['address'] = mb_substr($item['address'], $parAddrLen);
		}
		unset($item);
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
