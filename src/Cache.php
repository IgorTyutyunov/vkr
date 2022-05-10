<?php
namespace Igrik\Vkr;

class Cache
{
    public \Redis $redis;
    private string $key;
    private int $ttl;

    /**
     * @param array $keyData - массив с данными, из которых нужно сделать ключ кеша
     * @param int $ttl - время жизни кеша
     * @param string $tag - теги кеша, можно использовать для удаления кеша по тегу
     */
    public function __construct(array $keyData, int $ttl = 3600, string $tag = '')
    {
        $this->redis = new \Redis();
        $this->redis->pconnect('localhost');
        $keyData[] = $ttl;

        if(!empty($tag))
        {
            $keyData[] = $tag;
        }

        $this->key = md5(serialize($keyData));

        if(!empty($tag))
        {
            $this->key = "TAG_" . $tag . ":" . $this->key;
        }

        $this->ttl = $ttl;
    }

    /**
     * Получение закешированных данных. Если данных нет, то вернёт false
     *
     * @return bool|array
     */
    public function getCache()
    {
        if($this->isExists())
        {
            return unserialize($this->redis->get($this->key));
        }

        return false;
    }

    /**
     * @param array $data - массив с данными, которые нужно сохранить в кеш
     */
    public function setCache(array $data)
    {
        $this->redis->set($this->key, serialize($data), $this->ttl);
    }

    /**
     * Проверяет существование кеша пол ключу
     * @return bool
     */
    private function isExists():bool
    {
       return $this->redis->exists($this->key);
    }

}