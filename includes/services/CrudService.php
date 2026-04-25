<?php

class CrudService
{
    private CrudRepository $repository;

    public function __construct()
    {
        $this->repository = new CrudRepository();
    }

    public function paginate(string $table, array $filters = [], int $page = 1, int $perPage = 15, string $orderBy = 'id DESC'): array
    {
        $pager = paginate($page, $perPage);
        return $this->repository->paginate($table, $filters, $pager['limit'], $pager['offset'], $orderBy);
    }

    public function find(string $table, int $id): ?array
    {
        return $this->repository->find($table, $id);
    }

    public function save(string $table, array $data, ?int $id = null): int
    {
        return $this->repository->save($table, $data, $id);
    }

    public function delete(string $table, int $id): void
    {
        $this->repository->softDelete($table, $id);
    }
}
