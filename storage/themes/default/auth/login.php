<?php
/**
 * Login — Landing v2 redesign
 * Wrapped by layouts/auth.php which renders inside .landing-v2.
 *
 * PRESERVED:
 *   - <form method="post" action="route('login.auth')">
 *   - name="email" id="input-email", name="password" id="input-password"
 *   - name="rememberme" checkbox
 *   - csrf() token output
 *   - \Helpers\Captcha::display('login')
 *   - Social login routes: login.facebook, login.google, login.twitter
 *   - Config gates: config('user'), config('private'), config('maintenance'),
 *     config('fb_connect'), config('gl_connect'), config('tw_connect')
 */
?>
<section class="lv2-auth">

    <a href="<?php echo route('home') ?>"
       class="lv2-auth__back"
       data-toggle="tooltip"
       data-placement="right"
       title="<?php echo e('Go back') ?>"
       aria-label="<?php echo e('Go back to home') ?>">
        <i data-feather="arrow-left" aria-hidden="true"></i>
    </a>

    <div class="lv2-auth__card" role="region" aria-labelledby="lv2-auth-title">
        <a href="<?php echo route('home') ?>" class="lv2-auth__brand">
            <?php if(config('logo')): ?>
                <img src="<?php echo uploads(config('logo')) ?>" alt="<?php echo config('sitename') ?>" style="max-height:32px; width:auto;">
            <?php else: ?>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="url(#lv2authlogo)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <defs><linearGradient id="lv2authlogo" x1="0" x2="1" y1="0" y2="1"><stop offset="0%" stop-color="#4F46E5"/><stop offset="100%" stop-color="#7C3AED"/></linearGradient></defs>
                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                </svg>
                <?php echo config('sitename') ?>
            <?php endif ?>
        </a>

        <h1 id="lv2-auth-title" class="lv2-auth__title"><?php ee('Welcome back') ?></h1>
        <p class="lv2-auth__lead"><?php ee('Log in to your account to continue.') ?></p>

        <div class="lv2-flash"><?php message() ?></div>

        <form method="post" action="<?php echo route('login.auth') ?>" autocomplete="on">
            <div class="lv2-form-group">
                <label for="input-email" class="lv2-form-label"><?php ee('Email or username') ?></label>
                <div class="lv2-form-row">
                    <span class="lv2-form-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </span>
                    <input type="text"
                           class="lv2-form-control"
                           id="input-email"
                           name="email"
                           placeholder="<?php echo e('you@example.com') ?>"
                           autocomplete="username"
                           required>
                </div>
            </div>

            <div class="lv2-form-group">
                <label for="input-password" class="lv2-form-label"><?php ee('Password') ?></label>
                <div class="lv2-form-row">
                    <span class="lv2-form-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <input type="password"
                           class="lv2-form-control"
                           id="input-password"
                           name="password"
                           placeholder="<?php echo e('Enter your password') ?>"
                           autocomplete="current-password"
                           required>
                </div>
                <div class="lv2-form-meta">
                    <span></span>
                    <a href="<?php echo route('forgot') ?>"><?php ee('Forgot password?') ?></a>
                </div>
            </div>

            <label class="lv2-form-check">
                <input type="checkbox" id="rememberme" name="rememberme">
                <?php ee('Remember me for 14 days') ?>
            </label>

            <?php echo \Helpers\Captcha::display('login') ?>
            <?php echo csrf() ?>

            <button type="submit" class="lv2-btn lv2-btn--primary" style="width:100%;">
                <?php ee('Log in') ?>
            </button>
        </form>

        <?php if(config("user") && !config("private") && !config("maintenance")): ?>
            <?php if(config('fb_connect') || config('tw_connect') || config('gl_connect')): ?>
                <div class="lv2-auth__divider"><span><?php ee('or continue with') ?></span></div>

                <?php if(config('gl_connect')): ?>
                    <a href="<?php echo route('login.google') ?>" class="lv2-social-btn">
                        <img src="<?php echo assets('images/google.svg') ?>" alt="" aria-hidden="true">
                        <?php echo e("Sign in with") ?> Google
                    </a>
                <?php endif ?>
                <?php if(config('fb_connect')): ?>
                    <a href="<?php echo route('login.facebook') ?>" class="lv2-social-btn">
                        <img src="<?php echo assets('images/facebook.svg') ?>" alt="" aria-hidden="true">
                        <?php echo e("Sign in with") ?> Facebook
                    </a>
                <?php endif ?>
                <?php if(config('tw_connect')): ?>
                    <a href="<?php echo route('login.twitter') ?>" class="lv2-social-btn">
                        <img src="<?php echo assets('images/x.svg') ?>" alt="" aria-hidden="true">
                        <?php echo e("Sign in with") ?> X
                    </a>
                <?php endif ?>
            <?php endif ?>

            <p class="lv2-auth__footer-link">
                <?php ee("Don't have an account?") ?>
                <a href="<?php echo route('register') ?>"><?php ee('Create one') ?></a>
            </p>
        <?php endif ?>
    </div>
</section>
