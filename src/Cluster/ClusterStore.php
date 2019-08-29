<?php

namespace Enflow\Component\Laravel\Cluster;

use Illuminate\Cache\MemcachedStore;

class ClusterStore extends MemcachedStore
{
    public function flush()
    {
        $prefix = $this->getPrefix();

        $keys = $this->memcached->getAllKeys();
        if ($keys === null) {
            throw new \Exception("Unable to fetch memcached keys. Is the service started?");
        }

        $toDelete = [];

        foreach ($keys as $key) {
            if (preg_match('/' . $prefix . '/', $key)) {
                $keyWithoutPrefix = substr($key, strlen($prefix));

                // We should use multiple stores for sessions & cache sepertaly (i.e. netflix/dynamite for redis cache), but unable to install for OpensSSL 1.1.* at the moment for Dynamite
                $isProbablySessionKey = strlen($keyWithoutPrefix) === 40 && ctype_alnum($keyWithoutPrefix);

                if (!$isProbablySessionKey) {
                    $toDelete[] = $key;
                }
            }
        }

        if ($toDelete) {
            $this->memcached->deleteMulti($toDelete);
        }

        return true;
    }
}
