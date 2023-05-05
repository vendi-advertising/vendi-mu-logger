<?php

namespace Vendi\Logger;

use Logtail\Monolog\LogtailHandler;
use Monolog\ErrorHandler;
use Monolog\Handler\ChromePHPHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\ErrorHandler as SymfonyErrorHandler;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;

class VendiLogger
{
    private function __construct(private readonly string $baseDir)
    {
    }

    private function maybeGetLogtailHandler(): ?HandlerInterface
    {
        if (!class_exists(LogtailHandler::class)) {
            return null;
        }

        if ($logTailSourceToken = $this->getSetting('VENDI_LOGGER_LOGTAIL_SOURCE_TOKEN')) {
            $logTailLevel = Level::fromName($this->getSetting('VENDI_LOGGER_LOGTAIL_LEVEL', Level::Error->name));

            return new LogtailHandler($logTailSourceToken, $logTailLevel);
        }

        return null;
    }

    private function getLogLevelFromName(?string $name = null): ?Level
    {
        if (!$name) {
            return null;
        }

        try {
            return Level::fromName($name);
        } catch (\Throwable) {
            return null;
        }
    }

    private function maybeGetChromeHandler(): ?HandlerInterface
    {
        if (!class_exists(ChromePHPHandler::class)) {
            return null;
        }

        if ($chromePhpLevel = $this->getLogLevelFromName($this->getSetting('VENDI_LOGGER_CHROME_PHP_HANDLER_LEVEL'))) {
            return new ChromePHPHandler($chromePhpLevel);
        }

        return null;
    }

    private function maybeGetFileHandler(): ?HandlerInterface
    {
        if (!class_exists(RotatingFileHandler::class)) {
            return null;
        }

        if ($vendiLoggerPath = $this->getSetting('VENDI_LOGGER_FILE_LOG_ROOT', WP_CONTENT_DIR)) {
            $vendiLoggerPath = untrailingslashit($vendiLoggerPath).'/vendi-logger';
            if ($uniqueId = $this->getSetting('VENDI_LOGGER_FILE_LOG_UNIQUE_ID')) {
                $vendiLoggerPath .= '-'.untrailingslashit($uniqueId);
            }

            if (!is_dir($vendiLoggerPath) && !@mkdir($vendiLoggerPath, recursive: true) && !is_dir($vendiLoggerPath)) {
                $vendiLoggerPath = null;
            }

            // This can happen if directory creation fails
            if (null !== $vendiLoggerPath) {
                $vendiLoggerFile = $vendiLoggerPath.'/vendi-logger.log';
                $vendiLoggerLevel = $this->getLogLevelFromName($this->getSetting('VENDI_LOGGER_FILE_LOG_FILE_LEVEL', Level::Debug->name));

                return new RotatingFileHandler($vendiLoggerFile, 10, $vendiLoggerLevel);
            }
        }

        return null;
    }

    private function getHandlers(): array
    {
        $handlers = [];

        if ($logTailHandler = $this->maybeGetLogtailHandler()) {
            $handlers[] = $logTailHandler;
        }

        if ($chromeHandler = $this->maybeGetChromeHandler()) {
            $handlers[] = $chromeHandler;
        }

        if ($fileHandler = $this->maybeGetFileHandler()) {
            $handlers[] = $fileHandler;
        }

        return $handlers;
    }

    public static function boot(string $baseDir): void
    {
        $obj = new self($baseDir);
        if ($obj->checkForFastOptOut()) {
            return;
        }

        $obj->loadDependencies();
        $obj->enableErrorTemplate();
        if ($handlers = $obj->getHandlers()) {
            $logger = null;
            foreach ($handlers as $handler) {
                if ($handler instanceof HandlerInterface) {
                    // Defer until the last minute to create the logger
                    if (!$logger) {
                        $logger = new Logger('WordPress logger');
                    }
                    $logger->pushHandler($handler);
                }
            }
            if ($logger) {
                ErrorHandler::register($logger);
            }
        }
    }

    private function enableErrorTemplate(): void
    {
        // This is a developer-only error message, so if WP_DEBUG isn't enabled, which it
        // shouldn't be on PROD, then we don't want to enable this.
        if (defined('WP_DEBUG') && WP_DEBUG && $this->getSetting('VENDI_LOGGER_ENABLE_FULL_DEBUG')) {
            Debug::enable();

            return;
        }

        SymfonyErrorHandler::register();
        HtmlErrorRenderer::setTemplate($this->baseDir.'/templates/error.html.php');
    }

    private function loadDependencies(): void
    {
        require_once $this->baseDir.'/vendor/autoload.php';
    }

    private function checkForFastOptOut(): bool
    {
        if ($this->getSetting('VENDI_LOGGER_DISABLE')) {
            return true;
        }

        return false;
    }

    protected function maybeCastValue(string|int|bool|null $value): string|int|bool|null
    {
        if (is_null($value) || is_int($value) || is_bool($value)) {
            return $value;
        }

        if (null !== ($maybeBool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))) {
            return $maybeBool;
        }

        if (null !== ($maybeInt = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE))) {
            return $maybeInt;
        }

        return $value;
    }

    public function getSetting(string $globalSettingName, string|int|bool|null $defaultValue = null): string|int|bool|null
    {
        if ($value = getenv($globalSettingName)) {
            return $this->maybeCastValue($value);
        }

        if (defined($globalSettingName)) {
            return constant($globalSettingName);
        }

        return $defaultValue;
    }
}