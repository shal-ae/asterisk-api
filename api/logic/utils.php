<?php


function getTrunkNameFromChannel($channel)
{
    if (preg_match('/^(SIP|PJSIP|DAHDI|IAX2)\\/([\\w\\-\\.]+?)-\\w+$/', $channel, $m)) {
        $name = $m[2];
        if (!preg_match('/^\\d{2,6}$/', $name)) {
            return $name;
        }
    }
    return null;
}

function extractExtension($channel)
{
    if (preg_match('/(?:SIP|PJSIP|Local)\/(\d+)(?:\-|\/)/', $channel, $m)) {
        return $m[1];
    }
    return null;
}

function linkedArrayByMap(array $linkedIdMap): array
{
    $res = [];  // Убираем ключи для вывода в 1С
    foreach ($linkedIdMap as $id1 => $id2) {
        $res[] = [
            'id1' => $id1,
            'id2' => $id2
        ];
    }
    return $res;
}

// === Совместимость с PHP 7 ==
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        return substr($haystack, -strlen($needle)) === $needle;
    }
}
