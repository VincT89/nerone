<?php

namespace App\Providers;

use App\Models\Audition;
use App\Models\User;
use App\Services\AuditionUploadGuard;
use App\Services\SelfTapeQueueRecovery;
use Illuminate\Auth\Access\Response;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-casting', fn (User $user) => $user->is_casting_operator && $user->is_casting_admin);
        Gate::define('review-audition', fn (User $user, Audition $audition) =>
            $user->is_casting_operator && $audition->deletion_requested_at === null
                ? Response::allow()
                : Response::denyAsNotFound()
        );

        Event::listen(JobAttempted::class, SelfTapeQueueRecovery::class);

        RateLimiter::for('audition-uploads', function (Request $request) {
            $guard = app(AuditionUploadGuard::class);
            $guard->ensureCapacity($request);

            return Limit::perMinutes(15, (int) config('casting.upload_attempts_per_quarter_hour'))
                ->by($guard->connectionKey($request));
        });
    }
}
