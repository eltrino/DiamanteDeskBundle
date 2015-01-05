<?php
/*
 * Copyright (c) 2014 Eltrino LLC (http://eltrino.com)
 *
 * Licensed under the Open Software License (OSL 3.0).
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://opensource.org/licenses/osl-3.0.php
 *
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@eltrino.com so we can send you a copy immediately.
 */

/**
 * Created by PhpStorm.
 * User: s3nt1nel
 * Date: 5/1/15
 * Time: 2:49 PM
 */

namespace Diamante\DeskBundle\Tests\Model\Ticket\Filter;


use Diamante\DeskBundle\Api\Command\Filter\FilterBranchesCommand;
use Diamante\DeskBundle\Model\Branch\Filter\BranchFilterCriteriaProcessor;

class BranchFilterCriteriaProcessorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FilterBranchesCommand
     */
    private $branchFilterCommand;

    public function setUp()
    {
        $this->branchFilterCommand = new FilterBranchesCommand();
        $this->branchFilterCommand->name                = 'default';
        $this->branchFilterCommand->description         = 'Default description';
        $this->branchFilterCommand->key                 = 'DB';
        $this->branchFilterCommand->defaultAssignee     = 1;

    }

    /**
     * @test
     */
    public function testGetCriteria()
    {
        $processor = new BranchFilterCriteriaProcessor();
        $processor->setCommand($this->branchFilterCommand);
        $expectedCriteria = array(
            array('name', 'like', 'default'),
            array('defaultAssignee', 'eq', 1),
            array('description', 'like', 'Default description'),
            array('key', 'like', 'DB'),
        );

        $criteria = $processor->getCriteria();

        $this->assertNotEmpty($criteria);
        $this->assertCount(4, $criteria);
        for ($i = 0; $i<count($expectedCriteria); $i++) {
            $this->assertEquals($expectedCriteria[$i], $criteria[$i]);
        }
    }

    /**
     * @test
     */
    public function testGetPagingPropertiesWithDefaultValues()
    {
        $processor = new BranchFilterCriteriaProcessor();
        $processor->setCommand($this->branchFilterCommand);
        $pagingProperties = $processor->getPagingProperties();

        $this->assertInstanceOf('\Diamante\DeskBundle\Model\Shared\Filter\PagingProperties', $pagingProperties);
        $this->assertEquals(25, $pagingProperties->getPerPageCounter());
        $this->assertEquals(1, $pagingProperties->getPageNumber());
        $this->assertEquals('id', $pagingProperties->getOrderByField());
        $this->assertEquals('ASC', $pagingProperties->getSortingOrder());
    }

    /**
     * @test
     */
    public function testGetPagingPropertiesWithModifiedValues()
    {
        $command = new FilterBranchesCommand();
        $command->perPage = 50;
        $command->page = 2;
        $command->orderByField = 'subject';
        $command->sortingOrder = 'DESC';

        $processor = new BranchFilterCriteriaProcessor();
        $processor->setCommand($command);
        $pagingProperties = $processor->getPagingProperties();

        $this->assertInstanceOf('\Diamante\DeskBundle\Model\Shared\Filter\PagingProperties', $pagingProperties);
        $this->assertEquals(50, $pagingProperties->getPerPageCounter());
        $this->assertEquals(2, $pagingProperties->getPageNumber());
        $this->assertEquals('subject', $pagingProperties->getOrderByField());
        $this->assertEquals('DESC', $pagingProperties->getSortingOrder());
    }
}