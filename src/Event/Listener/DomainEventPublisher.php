<?php

/**
 * GpsLab component.
 *
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 * @license http://opensource.org/licenses/MIT
 */

namespace GpsLab\Bundle\DomainEvent\Event\Listener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use GpsLab\Bundle\DomainEvent\Service\EventPuller;
use GpsLab\Domain\Event\Bus\EventBus;
use GpsLab\Domain\Event\Event;

class DomainEventPublisher implements EventSubscriber
{
    /**
     * @var EventPuller
     */
    private $puller;

    /**
     * @var EventBus
     */
    private $bus;

    /**
     * @var bool
     */
    private $enable;

    /**
     * @var Event[]
     */
    private $events = [];

    /**
     * @param EventPuller $puller
     * @param EventBus    $bus
     * @param bool        $enable
     */
    public function __construct(EventPuller $puller, EventBus $bus, bool $enable)
    {
        $this->bus = $bus;
        $this->puller = $puller;
        $this->enable = $enable;
    }

    /**
     * @return array
     */
    public function getSubscribedEvents(): array
    {
        if (!$this->enable) {
            return [];
        }

        return [
            Events::onFlush,
            Events::postFlush,
        ];
    }

    /**
     * @param OnFlushEventArgs $args
     */
    public function onFlush(OnFlushEventArgs $args): void
    {
        // aggregate events from deleted entities
        $this->events = $this->puller->pull($args->getObjectManager()->getUnitOfWork());
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args): void
    {
        // aggregate PreRemove/PostRemove events
        $events = array_merge($this->events, $this->puller->pull($args->getObjectManager()->getUnitOfWork()));

        // clear aggregate events before publish it
        // it necessary for fix recursive publish of events
        $this->events = [];

        // flush only if has domain events
        // it necessary for fix recursive handle flush
        if (!empty($events)) {
            foreach ($events as $event) {
                $this->bus->publish($event);
            }

            $args->getObjectManager()->flush();
        }
    }
}
