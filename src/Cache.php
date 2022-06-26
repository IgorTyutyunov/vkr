<?php
namespace Igrik\Vkr;

class Cache
{
    public static \Redis $redis;
    private string $key;
    private int $ttl;

    /**
     * @param array $keyData - массив с данными, из которых нужно сделать ключ кеша
     * @param int $ttl - время жизни кеша
     * @param string $tag - теги кеша, можно использовать для удаления кеша по тегу
     */
    public function __construct(array $keyData = [], int $ttl = 3600, string $tag = '')
    {
        if(empty(self::$redis))
        {
            self::$redis = new \Redis();
            self::$redis->pconnect('localhost');
        }

        if(!empty($keyData))
        {
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
            return unserialize(self::$redis->get($this->key));
        }

        return false;
    }

    /**
     * @param array $data - массив с данными, которые нужно сохранить в кеш
     */
    public function setCache(array $data)
    {
        self::$redis->set($this->key, serialize($data), $this->ttl);
    }

    /**
     * Метод очищает кеш по тегу
     *
     * @param string $tag - тег кеша
     *
     * @return void
     */
    public function clearCacheByTag(string $tag): void
    {
        self::$redis->del($this->getKeysByTag($tag));
    }

    /**
     * Метод очищает кеш по тегу
     *
     * @param string $tag - тег кеша
     *
     * @return array
     */
    public function getCacheByTag(string $tag): array
    {
        $arKeys = $this->getKeysByTag($tag);

        $arResult = [];

        foreach ($arKeys as $key)
        {
            if($data = self::$redis->get($key));
            {
                $arResult[] = $data;
            }
        }

        return $arResult;
    }

    /**
     * Метод возвращает ключи кеша, которые соответствуют тегу
     * @param string $tag - тег кеша
     *
     * @return array - массив ключей
     */
    private function getKeysByTag(string $tag):array
    {
        return self::$redis->keys("TAG_{$tag}*");
    }

    /**
     * Проверяет существование кеша пол ключу
     * @return bool
     */
    private function isExists():bool
    {
       return self::$redis->exists($this->key);
    }

}