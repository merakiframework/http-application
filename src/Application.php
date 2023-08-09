<?php
declare(strict_types=1);

namespace Meraki\Http;

use Closure;
use InvalidArgumentException;
use RuntimeException;

final class Application
{
	public string $name = '';
	public object $directories;

	private static array $loadedConfigs = [];

	private function __construct(){}

	public static function create(string $basePath)
	{
		if (!is_dir($basePath)) {
			throw new RuntimeException('Environment not configured correctly: the directory does not exist.');
		}

		return (new self())
			->createDefaultDirectoryStructure($basePath)
			->configureFromFile('app.php');
	}

	private function configureFromFile(string $filename): self
	{
		$file = "{$this->directories->config}/$filename";

		if (is_readable($file)) {
			$copy = (include_once $file)($this);

			if (!($copy instanceof self)) {
				throw new RuntimeException('main config file "'.$file.'" must return an instance of "'.self::class.'".');
			}

			return $copy;
		}

		throw new RuntimeException('Main config file is missing at: '.$file);
	}

	private function createDefaultDirectoryStructure(string $basePath): self
	{
		return $this->configureDirectories([
			'root' => $basePath,
			'web' => "$basePath/public",
			'bin' => "$basePath/bin",
			'runtime' => "$basePath/runtime",
			'cache' => "$basePath/cache",
			'temp' => "$basePath/temp",
			'logs' => "$basePath/logs",
			'config' => "$basePath/config",
			'resources' => "$basePath/resources",
			'storage' => "$basePath/storage",
			'backups' => "$basePath/backups",
			'data' => "$basePath/data",
			'themes' => "$basePath/themes",
		]);
	}

	public function configureDirectories(array $dirs): self
	{
		$copy = clone $this;
		$copy->directories = (object)$dirs;

		return $copy;
	}

	public function name(string $name): self
	{
		$copy = clone $this;
		$copy->name = $name;

		return $copy;
	}

	public function getFromEnvironment(string $name, mixed $defaultValue = null): mixed
	{
		if (isset($_ENV[$name])) {
			return $_ENV[$name];
		}

		return $defaultValue ?: throw new \DomainException('missing environment variable: ' . $name);
	}

	public function getDirectory($name): ?string
	{
		return property_exists($this->directories, $name) ? $this->directories->$name : null;
	}

	public function inDevelopment(): bool
	{
		return $this->getFromEnvironment('DEVELOPMENT') == 'true';
	}

	public function getConfigFromFile($filename): object
	{
		if (array_key_exists($filename, self::$loadedConfigs)) {
			return self::$loadedConfigs[$filename];
		}

		$file = "{$this->directories->config}/$filename";

		if (is_readable($file)) {
			$loadConfig = Closure::bind(function ($file) {
				return include_once $file;
			}, null);

			$configClosure = $loadConfig($file);

			if (!($configClosure instanceof Closure)) {
				throw new InvalidArgumentException("config file must return a closure at: $file");
			}

			$configObject = call_user_func($configClosure, $this);

			self::$loadedConfigs[$filename] = $configObject;
			//$this->container = $this->container->addRule($config::class, $config);

			return self::$loadedConfigs[$filename];
		}

		throw new \RuntimeException("Cannot find config file: $file");
	}
}
