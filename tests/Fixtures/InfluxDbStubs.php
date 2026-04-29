<?php

namespace InfluxDB;

class Client
{
    public static bool $throwOnSelect = false;
    public static ?Database $lastDatabase = null;

    public function __construct(
        public string $host,
        public int $port,
        public string $username,
        public string $password,
        public bool $ssl,
        public bool $verifySsl,
        public int $timeout,
        public int $connectTimeout,
    ) {
    }

    public function selectDB(string $name): Database
    {
        if (self::$throwOnSelect) {
            throw new \RuntimeException('influx unavailable', 503);
        }

        self::$lastDatabase = new Database($name);

        return self::$lastDatabase;
    }
}

class Database
{
    public const PRECISION_SECONDS = 's';

    public array $points = [];
    public ?string $precision = null;

    public function __construct(public string $name)
    {
    }

    public function writePoints(array $points, string $precision): void
    {
        $this->points = $points;
        $this->precision = $precision;
    }
}

class Point
{
    public function __construct(
        public string $measurement,
        public int $value,
        public array $tags,
        public array $fields,
    ) {
    }
}
