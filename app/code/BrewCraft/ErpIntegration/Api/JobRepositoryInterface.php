<?php
namespace BrewCraft\ErpIntegration\Api;

use BrewCraft\ErpIntegration\Model\Job;

interface JobRepositoryInterface
{
    public function save(Job $job): Job;
}
