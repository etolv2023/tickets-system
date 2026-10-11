<?php

namespace App\Support;

/**
 * The strings resources/js shows to people, keyed by their Arabic source text.
 *
 * Shipped inline by the layouts so JS can translate without a request and
 * without carrying all of lang/en.json on every page. A string added in JS has
 * to be listed here too, or it stays Arabic in the English UI.
 */
final class JsTranslations
{
    /** @var array<int, string> */
    public const KEYS = [
        'نظام التذاكر',
        'الفلاتر',
        'الإشعارات مفعّلة',
        'الإشعارات مرفوضة من المتصفح',
        'متصفحك مش بيدعم الإشعارات',
        'فعّل إشعارات المتصفح',
        'هتوصلك حتى لو التاب مقفول والمتصفح شغال في الخلفية.',
        'اسمح بالإشعارات لـ«نظام التذاكر» من إعدادات الموقع في متصفحك.',
        'عشان يوصلك إشعار وانت شغّال على حاجة تانية.',
        '«:name» أكبر من :size ميجا.',
        'بايت',
        'كيلوبايت',
        'ميجا',
        'الصور بتتضاف من المرفقات — مفيش مرفقات في الصفحة دي.',
        ':count صور اتحطوا هنا وكمان في المرفقات.',
        'الصورة اتحطت هنا وكمان في المرفقات.',
        'الصورة مادخلتش — شوف رسالة المرفقات تحت.',
        'المتصفح رفض ملء الشاشة. جرّب F11.',
        'الترتيب الجديد ماتسجّلش. حدّث الصفحة وجرّب تاني.',
        'مقدرناش نغيّر الموعد. رجّعناه لمكانه؛ جرّب تاني.',
        'نسيبها كده؟',
        'الحفظ فشل.',
        'النقلة اترفضت.',
    ];

    /** @return array<string, string> empty in the Arabic UI: the keys are the Arabic text */
    public static function current(): array
    {
        if (app()->isLocale('ar')) {
            return [];
        }

        $out = [];

        foreach (self::KEYS as $key) {
            $out[$key] = __($key);
        }

        return $out;
    }
}
