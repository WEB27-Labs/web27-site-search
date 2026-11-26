<?php

declare(strict_types=1);

namespace WEB27\SiteSearch;
defined('ABSPATH') || exit;

final class Settings
{
    public const OPTION = 'web27_site_search_settings';
    public const APPEARANCE = 'web27_site_search_appearance';

    public static function isPostRequest(): bool
    {
        return isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD']) && sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))==='POST';
    }

    public static function privacyPolicy(): void
    {
        if (!function_exists('wp_add_privacy_policy_content')) { return; }
        $text=esc_html__('Site Search processes search queries locally. If dialogue memory is enabled, a one-day session cookie and temporary dialogue records are used. Optional logs retain queries and answer excerpts for the configured period. A hashed IP-based temporary counter limits requests. If external AI or semantic search is enabled, visitor queries and relevant public content are transmitted to the selected provider; AI answers may also use recent dialogue and administrator instructions. The site administrator must identify the provider, its privacy policy, actual retention settings and any applicable consent requirements before enabling external processing.', 'web27-site-search');
        wp_add_privacy_policy_content('WEB27 Site Search','<p>'.$text.'</p>');
    }

    public static function appearance(): string
    {
        $saved = get_option(self::APPEARANCE, false);
        if (is_string($saved) && in_array($saved, ['light','dark','contrast','system','custom'], true)) { return $saved; }
        return get_option(self::OPTION, false) !== false || get_option('w27ss_schema', false) !== false || get_option('w27studio_db_version', false) !== false ? 'dark' : 'system';
    }

    public static function initAppearance(): void
    {
        add_option(self::APPEARANCE, self::appearance(), '', false);
    }

    public static function saveAppearance($value): void
    {
        if (is_string($value) && in_array($value, ['light','dark','contrast','system','custom'], true)) {
            update_option(self::APPEARANCE, $value, false);
        }
    }

    public const DESIGN = 'web27_site_search_design';

    public static function design($input=null): array {
        $d=['placement'=>'bottom-right','mobile'=>'inherit','shape'=>'pill','content'=>'both','visibility'=>'auto','window'=>'modal','cards'=>'grid','width'=>760,'height'=>820,'offset_x'=>16,'offset_y'=>16,'radius'=>20,'accent'=>'#6044d8','background'=>'#0b1728','surface'=>'#14263c','text'=>'#f3f7fc'];
        $input=$input??get_option(self::DESIGN,[]);$input=is_array($input)?$input:[];
        $choices=['placement'=>['bottom-right','bottom-left','top-right','top-left'],'mobile'=>['inherit','bottom-right','bottom-left','top-right','top-left'],'shape'=>['pill','rounded','circle'],'content'=>['both','icon','text'],'visibility'=>['auto','always','never'],'window'=>['modal','right','left','full'],'cards'=>['grid','list','carousel','compact']];
        foreach($choices as $k=>$values){if(is_string($input[$k]??null)&&in_array($input[$k],$values,true)){$d[$k]=$input[$k];}}
        foreach(['width'=>[360,1100],'height'=>[420,1200],'offset_x'=>[8,96],'offset_y'=>[8,96],'radius'=>[0,32]] as $k=>$range){if(is_numeric($input[$k]??null)){$d[$k]=max($range[0],min($range[1],(int)$input[$k]));}}
        foreach(['accent','background','surface','text'] as $k){if(is_string($input[$k]??null)&&preg_match('/^#[0-9a-fA-F]{6}$/D',$input[$k])){$d[$k]=strtolower($input[$k]);}}
        return $d;
    }

