<?php
use App\Http\Controllers\{AuditionController,AuditionReviewController,CastingAuthController,ProductionTeamController,SingleSelectionController,UnifiedAuthController,MaterialController};
use App\Http\Middleware\CastingAccess;
use Illuminate\Support\Facades\Route;
Route::get('/',[AuditionController::class,'home'])->name('home');
Route::get('/candidati',[AuditionController::class,'apply'])->middleware('throttle:30,1,captcha-form')->name('audition.apply');
Route::post('/candidati/captcha',[\App\Http\Controllers\LocalCaptchaController::class,'renew'])->middleware('throttle:15,1,captcha-renew')->name('audition.captcha.renew');
Route::post('/candidati',[AuditionController::class,'store'])->middleware('throttle:audition-uploads')->name('audition.store');
Route::get('/ricevuto/{reference}',[AuditionController::class,'received'])->whereUuid('reference')->name('audition.received');
Route::view('/privacy','privacy')->name('privacy');
Route::view('/cookie','cookies')->name('cookies');
Route::get('/login',[UnifiedAuthController::class,'show'])->name('login');
Route::post('/login',[UnifiedAuthController::class,'enter'])->middleware('throttle:5,1,signin')->name('login.enter');
Route::redirect('/casting/accesso','/login');
Route::middleware(CastingAccess::class)->prefix('casting')->name('casting.')->group(function(){
    Route::get('/',[AuditionController::class,'index'])->name('index');
    Route::middleware('can:review-audition,audition')->group(function () {
        Route::get('/provini/{audition}',[AuditionController::class,'show'])->name('show');
        Route::get('/provini/{audition}/foto-intera',[MaterialController::class,'fullPhoto'])->name('full-photo');
        Route::get('/provini/{audition}/cv',[MaterialController::class,'cv'])->name('cv');
        Route::patch('/provini/{audition}',[AuditionController::class,'update'])->name('update');
        Route::get('/provini/{audition}/video',[AuditionController::class,'video'])->name('video');
        Route::get('/provini/{audition}/commenti',[AuditionReviewController::class,'comments'])->name('comments');
        Route::post('/provini/{audition}/commenti',[AuditionReviewController::class,'comment'])->middleware('throttle:30,1,comments')->name('comments.store');
        Route::patch('/provini/{audition}/voto',[AuditionReviewController::class,'vote'])->middleware('throttle:30,1,votes')->name('votes.store');
    });
    Route::get('/video/{video}',[AuditionReviewController::class,'video'])->name('videos.watch');
    Route::middleware('can:manage-casting')->group(function () {
        Route::get('/impostazioni',[SingleSelectionController::class,'index'])->name('settings');
        Route::patch('/impostazioni',[SingleSelectionController::class,'update'])->name('settings.update');
        Route::get('/provini/{audition}/elimina',[AuditionController::class,'confirmDelete'])->name('confirm-delete');
        Route::delete('/provini/{audition}',[AuditionController::class,'destroy'])->name('destroy');
        Route::get('/collaboratori',[ProductionTeamController::class,'index'])->name('team');
        Route::post('/collaboratori',[ProductionTeamController::class,'store'])->middleware('throttle:6,1,team')->name('team.store');
        Route::get('/collaboratori/{operator}/modifica',[ProductionTeamController::class,'edit'])->name('team.edit');
        Route::patch('/collaboratori/{operator}',[ProductionTeamController::class,'update'])->name('team.update');
        Route::get('/collaboratori/{operator}/elimina',[ProductionTeamController::class,'confirmDelete'])->name('team.confirm-delete');
        Route::delete('/collaboratori/{operator}',[ProductionTeamController::class,'destroy'])->name('team.destroy');
    });
    Route::post('/esci',[CastingAuthController::class,'logout'])->name('logout');
});
Route::get('/candidature/{audition}/foto',[MaterialController::class,'photo'])->name('material.photo');
