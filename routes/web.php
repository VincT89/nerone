<?php
use App\Http\Controllers\AuditionController;
use App\Http\Controllers\CastingAuthController;
use App\Http\Middleware\CastingAccess;
use Illuminate\Support\Facades\Route;
Route::view('/','home')->name('home');
Route::view('/candidati','apply',['disciplines'=>AuditionController::DISCIPLINES])->name('audition.apply');
Route::post('/candidati',[AuditionController::class,'store'])->middleware('throttle:8,1')->name('audition.store');
Route::get('/ricevuto/{reference}',[AuditionController::class,'received'])->whereUuid('reference')->name('audition.received');
Route::view('/privacy','privacy')->name('privacy');
Route::view('/cookie','cookies')->name('cookies');
Route::get('/casting/accesso',[CastingAuthController::class,'show'])->name('casting.login');
Route::post('/casting/accesso',[CastingAuthController::class,'login'])->middleware('throttle:5,1')->name('casting.enter');
Route::middleware(CastingAccess::class)->prefix('casting')->name('casting.')->group(function() {
    Route::get('/',[AuditionController::class,'index'])->name('index');
    Route::get('/provini/{audition}',[AuditionController::class,'show'])->name('show');
    Route::patch('/provini/{audition}',[AuditionController::class,'update'])->name('update');
    Route::get('/provini/{audition}/video',[AuditionController::class,'video'])->name('video');
    Route::post('/provini/{audition}/video',[AuditionController::class,'attach'])->name('attach');
    Route::get('/provini/{audition}/elimina',[AuditionController::class,'confirmDelete'])->name('confirm-delete');
    Route::delete('/provini/{audition}',[AuditionController::class,'destroy'])->name('destroy');
    Route::post('/esci',[CastingAuthController::class,'logout'])->name('logout');
});
