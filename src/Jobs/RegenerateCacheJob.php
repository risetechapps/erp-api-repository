<?php

namespace RiseTech\Repository\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RegenerateCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $repository;
    protected $method;
    protected $parameters;

    public function __construct($repository, string $method, array $parameters = [])
    {
        $this->repository = $repository;
        $this->method = $method;
        $this->parameters = $parameters;
    }

    public function handle()
    {
        $method = $this->method;
        $this->repository->$method(...$this->parameters);
    }
}
