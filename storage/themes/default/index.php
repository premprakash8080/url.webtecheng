<?php
/**
 * ===================================================================
 *  Home page — Landing v2 (mockup-aligned premium SaaS redesign)
 * -------------------------------------------------------------------
 *  PRESERVATION CONTRACT (JS-bound — do not rename without checking
 *  public/static/frontend/js/app.min.js and server.min.js):
 *    - <form data-trigger="shorten-form"> POSTs to route('shorten').
 *    - Field names + IDs: url, custom, pass.
 *    - Copy button: `.btn-warning.d-none` (toggled by JS on success).
 *    - Submit button: real <button type="submit">.
 *    - Result containers: #output-result, #qr-result, #text-result.
 *    - Captcha is injected by \Helpers\Captcha::display('shorten').
 *
 *  The CSS for this page (and all public pages using layouts/main.php)
 *  is loaded by layouts/main.php — see public/static/frontend/css/landing.css.
 *  The whole layout is wrapped in `.landing-v2` so styles are scoped.
 *
 *  Visual reference: user-provided mockup (purple/violet brand,
 *  reservation-style input, bio-page hero mockup, inline stat strip,
 *  integration row, features + analytics dashboard, CTA banner).
 * ===================================================================
 */

$_hero_img = (isset($themeconfig->hero) && !empty($themeconfig->hero))
    ? uploads($themeconfig->hero)
    : null;
?>

<!-- ============================================================
     HERO
     ============================================================ -->
