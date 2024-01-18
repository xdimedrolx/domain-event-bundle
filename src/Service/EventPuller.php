<?php

/**
 * GpsLab component.
 *
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 * @license http://opensource.org/licenses/MIT
 */

namespace GpsLab\Bundle\DomainEvent\Service;

use Doctrine\Common\Persistence\Proxy as CommonProxy;
use Doctrine\Persistence\Proxy;
use Doctrine\ORM\UnitOfWork;
use GpsLab\Domain\Event\Aggregator\AggregateEvents;
use GpsLab\Domain\Event\Event;

class EventPuller
{
    /**
     * @param UnitOfWork $uow
     *
     * @return Event[]
     */
    public function pull(UnitOfWork $uow): array
    {
        $events = [];

        $events[] = $this->pullFromEntities($uow->getScheduledEntityDeletions());
        $events[] = $this->pullFromEntities($uow->getScheduledEntityInsertions());
        $events[] = $this->pullFromEntities($uow->getScheduledEntityUpdates());

        // other entities
        foreach ($uow->getIdentityMap() as $entities) {
            $events[] = $this->pullFromEntities($entities);
        }

        return array_merge(...$events);
    }

    /**
     * @param array $entities
     *
     * @return Event[]
     */
    private function pullFromEntities(array $entities): array
    {
        $events = [];

        foreach ($entities as $entity) {
            // ignore Doctrine not initialized proxy classes
            // proxy class can't have a domain events
            if (
                ($entity instanceof Proxy && !$entity->__isInitialized()) ||
                ($entity instanceof CommonProxy && !$entity->__isInitialized())
            ) {
                continue;
            }

            if ($entity instanceof AggregateEvents) {
                $events[] = $entity->pullEvents();
            }
        }

        return array_merge(...$events);
    }
}
