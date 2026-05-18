<?php

return [
    'enabled' => true,

    /*
     * Customer emails only. User replies and notes are never changed.
     */
    'update_conversation_preview' => true,

    /*
     * Remove Outlook/Gmail-style quoted headers from the matched line to the end.
     */
    'strip_reply_headers' => true,

    /*
     * Remove legal footers from the matched line to the end.
     */
    'strip_disclaimers' => true,

    /*
     * Lines that usually start a human sign-off block.
     */
    'signature_starters' => [
        'с уважением',
        'с наилучшими пожеланиями',
        'best regards',
        'kind regards',
        'regards',
        'sincerely',
    ],

    /*
     * Organization-specific markers. Extend this list when a new noisy footer
     * appears in incoming tickets.
     */
    'corporate_markers' => [
        'agrana',
        'agrana.com',
        'agrana fruit',
        'shift masters',
        'festivalnaya',
        'privacy principles',
        'limited liability company',
        'огрн',
        'logo',
        'fashion banner',
    ],
];
