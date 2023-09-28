<?php

namespace ConfigCat\Tests;

use ConfigCat\ClientOptions;
use ConfigCat\ConfigCatClient;
use ConfigCat\Hooks;
use ConfigCat\Log\InternalLogger;
use ConfigCat\Log\LogLevel;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;

class LoggerTest extends TestCase
{
    public function testLoggerBypassesInternalLogicWhenGlobalLevelIsZero()
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $logger = new InternalLogger($mockLogger, 0, [InvalidArgumentException::class], new Hooks());

        $mockLogger
            ->expects(self::once())
            ->method('emergency')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('alert')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('critical')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('error')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('warning')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('notice')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('info')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('debug')
        ;

        $logger->emergency('');
        $logger->alert('');
        $logger->critical('');
        $logger->error('');
        $logger->notice('');
        $logger->info('');
        $logger->debug('');
        $logger->warning('');
    }

    public function testLoggerLogOnlyHigherLevelThanDebug()
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $logger = new InternalLogger($mockLogger, LogLevel::INFO, [], new Hooks());

        $mockLogger
            ->expects(self::once())
            ->method('emergency')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('alert')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('critical')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('error')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('warning')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('notice')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('info')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('debug')
        ;

        $logger->emergency('');
        $logger->alert('');
        $logger->critical('');
        $logger->error('');
        $logger->notice('');
        $logger->info('');
        $logger->debug('');
        $logger->warning('');
    }

    public function testLoggerLogOnlyHigherLevelThanInfo()
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $logger = new InternalLogger($mockLogger, LogLevel::NOTICE, [], new Hooks());

        $mockLogger
            ->expects(self::once())
            ->method('emergency')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('alert')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('critical')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('error')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('warning')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('notice')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('info')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('debug')
        ;

        $logger->emergency('');
        $logger->alert('');
        $logger->critical('');
        $logger->error('');
        $logger->notice('');
        $logger->info('');
        $logger->debug('');
        $logger->warning('');
    }

    public function testLoggerLogOnlyHigherLevelThanNotice()
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $logger = new InternalLogger($mockLogger, LogLevel::WARNING, [], new Hooks());

        $mockLogger
            ->expects(self::once())
            ->method('emergency')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('alert')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('critical')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('error')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('warning')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('notice')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('info')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('debug')
        ;

        $logger->emergency('');
        $logger->alert('');
        $logger->critical('');
        $logger->error('');
        $logger->notice('');
        $logger->info('');
        $logger->debug('');
        $logger->warning('');
    }

    public function testLoggerLogOnlyHigherLevelThanWarning()
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $logger = new InternalLogger($mockLogger, LogLevel::ERROR, [], new Hooks());

        $mockLogger
            ->expects(self::once())
            ->method('emergency')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('alert')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('critical')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('error')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('warning')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('notice')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('info')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('debug')
        ;

        $logger->emergency('');
        $logger->alert('');
        $logger->critical('');
        $logger->error('');
        $logger->notice('');
        $logger->info('');
        $logger->debug('');
        $logger->warning('');
    }

    public function testLoggerLogOnlyHigherLevelThanError()
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $logger = new InternalLogger($mockLogger, LogLevel::CRITICAL, [], new Hooks());

        $mockLogger
            ->expects(self::once())
            ->method('emergency')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('alert')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('critical')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('error')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('warning')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('notice')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('info')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('debug')
        ;

        $logger->emergency('');
        $logger->alert('');
        $logger->critical('');
        $logger->error('');
        $logger->notice('');
        $logger->info('');
        $logger->debug('');
        $logger->warning('');
    }

    public function testLoggerLogOnlyHigherLevelThanCritical()
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $logger = new InternalLogger($mockLogger, LogLevel::ALERT, [], new Hooks());

        $mockLogger
            ->expects(self::once())
            ->method('emergency')
        ;

        $mockLogger
            ->expects(self::once())
            ->method('alert')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('critical')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('error')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('warning')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('notice')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('info')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('debug')
        ;

        $logger->emergency('');
        $logger->alert('');
        $logger->critical('');
        $logger->error('');
        $logger->notice('');
        $logger->info('');
        $logger->debug('');
        $logger->warning('');
    }

    public function testLoggerLogOnlyHigherLevelThanAlert()
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $logger = new InternalLogger($mockLogger, LogLevel::EMERGENCY, [], new Hooks());

        $mockLogger
            ->expects(self::once())
            ->method('emergency')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('alert')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('critical')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('error')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('warning')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('notice')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('info')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('debug')
        ;

        $logger->emergency('');
        $logger->alert('');
        $logger->critical('');
        $logger->error('');
        $logger->notice('');
        $logger->info('');
        $logger->debug('');
        $logger->warning('');
    }

    public function testLoggerNoLog()
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $logger = new InternalLogger($mockLogger, LogLevel::NO_LOG, [], new Hooks());

        $mockLogger
            ->expects(self::never())
            ->method('emergency')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('alert')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('critical')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('error')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('warning')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('notice')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('info')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('debug')
        ;

        $logger->emergency('');
        $logger->alert('');
        $logger->critical('');
        $logger->error('');
        $logger->notice('');
        $logger->info('');
        $logger->debug('');
        $logger->warning('');
    }

    public function testClientNoLog()
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $client = new ConfigCatClient('not-existing', [
            ClientOptions::LOGGER => $mockLogger,
            ClientOptions::LOG_LEVEL => LogLevel::NO_LOG,
        ]);

        $mockLogger
            ->expects(self::never())
            ->method('emergency')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('alert')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('critical')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('error')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('warning')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('notice')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('info')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('debug')
        ;

        $client->getValue('fake', false);
    }

    public function testLoggerBypassesLogWhenExceptionIsIgnored()
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $logger = new InternalLogger($mockLogger, LogLevel::EMERGENCY, [InvalidArgumentException::class], new Hooks());

        $mockLogger
            ->expects(self::never())
            ->method('emergency')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('alert')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('critical')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('error')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('warning')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('notice')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('info')
        ;

        $mockLogger
            ->expects(self::never())
            ->method('debug')
        ;

        $logger->emergency('', ['exception' => new InvalidArgumentException()]);
        $logger->alert('', ['exception' => new InvalidArgumentException()]);
        $logger->critical('', ['exception' => new InvalidArgumentException()]);
        $logger->error('', ['exception' => new InvalidArgumentException()]);
        $logger->notice('', ['exception' => new InvalidArgumentException()]);
        $logger->info('', ['exception' => new InvalidArgumentException()]);
        $logger->debug('', ['exception' => new InvalidArgumentException()]);
        $logger->warning('', ['exception' => new InvalidArgumentException()]);
    }
}
