<?php
/**
 * Public footer — Landing v2.
 * Renders inside the .landing-v2 wrapper from layouts/main.php.
 * Preserves: \Helpers\App::pages('main'|'company'|'policy'|'terms'),
 *            config('sociallinks'|'facebook'|'twitter'|'helpcenter'|'api'|
 *                   'affiliate'|'contact'|'report'|'verifylink'|'cookieconsent'),
 *            route('page'|'help'|'apidocs'|'affiliate'|'contact'|'report'|'links.verify'|'register'),
 *            \Helpers\App::langs(), \Core\Localization::locale().
 */
?>
<footer class="lv2-footer" id="footer-main">
    <div class="lv2-container">

        <div class="lv2-footer__grid">

            <!-- Brand column -->
            <div class="lv2-footer__brand">
                <a href="<?php echo route('home') ?>" class="lv2-footer__brand-name" aria-label="<?php echo config('sitename') ?>">
                    <?php if(config('logo')): ?>
                        <img src="<?php echo uploads(config('logo')) ?>" alt="<?php echo config('sitename') ?>">
                    <?php else: ?>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#lv2flogo)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <defs><linearGradient id="lv2flogo" x1="0" x2="1" y1="0" y2="1"><stop offset="0%" stop-color="#4F46E5"/><stop offset="100%" stop-color="#7C3AED"/></linearGradient></defs>
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                        </svg>
                        <?php echo config('sitename') ?>
                    <?php endif ?>
                </a>
                <p class="lv2-footer__desc"><?php echo e(config('description') ?: e('Shorten URLs, build branded bio pages, generate QR codes, and track every click — all from one premium platform.')) ?></p>
                <div class="lv2-footer__socials" aria-label="<?php echo e('Social links') ?>">
                    <?php if($facebook = config('facebook')): ?>
                        <a href="<?php echo $facebook ?>" target="_blank" rel="noopener" aria-label="Facebook"><i class="fab fa-facebook" aria-hidden="true"></i></a>
                    <?php endif ?>
                    <?php if($twitter = config('twitter')): ?>
                        <a href="<?php echo $twitter ?>" target="_blank" rel="noopener" aria-label="X (Twitter)"><i class="fab fa-x-twitter" aria-hidden="true"></i></a>
                    <?php endif ?>
                    <?php if(isset(config('sociallinks')->linkedin) && $linkedin = config('sociallinks')->linkedin): ?>
                        <a href="<?php echo $linkedin ?>" target="_blank" rel="noopener" aria-label="LinkedIn"><i class="fab fa-linkedin" aria-hidden="true"></i></a>
                    <?php endif ?>
                    <?php if(isset(config('sociallinks')->instagram) && $instagram = config('sociallinks')->instagram): ?>
                        <a href="<?php echo $instagram ?>" target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram" aria-hidden="true"></i></a>
                    <?php endif ?>
                </div>
            </div>

            <!-- Solutions -->
            <div>
                <h6 class="lv2-footer__heading"><?php ee('Solutions') ?></h6>
                <ul class="lv2-footer__list">
                    <li><a href="<?php echo route('page.qr') ?>"><?php ee('QR Codes') ?></a></li>
                    <li><a href="<?php echo route('page.bio') ?>"><?php ee('Bio Pages') ?></a></li>
                    <?php foreach(\Helpers\App::pages('main') as $page): ?>
                        <li><a href="<?php echo route('page', [$page->seo]) ?>"><?php ee($page->name) ?></a></li>
                    <?php endforeach ?>
                </ul>
            </div>

            <!-- Resources -->
            <div>
                <h6 class="lv2-footer__heading"><?php ee('Resources') ?></h6>
                <ul class="lv2-footer__list">
                    <?php if(config('helpcenter')): ?>
                        <li><a href="<?php echo route('help') ?>"><?php ee('Help Center') ?></a></li>
                    <?php endif ?>
                    <?php if(config('contact')): ?>
                        <li><a href="<?php echo route('contact') ?>"><?php ee('Contact Us') ?></a></li>
                    <?php endif ?>
                    <?php if(config('blog')): ?>
                        <li><a href="<?php echo route('blog') ?>"><?php ee('Blog') ?></a></li>
                    <?php endif ?>
                    <?php if(config('api')): ?>
                        <li><a href="<?php echo route('apidocs') ?>"><?php ee('Developers') ?></a></li>
                    <?php endif ?>
                </ul>
            </div>

            <!-- Company -->
            <div>
                <h6 class="lv2-footer__heading"><?php ee('Company') ?></h6>
                <ul class="lv2-footer__list">
                    <?php foreach(\Helpers\App::pages('company') as $page): ?>
                        <li><a href="<?php echo route('page', [$page->seo]) ?>"><?php ee($page->name) ?></a></li>
                    <?php endforeach ?>
                    <?php if(config('pro')): ?>
                        <li><a href="<?php echo route('pricing') ?>"><?php ee('Pricing') ?></a></li>
                    <?php endif ?>
                    <?php if(config('pro') && config('affiliate')->enabled): ?>
                        <li><a href="<?php echo route('affiliate') ?>"><?php ee('Affiliate') ?></a></li>
                    <?php endif ?>
                    <?php foreach(\Helpers\App::pages('policy') as $page): ?>
                        <li><a href="<?php echo route('page', [$page->seo]) ?>"><?php ee($page->name) ?></a></li>
                    <?php endforeach ?>
                    <?php foreach(\Helpers\App::pages('terms') as $page): ?>
                        <li><a href="<?php echo route('page', [$page->seo]) ?>"><?php ee($page->name) ?></a></li>
                    <?php endforeach ?>
                </ul>
            </div>

            <!-- Newsletter -->
            <div>
                <h6 class="lv2-footer__heading"><?php ee('Newsletter') ?></h6>
                <p class="lv2-newsletter__sub"><?php ee('Get tips, updates, and offers.') ?></p>
                <form class="lv2-newsletter__form"
                      action="<?php echo url('server/subscribe') ?>"
                      method="post"
                      data-trigger="newsletter-form"
                      aria-label="<?php echo e('Newsletter signup') ?>">
                    <input type="email"
                           class="lv2-newsletter__input"
                           name="email"
                           placeholder="<?php echo e('Enter your email') ?>"
                           required
                           autocomplete="email">
                    <button type="submit" class="lv2-newsletter__btn"><?php ee('Subscribe') ?></button>
                </form>
            </div>
        </div>

        <!-- Bottom row -->
        <div class="lv2-footer__bottom">
            <div class="lv2-footer__copy">
                &copy; <?php echo date("Y") ?>
                <a href="<?php echo config('url') ?>"><?php echo config('sitename') ?></a>.
                <?php ee('All Rights Reserved') ?>
            </div>

            <div class="lv2-footer__bottom-links">
                <?php if(config('report')): ?>
                    <a href="<?php echo route('report') ?>"><?php ee('Report') ?></a>
                <?php endif ?>
                <?php if(config('verifylink')): ?>
                    <a href="<?php echo route('links.verify') ?>"><?php ee('Verify Link') ?></a>
                <?php endif ?>
                <?php if(config('cookieconsent')->enabled): ?>
                    <a href="#" data-cc="c-settings"><?php ee('Cookie Settings') ?></a>
                <?php endif ?>
                <?php if($langs = \Helpers\App::langs()): ?>
                    <span class="dropup lv2-footer__lang">
                        <a class="lv2-footer__lang" data-toggle="dropdown" href="#" aria-haspopup="true" aria-expanded="false">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                            <?php echo strtoupper(\Core\Localization::locale()) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-right">
                            <?php foreach($langs as $lang): ?>
                                <li><a class="dropdown-item" href="<?php echo url($lang['code']) ?>"><?php echo $lang['name'] ?></a></li>
                            <?php endforeach ?>
                        </ul>
                    </span>
                <?php endif ?>
            </div>
        </div>
    </div>
</footer>
