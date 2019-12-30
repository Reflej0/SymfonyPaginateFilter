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

    public function getAll($joins = [], $pageNumber = 1, $pageSize = 10, $filters = [], $order = [], $alias = 'c')
    {
        //Definicion de alias de tabla.
        $a = $alias.'.';
        $query = $this->createQueryBuilder($alias);

        //https://stackoverflow.com/questions/22537734/check-if-alias-exists/27020853
        $pilaAlias = array();
        //Aplicacion de joins
        if(!empty($joins))
        {
            foreach($joins as $j)
            {
                $joins = explode(', ', $j);
                //Itero las tablas por las que voy a joinear.
                foreach($joins as $key => $join)
                {
                    //Si tengo que joinear con una tabla que esta a mas de 1 distancia de la tabla base.
                    if(strpos($join, '.') != FALSE)
                    {
                        $anidado = explode('.', $join);
                        foreach($anidado as $k => $anid)
                        {
                            if(!in_array($anid, $pilaAlias))
                            {
                                //Si estoy en el primer join debo hacer un join de la tabla base con la primera a joinear.
                                if($k == 0)
                                    $query->innerJoin($a.$anid, $anid);
                                //A partir del segundo join, debo joinear con la tabla anterior.
                                else
                                    $query->innerJoin($anidado[$k-1].'.'.$anid, $anid);
                                array_push($pilaAlias, $anid);
                            }
                        }
                    }
                    //Si tengo que joinear con una tabla que esta a 1 distancia de la tabla base.
                    else
                        $query->innerJoin($a.$join, $join);
                }
            }
        }

        //Aplicacion de where.
        if(!empty($filters))
        {
            foreach($filters as $f)
            {
                //Si tengo que filtrar por un join.
                if(array_key_exists("related",$f))
                {
                    //Si tengo que filtrar por un campo que no se encuentra en una tabla que esta a 1 distancia de la tabla base.
                    if(strpos($f['related'], '.') != FALSE)
                    {
                        $b = explode('.', $f['related']);
                        $b = $b[count($b)-1];
                    }
                    //Si tengo que filtrar por un campo que se encuentra en una tabla que esta a 1 de distancia de la tabla base.
                    else
                        $b = $f['related'];
                    if($f['operator'] == 'exact')
                    $query = $query->andWhere($b.'.'.$f['property'].' = :'.str_replace('.','', $f['related']).$f['property'])->setParameter(':'.str_replace('.','', $f['related']).$f['property'], $f['value']);
                    elseif($f['operator'] == 'like')
                        $query = $query->andWhere($b.'.'.$f['property'].' LIKE :'.str_replace('.','', $f['related']).$f['property'])->setParameter(':'.str_replace('.','', $f['related']).$f['property'], "%".$f['value']."%");
                    elseif($f['operator'] == 'between')
                        $query = $query->andWhere($b.'.'.$f['property'].' BETWEEN :'.str_replace('.','', $f['related']).$f['property'].'valueFrom AND :' .str_replace('.','', $f['related']).$f['property'].'valueTo')->setParameter(':'.str_replace('.','', $f['related']).$f['property'].'valueFrom', $f['valueFrom'])->setParameter(':'.str_replace('.','', $f['related']).$f['property'].'valueTo', $f['valueTo']);
                    elseif($f['operator'] == '>' || $f['operator'] == '<')
                        $query = $query->andWhere($b.'.'.$f['property'].' '.$f['operator'].' :'.str_replace('.','', $f['related']).$f['property'])->setParameter(':'.str_replace('.','', $f['related']).$f['property'], $f['value']);
                }
                //Si tengo que filtrar por un campo propio de la entidad.
                else
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
        }

        //Aplicacion de select(*) formato Doctrine.
        $all = "";
        if(!empty($joins))
        {
            foreach($joins as $j)
            {
                if(strpos($j, '.') != FALSE)
                    $all.= ', '.str_replace(".",", ", $j);
                else
                    $all.= ', '.$j;
            }
        }
        
        //Solo visualizo los que estan "enabled"
        $query = $query->andWhere($alias."."."enabled = true");
        
        //Aplicacion de select(*) formato Doctrine.
        $query = $query->select($alias.$all);

        //Aplicacion de orders by.
        if(!empty($order))
        {
                if(array_key_exists("related",$order))
                {
                    $joins = explode(".", $order['related']);
                    $anidamiento = count($joins)-1;
                    $b = $joins[$anidamiento];
                    $query = $query->addOrderBy($b.'.'.$order['property'], $order['type']);
                }
                else
                    $query = $query->addOrderBy($a.$order['property'], $order['type']);
        }

        //Generacion de consulta.
        $query = $query->getQuery();

        //Paginacion de consulta.
        $paginator = $this->paginate($query, $pageNumber, $pageSize);
        return array('paginator' => $paginator, 'query' => $query);
    }

    public function registrosTotales($filters = [], $alias = 'c')
    {
        //Definicion de alias de tabla.
        $a = $alias.'.';
        $query = $this->createQueryBuilder($alias)->select('count('.$a.'id)');

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

        //Generacion de consulta.
        $query = $query->getQuery()->getSingleScalarResult();
        return $query;
    }

    public function getEntitiesBy($filters = [], $alias = 'c')
    {
        //Definicion de alias de tabla.
        $a = $alias . '.';
        $query = $this->createQueryBuilder($alias);

        //Aplicacion de where.
        foreach ($filters as $filter) {
            if ($filter['operator'] == 'exact') {
                $query->andWhere($a . $filter['property'] . ' = :' . $filter['property'])
                    ->setParameter(':' . $filter['property'], $filter['value']);

            } elseif ($filter['operator'] == 'like') {
                $query->andWhere($a . $filter['property'] . ' LIKE :' . $filter['property'])
                    ->setParameter(':' . $filter['property'], "%" . $filter['value'] . "%");

            } elseif ($filter['operator'] == 'between') {
                $query->andWhere($a . $filter['property'] . ' BETWEEN :' . $filter['property'] . 'valueFrom AND :' . $filter['property'] . 'valueTo')
                    ->setParameter(':' . $filter['property'] . 'valueFrom', $filter['valueFrom'])
                    ->setParameter(':' . $filter['property'] . 'valueTo', $filter['valueTo']);

            } elseif ($filter['operator'] == '>' || $filter['operator'] == '<') {
                $query->andWhere($a . $filter['property'] . ' ' . $filter['operator'] . ' :' . $filter['property'])
                    ->setParameter(':' . $filter['property'], $filter['value']);

            }
        }

        return $query->getQuery()->getResult();
    }
}
