<?php

function fetchCelRowsByLinkedIds(PDO $pdo, array $linkedids): array
{
    if (empty($linkedids)) {
        return [];
    }

    // Подготовим плейсхолдеры
    $placeholders = [];
    $params = [];
    foreach ($linkedids as $i => $id) {
        $ph = ":id$i";
        $placeholders[] = $ph;
        $params[$ph] = $id;
    }

    $sql = "
        SELECT
            id, eventtime, eventtype, linkedid, uniqueid, channame, peer, cid_num, cid_name, context, exten, appname        FROM cel
        WHERE linkedid IN (" . implode(', ', $placeholders) . ")
        ORDER BY eventtime DESC
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $ph => $val) {
        $stmt->bindValue($ph, $val);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

