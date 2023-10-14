<?php
namespace dynoser\Hooks;

use dynoser\Hooks\HookBase as base;

trait HookLog
{
    public static ?string $logFileName = null;

    public static function setLog(string $logFileName): ?string {
        if ($logFileName) {
            self::$logFileName = realpath($logFileName);
            $logCreated = empty(self::$logFileName);
            if ($logCreated) {
                // File not found, can create?
                if (touch($logFileName)) {
                    self::$logFileName = realpath($logFileName);
                } else {
                    throw new \Exception("Can't create Hook-log file: $logFileName");
                }
            }
            base::$logFile = fopen(self::$logFileName, 'a');
            fwrite(base::$logFile, \PHP_EOL); // add one empty string
            self::tryLog('', null, 1, ($logCreated) ? '#created' : '#continue');
        } else {
            if (base::$logFile) {
                fclose(base::$logFile);
            }
            base::$logFile = null;
            self::$logFileName = null;
        }
        return self::$logFileName;
    }

    public static function tryLog(string $hookName = '', $param = null, int $backTraceLevel = 1, string $HookMark = '') {
        if (base::$logFile) {
            $trace = debug_backtrace();
            
            // prepare $funName from function and class
            $funName = $trace[$backTraceLevel]['function'] ?? '?';
            $class   = $trace[$backTraceLevel]['class'] ?? '';
            if ($class) {
                $funName = $class . '\\' . $funName;
            }

            // prepare $fileShow = filename:line
            $file = $trace[$backTraceLevel]['file'] ?? '';
            $line = $trace[$backTraceLevel]['line'] ?? '';
            $fileShow = ($file) ? basename($file) . ':' . $line : '?';

            // prepare log-string
            $hookShow = (strlen($hookName) < 32) ? str_pad($hookName, 32, ' ', \STR_PAD_RIGHT) : $hookName;
            $paramShow = is_string($param) ? "'$param'" : gettype($param);
            
            // TODO: fix format
            $logMessage = date('Y-m-d H:i:s ') . $funName . ':' . $hookShow . $HookMark . "\t" . $paramShow . "\t" . $fileShow . "\t" . $_SERVER['PHP_SELF'] . \PHP_EOL;

            // @file_put_contents(self::$logFileName, $logMessage, \FILE_APPEND);
            fwrite(base::$logFile, $logMessage);
        }
    }
    
}