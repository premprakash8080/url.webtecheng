<?php
/**
 * ===================================================================
 *  Home page — Landing v2 (premium SaaS redesign)
 * -------------------------------------------------------------------
 *  Design goal: Stripe/Linear/Vercel-grade trust signal in the first
 *  second, while preserving every existing app integration.
 *
 *  Preservation contract (DO NOT change without checking JS handlers):
 *    - <form method="post" action="route('shorten')" data-trigger="shorten-form">
 *      with field names {url, custom, pass} and ids {url, custom, pass}.
 *    - Submit/copy buttons inside the form: `.btn-warning.d-none` is the
 *      copy button the JS layer toggles; `.btn-success` is the submit
 *      (kept as a real <button type="submit">).
 *    - #output-result, #qr-result, #text-result — JS shows the shortened
 *      URL + QR after a successful POST.
 *    - Captcha is rendered via \Helpers\Captcha::display('shorten').
 *    - All `route()`, `e()`, `ee()`, `config()`, `themeSettings::config()`,
 *      and conditional sections are preserved 1:1.
 *
 *  Scoping: every visual rule lives under `.landing-v2` (loaded only by
 *  this template via View::push), so dashboard / admin / auth styles
 *  are unaffected.
 * ===================================================================
 */

// Load the premium landing CSS only on this page.
\Core\View::push('<link rel="stylesheet" href="'.assets('frontend/css/landing.css').'?v=1" />', 'custom')->toHeader();

// Preconnect to font CDN to improve LCP for the new Inter typography.
\Core\View::push('<link rel="preconnect" href="https://fonts.googleapis.com" />', 'custom')->toHeader();
\Core\View::push('<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />', 'custom')->toHeader();
\Core\View::push('<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" />', 'custom')->toHeader();

$_hero_img = (isset($themeconfig->hero) && !empty($themeconfig->hero))
    ? uploads($themeconfig->hero)
    : assets("images/landing.png");
?>

