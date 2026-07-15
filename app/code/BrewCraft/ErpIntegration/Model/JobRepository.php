<?php
namespace BrewCraft\ErpIntegration\Model;

use BrewCraft\ErpIntegration\Api\JobRepositoryInterface;
use BrewCraft\ErpIntegration\Model\ResourceModel\Job as ResourceModel;

class JobRepository implements JobRepositoryInterface
{
    public function __construct(
        private readonly ResourceModel $resource
    ) {
    }

    public function save(Job $job): Job
    {
        $this->resource->save($job);

        return $job;
    }
}
