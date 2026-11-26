<?php
namespace WEB27\SiteSearch;
defined('ABSPATH') || exit;

final class Welcome {
    public static function intent(string $question): string {
        $q=trim(preg_replace('/[^\pL\pN]+/u',' ',mb_strtolower($question)));
        $q=str_replace(['увас','ё'],['у вас','е'],$q);
        if(preg_match('/^(?:привет|здравствуй(?:те)?|добрый (?:день|вечер)|доброе утро|hello|hi|hey|good morning|good afternoon|good evening)$/u',$q)){return 'hello';}
        if(preg_match('/^(?:спасибо|благодарю|thanks|thank you|many thanks)$/u',$q)){return 'thanks';}
        $q=preg_replace('/^(?:а |подскажите |расскажите |пожалуйста )+/u','',$q);
        if(preg_match('/^(?:какие (?:у вас |здесь )?(?:есть )?(?:игры|товары|услуги|объекты|разделы)(?: у вас| есть| на сайте)?|what (?:games|products|services|items) (?:do you have|are available)(?: here)?|show (?:me )?(?:the |your )?(?:catalog|catalogue))$/u',$q)){return 'overview';}
        return preg_match('/^(?:что (?:есть |имеется )?(?:у вас|тут|здесь|на сайте)(?: есть| на сайте| можно делать)?|что вы (?:продаете|предлагаете|умеете)|чем (?:вы занимаетесь|можете помочь)|что (?:это за сайт|можно здесь делать)|куда я попал[аи]?|о чем (?:этот )?сайт|во что (?:здесь |тут |у вас )?можно (?:по)?играть|какие (?:у вас |здесь )?(?:игры|услуги|товары|разделы)(?: есть| на сайте)?|what is this site about|what do you offer|what do you sell|what can you do|what is available here|how can you help|what can i find here)$/u',$q)?'overview':'';
    }
    public static function profile(array $types): array {
        $name=wp_strip_all_tags(get_bloginfo('name'));
        $summary=trim(wp_strip_all_tags(get_bloginfo('description')));
        if(preg_match('/^(?:just another wordpress site|ещ[её] один сайт на wordpress)/iu',$summary)){$summary='';}
        $sources=[];
        if($types){
            $pages=get_posts(['post_type'=>$types,'post_status'=>'publish','has_password'=>false,'numberposts'=>25,'orderby'=>'menu_order title','order'=>'ASC','suppress_filters'=>false]);
            foreach($pages as $page){
                if(!Knowledge::publicPost($page)||(int)$page->ID===(int)get_option('wp_page_for_privacy_policy')){continue;}
                $title=trim(wp_strip_all_tags($page->post_title));
                if(!Settings::sameSiteUrl((string)get_permalink($page)) || !$title || preg_match('/^(?:sample page|hello world!?|privacy policy|пример страницы|привет,? мир!?|политика конфиденциальности)$/iu',$title)){continue;}
                $sources[]=['title'=>$title,'url'=>get_permalink($page),'excerpt'=>$title,'score'=>0];
                if(count($sources)===3){break;}
            }
        }
        if(class_exists(Universal::class) && ($connected=Universal::overview((int)Settings::get()['rag_limit']))){
            $sources=array_slice(array_merge($connected,$sources),0,(int)Settings::get()['rag_limit']);
            $remaining=(int)Settings::get()['rag_context_chars'];$limited=[];
            foreach($sources as $hit){if($remaining<=0){break;}$hit['excerpt']=wp_html_excerpt($hit['excerpt'],$remaining,'');$remaining-=mb_strlen($hit['excerpt']);$limited[]=$hit;}
            $sources=$limited;
        }
        return [$name,$summary,$sources];
    }
}
