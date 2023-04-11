<?php


namespace Kyutefox\KyutePDO\cache;


class Cache
{
    /**
     * @var mixed|null
     */

    protected $cacheDir = null;
    protected $cache = null;
    protected $finish = null;

    /**
     * Cache constructor.
     * @param string|null $dir
     * @param int $time
     */

    function __construct(?string $dir = "", int $time = 0)
    {
        if (!file_exists($dir)) {
            mkdir($dir, 0755);
        }

        $this->cacheDir = $dir;
        $this->cache = $time;
        $this->finish = time() + $time;
    }

    /**
     * @param $sql
     * @param false $array
     * @return false|mixed|void
     */

    public function getCache($sql, $array = false)
    {
        if (is_null($this->cache)) {
            return false;
        }

        $cacheFile = $this->cacheDir . $this->fileName($sql) . '.cache';
        if (file_exists($cacheFile)) {
            $cache = json_decode(file_get_contents($cacheFile), $array);

            if (($array ? $cache['finish'] : $cache->finish) < time()) {
                unlink($cacheFile);
                return;
            }

            return ($array ? $cache['data'] : $cache->data);
        }

        return false;
    }

    /**
     * @param $sql
     * @param $result
     * @return false|void
     */

    public function setCache($sql, $result)
    {
        if (is_null($this->cache)) {
            return false;
        }

        $cacheFile = $this->cacheDir . $this->fileName($sql) . '.cache';
        $cacheFile = fopen($cacheFile, 'w');

        if ($cacheFile) {
            fputs($cacheFile, json_encode(['data' => $result, 'finish' => $this->finish]));
        }

        return;
    }

    /**
     * @param $name
     * @return string
     */

    protected function fileName($name): string
    {
        return md5($name);
    }
}