<section class="lv2-hero" aria-labelledby="lv2-hero-title">
    <div class="lv2-container">
        <div class="lv2-hero__grid">

            <div class="lv2-hero__copy lv2-fade-up">
                <span class="lv2-eyebrow">
                    <span class="lv2-eyebrow__dot" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l1.8 5.6L19 9l-4.2 3.3L16 18l-4-2.6L8 18l1.2-5.7L5 9l5.2-1.4L12 2z"/></svg>
                    </span>
                    <?php ee('Create. Customize. Share.') ?>
                </span>

                <h1 id="lv2-hero-title" class="lv2-hero__title">
                    <?php ee('One link to') ?><br>
                    <span class="lv2-gradient-text"><?php ee('rule them all.') ?></span>
                </h1>

                <p class="lv2-hero__lead">
                    <?php ee('Shorten URLs, build branded bio pages, generate QR codes, and track every click — all from one premium platform that connects your audience to everything you do.') ?>
                </p>

                <!-- Flash messages preserved -->
                <div class="lv2-flash"><?php message() ?></div>

                <!-- Shortener form — JS hooks frozen -->
                <form method="post"
                      action="<?php echo route('shorten') ?>"
                      data-trigger="shorten-form"
                      aria-label="<?php echo e('Shorten a URL') ?>">

                    <div class="lv2-domain-input">
                        <span class="lv2-domain-input__leading" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                            </svg>
                        </span>
                        <input type="text"
                               class="form-control lv2-domain-input__field"
                               name="url"
                               id="url"
                               placeholder="<?php echo e('Paste a long URL') ?>"
                               autocomplete="off"
                               inputmode="url"
                               spellcheck="false"
                               required>
                        <!-- Copy button: JS toggles `.d-none` after successful shorten -->
                        <button class="btn btn-warning d-none lv2-domain-input__btn"
                                type="button"
                                aria-label="<?php echo e('Copy short link') ?>"><?php ee('Copy') ?></button>
                        <button class="btn btn-success lv2-domain-input__btn"
                                type="submit"><?php ee('Shorten') ?></button>
                    </div>

                    <?php if(!config('pro')): ?>
                        <details class="lv2-details" style="margin-top: 8px;">
                            <summary style="cursor:pointer; font-size: 12px; color: var(--lv2-text-3); font-weight: 600; padding: 6px 0;">
                                <?php ee('Advanced options') ?>
                            </summary>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 10px;">
                                <div>
                                    <label for="custom" style="display:block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: var(--lv2-text-2);"><?php ee('Custom alias') ?></label>
                                    <input type="text"
                                           class="form-control lv2-newsletter__input"
                                           name="custom"
                                           id="custom"
                                           placeholder="<?php echo e('your-brand') ?>"
                                           autocomplete="off">
                                </div>
                                <div>
                                    <label for="pass" style="display:block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: var(--lv2-text-2);"><?php ee('Password protection') ?></label>
                                    <input type="text"
                                           class="form-control lv2-newsletter__input"
                                           name="pass"
                                           id="pass"
                                           placeholder="<?php echo e('Optional password') ?>"
                                           autocomplete="off">
                                </div>
                            </div>
                        </details>
                    <?php endif ?>

                    <?php if(!\Core\Auth::logged()) { echo \Helpers\Captcha::display('shorten'); } ?>
                </form>

                <!-- Result container — JS targets these IDs, must remain -->
                <div id="output-result" class="d-none" role="status" aria-live="polite" style="margin-top: var(--lv2-space-5); padding: var(--lv2-space-5); border-radius: var(--lv2-radius-md); background: rgba(16,185,129,.08); border: 1px solid rgba(16,185,129,.3);">
                    <div class="row">
                        <div id="qr-result" class="col-md-4 p-2"></div>
                        <div id="text-result" class="col-md-8">
                            <p style="margin-bottom: 12px; font-size: 14px;"><?php ee('Your link has been successfully shortened. Want more customization options?') ?></p>
                            <a href="<?php echo route('register') ?>" class="lv2-btn lv2-btn--primary lv2-btn--sm"><?php ee('Get started') ?></a>
                        </div>
                    </div>
                </div>

                <!-- Trust checkpills -->
                <div class="lv2-trust-row" aria-label="<?php echo e('Why people trust us') ?>">
                    <span class="lv2-trust-row__item">
                        <span class="lv2-trust-row__check" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </span>
                        <?php ee('Free Forever') ?>
                    </span>
                    <span class="lv2-trust-row__item">
                        <span class="lv2-trust-row__check" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </span>
                        <?php ee('No Credit Card') ?>
                    </span>
                    <span class="lv2-trust-row__item">
                        <span class="lv2-trust-row__check" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </span>
                        <?php ee('Easy to Use') ?>
                    </span>
                </div>

                <!-- Hero CTAs -->
                <div class="lv2-hero__ctas">
                    <?php if(!\Core\Auth::logged() && config("user") && !config("private") && !config("maintenance")): ?>
                        <a href="<?php echo route('register') ?>" class="lv2-btn lv2-btn--primary lv2-btn--lg">
                            <?php ee('Get Started — It\'s Free') ?>
                        </a>
                    <?php elseif(\Core\Auth::logged()): ?>
                        <a href="<?php echo route('dashboard') ?>" class="lv2-btn lv2-btn--primary lv2-btn--lg">
                            <?php ee('Go to dashboard') ?>
                        </a>
                    <?php endif ?>
                    <?php if(config('contact')): ?>
                        <a href="<?php echo route('contact') ?>" class="lv2-btn lv2-btn--ghost lv2-btn--lg">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            <?php ee('Contact Sales') ?>
                        </a>
                    <?php endif ?>
                </div>

                <!-- Social proof -->
                <div class="lv2-social-proof">
                    <div class="lv2-avatar-stack" aria-hidden="true">
                        <img src="https://i.pravatar.cc/72?img=11" alt="" loading="lazy">
                        <img src="https://i.pravatar.cc/72?img=32" alt="" loading="lazy">
                        <img src="https://i.pravatar.cc/72?img=45" alt="" loading="lazy">
                        <img src="https://i.pravatar.cc/72?img=68" alt="" loading="lazy">
                    </div>
                    <div class="lv2-social-proof__meta">
                        <div class="lv2-social-proof__stars" aria-label="<?php echo e('Rated 4.9 out of 5') ?>">
                            <?php for($i=0;$i<5;$i++): ?>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <?php endfor ?>
                        </div>
                        <p class="lv2-social-proof__text" style="margin:0;">
                            <strong>4.9/5</strong> <?php ee('from') ?> <strong>1,200+ <?php ee('happy users') ?></strong>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Hero visual: bio-page mockup -->
            <div class="lv2-hero__visual lv2-fade-up lv2-fade-up--d2" aria-hidden="true">
                <?php if($_hero_img): ?>
                    <img src="<?php echo $_hero_img ?>"
                         alt="<?php echo config('title') ?>"
                         style="max-width:420px; width:100%; border-radius: var(--lv2-radius-2xl); box-shadow: var(--lv2-shadow-lg); border: 1px solid var(--lv2-border);"
                         loading="eager"
                         decoding="async"
                         fetchpriority="high">
                <?php else: ?>
                <div class="lv2-mockup">
                    <div class="lv2-mockup__bar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        <span class="lv2-mockup__bar-url">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="display:inline; vertical-align:-1px;"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            yourname.<?php echo parse_url(config('url'), PHP_URL_HOST) ?: 'url.example.com' ?>
                        </span>
                    </div>
                    <div class="lv2-mockup__panel">
                        <div class="lv2-mockup__avatar">M</div>
                        <div class="lv2-mockup__name">Madison</div>
                        <div class="lv2-mockup__socials">
                            <a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 12a10 10 0 1 0-11.56 9.88V14.9H7.9V12h2.54V9.8c0-2.5 1.49-3.88 3.77-3.88 1.09 0 2.24.19 2.24.19v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.77l-.44 2.9h-2.33v6.98A10 10 0 0 0 22 12z"/></svg></a>
                            <a href="#" aria-label="X"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
                        </div>
                        <div class="lv2-mockup__links">
                            <span class="lv2-mockup__link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                New Merch
                            </span>
                            <span class="lv2-mockup__link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
                                Shop
                            </span>
                        </div>
                    </div>
                </div>
                <?php endif ?>
            </div>

        </div>

        <!-- Inline stat strip (only shown when stats config on; otherwise hidden) -->
        <?php if (config("homepage_stats")): ?>
        <div class="lv2-stat-strip lv2-fade-up lv2-fade-up--d3" style="max-width: 560px; margin-left: auto; margin-right: auto;">
            <div class="lv2-stat-strip__item">
                <div class="lv2-stat-strip__value"><span class="counter"><?php echo $count->users ?></span>+</div>
                <div class="lv2-stat-strip__label"><?php ee('Active Users') ?></div>
            </div>
            <div class="lv2-stat-strip__item">
                <div class="lv2-stat-strip__value"><span class="counter"><?php echo $count->links ?></span>+</div>
                <div class="lv2-stat-strip__label"><?php ee('Links Created') ?></div>
            </div>
            <div class="lv2-stat-strip__item">
                <div class="lv2-stat-strip__value">99.9%</div>
                <div class="lv2-stat-strip__label"><?php ee('Uptime') ?></div>
            </div>
        </div>
        <?php endif ?>

    </div>
