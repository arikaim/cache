<?php
/**
 * Arikaim
 *
 * @link        http://www.arikaim.com
 * @copyright   Copyright (c)  Konstantin Atanasov <info@arikaim.com>
 * @license     http://www.arikaim.com/license
 * 
*/
namespace Arikaim\Core\Cache;

use Doctrine\Common\Cache\Cache as CacheDriverInterface;

use Arikaim\Core\Interfaces\CacheInterface;
use Exception;

/**
 * Cache 
*/
class Cache implements CacheInterface
{
    const DEFAULT_DRIVER    = 'filesystem';
    // drivers
    const FILESYSTEM_DRIVER = 'filesystem';
    const PHPFILE_DRIVER    = 'phpfile';
    const APCU_DRIVER       = 'apcu';
    const ARRAY_DRIVER      = 'array';
    const VOID_DRIVER       = 'void';
    const MEMCACHED_DRIVER  = 'memcached';
    const MEMCACHE_DRIVER   = 'memcache';
    const REDIS_DRIVER      = 'redis';
    const PREDIS_DRIVER     = 'predis';

    /**
     * Drivers list
     *
     * @var array
     */
    protected $drivers = [
        Self::FILESYSTEM_DRIVER => 'Doctrine\Common\Cache\FilesystemCache',
        Self::PHPFILE_DRIVER    => 'Doctrine\Common\Cache\PhpFileCache',
        Self::APCU_DRIVER       => 'Doctrine\Common\Cache\ApcuCache',
        Self::MEMCACHED_DRIVER  => 'Doctrine\Common\Cache\MemcachedCache',
        Self::MEMCACHE_DRIVER   => 'Doctrine\Common\Cache\MemcacheCache',
        Self::ARRAY_DRIVER      => 'Doctrine\Common\Cache\ArrayCache',
        Self::VOID_DRIVER       => 'Doctrine\Common\Cache\VoidCache',
        Self::REDIS_DRIVER      => 'Doctrine\Common\Cache\RedisCache',
        Self::PREDIS_DRIVER     => 'Doctrine\Common\Cache\PredisCache'
    ];

    /**
     * Cache driver
     *
     * @var Doctrine\Common\Cache\Cache
     */
    protected $driver;
    
    /**
     * Cache status
     *
     * @var bool
     */
    private $status;

    /**
     * Cache directory
     *
     * @var string
     */
    protected $cacheDir;

    /**
     * Route cache file
     *
     * @var string
     */
    protected $routeCacheFile;

    /**
     * Default save time
     *
     * @var int
     */
    protected $saveTime;

    /**
     * Constructor
     *
     * @param string $cacheDir       
     * @param string|null $driverName
     * @param boolean $status
     */
    public function __construct(
        string $cacheDir,      
        ?string $driverName = null, 
        bool $status = false, 
        int $saveTime = 7
    )
    {
        $this->status = $status;
        $this->saveTime = $saveTime;      
        $this->cacheDir = $cacheDir;       
        $this->driver = $this->createDriver($driverName);  
    }

    /**
     * Get supported driver names.
     *
     * @return array
     */
    public function getSupportedDrivers(): array
    {
        $result = [];
        foreach ($this->drivers as $name => $class) {
            if ($this->isAvailable($name) == true) {
                $result[$name] = $class;
            }
        }

        return $result;        
    }

    /**
     * Return true if driver name is avaliable
     *
     * @param string $driverName
     * @return boolean
     */
    public function isAvailable(string $driverName): bool
    {
        switch ($driverName) {          
            case Self::APCU_DRIVER: {
                return \extension_loaded('apcu');
            }
            case Self::MEMCACHED_DRIVER: {
                return \class_exists('\Memcache');
            }
            case Self::MEMCACHE_DRIVER: {
                return \class_exists('\Memcached');
            }
            case Self::REDIS_DRIVER: {
                return \class_exists('\Redis');
            }
            case Self::PREDIS_DRIVER: {
                return \class_exists('\Predis\Client');
            }
        }

        return true;
    }

