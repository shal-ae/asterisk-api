<?php

//if ($keep_one_answered_for_uniqueid) {
//    foreach ($groupedArray as &$group) {
//        $byDst = [];
//
//        // группируем по dst_ext
//        foreach ($group['items'] as $item) {
//            $dst = $item['dst_ext'] ?? $item['dstchannel'] ?? 'unknown';
//            if (!isset($byDst[$dst])) {
//                $byDst[$dst] = [];
//            }
//            $byDst[$dst][] = $item;
//        }
//
//        $filteredItems = [];
//
//        foreach ($byDst as $dst => $records) {
//            $hasAnswered = false;
//            foreach ($records as $r) {
//                if (($r['disposition'] ?? '') === 'ANSWERED') {
//                    $hasAnswered = true;
//                    break;
//                }
//            }
//
//            if ($hasAnswered) {
//                foreach ($records as $r) {
//                    if (($r['disposition'] ?? '') === 'ANSWERED') {
//                        $filteredItems[] = $r;
//                    }
//                }
//            } else {
//                $filteredItems = array_merge($filteredItems, $records);
//            }
//        }
//
//        $group['items'] = $filteredItems;
//    }
//    unset($group);
//}
//
//
//if ($keep_one_answered_for_uniqueid) {
//    // Строим массив отвеченных uniqueid
//    $answeredIds = [];
//    $answered = [];
//    foreach ($groupedArray as &$group) {
//        foreach ($group['items'] as $item) {
//            $uid = $item['uniqueid'];
//            if ((($item['disposition'] ?? '') === 'ANSWERED') && !isset($answered[$uid])) {
//                $answeredIds[] = $uid;
//                $answered[$uid] = $item;
//            }
//        }
//    }
//
//    $alreadyIncluded = [];
//    foreach ($groupedArray as &$group) {
//        $filteredItems = [];
//        foreach ($group['items'] as $item) {
//            $uid = $item['uniqueid'];
//            $isAnswered = ($item['disposition'] ?? '') === 'ANSWERED';
//
//            if (isset($answered[$uid])) {
//                if ($isAnswered && !isset($alreadyIncluded[$uid])) {
//                    $filteredItems[] = $item;
//                    $alreadyIncluded[$uid] = true;
//                }
//                // иначе — пропускаем все другие записи с этим uniqueid
//            } else {
//                // если нет ни одного ANSWERED — оставляем всё
//                $filteredItems[] = $item;
//            }
//
////            if (isset($answered[$uid]) && (($item['disposition'] ?? '') !== 'ANSWERED')) {
////                continue;
////            } else {
////                $filteredItems[] = $item;
////            }
//        }
//        $group['items'] = $filteredItems;
//    }
//    unset($group); // good practice with references
//}
//
//if ($keep_one_answered_for_uniqueid && false) {
//
//
//    foreach ($groupedArray as &$group) {
//        $byUniqueid = [];
//        foreach ($group['items'] as $item) {
//            $uid = $item['uniqueid'];
//            if (!isset($byUniqueid[$uid])) {
//                $byUniqueid[$uid] = [];
//            }
//            $byUniqueid[$uid][] = $item;
//        }
//
//        $filteredItems = [];
//        foreach ($byUniqueid as $uid => $records) {
//            // Сортируем, чтобы приоритет был у disposition = ANSWERED, потом по billsec, потом по calldate
//            usort($records, function ($a, $b) {
//                $dispA = ($a['disposition'] === 'ANSWERED') ? 0 : 1;
//                $dispB = ($b['disposition'] === 'ANSWERED') ? 0 : 1;
//                if ($dispA !== $dispB) return $dispA - $dispB;
//
//                $bsA = (int)($a['billsec'] ?? 0);
//                $bsB = (int)($b['billsec'] ?? 0);
//                if ($bsA !== $bsB) return $bsB - $bsA;
//
//                return strcmp($b['calldate'], $a['calldate']);
//            });
//
//            $filteredItems[] = $records[0]; // только одна лучшая запись
//        }
//
//        $group['items'] = $filteredItems;
//    }
//    unset($group);
//}
