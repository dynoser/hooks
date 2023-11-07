<?php
namespace dynoser\Hooks;

use dynoser\Hooks\HookLog as log;

class HookBase
{
    private static $hookAfterArr   = []; // [hookName] => callable
    private static $hookBeforeArr  = []; // [hookName] => callable
    private static $hookForEachArr = []; // [hookName] => callable
    public  static $hooksAllArr    = []; // [hookName] => [callables]
    
    public static $logFile    = null;    // for trait HookLog

    public static $hookDepend = null;    // for trait HookDepend

    public static array $hookNext = [];  // for trait HookNext, [moduleName] => callable
    public static $lastModuleName = null;// for trait HookNext, last beginModule name

    public static function initHook(
        string $hookName,
        ?callable $afterHookCall = null,
        ?callable $beforeHookCall = null,
        ?callable $forEachHookCall = null
    ) {
        if (\array_key_exists($hookName, self::$hooksAllArr)) {
            throw new \Exception("Hook $hookName already inicialized");
        }
        if ($afterHookCall) {
            self::setAfterHook($hookName, $afterHookCall);
        }
        if ($beforeHookCall) {
            self::setBeforeHook($hookName, $beforeHookCall);
        }
        if ($forEachHookCall) {
            self::setForEachHook($hookName, $forEachHookCall);
        }
        self::HookNameInit($hookName);
    }
    
    public static function HookNameInit(string $hookName) {
        if (!\array_key_exists($hookName, self::$hooksAllArr)) {
            self::$logFile && log::tryLog($hookName, null, 2);
            self::$hooksAllArr[$hookName] = [];            
        }
    }
    
    public static function setAfterHook(string $hookName, callable $call) {
        self::$logFile && log::tryLog($hookName, $call);
        self::$hookAfterArr[$hookName] = self::toCallable($call);
        self::HookNameInit($hookName);
    }

    public static function setBeforeHook(string $hookName, callable $call) {
        self::$logFile && log::tryLog($hookName, $call);
        self::$hookBeforeArr[$hookName] = self::toCallable($call);
        self::HookNameInit($hookName);
    }
   
    public static function setForEachHook(string $hookName, callable $call) {
        self::$hookForEachArr[$hookName] = self::toCallable($call);
        self::HookNameInit($hookName);
    }

    public static function setHook(
        string $hookName,
        callable $hookCall,
        ?string $hookID = '',
        ?bool $mustBeDefined = false
    ): void {
        self::$logFile && log::tryLog($hookName, $hookID);

        if (!\array_key_exists($hookName, self::$hooksAllArr)) {
            if ($mustBeDefined) {
                throw new \Exception("Hook $hookName undefined");
            }
            self::HookNameInit($hookName);
        }
        if ($hookID) {
            self::$hooksAllArr[$hookName][$hookID] = self::toCallable($hookCall);
        } else {
            self::$hooksAllArr[$hookName][] = self::toCallable($hookCall);
        }
    }
    
    public static function unsetHookByID(string $hookName, $hookID): bool
    {
        self::$logFile && log::tryLog($hookName, $hookID);
        $removed = isset(self::$hooksAllArr[$hookName][$hookID]);
        if ($removed) {
            unset(self::$hooksAllArr[$hookName][$hookID]);
        }
        return $removed;
    }

    public static function callHooksError(string $hookName, $param, callable $errorFn) {
        $retArr = self::callHooksForEach($hookName, $param, $errorFn, false);
        if (!\is_array($retArr)) {
            $errorFn("Hooks result is not array");
        }
        return $retArr;
    }

    public static function callHooksForEach(string $hookName, $param, callable $forEachResultFn, bool $emptyAlso = false) {
        $retArr = self::callHooks($hookName, $param, false, false);
        if (\is_array($retArr)) {
            foreach($retArr as $result) {
                if ($emptyAlso || !empty($result)) {
                    $forEachResultFn($result);
                }
            }
        }
        return $retArr;
    }

    public static function callHooksEcho(string $hookName, $param = null, bool $mustBeDefined = false) {
        return self::callHooks($hookName, $param, $mustBeDefined, true);
    }

    public static function callHooks(string $hookName, $param = null, bool $mustBeDefined = false, bool $doEcho = false)
    {
        if ($hookName[0] === '#') {
            $hookName = \Solomono\Hook::$currentPage . $hookName;
        }
        $calledHooksCnt = 0;
        $retArr = [];
        
        // append hookNext if exists
        if (self::$hookNext) {
            self::$hooksAllArr[$hookName] = \array_merge(self::$hooksAllArr[$hookName] ?? [], self::$hookNext);
            self::$hookNext = [];
        }

        // call hookBefore (if exists)
        if (\array_key_exists($hookName, self::$hookBeforeArr)) {
            $calledHooksCnt++;
            self::$logFile && log::tryLog($hookName, $param, 1, '#callBeforeHook');
            $retArr = self::$hookBeforeArr[$hookName]($param);
            if (!\is_array($retArr)) {
                $retArr = [];
            }
        }
        
        // call hooksAll (if exists)
        if (\array_key_exists($hookName, self::$hooksAllArr)) {
            self::$logFile && log::tryLog($hookName, $param);
            $forEachHook = self::$hookForEachArr[$hookName] ?? null;

            self::$hookDepend && self::HookDependPrepare($hookName, $param);
            
            foreach(self::$hooksAllArr[$hookName] as $k => $call) {
                // call forEachHook (if exists)
                $hookResult = $forEachHook ?  $forEachHook($call, $param) : $call($param);
                $calledHooksCnt++;
                
                if (!\is_null($hookResult)) {
                    if (\is_array($hookResult)) {
                        $retArr += $hookResult;
                    } else {
                        if ($doEcho && \is_string($hookResult)) {
                            echo $hookResult;
                        }
                        if (\is_numeric($k)) {
                            $retArr[] = $hookResult;
                        } else {
                            $retArr[$k] = $hookResult;
                        }
                    }
                }
            }
        } elseif ($mustBeDefined) {
            throw new \Exception("Hook $hookName undefined");
        }

        // call hookAfter (if exists)
        if (\array_key_exists($hookName, self::$hookAfterArr)) {
            $calledHooksCnt++;
            self::$logFile && log::tryLog($hookName, $retArr, 1, '#callAfterHook');
            return self::$hookAfterArr[$hookName]($retArr);
        }
        
        if (!$calledHooksCnt) {
            self::$logFile && log::tryLog($hookName, $param, 1, '#empty');
        }

        return $retArr;
    }
    
    public static function toCallable($call, string $throwExceptionMsg = ''): ?callable {
        if (\is_array($call) || \is_string($call)) {
            return \Closure::fromCallable($call);
        } elseif (\is_callable($call)) {
            return $call;
        } elseif ($throwExceptionMsg) {
            throw new \Exception($throwExceptionMsg);
        }
        return null;
    }

}