</section>

<!-- ============================================================
     INTEGRATION STRIP
     ============================================================ -->
<section class="lv2-section--tight" style="padding-top: 0;">
    <div class="lv2-container">
        <div class="lv2-integ-strip" aria-label="<?php echo e('Integrations') ?>">
            <div class="lv2-integ-strip__title"><?php ee('Connect your audience everywhere') ?></div>
            <div class="lv2-integ-strip__items">
                <?php
                $_integ = [
                    ['wp.svg', 'WordPress'],
                    ['slack.svg', 'Slack'],
                    ['facebook.svg', 'Facebook'],
                    ['twitter.svg', 'X'],
                    ['linkedin.svg', 'LinkedIn'],
                    ['tiktok.svg', 'TikTok'],
                    ['pinterest.svg', 'Pinterest'],
                    ['zapier.svg', 'Zapier'],
                ];
                foreach($_integ as $_i): ?>
                    <div class="lv2-integ-strip__item" title="<?php echo $_i[1] ?>">
                        <span class="lv2-integ-strip__circle">
                            <img src="<?php echo assets('images/'.$_i[0]) ?>" alt="<?php echo $_i[1] ?>" loading="lazy" decoding="async">
                        </span>
                        <span class="lv2-integ-strip__name"><?php echo $_i[1] ?></span>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     USER HISTORY (conditional — preserved)
     ============================================================ -->
