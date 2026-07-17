<?php

/**
 * Server-side HTML sanitising for anything that comes out of the editor.
 * F04.1: the whitelist below is the whole contract — script, iframe, object,
 * on* handlers and style are all absent, so HTMLPurifier drops them.
 *
 * Used in phase 2 for ticket descriptions and comments. Never sanitise in JS.
 *
 * @link http://htmlpurifier.org/live/configdoc/plain.html
 */
return [
    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,
    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,

    'settings' => [
        'default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',

            // The F04.1 whitelist, verbatim. Nothing else survives.
            'HTML.Allowed' => 'p,br,strong,em,u,ul,ol,li,a[href|title|target],'
                . 'code,pre,h3,h4,blockquote,img[src|alt|width|height],'
                . 'table,thead,tbody,tr,td[colspan|rowspan],th[colspan|rowspan]',

            // No style attribute is allowed through, so no CSS property is either.
            'CSS.AllowedProperties' => '',

            // Blocks javascript: and data: URIs in href and src.
            'URI.AllowedSchemes' => [
                'http' => true,
                'https' => true,
                'mailto' => true,
            ],

            // Outbound links can't reach back into our window.
            'HTML.TargetBlank' => true,
            'HTML.TargetNoopener' => true,
            'HTML.TargetNoreferrer' => true,
            'Attr.AllowedFrameTargets' => ['_blank'],

            // The editor already emits paragraphs; re-wrapping mangles pasted markup.
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => true,

            // Keeps ids out of user content so they can't collide with the app's.
            'Attr.EnableID' => false,
        ],
    ],
];
