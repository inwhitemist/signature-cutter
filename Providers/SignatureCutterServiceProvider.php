<?php

namespace Modules\SignatureCutter\Providers;

use App\Thread;
use Illuminate\Support\ServiceProvider;
use Modules\SignatureCutter\Services\SignatureStripper;

class SignatureCutterServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerConfig();
        $this->hooks();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(SignatureStripper::class, function () {
            return new SignatureStripper(config('signaturecutter', []));
        });
    }

    /**
     * Module hooks.
     *
     * @return void
     */
    public function hooks()
    {
        \Eventy::addAction('thread.created', function ($thread) {
            $this->cleanThread($thread);
        }, 20, 1);
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('signaturecutter.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php',
            'signaturecutter'
        );
    }

    /**
     * Clean one newly-created customer email thread.
     *
     * @param Thread $thread
     *
     * @return void
     */
    protected function cleanThread(Thread $thread)
    {
        if (!config('signaturecutter.enabled', true)) {
            return;
        }

        if ((int) $thread->type !== Thread::TYPE_CUSTOMER) {
            return;
        }

        if ((int) $thread->state !== Thread::STATE_PUBLISHED) {
            return;
        }

        if ((int) $thread->source_type !== Thread::SOURCE_TYPE_EMAIL) {
            return;
        }

        $cleaned = app(SignatureStripper::class)->clean($thread->body);

        if ($cleaned === $thread->body) {
            return;
        }

        $thread->body = $cleaned;
        $thread->save();

        if (config('signaturecutter.update_conversation_preview', true) && $thread->conversation) {
            $thread->conversation->setPreview($thread->body);
            $thread->conversation->save();
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
