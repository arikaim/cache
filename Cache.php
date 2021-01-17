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

use Arikaim\Core\Utils\File;
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
    const APCU_DRIVER       = 'apcu';
    const ARRAY_DRIVER      = 'array';
    const VOID_DRIVER       = 'void';
    const MEMCACHED_DRIVER  = 'memcached';
    
    /**
     * Drivers list
     *
     * @var array
     */
    protected $drivers = [
        Self::FILESYSTEM_DRIVER => 'Doctrine\Common\Cache\FilesystemCache',
        Self::APCU_DRIVER       => 'Doctrine\Common\Cache\ApcuCache',
        Self::MEMCACHED_DRIVER  => 'Doctrine\Common\Cache\MemcachedCache',
        Self::ARRAY_DRIVER      => 'Doctrine\Common\Cache\ArrayCache',
        Self::VOID_DRIVER       => 'Doctrine\Common\Cache\VoidCache'
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
     * Router cache file name
     *
     * @var string|null
     */
    private $routerCacheFile;

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
     * Constructor
     *
     * @param string $cacheDir   
     * @param string|null $routerCacheFile
     * @param string|null $driverName
     * @param boolean $status
     */
    public function __construct(
        string $cacheDir, 
        ?string $routerCacheFile, 
        ?string $driverName = null, 
        bool $status = false
    )
    {
        $this->status = $status;
        $this->cacheDir = $cacheDir;
        $this->routerCacheFile = $routerCacheFile;
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
                return (\ini_get('apc.enabled') && \extension_loaded('apc'));
            }
            case Self::MEMCACHED_DRIVER: {
                return \class_exists('Memcache');
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
        $name = (empty($name) == true) ? Self::DEFAULT_DRIVER : $name;

        switch ($name) {
            case Self::FILESYSTEM_DRIVER:
                return new \Doctrine\Common\Cache\FilesystemCache($this->cacheDir);
            case Self::APCU_DRIVER:
                return new \Doctrine\Common\Cache\ApcuCache();           
            case Self::ARRAY_DRIVER:
                return new \Doctrine\Common\Cache\ArrayCache();
            case Self::MEMCACHED_DRIVER: {                
                $driver = new \Doctrine\Common\Cache\MemcachedCache();
                $memcachedClass = '\Memcached';
                $memcached = (\class_exists($memcachedClass) == true) ? new $memcachedClass() : null;
                $driver->setMemcached($memcached);

                return $driver;
            }
            case Self::VOID_DRIVER:
                return new \Doctrine\Common\Cache\VoidCache();
            default:
                throw new Exception('Error: cache driver not valid!',1);  
        }
        
        return null;
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
     * @return mixed|null
     */
    public function fetch(string $id)
    {      
        return ($this->status == true) ? $this->driver->fetch($id) : null;
    }
    
    /**
     * Check if cache contains item
     *
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return $this->driver->contains($id);
    }

    /**
     * Save cache item
     *
     * @param string $id item id
     * @param mixed $data item data
     * @param integer $lifeTime lifetime in minutes
     * @return bool
     */
    public function save(string $id, $data, int $lifeTime = 1): bool
    {
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
        $this->clearRouteCache();
        
        return File::deleteDirectory($this->cacheDir);
    }

    /**
     * Return true if route ceche file exist
     *
     * @return boolean
     */
    public function hasRouteCache(): bool
    {
        return (empty($this->routerCacheFile) == true) ? false : File::exists($this->routerCacheFile);
    }

    /**
     * Delete route cache items and route cache file
     *
     * @return bool
     */
    public function clearRouteCache(): bool
    {
        $this->delete('routes.list');

        return (empty($this->routerCacheFile) == true) ? true : File::delete($this->routerCacheFile);
    }
}
