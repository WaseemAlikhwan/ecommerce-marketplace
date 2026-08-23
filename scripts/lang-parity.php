<?php

$root = dirname(__DIR__);
$en = json_decode(file_get_contents($root.'/lang/en.json'), true);
$ar = json_decode(file_get_contents($root.'/lang/ar.json'), true);

if (! is_array($en) || ! is_array($ar)) {
    fwrite(STDERR, "Failed to decode lang JSON\n");
    exit(1);
}

$enKeys = array_keys($en);
$arKeys = array_keys($ar);
sort($enKeys);
sort($arKeys);

$onlyEn = array_values(array_diff($enKeys, $arKeys));
$onlyAr = array_values(array_diff($arKeys, $enKeys));

echo 'EN='.count($enKeys).' AR='.count($arKeys).PHP_EOL;
echo 'only_en='.count($onlyEn).' only_ar='.count($onlyAr).PHP_EOL;

if ($onlyEn !== [] || $onlyAr !== []) {
    echo json_encode(['only_en' => $onlyEn, 'only_ar' => $onlyAr], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
    exit(1);
}

echo "PARITY_OK\n";
