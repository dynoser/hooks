<?php
namespace dynoser\Hooks;

trait HookInt {
    
    public static $intPathAdd = '/storage/int/';
    
    public static $alreadyIntArr = []; //ShortPath => IntPath
    
    public static $currentPage = null;
    public static $oldPagesArr = [];
    
    public static ?string $rootDir = null;
    
    public static array $includesArr = []; //[ShortPath][incName] => fullFileName for include
    public static string $includeEmptyFile = ''; // need to define

    public static function include(string $incName) {
        if (!self::$currentPage) {
            throw new \Exception("Hook::int required before Hook::include('$incName')");
        }
        return self::$includesArr[self::$currentPage][$incName] ?? self::$includeEmptyFile;
    }
    public static function setInclude($incName, $fullFileName) {
        if (!self::$currentPage) {
            throw new \Exception("Hook::int required before Hook::setInclude('$incName', '$fullFileName')");
        }
        self::$includesArr[self::$currentPage][$incName] = $fullFileName;
    }

    public static function getRootDir(): string {
        if (!self::$rootDir) {
            if (\class_exists('dynoser\\autoload\\AutoLoadSetup', false)) {
                $rootDir = \dynoser\autoload\AutoLoadSetup::$rootDir;
            } elseif (!empty($GLOBALS['rootDir'])) {
                $rootDir = $GLOBALS['rootDir'];
            } elseif (\defined("ROOT_DIR")) {
                $rootDir = ROOT_DIR;
            } elseif (\defined("DIR_ROOT")) {
                $rootDir = DIR_ROOT;
            }else {
                throw new \Exception("Can't detect RootDir");
            }
            $rootDir = realpath($rootDir);
            if (!$rootDir) {
                throw new \Exception("rootDir not found");
            }
            self::$rootDir = \strtr($rootDir, '\\' , '/');
        }
        return self::$rootDir;
    }
    
    public static function int(string $fileFull = '', bool $realyLoad = true): ?string
    {
        if (!$fileFull) {
            // get the file this call was from
            $trace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 1);

            if (empty($trace[0]['file'])) {
                throw new \Exception("Unexpected backtrace");
            }
            $fileFull = $trace[0]['file'];
        }
        $fileFull = \strtr($fileFull, '\\', '/');
        
        $shortPath = self::cutShortName($fileFull, true);
        if (\array_key_exists($shortPath, self::$alreadyIntArr)) {
            throw new \Exception("Already Hook::int for page: $shortPath");
        }

        // calculate default page
        $currentPage = \basename($fileFull);
        $i = \strrpos($currentPage, '.');
        if ($i) {
            $currentPage = \substr($currentPage, 0, $i);
        }
        
        if (!\is_null(self::$currentPage)) {
            \array_push(self::$oldPagesArr, self::$currentPage);
        }
        self::$currentPage = $currentPage;

        $intFileFull = self::getIntPathForShort($shortPath);

        if (empty(self::$includeEmptyFile)) {
            self::$includeEmptyFile = self::getRootDir() . self::$intPathAdd . 'empty.php';
        }

        if (\file_exists($intFileFull)) {
            self::$alreadyIntArr[$shortPath] = $intFileFull;
            if ($realyLoad) {
                // if one of moduleBegin is opened (then hookNext trait is exists)
                $doNotIntArr = (self::$lastModuleName) ? \array_keys(self::$moduleStarted[$shortPath]) : [];
                require_once $intFileFull;
            }
            return $intFileFull;
        }
        self::$alreadyIntArr[$shortPath] = null;
        return null;
    }
    
    public function pop() {
        if (self::$oldPagesArr) {
            self::$currentPage = \array_pop(self::$oldPagesArr);
        }
    }
    
    public static function cutShortName(string $fileFull, bool $throwIfOut = false): string
    {
        $rootDir = self::getRootDir();
        if (empty($rootDir)) {
            throw new \Exception("\$rootDir required");
        }
        
        $l = \strlen($rootDir);
        
        if (\substr($fileFull, 0, $l) !== $rootDir) {
            if ($throwIfOut) {
                throw new \Exception("Unexpected call not in rootDir: $fileFull");
            }
            $shortPath = $fileFull;
        } else {
            $shortPath = \substr($fileFull, $l + 1);
        }
        return \strtr($shortPath, '\\', '/');
    }
    
    public static function getIntPathForShort(string $shortPath, string $incName = null): string
    {
        $incName = ($incName) ? ('-inc-' . $incName ) : '-int';

        $i = \strrpos($shortPath, '.');
        if ($i) {
            $shortPath = \substr($shortPath, 0, $i) . $incName . \substr($shortPath, $i);
        } else {
            $shortPath .= $incName . '.php';
        }
        return self::getRootDir() . self::$intPathAdd . $shortPath;
    }
}
