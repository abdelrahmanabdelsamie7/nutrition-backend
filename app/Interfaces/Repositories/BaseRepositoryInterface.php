<?php

namespace App\Interfaces\Repositories;

interface BaseRepositoryInterface
{
    public function all(array $columns = ['*']);
    public function find(int $id);
    public function findOrFail(int $id);
    public function findBy(array $criteria, array $columns = ['*']);
    public function findFirst(array $criteria, array $columns = ['*']);
    public function create(array $data);
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function paginate(int $perPage = 15, array $columns = ['*']);
    public function with(array $relations);
    public function count(array $criteria = []): int;
    public function exists(array $criteria): bool;
}
