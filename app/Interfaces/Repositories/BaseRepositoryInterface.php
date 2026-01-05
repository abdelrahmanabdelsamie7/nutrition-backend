<?php

namespace App\Interfaces\Repositories;

interface BaseRepositoryInterface
{
    public function all(array $columns = ['*']);
    public function find(string $id);
    public function findOrFail(string $id);
    public function findBy(array $criteria, array $columns = ['*']);
    public function findFirst(array $criteria, array $columns = ['*']);
    public function create(array $data);
    public function update(string $id, array $data);
    public function delete(string $id);
    public function paginate(int $perPage = 15, array $columns = ['*']);
    public function with(array $relations);
    public function count(array $criteria = []): int;
    public function exists(array $criteria): bool;
}
