<?php

declare(strict_types=1);

namespace WEB27\SiteSearch;
defined( 'ABSPATH' ) || exit;

final class Frontend
{
    public function register(): void
    {
        add_shortcode('web27_site_search',[$this,'shortcode']);
        add_action('wp_enqueue_scripts',[$this,'assets']);
        add_action('wp_footer',[$this,'floating']);
    }

    public function assets(): void
    {
        Language::withInterfaceLocale(Language::interfaceLocale(),fn()=>$this->renderAssets());
    }

    private function renderAssets(): void
    {
        $o=Settings::localized();
        if (empty($o['enabled']) || empty($o['frontend_enabled'])) { return; }
        wp_register_style('web27-site-search',WEB27_SITE_URL.'assets/search.css',[],WEB27_SITE_VERSION);
        wp_register_script('web27-site-search',WEB27_SITE_URL.'assets/search.js',[],WEB27_SITE_VERSION,true);
        wp_enqueue_style('web27-site-search');
        wp_enqueue_script('web27-site-search');
        wp_localize_script('web27-site-search','Web27SiteSearch',[
            'voteEndpoint'=>rest_url('web27-site-search/v1/vote'),
            'endpoint'=>rest_url('web27-site-search/v1/query'),
            'siteUrl'=>home_url('/'),
            'locale'=>Language::interfaceLocale(),
            'strings'=>[
                'openPage'=>__("Open page", 'web27-site-search'),
                'page'=>__("page", 'web27-site-search'),
                'searching'=>__("Searching…", 'web27-site-search'),
                'submit'=>__("Find an answer", 'web27-site-search'),
                'searchingMaterials'=>__("Searching site materials…", 'web27-site-search'),
                'received'=>__("Answer received.", 'web27-site-search'),
                'helpful'=>__("Helpful", 'web27-site-search'),
                'notHelpful'=>__("Not helpful", 'web27-site-search'),
                'ratingSaved'=>__("Rating saved", 'web27-site-search'),
                'ratingError'=>__("Could not save rating", 'web27-site-search'),
                'previous'=>__('Previous materials', 'web27-site-search'),
                'next'=>__('Next materials', 'web27-site-search'),
                'related'=>__("Related materials", 'web27-site-search'),
                'openArrow'=>__("Open page →", 'web27-site-search'),
                'fallback'=>__("Search results (AI unavailable).", 'web27-site-search'),
                'local'=>__("Local search results.", 'web27-site-search'),
            ],
            'operator'=>(string)$o['operator_name'],
            'error'=>__('Could not get an answer. Please try again.', 'web27-site-search'),
        ]);
    }

    public function shortcode(): string
    {
        return Language::withInterfaceLocale(Language::interfaceLocale(),fn()=>$this->renderShortcode());
    }

    private function renderShortcode(): string
    {
        $o=Settings::localized();
        if (empty($o['enabled']) || empty($o['frontend_enabled'])) { return ''; }
        return $this->panel(false);
    }

    public function floating(): void
    {
        Language::withInterfaceLocale(Language::interfaceLocale(),fn()=>$this->renderFloating());
    }

    private function renderFloating(): void
    {
        $o=Settings::localized();
        if (empty($o['enabled']) || empty($o['frontend_enabled'])) { return; }
        $themeInline = current_theme_supports('web27-site-search-inline');
        $design=Settings::design();
        $showButton=$design['visibility']==='always'||($design['visibility']==='auto'&&!$themeInline&&(string)$o['frontend_mode']==='floating');
        if (!$themeInline && !$showButton && $design['visibility']!=='never') { return; }
        if ($showButton) {
            echo '<button class="w27public-launcher" lang="'.esc_attr(str_replace('_','-',Language::interfaceLocale())).'" data-w27public-theme="'.esc_attr(Settings::appearance()) . '"'.Settings::designAttributes().' type="button" data-w27public-open aria-haspopup="dialog" aria-controls="w27public-dialog" aria-label="'.esc_attr((string)$o['launcher_label']).'"><span class="w27public-launcher-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg></span><span class="w27public-launcher-label">'.esc_html((string)$o['launcher_label']).'</span></button>';
        }
        echo '<dialog class="w27public-dialog" lang="'.esc_attr(str_replace('_','-',Language::interfaceLocale())).'" id="w27public-dialog" aria-label="'.esc_attr((string)$o['panel_title']).'" data-w27public-dialog data-w27public-theme="'.esc_attr(Settings::appearance()).'"'.Settings::designAttributes().'>';
        echo $this->panel(true);
        echo '</dialog>';
    }

