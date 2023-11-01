<?php

namespace Kalpesh\CacheWatcher;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Kalpesh\CacheWatcher\Services\CacheWatchService;

class CacheWatchServiceProvider extends ServiceProvider
{
    protected $setting;
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->setting = $this->app['config']->get('cache-watcher');


        $this->app->bind("CacheWatch", function () {
            return new CacheWatchService($this->setting);
        });

        if (!file_exists(config_path('cache-watcher.php'))) {
            $this->publishes([
                __DIR__ . '/Config/cache-watcher.php' => config_path("cache-watcher.php")
            ], "config");
        }

        $modals  = $this->getModelNames();
        if (!blank($modals)) {
            foreach ($modals as $modal) {
                $this->registerModelObserver($modal);
            }
        }
    }


    protected function registerModelObserver($modelName)
    {
        $modelClass = 'App\\Models\\' . $modelName;

        $modelClass::created(function ($model) use ($modelName) {
            $this->deleteCahce($modelName);
        });

        $modelClass::updated(function ($model) use ($modelName) {
            $this->deleteCahce($modelName);
        });

        $modelClass::deleted(function ($model)  use ($modelName) {
            $this->deleteCahce($modelName);
        });
    }

    public function deleteCahce($modelName)
    {
        if (Cache::store($this->setting['store'])->has('CachWatchHistory')) {
            $getCache = Cache::store($this->setting['store'])->get('CachWatchHistory')->toArray();
            if (!blank($getCache)) {
                foreach ($getCache as $key =>  $item) {
                    if (!blank($item['table']) && in_array($modelName, $item['table'])) {
                        unset($getCache[$key]);
                    }
                }
            }
            Cache::store($this->setting['store'])->put('CachWatchHistory', collect(array_values($getCache)), 1440);
        }
    }


    private function getModelNames()
    {
        $models = [];
        $files = File::allFiles(app_path());

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            if (Str::contains($contents, 'Illuminate\Database\Eloquent\Model')) {
                $className = $this->getClassName($contents);
                if ($className) {
                    $models[] = $className;
                }
            }
        }

        return $models;
    }


    private function getClassName($contents)
    {
        if (preg_match('/class\s+(\w+)/', $contents, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
