<?php

require __DIR__.'/../Services/SignatureStripper.php';

use Modules\SignatureCutter\Services\SignatureStripper;

$stripper = new SignatureStripper(require __DIR__.'/../Config/config.php');

$cases = [
    [
        "Коллеги, контейнер в статусе Released.\n\nС уважением,\n\nМихаил.\n\nMikhail AUMEYSTERS | Shift Masters | Shift master\n\nAGRANA Fruit Moscow region LLC | Festivalnaya street, 5 | 142214 Serpukhov, Russia\nPhone: +7 (4967) 76-09-70 (Ext. 56824)\nMobile: +7 (985) 111-31-65\nE-Mail: Mikhail.AUMEYSTERS@agrana.com | https://www.agrana.com | Privacy Principles\n\nLogo\n\nDisclaimer: This message contains confidential information and is solely intended for the addressee(s).\n\nAgrana Fruit in Fashion Banner",
        "Коллеги, контейнер в статусе Released.",
    ],
    [
        "Ок, принято.\r\n\r\nС Уважением,\r\n\r\nMaksim ELTSOW | Line leader | T: +7 (4967) 76-09-70 (Ext. 56886) | M: +7 (985) 080-50-70",
        "Ок, принято.",
    ],
    [
        "Новый комментарий.\n\nFrom: AUMEYSTERS Mikhail <Mikhail.AUMEYSTERS@agrana.com>\nSent: Saturday, May 16, 2026 11:11 PM\nTo: ELTSOW Maksim <Maksim.ELTSOW@agrana.com>\nCc: RUSV_SHIFTMASTER <RUSV_SHIFTMASTER@agrana.com>\nSubject: Статус контейнера\n\nold text",
        "Новый комментарий.",
    ],
];

foreach ($cases as $index => $case) {
    $actual = $stripper->clean($case[0]);
    if ($actual !== $case[1]) {
        fwrite(STDERR, "Case ".($index + 1)." failed\nExpected:\n".$case[1]."\nActual:\n".$actual."\n");
        exit(1);
    }
}

echo "SignatureCutter smoke tests passed\n";