    /**
     * Create cache driver
     *
     * @param string|null $name
     * @throws Exception
     * @return Doctrine\Common\Cache\Cache|null
     */
    public function createDriver(?string $name)
    {
        $name = empty($name) ? Self::DEFAULT_DRIVER : $name;
        $class = $this->drivers[$name] ?? null;

        if (empty($class) == true) {
            throw new Exception('Error: cache driver not valid!',1);  
        }

        switch ($name) {
            case Self::FILESYSTEM_DRIVER:               
                return new $class($this->cacheDir);
            case Self::PHPFILE_DRIVER:              
                return new $class($this->cacheDir);
            case Self::MEMCACHED_DRIVER: {                
                $driver = new $class();                     
                $driver->setMemcached(new \Memcached());
                return $driver;
            }
            case Self::MEMCACHE_DRIVER: {                
                $driver = new $class();
                $memcache = new \Memcache();
                $memcache->connect('localhost',11211);
                $driver->setMemcache($memcache);
                return $driver;
            }           
            case Self::REDIS_DRIVER: {                
                return new $class(new \Redis());              
            }
            case Self::PREDIS_DRIVER: {               
                return new $class(new \Predis\Client());             
            }          
        }
        
        return new $class();
    }

    /**
     * Get cache dir
     *
     * @return string
     */
    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    /**
     * Set status true - enabled
     *
     * @param boolean $status
     * @return void
     */
    public function setStatus(bool $status): void
    {      
        $this->status = $status;
    }

    /**
     * Get status
     *
     * @return boolean
     */
    public function getStatus(): bool
    {
        return $this->status;
    }

    /**
     * Return true if cache is disabled
     *
     * @return boolean
     */
    public function isDiabled(): bool
    {
        return !$this->status;
    }

    /**
     * Return cache driver
     *
     * @return Doctrine\Common\Cache\Cache
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * Get cache driver name
     *
     * @return string|null
     */
    public function getDriverName(): ?string
    {
        $driverClass = \get_class($this->driver);
        $drivers = $this->getSupportedDrivers();
        $found = \array_search($driverClass,$drivers);

        return ($found === false) ? null : $found;
    }

    /**
     * Set cache driver
     *
     * @param Doctrine\Common\Cache\Cache|string $driver
     * @throws Exception
     * @return void
     */
    public function setDriver($driver): void
    {
        if ($driver instanceof CacheDriverInterface) {
            $this->driver = $driver;
        } else {
            $driver = $this->createDriver($driver);
            if (empty($driver) == true) {
                throw new Exception('Error: cache driver not valid!', 1);
            }
            $this->driver = $driver;
        }
    }

    /**
     * Read item
     *
     * @param string $id
     * @return mixed|false
     */
    public function fetch(string $id)
    {      
        return ($this->status == true) ? $this->driver->fetch($id) : false;
    }
    
    /**
     * Check if cache contains item
     *
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return ($this->status == true) ? $this->driver->contains($id) : false;
    }

    /**
     * Save cache item
     *
     * @param string $id item id
     * @param mixed $data item data
     * @param integer|null $lifeTime lifetime in minutes
     * @return bool
     */
    public function save(string $id, $data, ?int $lifeTime = null): bool
    {
        $lifeTime = $lifeTime ?? $this->saveTime;

        return ($this->status == true) ? $this->driver->save($id,$data,($lifeTime * 60)) : false;
    }

    /**
     * Delete cache item
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool
    {
        if ($this->status == false) {
            return false;
        }

        if ($this->driver->contains($id) == true) {
            return $this->driver->delete($id);
        }

        return true;
    }

    /**
     * Return cache stats
     *
     * @return array|null
     */
    public function getStats(): ?array
    {
        return ($this->status == true) ? $this->driver->getStats() : null;
    }

    /**
     * Delete all cache items + views cache files and route cache
     *
     * @return bool
     */
    public function clear(): bool
    {       
        $this->driver->deleteAll();
        
        return \Arikaim\Core\Utils\File::deleteDirectory($this->cacheDir);
    }
}