<?php if(config('user_history') && !\Core\Auth::logged() && $urls = \Helpers\App::userHistory()): ?>
    <section class="lv2-section" aria-labelledby="lv2-history-title">
        <div class="lv2-container">
            <h2 id="lv2-history-title" class="lv2-section-title"><?php ee('Your latest links') ?></h2>
            <p class="lv2-section-lead"><?php ee('Pick up where you left off — we kept your recent links in this browser.') ?></p>
            <div class="lv2-link-list" style="max-width: 720px;">
                <?php foreach($urls as $url): ?>
                    <div class="lv2-link-list__item">
                        <a class="lv2-link-list__title" href="<?php echo $url['url'] ?>" target="_blank" rel="noopener"><?php echo $url['meta_title'] ?></a>
                        <a class="lv2-link-list__url" href="<?php echo \Helpers\App::shortRoute($url['domain'], $url['alias'].$url['custom']) ?>"><?php echo \Helpers\App::shortRoute($url['domain'], $url['alias'].$url['custom']) ?></a>
                    </div>
                <?php endforeach ?>
            </div>
            <div style="margin-top: var(--lv2-space-6);">
                <a href="<?php echo route('register') ?>" class="lv2-btn lv2-btn--primary"><?php ee('Unlock advanced features') ?> &rarr;</a>
            </div>
        </div>
    </section>
<?php endif ?>

<!-- ============================================================
     PUBLIC LATEST LINKS (conditional — preserved)
     ============================================================ -->
<?php if(config('public_dir')): ?>
    <section class="lv2-section" aria-labelledby="lv2-public-title">
        <div class="lv2-container">
            <h2 id="lv2-public-title" class="lv2-section-title"><?php ee('Latest links') ?></h2>
            <p class="lv2-section-lead"><?php ee('A live look at what the community is sharing.') ?></p>
            <div class="lv2-link-list" style="max-width: 720px;">
                <?php foreach(\Core\DB::url()->where('public', '1')->orderByDesc('date')->limit(15)->findArray() as $url): ?>
                    <div class="lv2-link-list__item">
                        <a class="lv2-link-list__title" href="<?php echo $url['url'] ?>" target="_blank" rel="noopener"><?php echo $url['meta_title'] ?></a>
                        <a class="lv2-link-list__url" href="<?php echo \Helpers\App::shortRoute($url['domain'], $url['alias'].$url['custom']) ?>"><?php echo \Helpers\App::shortRoute($url['domain'], $url['alias'].$url['custom']) ?></a>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    </section>
