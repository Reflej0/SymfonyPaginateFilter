<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;


abstract class CentralRepository extends ServiceEntityRepository
{
    public function paginate($dql, $pageNumber = 1, $pageSize = 10)
    {
        $paginator = new Paginator($dql);

        $paginator->getQuery()
            ->setFirstResult($pageSize * ($pageNumber - 1))
            ->setMaxResults($pageSize);

        return $paginator;
    }

    public function getAll($pageNumber = 1, $pageSize = 10, $filters = [], $orders = [], $alias = 'c')
    {
        //Definicion de alias de tabla.
        $a = $alias.'.';
        $query = $this->createQueryBuilder($alias);

        //Aplicacion de where.

        if(!empty($filters))
        {
            foreach($filters as $f)
            {
                if($f['operator'] == 'exact')
                    $query = $query->andWhere($a.$f['property'].' = :'.$f['property'])->setParameter(':'.$f['property'], $f['value']);
                elseif($f['operator'] == 'like')
                    $query = $query->andWhere($a.$f['property'].' LIKE :'.$f['property'])->setParameter(':'.$f['property'], "%".$f['value']."%");
                elseif($f['operator'] == 'between')
                    $query = $query->andWhere($a.$f['property'].' BETWEEN :'.$f['property'].'valueFrom AND :' .$f['property'].'valueTo')->setParameter(':'.$f['property'].'valueFrom', $f['valueFrom'])->setParameter(':'.$f['property'].'valueTo', $f['valueTo']);
                elseif($f['operator'] == '>' || $f['operator'] == '<')
                    $query = $query->andWhere($a.$f['property'].' '.$f['operator'].' :'.$f['property'])->setParameter(':'.$f['property'], $f['value']);
            }
        }

        //Aplicacion de orders by.
        if(!empty($orders))
            foreach($orders as $o)
                $query = $query->addOrderBy($a.$o['property'], $o['type']);

        //Generacion de consulta.
        $query = $query->getQuery();

        //Paginacion de consulta.
        $paginator = $this->paginate($query, $pageNumber, $pageSize);
        return array('paginator' => $paginator, 'query' => $query);
    }
}
