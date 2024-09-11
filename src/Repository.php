<?php

namespace RiseTech\Repository;

class Repository
{
    public static array $driverNotSupported = ["file"];


    public static string $methodAll = 'ALL';
    public static string $methodFind = 'FIND';
    public static string $methodFindWhere = 'FIND_WHERE';
    public static string $methodFindWhereEmail = 'FIND_WHERE_EMAIL';
    public static string $methodFindWhereFirst = 'FIND_WHERE_FIRST';
    public static string $methodDataTable = 'DATATABLE';
    public static string $methodOrder = 'ORDER';
}
