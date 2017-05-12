<?php

namespace LiteCQRS\EventStore;

use LiteCQRS\AggregateRootNotFoundException;
use LiteCQRS\AggregateRoot;
use LiteCQRS\DefaultDomainEvent;

use Rhumsaa\Uuid\Uuid;

class EventSourceRepositoryTest extends \PHPUnit_Framework_TestCase
{
    private $eventBus;

    public function setUp()
    {
        $this->eventBus = \Phake::mock('LiteCQRS\Eventing\EventMessageBus');
    }

    /**
     * @test
     */
    public function it_returns_aggregate_root_loaded_from_event_stream()
    {
        $uuid = Uuid::uuid4();
        $eventStream = new EventStream('LiteCQRS\EventStore\EventSourcedAggregate', $uuid, array(new TestEvent()));

        $eventStore = $this->mockEventStoreReturning($uuid, $eventStream);
        $repository = new EventSourceRepository($eventStore, $this->eventBus);

        $entity = $repository->find('LiteCQRS\EventStore\EventSourcedAggregate', $uuid);

        $this->assertTrue($entity->eventApplied);
        $this->assertSame($uuid, $entity->getId());
    }

    /**
     * @test
     */
    public function it_throws_not_found_exception_when_classnames_missmatch()
    {
        $uuid = Uuid::uuid4();
        $eventStream = new EventStream('LiteCQRS\EventStore\EventSourcedAggregate', $uuid, array(new TestEvent()));

        $eventStore = $this->mockEventStoreReturning($uuid, $eventStream);
        $repository = new EventSourceRepository($eventStore, $this->eventBus);

        $this->setExpectedException('LiteCQRS\AggregateRootNotFoundException');

        $entity = $repository->find('stdClass', $uuid);
    }

    /**
     * @test
     */
    public function it_throws_not_found_exception_when_no_eventstream_found()
    {
        $uuid = Uuid::uuid4();

        $eventStore = \Phake::mock('LiteCQRS\EventStore\EventStore');
        \Phake::when($eventStore)->find($uuid)->thenThrow(new AggregateRootNotFoundException());

        $repository = new EventSourceRepository($eventStore, $this->eventBus);

        $this->setExpectedException('LiteCQRS\AggregateRootNotFoundException');

        $repository->find('stdClass', $uuid);
    }

    protected function mockEventStoreReturning($uuid, $eventStream)
    {
        $eventStore = \Phake::mock('LiteCQRS\EventStore\EventStore');

        \Phake::when($eventStore)->find($uuid)->thenReturn($eventStream);

        return $eventStore;
    }

    /**
     * @test
     */
    public function it_commits_eventstream_when_adding_aggregate()
    {
        $id     = Uuid::uuid4();
        $object = new EventSourcedAggregate($id);
        $event  = new TestEvent();
        $tx     = new Transaction(new EventStream('foo', $object->getId()), array($event));

        $eventStore = \Phake::mock('LiteCQRS\EventStore\EventStore');
        $repository = new EventSourceRepository($eventStore, $this->eventBus);

        \Phake::when($eventStore)
            ->commit($this->isInstanceOf('LiteCQRS\EventStore\EventStream'))
            ->thenReturn($tx);

        $repository->save($object);

        \Phake::verify($this->eventBus)->publish($event);

        $this->assertEquals($id, $event->getAggregateId());
    }

    /**
     * @test
     */
    public function it_throws_concurrency_exception_when_versions_missmatch()
    {
        $uuid = Uuid::uuid4();
        $eventStream = new EventStream('LiteCQRS\EventStore\EventSourcedAggregate', $uuid);

        $eventStore = $this->mockEventStoreReturning($uuid, $eventStream);

        $this->setExpectedException('LiteCQRS\EventStore\ConcurrencyException');

        $repository = new EventSourceRepository($eventStore, $this->eventBus);
        $repository->find('LiteCQRS\EventStore\EventSourcedAggregate', $uuid, 1337);
    }
}

class EventSourcedAggregate extends AggregateRoot
{
    public $eventApplied = false;

    public function __construct(Uuid $uuid)
    {
        $this->setId($uuid);
    }

    protected function applyTest(TestEvent $event)
    {
        $this->eventApplied = true;
    }
}

class TestEvent extends DefaultDomainEvent
{
}
