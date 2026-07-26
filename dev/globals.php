<?php

if (!function_exists('dd')) {
    /**
     * Dump & Die: Print variables and stop execution.
     *
     * Usage:
     * dd($var1, $var2, ...);
     */
    function dd(/*args*/)
    {
        $isCli = str_contains(strtolower(php_sapi_name()), 'cli');
        
        // Try to call tearDown() if running inside PHPUnit
        if ($isCli) {
            $traces = debug_backtrace(
                DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS
            );

            foreach ($traces as $trace) {
                if (
                    isset($trace['object']) &&
                    method_exists($trace['object'], 'tearDown')
                ) {
                    try {
                        $trace['object']->tearDown();
                    } catch (\Throwable $e) {}
                    
                    break;
                }
            }
        }

        ob_start();
        foreach (func_get_args() as $arg) {
            echo "<pre>";
            print_r($arg);
            echo "</pre>";
        }
        $ret = ob_get_clean();

        if ($isCli) {
            echo PHP_EOL . PHP_EOL . strip_tags($ret) . PHP_EOL . PHP_EOL;
        } else {
            echo strip_tags($ret);
        }

        die(1);
    }
}

if (!function_exists('ddd')) {
    /**
     * Dump & Don't Die: Print variables but continue execution.
     *
     * Usage:
     * ddd($var1, $var2, ...);
     */
    function ddd(/*args*/)
    {
        ob_start();
        foreach (func_get_args() as $arg) {
            echo "<pre>";
            print_r($arg);
            echo "</pre>";
        }
        $ret = ob_get_clean();

        if (str_contains(strtolower(php_sapi_name()), 'cli')) {
            echo PHP_EOL.PHP_EOL . strip_tags($ret) . PHP_EOL.PHP_EOL;
        } else {
            echo strip_tags($ret);
        }
    }
}

if (!function_exists('fluentevent_log')) {
    /**
     * Log a message to the debug.log file.
     * 
     * @param  mix $m
     * @return void
     */
    function fluentevent_log($m) {
        if (is_array($m) || is_object($m)) {
            $m = json_encode($m, JSON_PRETTY_PRINT);
        }

        $bt    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        $file  = isset($bt[0]['file']) ? $bt[0]['file'] : 'unknown file';
        $line  = isset($bt[0]['line']) ? $bt[0]['line'] : '??';

        $relativeFile = str_replace(ABSPATH, '', $file);

        $timestamp = date('Y-m-d H:i:s');

        $message = <<<LOG
[{$timestamp}]
File: {$relativeFile}
Line: {$line}
Log: {$m}
----------------------------------------------------------------------------

LOG;

        $log_file = WP_CONTENT_DIR . '/debug.log';
        error_log($message, 3, $log_file);
    }
}

if (!function_exists('debug')) {
    /**
     * Log a message.
     * @param  mix $m
     * @return void
     */
    function debug($m) {
        fluentevent_log($m);
    }
}

if (!function_exists('fluentevent_eql')) {
    /**
     * Enable query logging and return the current query count.
     *
     * Usage:
     * $start = fluentevent_eql();
     */
    function fluentevent_eql() {
        $lastIndex = 0;
        defined('SAVEQUERIES') || define('SAVEQUERIES', true);
        if ($queries = (array) $GLOBALS['wpdb']->queries) {
            $lastIndex = count($queries);
        }
        return $lastIndex;
    }
}

if (!function_exists('fluentevent_gql')) {
    /**
     * Get the database query log from a given start index.
     *
     * Usage:
     * $queries = fluentevent_gql($start);
     */
    function fluentevent_gql($start = 0) {
        $result = [];
        $queries = (array) $GLOBALS['wpdb']->queries;
        $queries = $start > 0 ? array_slice($queries, $start) : $queries;
        foreach ($queries as $key => $query) {
            $result[++$key] = array_combine([
                'query', 'execution_time'
            ], array_slice($query, 0, 2));
        }
        return $result;
    }
}

if (!function_exists('fluentevent_wql')) {
    /**
     * Write the database query log to the error log starting from $start index.
     *
     * Usage:
     * fluentevent_wql($start);
     */
    function fluentevent_wql($start) {
        fluentevent_log(fluentevent_gql($start));
    }
}

