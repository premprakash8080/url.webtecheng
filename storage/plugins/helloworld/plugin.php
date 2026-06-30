<?php
/**
 * =======================================================================================
 *                           GemFramework (c) GemPixel                                     
 * ---------------------------------------------------------------------------------------
 *  This software is packaged with an exclusive framework as such distribution
 *  or modification of this framework is not allowed before prior consent from
 *  GemPixel. If you find that this framework is packaged in a software not distributed 
 *  by GemPixel or authorized parties, you must not use this software and contact gempixel
 *  at https://gempixel.com/contact to inform them of this misuse.
 * =======================================================================================
 *
 * @package GemPixel\Premium-URL-Shortener
 * @author GemPixel (https://gempixel.com) 
 * @license https://gempixel.com/licenses
 * @link https://gempixel.com  
 */

use Core\Plugin;
use Core\View;


/**
 * Sample GET routing with a callback function that outputs a page with the admin layout
 * the route is named "helloworld" and it uses the admin Auth middleware to lock the page to admins only.
 */

Gem::get('/admin/plugins/sample', function(){
    
    // Sets the page title
    View::set('title', 'Sample Plugin Hello World!');

    $variable = 'Hello World';

    // Generates a view using the template engine
    return View::with(function() use ($variable){
        
        // Output the page content this can be a file as well using include(PATHTOYOURFILE)
        return print('<div class="card card-body"><strong>'.$variable.'.</strong><br><br>This is a sample plugin. How exciting is that?</div>'); 
        
        // Extend the admin layout: storage/themes/default/admin/layouts/main.php
    })->extend('admin.layouts.main');
    
    // Name the route and assign the admin auth middleware to protect the page
    // To lock a page for users only, use Auth
})->name('helloworld')->middleware('Auth@admin');

// Add the route to the admin menu
Plugin::register('adminmenu', function(){
    return print(adminmenu(['title' => 'Hello World', 'route' => route('helloworld')]));
});