<?php else: ?>

    <!-- ============================================================
         POWERFUL FEATURES — feature grid + analytics dashboard
         ============================================================ -->
    <section class="lv2-section" aria-labelledby="lv2-features-title">
        <div class="lv2-container">
            <div class="lv2-features-layout">
                <div class="lv2-fade-up">
                    <span class="lv2-section-eyebrow"><?php ee('Powerful features') ?></span>
                    <h2 id="lv2-features-title" class="lv2-section-title"><?php ee('Everything you need to grow') ?></h2>
                    <p class="lv2-section-lead"><?php ee('Powerful tools to help you create, customize, and analyze your links like never before.') ?></p>

                    <div class="lv2-feature-grid">
                        <article class="lv2-feature-card">
                            <span class="lv2-feature-card__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                            </span>
                            <div>
                                <h3 class="lv2-feature-card__title"><?php ee('45+ Dynamic Widgets') ?></h3>
                                <p class="lv2-feature-card__desc"><?php ee('Add videos, forms, links &amp; more.') ?></p>
                            </div>
                        </article>
                        <article class="lv2-feature-card">
                            <span class="lv2-feature-card__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                            </span>
                            <div>
                                <h3 class="lv2-feature-card__title"><?php ee('Customizable Designs') ?></h3>
                                <p class="lv2-feature-card__desc"><?php ee('Match your brand perfectly.') ?></p>
                            </div>
                        </article>
                        <article class="lv2-feature-card">
                            <span class="lv2-feature-card__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                            </span>
                            <div>
                                <h3 class="lv2-feature-card__title"><?php ee('Advanced Analytics') ?></h3>
                                <p class="lv2-feature-card__desc"><?php ee('Track clicks and engagement.') ?></p>
                            </div>
                        </article>
                        <article class="lv2-feature-card">
                            <span class="lv2-feature-card__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            </span>
                            <div>
                                <h3 class="lv2-feature-card__title"><?php ee('SEO Optimized') ?></h3>
                                <p class="lv2-feature-card__desc"><?php ee('Rank higher. Get discovered.') ?></p>
                            </div>
                        </article>
                        <article class="lv2-feature-card">
                            <span class="lv2-feature-card__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            </span>
                            <div>
                                <h3 class="lv2-feature-card__title"><?php ee('QR Codes') ?></h3>
                                <p class="lv2-feature-card__desc"><?php ee('Create &amp; customize QR codes.') ?></p>
                            </div>
                        </article>
                        <article class="lv2-feature-card">
                            <span class="lv2-feature-card__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                            </span>
                            <div>
                                <h3 class="lv2-feature-card__title"><?php ee('Custom Domains') ?></h3>
                                <p class="lv2-feature-card__desc"><?php ee('Use your own domain.') ?></p>
                            </div>
                        </article>
                    </div>
                </div>

                <!-- Decorative analytics dashboard mockup -->
                <div class="lv2-analytics lv2-fade-up lv2-fade-up--d2" aria-hidden="true">
                    <div class="lv2-analytics__row">
                        <div class="lv2-analytics__tile">
                            <div class="lv2-analytics__tile-label"><?php ee('Total Clicks') ?></div>
                            <div class="lv2-analytics__tile-value">24,543 <span class="lv2-analytics__delta">+12.5%</span></div>
                        </div>
                        <div class="lv2-analytics__tile">
                            <div class="lv2-analytics__tile-label"><?php ee('Top Source') ?></div>
                            <div class="lv2-analytics__tile-source">
                                <img src="<?php echo assets('images/zapier.svg') ?>" alt="" onerror="this.style.display='none'">
                                Instagram
                            </div>
                        </div>
                    </div>
                    <div class="lv2-analytics__chart">
                        <div class="lv2-analytics__chart-tooltip"><strong>May 12</strong><br>4,753 <?php ee('Clicks') ?></div>
                        <svg viewBox="0 0 400 110" preserveAspectRatio="none">
                            <defs>
                                <linearGradient id="lv2chartg" x1="0" x2="1" y1="0" y2="0">
                                    <stop offset="0%" stop-color="#4F46E5"/>
                                    <stop offset="100%" stop-color="#06B6D4"/>
                                </linearGradient>
                                <linearGradient id="lv2chartf" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%" stop-color="#4F46E5" stop-opacity=".22"/>
                                    <stop offset="100%" stop-color="#4F46E5" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                            <path d="M0,82 L50,76 L100,68 L150,46 L200,40 L250,28 L300,22 L350,32 L400,16 L400,110 L0,110 Z" fill="url(#lv2chartf)"/>
                            <path d="M0,82 L50,76 L100,68 L150,46 L200,40 L250,28 L300,22 L350,32 L400,16" fill="none" stroke="url(#lv2chartg)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="200" cy="40" r="6" fill="#fff" stroke="#4F46E5" stroke-width="2.5"/>
                        </svg>
                        <div class="lv2-analytics__chart-x">
                            <span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span><span>Jun</span><span>Jul</span>
                        </div>
                    </div>
                    <div class="lv2-analytics__row">
                        <div class="lv2-analytics__tile">
                            <div class="lv2-analytics__tile-label"><?php ee('Unique Visitors') ?></div>
                            <div class="lv2-analytics__tile-value">8,921 <span class="lv2-analytics__delta">+8.3%</span></div>
                        </div>
                        <div class="lv2-analytics__tile">
                            <div class="lv2-analytics__tile-label"><?php ee('Avg. Click Rate') ?></div>
                            <div class="lv2-analytics__tile-value">3.6% <span class="lv2-analytics__delta">+4.1%</span></div>
                        </div>
                    </div>
                    <div class="lv2-analytics__see-more"><?php ee('See detailed analytics') ?> &rarr;</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         CAPABILITIES — 3-up
         ============================================================ -->
    <section class="lv2-section" aria-labelledby="lv2-cap-title">
        <div class="lv2-container">
            <div style="text-align:center; max-width: 640px; margin: 0 auto var(--lv2-space-9);">
                <span class="lv2-section-eyebrow"><?php ee('Why teams choose us') ?></span>
                <h2 id="lv2-cap-title" class="lv2-section-title"><?php ee('Built for serious marketers') ?></h2>
                <p class="lv2-section-lead" style="margin-left:auto; margin-right:auto;"><?php ee('From smart targeting to deep analytics — every tool you need, ready out of the box.') ?></p>
            </div>

            <div class="lv2-cap-grid">
                <article class="lv2-cap lv2-fade-up">
                    <span class="lv2-cap__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                    </span>
                    <h3 class="lv2-cap__title"><?php ee('Smart Targeting') ?></h3>
                    <p class="lv2-cap__desc"><?php ee('Route every visitor to the right page by country, device, or language. Add retargeting pixels and bring them back when it matters.') ?></p>
                </article>

                <article class="lv2-cap lv2-cap--c1 lv2-fade-up lv2-fade-up--d1">
                    <span class="lv2-cap__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    </span>
                    <h3 class="lv2-cap__title"><?php ee('In-Depth Analytics') ?></h3>
                    <p class="lv2-cap__desc"><?php ee("Real-time clicks broken down by country, city, OS, browser, referrer and time — so you optimize what's working.") ?></p>
                </article>

                <article class="lv2-cap lv2-cap--c2 lv2-fade-up lv2-fade-up--d2">
                    <span class="lv2-cap__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    </span>
                    <h3 class="lv2-cap__title"><?php ee('Digital Experience') ?></h3>
                    <p class="lv2-cap__desc"><?php ee('Splash pages, branded overlays, password gates and click caps — premium polish without engineering effort.') ?></p>
                </article>
            </div>
        </div>
    </section>

    <!-- ============================================================
         TESTIMONIALS (conditional — preserved)
         ============================================================ -->
    <?php if($testimonials = config('testimonials')): ?>
    <section class="lv2-section" aria-labelledby="lv2-test-title">
        <div class="lv2-container">
            <div style="text-align:center; max-width: 640px; margin: 0 auto var(--lv2-space-9);">
                <span class="lv2-section-eyebrow"><?php ee('Loved by teams') ?></span>
                <h2 id="lv2-test-title" class="lv2-section-title"><?php ee('What our customers say') ?></h2>
            </div>

            <div class="lv2-cap-grid">
                <?php foreach($testimonials as $testimonial): ?>
                    <article class="lv2-cap">
                        <div style="display:flex; gap:2px; color: var(--lv2-warning); margin-bottom: 12px;">
                            <?php for($i=0;$i<5;$i++): ?>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <?php endfor ?>
                        </div>
                        <p style="font-size: var(--lv2-text-md); color: var(--lv2-text-2); line-height: 1.6; margin-bottom: var(--lv2-space-5);"><?php echo $testimonial->testimonial ?></p>
                        <div style="display:flex; align-items:center; gap:12px;">
                            <?php
                            if(isset($testimonial->avatar) && file_exists(appConfig('app')['storage']['avatar']['path'].'/'.$testimonial->avatar)) {
                                $testimonial->avatar = uploads($testimonial->avatar, 'avatar');
                            } else if($testimonial->email) {
                                $testimonial->avatar = 'https://www.gravatar.com/avatar/'.md5(trim($testimonial->email)).'?s=64&d=identicon';
                            }
                            ?>
                            <?php if(!empty($testimonial->avatar)): ?>
                                <img src="<?php echo $testimonial->avatar ?>" alt="<?php echo $testimonial->name ?>"
                                     style="width:44px; height:44px; border-radius:50%; object-fit:cover;"
                                     loading="lazy">
                            <?php endif ?>
                            <div>
                                <p style="font-size: var(--lv2-text-sm); font-weight: 600; margin: 0;"><?php echo $testimonial->name ?></p>
                                <?php if($testimonial->job): ?>
                                    <p style="font-size: var(--lv2-text-xs); color: var(--lv2-text-3); margin: 0;"><?php echo $testimonial->job ?></p>
                                <?php endif ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach ?>
            </div>
        </div>
    </section>
    <?php endif ?>

    <!-- ============================================================
         CTA BANNER
         ============================================================ -->
    <?php if(!\Core\Auth::logged() && config("user") && !config("private") && !config("maintenance")): ?>
    <section class="lv2-section">
        <div class="lv2-container">
            <div class="lv2-cta-banner lv2-fade-up">
                <div>
                    <h2 class="lv2-cta-banner__title"><?php ee('Take control of your links') ?></h2>
                    <p class="lv2-cta-banner__lead"><?php ee("You're one click away from taking control of all your links and instantly getting better results.") ?></p>
                </div>
                <div class="lv2-cta-banner__action">
                    <a href="<?php echo route('register') ?>" class="lv2-btn lv2-btn--white lv2-btn--lg">
                        <?php ee('Get Started for Free') ?>
                    </a>
                </div>
            </div>
        </div>
    </section>
    <?php endif ?>

<?php endif ?>