    private function panel(bool $modal): string
    {
        $o=Settings::localized();
        $suggestions=array_values(array_filter(array_map('trim',preg_split('/\R/u',(string)$o['suggestions']) ?: [])));
        $inputId = wp_unique_id('w27public-q-');
        ob_start(); ?>
        <section class="w27public-panel<?php echo $modal ? ' is-modal' : ' is-inline'; ?>" data-w27public-root lang="<?php echo esc_attr(str_replace('_','-',Language::interfaceLocale())); ?>" data-w27public-theme="<?php echo esc_attr(Settings::appearance()); ?>"<?php echo Settings::designAttributes(); ?>>
            <div class="w27public-layout">
            <div class="w27public-head">
                <div><div class="w27public-eyebrow"><?php echo esc_html__('SITE SEARCH', 'web27-site-search'); ?></div><h2><?php echo esc_html((string)$o['panel_title']); ?></h2><p><?php echo esc_html__('Enter a question or the name of a resource.', 'web27-site-search'); ?></p></div>
                <div class="w27public-head-actions"><button class="w27public-reset" type="button" data-w27public-reset><?php echo esc_html__('New question', 'web27-site-search'); ?></button>
                <?php if ($modal): ?><button class="w27public-close" type="button" data-w27public-close aria-label="<?php echo esc_attr__('Close', 'web27-site-search'); ?>">×</button><?php endif; ?></div>
            </div>
            <form class="w27public-form" data-w27public-form>
                <label class="w27public-sr" for="<?php echo esc_attr($inputId); ?>"><?php echo esc_html__('Question', 'web27-site-search'); ?></label>
                <input id="<?php echo esc_attr($inputId); ?>" data-w27public-input type="search" minlength="<?php echo (int)$o['min_query_chars']; ?>" required maxlength="500" autocomplete="off" placeholder="<?php echo esc_attr((string)$o['placeholder']); ?>">
                <button type="submit"><?php echo esc_html__('Find an answer', 'web27-site-search'); ?></button>
            </form>
            <div class="w27public-results" data-w27public-results>
            <?php if (Settings::gatewayConfigured() || Settings::embeddingsConfigured($o)): ?><p class="w27public-disclosure"><?php echo esc_html__('AI search may send your query to an external service. Do not enter personal or confidential information.', 'web27-site-search'); ?><?php if (get_privacy_policy_url()): ?> <a href="<?php echo esc_url(get_privacy_policy_url()); ?>"><?php echo esc_html__('Privacy policy', 'web27-site-search'); ?></a><?php endif; ?></p><?php endif; ?>
            <?php if ($suggestions): ?><div class="w27public-suggest" aria-label="<?php echo esc_attr__('Suggested questions', 'web27-site-search'); ?>"><?php foreach (array_slice($suggestions,0,5) as $s): ?><button type="button" data-w27public-suggest="<?php echo esc_attr($s); ?>"><?php echo esc_html($s); ?></button><?php endforeach; ?></div><?php endif; ?>
            <div class="w27public-state" data-w27public-state role="status" aria-live="polite"></div>
            <div class="w27public-answer" data-w27public-answer aria-live="polite" hidden></div>
            <div class="w27public-sources" data-w27public-sources hidden></div>
            <div class="w27public-actions" data-w27public-actions hidden></div>
            </div>
            </div>
        </section>
        <?php return (string)ob_get_clean();
    }
}
