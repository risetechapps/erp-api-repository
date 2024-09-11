<?php

namespace RiseTech\Repository\Core;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use RiseTech\Repository\Contracts\RepositoryInterface;
use Risetech\Repository\Exception\NotEntityDefinedException;
use RiseTech\Repository\Jobs\RegenerateCacheJob;
use RiseTech\Repository\Repository;

abstract class BaseRepository implements RepositoryInterface
{
    protected $entity;
    protected Carbon $tll;
    protected $driver;
    protected bool $supportTag = false;

    /**
     * @throws NotEntityDefinedException
     */
    public function __construct()
    {
        $this->entity = $this->resolveEntity();
        $this->tll = Carbon::now()->addHours(24);
        $this->driver = Cache::getDefaultDriver();
        $this->supportTag = !in_array($this->driver, Repository::$driverNotSupported);
    }

    /**
     * @throws NotEntityDefinedException
     */
    public function resolveEntity()
    {
        if (!method_exists($this, 'entity')) {
            throw new NotEntityDefinedException;
        }
        return app($this->entity());
    }

    public function Trashed(): bool
    {
        if ($this->containsSoftDelete()) {
            // Implementa validação de usuário, se necessário.
        }
        return false;
    }

    public function containsSoftDelete(): bool
    {
        return collect(class_uses_recursive($this->entity))->contains(SoftDeletes::class);
    }

    private function getQualifyTagCache(string $method, array $parameters = []): string
    {
        $paramsHash = !empty($parameters) ? '_' . md5(json_encode($parameters)) : '';
        $name = DIRECTORY_SEPARATOR . $method . $paramsHash;

        if ($this->Trashed()) $name .= '_TRASHED';

        return $name;
    }

    private function rememberCache(callable $call, string $method, array $parameters = [])
    {
        $cacheKey = $this->getQualifyTagCache($method, $parameters);

        if ($this->supportTag) {
            return Cache::tags([get_class($this->entity)])->remember($cacheKey, $this->tll, $call);
        } else {
            return Cache::remember($cacheKey, $this->tll, $call);
        }
    }

    public function clearCacheForMethod(string $method, array $parameters = []): void
    {
        $cacheKey = $this->getQualifyTagCache($method, $parameters);

        if($this->supportTag){
            Cache::tags([get_class($this->entity)])->forget($cacheKey);
        }else{
            Cache::forget($cacheKey);
        }

        dispatch(new RegenerateCacheJob($this, $method, $parameters));

    }

    public function clearCacheForEntity(): void
    {
        if($this->supportTag){
            Cache::tags([get_class($this->entity)])->flush();
        }
        dispatch(new RegenerateCacheJob($this, 'getAll'));

    }

    public function getAll()
    {
        return $this->rememberCache(function () {
            return $this->Trashed() ? $this->entity->withTrashed(true)->get() : $this->entity->all();
        }, Repository::$methodAll);
    }

    public function findById($id)
    {
        return $this->rememberCache(function () use ($id) {
            return $this->entity->withTrashed($this->Trashed())->find($id);
        }, Repository::$methodFind, [$id]);
    }

    public function findWhere($column, $valor)
    {
        return $this->rememberCache(function () use ($column, $valor) {
            return $this->entity->withTrashed($this->Trashed())->where($column, $valor)->get();
        }, Repository::$methodFindWhere, [$column, $valor]);
    }

    public function findWhereEmail($valor)
    {
        return $this->rememberCache(function () use ($valor) {
            return $this->entity->withTrashed($this->Trashed())->where('email', $valor)->get();
        }, Repository::$methodFindWhereEmail, [$valor]);
    }

    public function findWhereFirst($column, $valor)
    {
        return $this->rememberCache(function () use ($column, $valor) {
            return $this->entity->withTrashed($this->Trashed())->where($column, $valor)->first();
        }, Repository::$methodFindWhereFirst, [$column, $valor]);
    }

    public function store(array $data)
    {
        $created = $this->entity->create($data);
        $this->clearCacheForEntity();
        return $created;
    }

    public function update($id, array $data)
    {
        $updated = $this->findById($id)->update($data);
        $this->clearCacheForMethod('FIND', [$id]);
        $this->clearCacheForEntity();

        return $updated;
    }

    public function createOrUpdate($id, array $data)
    {
        if ($this->findById($id) == null) {
            return $this->store($data);
        } else {
            return $this->update($id, $data);
        }
    }

    public function delete($id)
    {
        $deleted = $this->entity->find($id)->delete();

        $this->clearCacheForMethod('FIND', [$id]);
        $this->clearCacheForEntity();

        return $deleted;
    }

    public function destroy($id)
    {
        $destroyed = $this->findById($id)->forceDelete();

        $this->clearCacheForMethod('FIND', [$id]);
        $this->clearCacheForEntity();

        return $destroyed;
    }

    public function recovery($id)
    {
        $restored = $this->findById($id)->restore();

        $this->clearCacheForMethod('FIND', [$id]);
        $this->clearCacheForEntity();

        return $restored;
    }

    public function relationships(...$relationships)
    {
        return $this->entity = $this->entity->with($relationships);
    }

    public function paginate($totalPage = 10)
    {
        $data = $this->entity->withTrashed($this->Trashed())->paginate($totalPage);
        return [
            'data' => $data->items(),
            'recordsFiltered' => 0,
            'recordsTotal' => $data->total(),
            'totalPages' => $data->lastPage(),
            'perPage' => $data->perPage()
        ];
    }

    public function dataTable()
    {
        return $this->rememberCache(function () {
            return $this->entity->withTrashed($this->Trashed())->get();
        }, Repository::$methodDataTable);
    }

    public function orderBy($column, $order = 'DESC')
    {
        if (mb_strtoupper($order) != 'DESC' && mb_strtoupper($order) != 'ASC') {
            $order = 'ASC';
        }

        return $this->rememberCache(function () use ($column, $order) {
            $this->entity->withTrashed($this->Trashed())->orderBy($column, $order)->get();
        }, Repository::$methodOrder);
    }
}
