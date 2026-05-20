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

        $stripper = app(SignatureStripper::class);

        if ($stripper->shouldDrop($thread->body)) {
            $this->dropThread($thread);
            return;
        }

        $cleaned = $stripper->clean($thread->body);

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
     * Remove a whole noisy incoming email and repair conversation fields
     * that may have been updated by the customer reply.
     *
     * @param Thread $thread
     *
     * @return void
     */
    protected function dropThread(Thread $thread)
    {
        $conversation = $thread->conversation;

        if (!empty($thread->first) && $conversation) {
            if (method_exists($conversation, 'deleteForever')) {
                $conversation->deleteForever();
            } else {
                $conversation->delete();
            }

            return;
        }

        if (method_exists($thread, 'deleteThread')) {
            $thread->deleteThread();
        } else {
            $thread->delete();
        }

        if ($conversation) {
            $this->restoreConversationAfterDroppedThread($conversation);
        }
    }

    /**
     * @param mixed $conversation
     *
     * @return void
     */
    protected function restoreConversationAfterDroppedThread($conversation)
    {
        $threads = Thread::where('conversation_id', $conversation->id)
            ->where('state', Thread::STATE_PUBLISHED)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $lastReply = null;
        $lastStatus = null;

        foreach ($threads as $existingThread) {
            if ($lastReply === null && in_array((int) $existingThread->type, [Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE], true)) {
                $lastReply = $existingThread;
            }

            if ($lastStatus === null
                && in_array((int) $existingThread->status, [Thread::STATUS_ACTIVE, Thread::STATUS_PENDING, Thread::STATUS_CLOSED, Thread::STATUS_SPAM], true)
                && (
                    (int) $existingThread->action_type === Thread::ACTION_TYPE_STATUS_CHANGED
                    || in_array((int) $existingThread->type, [Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE], true)
                )
            ) {
                $lastStatus = $existingThread;
            }

            if ($lastReply !== null && $lastStatus !== null) {
                break;
            }
        }

        if ($lastReply) {
            $conversation->last_reply_at = $lastReply->created_at;
            $conversation->last_reply_from = (int) $lastReply->type === Thread::TYPE_CUSTOMER
                ? Thread::PERSON_CUSTOMER
                : Thread::PERSON_USER;
        }

        if ($lastStatus) {
            $conversation->status = $lastStatus->status;
        }

        $conversation->save();

        if (method_exists('\App\Conversation', 'updatePreview')) {
            \App\Conversation::updatePreview($conversation->id);
        } elseif (method_exists($conversation, 'setPreview')) {
            $conversation->setPreview($lastReply ? $lastReply->body : '');
            $conversation->save();
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
