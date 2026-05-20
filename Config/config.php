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
    'strip_reply_headers' => false,

    /*
     * Remove legal footers from the matched line to the end.
     */
    'strip_disclaimers' => true,

    /*
     * Emails matching these patterns are service noise and should be removed
     * entirely instead of being shown as customer replies.
     */
    'drop_email_patterns' => [
        '/\breacted to your message\s*:/iu',
        '/your message to\s+rusv\.1c-support@agrana\.com\s+couldn(?:\'|\x{2019})t be delivered/iu',
        '/only accepts messages from people in its organization or on its allowed senders list,?\s+and your email address isn(?:\'|\x{2019})t on the list/iu',
    ],

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
        'logo',
        'fashion banner',
    ],
];
