<?php
namespace dynoser\Hooks;

trait HookNext {
    public static array $moduleStarted = []; // [file_short_path][MODULE] => function | null
    public static array $moduleBeforeInt = []; // [file_short_path][MODULE] => string $codePoint  for remove-codePoint before int
   
    /**
    * Gets the full class name for the short class name from a "use" in code.
    *
    * @param string $codeStr String of code in which to look for "use" definitions.
    * @param string $useShortName Short class name for search.
    * @return string|null full class name (with namespace), or null if not found.
    */
    public static function getUseFromCode(string $codeStr, string $useShortName): ?string
    {
        $moduleCreaterClass = null;

        // scan "use" definition for $useShortName
        preg_match_all('/use\s+([^;]+);/', $codeStr, $matches);

        if (!empty($matches[1])) {
            foreach($matches[1] as $nspath) {

                // Check if the current namespace contains a short class name
                $i = stripos($nspath, $useShortName);
                if ($i) {
                    // Extract the full namespace from the found match
                    $moduleCreaterClass = substr($nspath, 0, $i+strlen($useShortName));
                    
                    // Remove possible aliases after the namespace
                    if ($i = strpos($moduleCreaterClass, ' ')) {
                        $moduleCreaterClass = substr($moduleCreaterClass, 0, $i);
                    }
                    break;
                }
            }
        }
        return $moduleCreaterClass;
    }
    
    public static function isModuleNameInvalid($moduleName) {
        $l = \strlen($moduleName);
        return $l < 3 || $l > 16 || !\preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $moduleName);
    }
    
    public static function moduleBegin(string $srcModuleName, callable $definitions_fn = null)
    {
        // get the file this call was from
        $trace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 1);

        if (empty($trace[0]['file'])) {
            throw new \Exception("Unexpected backtrace");
        }
        $fileFull = \strtr($trace[0]['file'], '\\', '/');

        if (self::isModuleNameInvalid($srcModuleName)) {
            throw new \Exception("\nERROR in $fileFull\nBad module name for moduleBegin: $srcModuleName\n\n");
        }
        $moduleName = \strtoupper($srcModuleName);

        $shortPath = self::cutShortName($fileFull, true);
        if (!empty(self::$moduleStarted[$shortPath][$moduleName])) {
            throw new \Exception("Already module beginned Hook::moduleBegin '$moduleName' for page: $shortPath");
        }
        self::$moduleStarted[$shortPath][$moduleName] = $definitions_fn;
        self::$lastModuleName = $moduleName;
        if (\is_callable($definitions_fn)) {
            $definitions_fn($shortPath);
        }
    }
    
    public static function code(string $incName, string $moduleName = null) {
        // get module name
        if (!$moduleName) {
            $moduleName = self::$lastModuleName;
            if (empty($moduleName)) {
                throw new \Exception("No Hook::moduleBegin");
            }
        }
    }
        
    public static function remove(string $codePoint, string $moduleName = null) {
        // get module name
        if (!$moduleName) {
            $moduleName = self::$lastModuleName;
            if (empty($moduleName)) {
                throw new \Exception("No Hook::moduleBegin");
            }
        }
        
        // get the file this call was from
        $trace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 1);

        if (empty($trace[0]['file'])) {
            throw new \Exception("Unexpected backtrace");
        }
        $fileFull = \strtr($trace[0]['file'], '\\', '/');

        $shortPath = self::cutShortName($fileFull, true);
        
        $hasIntegration = \array_key_exists($shortPath, self::$alreadyIntArr);
        
        if ($hasIntegration) {
            if (!\array_key_exists($shortPath,  self::$moduleStarted)
             || !\array_key_exists($moduleName, self::$moduleStarted[$shortPath])) {
                throw new \Exception("Hook::moduleBegin('$moduleName') not defined in $shortPath");                
            }
        } else {
            self::$moduleBeforeInt[$shortPath][$moduleName] = $codePoint;
        }
        
        // DO NOTHING, this method using only as codePoint marker for module editing
    }

    public static function next(callable $callable, string $moduleName = null) {
        // get module name
        if (!$moduleName) {
            $moduleName = self::$lastModuleName;
            if (empty($moduleName)) {
                throw new \Exception("No Hook::moduleBegin");
            }
        }
        if (!empty(self::$hookNext[$moduleName])) {
            throw new \Exception("Hook::next($moduleName) already defined");
        }
        
        self::$hookNext[$moduleName] = $callable;
    }
}
