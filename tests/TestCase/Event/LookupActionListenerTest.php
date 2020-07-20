<?php

namespace App\Test\TestCase\Event;

use App\Event\Controller\Api\LookupActionListener;
use App\Event\EventName;
use Cake\Controller\Controller;
use Cake\Event\Event;
use Cake\Http\ServerRequest;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class LookupActionListenerTest extends TestCase
{
    private $Users;

    private $Things;

    public $fixtures = [
        'app.Users',
        'app.Things',
        'plugin.Groups.Groups',
        'plugin.Groups.GroupsUsers',
    ];

    public function setUp()
    {
        parent::setUp();

        $this->Users = TableRegistry::getTableLocator()->get('Users');
        $this->Things = TableRegistry::getTableLocator()->get('Things');
    }

    public function testBeforeLookupEmptyQuery(): void
    {
        $query = $this->Users->find('all');
        $controller = new Controller($this->getRequest(), null, 'Users');
        $event = new Event(
            (string)EventName::API_LOOKUP_BEFORE_FIND(),
            $controller
        );

        $listener = new LookupActionListener();
        $listener->beforeLookup($event, $query);
        $this->assertFalse($query->isEmpty());
    }

    public function testBeforeLookupTypeAheadFields(): void
    {
        $query = $this->Things->find('all');
        $controller = new Controller($this->getRequest(['query' => 'Thing #']), null, 'Things');
        $event = new Event(
            (string)EventName::API_LOOKUP_BEFORE_FIND(),
            $controller
        );

        $listener = new LookupActionListener();
        $listener->beforeLookup($event, $query);
        $this->assertEquals(2, $query->count());
    }

    public function testBeforeLookupWithQuery(): void
    {
        $query = $this->Users->find('all');
        $controller = new Controller($this->getRequest(['query' => 'user-1']), null, 'Users');
        $event = new Event(
            (string)EventName::API_LOOKUP_BEFORE_FIND(),
            $controller
        );

        $listener = new LookupActionListener();
        $listener->beforeLookup($event, $query);
        $this->assertEquals(1, $query->count());
    }

    /**
     * @param mixed[] $query Query parameters
     */
    private function getRequest(array $query = []): ServerRequest
    {
        return new ServerRequest([
            'params' => [
                'controller' => 'Users',
                'action' => 'lookup',
                'pass' => [],
                'prefix' => 'api/v1/v0',
                'plugin' => null,
                '_ext' => 'json',
                '_matchedRoute' => '/api/:controller/:action/*',
                'isAjax' => true,
            ],
            'query' => $query,
        ]);
    }
}
