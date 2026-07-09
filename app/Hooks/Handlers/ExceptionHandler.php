<?php

namespace FluentEvents\App\Hooks\Handlers;

use FluentEvents\Framework\Foundation\Application;

class ExceptionHandler
{
    /**
     * Application container injected by the framework.
     *
     * @var \FluentEvents\Framework\Foundation\Application|null
     */
    protected $app = null;

    /**
     * Keep a reference to the Application so custom log destinations can be
     * resolved from the container if the default `error_log()` path isn't
     * suitable.
     *
     * @param  \FluentEvents\Framework\Foundation\Application  $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Handle an uncaught exception raised anywhere inside the plugin's
     * request/response lifecycle. Registered via
     * `$app->addCustomAction('exception', 'ExceptionHandler')` in
     * app/Hooks/actions.php — framework re-dispatches the exception through
     * this handler so plugins can intercept it without touching the core
     * error pipeline.
     *
     * Default behaviour: emit the exception class, message, file, line, and
     * stack trace to `error_log()` when `WP_DEBUG` is on. Swap in your own
     * logger (Sentry, Rollbar, custom tables) as needed — just keep the
     * signature so the hook keeps firing.
     *
     * @param  \Throwable  $e
     * @return void
     */
    public function handle($e)
    {
        $logEntry = sprintf(
            "%s: %s in %s on line %d\nStack trace:\n%s\n\n",
            get_class($e),              // Exception class
            $e->getMessage(),           // Message
            $e->getFile(),              // File where it occurred
            $e->getLine(),              // Line number
            $e->getTraceAsString()      // Full stack trace
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($logEntry); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }
}
