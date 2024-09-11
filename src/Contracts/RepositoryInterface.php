<?php

namespace RiseTech\Repository\Contracts;
interface RepositoryInterface
{
    public function getAll();
    public function findById($id);
    public function findWhere($column, $valor);
    public function findWhereEmail($valor);
    public function findWhereFirst($column, $valor);
    public function store(array $data);
    public function update($id, array $data);
    public function createOrUpdate($id, array $data);
    public function delete($id);
    public function destroy($id);
    public function recovery($id);
    public function relationships(...$relationships);
    public function paginate($totalPage = 10);
    public function dataTable();
    public function orderBy($column, $order = 'DESC');
}
