<?php

namespace Kyutefox\KyutePDO\Config;

use PDO;

class Connection
{
    private string $host;
    private string $driver;
    private string $charset;
    private string $collation;
    private string $port;
    private string $name;
    private string $user;
    private string $pass;
    private string $dsn;
    private array $config;

    public function __construct(array $config)
    {
        $this->driver       = !empty($config["db_driver"])      ? $config["db_driver"]      : 'mysql';
        $this->host         = !empty($config["db_host"])        ? $config["db_host"]        : 'localhost';
        $this->charset      = !empty($config["db_charset"])     ? $config["db_charset"]     : 'utf8mb4';
        $this->collation    = !empty($config["db_collation"])   ? $config["db_collation"]   : 'utf8mb4_unicode_ci';
        $this->port         = !empty($config["db_port"])        ? (strstr($config["db_port"], ':') ? explode(':', $config["db_port"])[1] : "") : '3306';
        $this->user         = !empty($config["db_user"])        ? $config["db_user"]        : 'root';
        $this->pass         = !empty($config["db_pass"])        ? $config["db_pass"]        : '';
        $this->name         = !empty($config["db_name"])        ? $config["db_name"]        : '';
        $this->config       = $config["db_config"]              ?? [];
    }

    public function init(): ?PDO
    {
        if (in_array($this->driver, ['mysql', 'pgsql']))
        {
            $this->dsn = $this->driver . ':host=' . str_replace(':' . $this->port, '', $this->host) . ';port=' . $this->port . ';dbname=' . $this->name;
        }

        if ($this->driver == 'sqlite') $this->dsn = $this->driver . ':' . $this->name;
        if ($this->driver == "oracle") $this->dsn = 'oci:dbname=' . $this->name . '/' . $this->host;

        return $this->connect($this->dsn);
    }

    private function connect(string $dataSource)
    {
        try
        {
            $pdo = new \PDO($dataSource, $this->user, $this->pass, $this->config);
            $pdo->exec("SET NAMES '" . $this->charset . "' COLLATE '" . $this->collation . "'");
            $pdo->exec("SET CHARACTER SET '" . $this->charset . "'");
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        }
        catch(\PDOException $e)
        {
            die("Specified database connection couldn't be started with PDO. " . $e->getMessage());
        }

        return $pdo;
    }
}
