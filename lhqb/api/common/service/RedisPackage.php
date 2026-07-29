<?php

// +----------------------------------------------------------------------
// | Description: redis 缓存封装使用
// +----------------------------------------------------------------------
// | Author: php
// +----------------------------------------------------------------------

namespace api\common\service;


class RedisPackage
{
    protected static $handler = null;
    protected $options = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => '',
        'select' => 0,
        'timeout' => 1,
        'expire' => 0,
        'persistent' => false,
        'prefix' => '',
    ];

    public function __construct($options = [])
    {
        if (!extension_loaded('redis')) {
            throw new \BadFunctionCallException('not support: redis');
        }

        $envOptions = [
            'host' => getenv('REDIS_HOST') ?: $this->options['host'],
            'port' => getenv('REDIS_PORT') ?: $this->options['port'],
            'password' => getenv('REDIS_PASSWORD') !== false ? getenv('REDIS_PASSWORD') : $this->options['password'],
            'select' => getenv('REDIS_DB') !== false ? getenv('REDIS_DB') : $this->options['select'],
            'timeout' => getenv('REDIS_TIMEOUT') !== false ? getenv('REDIS_TIMEOUT') : $this->options['timeout'],
        ];
        $this->options = array_merge($this->options, $envOptions);

        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }

        $func = $this->options['persistent'] ? 'pconnect' : 'connect';
        self::$handler = new \Redis;
        self::$handler->$func($this->options['host'], $this->options['port'], $this->options['timeout']);

        if ($this->options['password'] !== '') {
            self::$handler->auth($this->options['password']);
        }

        if ((int)$this->options['select'] !== 0) {
            self::$handler->select((int)$this->options['select']);
        }
    }
    /**
     * 写入缓存
     * @param string $key 键名
     * @param string $value 键值
     * @param int $exprie 过期时间 0:永不过期
     * @return bool
     */
    public static function set($key, $value, $exprie = 0)
    {
        if ($exprie == 0) {
            $set = self::$handler->set($key, $value);
        } else {
            $set = self::$handler->setex($key, $exprie, $value);
        }
        return $set;
    }
    /**
     * 读取缓存
     * @param string $key 键值
     * @return mixed
     */
    public static function get($key)
    {
        $fun = is_array($key) ? 'Mget' : 'get';
        return self::$handler->{$fun}($key);
    }

    /**
     * 获取值长度
     * @param string $key
     * @return int
     */
    public static function lLen($key)
    {
        return self::$handler->lLen($key);
    }

    /**
     * 将一个或多个值插入到列表头部
     * @param $key
     * @param $value
     * @return int
     */
    public static function LPush($key, $value, $value2 = null, $valueN = null)
    {
        return self::$handler->lPush($key, $value, $value2, $valueN);
    }

    /**
     * 移出并获取列表的第一个元素
     * @param string $key
     * @return string
     */
    public static function lPop($key)
    {
        return self::$handler->lPop($key);
    }

    /**
     * 读取keys
     */
    public static function keys($prefix)
    {
        return self::$handler->keys("*".$prefix."*");
    }

    /**
     * 获取所有(一个或多个)给定 key 的值
     */
    public static function mGet($keyList)
    {
      return self::$handler->mGet($keyList);
    }
}
