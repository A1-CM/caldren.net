<?php

use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\CaldrenController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'site.home')->name('home');
Route::view('/product', 'site.product')->name('product');
Route::redirect('/how-it-works', '/#how-it-works', 301)->name('how');
Route::redirect('/pricing', '/#pricing', 301)->name('pricing');
Route::redirect('/contact', '/#contact', 301)->name('contact');
Route::redirect('/privacy', '/#privacy', 301)->name('privacy');
Route::redirect('/terms', '/#terms', 301)->name('terms');
Route::post('/contact', [CaldrenController::class, 'contact'])->middleware('throttle:5,1')->name('contact.store');
Route::post('/contact/campus-sales', [CaldrenController::class, 'campusSalesInquiry'])->middleware('throttle:5,1')->name('contact.sales.store');
Route::middleware('guest')->group(function () {
    Route::post('/oauth/start', [OAuthController::class, 'start'])->middleware('throttle:5,1')->name('oauth.start');
    Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback'])->whereIn('provider', ['google', 'github'])->name('oauth.callback');
    Route::get('/oauth/complete', [OAuthController::class, 'complete'])->name('oauth.complete');
    Route::post('/oauth/complete', [OAuthController::class, 'finishRegistration'])->middleware('throttle:5,1')->name('oauth.finish');
    Route::get('/register', [AccountController::class, 'register'])->name('register');
    Route::post('/register', [AccountController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/login', [AccountController::class, 'login'])->name('login');
    Route::post('/login', [AccountController::class, 'authenticate'])->middleware('throttle:5,1')->name('login.store');
});
Route::get('/invitation/{token}', [AccountController::class, 'acceptInvitation'])->name('invitation.accept');
Route::get('/forgot-password', [PasswordController::class, 'requestForm'])->middleware('guest')->name('password.request');
Route::post('/forgot-password', [PasswordController::class, 'sendLink'])->middleware(['guest', 'throttle:5,1'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordController::class, 'resetForm'])->middleware('guest')->name('password.reset');
Route::post('/reset-password', [PasswordController::class, 'reset'])->middleware(['guest', 'throttle:5,1'])->name('password.update');
Route::post('/logout', [AccountController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware('auth')->group(function () {
    Route::post('/dashboard/organization', [CaldrenController::class, 'switchOrganization'])->name('organization.switch');
    Route::get('/dashboard', [CaldrenController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard/buildings', [CaldrenController::class, 'buildings'])->name('buildings.index');
    Route::post('/dashboard/buildings', [CaldrenController::class, 'createBuilding'])->name('buildings.store');
    Route::get('/dashboard/buildings/{building}', [CaldrenController::class, 'showBuilding'])->name('buildings.show');
    Route::post('/dashboard/buildings/{building}/upload', [CaldrenController::class, 'upload'])->name('buildings.upload');
    Route::post('/dashboard/buildings/{building}/bills', [CaldrenController::class, 'bill'])->name('buildings.bills');
    Route::post('/dashboard/buildings/{building}/forecast', [CaldrenController::class, 'forecast'])->middleware('throttle:5,1')->name('buildings.forecast');
    Route::get('/dashboard/team', [CaldrenController::class, 'team'])->name('team');
    Route::post('/dashboard/team/invitations', [CaldrenController::class, 'invite'])->middleware('throttle:10,1')->name('team.invite');
    Route::get('/dashboard/billing', [CaldrenController::class, 'billing'])->name('billing');
    Route::post('/dashboard/billing/demo-checkout', [CaldrenController::class, 'demoCheckout'])->middleware('throttle:5,1')->name('billing.demo');
});