    public static function designAttributes(): string {
        $d=self::design();$attrs='';
        foreach(['placement','mobile','shape','content','window','cards'] as $k){$attrs.=' data-w27-'.$k.'="'.esc_attr($d[$k]).'"';}
        $style='';foreach(['width','height','offset_x','offset_y','radius'] as $k){$style.='--w27-'.str_replace('_','-',$k).':'.$d[$k].'px;';}
        foreach(['accent','background','surface','text'] as $k){$style.='--w27-custom-'.$k.':'.$d[$k].';';}
        $rgb=array_map(static function($v){$c=hexdec($v)/255;return $c<=0.04045?$c/12.92:pow(($c+0.055)/1.055,2.4);},str_split(substr($d['accent'],1),2));
        $style.='--w27-custom-button-text:'.(0.2126*$rgb[0]+0.7152*$rgb[1]+0.0722*$rgb[2]>0.179?'#000':'#fff').';';
        return $attrs.' style="'.esc_attr($style).'"';
    }

    public static function sourceImage($url): string {
        if(!is_string($url)||strlen($url)>2048||!self::sameSiteUrl($url)){return '';}
        $p=wp_parse_url($url);$path=rawurldecode((string)($p['path']??''));
        if(!empty($p['query'])||!empty($p['fragment'])||preg_match('~(?:^|/)(?:wp-admin|wp-json|wp-login\.php)(?:/|$)~i',$path)||!preg_match('/\.(?:png|jpe?g|webp|avif|gif)$/iD',$path)){return '';}
        return $url;
    }

    const CHAT_MODEL='openai/gpt-6-luna';

     
    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'frontend_enabled' => true,
            'frontend_mode' => 'floating',
            'widget_language' => 'auto',
            'operator_name' => get_bloginfo('name'),
            'launcher_label' => 'Site Search',
            'panel_title' => 'How can we help?',
            'placeholder' => 'Ask a question about this site',
            'suggestions' => '',
            'semantic'=>false,'custom_embeddings_confirmed'=>false,'embed_model'=>'openai/text-embedding-3-small',
            'external_enabled' => false,
            'provider' => 'proxyapi_openai',
            'api_base' => '',
            'api_protocol' => 'chat_completions',
            'api_key' => '',
            'model' => self::CHAT_MODEL,
            'temperature' => 0.2,
            'top_p' => 1.0,
            'max_tokens' => 700,
            'reasoning_effort' => 'none',
            'request_timeout' => 18,
            'prompt_cache_key' => '',
            'proxyapi_log_metadata' => false,
            'system_prompt' => '',
            'rag_answer_instructions' => 'Give a direct answer first. Be concise and clear. For complex questions, use clear steps.',
            'rag_data_instructions' => 'Use only relevant sources. Distinguish starting prices from final prices. If sources conflict, explain and suggest clarification.',
            'rag_client_instructions' => 'Consider the visitor’s stated goal. Ask at most one clarifying question per answer. Suggest a relevant next step supported by the site.',

