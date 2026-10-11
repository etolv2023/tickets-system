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
        'عوامل التصفية',
        'الإشعارات مفعّلة',
        'الإشعارات محظورة في المتصفح',
        'متصفحك لا يدعم الإشعارات',
        'فعّل إشعارات المتصفح',
        'ستصلك حتى لو كانت علامة التبويب مغلقة والمتصفح يعمل في الخلفية.',
        'اسمح بالإشعارات لموقع «نظام التذاكر» من إعدادات الموقع في متصفحك.',
        'لتصلك الإشعارات أثناء عملك على شيء آخر.',
        'حجم «:name» أكبر من :size ميغابايت.',
        'بايت',
        'كيلوبايت',
        'ميغابايت',
        'تُضاف الصور من خلال المرفقات — لا توجد مرفقات في هذه الصفحة.',
        'أُدرجت :count صور هنا وأُضيفت إلى المرفقات.',
        'أُدرجت الصورة هنا وأُضيفت إلى المرفقات.',
        'لم تُدرج الصورة — راجع رسالة المرفقات أدناه.',
        'رفض المتصفح وضع ملء الشاشة. جرّب F11.',
        'لم يُحفظ الترتيب الجديد. حدّث الصفحة وحاول مرة أخرى.',
        'تعذّر تغيير الموعد، وأُعيد إلى مكانه. حاول مرة أخرى.',
        'هل تريد الإبقاء عليها؟',
        'تعذّر الحفظ.',
        'تم رفض النقل.',
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
