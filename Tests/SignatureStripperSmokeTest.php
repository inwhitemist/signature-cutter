<?php

require __DIR__.'/../Services/SignatureStripper.php';

use Modules\SignatureCutter\Services\SignatureStripper;

$stripper = new SignatureStripper(require __DIR__.'/../Config/config.php');

$cases = [
    [
        "Коллеги, контейнер в статусе Released.\n\nС уважением,\n\nМихаил.\n\nM | Shift Masters | Shift master\n\nAGRANA Fruit Moscow region LLC | Festivalnaya street, 5 | 142214 Serpukhov, Russia\nPhone: \nE-Mail:  | https://www.agrana.com | Privacy Principles\n\nLogo\n\nDisclaimer: This message contains confidential information and is solely intended for the addressee(s).\n\nAgrana Fruit in Fashion Banner",
        "Коллеги, контейнер в статусе Released.",
    ],
    [
        "Ок, принято.\r\n\r\nС Уважением,\r\n\r\n | Line leader |",
        "Ок, принято.",
    ],
    [
        "Новый комментарий.\n\nFrom: \nSent: Saturday, May 16, 2026 11:11 PM\nTo:\nCc: \nSubject: Статус контейнера\n\nold text",
        "Новый комментарий.",
    ],
    [
        "Ivan Petrov reacted to your message:\r\n\r\n\"Принято\"",
        "",
    ],
    [
        "<div>Your message to rusv.1c-support@agrana.com couldn't be delivered.</div><div>rusv.1c-support only accepts messages from people in its organization or on its allowed senders list, and your email address isn't on the list.</div>",
        "",
    ],
    // Новый тест: подпись с номерами телефонов должна быть обрезана, но контент после подписи (следующее письмо) должен быть сохранён
    [
        "Коллеги, привет\n\nБыла наша разовая доставка клиенту.\nЗадания на перевозку нет, реализация 0000-001637, стоит самовывоз.\n\nПросьба сделать задание.\n\n---\n\nVadim VORONIN | Senior Logistics Specialist | T: +7 (4967) 76-09-70 (Ext. 56771) | M: +7 (915) 190-21-68\n\nDisclaimer: This message contains confidential information\n\nFrom: MAKAROVA Elena\nSent: Thursday, April 30, 2026 4:31 PM\nTo: LEBEDEVA Elena\nSubject: Важное сообщение\n\nЭто важное сообщение из другого письма в цепочке.",
        "Коллеги, привет\n\nБыла наша разовая доставка клиенту.\nЗадания на перевозку нет, реализация 0000-001637, стоит самовывоз.\n\nПросьба сделать задание.",
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
