<?php

declare(strict_types=1);

namespace WEB27\SiteSearch;
defined('ABSPATH') || exit;

final class Language
{
    public static function restArg(): array
    {
        return ['type'=>'string','maxLength'=>32,'pattern'=>'^[a-z]{2,3}(?:_[A-Za-z0-9]{2,8}){0,3}$'];
    }

    public static function pageLocale(\WP_REST_Request $request): string
    {
        $forced=Settings::widgetLanguage();
        if ($forced!=='auto') { return $forced; }
        $requested=$request->get_param('locale');
        $site=get_locale();
        if (!is_string($requested) || $requested==='') { return $site; }
        if (!preg_match('/^[a-z]{2,3}(?:_[A-Za-z0-9]{2,8}){0,3}$/D',$requested)) { return 'en_US'; }
        $allowed=array_merge(['en_US','ru_RU',$site],get_available_languages());
        return in_array($requested,$allowed,true)?$requested:'en_US';
    }

    public static function withLocale(\WP_REST_Request $request, callable $callback)
    {
        return self::withInterfaceLocale(self::pageLocale($request),$callback);
    }

    public static function interfaceLocale(): string
    {
        $forced=Settings::widgetLanguage();
        return $forced==='auto'?get_locale():$forced;
    }

    public static function withInterfaceLocale(string $locale,callable $callback)
    {
        if ($locale===(function_exists('determine_locale')?determine_locale():get_locale())) { return $callback(); }
        if (switch_to_locale($locale)) {
            try { return $callback(); }
            finally { restore_previous_locale(); }
        }
        if (!in_array($locale,['en_US','ru_RU'],true)) { return $callback(); }
        $catalog=null;
        if ($locale==='ru_RU') {
            if (!class_exists('MO')) { require_once ABSPATH.WPINC.'/pomo/mo.php'; }
            $catalog=new \MO();
            if (!$catalog->import_from_file(__DIR__.'/languages/web27-site-search-ru_RU.mo')) { return $callback(); }
        }
        $translate=static fn($translated,$original)=>$catalog?$catalog->translate($original):$original;
        add_filter('gettext_web27-site-search',$translate,PHP_INT_MAX,2);
        try { return $callback(); }
        finally { remove_filter('gettext_web27-site-search',$translate,PHP_INT_MAX); }
    }

    public static function explicitLanguage(string $text): string
    {
        if (preg_match('/(?:\b(?:answer|reply|respond|write|speak)\s+(?:to me\s+)?in\s+(?:the\s+)?(?:russian|ru)\b|(?:ответь(?:те)?|пиши(?:те)?|говори(?:те)?)\s+по[- ]русски|на русском(?: языке)?\s*[.!?]*$|^по[- ]русски\s*[.!?]*$)/iu',$text)) { return 'ru'; }
        if (preg_match('/(?:\b(?:answer|reply|respond|write|speak)\s+(?:to me\s+)?in\s+(?:the\s+)?(?:english|en)\b|(?:ответь(?:те)?|пиши(?:те)?|говори(?:те)?)\s+по[- ]английски|на английском(?: языке)?\s*[.!?]*$|^по[- ]английски\s*[.!?]*$|^in english\s*[.!?]*$)/iu',$text)) { return 'en'; }
        return '';
    }

    private static function substantiveLanguage(string $text): string
    {
        $text=trim(preg_replace('~https?://\S+|\b[A-Z]+[-_]?\d+[A-Z0-9_-]*\b~u',' ',$text)??$text);
        if (preg_match('/^(?:yes|no|ok(?:ay)?|sure|да|нет|ок|ага|угу|хорошо|[\pN\s.,+\-]+)[.!?\s]*$/iu',$text)) { return ''; }
        if (preg_match('/^(?:hi|hello|hey|thanks|thank you)[.!?]*$/i',$text)) { return 'en'; }
        if (preg_match('/^[A-Z0-9_-]+$/D',$text)) { return ''; }
        $ru=preg_match_all('/\p{Cyrillic}/u',$text);
        $en=preg_match_all('/\p{Latin}/u',$text);
        if ($ru>0 && $en===0) { return 'ru'; }
        if ($en>0 && $ru===0) { return 'en'; }
        $ruCue=(bool)preg_match('/(?:^|[^\pL])(?:цена|стоимость|сколько|купить|заказать|где|когда|как|есть|найти|расскажите|покажи)(?:$|[^\pL])/iu',$text);
        $enCue=(bool)preg_match('/\b(?:price|cost|how|where|when|buy|order|find|show|tell|does|available)\b/i',$text);
        if ($ruCue!==$enCue) { return $ruCue?'ru':'en'; }
        if ($ru>=3 && $ru>$en*1.5) { return 'ru'; }
        if ($en>=3 && $en>$ru*1.5) { return 'en'; }
        return '';
    }

    public static function answerLanguage(string $question,array $history,string $locale): string
    {
        $language=self::explicitLanguage($question) ?: self::substantiveLanguage($question);
        if ($language!=='') { return $language; }
        foreach (array_reverse($history) as $turn) {
            if (($turn['role']??'')!=='user') { continue; }
            $text=(string)($turn['content']??'');
            $language=self::explicitLanguage($text) ?: self::substantiveLanguage($text);
            if ($language!=='') { return $language; }
        }
        return str_starts_with(strtolower($locale),'ru')?'ru':'en';
    }

    public static function isFollowup(string $question): bool
    {
        if (self::explicitLanguage($question)!=='' && preg_match('/^(?:please\s+)?(?:answer|reply|respond|write|speak|in English|in Russian|ответь|ответьте|пиши|пишите|говори|по[- ]|на английском|на русском)/iu',trim($question))) { return true; }
        return (bool)preg_match('/^(?:а\s|и сколько|подробнее\b|сколько\s*[?!.]*$|где\s*[?!.]*$|когда\s*[?!.]*$|что дальше\b|what about\b|and\s|how much\s*[?!.]*$|how long\s*[?!.]*$|where\s*[?!.]*$|when\s*[?!.]*$|tell me more\b|more details\b|what next\b|(?:yes|no|okay|ok|да|нет|ок|ага|угу)[\s.!?]*$)/iu',trim($question));
    }

     
    public static function phrase(string $language,string $english,string $russian): string
    {
        return $language==='ru'?$russian:$english;
    }
}
