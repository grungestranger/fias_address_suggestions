<?php

require_once realpath(__DIR__ . '/../config.php');

try {
	if (!isset($_GET['region'], $_GET['str'])) {
		throw new Exception('No data');
	}

	if (
		!is_string($region = $_GET['region'])
		|| !is_string($str = $_GET['str'])
	) {
		throw new Exception('Wrong data');
	}

	/*
	 * String
	 */

	$sourseStr = $str;

	$str = trim(mb_ereg_replace('\s+', ' ', $str));

	if (mb_strlen($str) < MIN_LENGTH) {
		throw new Exception('Trim string must be min length: ' . MIN_LENGTH);
	}

	$str = mb_strtolower($str);

	/*
	 * Region
	 */

	if (
		!mb_ereg_match('^\d{2}$', $region)
		|| intval($region) == 0
	) {
		throw new Exception('Wrong region');
	}

	/*
	 * Count
	 */

	if (!isset($_GET['count'])) {
		$limit = COUNT_HINTS;
	} elseif (($limit = intval($_GET['count'])) < 1) {
		$limit = 1;
	} elseif ($limit > MAX_COUNT_HINTS) {
		$limit = MAX_COUNT_HINTS;
	}

	/*
	 * DB connection.
	 */

	$db = new PDO(DBSTRING);
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, TRUE);

	/*
	 * Find region.
	 */

	$sql = <<<SQL
SELECT * FROM addrobj WHERE parentguid IS NULL AND regioncode = :region LIMIT 1;
SQL;
	$prepare = $db->prepare($sql);
	$prepare->bindValue(':region', $region);
	$prepare->execute();
	if (!$prepare->fetchAll(PDO::FETCH_ASSOC)) {
		throw new Exception('Wrong region');
	}

	/*
	 * Check the full address in the phrase.
	 * Cut off the part from the end of the spaces. Remove the commas at the end.
	 */

	$sql = <<<SQL
SELECT * FROM addrobj WHERE regioncode = :region AND lower(address) = :str LIMIT 1;
SQL;
	$prepare = $db->prepare($sql);
	$prepare->bindValue(':region', $region);
	$prepare->bindParam(':str', $part);

	$parent = NULL;
	$part = $str;
	while ($part) {
		if (mb_substr($part, -1) == ',') {
			$part = mb_substr($part, 0, mb_strlen($part) - 1);
		}
		$prepare->execute();
		if ($res = $prepare->fetchAll(PDO::FETCH_ASSOC)) {
			$parent = $res[0];
			break;
		}
		$part = mb_strrchr($part, ' ', TRUE);
	}

	/*
	 * If the full address is found.
	 */

	if ($parent) {
		$str = mb_substr($str, mb_strlen($parent['address']));
		if (mb_substr($str, 0, 1) == ',') {
			$str = mb_substr($str, 1);
		}
		$str = trim($str);

		/*
		 * If the object is final - search houses.
		 */

		if ($parent['final'] == 't') {		
			$result = NULL;

			/*
			 * Check for full compliance.
			 */

			if ($str != '') {
				$sql = <<<SQL
SELECT * FROM house WHERE aoguid = :aoguid AND lower(number) = :str LIMIT 1;
SQL;
				$prepare = $db->prepare($sql);
				$prepare->bindValue(':aoguid', $parent['aoguid']);
				$prepare->bindValue(':str', $str);
				$prepare->execute();
				if ($res = $prepare->fetchAll(PDO::FETCH_ASSOC)) {
					$finalStr = $parent['address'] . ', ' . $res[0]['number'];
					if ($sourseStr == $finalStr) {
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

			/*
			 * Suggestions for houses.
			 */

			if (!$result) {
				if ($str == '') {
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
				$prepare->bindValue(':aoguid', $parent['aoguid']);
				$prepare->bindValue(':limit', $limit, PDO::PARAM_INT);
				if ($str != '') {
					$prepare->bindValue(':str', $str);
				}
				$prepare->execute();
				if ($res = $prepare->fetchAll(PDO::FETCH_ASSOC)) {
					$result = [
						'final' => FALSE,
						'items' => [],
					];
					foreach ($res as $item) {
						$result['items'][] = [
							'address' => $parent['address'] . ', ' . $item['number'],
						];
					}

				/*
				 * If the final object has no houses.
				 */

				} else {
					if ($sourseStr == $parent['address']) {
						$result = [
							'final' => TRUE,
						];
					} else {
						$result = [
							'final' => FALSE,
							'items' => [
								[
									'address' => $parent['address'],
								],
							],
						];
					}
				}
			}

		/*
		 * Suggestions for addresses.
		 */

		} else {
			if ($str != '') {
				$sql = <<<SQL
SELECT * FROM (
	WITH RECURSIVE r AS (
		SELECT * FROM addrobj WHERE aoguid = :aoguid
		UNION ALL
		SELECT ao.* FROM addrobj ao
			JOIN r
				ON ao.parentguid = r.aoguid
					AND r.final = 'f'
	)
	SELECT * FROM r
) as tmp
WHERE aoguid != :aoguid
ORDER BY similarity(substring(address, :parAddrLen), :str) DESC LIMIT :limit;
SQL;
			} else {
				$sql = <<<SQL
SELECT * FROM addrobj WHERE parentguid = :aoguid
ORDER BY lower(formalname) LIMIT :limit;
SQL;
			}
			$prepare = $db->prepare($sql);
			$prepare->bindValue(':aoguid', $parent['aoguid']);
			$prepare->bindValue(':limit', $limit, PDO::PARAM_INT);
			if ($str != '') {
				$prepare->bindValue(':parAddrLen', mb_strlen($parent['address']) + 1, PDO::PARAM_INT);
				$prepare->bindValue(':str', $str);
			}
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

	/*
	 * If the full address is not found. Suggestions for addresses.
	 */

	} else {
		$trgmLimit = 0.5;
		$db->exec('SELECT set_limit(' . $trgmLimit . ');');
		$res = NULL;
		while (!$res) {
			$sql = <<<SQL
SELECT * FROM addrobj WHERE regioncode = :region AND address % :str
ORDER BY similarity(address, :str) DESC LIMIT :limit;
SQL;
			$prepare = $db->prepare($sql);
			$prepare->bindValue(':region', $region);
			$prepare->bindValue(':limit', $limit, PDO::PARAM_INT);
			$prepare->bindValue(':str', $str);
			$prepare->execute();
			if (!($res = $prepare->fetchAll(PDO::FETCH_ASSOC))) {
				$trgmLimit -= 0.1;
				$db->exec('SELECT set_limit(' . $trgmLimit . ');');
			}
		}

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
	
	$result['success'] = TRUE;

} catch (Exception $e) {
	$result = [
		'success' => FALSE,
		'error' => $e->getMessage(),
	];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);
