<?php

declare(strict_types=1);

namespace G41797\Queue\Kafka\Functional;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use Yiisoft\Queue\Adapter\AdapterInterface;
use Yiisoft\Queue\Message\IdEnvelope;
use Yiisoft\Queue\Message\Message;
use Yiisoft\Queue\Message\MessageInterface;

use G41797\Queue\Kafka\Adapter;
use G41797\Queue\Kafka\CheckMessageHandler;
use G41797\Queue\Kafka\NullLoop;

class AdapterTest extends FunctionalTestCase
{

    private ?LoggerInterface $logger = null;
    private function getLogger(): LoggerInterface
    {
        if ($this->logger == null) {
            $this->logger = new NullLogger();
        }
        return $this->logger;
    }

    private ?CheckMessageHandler $handler = null;

    protected function getCallback(): callable
    {
        return [$this->getHandler(), 'handle'];
    }

    protected function getHandler(): CheckMessageHandler
    {
        if ($this->handler == null) {
            $this->handler = new CheckMessageHandler();
        }

        return $this->handler;
    }

    private ?NullLoop $loop = null;
    protected function getLoop(): NullLoop
    {
        if ($this->loop == null) {
            $this->loop = new NullLoop();
        }

        return $this->loop;
    }

    protected function createSubmitter() : AdapterInterface {
        $logger = $this->getLogger();
        return new Adapter(logger: $logger);
    }

    protected function createWorker() : AdapterInterface {
        $logger = $this->getLogger();
        $loop = $this->getLoop();
        return new Adapter(logger: $logger, loop: $loop, timeoutSec: 3.0);
    }

    static function getJob(): MessageInterface {
        return new Message("handler", data: 'data',metadata: []);
    }

    public function pingPong(): void
    {
        $this->reset();

        $job = self::getJob();

        $submitter = $this->createSubmitter();
        $worker = $this->createWorker();

        $submitted = $this->submit($submitter, $job, 1);
        $this->assertEquals(1, count($submitted));
        $this->process($worker, $job, $submitted);

        return;
    }

    protected function submit(AdapterInterface $submitter, MessageInterface $job, int $count = 1000): array
    {
        $ids = [];

        for ($i= 0; $i < $count; $i++) {

            $submitted = $submitter->push($job);

            $this->assertNotNull($submitter);
            $this->assertTrue($submitted instanceof IdEnvelope);
            $this->assertArrayHasKey(IdEnvelope::MESSAGE_ID_KEY, $submitted->getMetadata());
            $id = $submitted->getMetadata()[IdEnvelope::MESSAGE_ID_KEY];
            $this->assertIsString($id);

            $ids[] = $id;
        }

        return $ids;
    }

    protected function process(AdapterInterface $worker, MessageInterface $expectedJob, array $ids): void
    {
        $this->getLoop()->update(count($ids));
        $this->getHandler()->update($expectedJob);

        $worker->subscribe($this->getCallback());

        $this->assertEquals(count($ids), $this->getHandler()->processed());

        return;
    }

    protected function reset(): void
    {
        $this->logger   = null;
        $this->loop     = null;
        $this->handler  = null;
    }

    public function testSubmitterWorker(): void
    {
        $count = 10;

        for ($i = 0; $i < $count; $i++) {
            $this->pingPong();
        }

        return;
    }


}
