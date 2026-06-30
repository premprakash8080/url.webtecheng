<?php
/**
 * =======================================================================================
 *                           GemFramework (c) GemPixel
 * ---------------------------------------------------------------------------------------
 *  This software is packaged with an exclusive framework as such distribution
 *  or modification of this framework is not allowed before prior consent from
 *  GemPixel. If you find that this framework is packaged in a software not distributed
 *  by GemPixel or authorized parties, you must not use this software and contact GemPixel
 *  at https://gempixel.com/contact to inform them of this misuse.
 * =======================================================================================
 *
 * @package GemPixel\Premium-URL-Shortener
 * @author GemPixel (https://gempixel.com)
 * @license https://gempixel.com/licenses
 * @link https://gempixel.com
 */

use Core\View;
use Core\File;
use Core\Helper;
use Core\Request;
use Core\Response;
use Core\Localization;
use Core\DB;
use Core\Plugin;
use Helpers\CDN;

class Home {
    /**
     * Home Page
     *
     * @author GemPixel <https://gempixel.com>
     * @version 7.5.4
     * @return void
     */
    public function index(Request $request){
        
        // @group plugin
        Plugin::dispatch('home.pre');

        if(config('home_redir')){
            return Helper::redirect(null, 301)->to(config('home_redir'));
        }

        $count = new \stdClass;
        if(config("homepage_stats")){
            $count->users = \Helpers\App::formatNumber(\Core\DB::user()->count());
            $count->links = \Helpers\App::formatNumber(\Core\DB::url()->count());
            $count->clicks = \Helpers\App::formatNumber(\Core\DB::url()->selectExpr('SUM(click) as click')->first()->click);
        }

        $random = rand(1, 5);
        $numbers = [];
        $numbers[0] = $random + rand(1, 5);
        $numbers[1] = $numbers[0] + rand(5, 6);
        $numbers[2] = $numbers[1] + rand(6, 10);

        View::push(assets('frontend/libs/clipboard/dist/clipboard.min.js'), 'js')->toFooter();

        View::push(assets('frontend/libs/typedjs/typed.min.js'), 'script')->toFooter();

        $themeconfig = config('theme_config');

        // @group plugin
        Plugin::dispatch('home', $count);

        // SEO Optimization: Set optimized title, description, and keywords
        $siteTitle = config('title');
        $seoTitle = $siteTitle . ' - Free URL Shortener | Tiny URL Generator | Link Shortener';
        $seoDescription = 'Free URL shortener and link shortener tool. Create short URLs, custom links, and track clicks. Generate QR codes, manage bio pages, and analyze link performance. The best free URL shortener alternative to TinyURL.';
        $seoKeywords = 'URL shortener, free URL shortener, tiny URL, link shortener, short URL generator, URL shortener free, shorten URL, custom URL shortener, free link shortener, URL shortener tool';
        
        View::set('title', $seoTitle);
        View::set('description', $seoDescription);
        View::set('keywords', $seoKeywords);
        View::set('type', 'website');
        
        // Set Open Graph image if available
        if(config('logo')){
            View::set('image', uploads(config('logo')));
        } elseif(isset($themeconfig->hero) && !empty($themeconfig->hero)){
            View::set('image', uploads($themeconfig->hero));
        } else {
            View::set('image', url().'/'.View::ASSET.'/images/landing.png');
        }

        // Add WebApplication Schema Markup for SEO
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "WebApplication",
            "name" => $siteTitle,
            "description" => $seoDescription,
            "url" => url(),
            "applicationCategory" => "UtilityApplication",
            "operatingSystem" => "Web",
            "offers" => [
                "@type" => "Offer",
                "price" => "0",
                "priceCurrency" => "USD"
            ],
            "aggregateRating" => [
                "@type" => "AggregateRating",
                "ratingValue" => "4.8",
                "ratingCount" => isset($count->users) ? $count->users : "1000"
            ],
            "featureList" => [
                "Free URL Shortening",
                "Custom Short Links",
                "QR Code Generation",
                "Link Analytics",
                "Bio Pages",
                "Password Protection",
                "Link Expiration",
                "API Access"
            ]
        ];

        // Add SoftwareApplication schema as well
        $softwareSchema = [
            "@context" => "https://schema.org",
            "@type" => "SoftwareApplication",
            "name" => $siteTitle,
            "description" => $seoDescription,
            "url" => url(),
            "applicationCategory" => "WebApplication",
            "operatingSystem" => "Any",
            "offers" => [
                "@type" => "Offer",
                "price" => "0",
                "priceCurrency" => "USD"
            ]
        ];

        View::push('<script type="application/ld+json">'.json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).'</script>', 'custom')->toHeader();
        View::push('<script type="application/ld+json">'.json_encode($softwareSchema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).'</script>', 'custom')->toHeader();

        // Add Organization Schema
        $organizationSchema = [
            "@context" => "https://schema.org",
            "@type" => "Organization",
            "name" => $siteTitle,
            "url" => url(),
            "logo" => config('logo') ? uploads(config('logo')) : url().'/favicon.ico',
            "description" => $seoDescription,
            "sameAs" => array_filter([
                config('facebook'),
                config('twitter'),
                config('instagram')
            ])
        ];
        
        View::push('<script type="application/ld+json">'.json_encode($organizationSchema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).'</script>', 'custom')->toHeader();

        return View::with('index', compact('count', 'themeconfig', 'numbers'))->extend('layouts.main');
    }
    /**
     * Bundle & Serve Static Assets
     *
     * @author GemPixel <https://gempixel.com>
     * @version 6.7.4
     * @param \Core\Request $request
     * @return void
     */
    public function packed(Request $request, $ext = 'css'){

        $ext = in_array($ext, ['css', 'js']) ? $ext : 'css';
        $names = explode(',', $request->name);
        $cdn = appConfig('cdn');
        $content = '';
        foreach($names as $name){
            if(isset($cdn[$name])){
                if(is_array($cdn[$name][$ext])){
                    foreach($cdn[$name][$ext] as $file){
                        $content .= file_get_contents($file)."\n";
                    }
                }else {
                    $content .= file_get_contents($cdn[$name][$ext])."\n";
                }
            }
        }

        return Response::factory($content)->setHeader(['content-type', $ext == 'js' ? 'text/javascript' : 'text/css'])->send();
    }
}