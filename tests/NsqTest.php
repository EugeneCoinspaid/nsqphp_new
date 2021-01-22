<?php

declare(strict_types=1);

use Nsq\Message;
use Nsq\Subscriber;
use Nsq\Writer;
use Nsq\Exception;
use PHPUnit\Framework\TestCase;

final class NsqTest extends TestCase
{
    public function test(): void
    {
        $writer = new Writer('tcp://localhost:4150');
        $writer->pub(__FUNCTION__, __FUNCTION__);

        $reader = new \Nsq\Reader('tcp://localhost:4150');
        $subscriber = new Subscriber($reader);
        $generator = $subscriber->subscribe(__FUNCTION__, __FUNCTION__, 1);

        /** @var null|Message $envelope */
        $envelope = $generator->current();

        static::assertInstanceOf(Message::class, $envelope);
        static::assertSame(__FUNCTION__, $envelope->body);
        $envelope->finish();

        $generator->next();
        static::assertNull($generator->current());

        $writer->mpub(__FUNCTION__, [
            'First mpub message.',
            'Second mpub message.',
        ]);

        $generator->next();
        /** @var null|Message $envelope */
        $envelope = $generator->current();
        static::assertInstanceOf(Message::class, $envelope);
        static::assertSame('First mpub message.', $envelope->body);
        $envelope->finish();

        $generator->next();
        /** @var null|Message $envelope */
        $envelope = $generator->current();
        static::assertInstanceOf(Message::class, $envelope);
        static::assertSame('Second mpub message.', $envelope->body);
        $envelope->requeue(0);

        $generator->next();
        /** @var null|Message $envelope */
        $envelope = $generator->current();
        static::assertInstanceOf(Message::class, $envelope);
        static::assertSame('Second mpub message.', $envelope->body);
        $envelope->finish();

        $writer->dpub(__FUNCTION__, 2000, 'Deferred message.');

        $generator->next();
        /** @var null|Message $envelope */
        $envelope = $generator->current();
        static::assertNull($envelope);

        $generator->send(Subscriber::CHANGE_TIMEOUT);
        $generator->send(10.0);

        /** @var null|Message $envelope */
        $envelope = $generator->current();
        static::assertInstanceOf(Message::class, $envelope);
        static::assertSame('Deferred message.', $envelope->body);
        $envelope->finish();

        static::assertFalse($reader->isClosed());
        $generator->send(Subscriber::STOP);
        static::assertTrue($reader->isClosed());
    }

    /**
     * @dataProvider pubFails
     */
    public function testPubFail(string $topic, string $body, string $exceptionMessage): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($exceptionMessage);

        $writer = new Writer('tcp://localhost:4150');
        $writer->pub($topic, $body);
    }

    /**
     * @return Generator<string, array>
     */
    public function pubFails(): Generator
    {
        yield 'Empty body' => ['test', '', 'E_BAD_MESSAGE PUB invalid message body size 0'];
        yield 'Invalid topic' => ['test$%^&', '', 'E_BAD_TOPIC PUB topic name "test$%^&" is not valid'];
    }
}
