<?php

namespace TalisOrm\AggregateRepositoryTest;

use DateTimeImmutable;
use Doctrine\DBAL\Schema\Schema;
use TalisOrm\Aggregate;
use TalisOrm\AggregateBehavior;
use TalisOrm\AggregateId;
use TalisOrm\ChildEntity;
use TalisOrm\DateTimeUtil;
use TalisOrm\DomainEvents\EventRecordingCapabilities;
use TalisOrm\Schema\SpecifiesSchema;
use Webmozart\Assert\Assert;

/**
 * @phpstan-implements Aggregate<Order>
 */
final class Order implements Aggregate, SpecifiesSchema
{
    use AggregateBehavior;

    /**
     * @var OrderId
     */
    private $orderId;

    /**
     * @var DateTimeImmutable
     */
    private $orderDate;

    /**
     * @var Line[]
     */
    private $lines = [];

    /**
     * @var int
     */
    private $quantityPrecision;

    private function __construct()
    {
    }

    /**
     * @param OrderId $orderId
     * @param DateTimeImmutable $orderDate
     * @return Order
     */
    public static function create(OrderId $orderId, DateTimeImmutable $orderDate, int $quantityPrecision)
    {
        $order = new self();

        $order->orderId = $orderId;
        $order->orderDate = $orderDate;
        $order->quantityPrecision = $quantityPrecision;

        $order->recordThat(new OrderCreated());

        return $order;
    }

    /**
     * @param DateTimeImmutable $orderDate
     * @return void
     */
    public function update(DateTimeImmutable $orderDate)
    {
        $this->orderDate = $orderDate;

        $this->recordThat(new OrderUpdated());
    }

    /**
     * @param LineNumber $lineId
     * @param ProductId $productId
     * @param Quantity $quantity
     * @return void
     */
    public function addLine(LineNumber $lineId, ProductId $productId, Quantity $quantity)
    {
        $this->lines[] = Line::create($this->orderId, $lineId, $productId, $quantity, $this->quantityPrecision);

        $this->recordThat(new LineAdded());
    }

    /**
     * @param LineNumber $lineId
     * @param ProductId $productId
     * @param Quantity $quantity
     * @return void
     */
    public function updateLine(LineNumber $lineId, ProductId $productId, Quantity $quantity)
    {
        foreach ($this->lines as $index => $line) {
            if ($line->lineNumber()->asInt() === $lineId->asInt()) {
                $line->update($productId, $quantity);
            }
        }

        $this->recordThat(new LineUpdated());
    }

    /**
     * @param LineNumber $lineId
     * @return void
     */
    public function deleteLine(LineNumber $lineId)
    {
        foreach ($this->lines as $index => $line) {
            if ($line->lineNumber()->asInt() === $lineId->asInt()) {
                unset($this->lines[$index]);
                $this->deleteChildEntity($line);
            }
        }

        $this->recordThat(new LineDeleted());
    }

    /**
     * @return OrderId
     */
    public function orderId()
    {
        return $this->orderId;
    }

    public function childEntitiesByType(): array
    {
        return [
            Line::class => $this->lines
        ];
    }

    public static function childEntityTypes(): array
    {
        return [
            Line::class
        ];
    }

    public function state(): array
    {
        $this->aggregateVersion++;

        return [
            'order_id' => $this->orderId->orderId(),
            'company_id' => $this->orderId->companyId(),
            'order_date' => $this->orderDate->format('Y-m-d'),
            Aggregate::VERSION_COLUMN => $this->aggregateVersion
        ];
    }

    public static function fromState(array $aggregateState, array $childEntitiesByType)
    {
        $order = new self();

        $order->orderId = new OrderId($aggregateState['order_id'], (int)$aggregateState['company_id']);
        $dateTimeImmutable = DateTimeUtil::createDateTimeImmutable($aggregateState['order_date']);

        if (!$dateTimeImmutable instanceof DateTimeImmutable) {
            throw new \RuntimeException('Invalid date string from database');
        }
        $order->orderDate = $dateTimeImmutable;

        $order->lines = $childEntitiesByType[Line::class];

        $order->aggregateVersion = (int)$aggregateState[Aggregate::VERSION_COLUMN];

        $order->quantityPrecision = $aggregateState['quantityPrecision'];

        return $order;
    }

    public static function tableName(): string
    {
        return 'orders';
    }

    public function identifier(): array
    {
        return [
            'order_id' => $this->orderId->orderId(),
            'company_id' => $this->orderId->companyId()
        ];
    }

    public static function identifierForQuery(AggregateId $aggregateId): array
    {
        Assert::isInstanceOf($aggregateId, OrderId::class);
        /** @var OrderId $aggregateId */

        return [
            'order_id' => $aggregateId->orderId(),
            'company_id' => $aggregateId->companyId()
        ];
    }

    public static function specifySchema(Schema $schema): void
    {
        $table = $schema->createTable('orders');
        $table->addColumn('order_id', 'string');
        $table->addColumn('company_id', 'integer');
        $table->addColumn('order_date', 'date');
        $table->addColumn(Aggregate::VERSION_COLUMN, 'integer');
        $table->setPrimaryKey(['order_id', 'company_id']);

        Line::specifySchema($schema);
    }

    /**
     * @param int $aggregateVersion
     * @return void
     */
    public function setAggregateVersion($aggregateVersion)
    {
        Assert::integer($aggregateVersion);
        $this->aggregateVersion = $aggregateVersion;
    }

    /**
     * @return array&Line[]
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function quantityPrecision(): int
    {
        return $this->quantityPrecision;
    }
}