            'rag_limit' => 8,
            'rag_context_chars' => 18000,
            'min_query_chars' => 2,
            'rate_limit' => 20,
            'daily_ai_budget' => 100,
            'history_enabled' => true,
            'history_turns' => 4,
            'history_ttl_minutes' => 45,
            'log_enabled' => false,
            'log_retention_days' => 30,
            'sitemap_url' => home_url('/wp-sitemap.xml'),
            'index_virtual_pages' => false,
            'index_wp_pages' => true,
            'index_wp_posts' => true,
            'index_batch_size' => 2,
            'index_interval_minutes' => 5,
            'index_timeout' => 15,
            'index_user_agent' => 'WEB27 Site Search/' . WEB27_SITE_VERSION,
        ];
    }

     
    public static function get(): array
    {
        $saved = get_option(self::OPTION, []);
        $saved = is_array($saved) ? $saved : [];
        $o = wp_parse_args($saved, self::defaults());

        if (defined('WEB27_SITE_API_KEY') && WEB27_SITE_API_KEY !== '') {
            $o['api_key'] = (string) WEB27_SITE_API_KEY;
        }
        if (defined('WEB27_SITE_API_URL') && WEB27_SITE_API_URL !== '') {
            $previousBase=self::base($o);
            $o['api_base'] = self::baseFromEndpoint((string) WEB27_SITE_API_URL);
            $o['provider'] = 'custom';
            if ((!defined('WEB27_SITE_API_KEY') || WEB27_SITE_API_KEY==='') && self::base($o)!==$previousBase) { $o['api_key']=''; }
        }
        if (defined('WEB27_SITE_MODEL') && WEB27_SITE_MODEL !== '') {
            $o['model'] = (string) WEB27_SITE_MODEL;
        }
        $types=$saved['types']??['page','post'];
        $o['index_wp_posts']=$saved['index_wp_posts']??in_array('post',$types,true);
        $o['index_wp_pages']=$saved['index_wp_pages']??in_array('page',$types,true);
        $o['types']=array_keys(array_filter(['page'=>$o['index_wp_pages'],'post'=>$o['index_wp_posts']]));
        $o['logging']=$o['log_enabled']=$saved['log_enabled']??$saved['logging']??false;
        $o['daily_calls']=$o['daily_ai_budget']=$saved['daily_ai_budget']??$saved['daily_calls']??100;
        if (!in_array($o['provider'],self::providers(),true)) { $o['provider']='custom'; $o['api_base']=''; $o['api_key']=''; }
        if ($o['provider']==='openrouter') { $o['api_protocol']='chat_completions'; }
        return $o;
    }

     
    public static function localized(): array
    {
        $o = self::get();
        $saved = get_option(self::OPTION, []);
        $saved = is_array($saved) ? $saved : [];
        $labels = [
            'launcher_label' => __("Site Search", 'web27-site-search'),
            'panel_title' => __("How can we help?", 'web27-site-search'),
            'placeholder' => __("Ask a question about this site", 'web27-site-search'),
            'rag_answer_instructions' => __("Give a direct answer first. Be concise and clear. For complex questions, use clear steps.", 'web27-site-search'),
            'rag_data_instructions' => __("Use only relevant sources. Distinguish starting prices from final prices. If sources conflict, explain and suggest clarification.", 'web27-site-search'),
            'rag_client_instructions' => __("Consider the visitor’s stated goal. Ask at most one clarifying question per answer. Suggest a relevant next step supported by the site.", 'web27-site-search'),
        ];
        foreach ($labels as $key => $label) {
            if (!array_key_exists($key, $saved)) { $o[$key] = $label; }
        }
        return $o;
    }

    public static function sanitize(array $input): array
    {
        $d = self::defaults();
        $savedRaw = get_option(self::OPTION, []);
        $prev = self::get();
        $prev['api_key']=is_array($savedRaw)?($savedRaw['api_key']??''):'';

        $submittedKey = $input['api_key'] ?? '';
        $input = array_merge($prev, $input);
        $input['api_key'] = $submittedKey;
        

        $provider = sanitize_key((string)($input['provider'] ?? $prev['provider']));
        if (!in_array($provider, self::providers(), true)) {
            $provider = 'proxyapi_openai';
        }
        $protocol = sanitize_key((string)($input['api_protocol'] ?? $prev['api_protocol']));
        if (!in_array($protocol, ['chat_completions','responses'], true)) {
            $protocol = 'chat_completions';
        }
        $reasoning = sanitize_key((string)($input['reasoning_effort'] ?? 'none'));
        if (!in_array($reasoning, ['none','low','medium','high','xhigh','max'], true)) {
            $reasoning = 'none';
        }
        $mode = sanitize_key((string)($input['frontend_mode'] ?? 'floating'));
        if (!in_array($mode, ['floating','shortcode_only'], true)) {
            $mode = 'floating';
        }

        $base = esc_url_raw(trim((string)($input['api_base'] ?? '')));
        if ($provider !== 'custom') {
            $base = '';
        } elseif ($base !== '') {
            $base = self::baseFromEndpoint($base);
            if (!self::validBase($base)) {
                $base = '';
            }
        }

        $newKey = trim((string)($input['api_key'] ?? ''));
        $raw = self::storedProfile(is_array($savedRaw)?$savedRaw:[]);
        $same = $provider === $raw['provider'] && ($provider !== 'custom' || $base === self::baseFromEndpoint((string)$raw['api_base']));
        $apiKey = $newKey !== '' ? sanitize_text_field($newKey) : ($same ? (string)$raw['api_key'] : '');
        if ($provider === 'openrouter') { $protocol = 'chat_completions'; }
        if (!empty($input['clear_api_key'])) { $apiKey = ''; }
        if ($provider==='custom' && $base==='') { $apiKey=''; $input['external_enabled']=false; $input['semantic']=false; }
        if (!$same && $newKey==='' && !self::serverLocked()) { $input['external_enabled']=false; $input['semantic']=false; }
        $sitemap = esc_url_raw(trim((string)($input['sitemap_url'] ?? $d['sitemap_url'])));
        if ($sitemap === '' || !self::sameSiteUrl($sitemap)) {
            $sitemap = (string)$d['sitemap_url'];
        }

        return [
            'semantic'=>!empty($input['semantic']),
            'custom_embeddings_confirmed'=>$provider==='custom' && !empty($input['semantic']) && !empty($input['custom_embeddings_confirmed']),
            'embed_model'=>sanitize_text_field($input['embed_model']??''),
            'enabled' => !empty($input['enabled']),
            'frontend_enabled' => !empty($input['frontend_enabled']),
            'frontend_mode' => $mode,
            'widget_language' => self::normalizeWidgetLanguage($input['widget_language'] ?? 'auto'),
            'operator_name' => sanitize_text_field((string)($input['operator_name'] ?? $d['operator_name'])),
            'launcher_label' => sanitize_text_field((string)($input['launcher_label'] ?? $d['launcher_label'])),
            'panel_title' => sanitize_text_field((string)($input['panel_title'] ?? $d['panel_title'])),
            'placeholder' => sanitize_text_field((string)($input['placeholder'] ?? $d['placeholder'])),
            'suggestions' => sanitize_textarea_field((string)($input['suggestions'] ?? $d['suggestions'])),
            'external_enabled' => !empty($input['external_enabled']),
            'provider' => $provider,
            'api_base' => $base,
            'api_protocol' => $protocol,
            'api_key' => $apiKey,
            'model' => sanitize_text_field((string)($input['model'] ?? '')),
            'temperature' => max(0.0, min(2.0, (float)($input['temperature'] ?? $d['temperature']))),
            'top_p' => max(0.05, min(1.0, (float)($input['top_p'] ?? $d['top_p']))),
            'max_tokens' => max(120, min(4000, absint($input['max_tokens'] ?? $d['max_tokens']))),
            'reasoning_effort' => $reasoning,
            'request_timeout' => max(5, min(60, absint($input['request_timeout'] ?? $d['request_timeout']))),
            'prompt_cache_key' => sanitize_key((string)($input['prompt_cache_key'] ?? $d['prompt_cache_key'])),
            'proxyapi_log_metadata' => !empty($input['proxyapi_log_metadata']),
            'system_prompt' => sanitize_textarea_field((string)($input['system_prompt'] ?? '')),
            'rag_answer_instructions' => sanitize_textarea_field((string)($input['rag_answer_instructions'] ?? $d['rag_answer_instructions'])),
            'rag_data_instructions' => sanitize_textarea_field((string)($input['rag_data_instructions'] ?? $d['rag_data_instructions'])),
            'rag_client_instructions' => sanitize_textarea_field((string)($input['rag_client_instructions'] ?? $d['rag_client_instructions'])),
            'rag_limit' => max(3, min(12, absint($input['rag_limit'] ?? $d['rag_limit']))),
            'rag_context_chars' => max(4000, min(50000, absint($input['rag_context_chars'] ?? $d['rag_context_chars']))),
            'min_query_chars' => max(2, min(12, absint($input['min_query_chars'] ?? $d['min_query_chars']))),
            'rate_limit' => max(1, min(120, absint($input['rate_limit'] ?? $d['rate_limit']))),
            'daily_ai_budget' => max(0, min(10000, absint($input['daily_ai_budget'] ?? $d['daily_ai_budget']))),
            'history_enabled' => !empty($input['history_enabled']),
            'history_turns' => max(1, min(10, absint($input['history_turns'] ?? $d['history_turns']))),
            'history_ttl_minutes' => max(5, min(240, absint($input['history_ttl_minutes'] ?? $d['history_ttl_minutes']))),
            'log_enabled' => !empty($input['log_enabled']),
            'log_retention_days' => max(1, min(365, absint($input['log_retention_days'] ?? $d['log_retention_days']))),
            'sitemap_url' => $sitemap,
            'index_virtual_pages' => !empty($input['index_virtual_pages']),
            'index_wp_pages' => !empty($input['index_wp_pages']),
            'index_wp_posts' => !empty($input['index_wp_posts']),
            'index_batch_size' => max(1, min(10, absint($input['index_batch_size'] ?? $d['index_batch_size']))),
            'index_interval_minutes' => max(1, min(60, absint($input['index_interval_minutes'] ?? $d['index_interval_minutes']))),
            'index_timeout' => max(5, min(30, absint($input['index_timeout'] ?? $d['index_timeout']))),
            'index_user_agent' => sanitize_text_field((string)($input['index_user_agent'] ?? $d['index_user_agent'])),
        ];
    }

    public static function normalizeWidgetLanguage($value): string
    {
        return is_string($value) && in_array($value,['auto','en_US','ru_RU'],true) ? $value : 'auto';
    }

    public static function widgetLanguage(): string
    {
        $saved=get_option(self::OPTION,[]);
        return self::normalizeWidgetLanguage(is_array($saved)?($saved['widget_language']??'auto'):'auto');
    }

    public static function providers(): array
    {
        return ['proxyapi_openai','proxyapi_compatible','openai','openrouter','custom'];
    }

    public static function profileDefaults(string $provider): array
    {
        $models=match($provider) {
            'openai'=>['gpt-5-mini','text-embedding-3-small'],
            'openrouter'=>['openai/gpt-5-mini','openai/text-embedding-3-small'],
            'custom'=>['',''],
            default=>[self::CHAT_MODEL,'openai/text-embedding-3-small'],
        };
        return ['provider'=>$provider,'api_base'=>'','api_key'=>'','model'=>$models[0],'embed_model'=>$models[1],
            'api_protocol'=>'chat_completions','temperature'=>0.2,'top_p'=>1.0,'reasoning_effort'=>'none',
            'prompt_cache_key'=>'','proxyapi_log_metadata'=>false];
    }

    public static function storedProfile(array $saved): array
    {
        $provider=(string)($saved['provider']??'proxyapi_openai');
        if (!in_array($provider,self::providers(),true)) { $provider='custom'; $saved['api_key']=''; $saved['api_base']=''; }
        $defaults=self::profileDefaults($provider);
        return array_replace($defaults,array_intersect_key($saved,$defaults),['provider'=>$provider]);
    }

    public static function serverLocked(): bool
    {
        return (defined('WEB27_SITE_API_KEY') && WEB27_SITE_API_KEY!=='') ||
            (defined('WEB27_SITE_API_URL') && WEB27_SITE_API_URL!=='') ||
            (defined('WEB27_SITE_MODEL') && WEB27_SITE_MODEL!=='');
    }

    public static function switchProvider(array $saved,string $target): array
    {
        if (self::serverLocked() || !in_array($target,self::providers(),true)) { throw new \RuntimeException('provider_switch_unavailable'); }
        $old=self::storedProfile($saved);
        if ($old['provider']===$target) { return $saved; }
        $profiles=is_array($saved['provider_profiles']??null)?$saved['provider_profiles']:[];
        $profiles[$old['provider']]=$old;
        $next=self::profileDefaults($target);
        if (isset($profiles[$target]) && is_array($profiles[$target])) {
            $next=array_replace($next,array_intersect_key($profiles[$target],$next),['provider'=>$target]);
        }
        unset($profiles[$target]);
        return array_replace($saved,$next,['provider_profiles'=>$profiles,'external_enabled'=>false,'semantic'=>false,'custom_embeddings_confirmed'=>false]);
    }

    public static function validBase(string $base): bool
    {
        $parts=wp_parse_url($base);
        return is_array($parts) && strtolower((string)($parts['scheme']??''))==='https' && !empty($parts['host']) &&
            !isset($parts['user']) && !isset($parts['pass']) &&
            !isset($parts['query']) && !isset($parts['fragment']);
    }

    public static function base(array $o): string
    {
        $base=match($o['provider']??'proxyapi_openai') {
            'proxyapi_openai','proxyapi_compatible'=>'https://api.proxyapi.ru/v1',
            'openai'=>'https://api.openai.com/v1',
            'openrouter'=>'https://openrouter.ai/api/v1',
            'custom'=>self::baseFromEndpoint((string)($o['api_base']??'')),
            default=>'',
        };
        return self::validBase($base)?$base:'';
    }

    public static function endpoint(?array $o = null): string
    {
        $o=$o??self::get(); $base=self::base($o);
        $suffix=($o['provider']??'')!=='openrouter' && ($o['api_protocol']??'')==='responses'?'/responses':'/chat/completions';
        return $base!==''?$base.$suffix:'';
    }

    public static function embeddingsEndpoint(array $o): string
    {
        $base=self::base($o); return $base!==''?$base.'/embeddings':'';
    }

    public static function embeddingsConfigured(array $o): bool
    {
        return !empty($o['semantic']) && ($o['provider']!=='custom' || !empty($o['custom_embeddings_confirmed'])) && self::embeddingsEndpoint($o)!=='' && !empty($o['api_key']) && !empty($o['embed_model']);
    }

    public static function vectorModel(array $o): string
    {
         
        if (in_array($o['provider'],['proxyapi_openai','proxyapi_compatible'],true)) { return (string)$o['embed_model']; }
        return 'w27v2:'.hash('sha256',wp_json_encode([$o['provider'],self::base($o),$o['embed_model']]));
    }

    public static function profileSignature(array $o): string
    {
        return wp_hash(wp_json_encode([$o['provider'],self::base($o),(string)$o['model'],(string)$o['embed_model'],(string)$o['api_protocol'],hash('sha256',(string)$o['api_key'])]));
    }

    public static function gatewayConfigured(): bool
    {
        $o = self::get();
        return !empty($o['external_enabled']) && self::endpoint($o) !== '' && trim((string)$o['api_key']) !== '' && trim((string)$o['model']) !== '';
    }

    public static function sameSiteUrl(string $url): bool
    {
        $home=wp_parse_url(home_url('/'));
        $target=wp_parse_url($url);
        if (!is_array($home) || !is_array($target) || isset($target['user']) || isset($target['pass'])) { return false; }
        $scheme=strtolower((string)($target['scheme']??''));
        $homeScheme=strtolower((string)($home['scheme']??''));
        if (!in_array($scheme,['http','https'],true) || $scheme!==$homeScheme) { return false; }
        $host=strtolower((string)($target['host']??''));
        return $host!=='' && $host===strtolower((string)($home['host']??'')) && (int)($target['port']??($scheme==='https'?443:80))===(int)($home['port']??($homeScheme==='https'?443:80));
    }

    private static function baseFromEndpoint(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $url = preg_replace('#/(?:chat/completions|responses|embeddings)$#', '', $url) ?: $url;
        return rtrim($url, '/');
    }
}
