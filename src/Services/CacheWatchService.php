<?php

namespace Kalpesh\CacheWatcher\Services;

use Closure;
use Exception;
use ReflectionFunction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;

class CacheWatchService
{

    protected $query;
    protected $key;
    protected $tables = [];
    protected $params = [];
    protected $cacheHistory;
    protected $currentPage = 1;
    protected $setting = [];


    public function __construct($setting)
    {
        $this->setting = $setting;
        $this->setHistory();
        $this->currentPage = !blank(request()->query('page')) ? request()->query('page') : 1;
    }

    protected function setHistory()
    {
        $this->cacheHistory = collect(Cache::store($this->setting['store'])->get('CachWatchHistory'));
    }

    public function setQuery(Closure $query)
    {
        $this->query = $query;
        $this->key = $this->generateUid();
        return $this;
    }

    protected function generateUid()
    {
        $reflection  = new ReflectionFunction($this->query);
        $functionName = $reflection->getName();
        $fileName = $reflection->getFileName();
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();
        $docComment = $reflection->getDocComment() ? 'yes' : 'no';
        $this->params = $reflection->getStaticVariables();
        $keyFormat = "$functionName-$fileName-$startLine-$endLine-$docComment-$this->currentPage";
        return md5($keyFormat);
    }


    public function watchModals(array $tables)
    {
        $this->tables = $tables;
        return $this;
    }

    protected function getCacheItemByKey($uid)
    {
        return $this->cacheHistory->where('uid', $uid)->first();
    }

    public function done()
    {
        $data = collect();
        $cahceData = $this->getCacheItemByKey($this->key);
        if ($this->removeItemOnCahngeParams($cahceData)) {
            $cahceData = [];
        }

        if (!blank($cahceData)) {
            $data = $cahceData['data'];
        } else {
            if (is_callable($this->query)) {
                $data = call_user_func($this->query);
                if ($data instanceof LengthAwarePaginator) {
                    $this->currentPage = $data->currentPage();
                }
                $this->storeCacheItem($data);
            } else {
                throw new Exception('Query is not callable.please check');
            }
        }
        return $data;
    }

    protected function storeCacheItem($data)
    {
        $allHistory = $this->cacheHistory;
        $allHistory->push([
            'uid' => $this->key,
            'table' => $this->tables,
            'params' => $this->params,
            "data" => $data,
            "currentPage" => $this->currentPage
        ]);
        Cache::store($this->setting['store'])->put('CachWatchHistory', $allHistory, 1440);
        $this->resetParams();
    }

    public function removeItemOnCahngeParams($cahceData)
    {
        $isRemove = false;
        if (!blank($cahceData) && isset($cahceData['params'])) {
            if (
                $this->params != $cahceData['params']
                ||
                $cahceData['currentPage'] != $this->currentPage
            ) {
                $uid = $cahceData['uid'];
                $updateCache = Cache::store($this->setting['store'])->get('CachWatchHistory')->reject(function ($item) use ($uid) {
                    return $item['uid'] == $uid;
                });
                Cache::store($this->setting['store'])->put('CachWatchHistory', $updateCache, 1440);
                $isRemove =  true;
            }
        }
        return $isRemove;
    }

    public function resetParams()
    {
        $this->query = null;
        $this->key = "";
        $this->tables = [];
        $this->params = [];
        $this->currentPage = 1;
    }
}
