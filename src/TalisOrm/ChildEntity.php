<?php

namespace TalisOrm;

/**
 * @see ChildEntityBehavior
 */
interface ChildEntity extends Entity
{
    /**
     * Recreate the child entity, based on the state that was retrieved from the database. This can be expected to be
     * equivalent to the state that was earlier returned by `Entity::state()`. Sample implementation:
     *
     *     $line = new Line();
     *
     *     $line->orderId = new OrderId($state['order_id']);
     *     $line->lineNumber = new LineNumber($state['line_number']);
     *
     *     return $line;
     *
     * @param array<string, mixed> $state
     * @param array<string, mixed> $aggregateState
     * @return static
     */
    public static function fromState(array $state, array $aggregateState);
}