<div class="landing-v2">

    <!-- ============================================================
         HERO
         ============================================================ -->
    <section class="lv2-hero" aria-labelledby="lv2-hero-title">
        <div class="lv2-container">
            <div class="lv2-hero__layout">

                <div class="lv2-hero__copy lv2-fade-up">
                    <span class="lv2-eyebrow">
                        <span class="lv2-eyebrow__dot" aria-hidden="true"></span>
                        <?php ee('Free URL shortener &middot; QR &middot; Bio pages') ?>
                    </span>

                    <h1 id="lv2-hero-title" class="lv2-hero__title">
                        <?php ee('Short links that build') ?>
                        <span class="lv2-gradient-text"><?php ee('your brand') ?></span>
                    </h1>

                    <p class="lv2-hero__lead">
                        <?php ee('Shorten any URL in seconds. Add custom aliases, password protection, QR codes, geo &amp; device targeting, and rich analytics — all from one premium dashboard.') ?>
                    </p>

                    <!-- Flash messages preserved (framework renders alert HTML) -->
                    <div class="lv2-flash"><?php message() ?></div>

                    <!-- Shortener form — JS-bound; field names & IDs frozen -->
                    <form method="post"
                          action="<?php echo route('shorten') ?>"
                          data-trigger="shorten-form"
                          aria-label="<?php echo e('Shorten a URL') ?>">

                        <div class="lv2-shortener">
                            <input type="text"
                                   class="form-control lv2-shortener__input"
                                   placeholder="<?php echo e('Paste a long URL — https://example.com/your-long-link') ?>"
                                   name="url"
                                   id="url"
                                   autocomplete="off"
                                   inputmode="url"
                                   spellcheck="false"
                                   required>
                            <div class="lv2-shortener__actions">
                                <!-- Copy button: JS toggles `.d-none` after a successful shorten -->
                                <button class="btn btn-warning d-none lv2-shortener__btn lv2-shortener__btn--copy"
                                        type="button"><?php ee('Copy') ?></button>
                                <button class="btn btn-success lv2-shortener__btn" type="submit">
                                    <?php ee('Shorten') ?>
                                </button>
                            </div>
                        </div>

                        <?php if(!config('pro')): ?>
                            <a href="#advanced"
                               data-toggle="collapse"
                               class="btn btn-xs btn-primary mb-2 lv2-shortener__toggle"
                               aria-expanded="false"
                               aria-controls="advanced">
                                <i data-feather="sliders" aria-hidden="true"></i>
                                <?php ee('Advanced') ?>
                            </a>
                            <div class="collapse" id="advanced">
                                <div class="lv2-shortener__advanced">
                                    <div class="lv2-shortener__field">
                                        <label for="custom"><?php ee('Custom alias') ?></label>
                                        <input type="text"
                                               class="form-control"
                                               name="custom"
                                               id="custom"
                                               placeholder="<?php echo e('your-brand') ?>"
                                               autocomplete="off">
                                    </div>
                                    <div class="lv2-shortener__field">
                                        <label for="pass"><?php ee('Password protection') ?></label>
                                        <input type="text"
                                               class="form-control"
                                               name="pass"
                                               id="pass"
                                               placeholder="<?php echo e('Optional password') ?>"
                                               autocomplete="off">
                                    </div>
                                </div>
                            </div>
                        <?php endif ?>

                        <?php if(!\Core\Auth::logged()) { echo \Helpers\Captcha::display('shorten'); } ?>
                    </form>

                    <!-- Result container — JS targets these IDs, must remain -->
                    <div id="output-result" class="lv2-shorten-result d-none" role="status" aria-live="polite">
                        <div class="row">
                            <div id="qr-result" class="col-md-4 p-2"></div>
                            <div id="text-result" class="col-md-8">
                                <p class="mb-3"><?php ee('Your link has been successfully shortened. Want more customization options?') ?></p>
                                <a href="<?php echo route('register') ?>" class="lv2-btn lv2-btn--primary lv2-btn--sm"><?php ee('Get started') ?></a>
                            </div>
                        </div>
                    </div>

                    <!-- Trust pills -->
                    <ul class="lv2-trust" aria-label="<?php echo e('Why people trust us') ?>">
                        <li class="lv2-trust__item">
                            <span class="lv2-trust__check" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </span>
                            <?php ee('No credit card required') ?>
                        </li>
                        <li class="lv2-trust__item">
                            <span class="lv2-trust__check" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </span>
                            <?php ee('Free forever plan') ?>
                        </li>
                        <li class="lv2-trust__item">
                            <span class="lv2-trust__check" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </span>
                            <?php ee('Setup in seconds') ?>
                        </li>
                    </ul>
                </div>

                <div class="lv2-hero__visual lv2-fade-up lv2-fade-up--d2" aria-hidden="true">
                    <img src="<?php echo $_hero_img ?>"
                         alt="<?php echo config('title') ?>"
                         class="lv2-hero__visual-img"
                         loading="eager"
                         decoding="async"
                         fetchpriority="high">

                    <div class="lv2-float lv2-float--tl">
                        <span class="lv2-float__dot"><i data-feather="zap" aria-hidden="true"></i></span>
                        <div>
                            <div class="lv2-float__label"><?php ee('Click recorded') ?></div>
                            <div class="lv2-float__sub"><?php ee('Real-time analytics') ?></div>
                        </div>
                    </div>
                    <div class="lv2-float lv2-float--br">
                        <span class="lv2-float__dot"><i data-feather="shield" aria-hidden="true"></i></span>
                        <div>
                            <div class="lv2-float__label"><?php ee('Secure &amp; private') ?></div>
                            <div class="lv2-float__sub"><?php ee('Password-protected links') ?></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ============================================================
         USER HISTORY (preserved — only renders when config + no auth)
         ============================================================ -->
    <?php if(config('user_history') && !\Core\Auth::logged() && $urls = \Helpers\App::userHistory()): ?>
        <section class="lv2-section lv2-section--alt" aria-labelledby="lv2-history-title">
            <div class="lv2-container">
                <div class="lv2-grid lv2-grid--2">
                    <div>
                        <h2 id="lv2-history-title" class="lv2-section__title"><?php ee('Your latest links') ?></h2>
                        <p class="lv2-section__lead" style="margin-left:0"><?php ee('Pick up where you left off — we kept your recent links in this browser.') ?></p>
                        <div class="lv2-link-list">
                            <?php foreach($urls as $url): ?>
                                <div class="lv2-link-list__item">
                                    <a class="lv2-link-list__title" href="<?php echo $url['url'] ?>" target="_blank" rel="noopener"><?php echo $url['meta_title'] ?></a>
                                    <a class="lv2-link-list__url" href="<?php echo \Helpers\App::shortRoute($url['domain'], $url['alias'].$url['custom']) ?>"><?php echo \Helpers\App::shortRoute($url['domain'], $url['alias'].$url['custom']) ?></a>
                                </div>
                            <?php endforeach ?>
                        </div>
                        <div style="margin-top: 1.5rem;">
                            <a href="<?php echo route('register') ?>" class="lv2-btn lv2-btn--primary"><?php ee('Unlock advanced features') ?> &rarr;</a>
                        </div>
                    </div>
                    <div>
                        <?php \Helpers\App::ads('resp') ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif ?>

    <!-- ============================================================
         PUBLIC LATEST LINKS (preserved — conditional)
         ============================================================ -->
    <?php if(config('public_dir')): ?>
        <section class="lv2-section lv2-section--alt" aria-labelledby="lv2-public-title">
            <div class="lv2-container">
                <div class="lv2-grid lv2-grid--2">
                    <div>
                        <h2 id="lv2-public-title" class="lv2-section__title"><?php ee('Latest links') ?></h2>
                        <p class="lv2-section__lead" style="margin-left:0"><?php ee('A live look at what the community is sharing.') ?></p>
                        <div class="lv2-link-list">
                            <?php foreach(\Core\DB::url()->where('public', '1')->orderByDesc('date')->limit(15)->findArray() as $url): ?>
                                <div class="lv2-link-list__item">
                                    <a class="lv2-link-list__title" href="<?php echo $url['url'] ?>" target="_blank" rel="noopener"><?php echo $url['meta_title'] ?></a>
                                    <a class="lv2-link-list__url" href="<?php echo \Helpers\App::shortRoute($url['domain'], $url['alias'].$url['custom']) ?>"><?php echo \Helpers\App::shortRoute($url['domain'], $url['alias'].$url['custom']) ?></a>
                                </div>
                            <?php endforeach ?>
                        </div>
                    </div>
                    <div>
                        <?php \Helpers\App::ads('resp') ?>
                    </div>
                </div>
            </div>
        </section>
    <?php else: ?>

        <!-- ============================================================
             FEATURE BENTO
             ============================================================ -->
        <section class="lv2-section" aria-labelledby="lv2-features-title">
            <div class="lv2-container">
                <div class="lv2-section__head lv2-fade-up">
                    <span class="lv2-eyebrow">
                        <span class="lv2-eyebrow__dot" aria-hidden="true"></span>
                        <?php ee('Why teams choose us') ?>
                    </span>
                    <h2 id="lv2-features-title" class="lv2-section__title">
                        <?php ee('Everything you need to') ?> <span class="lv2-gradient-text"><?php ee('grow') ?></span>
                    </h2>
                    <p class="lv2-section__lead">
                        <?php ee("From smart targeting to deep analytics — the toolkit modern marketers, creators and developers reach for first.") ?>
                    </p>
                </div>

                <div class="lv2-feature-bento">
                    <article class="lv2-feature lv2-fade-up lv2-fade-up--d1">
                        <div class="lv2-feature__icon" aria-hidden="true"><i data-feather="target"></i></div>
                        <h3 class="lv2-feature__title"><?php ee('Smart Targeting') ?></h3>
                        <p class="lv2-feature__desc">
                            <?php ee('Route every visitor to the right page by country, device, or language. Add retargeting pixels and bring them back when it matters.') ?>
                        </p>
                    </article>

                    <article class="lv2-feature lv2-feature--alt-1 lv2-fade-up lv2-fade-up--d2">
                        <div class="lv2-feature__icon" aria-hidden="true"><i data-feather="bar-chart-2"></i></div>
                        <h3 class="lv2-feature__title"><?php ee('In-Depth Analytics') ?></h3>
                        <p class="lv2-feature__desc">
                            <?php ee("Real-time clicks broken down by country, city, OS, browser, referrer, and time — so you can optimize what's working.") ?>
                        </p>
                    </article>

                    <article class="lv2-feature lv2-feature--alt-2 lv2-fade-up lv2-fade-up--d3">
                        <div class="lv2-feature__icon" aria-hidden="true"><i data-feather="star"></i></div>
                        <h3 class="lv2-feature__title"><?php ee('Digital Experience') ?></h3>
                        <p class="lv2-feature__desc">
                            <?php ee('Splash pages, branded overlays, password gates and click caps — premium polish without engineering effort.') ?>
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <!-- ============================================================
             SHOWCASE — analytics
             ============================================================ -->
        <section class="lv2-section lv2-section--alt">
            <div class="lv2-container">
                <div class="lv2-showcase lv2-showcase--reverse">
                    <div class="lv2-showcase__copy lv2-fade-up">
                        <span class="lv2-showcase__eyebrow"><i data-feather="trending-up" aria-hidden="true"></i> <?php ee('Analytics') ?></span>
                        <h2 class="lv2-showcase__title"><?php ee('Advanced link analytics &amp; tracking') ?></h2>
                        <p class="lv2-showcase__lead">
                            <?php ee('Track every click with geo, device, browser, referrer and timestamp. Understand who clicks, when and why — then double down on what converts.') ?>
                        </p>
                        <ul class="lv2-checklist">
                            <li><span class="lv2-checklist__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span><?php ee('Redirection Tools') ?></li>
                            <li><span class="lv2-checklist__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span><?php ee('Powerful Statistics') ?></li>
                            <li><span class="lv2-checklist__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span><?php ee('Beautiful Profiles') ?></li>
                        </ul>
                    </div>
                    <div class="lv2-showcase__visual lv2-fade-up lv2-fade-up--d1">
                        <img src="<?php echo assets('images/profiles.png') ?>"
                             alt="<?php echo e('Powerful link analytics dashboard') ?>"
                             loading="lazy" decoding="async">
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================
             SHOWCASE — link management
             ============================================================ -->
        <section class="lv2-section">
            <div class="lv2-container">
                <div class="lv2-showcase">
                    <div class="lv2-showcase__copy lv2-fade-up">
                        <span class="lv2-showcase__eyebrow"><i data-feather="lock" aria-hidden="true"></i> <?php ee('Management') ?></span>
                        <h2 class="lv2-showcase__title"><?php ee('Professional link management') ?></h2>
                        <p class="lv2-showcase__lead">
                            <?php ee('Password gates, expiration dates, custom aliases, branded domains and bulk operations — enterprise control without the enterprise complexity.') ?>
                        </p>
                        <ul class="lv2-checklist">
                            <li><span class="lv2-checklist__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span><?php ee('Link Management') ?></li>
                            <li><span class="lv2-checklist__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span><?php ee('Privacy Control') ?></li>
                            <li><span class="lv2-checklist__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span><?php ee('Powerful Dashboard') ?></li>
                        </ul>
                    </div>
                    <div class="lv2-showcase__visual lv2-fade-up lv2-fade-up--d1">
                        <img src="<?php echo assets('images/filters.png') ?>"
                             alt="<?php echo e('Filter, password-protect and expire your links') ?>"
                             loading="lazy" decoding="async">
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================
             SHOWCASE — QR
             ============================================================ -->
        <section class="lv2-section lv2-section--alt">
            <div class="lv2-container">
                <div class="lv2-showcase lv2-showcase--reverse">
                    <div class="lv2-showcase__copy lv2-fade-up">
                        <span class="lv2-showcase__eyebrow"><i data-feather="grid" aria-hidden="true"></i> <?php ee('QR Codes') ?></span>
                        <h2 class="lv2-showcase__title"><?php ee('Free QR code generator') ?></h2>
                        <p class="lv2-showcase__lead">
                            <?php ee('Branded, customisable QR codes for any URL. Custom colors, logos, frames and shapes — perfect for print, packaging and campaigns.') ?>
                        </p>
                        <a href="<?php echo route('register') ?>" class="lv2-btn lv2-btn--primary">
                            <?php ee('Get started') ?>
                            <svg class="lv2-btn__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </a>
                    </div>
                    <div class="lv2-showcase__visual lv2-fade-up lv2-fade-up--d1">
                        <img src="<?php echo assets('images/qrcodes.png') ?>"
                             alt="<?php echo e('Custom-designed QR codes') ?>"
                             loading="lazy" decoding="async">
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================
             ACTIVITY WIDGET — live clicks visual
             ============================================================ -->
        <section class="lv2-section">
            <div class="lv2-container">
                <div class="lv2-activity">
                    <div class="lv2-activity-cards" aria-label="<?php echo e('Live link activity') ?>">
                        <div class="lv2-activity-card">
                            <img class="lv2-activity-card__flag" src="<?php echo assets('images/flags/us.svg') ?>" alt="<?php echo e('New York, United States') ?>" loading="lazy">
                            <div class="lv2-activity-card__body">
                                <p class="lv2-activity-card__title"><?php ee('Someone visited your link') ?></p>
                                <p class="lv2-activity-card__meta"><?php ee('New York, United States') ?></p>
                            </div>
                            <span class="lv2-activity-card__pill"><?php ee('{d} minutes ago', null, ['d' => 2]) ?></span>
                        </div>
                        <div class="lv2-activity-card">
                            <img class="lv2-activity-card__flag" src="<?php echo assets('images/flags/fr.svg') ?>" alt="<?php echo e('Paris, France') ?>" loading="lazy">
                            <div class="lv2-activity-card__body">
                                <p class="lv2-activity-card__title"><?php ee('Someone visited your link') ?></p>
                                <p class="lv2-activity-card__meta"><?php ee('Paris, France') ?></p>
                            </div>
                            <span class="lv2-activity-card__pill"><?php ee('{d} minutes ago', null, ['d' => 5]) ?></span>
                        </div>
                        <div class="lv2-activity-card">
                            <img class="lv2-activity-card__flag" src="<?php echo assets('images/flags/gb.svg') ?>" alt="<?php echo e('London, United Kingdom') ?>" loading="lazy">
                            <div class="lv2-activity-card__body">
                                <p class="lv2-activity-card__title"><?php ee('Someone visited your link') ?></p>
                                <p class="lv2-activity-card__meta"><?php ee('London, United Kingdom') ?></p>
                            </div>
                            <span class="lv2-activity-card__pill"><?php ee('{d} minutes ago', null, ['d' => 8]) ?></span>
                        </div>
                    </div>
                    <div class="lv2-fade-up lv2-fade-up--d2">
                        <span class="lv2-showcase__eyebrow"><i data-feather="activity" aria-hidden="true"></i> <?php ee('Marketing Strategy') ?></span>
                        <h2 class="lv2-showcase__title"><?php ee('Optimize your marketing strategy') ?></h2>
                        <p class="lv2-showcase__lead">
                            <?php ee('Understand your customers to lift conversion. Track clicks, countries, referrers and devices — the data lives in one clean dashboard.') ?>
                        </p>
                        <a href="<?php echo route('register') ?>" class="lv2-btn lv2-btn--primary">
                            <?php ee('Get started') ?>
                            <svg class="lv2-btn__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================
             CAPABILITIES — 6-up grid (formerly "More features than asked for")
             ============================================================ -->
        <section class="lv2-section lv2-section--alt" aria-labelledby="lv2-cap-title">
            <div class="lv2-container">
                <div class="lv2-section__head lv2-fade-up">
                    <span class="lv2-eyebrow">
                        <span class="lv2-eyebrow__dot" aria-hidden="true"></span>
                        <?php ee('Capabilities') ?>
                    </span>
                    <h2 id="lv2-cap-title" class="lv2-section__title"><?php ee('More than you asked for') ?></h2>
                    <p class="lv2-section__lead"><?php ee('Every premium link feature, ready out of the box.') ?></p>
                </div>

                <div class="lv2-grid lv2-grid--3">
                    <article class="lv2-card lv2-card--hover lv2-card--tint-6">
                        <div class="lv2-card__icon" aria-hidden="true"><i data-feather="loader"></i></div>
                        <h3 class="lv2-card__title"><?php ee('Custom Landing Page') ?></h3>
                        <p class="lv2-card__body"><?php ee('Create a custom landing page to promote your product or service on forefront and engage the user in your marketing campaign.') ?></p>
                    </article>
                    <article class="lv2-card lv2-card--hover lv2-card--tint-1">
                        <div class="lv2-card__icon" aria-hidden="true"><i data-feather="layers"></i></div>
                        <h3 class="lv2-card__title"><?php ee('CTA Overlays') ?></h3>
                        <p class="lv2-card__body"><?php ee('Use our overlay tool to display unobtrusive notifications, polls or even a contact on the target website. Great for campaigns.') ?></p>
                    </article>
                    <article class="lv2-card lv2-card--hover lv2-card--tint-3">
                        <div class="lv2-card__icon" aria-hidden="true"><i data-feather="compass"></i></div>
                        <h3 class="lv2-card__title"><?php ee('Event Tracking') ?></h3>
                        <p class="lv2-card__body"><?php ee('Add your custom pixel from providers such as Facebook and track events right when they are happening.') ?></p>
                    </article>
                    <article class="lv2-card lv2-card--hover lv2-card--tint-5">
                        <div class="lv2-card__icon" aria-hidden="true"><i data-feather="users"></i></div>
                        <h3 class="lv2-card__title"><?php ee('Team Management') ?></h3>
                        <p class="lv2-card__body"><?php ee('Invite your team members and assign them specific privileges to manage links, bundles, pages and other features.') ?></p>
                    </article>
                    <article class="lv2-card lv2-card--hover lv2-card--tint-4">
                        <div class="lv2-card__icon" aria-hidden="true"><i data-feather="globe"></i></div>
                        <h3 class="lv2-card__title"><?php ee('Branded Domain Names') ?></h3>
                        <p class="lv2-card__body"><?php ee("Easily add your own domain name for short your links and take control of your brand name and your users' trust.") ?></p>
                    </article>
                    <article class="lv2-card lv2-card--hover lv2-card--tint-2">
                        <div class="lv2-card__icon" aria-hidden="true"><i data-feather="terminal"></i></div>
                        <h3 class="lv2-card__title"><?php ee('Robust API') ?></h3>
                        <p class="lv2-card__body"><?php ee('Use our powerful API to build custom applications or extend your own application with our powerful tools.') ?></p>
                    </article>
                </div>
            </div>
        </section>

        <!-- ============================================================
             INTEGRATIONS GRID
             ============================================================ -->
        <section class="lv2-section" aria-labelledby="lv2-int-title">
            <div class="lv2-container">
                <div class="lv2-section__head lv2-fade-up">
                    <span class="lv2-eyebrow">
                        <span class="lv2-eyebrow__dot" aria-hidden="true"></span>
                        <?php ee('Integrations') ?>
                    </span>
                    <h2 id="lv2-int-title" class="lv2-section__title"><?php ee('Plays nicely with your stack') ?></h2>
                    <p class="lv2-section__lead"><?php ee('Connect with popular tools and boost your productivity.') ?></p>
                </div>

                <?php
                $_integrations = [
                    ['wp.svg', 'WordPress'],
                    ['slack.svg', 'Slack'],
                    ['shortcuts.svg', 'Shortcuts'],
                    ['gtm.svg', 'Google Tag Manager'],
                    ['facebook.svg', 'Facebook'],
                    ['zapier.svg', 'Zapier'],
                    ['bing.svg', 'Bing'],
                    ['twitter.svg', 'Twitter'],
                    ['snapchat.svg', 'Snapchat'],
                    ['reddit.svg', 'Reddit'],
                    ['ga.svg', 'Google Analytics'],
                    ['linkedin.svg', 'LinkedIn'],
                    ['pinterest.svg', 'Pinterest'],
                    ['quora.svg', 'Quora'],
                    ['tiktok.svg', 'TikTok'],
                    ['aroll.svg', 'Adroll'],
                ];
                ?>
                <div class="lv2-integrations">
                    <?php foreach($_integrations as $_int): ?>
                        <div class="lv2-integration">
                            <img src="<?php echo assets('images/'.$_int[0]) ?>" alt="<?php echo $_int[1] ?>" loading="lazy" decoding="async">
                            <p class="lv2-integration__name"><?php echo $_int[1] ?></p>
                        </div>
                    <?php endforeach ?>
                </div>

                <div style="text-align:center; margin-top: 2.5rem;">
                    <a href="<?php echo route('register') ?>" class="lv2-btn lv2-btn--ghost"><?php ee('See all integrations') ?> &rarr;</a>
                </div>
            </div>
        </section>

        <!-- ============================================================
             TESTIMONIALS (conditional — preserved)
             ============================================================ -->
        <?php if($testimonials = config('testimonials')): ?>
        <section class="lv2-section lv2-section--alt" aria-labelledby="lv2-testimonials-title">
            <div class="lv2-container">
                <div class="lv2-section__head lv2-fade-up">
                    <span class="lv2-eyebrow">
                        <span class="lv2-eyebrow__dot" aria-hidden="true"></span>
                        <?php ee('Loved by teams') ?>
                    </span>
                    <h2 id="lv2-testimonials-title" class="lv2-section__title"><?php ee('What our customers say about us') ?></h2>
                </div>
                <div class="lv2-testimonials">
                    <?php foreach($testimonials as $testimonial): ?>
                        <article class="lv2-testimonial">
                            <div class="lv2-testimonial__stars" aria-label="<?php echo e('5 out of 5 stars') ?>">
                                <?php for($i=0; $i<5; $i++): ?>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                <?php endfor ?>
                            </div>
                            <p class="lv2-testimonial__quote"><?php echo $testimonial->testimonial ?></p>
                            <div class="lv2-testimonial__author">
                                <?php
                                if(isset($testimonial->avatar) && file_exists(appConfig('app')['storage']['avatar']['path'].'/'.$testimonial->avatar)) {
                                    $testimonial->avatar = uploads($testimonial->avatar, 'avatar');
                                } else if($testimonial->email) {
                                    $testimonial->avatar = 'https://www.gravatar.com/avatar/'.md5(trim($testimonial->email)).'?s=64&d=identicon';
                                }
                                ?>
                                <?php if(!empty($testimonial->avatar)): ?>
                                    <img class="lv2-testimonial__avatar" src="<?php echo $testimonial->avatar ?>" alt="<?php echo $testimonial->name ?>" loading="lazy">
                                <?php endif ?>
                                <div>
                                    <p class="lv2-testimonial__name"><?php echo $testimonial->name ?></p>
                                    <?php if($testimonial->job): ?>
                                        <p class="lv2-testimonial__role"><?php echo $testimonial->job ?></p>
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
             STATS (conditional — preserved)
             ============================================================ -->
        <?php if (config("homepage_stats")): ?>
        <section class="lv2-section" aria-labelledby="lv2-stats-title">
            <div class="lv2-container">
                <div class="lv2-section__head lv2-fade-up">
                    <span class="lv2-eyebrow">
                        <span class="lv2-eyebrow__dot" aria-hidden="true"></span>
                        <?php ee('Trusted at scale') ?>
                    </span>
                    <h2 id="lv2-stats-title" class="lv2-section__title"><?php ee('Numbers that speak for themselves') ?></h2>
                </div>
                <div class="lv2-stats">
                    <div class="lv2-stat lv2-fade-up">
                        <div class="lv2-stat__label"><?php ee('Powering') ?></div>
                        <div class="lv2-stat__value"><span class="counter"><?php echo $count->links ?></span>+</div>
                        <p class="lv2-stat__caption"><?php ee('Links') ?></p>
                    </div>
                    <div class="lv2-stat lv2-fade-up lv2-fade-up--d1">
                        <div class="lv2-stat__label"><?php ee('Serving') ?></div>
                        <div class="lv2-stat__value"><span class="counter"><?php echo $count->clicks ?></span>+</div>
                        <p class="lv2-stat__caption"><?php ee('Clicks') ?></p>
                    </div>
                    <div class="lv2-stat lv2-fade-up lv2-fade-up--d2">
                        <div class="lv2-stat__label"><?php ee('Trusted by') ?></div>
                        <div class="lv2-stat__value"><span class="counter"><?php echo $count->users ?></span>+</div>
                        <p class="lv2-stat__caption"><?php ee('Happy Customers') ?></p>
                    </div>
                </div>
            </div>
        </section>
        <?php endif ?>

        <!-- ============================================================
             FINAL CTA
             ============================================================ -->
        <?php if(!\Core\Auth::logged() && config("user") && !config("private") && !config("maintenance")): ?>
        <section class="lv2-section">
            <div class="lv2-container">
                <div class="lv2-cta-banner lv2-fade-up">
                    <h2 class="lv2-cta-banner__title"><?php ee('Ready to start shortening?') ?></h2>
                    <p class="lv2-cta-banner__lead">
                        <?php ee('Join thousands of marketers, creators and developers using our platform every day. Free forever — no credit card required.') ?>
                    </p>
                    <div class="lv2-cta-banner__actions">
                        <a href="<?php echo route('register') ?>" class="lv2-btn lv2-btn--primary lv2-btn--lg">
                            <?php ee('Create free account') ?>
                            <svg class="lv2-btn__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </a>
                        <?php if(config('pro')): ?>
                            <a href="<?php echo route('pricing') ?>" class="lv2-btn lv2-btn--ghost lv2-btn--lg"><?php ee('Compare plans') ?></a>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </section>
        <?php endif ?>

    <?php endif ?>

</div>
